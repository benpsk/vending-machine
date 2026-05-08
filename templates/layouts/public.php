<?php
/** @var string $title */
/** @var \App\Users\User|null $currentUser */
/** @var string|null $csrf */
/** @var string $content */
$currentUser ??= null;
$csrf ??= null;
$content ??= '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script defer src="/assets/js/validation.js"></script>
</head>
<body class="public-area">
    <?php include __DIR__ . '/../partials/nav-public.php'; ?>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
