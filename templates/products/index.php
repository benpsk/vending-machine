<?php
/** @var string $title */
/** @var list<\App\Products\Product> $products */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var string $sort */
/** @var string $dir */
/** @var string $baseUrl */
/** @var bool $showAdminActions */

$sortColumns = [
    'id' => 'ID',
    'name' => 'Name',
    'price' => 'Price',
    'quantity_available' => 'Qty',
];
?>
<h1><?= e($title) ?></h1>

<?php if ($showAdminActions): ?>
    <p><a class="button" href="/admin/products/create">+ New product</a></p>
<?php endif; ?>

<table class="products">
    <thead>
        <tr>
            <?php foreach ($sortColumns as $col => $label): ?>
                <?php
                $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
                $arrow = $sort === $col ? ($dir === 'asc' ? ' ↑' : ' ↓') : '';
                $href = $baseUrl . '?' . http_build_query([
                    'page' => 1,
                    'perPage' => $perPage,
                    'sort' => $col,
                    'dir' => $nextDir,
                ]);
                ?>
                <th><a href="<?= e($href) ?>"><?= e($label) ?><?= e($arrow) ?></a></th>
            <?php endforeach; ?>
            <?php if ($showAdminActions): ?>
                <th>Actions</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if ($products === []): ?>
            <tr><td colspan="<?= e((string)($showAdminActions ? 5 : 4)) ?>"><em>No products yet.</em></td></tr>
        <?php endif; ?>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><?= e((string)$product->id) ?></td>
                <td>
                    <?php $primaryHref = $showAdminActions
                        ? '/admin/products/' . $product->id . '/edit'
                        : '/products/' . $product->id; ?>
                    <a href="<?= e($primaryHref) ?>"><?= e($product->name) ?></a>
                </td>
                <td class="num"><?= e($product->price) ?></td>
                <td class="num"><?= e((string)$product->quantityAvailable) ?></td>
                <?php if ($showAdminActions): ?>
                    <td>
                        <a href="<?= e('/admin/products/' . $product->id . '/edit') ?>">Edit</a>
                        |
                        <a href="<?= e('/admin/products/' . $product->id . '/delete') ?>">Delete</a>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/../partials/pagination.php'; ?>
