<?php
/**
 * Listing footer: record summary, page-size chooser and page links.
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var string $recordNoun
 */
$noun = $recordNoun ?? 'record';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="text-muted small"><?= e($paginator->summary($noun)) ?></div>

    <div class="d-flex align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-1 m-0">
            <?php
            // Carry the current filters through the page-size change.
            foreach (($_GET ?? []) as $key => $value) {
                if (in_array($key, ['per_page', 'page'], true) || is_array($value)) {
                    continue;
                }
                echo '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
            }
            ?>
            <label for="perPage" class="text-muted small mb-0">Show</label>
            <select name="per_page" id="perPage" class="form-select form-select-sm"
                    style="width:auto" data-auto-submit>
                <?php foreach (config('pagination.per_page_options', [10, 25, 50, 100]) as $size): ?>
                    <option value="<?= (int)$size ?>" <?= $paginator->perPage === (int)$size ? 'selected' : '' ?>>
                        <?= (int)$size ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <?= $paginator->render() ?>
    </div>
</div>
