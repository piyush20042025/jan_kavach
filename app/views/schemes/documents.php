<?php
/**
 * Required documents for a scheme (specification section 10).
 *
 * @var array $scheme, $documentTypes, $selected  (keyed by document_type_id)
 */
$pageSubtitle = $scheme['name'];

$byCategory = [];
foreach ($documentTypes as $type) {
    $byCategory[$type['category']][] = $type;
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
        Tick every document this scheme needs. Marking one <strong>mandatory</strong> makes it count towards the
        &ldquo;missing documents&rdquo; figure shown against each beneficiary match, so field teams know exactly
        what still has to be collected before an application can be made.
    </div>
</div>

<form method="post" action="<?= e(url('/schemes/' . (int)$scheme['id'] . '/documents')) ?>" data-guard>
<?= csrf_field() ?>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title">
            <i class="bi bi-file-earmark-text"></i>Document Requirements
            <span class="badge bg-secondary" id="docCount"><?= count($selected) ?></span>
        </h3>
        <div class="d-flex gap-2 align-items-center">
            <input type="search" class="form-control form-control-sm" id="docSearch"
                   placeholder="Filter documents&hellip;" style="width:210px">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllDocs">Clear all</button>
        </div>
    </div>

    <div class="jk-card__body">
        <?php foreach ($byCategory as $category => $types): ?>
            <div class="doc-category mb-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h4 class="h6 mb-0 text-primary"><?= e($category) ?></h4>
                    <span class="flex-grow-1 border-bottom"></span>
                </div>

                <div class="row g-2">
                    <?php foreach ($types as $type): ?>
                        <?php
                        $typeId = (int)$type['id'];
                        $linked = $selected[$typeId] ?? null;
                        $isOn   = $linked !== null;
                        ?>
                        <div class="col-md-6 doc-item" data-name="<?= e(mb_strtolower($type['name'])) ?>">
                            <div class="border rounded p-2 h-100 <?= $isOn ? 'border-primary bg-light' : '' ?>"
                                 id="docBox<?= $typeId ?>">
                                <div class="form-check">
                                    <input class="form-check-input doc-toggle" type="checkbox"
                                           name="document_type_id[]" value="<?= $typeId ?>"
                                           id="doc<?= $typeId ?>" data-type-id="<?= $typeId ?>"
                                           <?= $isOn ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="doc<?= $typeId ?>">
                                        <?= e($type['name']) ?>
                                    </label>
                                    <?php if (!empty($type['description'])): ?>
                                        <div class="form-text mb-0"><?= e(str_limit($type['description'], 90)) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="doc-options mt-2 ps-4" style="<?= $isOn ? '' : 'display:none' ?>">
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_mandatory[]"
                                                   value="<?= $typeId ?>" id="mand<?= $typeId ?>"
                                                   <?= $isOn && (int)$linked['is_mandatory'] === 1 ? 'checked' : (!$isOn ? 'checked' : '') ?>>
                                            <label class="form-check-label small" for="mand<?= $typeId ?>">Mandatory</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="validity_required[]"
                                                   value="<?= $typeId ?>" id="val<?= $typeId ?>"
                                                   <?= $isOn
                                                        ? ((int)$linked['validity_required'] === 1 ? 'checked' : '')
                                                        : ((int)$type['validity_required'] === 1 ? 'checked' : '') ?>>
                                            <label class="form-check-label small" for="val<?= $typeId ?>">Validity dates matter</label>
                                        </div>
                                    </div>
                                    <input type="text" class="form-control form-control-sm mt-2"
                                           name="remarks[<?= $typeId ?>]" maxlength="500"
                                           value="<?= e($linked['remarks'] ?? '') ?>"
                                           placeholder="Remarks for the field team (optional)">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div id="docNoResults" class="text-center text-muted py-3" style="display:none">
            No document type matched that filter.
        </div>
    </div>

    <div class="jk-card__foot d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span class="text-muted small">
            Need a document type that is not listed?
            <?php if (can('master.manage')): ?>
                <a href="<?= e(url('/masters/document-types')) ?>">Add it to the Document Master</a>.
            <?php else: ?>
                Ask an administrator to add it to the Document Master.
            <?php endif; ?>
        </span>
        <div class="d-flex gap-2">
            <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Documents</button>
        </div>
    </div>
</div>
</form>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const countEl = document.getElementById('docCount');

  function refreshCount() {
    countEl.textContent = document.querySelectorAll('.doc-toggle:checked').length;
  }

  document.querySelectorAll('.doc-toggle').forEach(function (toggle) {
    toggle.addEventListener('change', function () {
      const id = toggle.dataset.typeId;
      const box = document.getElementById('docBox' + id);
      const options = box.querySelector('.doc-options');
      options.style.display = toggle.checked ? '' : 'none';
      box.classList.toggle('border-primary', toggle.checked);
      box.classList.toggle('bg-light', toggle.checked);
      refreshCount();
    });
  });

  document.getElementById('clearAllDocs').addEventListener('click', function () {
    document.querySelectorAll('.doc-toggle:checked').forEach(function (toggle) {
      toggle.checked = false;
      toggle.dispatchEvent(new Event('change'));
    });
  });

  // Filter the list as the administrator types.
  const search = document.getElementById('docSearch');
  search.addEventListener('input', function () {
    const term = search.value.trim().toLowerCase();
    let visible = 0;

    document.querySelectorAll('.doc-item').forEach(function (item) {
      const match = term === '' || item.dataset.name.indexOf(term) >= 0;
      item.style.display = match ? '' : 'none';
      if (match) visible++;
    });

    // Hide a category heading whose documents are all filtered out.
    document.querySelectorAll('.doc-category').forEach(function (category) {
      const shown = category.querySelectorAll('.doc-item:not([style*="none"])').length;
      category.style.display = shown > 0 ? '' : 'none';
    });

    document.getElementById('docNoResults').style.display = visible === 0 ? '' : 'none';
  });

  refreshCount();
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
