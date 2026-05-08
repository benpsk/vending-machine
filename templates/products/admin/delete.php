<?php
/** @var \App\Products\Product $product */
/** @var string $csrf */
?>
<h1>Delete <?= e($product->name) ?>?</h1>
<p>This will remove the product permanently.</p>
<form method="post" action="<?= e('/admin/products/' . $product->id . '/delete') ?>">
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <button type="submit">Delete</button>
    <a href="/admin/products">Cancel</a>
</form>
