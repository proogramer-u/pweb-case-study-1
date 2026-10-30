<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config/database.php';
if (
    $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() ||
    $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn()
) {
    fwrite(STDERR, "Catalog already contains data; nothing changed.\n");
    exit(1);
}
$pdo->beginTransaction();
try {
    $ids = [];
    foreach (['Coffee', 'Non-coffee', 'Food'] as $name) {
        $pdo->prepare('INSERT INTO categories(name) VALUES(?)')->execute([$name]);
        $ids[$name] = (int) $pdo->lastInsertId();
    }
    $rows = [
        ['Coffee', 'Iced Americano', 'Espresso dengan es batu', '18000.00'],
        ['Coffee', 'Cafe Latte', 'Espresso dan susu segar', '25000.00'],
        ['Non-coffee', 'Matcha Latte', 'Minuman matcha creamy', '28000.00'],
        ['Food', 'Cheese Toast', 'Roti panggang dengan keju', '22000.00'],
        ['Coffee', 'Caramel Macchiato', 'Espresso with milk and caramel syrup', '32000.00']
    ];
    foreach ($rows as [$category, $name, $description, $price]) {
        $pdo->prepare(
            'INSERT INTO products(category_id,name,description,price) VALUES(?,?,?,?)'
        )->execute([$ids[$category], $name, $description, $price]);
    }
    $pdo->commit();
    echo "Original sample catalog initialized.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
