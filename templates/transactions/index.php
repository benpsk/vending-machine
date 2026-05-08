<?php
/** @var string $title */
/** @var list<\App\Transactions\Transaction> $transactions */
/** @var array<int, \App\Products\Product> $productMap */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var string $baseUrl */
?>
<h1><?= e($title) ?></h1>

<?php if ($transactions === []): ?>
    <p>You haven't bought anything yet. <a href="/products">Browse products</a> to get started.</p>
<?php else: ?>
    <table class="products">
        <thead>
            <tr>
                <th>When</th>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $txn): ?>
                <?php $product = $productMap[$txn->productId] ?? null; ?>
                <tr>
                    <td><?= e($txn->createdAt->format('Y-m-d H:i')) ?></td>
                    <td>
                        <?php if ($product !== null): ?>
                            <a href="<?= e('/products/' . $product->id) ?>"><?= e($product->name) ?></a>
                        <?php else: ?>
                            <span class="muted">(removed)</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= e((string)$txn->quantity) ?></td>
                    <td class="num"><?= e($txn->unitPrice) ?></td>
                    <td class="num"><strong><?= e($txn->totalAmount) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php include __DIR__ . '/../partials/pagination.php'; ?>
<?php endif; ?>
