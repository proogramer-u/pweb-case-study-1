<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = requireRole('customer');
$orders = db('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC', [$user['id']])->fetchAll();
pageStart('Pesanan saya');
?>

<h1>Cerita ngopimu.</h1>
<p class="muted">Lihat status, detail, dan ulas menu yang sudah kamu nikmati.</p>

<?php if (!$orders): ?>
    <div class="empty">
        <h2>Belum ada pesanan.</h2>
        <a class="button" href="<?= url('products.php') ?>">Pesan menu pertama</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th>Diskon</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a href="<?= url('customer/order_detail.php?id=' . $order['id']) ?>"><?= e( $order['order_number'] ) ?></a>
                        </td>
                        <td><?= e($order['created_at']) ?></td>
                        <td>
                            <span class="badge <?= e($order['status']) ?>"><?= e($order['status']) ?></span>
                        </td>
                        <td><?= rupiah($order['discount_amount']) ?></td>
                        <td><?= rupiah($order['total_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php pageEnd(); ?>
