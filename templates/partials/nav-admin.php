<?php
/** @var \App\Users\User|null $currentUser */
/** @var string|null $csrf */
$currentUser ??= null;
$csrf ??= null;
?>
<header class="admin-header">
    <nav>
        <a class="brand" href="/admin/products">
            Vending Machine <span class="admin-wordmark">Admin</span>
        </a>
        <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
        <label for="nav-toggle" class="hamburger" aria-label="Toggle menu" role="button"></label>
        <div class="nav-links">
            <a href="/admin/products">Products</a>
            <a href="/admin/transactions">Transactions</a>
            <a class="view-site" href="/" title="Open the public storefront">View site &nearr;</a>
            <?php if ($currentUser !== null): ?>
                <form method="post" action="/logout" class="logout-form">
                    <input type="hidden" name="_token" value="<?= e($csrf ?? '') ?>">
                    <button type="submit">Log out</button>
                </form>
            <?php endif; ?>
        </div>
    </nav>
</header>
