<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
[$from, $to] = dateFilters();
$metrics = db(
    "
        SELECT
            COUNT(*) total_orders,
            COALESCE(SUM(status = 'completed'), 0) completed_orders,
            COALESCE(SUM(status = 'pending'), 0) pending_orders,
            COALESCE(
                SUM(
                    CASE
                        WHEN status = 'completed' THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) revenue
        FROM orders
        WHERE
            created_at >= ?
            AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    ",
    [$from, $to]
)->fetch();
$available = db('SELECT COUNT(*) FROM products WHERE is_available=1')->fetchColumn();
$sales = db(
    "
        SELECT
            DATE(created_at) sale_date,
            SUM(total_amount) revenue
        FROM orders
        WHERE
            status = 'completed'
            AND created_at >= ?
            AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY sale_date
    ",
    [$from, $to]
)->fetchAll();
$recent = db(
    '
        SELECT
            o.*,
            COALESCE(u.name, o.guest_name) customer_name
        FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
        WHERE
            o.created_at >= ?
            AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY o.id DESC
        LIMIT 8
    ',
    [$from, $to]
)->fetchAll();
// Siapkan data tampilan sebelum mulai menulis HTML.
$summaryCards = [
    'Total pesanan' => $metrics['total_orders'],
    'Pesanan selesai' => $metrics['completed_orders'],
    'Pendapatan selesai' => rupiah($metrics['revenue']),
    'Menu tersedia' => $available,
    'Menunggu proses' => $metrics['pending_orders']
];

$chartLabels = json_encode(array_column($sales, 'sale_date'));
$chartValues = json_encode(array_map('floatval', array_column($sales, 'revenue')));

pageStart('Dashboard owner');
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Ruang owner</p>
        <h1>Hari baik dimulai di sini.</h1>
        <p class="muted">Ringkasan penjualan berdasarkan tanggal pemesanan, dalam WIB.</p>
    </div>
    <a class="button" href="<?= url( 'admin/export_excel.php?' . http_build_query(['from' => $from, 'to' => $to]) ) ?>">Unduh Excel</a>
</div>
<form method="get" class="filters">
    <label for="from">Dari</label>
    <input id="from" name="from" type="date" value="<?= e($from) ?>" required>
    <label for="to">Sampai</label>
    <input id="to" name="to" type="date" value="<?= e($to) ?>" required>
    <button type="submit">Tampilkan</button>
</form>
<div class="stats">

    <?php foreach ($summaryCards as $label => $value): ?>
        <div class="stat">
            <span><?= e($label) ?></span>
            <strong><?= e($value) ?></strong>
        </div>
    <?php endforeach; ?>

</div>
<section class="panel">
    <h2>Penjualan harian</h2>
    <p class="muted">Hanya pesanan berstatus completed yang dihitung sebagai pendapatan.</p>

    <?php if (!$sales): ?>
        <p>Belum ada penjualan selesai pada periode ini.</p>
    <?php endif; ?>

    <div class="chart-wrap">
        <canvas
            id="sales-chart"
            data-labels="<?= e($chartLabels) ?>"
            data-values="<?= e( json_encode(array_map('floatval', array_column($sales, 'revenue'))) ) ?>"
           
            role="img"
            aria-label="Grafik pendapatan harian"></canvas>
    </div>
    <details>
        <summary>Data penjualan harian</summary>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Pendapatan</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($sales as $dailySales): ?>
                    <tr>
                        <td><?= e($dailySales['sale_date']) ?></td>
                        <td><?= rupiah($dailySales['revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </details>
</section>
<div class="section-heading">
    <h2>Pesanan terbaru</h2>
    <a href="<?= url('admin/orders.php') ?>">Semua pesanan</a>
</div>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Nomor</th>
                <th>Pemesan</th>
                <th>Status</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>

            <?php foreach ($recent as $order): ?>
                <tr>
                    <td>
                        <a href="<?= url('admin/order_detail.php?id=' . $order['id']) ?>"><?= e( $order['order_number'] ) ?></a>
                    </td>
                    <td><?= e($order['customer_name']) ?></td>
                    <td>
                        <span class="badge <?= e($order['status']) ?>"><?= e($order['status']) ?></span>
                    </td>
                    <td><?= rupiah($order['total_amount']) ?></td>
                </tr>
            <?php endforeach;
            if (!$recent): ?>

            <tr>
                <td colspan="4">Belum ada pesanan pada periode ini.</td>
            </tr>
            <?php endif;
            ?>

        </tbody>
    </table>
</div>
<script src="<?= url('assets/js/chart.umd.min.js') ?>" defer></script>
<script src="<?= url('assets/js/dashboard.js') ?>" defer></script>

<?php pageEnd(); ?>
