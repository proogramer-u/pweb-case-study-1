<?php
if (PHP_SAPI !== 'cli') {
    exit();
}
$name = getenv('DB_NAME');
if (!preg_match('/^kopipiro_test_[a-z0-9_]+$/D', $name ?: '')) {
    fwrite(STDERR, 'Isolated test database required.');
    exit(1);
}
$server = new PDO(
    'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') . ';charset=utf8mb4',
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$action = $argv[1] ?? '';
if ($action === 'setup' || $action === 'setup_empty') {
    $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4');
    require __DIR__ . '/../config/database.php';
    $pdo->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
    if ($action === 'setup_empty') {
        echo '{}';
        exit();
    }
    $pdo->exec("INSERT INTO categories(name) VALUES('Coffee'),('Food')");
    $pdo->exec(
        "INSERT INTO products(category_id,name,description,price) VALUES(1,'Test Latte','Espresso dan susu',25000),(2,'Test Toast','Roti panggang',20000)"
    );
    $pass = bin2hex(random_bytes(16));
    $users = [];
    foreach (['owner', 'cashierA', 'cashierB', 'customerA', 'customerB'] as $who) {
        $role = str_starts_with($who, 'cashier')
            ? 'cashier'
            : (str_starts_with($who, 'customer')
                ? 'customer'
                : 'owner');
        $s = $pdo->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)');
        $s->execute([$who, $who . '@example.test', password_hash($pass, PASSWORD_DEFAULT), $role]);
        $users[$who] = (int) $pdo->lastInsertId();
    }
    echo json_encode(['password' => $pass, 'users' => $users]);
} elseif ($action === 'drop') {
    $server->exec('DROP DATABASE `' . $name . '`');
} elseif ($action === 'query') {
    require __DIR__ . '/../config/database.php';
    $data = json_decode(stream_get_contents(STDIN), true);
    $s = $pdo->prepare($data['sql']);
    $s->execute($data['args'] ?? []);
    echo json_encode($s->fetchAll());
}
