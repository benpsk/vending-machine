<?php
/** @var array<string, list<string>>|null $errors */
$errors ??= [];
?>
<?php if ($errors !== []): ?>
    <ul class="form-errors" role="alert">
        <?php foreach ($errors as $field => $messages): ?>
            <?php foreach ($messages as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
