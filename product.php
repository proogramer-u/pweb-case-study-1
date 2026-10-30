<?php
require_once __DIR__ . '/includes/bootstrap.php';
$productId = (int) input('id', $_GET);
$product = db(
    '
        SELECT
            p.*,
            c.name category_name
        FROM products p
            JOIN categories c ON c.id = p.category_id
        WHERE
            p.id = ?
    ',
    [$productId]
)->fetch();
if (!$product) {
    abortRequest(404, 'Menu tidak tersedia.');
}
$user = currentUser();
$eligible =
    $user &&
    $user['role'] === 'customer' &&
    db(
        "
            SELECT
                1
            FROM orders o
                JOIN order_items i ON i.order_id = o.id
            WHERE
                o.user_id = ?
                AND o.status = 'completed'
                AND i.product_id = ?
            LIMIT 1
        ",
        [$user['id'], $productId]
    )->fetchColumn();
$ownReview = $user
    ? db('SELECT * FROM reviews WHERE user_id=? AND product_id=?', [$user['id'], $productId])->fetch()
    : null;
$reviews = db(
    '
        SELECT
            r.*,
            u.name
        FROM reviews r
            JOIN users u ON u.id = r.user_id
        WHERE
            product_id = ?
        ORDER BY r.created_at DESC
    ',
    [$productId]
)->fetchAll();
pageStart($product['name']);
?>

<div class="split">
    <section class="product-card">
        <img src="<?= imageUrl($product['image'], $product['name']) ?>" alt="<?= e($product['name']) ?>">
        <div class="product-body">
            <span class="category"><?= e($product['category_name']) ?></span>
            <h1><?= e($product['name']) ?></h1>
            <p><?= e($product['description']) ?></p>
            <form method="post" action="<?= url('cart/add.php') ?>">

                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $productId ?>">
                <strong class="price"><?= rupiah($product['price']) ?></strong>

                <?php if ($product['is_available']): ?>
                    <button type="submit">+ Tambah</button>
                <?php else: ?>
                    <span class="badge cancelled">Sedang tidak tersedia</span>
                <?php endif; ?>

            </form>
        </div>
    </section>
    <section class="panel">
        <h2>Kata teman ngopi</h2>

        <?php if (!$reviews): ?>
            <p class="muted">Belum ada ulasan untuk menu ini.</p>
        <?php endif;
        foreach ($reviews as $review): ?>

        <article class="review">
            <strong><?= e($review['name']) ?></strong>
            <span class="rating"> · <?= (int) $review['rating'] ?>/5 ★</span>
            <p><?= nl2br(e($review['comment'])) ?></p>
            <small><?= e($review['created_at']) ?></small>
        </article>
        <?php endforeach;
        ?>

        <?php if ($eligible): ?>
            <h3><?= $ownReview ? 'Edit ulasanmu' : 'Bagaimana rasanya?' ?></h3>
            <form method="post" action="<?= url('customer/review.php') ?>">

                <?= csrfField() ?>
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <label for="rating">Rating</label>
                <select id="rating" name="rating" required>

                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>" <?= ($ownReview['rating'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?> dari 5
                        </option>
                    <?php endfor; ?>

                </select>
                <label for="comment">Ulasan (opsional)</label>
                <textarea id="comment" name="comment" maxlength="2000"><?= e($ownReview['comment'] ?? '') ?></textarea>
                <button type="submit">Simpan ulasan</button>
            </form>
        <?php else: ?>
            <p class="help">Ulasan tersedia untuk pelanggan setelah pesanan selesai.</p>
        <?php endif; ?>

    </section>
</div>

<?php pageEnd(); ?>
