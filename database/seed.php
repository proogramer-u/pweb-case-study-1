<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config/database.php';
$role = $argv[1] ?? '';
$email = getenv('SEED_EMAIL');
$password = getenv('SEED_PASSWORD');
$name = getenv('SEED_NAME') ?: ucfirst($role);
if (
    !in_array($role, ['owner', 'cashier'], true) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($email) > 190 ||
    (strlen($password ?: '') < 12 || strlen($password ?: '') > 72) ||
    strlen($name) > 150
) {
    fwrite(
        STDERR,
        "Usage: SEED_EMAIL=... SEED_PASSWORD=<12-72 bytes> SEED_NAME=... php database/seed.php owner|cashier\n"
    );
    exit(1);
}
$check = $pdo->prepare('SELECT id FROM users WHERE email=?');
$check->execute([$email]);
if ($check->fetch()) {
    fwrite(STDERR, "Email exists; no account modified.\n");
    exit(1);
}
$pdo->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)')->execute([
    $name,
    $email,
    password_hash($password, PASSWORD_DEFAULT),
    $role
]);
echo "Local $role created. No existing accounts modified.\n";
