<?php $user = currentUser(); ?>

<!doctype html>
<html lang="id">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= e($GLOBALS['pageTitle']) ?> · Kopipiro</title>
        <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
        <script src="<?= url('assets/js/app.js') ?>" defer></script>
    </head>

    <body>
        <a class="skip" href="#main">Langsung ke konten</a>
        <header class="site-header">
            <a class="brand" href="<?= url() ?>">
                <span class="brand-icon" aria-hidden="true">☕</span> kopipiro<span
                    class="brand-dot">.</span>
            </a>
            <nav aria-label="Navigasi utama">
                <a href="<?= url('products.php') ?>">Menu</a>

                <?php if ($user): ?>
                    <a href="<?= url(roleHome($user)) ?>"><?= $user['role'] === 'customer' ? 'Belanja' : 'Dashboard' ?></a>

                    <?php if ($user['role'] === 'customer'): ?>
                        <a href="<?= url('customer/orders.php') ?>">Pesanan saya</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?= url('auth/login.php') ?>">Masuk</a>
                <?php endif; ?>

                <a class="cart-link" href="<?= url('cart/index.php') ?>">Keranjang <span><?= array_sum($_SESSION['cart'] ?? []) ?>
                    </span>
                </a>

                <?php if ($user): ?>
                    <form class="inline" method="post" action="<?= url('auth/logout.php') ?>">

                        <?= csrfField() ?>
                        <button class="text-button" type="submit">Keluar</button>
                    </form>
                <?php endif; ?>

            </nav>
        </header>

        <?php if (($user['role'] ?? '') === 'owner'): ?>
            <nav class="subnav" aria-label="Pengelolaan">
                <a href="<?= url('admin/dashboard.php') ?>">Ringkasan</a>
                <a href="<?= url('admin/products.php') ?>">Produk</a>
                <a href="<?= url('admin/categories.php') ?>">Kategori</a>
                <a href="<?= url('admin/orders.php') ?>">Pesanan</a>
                <a href="<?= url('admin/cashiers.php') ?>">Kasir</a>
            </nav>
        <?php endif; ?>

        <main id="main" class="container">

            <?php if (isset($_SESSION['flash'])): ?>
                <div class="notice" role="status"><?= e($_SESSION['flash']) ?></div>

                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
