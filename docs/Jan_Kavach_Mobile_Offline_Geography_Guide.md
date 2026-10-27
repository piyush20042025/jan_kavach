# Jan Kavach Mobile App — Offline Geography Master Data Sync & Dropdown Architecture

## 1. Overview & Architecture

To enable complete **offline beneficiary registration** in the Jan Kavach mobile application (Android / iOS / Flutter / React Native), field servicemen must be able to synchronize and locally persist the geography hierarchy while online.

When the device switches to offline/airplane mode during field operations:
- State, District, Block, Gram Panchayat, and Village dropdowns populate instantaneously from the local SQLite/Room/ObjectBox/Hive database.
- Online beneficiary registration continues to use standard validation through `POST /beneficiaries`.
- Dependency cascades (e.g. changing State clears District, Block, GP, Village) remain identical in both online and offline modes.

---

## 2. API Specifications & Endpoints

**Base URL:**
`https://jankavach.indevconsultancy.in/public/api/mobile/user`

**Authentication:**
All geography endpoints are protected by `mobile_auth` middleware requiring the Bearer token:
```http
Authorization: Bearer <ACCESS_TOKEN>
Accept: application/json
Content-Type: application/json
```

### Hierarchy Endpoints:

| Level | Method | Endpoint | Description |
|:---|:---|:---|:---|
| **Level 1** | `GET` | `/geography/states` | Fetches all active states (36 States & UTs). |
| **Level 2** | `GET` | `/geography/districts/{stateId}` | Fetches districts belonging to `{stateId}`. |
| **Level 3** | `GET` | `/geography/blocks/{districtId}` | Fetches blocks belonging to `{districtId}`. |
| **Level 4** | `GET` | `/geography/gram-panchayats/{blockId}` | Fetches Gram Panchayats belonging to `{blockId}`. |
| **Level 5a** | `GET` | `/geography/villages/{gpId}` | Fetches villages under Gram Panchayat `{gpId}`. |
| **Level 5b** | `GET` | `/geography/villages-by-block/{blockId}` | Fetches all villages under Block `{blockId}` (labeled with GP name). |

---

## 3. Local SQLite Schema (Mobile Client)

```sql
CREATE TABLE IF NOT EXISTS geo_states (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS geo_districts (
    id INTEGER PRIMARY KEY,
    state_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    FOREIGN KEY (state_id) REFERENCES geo_states (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_geo_districts_state ON geo_districts(state_id);

CREATE TABLE IF NOT EXISTS geo_blocks (
    id INTEGER PRIMARY KEY,
    district_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    FOREIGN KEY (district_id) REFERENCES geo_districts (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_geo_blocks_district ON geo_blocks(district_id);

CREATE TABLE IF NOT EXISTS geo_gram_panchayats (
    id INTEGER PRIMARY KEY,
    block_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    FOREIGN KEY (block_id) REFERENCES geo_blocks (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_geo_gps_block ON geo_gram_panchayats(block_id);

CREATE TABLE IF NOT EXISTS geo_villages (
    id INTEGER PRIMARY KEY,
    block_id INTEGER NOT NULL,
    gram_panchayat_id INTEGER NULL,
    name TEXT NOT NULL,
    area_type TEXT NULL,
    pincode TEXT NULL,
    FOREIGN KEY (block_id) REFERENCES geo_blocks (id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_geo_villages_gp ON geo_villages(gram_panchayat_id);
CREATE INDEX IF NOT EXISTS idx_geo_villages_block ON geo_villages(block_id);

CREATE TABLE IF NOT EXISTS sync_meta (
    meta_key TEXT PRIMARY KEY,
    meta_value TEXT NOT NULL
);
```

---

## 4. Download & Sync Logic (Atomic & Transactional)

