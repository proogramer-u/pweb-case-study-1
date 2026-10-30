<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePost();
if (input('action') === 'clear') {
    $_SESSION['cart'] = [];
    unset($_SESSION['checkout_key']);
    redirect('cart/index.php');
}
$productId = filter_var(input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$productId || !isset($_SESSION['cart'][$productId])) {
    abortRequest(400, 'Item keranjang tidak ditemukan.');
}
try {
    $_SESSION['cart'][$productId] = quantity(input('quantity'));
    unset($_SESSION['checkout_key']);
} catch (InvalidArgumentException $exception) {
    flash($exception->getMessage());
}
redirect('cart/index.php');
