<?php
/** @var \App\Users\User|null $currentUser */
$currentUser ??= null;
?>
<h1>Vending Machine</h1>
<p>A small PHP app for managing products and processing purchases. Web UI for humans, REST API for machines.</p>

<p class="cta-row">
    <a class="button" href="/products">Browse products</a>
    <?php if ($currentUser === null): ?>
        <a class="button secondary" href="/login">Log in</a>
    <?php endif; ?>
</p>
