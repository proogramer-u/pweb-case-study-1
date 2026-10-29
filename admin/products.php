
<?php
session_start();

require_once "../config/database.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Query SELECT produk tetap seperti sebelumnya.

$stmt = $pdo->query(
    "SELECT products.id,
            products.name,
            products.price,
            categories.name AS category_name
     FROM products
     JOIN categories
       ON products.category_id = categories.id
     ORDER BY products.id DESC"
);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Products - Kopipiro</title>
</head>
<body>

<h1>Manage Products</h1>

<a href="create_product.php">+ Add Product</a>

<br><br>

<table border="1" cellpadding="10">
    <tr>
        <th>ID</th>
        <th>Product</th>
        <th>Category</th>
        <th>Price</th>
        <th>Actions</th>
    </tr>

    <?php foreach ($products as $product): ?>
    <tr>
        <td><?= (int)$product['id'] ?></td>

        <td>
            <?= htmlspecialchars($product['name']) ?>
        </td>

        <td>
            <?= htmlspecialchars($product['category_name']) ?>
        </td>

        <td>
            Rp <?= number_format(
                $product['price'], 0, ',', '.'
            ) ?>
        </td>

        <td>
            <a href="edit_product.php?id=<?= (int)$product['id'] ?>">
                Edit
            </a>

            
<form action="delete_product.php"
      method="POST"
      style="display:inline"
      onsubmit="return confirm('Are you sure you want to delete this product?')">

    <input type="hidden"
           name="id"
           value="<?= (int)$product['id'] ?>">

    <input type="hidden"
           name="csrf_token"
           value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <button type="submit">Delete</button>

</form>

    </tr>
    <?php endforeach; ?>

</table>

</body>
</html>
