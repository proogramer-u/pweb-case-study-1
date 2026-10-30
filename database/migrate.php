<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require __DIR__ . '/../config/database.php';
// SQL is trusted local schema, never user input.
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
echo "Additive schema applied. Existing rows preserved.\n";
