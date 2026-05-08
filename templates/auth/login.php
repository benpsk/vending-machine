<?php
/** @var string $csrf */
/** @var string|null $error */
/** @var string $username */
/** @var string $next */
$error ??= null;
$username ??= '';
$next ??= '/';
?>
<h1>Sign in</h1>
<?php include __DIR__ . '/../partials/flash.php'; ?>
<form method="post" action="/login" novalidate>
    <input type="hidden" name="_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <p>
        <label for="username">Username</label>
        <input id="username" name="username" type="text" autocomplete="username"
               required value="<?= e($username) ?>">
    </p>
    <p>
        <label for="password">Password</label>
        <input id="password" name="password" type="password"
               autocomplete="current-password" required>
    </p>
    <p>
        <button type="submit">Sign in</button>
    </p>
</form>
