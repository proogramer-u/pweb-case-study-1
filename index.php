<?php
require_once __DIR__ . '/includes/bootstrap.php';

$search = input('q', $_GET);
$category = input('category', $_GET);
$sql =
    '
        SELECT
            p.*,
            c.name category_name,
            r.average_rating,
            r.review_count
        FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT
                    product_id,
                    AVG(rating) average_rating,
                    COUNT(*) review_count
                FROM reviews
                GROUP BY product_id
            ) r ON r.product_id = p.id
        WHERE
            p.is_available = 1
    ';
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($category !== '') {
    $sql .= ' AND p.category_id=?';
    $params[] = $category;
}

$products = db($sql . ' ORDER BY p.id', $params)->fetchAll();
$categories = db('SELECT * FROM categories ORDER BY name')->fetchAll();
pageStart('Kopi pilihan, teman harian');
?>

<?php if (!isset($catalogOnly) && $search === '' && $category === ''): ?>
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">Dibuat dengan hati, dinikmati setiap hari.</p>
            <h1>Secangkir kopi.
                <br>Sejuta cerita.
            </h1>
            <p>Dari tegukan pertama sampai obrolan terakhir. Temukan teman ngopi favoritmu di Kopipiro.
            </p>
            <a class="button" href="#menu">Pilih menu favorit</a>
        </div>
        <img src="<?= url('assets/images/hero.jpeg') ?>" alt="Kopi pilihan untuk menemani harimu">
    </section>
<?php endif; ?>

<section id="menu">
    <div class="section-heading">
        <div>
            <h2>Temukan favoritmu</h2>
            <p class="muted">Kopi, minuman segar, dan teman ngemil.</p>
        </div>
        <span class="muted"><?= count($products) ?> menu tersedia</span>
    </div>
    <form method="get" class="filters" action="<?= url('products.php') ?>">
        <label class="sr-only" for="q">Cari menu</label>
        <input id="q" name="q" type="search" maxlength="150" placeholder="Cari kopi atau camilan…"
            value="<?= e($search) ?>">
        <label class="sr-only" for="category">Kategori</label>
        <select id="category" name="category">
            <option value="">Semua kategori</option>

            <?php foreach ($categories as $categoryItem): ?>
                <option value="<?= $categoryItem['id'] ?>" <?= $category === (string) $categoryItem['id'] ? 'selected' : '' ?>><?= e($categoryItem['name']) ?></option>
            <?php endforeach; ?>

        </select>
        <button type="submit">Cari menu</button>
    </form>
    <div class="chips">
        <a class="chip <?= $category === '' ? 'active' : '' ?>" href="<?= url('products.php?' . http_build_query(['q' => $search])) ?>">Semua menu</a>

        <?php foreach ($categories as $categoryItem): ?>
            <a
                class="chip <?= $category === (string) $categoryItem['id'] ? 'active' : '' ?>"
                href="<?= url('products.php?' . http_build_query(['q' => $search, 'category' => $categoryItem['id']])) ?>"
            >
                <?= e($categoryItem['name']) ?>
            </a>
        <?php endforeach; ?>

    </div>

    <?php if (!$products): ?>
        <div class="empty">
            <h3>Belum ada menu yang cocok.</h3>
            <p>Coba kata kunci atau kategori lain.</p>
            <a href="<?= url('products.php') ?>">Lihat semua menu</a>
        </div>
    <?php endif; ?>

    <div class="grid">

        <?php foreach ($products as $product): ?>
            <article class="product-card">
                <img loading="lazy" src="<?= imageUrl($product['image'], $product['name']) ?>" alt="<?= e($product['name']) ?>">
                <div class="product-body">
                    <span class="category"><?= e($product['category_name']) ?></span>
                    <h3>
                        <a href="<?= url('product.php?id=' . $product['id']) ?>"><?= e($product['name']) ?></a>
                    </h3>
                    <p><?= e($product['description']) ?></p>
                    <span class="rating">
                        <?php if ($product['review_count']): ?>
                            ★ <?= number_format((float) $product['average_rating'], 1) ?>
                            (<?= (int) $product['review_count'] ?> ulasan)
                        <?php else: ?>
                            Belum ada ulasan
                        <?php endif; ?>
                    </span>
                    <form method="post" action="<?= url('cart/add.php') ?>">

                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                        <span class="price"><?= rupiah($product['price']) ?></span>
                        <button type="submit" aria-label="Tambah <?= e($product['name']) ?> ke keranjang">+
                            Tambah</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>

    </div>
</section>

<?php if (!currentUser()): ?>
    <aside class="member-banner">
        <div>
            <strong>Jadi teman Kopipiro, hemat 10%.</strong>
            <p>Daftar gratis. Diskon otomatis di setiap pesanan.</p>
        </div>
        <a href="<?= url('auth/register.php') ?>">Daftar sekarang</a>
    </aside>
<?php endif; ?>

<?php pageEnd(); ?>
