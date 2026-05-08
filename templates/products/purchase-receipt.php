<?php
/** @var \App\Transactions\Transaction $transaction */
/** @var \App\Products\Product $product */
?>
<h1>Thank you for your purchase</h1>

<table class="receipt">
    <tr><th>Transaction</th><td>#<?= e((string)$transaction->id) ?></td></tr>
    <tr><th>Product</th><td><?= e($product->name) ?></td></tr>
    <tr><th>Quantity</th><td><?= e((string)$transaction->quantity) ?></td></tr>
    <tr><th>Unit price</th><td><?= e($transaction->unitPrice) ?> USD</td></tr>
    <tr><th>Total</th><td><strong><?= e($transaction->totalAmount) ?> USD</strong></td></tr>
    <tr><th>Time</th><td><?= e($transaction->createdAt->format('Y-m-d H:i:s')) ?></td></tr>
</table>

<p><a href="/products">&laquo; Back to products</a></p>
