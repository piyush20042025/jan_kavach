<?php
/**
 * Rows the import rejected, with the reason for each.
 * @var array $batch, $rows, $counts, $fields
 */
$pageSubtitle = $batch['original_name'] . ' &middot; ' . $batch['batch_code'];

ob_start(); ?>
    <a href="<?= e(url('/import/schemes/' . (int)$batch['id'] . '/preview')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Preview
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle mt-1"></i>
    <div>
        These rows were left out of the import. Correct them in your source file and upload it again &mdash;
        rows that already imported successfully will be recognised as duplicates, so you can safely re-upload
        the whole workbook and skip them.
    </div>
</div>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title">
            <i class="bi bi-x-octagon"></i>Rejected Rows
            <span class="badge bg-danger"><?= count($rows) ?></span>
        </h3>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($rows)): ?>
            <div class="jk-empty">
                <i class="bi bi-check2-circle"></i>
                <h5>No rows were rejected</h5>
                <p>Every row in this batch was readable.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table jk-table--compact">
                    <thead>
                        <tr><th class="num">Row</th><th style="min-width:220px">Scheme Name</th>
                            <th style="min-width:260px">Reason</th><th>Data Read</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $data = json_list($row['raw_data']); ?>
                            <tr>
                                <td class="num text-subtle"><?= (int)$row['row_no'] ?></td>
                                <td class="fw-semibold"><?= e($row['scheme_name'] ?: '(no name supplied)') ?></td>
                                <td class="text-danger small"><?= e($row['message'] ?? 'Unknown error') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-link p-0" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#raw<?= (int)$row['id'] ?>">
                                        Show values
                                    </button>
                                    <div class="collapse mt-2" id="raw<?= (int)$row['id'] ?>">
                                        <div class="jk-dl" style="font-size:.74rem">
                                            <?php foreach ($fields as $key => $label): ?>
                                                <?php
                                                $value = $data[$key] ?? null;
                                                if (is_array($value)) {
                                                    $value = implode(', ', $value);
                                                }
                                                if ($value === null || $value === '') continue;
                                                ?>
                                                <div class="jk-dl__row">
                                                    <div class="jk-dl__key"><?= e($label) ?></div>
                                                    <div class="jk-dl__val"><?= e(str_limit((string)$value, 150)) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
