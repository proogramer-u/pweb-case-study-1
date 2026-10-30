<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
require_once __DIR__ . '/upload_helper.php';
$editing = $editing ?? false;
$productId = (int) input('id', $_GET);
$product = $editing
    ? db('SELECT * FROM products WHERE id=?', [$productId])->fetch()
    : [
        'name' => '',
        'description' => '',
        'price' => '',
        'category_id' => '',
        'image' => null,
        'is_available' => 1
    ];
if (!$product) {
    abortRequest(404, 'Produk tidak ditemukan.');
}
$categories = db('SELECT * FROM categories ORDER BY name')->fetchAll();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    $name = input('name');
    $description = input('description');
    $price = input('price');
    $category = input('category_id');
    $available = input('is_available') === '1' ? 1 : 0;
    if (!$name || strlen($name) > 150) {
        $errors[] = 'Nama harus 1–150 karakter.';
    }
    if (strlen($description) > 5000) {
        $errors[] = 'Deskripsi maksimal 5000 karakter.';
    }
    try {
        if (moneyCents($price) <= 0) {
            $errors[] = 'Harga harus lebih besar dari nol.';
        }
    } catch (InvalidArgumentException $exception) {
        $errors[] = $exception->getMessage();
    }
    if (!db('SELECT id FROM categories WHERE id=?', [$category])->fetch()) {
        $errors[] = 'Kategori tidak ditemukan.';
    }
    $newImage = null;
    if (!$errors) {
        try {
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $newImage = saveProductImage($_FILES['image']);
            }
            if ($editing) {
                db(
                    'UPDATE products SET name=?,description=?,price=?,category_id=?,image=?,is_available=? WHERE id=?',
                    [
                        $name,
                        $description,
                        $price,
                        $category,
                        $newImage ?? $product['image'],
                        $available,
                        $productId
                    ]
                );
            } else {
                db(
                    'INSERT INTO products(name,description,price,category_id,image,is_available) VALUES(?,?,?,?,?,?)',
                    [$name, $description, $price, $category, $newImage, $available]
                );
            }
            if ($editing && $newImage) {
                removeProductImage($product['image']);
            }
            flash($editing ? 'Menu diperbarui.' : 'Menu ditambahkan.');
            redirect('admin/products.php');
        } catch (Throwable $exception) {
            removeProductImage($newImage);
            if ($exception instanceof InvalidArgumentException) {
                $errors[] = $exception->getMessage();
            } else {
                error_log((string) $exception);
                $errors[] = 'Menu belum dapat disimpan. Coba lagi.';
            }
        }
    }
    $product = array_merge($product, [
        'name' => $name,
        'description' => $description,
        'price' => $price,
        'category_id' => $category,
        'is_available' => $available
    ]);
}
pageStart($editing ? 'Edit menu' : 'Tambah menu');
?>

<div class="page-heading">
    <h1><?= $editing ? 'Edit menu.' : 'Menu baru.' ?></h1>
    <a href="<?= url('admin/products.php') ?>">Kembali ke produk</a>
</div>
<section class="panel">

    <?php errors($errors); ?>

    <form method="post" enctype="multipart/form-data">

        <?= csrfField() ?>
        <div class="split">
            <div>
                <label for="name">Nama menu</label>
                <input id="name" name="name" maxlength="150" value="<?= e($product['name']) ?>" required>
                <label for="category_id">Kategori</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Pilih kategori</option>

                    <?php foreach ($categories as $categoryItem): ?>
                        <option value="<?= $categoryItem['id'] ?>" <?= (string) $product['category_id'] === (string) $categoryItem['id'] ? 'selected' : '' ?>><?= e($categoryItem['name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
                <label for="description">Deskripsi</label>
                <textarea id="description" name="description"
                    maxlength="5000"><?= e($product['description']) ?></textarea>
                <label for="price">Harga (Rp)</label>
                <input id="price" name="price" type="number" min="0.01" max="9999999999.99"
                    step="0.01" value="<?= e($product['price']) ?>" required>
                <label>
                    <input name="is_available" type="checkbox" value="1" <?= $product['is_available'] ? 'checked' : '' ?>>
                    Tersedia di katalog
                </label>
            </div>
            <div>
                <label for="image">Foto menu</label>
                <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                    data-file-preview="preview">
                <p class="help">JPG, PNG, atau WebP. Maksimal 2 MB. Kosongkan untuk mempertahankan
                    foto lama.</p>
                <img id="preview" class="preview" src="<?= imageUrl($product['image'], $product['name']) ?>"
                    alt="Pratinjau foto menu">
            </div>
        </div>
        <button type="submit">Simpan menu</button>
    </form>
</section>

<?php pageEnd(); ?>
