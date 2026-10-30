<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
requirePost();
$id = filter_var(input('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    abortRequest(400, 'ID tidak valid.');
}
db('UPDATE products SET is_available=0 WHERE id=?', [$id]);
flash('Menu dinonaktifkan. Riwayat pesanan tetap tersimpan.');
redirect('admin/products.php');
