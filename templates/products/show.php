<?php
/** @var \App\Products\Product $product */
?>
<h1><?= e($product->name) ?></h1>
<dl class="product-detail">
    <dt>Price</dt><dd><?= e($product->price) ?> USD</dd>
    <dt>Available</dt><dd><?= e((string)$product->quantityAvailable) ?></dd>
</dl>
<?php if ($product->quantityAvailable > 0): ?>
    <p>
        <a class="button" href="<?= e('/products/' . $product->id . '/purchase') ?>">Buy</a>
    </p>
<?php else: ?>
    <p><strong>Out of stock.</strong></p>
<?php endif; ?>
<p><a href="/products">&laquo; Back to list</a></p>
