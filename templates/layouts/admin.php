<?php
/** @var string $title */
/** @var \App\Users\User|null $currentUser */
/** @var string|null $csrf */
/** @var string $content */
/** @var array<string, mixed> $seo */
$currentUser ??= null;
$csrf ??= null;
$content ??= '';
$seo = is_array($seo ?? null) ? $seo : [];
$seo['title']  = ($title ?? 'Admin') . ' · Admin';
$seo['robots'] = 'noindex,nofollow,noarchive';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Admin</title>
    <?php include __DIR__ . '/../partials/seo.php'; ?>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script defer src="/assets/js/validation.js"></script>
</head>
<body class="admin-area">
    <?php include __DIR__ . '/../partials/nav-admin.php'; ?>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
