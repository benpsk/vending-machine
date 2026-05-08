<?php
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var string $baseUrl */
/** @var string|null $sort */
/** @var string|null $dir */
$sort ??= null;
$dir ??= null;
$lastPage = max(1, (int)ceil($total / max(1, $perPage)));

$buildUrl = static function (int $targetPage) use ($baseUrl, $perPage, $sort, $dir): string {
    $params = ['page' => $targetPage, 'perPage' => $perPage];
    if ($sort !== null) {
        $params['sort'] = $sort;
    }
    if ($dir !== null) {
        $params['dir'] = $dir;
    }
    return $baseUrl . '?' . http_build_query($params);
};
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a rel="prev" href="<?= e($buildUrl($page - 1)) ?>">&laquo; Prev</a>
    <?php else: ?>
        <span class="pagination-disabled">&laquo; Prev</span>
    <?php endif; ?>

    <span class="pagination-info">
        Page <?= e((string)$page) ?> of <?= e((string)$lastPage) ?>
        (<?= e((string)$total) ?> total)
    </span>

    <?php if ($page < $lastPage): ?>
        <a rel="next" href="<?= e($buildUrl($page + 1)) ?>">Next &raquo;</a>
    <?php else: ?>
        <span class="pagination-disabled">Next &raquo;</span>
    <?php endif; ?>
</nav>
