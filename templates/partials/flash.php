<?php
/** @var string|null $error */
/** @var string|null $success */
$error ??= null;
$success ??= null;
?>
<?php if ($error !== null && $error !== ''): ?>
    <div role="alert" class="flash flash-error"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success !== null && $success !== ''): ?>
    <div role="status" class="flash flash-success"><?= e($success) ?></div>
<?php endif; ?>
