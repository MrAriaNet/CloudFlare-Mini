<?php
/** @var array $pagination */
/** @var string $routeName */
$page = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$total = (int) ($pagination['total'] ?? 0);
$perPage = (int) ($pagination['per_page'] ?? 20);
$q = (string) ($q ?? '');
$routeName = (string) ($routeName ?? 'logs');
$perOptions = per_page_options();

if ($total <= 0) {
    return;
}
?>
<div class="pagination-bar">
    <div class="pagination-meta">
        <span class="muted">
            Showing <?= (int) ((($page - 1) * $perPage) + 1) ?>–<?= (int) min($page * $perPage, $total) ?>
            of <?= $total ?>
        </span>
        <form method="get" action="<?= e(url()) ?>" class="per-page-form">
            <?php foreach ($_GET as $key => $value): ?>
                <?php
                if (!is_scalar($value) || $key === 'per' || $key === 'page') {
                    continue;
                }
                ?>
                <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
            <?php endforeach; ?>
            <?php if (!isset($_GET['r'])): ?>
                <input type="hidden" name="r" value="<?= e($routeName) ?>">
            <?php endif; ?>
            <label class="per-page">
                <span>Per page</span>
                <select name="per" onchange="this.form.submit()">
                    <?php foreach ($perOptions as $opt): ?>
                        <option value="<?= (int) $opt ?>" <?= $perPage === (int) $opt ? 'selected' : '' ?>><?= (int) $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-small btn-secondary" href="<?= e(url(pagination_query(['r' => $routeName, 'page' => $page - 1, 'per' => $perPage, 'q' => $q]))) ?>">Previous</a>
            <?php else: ?>
                <span class="btn btn-small btn-secondary" style="opacity:.45;pointer-events:none;">Previous</span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <?php if ($i === $page): ?>
                    <span class="btn btn-small btn-primary"><?= $i ?></span>
                <?php else: ?>
                    <a class="btn btn-small btn-secondary" href="<?= e(url(pagination_query(['r' => $routeName, 'page' => $i, 'per' => $perPage, 'q' => $q]))) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a class="btn btn-small btn-secondary" href="<?= e(url(pagination_query(['r' => $routeName, 'page' => $page + 1, 'per' => $perPage, 'q' => $q]))) ?>">Next</a>
            <?php else: ?>
                <span class="btn btn-small btn-secondary" style="opacity:.45;pointer-events:none;">Next</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
