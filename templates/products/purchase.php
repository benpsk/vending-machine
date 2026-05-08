<?php
/** @var \App\Products\Product $product */
/** @var string $quantity */
/** @var string|null $error */
/** @var string $csrf */
$error ??= null;
$outOfStock = $product->quantityAvailable === 0;
?>
<h1>Buy <?= e($product->name) ?></h1>
<?php include __DIR__ . '/../partials/flash.php'; ?>

<dl class="product-detail">
    <dt>Price</dt><dd><?= e($product->price) ?> USD</dd>
    <dt>Available</dt><dd><?= e((string)$product->quantityAvailable) ?></dd>
</dl>

<?php if ($outOfStock): ?>
    <p><strong>Out of stock.</strong> Check back later.</p>
    <p><a href="/products">&laquo; Back to list</a></p>
<?php else: ?>
    <form method="post" action="<?= e('/products/' . $product->id . '/purchase') ?>" novalidate data-validate>
        <input type="hidden" name="_token" value="<?= e($csrf) ?>">
        <p>
            <label for="quantity">Quantity</label>
            <input id="quantity" name="quantity" type="text" inputmode="numeric"
                   required data-rule="required|integer|min:1"
                   value="<?= e($quantity) ?>">
        </p>
        <p>
            <button type="submit">Buy</button>
            <a href="<?= e('/products/' . $product->id) ?>">Cancel</a>
        </p>
    </form>
<?php endif; ?>
