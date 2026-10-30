<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    $id = (int) input('id');
    $name = input('name');
    $action = input('action');
    if ($action === 'delete') {
        try {
            db('DELETE FROM categories WHERE id=?', [$id]);
            flash('Kategori dihapus.');
            redirect('admin/categories.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Kategori masih digunakan produk. Pindahkan produknya terlebih dahulu.';
            } else {
                throw $exception;
            }
        }
    } elseif (!$name || strlen($name) > 100) {
        $errors[] = 'Nama kategori harus 1–100 karakter.';
    } else {
        if ($id) {
            db('UPDATE categories SET name=? WHERE id=?', [$name, $id]);
        } else {
            db('INSERT INTO categories(name) VALUES(?)', [$name]);
        }
        flash('Kategori tersimpan.');
        redirect('admin/categories.php');
    }
}
$categories = db(
    '
        SELECT
            c.*,
            COUNT(p.id) product_count
        FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id,
            c.name
        ORDER BY c.name
    '
)->fetchAll();
pageStart('Kategori');
?>

<h1>Kategori menu.</h1>

<?php errors($errors); ?>

<section class="panel">
    <h2>Tambah kategori</h2>
    <form method="post">

        <?= csrfField() ?>
        <label for="new-name">Nama kategori</label>
        <input id="new-name" name="name" maxlength="100" required>
        <button type="submit">Tambah kategori</button>
    </form>
</section>

<?php foreach ($categories as $categoryItem): ?>
    <section class="panel">
        <form method="post">

            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $categoryItem['id'] ?>">
            <label for="name-<?= $categoryItem['id'] ?>">Nama kategori (<?= $categoryItem['product_count'] ?> produk)</label>
            <input id="name-<?= $categoryItem['id'] ?>" name="name" maxlength="100" value="<?= e($categoryItem['name']) ?>"
                required>
            <div class="actions">
                <button type="submit">Simpan nama</button>

                <?php if (!$categoryItem['product_count']): ?>
                    <button class="danger" name="action" value="delete" type="submit">Hapus kategori
                    </button>
                <?php endif; ?>

            </div>
        </form>
    </section>
<?php endforeach; ?>

<?php pageEnd(); ?>
