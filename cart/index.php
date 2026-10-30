<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$errors = [];
$items = [];
try {
    $items = cartItems();
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
    // Show unavailable entries so individual removal remains possible.
    foreach ($_SESSION['cart'] ?? [] as $productId => $itemQuantity) {
        $product = db('SELECT * FROM products WHERE id=?', [$productId])->fetch();
        $items[] = array_merge(
            $product ?: [
                'id' => $productId,
                'name' => 'Produk dihapus',
                'price' => '0.00',
                'image' => null,
                'is_available' => 0
            ],
            ['quantity' => $itemQuantity]
        );
    }
}
$totals = orderTotals($items, currentUser()['role'] ?? null);
pageStart('Keranjang');
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Pilihanmu hari ini</p>
        <h1>Keranjang ngopi.</h1>
    </div>
    <a href="<?= url('products.php') ?>">Tambah menu</a>
</div>

<?php errors($errors); ?>

<?php if (!$items): ?>
    <div class="empty">
        <h2>Belum ada teman ngopi.</h2>
        <p>Isi keranjang dengan menu favoritmu.</p>
        <a class="button" href="<?= url('products.php') ?>">Jelajahi menu</a>
    </div>
<?php else: ?>
    <div class="split">
        <div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Jumlah</th>
                            <th>Total</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($items as $product): ?>
                            <tr>
                                <td>
                                    <img class="thumb" src="<?= imageUrl($product['image'], $product['name']) ?>" alt="">
                                    <br>
                                    <strong><?= e($product['name']) ?></strong>
                                    <br><?= rupiah($product['price']) ?>

                                    <?php if (!$product['is_available']): ?>
                                        <br>
                                        <span class="badge cancelled">Tidak tersedia</span>
                                    <?php endif; ?>

                                </td>
                                <td>
                                    <form method="post" action="<?= url('cart/update.php') ?>">

                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <label class="sr-only" for="qty-<?= $product['id'] ?>">Jumlah
                                            <?= e($product['name']) ?></label>
                                        <input class="qty" id="qty-<?= $product['id'] ?>" type="number"
                                            name="quantity" min="1" max="99" value="<?= $product['quantity'] ?>">
                                        <button class="text-button" type="submit">Ubah</button>
                                    </form>
                                </td>
                                <td><?= rupiah( decimalMoney(moneyCents($product['price']) * $product['quantity']) ) ?></td>
                                <td>
                                    <form method="post" action="<?= url('cart/remove.php') ?>">

                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <button class="text-button" type="submit">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
            <form method="post" action="<?= url('cart/update.php') ?>">

                <?= csrfField() ?>
                <input type="hidden" name="action" value="clear">
                <button class="secondary" type="submit">Kosongkan keranjang</button>
            </form>
        </div>
        <aside class="panel summary">
            <h2>Ringkasan</h2>
            <p>
                <span>Subtotal</span>
                <span><?= rupiah(decimalMoney($totals['subtotal'])) ?></span>
            </p>
            <p>
                <span>Diskon (<?= (currentUser()['role'] ?? '') === 'customer' ? '10' : '0' ?>%)</span>
                <span>−<?= rupiah(decimalMoney($totals['discount'])) ?></span>
            </p>
            <p class="total">
                <span>Total</span>
                <span><?= rupiah(decimalMoney($totals['total'])) ?></span>
            </p>

            <?php if (!$errors): ?>
                <a class="button" href="<?= url('checkout.php') ?>">Lanjut checkout</a>
            <?php endif; ?>

            <p class="help">Pembayaran langsung di kasir.</p>
        </aside>
    </div>
<?php endif; ?>

<?php pageEnd(); ?>
