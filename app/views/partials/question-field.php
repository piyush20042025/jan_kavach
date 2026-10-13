<?php
/**
 * Renders one questionnaire question.
 *
 * Shared by the registration form and the assessment screen so a question
 * added in Settings appears on both without a code change
 * (specification section 18).
 *
 * @var array        $question  row from assessment_questions, with 'options' resolved
 * @var mixed        $current   the recorded answer: scalar, or array for a multiselect
 * @var string|null  $fieldBase name prefix, default 'answers'
 * @var int|null     $colWidth  Bootstrap column width override
 */
$key      = $question['question_key'];
$type     = $question['input_type'];
$required = (int)$question['is_required'] === 1;
$base     = $fieldBase ?? 'answers';
$name     = $base . '[' . $key . ']';
$id       = 'q_' . preg_replace('/[^A-Za-z0-9_]/', '', $key);
$options  = $question['options'] ?? [];

// A question that only opens when another is answered a certain way. The
// condition is evaluated in the browser and re-checked on the server.
$dependsKey    = $question['depends_on_key']   ?? null;
$dependsValue  = $question['depends_on_value'] ?? null;

$width = $colWidth ?? match ($type) {
    'textarea'    => 12,
    'multiselect' => 12,
    default       => 4,
};

$selected = is_array($current)
    ? array_map('strval', $current)
    : array_filter(array_map('trim', explode(',', (string)$current)), fn($v) => $v !== '');
?>
<div class="col-md-<?= (int)$width ?> jk-question"
     data-question="<?= e($key) ?>"
     <?php if ($dependsKey): ?>
         data-depends-on="<?= e($dependsKey) ?>"
         data-depends-value="<?= e($dependsValue ?? '') ?>"
     <?php endif; ?>>

    <label class="form-label" for="<?= e($id) ?>">
        <?= e($question['label']) ?>
        <?php if ($required): ?><span class="req">*</span><?php endif; ?>
        <?php if ((int)($question['feeds_eligibility'] ?? 0) === 1): ?>
            <i class="bi bi-lightning-charge-fill text-warning ms-1" style="font-size:.7rem"
               data-bs-toggle="tooltip"
               title="This answer is used by the scheme matching engine"></i>
        <?php endif; ?>
    </label>

    <?php if ($type === 'multiselect'): ?>
        <!-- Several answers at once; stored as a comma-separated set. -->
        <input type="hidden" name="<?= e($name) ?>[]" value="">
        <div class="jk-checkgrid">
            <?php foreach ($options as $code => $label): ?>
                <?php $optId = $id . '_' . preg_replace('/[^A-Za-z0-9]/', '', (string)$code); ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="<?= e($name) ?>[]"
                           id="<?= e($optId) ?>" value="<?= e($code) ?>"
                           <?= in_array((string)$code, $selected, true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($optId) ?>"><?= e($label) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif ($type === 'select'): ?>
        <select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>"
                data-question-input="<?= e($key) ?>" <?= $required ? 'required' : '' ?>>
            <option value="">Not recorded</option>
            <?php foreach ($options as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= (string)$current === (string)$code ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php elseif ($type === 'radio'): ?>
        <div class="d-flex flex-wrap gap-3 pt-1">
            <?php
            $radioOptions = $options ?: ['Yes' => 'Yes', 'No' => 'No'];
            foreach ($radioOptions as $code => $label):
                if ($code === 'Unknown') continue;
                $optId = $id . '_' . preg_replace('/[^A-Za-z0-9]/', '', (string)$code);
            ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="<?= e($name) ?>"
                           data-question-input="<?= e($key) ?>"
                           id="<?= e($optId) ?>" value="<?= e($code) ?>"
                           <?= (string)$current === (string)$code ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($optId) ?>"><?= e($label) ?></label>
                </div>
            <?php endforeach; ?>
            <button type="button" class="btn btn-sm btn-link p-0 text-muted jk-clear-radio"
                    data-target="<?= e($name) ?>" style="font-size:.74rem">clear</button>
        </div>

    <?php elseif (in_array($type, ['number', 'decimal'], true)): ?>
        <div class="input-group">
            <input type="number" class="form-control" id="<?= e($id) ?>" name="<?= e($name) ?>"
                   data-question-input="<?= e($key) ?>"
                   step="<?= $type === 'decimal' ? '0.01' : '1' ?>"
                   <?= $question['min_value'] !== null ? 'min="' . e($question['min_value']) . '"' : 'min="0"' ?>
                   <?= $question['max_value'] !== null ? 'max="' . e($question['max_value']) . '"' : '' ?>
                   value="<?= e(is_array($current) ? '' : $current) ?>" <?= $required ? 'required' : '' ?>>
            <?php if (!empty($question['unit'])): ?>
                <span class="input-group-text"><?= e($question['unit']) ?></span>
            <?php endif; ?>
        </div>

    <?php elseif ($type === 'date'): ?>
        <input type="date" class="form-control" id="<?= e($id) ?>" name="<?= e($name) ?>"
               data-question-input="<?= e($key) ?>"
               value="<?= e(is_array($current) ? '' : $current) ?>" <?= $required ? 'required' : '' ?>>

    <?php elseif ($type === 'textarea'): ?>
        <textarea class="form-control" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="2"
                  data-question-input="<?= e($key) ?>"
                  <?= $required ? 'required' : '' ?>><?= e(is_array($current) ? '' : $current) ?></textarea>

    <?php elseif ($type === 'checkbox'): ?>
        <div class="form-check pt-1">
            <input type="hidden" name="<?= e($name) ?>" value="">
            <input class="form-check-input" type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>"
                   data-question-input="<?= e($key) ?>" value="Yes"
                   <?= (string)$current === 'Yes' ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($id) ?>">Yes</label>
        </div>

    <?php else: ?>
        <input type="text" class="form-control" id="<?= e($id) ?>" name="<?= e($name) ?>"
               data-question-input="<?= e($key) ?>" maxlength="500"
               value="<?= e(is_array($current) ? implode(', ', $current) : $current) ?>"
               <?= $required ? 'required' : '' ?>>
    <?php endif; ?>

    <?php if (!empty($question['help_text'])): ?>
        <div class="form-text"><?= e($question['help_text']) ?></div>
    <?php endif; ?>
</div>
