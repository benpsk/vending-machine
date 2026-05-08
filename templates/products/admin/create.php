<?php
/** @var string $title */
/** @var array<string, string> $old */
/** @var array<string, list<string>> $errors */
/** @var string $csrf */
?>
<h1><?= e($title) ?></h1>
<?php include __DIR__ . '/../../partials/form-errors.php'; ?>

<form method="post" action="/admin/products" novalidate data-validate>
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <p>
        <label for="name">Name</label>
        <input id="name" name="name" type="text" required maxlength="100"
               data-rule="required" value="<?= e($old['name']) ?>">
    </p>
    <p>
        <label for="price">Price (USD)</label>
        <input id="price" name="price" type="text" inputmode="decimal" required
               data-rule="required|numeric|min:0.001" value="<?= e($old['price']) ?>">
    </p>
    <p>
        <label for="quantity_available">Quantity</label>
        <input id="quantity_available" name="quantity_available" type="text" inputmode="numeric"
               required data-rule="required|integer|min:0"
               value="<?= e($old['quantity_available']) ?>">
    </p>
    <p>
        <button type="submit">Create</button>
        <a href="/admin/products">Cancel</a>
    </p>
</form>
