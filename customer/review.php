<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = requireRole('customer');
requirePost();
$productId = (int) input('product_id');
$rating = filter_var(input('rating'), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 5]
]);
$comment = input('comment');
if (!$rating || strlen($comment) > 2000) {
    abortRequest(422, 'Rating harus 1–5 dan ulasan maksimal 2000 karakter.');
}
if (
    !db(
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
    )->fetchColumn()
) {
    abortRequest(403, 'Ulasan memerlukan pembelian yang selesai.');
}
db(
    '
        INSERT INTO reviews (user_id, product_id, rating, comment)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating),
            comment = VALUES(comment)
    ',
    [$user['id'], $productId, $rating, $comment]
);
flash('Ulasan tersimpan.');
redirect('product.php?id=' . $productId);
