<?php
require_once "config/database.php";

$stmt = $pdo->query(
    "SELECT * FROM products"
);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($products as $product) {
    echo "<h2>" .
        htmlspecialchars($product["name"]) .
        "</h2>";
    
    echo "<p>" . 
        number_format($product['price'], 0, ',', '.') .
        "</p>";
}
?>
