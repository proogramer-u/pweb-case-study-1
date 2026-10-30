<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
$products = db(
    '
        SELECT
            p.*,
            c.name category_name
        FROM products p
            JOIN categories c ON c.id = p.category_id
        ORDER BY p.id DESC
    '
)->fetchAll();
pageStart('Kelola produk');
?>

<div class="page-heading">
    <div>
        <p class="eyebrow">Katalog Kopipiro</p>
        <h1>Kelola menu.</h1>
    </div>
    <a class="button" href="<?= url('admin/create_product.php') ?>">+ Tambah produk</a>
</div>
<p class="muted">Nonaktifkan menu tanpa mengubah riwayat pembelian.</p>

<?php if (!$products): ?>
    <div class="empty">Belum ada produk. Tambahkan kategori, lalu produk pertama.</div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Gambar</th>
                    <th>Menu</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <img class="thumb" src="<?= imageUrl($product['image'], $product['name']) ?>" alt="<?= e($product['name']) ?>">
                        </td>
                        <td><?= e($product['name']) ?></td>
                        <td><?= e($product['category_name']) ?></td>
                        <td><?= rupiah($product['price']) ?></td>
                        <td>
                            <span class="badge"><?= $product['is_available'] ? 'Tersedia' : 'Nonaktif' ?></span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="<?= url('admin/edit_product.php?id=' . $product['id']) ?>">Edit</a>

                                <?php if ($product['is_available']): ?>
                                    <form method="post" action="<?= url('admin/delete_product.php') ?>"
                                        data-confirm="Nonaktifkan menu ini? Riwayat pembelian tetap tersimpan.">

                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= $product['id'] ?>">
                                        <button class="danger" type="submit">Nonaktifkan</button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php pageEnd(); ?>