```dart
// Dart / Flutter Implementation Reference
class GeographySyncService {
  final ApiClient api;
  final Database db;

  GeographySyncService(this.api, this.db);

  Future<void> syncGeographyHierarchy({
    int? assignedStateId,
    int? assignedDistrictId,
    Function(double progress, String status)? onProgress,
  }) async {
    onProgress?.call(0.05, "Fetching States...");
    final statesResponse = await api.get('/geography/states');
    final List states = statesResponse.data['data'];

    // Filter by assigned user scope if restricted, or sync all
    final targetStates = assignedStateId != null 
        ? states.where((s) => s['id'] == assignedStateId).toList()
        : states;

    await db.transaction((txn) async {
      // Upsert States (Avoid duplicate records via INSERT OR REPLACE)
      for (final s in targetStates) {
        await txn.insert('geo_states', {
          'id': s['id'],
          'name': s['name'],
        }, conflictAlgorithm: ConflictAlgorithm.replace);
      }

      int totalStates = targetStates.length;
      int stateIndex = 0;

      for (final s in targetStates) {
        stateIndex++;
        final stateId = s['id'];
        onProgress?.call(
          0.10 + (0.80 * (stateIndex / totalStates)),
          "Downloading geography for ${s['name']}...",
        );

        // Fetch Districts
        final distResp = await api.get('/geography/districts/$stateId');
        final List districts = distResp.data['data'] ?? [];

        final targetDistricts = assignedDistrictId != null
            ? districts.where((d) => d['id'] == assignedDistrictId).toList()
            : districts;

        for (final d in targetDistricts) {
          await txn.insert('geo_districts', {
            'id': d['id'],
            'state_id': stateId,
            'name': d['name'],
          }, conflictAlgorithm: ConflictAlgorithm.replace);

          final distId = d['id'];
          // Fetch Blocks
          final blockResp = await api.get('/geography/blocks/$distId');
          final List blocks = blockResp.data['data'] ?? [];

          for (final b in blocks) {
            await txn.insert('geo_blocks', {
              'id': b['id'],
              'district_id': distId,
              'name': b['name'],
            }, conflictAlgorithm: ConflictAlgorithm.replace);

            final blockId = b['id'];

            // Fetch Gram Panchayats
            final gpResp = await api.get('/geography/gram-panchayats/$blockId');
            final List gps = gpResp.data['data'] ?? [];

            for (final gp in gps) {
              await txn.insert('geo_gram_panchayats', {
                'id': gp['id'],
                'block_id': blockId,
                'name': gp['name'],
              }, conflictAlgorithm: ConflictAlgorithm.replace);

              // Fetch Villages by GP
              final vResp = await api.get('/geography/villages/${gp['id']}');
              final List villages = vResp.data['data'] ?? [];
              for (final v in villages) {
                await txn.insert('geo_villages', {
                  'id': v['id'],
                  'block_id': blockId,
                  'gram_panchayat_id': gp['id'],
                  'name': v['name'],
                  'area_type': v['area_type'],
                  'pincode': v['pincode'],
                }, conflictAlgorithm: ConflictAlgorithm.replace);
              }
            }

            // Also fetch villages without GP or complete block list
            final vbResp = await api.get('/geography/villages-by-block/$blockId');
            final List vByBlock = vbResp.data['data'] ?? [];
            for (final v in vByBlock) {
              await txn.insert('geo_villages', {
                'id': v['id'],
                'block_id': blockId,
                'name': v['name'],
                'area_type': v['area_type'],
                'pincode': v['pincode'],
              }, conflictAlgorithm: ConflictAlgorithm.ignore);
            }
          }
        }
      }

      // Record last sync time
      await txn.insert('sync_meta', {
        'meta_key': 'geography_last_synced_at',
        'meta_value': DateTime.now().toIso8601String(),
      }, conflictAlgorithm: ConflictAlgorithm.replace);
    });

    onProgress?.call(1.0, "Geography sync completed.");
  }
}
```

---

## 5. Offline Dropdown Provider & Cascade Handling

```dart
class GeographyDropdownProvider extends ChangeNotifier {
  final Database db;
  bool isOnline = true;

  List<Map<String, dynamic>> states = [];
  List<Map<String, dynamic>> districts = [];
  List<Map<String, dynamic>> blocks = [];
  List<Map<String, dynamic>> gramPanchayats = [];
  List<Map<String, dynamic>> villages = [];

  int? selectedStateId;
  int? selectedDistrictId;
  int? selectedBlockId;
  int? selectedGpId;
  int? selectedVillageId;

  GeographyDropdownProvider(this.db);

  Future<void> loadStates() async {
    states = await db.query('geo_states', orderBy: 'name ASC');
    notifyListeners();
  }

  Future<void> onStateChanged(int? stateId) async {
    selectedStateId = stateId;
    selectedDistrictId = null;
    selectedBlockId = null;
    selectedGpId = null;
    selectedVillageId = null;
    districts = [];
    blocks = [];
    gramPanchayats = [];
    villages = [];

    if (stateId != null) {
      districts = await db.query(
        'geo_districts',
        where: 'state_id = ?',
        whereArgs: [stateId],
        orderBy: 'name ASC',
      );
    }
    notifyListeners();
  }

  Future<void> onDistrictChanged(int? districtId) async {
    selectedDistrictId = districtId;
    selectedBlockId = null;
    selectedGpId = null;
    selectedVillageId = null;
    blocks = [];
    gramPanchayats = [];
    villages = [];

    if (districtId != null) {
      blocks = await db.query(
        'geo_blocks',
        where: 'district_id = ?',
        whereArgs: [districtId],
        orderBy: 'name ASC',
      );
    }
    notifyListeners();
  }

  Future<void> onBlockChanged(int? blockId) async {
    selectedBlockId = blockId;
    selectedGpId = null;
    selectedVillageId = null;
    gramPanchayats = [];
    villages = [];

    if (blockId != null) {
      gramPanchayats = await db.query(
        'geo_gram_panchayats',
        where: 'block_id = ?',
        whereArgs: [blockId],
        orderBy: 'name ASC',
      );
      // Fallback: villages by block if GP is optional
      villages = await db.query(
        'geo_villages',
        where: 'block_id = ?',
        whereArgs: [blockId],
        orderBy: 'name ASC',
      );
    }
    notifyListeners();
  }

  Future<void> onGramPanchayatChanged(int? gpId) async {
    selectedGpId = gpId;
    selectedVillageId = null;
    villages = [];

    if (gpId != null) {
      villages = await db.query(
        'geo_villages',
        where: 'gram_panchayat_id = ?',
        whereArgs: [gpId],
        orderBy: 'name ASC',
      );
    } else if (selectedBlockId != null) {
      villages = await db.query(
        'geo_villages',
        where: 'block_id = ?',
        whereArgs: [selectedBlockId],
        orderBy: 'name ASC',
      );
    }
    notifyListeners();
  }

  void onVillageChanged(int? villageId) {
    selectedVillageId = villageId;
    notifyListeners();
  }
}
```
