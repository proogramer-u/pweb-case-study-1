<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePost();
unset($_SESSION['cart'][input('id')], $_SESSION['checkout_key']);
redirect('cart/index.php');
