<?php
$owner = $user['role'] === 'owner';
$status = input('status', $_GET);
$search = input('q', $_GET);
[$from, $to] = dateFilters();
$sql =
    '
        SELECT
            o.*,
            COALESCE(u.name, o.guest_name) customer_name,
            c.name cashier_name
        FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN users c ON c.id = o.cashier_id
        WHERE
            o.created_at >= ?
            AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
    ';
$params = [$from, $to];
if (!$owner) {
    $sql .= ' AND o.cashier_id=?';
    $params[] = $user['id'];
}
if ($status !== '') {
    $sql .= ' AND o.status=?';
    $params[] = $status;
}
if ($search !== '') {
    $sql .= ' AND o.order_number LIKE ?';
    $params[] = '%' . $search . '%';
}
$orders = db($sql . ' ORDER BY o.id DESC', $params)->fetchAll();
pageStart($owner ? 'Kelola pesanan' : 'Pesanan kasir');
?>

<h1><?= $owner ? 'Pesanan masuk.' : 'Siap melayani.' ?></h1>
<p class="muted"><?= $owner ? 'Pantau pesanan dan tugaskan ke kasir.' : 'Hanya pesanan yang ditugaskan kepadamu yang ditampilkan.' ?></p>
<form method="get" class="filters">
    <label class="sr-only" for="q">Nomor pesanan</label>
    <input id="q" name="q" placeholder="Cari nomor pesanan" value="<?= e($search) ?>">
    <label class="sr-only" for="status">Status</label>
    <select id="status" name="status">
        <option value="">Semua status</option>

        <?php foreach (['pending', 'processing', 'ready', 'completed', 'cancelled'] as $statusOption): ?>
            <option <?= $statusOption === $status ? 'selected' : '' ?>><?= $statusOption ?></option>
        <?php endforeach; ?>

    </select>
    <label for="from">Dari</label>
    <input id="from" name="from" type="date" value="<?= e($from) ?>">
    <label for="to">Sampai</label>
    <input id="to" name="to" type="date" value="<?= e($to) ?>">
    <button type="submit">Terapkan</button>
</form>

<?php if (!$orders): ?>
    <div class="empty">
        <h2>Belum ada pesanan.</h2>
        <p>Coba rentang tanggal lain atau tunggu pesanan baru.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pesanan</th>
                    <th>Pemesan</th>
                    <th>Kasir</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a href="<?= url( ($owner ? 'admin/' : 'cashier/') . 'order_detail.php?id=' . $order['id'] ) ?>"><?= e($order['order_number']) ?></a>
                        </td>
                        <td><?= e($order['customer_name']) ?></td>
                        <td><?= e($order['cashier_name'] ?: 'Belum ditugaskan') ?></td>
                        <td><?= e($order['created_at']) ?></td>
                        <td>
                            <span class="badge <?= e($order['status']) ?>"><?= e($order['status']) ?></span>
                        </td>
                        <td><?= rupiah($order['total_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php pageEnd(); ?>
