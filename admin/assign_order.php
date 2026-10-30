<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
requirePost();
$orderId = (int) input('id');
$cashierId = (int) input('cashier_id');
if (!db("SELECT id FROM users WHERE id=? AND role='cashier'", [$cashierId])->fetch()) {
    abortRequest(422, 'Kasir tidak valid.');
}
$statement = db(
    "UPDATE orders SET cashier_id=? WHERE id=? AND status IN ('pending','processing','ready')",
    [$cashierId, $orderId]
);
if (
    !$statement->rowCount() &&
    !db(
        "SELECT id FROM orders WHERE id=? AND cashier_id=? AND status IN ('pending','processing','ready')",
        [$orderId, $cashierId]
    )->fetch()
) {
    abortRequest(409, 'Pesanan tidak dapat ditugaskan.');
}
flash('Kasir ditugaskan.');
redirect('admin/order_detail.php?id=' . $orderId);
