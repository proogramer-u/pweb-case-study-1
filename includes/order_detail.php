<?php
$order = orderScope((int) input('id', $_GET));
$items = db('SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$order['id']])->fetchAll();
pageStart('Pesanan ' . $order['order_number']);
?>

<?php if (isset($confirmation)): ?>
    <div class="success-mark" aria-hidden="true">✓</div>
    <h1>Pesananmu sudah kami terima.</h1>
    <p>Terima kasih sudah memilih Kopipiro. Bayar langsung di kasir.</p>
<?php else: ?>
    <h1>Detail pesanan</h1>
<?php endif; ?>

<div class="split">
    <section class="panel">
        <div class="section-heading">
            <h2><?= e($order['order_number']) ?></h2>
            <span class="badge <?= e($order['status']) ?>"><?= e($order['status']) ?></span>
        </div>
        <p class="muted"><?= e($order['created_at']) ?> WIB</p>
        <p>
            <strong><?= e($order['customer_name']) ?></strong>

            <?php if (($user['role'] ?? '') === 'owner' || ($user['role'] ?? '') === 'cashier'): ?>
                <br><?= e($order['guest_email'] ?: $order['customer_email']) ?> <?= e($order['guest_phone']) ?>
            <?php endif; ?>

            <br>Kasir: <?= e($order['cashier_name'] ?: 'Menunggu penugasan') ?>
        </p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Menu</th>
                        <th>Jumlah</th>
                        <th>Harga satuan</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?>

                                <?php if ( ($user['role'] ?? '') === 'customer' && $order['status'] === 'completed' && $item['product_id'] ): ?>
                                    <br>
                                    <a href="<?= url( 'product.php?id=' . $item['product_id'] ) ?>">Tulis ulasan</a>
                                <?php endif; ?>

                            </td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= rupiah($item['unit_price']) ?></td>
                            <td><?= rupiah($item['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>

        <?php if ( ($user['role'] ?? '') === 'owner' && !in_array($order['status'], ['completed', 'cancelled'], true) ): ?>
            <form action="<?= url('admin/assign_order.php') ?>" method="post">

                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $order['id'] ?>">
                <label for="cashier_id">Tugaskan kasir</label>
                <select id="cashier_id" name="cashier_id" required>
                    <option value="">Pilih kasir</option>

                    <?php foreach ( db("SELECT id,name FROM users WHERE role='cashier' ORDER BY name")->fetchAll() as $cashier ): ?>
                        <option value="<?= $cashier['id'] ?>" <?= $order['cashier_id'] === $cashier['id'] ? 'selected' : '' ?>><?= e($cashier['name']) ?></option>
                    <?php endforeach; ?>

                </select>
                <button type="submit">Simpan penugasan</button>
            </form>
        <?php endif; ?>

        <?php if (in_array($user['role'] ?? '', ['cashier', 'owner'], true)): ?>
            <div class="actions">

                <?php foreach (['processing', 'ready', 'completed', 'cancelled'] as $nextStatus):
                    if (!canTransition($user['role'], $order['status'], $nextStatus)) {
                        continue;
                    } ?>

                <form action="<?= url('cashier/update_status.php') ?>" method="post">

                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="status" value="<?= $nextStatus ?>">
                    <button class="secondary" type="submit"><?= $nextStatus === 'cancelled' ? 'Batalkan pesanan' : 'Ubah ke ' . $nextStatus ?></button>
                </form>

                <?php
                endforeach; ?>

            </div>
        <?php endif; ?>

    </section>
    <aside class="panel totals">
        <h2>Ringkasan</h2>
        <p>
            <span>Subtotal</span>
            <span><?= rupiah($order['subtotal']) ?></span>
        </p>
        <p>
            <span>Diskon</span>
            <span>−<?= rupiah($order['discount_amount']) ?></span>
        </p>
        <p class="total">
            <span>Total</span>
            <span><?= rupiah($order['total_amount']) ?></span>
        </p>
        <p class="help">Pembayaran manual di kasir.</p>
    </aside>
</div>

<?php pageEnd(); ?>
