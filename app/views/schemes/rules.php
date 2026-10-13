<?php
/**
 * Eligibility rule builder (specification section 9).
 *
 * @var array $scheme, $rules, $attributes, $operators, $lookups, $states, $districts
 */
$pageSubtitle = $scheme['name'];

// Attribute metadata the client needs to render the right value control.
$attributeMeta = [];
foreach ($attributes as $group => $items) {
    foreach ($items as $key => $attr) {
        $attributeMeta[$key] = [
            'label'     => $attr['label'],
            'type'      => $attr['data_type'],
            'lookup'    => $attr['lookup_key'],
            'unit'      => $attr['unit'],
            'operators' => $attr['allowed_operators']
                ? array_map('trim', explode(',', $attr['allowed_operators']))
                : array_keys($operators),
            'help'      => $attr['description'],
        ];
    }
}

// Geography lookups are rendered from the masters rather than lookup_options.
$geoOptions = [
    'state_id'    => [],
    'district_id' => [],
];
foreach ($states as $id => $name) {
    $geoOptions['state_id'][(string)$id] = $name;
}
foreach ($districts as $district) {
    $geoOptions['district_id'][(string)$district['id']] = $district['name'] . ' (' . $district['state_name'] . ')';
}

ob_start(); ?>
    <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Scheme
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        <strong>How these rules are used.</strong>
        Conditions in the same group must <em>all</em> hold; separate groups are alternative ways to qualify,
        and the best-scoring group decides the result.
        A <strong>required</strong> condition that fails rules the beneficiary out; other conditions only lower the
        match percentage. When a beneficiary has no value recorded for a condition it counts as
        <em>unknown</em>, which keeps them at &ldquo;Needs Verification&rdquo; rather than wrongly including or
        excluding them.
    </div>
</div>

