<?php
/**
 * Socio-economic assessment (specification section 18).
 *
 * The questionnaire is rendered from `assessment_questions`, so adding a
 * question in Settings makes it appear here with no code change.
 *
 * @var array $beneficiary, $sections, $answers, $assessment
 */
$pageSubtitle = $beneficiary['name'] . ' (' . $beneficiary['beneficiary_code'] . ')';

$totalQuestions = 0;
foreach ($sections as $questions) {
    $totalQuestions += count($questions);
}

ob_start(); ?>
    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'])) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Profile
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if ($assessment): ?>
    <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="d-flex gap-2 align-items-start">
            <i class="bi bi-info-circle mt-1"></i>
            <div>
                An assessment was recorded on <strong><?= fmt_date($assessment['assessment_date']) ?></strong>
                <?= !empty($assessment['assessed_by_name']) ? 'by ' . e($assessment['assessed_by_name']) : '' ?>
                (<?= e($assessment['completeness_pct']) ?>% complete).
                Saving again records a fresh assessment and updates the profile the matching engine reads.
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>"
      data-guard id="assessmentForm">
<?= csrf_field() ?>

<div class="row g-3">
<div class="col-lg-9">
    <?php foreach ($sections as $section => $questions): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-clipboard-data"></i><?= e($section) ?></h3>
                <span class="text-muted small"><?= count($questions) ?> question<?= count($questions) === 1 ? '' : 's' ?></span>
            </div>
            <div class="jk-card__body">
                <div class="row g-3">
                    <?php foreach ($questions as $question): ?>
                        <?php
                        // One shared renderer for every question, so a question
                        // added in Settings appears here and on the registration
                        // form without touching either view.
                        $current   = $answers[$question['question_key']] ?? '';
                        $fieldBase = 'answers';
                        $colWidth  = null;
                        include VIEW_PATH . '/partials/question-field.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($sections)): ?>
        <div class="jk-card">
            <div class="jk-empty">
                <i class="bi bi-clipboard-x"></i>
                <h5>No assessment questions are configured</h5>
                <p>An administrator needs to add questions under Settings &rsaquo; Assessment Questions
                   before field teams can record socio-economic information.</p>
                <?php if (can('settings.manage')): ?>
                    <a href="<?= e(url('/settings/assessment-questions')) ?>" class="btn btn-sm btn-primary">
                        Configure questions
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="col-lg-3">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-person"></i>Beneficiary</h3></div>
        <div class="jk-card__body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="jk-avatar"><?= e(initials($beneficiary['name'])) ?></span>
                <div class="min-width-0">
                    <div class="fw-semibold" style="font-size:.86rem"><?= e($beneficiary['name']) ?></div>
                    <div class="text-muted tiny"><?= e($beneficiary['beneficiary_code']) ?></div>
                </div>
            </div>
            <div class="text-muted small">
                <?= e($beneficiary['gender']) ?><?= $beneficiary['age'] !== null ? ', ' . (int)$beneficiary['age'] . ' yrs' : '' ?>
                <br><?= e(implode(', ', array_filter([$beneficiary['village_display'], $beneficiary['district_name']]))) ?>
            </div>

            <hr>

            <div class="mb-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Completeness</span>
                    <span class="fw-semibold" id="completeness">0%</span>
                </div>
                <div class="jk-progress"><span id="completenessBar" style="width:0%"></span></div>
                <div class="text-subtle tiny mt-1">
                    <span id="answeredCount">0</span> of <?= $totalQuestions ?> questions answered
                </div>
            </div>

            <hr>

            <button type="submit" class="btn btn-primary w-100" name="save_complete" value="1">
                <i class="bi bi-check-lg me-1"></i>Complete Assessment
            </button>
            <button type="submit" class="btn btn-outline-secondary w-100 mt-2" name="save_draft" value="1" data-keep-label>
                <i class="bi bi-save me-1"></i>Save as Draft
            </button>

            <p class="text-muted small mt-3 mb-0">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i>
                Completing the assessment runs scheme matching automatically, so the applicable schemes are
                identified straight away.
            </p>
        </div>
    </div>
</div>
</div>
</form>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const form  = document.getElementById('assessmentForm');
  const total = <?= (int)$totalQuestions ?>;

  function answered() {
    const seen = new Set();
    form.querySelectorAll('[name^="answers["]').forEach(function (field) {
      if (field.type === 'radio' && !field.checked) return;
      if (field.type === 'checkbox' && !field.checked) return;
      if (field.value === '' || field.value === null) return;
      seen.add(field.name);
    });
    return seen.size;
  }

  function refresh() {
    const count = answered();
    const pct = total > 0 ? Math.round(count * 100 / total) : 0;
    document.getElementById('completeness').textContent = pct + '%';
    document.getElementById('completenessBar').style.width = pct + '%';
    document.getElementById('answeredCount').textContent = count;
  }

  form.addEventListener('change', refresh);
  form.addEventListener('input', refresh);

  // A radio group has no natural "unset", so give the field worker a way
  // back to "not recorded" once they have answered.
  document.querySelectorAll('.jk-clear-radio').forEach(function (button) {
    button.addEventListener('click', function () {
      form.querySelectorAll('[name="' + button.dataset.target + '"]').forEach(function (radio) {
        radio.checked = false;
      });
      refresh();
    });
  });

  refresh();
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
