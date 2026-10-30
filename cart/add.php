<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePost();
$productId = filter_var(input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$productId) {
    abortRequest(400, 'Produk tidak valid.');
}
if (!db('SELECT id FROM products WHERE id=? AND is_available=1', [$productId])->fetch()) {
    abortRequest(404, 'Produk tidak tersedia.');
}
try {
    $requestedQuantity = quantity(input('quantity') ?: '1');
    $updatedQuantity = quantity(($_SESSION['cart'][$productId] ?? 0) + $requestedQuantity);
} catch (InvalidArgumentException $exception) {
    flash($exception->getMessage());
    redirect('cart/index.php');
}
if (count($_SESSION['cart'] ?? []) >= 50 && !isset($_SESSION['cart'][$productId])) {
    flash('Maksimal 50 jenis produk per pesanan.');
    redirect('cart/index.php');
}
$_SESSION['cart'][$productId] = $updatedQuantity;
unset($_SESSION['checkout_key']);
flash('Menu ditambahkan ke keranjang.');
redirect('cart/index.php');