<div class="row g-3">
<div class="col-xl-8">
    <form method="post" action="<?= e(url('/schemes/' . (int)$scheme['id'] . '/rules')) ?>" id="rulesForm" data-guard>
        <?= csrf_field() ?>

        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-sliders"></i>Eligibility Conditions
                    <span class="badge bg-secondary" id="ruleCount"><?= count($rules) ?></span>
                </h3>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addRuleBtn">
                        <i class="bi bi-plus-lg me-1"></i>Add Condition
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="addGroupBtn"
                            data-bs-toggle="tooltip" title="Add an alternative way to qualify">
                        <i class="bi bi-diagram-2 me-1"></i>Add Group
                    </button>
                </div>
            </div>

            <div class="jk-card__body">
                <div id="rulesContainer"></div>

                <div id="rulesEmpty" class="jk-empty" style="display:none">
                    <i class="bi bi-sliders"></i>
                    <h5>No conditions configured</h5>
                    <p>Until at least one condition is added, this scheme is reported as
                       <strong>Needs Verification</strong> for every beneficiary instead of being matched automatically.</p>
                    <button type="button" class="btn btn-sm btn-primary" id="addFirstRule">
                        <i class="bi bi-plus-lg me-1"></i>Add the first condition
                    </button>
                </div>
            </div>

            <div class="jk-card__foot d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <span class="text-muted small">
                    Saving replaces every condition for this scheme.
                    Existing beneficiary matches are refreshed the next time matching runs.
                </span>
                <div class="d-flex gap-2">
                    <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Rules
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Rule tester -->
<div class="col-xl-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-play-circle"></i>Try the Rules</h3>
        </div>
        <div class="jk-card__body">
            <p class="text-muted small">
                Enter a hypothetical profile to see how the <em>saved</em> rules grade it.
                Save your changes first to test them.
            </p>

            <div class="row g-2" id="testProfile">
                <div class="col-6">
                    <label class="form-label" for="test_age">Age</label>
                    <input type="number" class="form-control form-control-sm" id="test_age"
                           data-attr="age" min="0" max="120" placeholder="e.g. 62">
                </div>
                <div class="col-6">
                    <label class="form-label" for="test_gender">Gender</label>
                    <select class="form-select form-select-sm" id="test_gender" data-attr="gender">
                        <option value="">-</option>
                        <option value="Female">Female</option>
                        <option value="Male">Male</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="test_bpl">BPL Status</label>
                    <select class="form-select form-select-sm" id="test_bpl" data-attr="bpl_status">
                        <option value="">-</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="test_income">Annual Income</label>
                    <input type="number" class="form-control form-control-sm" id="test_income"
                           data-attr="annual_income" min="0" placeholder="e.g. 60000">
                </div>
                <div class="col-6">
                    <label class="form-label" for="test_state">State</label>
                    <select class="form-select form-select-sm" id="test_state" data-attr="state_id">
                        <option value="">-</option>
                        <?php foreach ($states as $id => $name): ?>
                            <option value="<?= (int)$id ?>"
                                <?= (int)($scheme['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="test_area">Rural / Urban</label>
                    <select class="form-select form-select-sm" id="test_area" data-attr="area_type">
                        <option value="">-</option>
                        <option value="Rural">Rural</option>
                        <option value="Urban">Urban</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="test_pregnant">Pregnant</label>
                    <select class="form-select form-select-sm" id="test_pregnant" data-attr="pregnancy_status">
                        <option value="">-</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="test_disability">Has Disability</label>
                    <select class="form-select form-select-sm" id="test_disability" data-attr="has_disability">
                        <option value="">-</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>

            <button type="button" class="btn btn-sm btn-accent w-100 mt-3" id="runTestBtn">
                <i class="bi bi-play-fill me-1"></i>Evaluate
            </button>

            <div id="testResult" class="mt-3"></div>
        </div>
    </div>
</div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const META       = <?= ejs($attributeMeta) ?>;
  const OPERATORS  = <?= ejs($operators) ?>;
  const LOOKUPS    = <?= ejs($lookups) ?>;
  const GEO        = <?= ejs($geoOptions) ?>;
  const GROUPED    = <?= ejs(array_map(fn($items) => array_keys($items), $attributes)) ?>;
  const EXISTING   = <?= ejs(array_map(fn($r) => [
      'rule_group'    => (int)$r['rule_group'],
      'attribute_key' => $r['attribute_key'],
      'operator'      => $r['operator'],
      'value1'        => $r['value1'],
      'value2'        => $r['value2'],
      'value_list'    => $r['value_list'],
      'weight'        => (int)$r['weight'],
      'is_mandatory'  => (int)$r['is_mandatory'] === 1,
      'description'   => $r['description'],
  ], $rules)) ?>;

  const container = document.getElementById('rulesContainer');
  const emptyBox  = document.getElementById('rulesEmpty');
  const countEl   = document.getElementById('ruleCount');
  let index = 0;

  const OPS_NO_VALUE = ['is_set', 'not_set'];
  const OPS_LIST     = ['any_of', 'not_in'];
  const OPS_RANGE    = ['between'];

  function optionsFor(attributeKey) {
    if (GEO[attributeKey]) return GEO[attributeKey];
    const meta = META[attributeKey];
    if (meta && meta.lookup && LOOKUPS[meta.lookup]) return LOOKUPS[meta.lookup];
    if (meta && meta.type === 'bool') return { '1': 'Yes', '0': 'No' };
    return null;
  }

  function attributeSelect(selected) {
    let html = '<option value="">Select a condition&hellip;</option>';
    Object.keys(GROUPED).forEach(function (group) {
      html += '<optgroup label="' + JK.escape(group) + '">';
      GROUPED[group].forEach(function (key) {
        html += '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' +
                JK.escape(META[key].label) + '</option>';
      });
      html += '</optgroup>';
    });
    return html;
  }

  function operatorSelect(attributeKey, selected) {
    const allowed = (META[attributeKey] && META[attributeKey].operators) || Object.keys(OPERATORS);
    let html = '';
    allowed.forEach(function (op) {
      if (!OPERATORS[op]) return;
      html += '<option value="' + op + '"' + (op === selected ? ' selected' : '') + '>' +
              JK.escape(OPERATORS[op]) + '</option>';
    });
    return html || '<option value="eq">Equal To</option>';
  }

  /** Render the value control appropriate to the attribute + operator. */
  function valueControl(row) {
    const attributeKey = row.querySelector('.rule-attr').value;
    const operator     = row.querySelector('.rule-op').value;
    const host         = row.querySelector('.rule-value-host');
    const i            = row.dataset.index;

    const stored = {
      value1: row.dataset.value1 || '',
      value2: row.dataset.value2 || '',
      list:   row.dataset.valueList || ''
    };

    if (!attributeKey) { host.innerHTML = '<div class="form-text">Choose a condition first.</div>'; return; }

    const meta    = META[attributeKey] || {};
    const options = optionsFor(attributeKey);
    const unit    = meta.unit ? '<span class="input-group-text">' + JK.escape(meta.unit) + '</span>' : '';

    if (OPS_NO_VALUE.indexOf(operator) >= 0) {
      host.innerHTML = '<div class="form-text text-muted">No value needed for this operator.</div>';
      return;
    }

    if (OPS_LIST.indexOf(operator) >= 0) {
      if (options) {
        const chosen = stored.list ? stored.list.split(',').map(s => s.trim()) : [];
        let html = '<select class="form-select form-select-sm rule-list" name="rules[' + i + '][value_list][]" multiple size="4">';
        Object.keys(options).forEach(function (code) {
          html += '<option value="' + JK.escape(code) + '"' +
                  (chosen.indexOf(String(code)) >= 0 ? ' selected' : '') + '>' +
                  JK.escape(options[code]) + '</option>';
        });
        host.innerHTML = html + '</select><div class="form-text">Select every acceptable value.</div>';
      } else {
        host.innerHTML = '<input type="text" class="form-control form-control-sm" name="rules[' + i + '][value_list]" ' +
                         'value="' + JK.escape(stored.list) + '" placeholder="Comma-separated values">';
      }
      return;
    }

    if (OPS_RANGE.indexOf(operator) >= 0) {
      host.innerHTML =
        '<div class="input-group input-group-sm">' +
          '<input type="number" step="any" class="form-control" name="rules[' + i + '][value1]" ' +
                 'value="' + JK.escape(stored.value1) + '" placeholder="From">' +
          '<span class="input-group-text">to</span>' +
          '<input type="number" step="any" class="form-control" name="rules[' + i + '][value2]" ' +
                 'value="' + JK.escape(stored.value2) + '" placeholder="To">' + unit +
        '</div>';
      return;
    }

    if (options) {
      let html = '<select class="form-select form-select-sm" name="rules[' + i + '][value1]">' +
                 '<option value="">Select&hellip;</option>';
      Object.keys(options).forEach(function (code) {
        html += '<option value="' + JK.escape(code) + '"' +
                (String(code) === String(stored.value1) ? ' selected' : '') + '>' +
                JK.escape(options[code]) + '</option>';
      });
      host.innerHTML = html + '</select>';
      return;
    }

    const inputType = (meta.type === 'int' || meta.type === 'decimal') ? 'number'
                    : (meta.type === 'date' ? 'date' : 'text');
    host.innerHTML = '<div class="input-group input-group-sm">' +
      '<input type="' + inputType + '"' + (inputType === 'number' ? ' step="any"' : '') +
      ' class="form-control" name="rules[' + i + '][value1]" value="' + JK.escape(stored.value1) + '">' + unit +
      '</div>';
  }

  function addRule(data) {
    data = data || {};
    const i = index++;

    const row = document.createElement('div');
    row.className = 'jk-rule-row' + (data.is_mandatory ? ' is-mandatory' : '');
    row.dataset.index = i;
    row.dataset.value1 = data.value1 || '';
    row.dataset.value2 = data.value2 || '';
    row.dataset.valueList = data.value_list || '';

    row.innerHTML =
      '<div class="row g-2 align-items-start">' +
        '<div class="col-md-4">' +
          '<label class="form-label">Condition</label>' +
          '<select class="form-select form-select-sm rule-attr" name="rules[' + i + '][attribute_key]">' +
            attributeSelect(data.attribute_key) +
          '</select>' +
          '<div class="form-text rule-help"></div>' +
        '</div>' +
        '<div class="col-md-3">' +
          '<label class="form-label">Operator</label>' +
          '<select class="form-select form-select-sm rule-op" name="rules[' + i + '][operator]">' +
            operatorSelect(data.attribute_key, data.operator) +
          '</select>' +
        '</div>' +
        '<div class="col-md-5">' +
          '<label class="form-label">Value</label>' +
          '<div class="rule-value-host"></div>' +
        '</div>' +
        '<div class="col-md-2">' +
          '<label class="form-label">Group</label>' +
          '<input type="number" min="1" max="20" class="form-control form-control-sm rule-group" ' +
                 'name="rules[' + i + '][rule_group]" value="' + (data.rule_group || 1) + '">' +
        '</div>' +
        '<div class="col-md-2">' +
          '<label class="form-label">Weight</label>' +
          '<input type="number" min="1" max="10" class="form-control form-control-sm" ' +
                 'name="rules[' + i + '][weight]" value="' + (data.weight || 1) + '">' +
        '</div>' +
        '<div class="col-md-3 d-flex align-items-center" style="padding-top:1.6rem">' +
          '<div class="form-check">' +
            '<input class="form-check-input rule-mandatory" type="checkbox" id="mand' + i + '" ' +
                   'name="rules[' + i + '][is_mandatory]" value="1"' + (data.is_mandatory ? ' checked' : '') + '>' +
            '<label class="form-check-label" for="mand' + i + '">Required</label>' +
          '</div>' +
        '</div>' +
        '<div class="col-md-4">' +
          '<label class="form-label">Note (shown in match reasons)</label>' +
          '<input type="text" class="form-control form-control-sm" maxlength="500" ' +
                 'name="rules[' + i + '][description]" value="' + JK.escape(data.description || '') + '">' +
        '</div>' +
        '<div class="col-md-1 d-flex align-items-center justify-content-end" style="padding-top:1.6rem">' +
          '<button type="button" class="btn btn-sm btn-outline-danger rule-remove" aria-label="Remove condition">' +
            '<i class="bi bi-trash"></i></button>' +
        '</div>' +
      '</div>';

    container.appendChild(row);

    const attrSelect = row.querySelector('.rule-attr');
    const opSelect   = row.querySelector('.rule-op');
    const helpEl     = row.querySelector('.rule-help');

    function syncHelp() {
      const meta = META[attrSelect.value];
      helpEl.textContent = meta && meta.help ? meta.help : '';
    }

    attrSelect.addEventListener('change', function () {
      opSelect.innerHTML = operatorSelect(attrSelect.value, opSelect.value);
      row.dataset.value1 = ''; row.dataset.value2 = ''; row.dataset.valueList = '';
      valueControl(row);
      syncHelp();
    });
    opSelect.addEventListener('change', function () { valueControl(row); });

    row.querySelector('.rule-mandatory').addEventListener('change', function () {
      row.classList.toggle('is-mandatory', this.checked);
    });
    row.querySelector('.rule-remove').addEventListener('click', function () {
      row.remove();
      refresh();
    });

    valueControl(row);
    syncHelp();
    refresh();
  }

  function refresh() {
    const rows = container.querySelectorAll('.jk-rule-row');
    countEl.textContent = rows.length;
    emptyBox.style.display = rows.length === 0 ? '' : 'none';
  }

  document.getElementById('addRuleBtn').addEventListener('click', function () { addRule(); });
  document.getElementById('addFirstRule').addEventListener('click', function () { addRule(); });

  document.getElementById('addGroupBtn').addEventListener('click', function () {
    // A new group starts one above whatever is already in use.
    let max = 0;
    container.querySelectorAll('.rule-group').forEach(function (input) {
      max = Math.max(max, parseInt(input.value, 10) || 1);
    });
    addRule({ rule_group: max + 1 });
  });

  EXISTING.forEach(addRule);
  refresh();

  // ---- rule tester -------------------------------------------------
  document.getElementById('runTestBtn').addEventListener('click', function () {
    const profile = {};
    document.querySelectorAll('#testProfile [data-attr]').forEach(function (field) {
      if (field.value !== '') profile[field.dataset.attr] = field.value;
    });

    const result = document.getElementById('testResult');
    result.innerHTML = '<div class="text-center text-muted small py-2">' +
                       '<span class="spinner-border spinner-border-sm me-1"></span>Evaluating&hellip;</div>';

    const body = new FormData();
    body.append(JK.config.csrfName, JK.config.csrfToken);
    Object.keys(profile).forEach(function (key) { body.append('profile[' + key + ']', profile[key]); });

    JK.request('/schemes/<?= (int)$scheme['id'] ?>/rules/test', { method: 'POST', body: body })
      .then(function (response) {
        const d = response.data || {};
        const variant = { 'Eligible': 'success', 'Potentially Eligible': 'warning',
                          'Needs Verification': 'info', 'Not Eligible': 'danger' }[d.eligibility_status] || 'secondary';

        let html = '<div class="alert alert-' + variant + ' py-2 px-2 mb-2">' +
                   '<div class="d-flex justify-content-between align-items-center">' +
                   '<strong>' + JK.escape(d.eligibility_status) + '</strong>' +
                   '<span class="fw-bold">' + d.match_percent + '%</span></div>' +
                   '<div class="small mt-1">' + JK.escape(d.reason_text || '') + '</div></div>';

        html += '<div class="small text-muted mb-2">' +
                d.counts.passed + ' passed &middot; ' + d.counts.failed + ' failed &middot; ' +
                d.counts.unknown + ' unknown (of ' + d.counts.total + ')</div>';

        function list(items, cls, title) {
          if (!items || items.length === 0) return '';
          let out = '<div class="fw-semibold small mt-2">' + title + '</div><ul class="jk-reason-list ' + cls + '">';
          items.forEach(function (item) { out += '<li>' + JK.escape(item.message) + '</li>'; });
          return out + '</ul>';
        }
        html += list(d.passed,  'jk-reason-list--pass',    'Conditions met');
        html += list(d.failed,  'jk-reason-list--fail',    'Conditions not met');
        html += list(d.unknown, 'jk-reason-list--unknown', 'Not yet recorded');

        result.innerHTML = html;
      })
      .catch(function () {
        result.innerHTML = '<div class="alert alert-danger py-2 px-2 mb-0 small">The test could not be run.</div>';
      });
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
