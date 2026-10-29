<?php

$host = "localhost";
$username = "root";
$password = "";
$dbname = "kopipiro";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );
} catch(PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit("Database connection failed");
}
?>