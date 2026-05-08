<?php
/** @var string $title */
/** @var list<\App\Transactions\Transaction> $transactions */
/** @var array<int, \App\Products\Product> $productMap */
/** @var array<int, \App\Users\User> $userMap */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
/** @var string $baseUrl */
?>
<h1><?= e($title) ?></h1>

<?php if ($transactions === []): ?>
    <p>No transactions recorded yet.</p>
<?php else: ?>
    <table class="products">
        <thead>
            <tr>
                <th>ID</th>
                <th>When</th>
                <th>User</th>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $txn): ?>
                <?php
                $product = $productMap[$txn->productId] ?? null;
                $buyer = $userMap[$txn->userId] ?? null;
                ?>
                <tr>
                    <td>#<?= e((string)$txn->id) ?></td>
                    <td><?= e($txn->createdAt->format('Y-m-d H:i')) ?></td>
                    <td>
                        <?php if ($buyer !== null): ?>
                            <?= e($buyer->username) ?>
                        <?php else: ?>
                            <span class="muted">(deleted)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($product !== null): ?>
                            <?= e($product->name) ?>
                        <?php else: ?>
                            <span class="muted">(deleted)</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= e((string)$txn->quantity) ?></td>
                    <td class="num"><?= e($txn->unitPrice) ?></td>
                    <td class="num"><strong><?= e($txn->totalAmount) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php include __DIR__ . '/../../partials/pagination.php'; ?>
<?php endif; ?>
