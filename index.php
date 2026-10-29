
<?php
require_once "config/database.php";

$stmt = $pdo->query(
    "SELECT * FROM products WHERE is_available = 1"
);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kopipiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <h1>Welcome to Kopipiro ☕</h1>
    <p>Discover your favorite coffee!</p>

    <div class="product-list">

        <?php foreach ($products as $product): ?>

            <div class="product-card">
                <h2>
                    <?= htmlspecialchars($product['name']) ?>
                </h2>

                <p>
                    <?= htmlspecialchars(
                        $product['description'] ?? ''
                    ) ?>
                </p>

                <strong>
                    Rp <?= number_format(
                        $product['price'], 0, ',', '.'
                    ) ?>
                </strong>
            </div>

        <?php endforeach; ?>

    </div>

</body>
</html>
