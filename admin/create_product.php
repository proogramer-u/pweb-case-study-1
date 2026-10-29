
<?php
session_start();

require_once "../config/database.php";
require_once "upload_helper.php";

// Generate CSRF token untuk melindungi form.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$imageName = null;
$name = "";
$description = "";
$price = "";
$categoryId = "";

// Mengambil daftar kategori dari database.
$stmt = $pdo->query("SELECT id, name FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Jalankan hanya jika form dikirim dengan POST.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $categoryId = $_POST["category_id"] ?? "";

    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit("Invalid form token.");
    }

    // Validasi nama.
    if ($name === "") {
        $errors[] = "Product name is required.";
    } elseif (strlen($name) > 150) {
        $errors[] = "Product name is too long.";
    }

    // Validasi deskripsi.
    if (strlen($description) > 5000) {
        $errors[] = "Description is too long.";
    }

    // Validasi harga: bilangan positif, maksimal 2 desimal.
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $price)
        || (float)$price <= 0) {
        $errors[] = "Price must be greater than zero.";
    }

    // Validasi kategori harus berupa integer positif.
    if (filter_var(
        $categoryId,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    ) === false) {
        $errors[] = "Invalid category.";
    } else {
        // Pastikan kategori benar-benar ada di database.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM categories WHERE id = :id"
        );

        $stmt->execute(["id" => $categoryId]);

        if ($stmt->fetchColumn() == 0) {
            $errors[] = "Category does not exist.";
        }
    }

    // INSERT hanya jika semua validasi berhasil.
    
if (empty($errors)) {

    try {
        // Upload gambar jika pengguna memilih file.
        if (isset($_FILES['image']) &&
            $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

            $imageName = saveProductImage($_FILES['image']);
        }

        // INSERT produk dan nama file.
        $sql = "INSERT INTO products
                (category_id, name, description, price, image)
                VALUES
                (:category_id, :name, :description, :price, :image)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            "category_id" => $categoryId,
            "name" => $name,
            "description" => $description,
            "price" => $price,
            "image" => $imageName
        ]);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: create_product.php?success=1");
        exit;

    } catch (RuntimeException $e) {

        // Hapus gambar jika proses INSERT gagal.
        if ($imageName !== null) {
            $path = __DIR__ . "/../uploads/products/" . $imageName;
            if (is_file($path)) {
                unlink($path);
            }
        }

        $errors[] = $e->getMessage();

    } catch (PDOException $e) {

        if ($imageName !== null) {
            $path = __DIR__ . "/../uploads/products/" . $imageName;
            if (is_file($path)) {
                unlink($path);
            }
        }

        error_log($e->getMessage());
        $errors[] = "Could not save product.";
    }
}


        // Refresh token dan redirect setelah INSERT.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: create_product.php?success=1");
        exit;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product - Kopipiro</title>
</head>
<body>

<h1>Add New Product</h1>

<?php if (isset($_GET["success"])): ?>
    <p style="color: green;">
        Product added successfully!
    </p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <ul style="color: red;">
        <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="POST" action="create_product.php" enctype="multipart/form-data">

    <input type="hidden"
           name="csrf_token"
           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <label>Product Name:</label>
    <input type="text"
           name="name"
           maxlength="150"
           value="<?= htmlspecialchars($name) ?>"
           required>

    <br><br>

    <label>Category:</label>
    <select name="category_id" required>
        <option value="">Select Category</option>

        <?php foreach ($categories as $category): ?>
            <option value="<?= (int)$category['id'] ?>"
                <?= (string)$categoryId === (string)$category['id']
                    ? 'selected' : '' ?>>
                <?= htmlspecialchars($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Description:</label>
    <textarea name="description"><?= htmlspecialchars($description) ?></textarea>

    <br><br>

    <label>Price:</label>
    <input type="number"
           name="price"
           min="0.01"
           step="0.01"
           value="<?= htmlspecialchars($price) ?>"
           required>

    <br><br>

    <label>Product Image:</label>

    <input type="file"
        name="image"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

    <br><br>

    <button type="submit">Add Product</button>

</form>

<p>
    <a href="../index.php">Back to Products</a>
</p>

</body>
</html>
