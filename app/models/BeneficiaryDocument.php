<?php
namespace JanKavach\Models;

use JanKavach\Core\Model;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Libraries\FileUploader;

/**
 * Beneficiary document management (specification section 19).
 */
class BeneficiaryDocument extends Model
{
    protected string $table = 'beneficiary_documents';

    protected array $fillable = [
        'beneficiary_id', 'document_type_id', 'document_number', 'issue_date', 'expiry_date',
        'file_path', 'original_name', 'stored_name', 'file_size', 'mime_type', 'file_hash',
        'verification_status', 'verified_by', 'verified_at', 'rejection_reason', 'remarks',
        'uploaded_by',
    ];

    public function findDetailed(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT bd.*, dt.name AS document_name, dt.code AS document_code, dt.category,
                    dt.is_sensitive, b.id AS beneficiary_id, b.name AS beneficiary_name,
                    b.beneficiary_code, b.state_id, b.district_id, b.field_user_id,
                    vu.full_name AS verified_by_name, uu.full_name AS uploaded_by_name
               FROM beneficiary_documents bd
               JOIN document_types dt ON dt.id = bd.document_type_id
               JOIN beneficiaries b   ON b.id  = bd.beneficiary_id
               LEFT JOIN users vu ON vu.id = bd.verified_by
               LEFT JOIN users uu ON uu.id = bd.uploaded_by
              WHERE bd.id = :id',
            ['id' => $id]
        );
    }

    /**
     * Which mandatory documents does this beneficiary still owe, across all
     * their positively-matched schemes?
     */
    public function outstandingFor(int $beneficiaryId): array
    {
        return $this->db->select(
            "SELECT dt.id, dt.name, dt.code, dt.category, dt.description,
                    GROUP_CONCAT(DISTINCT COALESCE(s.short_name, s.name) ORDER BY s.name SEPARATOR ', ') AS required_by
               FROM beneficiary_scheme_matches m
               JOIN schemes s          ON s.id = m.scheme_id
               JOIN scheme_documents sd ON sd.scheme_id = s.id AND sd.is_mandatory = 1
               JOIN document_types dt   ON dt.id = sd.document_type_id
              WHERE m.beneficiary_id = :id
                AND m.eligibility_status IN ('Eligible','Potentially Eligible')
                AND NOT EXISTS (
                      SELECT 1 FROM beneficiary_documents bd
                       WHERE bd.beneficiary_id = :id2
                         AND bd.document_type_id = dt.id
                         AND bd.verification_status <> 'Rejected')
              GROUP BY dt.id
              ORDER BY dt.sort_order, dt.name",
            ['id' => $beneficiaryId, 'id2' => $beneficiaryId]
        );
    }

    /** Documents on file, with the schemes each one unlocks. */
    public function heldBy(int $beneficiaryId): array
    {
        return $this->db->select(
            'SELECT bd.*, dt.name AS document_name, dt.code AS document_code,
                    dt.category, dt.is_sensitive, dt.validity_required,
                    vu.full_name AS verified_by_name, uu.full_name AS uploaded_by_name,
                    (SELECT COUNT(DISTINCT sd.scheme_id) FROM scheme_documents sd
                      WHERE sd.document_type_id = bd.document_type_id) AS used_by_schemes
               FROM beneficiary_documents bd
               JOIN document_types dt ON dt.id = bd.document_type_id
               LEFT JOIN users vu ON vu.id = bd.verified_by
               LEFT JOIN users uu ON uu.id = bd.uploaded_by
              WHERE bd.beneficiary_id = :id
              ORDER BY dt.sort_order, bd.created_at DESC',
            ['id' => $beneficiaryId]
        );
    }

    /** Verification queue across the user's geography. */
    public function pendingVerification(array $filters, int $page, int $perPage, array $queryString = []): Paginator
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL', "bd.verification_status = 'Pending'"];

        if (!empty($filters['state_id'])) {
            $where[] = 'b.state_id = :f_state';
            $bindings['f_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'b.district_id = :f_district';
            $bindings['f_district'] = (int)$filters['district_id'];
        }
        if (!empty($filters['document_type_id'])) {
            $where[] = 'bd.document_type_id = :f_type';
            $bindings['f_type'] = (int)$filters['document_type_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(b.name LIKE :s1 OR b.beneficiary_code LIKE :s2)';
            $bindings['s1'] = '%' . $filters['search'] . '%';
            $bindings['s2'] = '%' . $filters['search'] . '%';
        }

        $select = 'SELECT bd.*, dt.name AS document_name, dt.is_sensitive,
                          b.name AS beneficiary_name, b.beneficiary_code, b.mobile,
                          d.name AS district_name, uu.full_name AS uploaded_by_name
                     FROM beneficiary_documents bd
                     JOIN document_types dt ON dt.id = bd.document_type_id
                     JOIN beneficiaries b   ON b.id  = bd.beneficiary_id
                     LEFT JOIN districts d  ON d.id  = b.district_id
                     LEFT JOIN users uu     ON uu.id = bd.uploaded_by';
        $from   = 'FROM beneficiary_documents bd
                     JOIN document_types dt ON dt.id = bd.document_type_id
                     JOIN beneficiaries b   ON b.id  = bd.beneficiary_id
                     LEFT JOIN districts d  ON d.id  = b.district_id
                     LEFT JOIN users uu     ON uu.id = bd.uploaded_by';

        return $this->paginateQuery($select, $from, implode(' AND ', $where), $bindings,
                                    'bd.created_at DESC', $page, $perPage, $queryString);
    }

    public function verify(int $documentId, string $status, ?int $userId, ?string $reason = null): bool
    {
        if (!in_array($status, ['Verified', 'Rejected', 'Pending'], true)) {
            return false;
        }
        $this->db->update($this->table, [
            'verification_status' => $status,
            'verified_by'         => $status === 'Pending' ? null : $userId,
            'verified_at'         => $status === 'Pending' ? null : date('Y-m-d H:i:s'),
            'rejection_reason'    => $status === 'Rejected' ? $reason : null,
        ], ['id' => $documentId]);

        return true;
    }

    /** Remove the row and the file behind it. */
    public function deleteWithFile(int $documentId): bool
    {
        $document = $this->find($documentId);
        if (!$document) {
            return false;
        }
        FileUploader::delete($document['file_path'] ?? null);
        return $this->db->delete($this->table, ['id' => $documentId]) > 0;
    }

    /** Documents whose validity has lapsed or is about to. */
    public function expiring(int $withinDays = 60): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $bindings['days'] = $withinDays;

        return $this->db->select(
            'SELECT bd.id, bd.expiry_date, dt.name AS document_name,
                    b.id AS beneficiary_id, b.name AS beneficiary_name, b.beneficiary_code,
                    d.name AS district_name,
                    DATEDIFF(bd.expiry_date, CURDATE()) AS days_left
               FROM beneficiary_documents bd
               JOIN document_types dt ON dt.id = bd.document_type_id
               JOIN beneficiaries b   ON b.id  = bd.beneficiary_id
               LEFT JOIN districts d  ON d.id  = b.district_id
              WHERE ' . $scopeSql . ' AND b.deleted_at IS NULL
                AND bd.expiry_date IS NOT NULL
                AND bd.expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              ORDER BY bd.expiry_date LIMIT 200',
            $bindings
        );
    }

    /** Has the beneficiary already uploaded this document type? */
    public function existingOfType(int $beneficiaryId, int $documentTypeId): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM beneficiary_documents
              WHERE beneficiary_id = :b AND document_type_id = :t
              ORDER BY created_at DESC LIMIT 1',
            ['b' => $beneficiaryId, 't' => $documentTypeId]
        );
    }
}
