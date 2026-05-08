<?php
/** @var \App\Users\User|null $currentUser */
/** @var string|null $csrf */
$currentUser ??= null;
$csrf ??= null;
?>
<header>
    <nav>
        <a class="brand" href="/">Vending Machine</a>
        <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
        <label for="nav-toggle" class="hamburger" aria-label="Toggle menu" role="button"></label>
        <div class="nav-links">
            <a href="/products">Products</a>
            <?php if ($currentUser !== null): ?>
                <a href="/purchases">My purchases</a>
                <form method="post" action="/logout" class="logout-form">
                    <input type="hidden" name="_token" value="<?= e($csrf ?? '') ?>">
                    <button type="submit">Log out</button>
                </form>
            <?php else: ?>
                <a href="/login">Log in</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
