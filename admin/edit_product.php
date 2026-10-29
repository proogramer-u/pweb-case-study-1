
<?php
session_start();
require_once "../config/database.php";

// 1. Ambil ID dari URL dan validasi.
$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$id) {
    http_response_code(400);
    exit("Invalid product ID.");
}

// 2. Cari produk berdasarkan ID.
$stmt = $pdo->prepare(
    "SELECT * FROM products WHERE id = :id"
);
$stmt->execute(["id" => $id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    exit("Product not found.");
}

// 3. Ambil daftar kategori.
$categories = $pdo->query(
    "SELECT id, name FROM categories"
)->fetchAll(PDO::FETCH_ASSOC);

// 4. Siapkan CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

// 5. Jika form dikirim menggunakan POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit("Invalid form token.");
    }

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';

    // 6. Validasi input.
    if ($name === "" || strlen($name) > 150) {
        $errors[] = "Name must be 1-150 characters.";
    }

    if (strlen($description) > 5000) {
        $errors[] = "Description is too long.";
    }

    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $price)
        || (float)$price <= 0) {
        $errors[] = "Invalid price.";
    }

    $validCategoryId = filter_var(
        $categoryId,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

    if ($validCategoryId === false) {
        $errors[] = "Invalid category.";
    } else {
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM categories WHERE id = :id"
        );
        $check->execute(["id" => $validCategoryId]);

        if ($check->fetchColumn() == 0) {
            $errors[] = "Category not found.";
        }
    }

    // 7. Jika tidak ada error, jalankan UPDATE.
    if (empty($errors)) {

        $sql = "UPDATE products
                SET name = :name,
                    description = :description,
                    price = :price,
                    category_id = :category_id
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            "name" => $name,
            "description" => $description,
            "price" => $price,
            "category_id" => $validCategoryId,
            "id" => $id
        ]);

        header("Location: products.php?updated=1");
        exit;
    }

    // Pertahankan nilai input jika validasi gagal.
    $product['name'] = $name;
    $product['description'] = $description;
    $product['price'] = $price;
    $product['category_id'] = $categoryId;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - Kopipiro</title>
</head>
<body>

<h1>Edit Product</h1>

<?php foreach ($errors as $error): ?>
    <p style="color:red">
        <?= htmlspecialchars($error) ?>
    </p>
<?php endforeach; ?>

<form method="POST"
      action="edit_product.php?id=<?= $id ?>">

    <input type="hidden"
           name="csrf_token"
           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <label>Product Name</label>
    <input type="text"
           name="name"
           maxlength="150"
           value="<?= htmlspecialchars($product['name']) ?>"
           required>

    <br><br>

    <label>Category</label>
    <select name="category_id" required>
        <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['id'] ?>"
                <?= $product['category_id'] == $category['id']
                    ? 'selected' : '' ?>>
                <?= htmlspecialchars($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Description</label>
    <textarea name="description"><?= htmlspecialchars(
        $product['description'] ?? ''
    ) ?></textarea>

    <br><br>

    <label>Price</label>
    <input type="number"
           name="price"
           min="0.01"
           step="0.01"
           value="<?= htmlspecialchars($product['price']) ?>"
           required>

    <br><br>

    <button type="submit">Save Changes</button>
</form>

<p><a href="products.php">Back to Products</a></p>

</body>
</html>
