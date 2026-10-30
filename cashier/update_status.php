<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = requireLogin();
if (!in_array($user['role'], ['owner', 'cashier'], true)) {
    abortRequest(403, 'Akses ditolak.');
}
requirePost();
$orderId = (int) input('id');
$nextStatus = input('status');
$pdo->beginTransaction();
$sql = 'SELECT * FROM orders WHERE id=?';
$params = [$orderId];
if ($user['role'] === 'cashier') {
    $sql .= ' AND cashier_id=?';
    $params[] = $user['id'];
}
$order = db($sql . ' FOR UPDATE', $params)->fetch();
if (!$order) {
    $pdo->rollBack();
    abortRequest(404, 'Pesanan tidak ditemukan.');
}
if (!canTransition($user['role'], $order['status'], $nextStatus)) {
    $pdo->rollBack();
    abortRequest(409, 'Perubahan status tidak diizinkan.');
}
db('UPDATE orders SET status=? WHERE id=?', [$nextStatus, $orderId]);
$pdo->commit();
flash('Status pesanan diperbarui.');
redirect(($user['role'] === 'owner' ? 'admin/' : 'cashier/') . 'order_detail.php?id=' . $orderId);
