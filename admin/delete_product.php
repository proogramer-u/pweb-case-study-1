
<?php
session_start();

require_once "../config/database.php";

// Hanya menerima POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// Verifikasi CSRF token.
$token = $_POST['csrf_token'] ?? '';

if (!is_string($token) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit("Invalid form token.");
}

// Validasi ID produk.
$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if (!$id) {
    http_response_code(400);
    exit("Invalid product ID.");
}

// Hapus hanya produk dengan ID yang diminta.
$stmt = $pdo->prepare(
    "DELETE FROM products WHERE id = :id"
);

$stmt->execute(["id" => $id]);

header("Location: products.php?deleted=1");
exit;
