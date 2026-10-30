<?php
require_once __DIR__ . '/domain.php';

// Pengaturan dasar ini dijalankan sebelum halaman mengirim HTML.
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $exception) {
    error_log((string) $exception);
    http_response_code(500);
    echo 'Permintaan belum dapat diproses. Periksa konfigurasi database atau coba lagi.';
});

// Session menyimpan identitas pengguna, token form, dan keranjang.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/'
    ]);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header(
    "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'"
);
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/database.php';

// Mendukung instalasi di subfolder XAMPP maupun di document root.
$base = getenv('APP_BASE');
if ($base === false) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $directory = dirname($script);
    if (in_array(basename($directory), ['admin', 'auth', 'cart', 'cashier', 'customer'], true)) {
        $directory = dirname($directory);
    }
    $base = $directory === '/' || $directory === '.' ? '' : $directory;
}
define('BASE_URL', rtrim($base, '/'));
function url(string $path = ''): string {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Gunakan e() saat menampilkan nilai dinamis di dalam HTML.
function e(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input(string $key, ?array $source = null): string {
    $value = ($source ?? $_POST)[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function db(string $sql, array $params = []): PDOStatement {
    global $pdo;
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function redirect(string $path): never {
    header('Location: ' . url($path), true, 303);
    exit();
}

function abortRequest(int $status, string $message): never {
    http_response_code($status);
    echo e($message);
    exit();
}

function csrfToken(): string {
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

// Semua aksi yang mengubah data wajib menggunakan POST dan token CSRF.
function requirePost(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        abortRequest(405, 'Gunakan POST.');
    }
    if (!hash_equals(csrfToken(), input('csrf_token'))) {
        abortRequest(403, 'Token formulir tidak valid. Muat ulang halaman.');
    }
}

function currentUser(): ?array {
    static $loaded = false;
    static $user = null;
    if (!$loaded) {
        $loaded = true;
        if (isset($_SESSION['user_id'])) {
            $user = db(
                'SELECT id, name, email, role FROM users WHERE id = ?',
                [$_SESSION['user_id']]
            )->fetch() ?: null;
        }
    }
    return $user;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        redirect('auth/login.php');
    }
    return $user;
}

function requireRole(string $role): array {
    $user = requireLogin();
    if ($user['role'] !== $role) {
        abortRequest(403, 'Akses ditolak.');
    }
    return $user;
}

function roleHome(array $user): string {
    return [
        'owner' => 'admin/dashboard.php',
        'cashier' => 'cashier/dashboard.php',
        'customer' => 'index.php'
    ][$user['role']];
}

function rupiah(mixed $value): string {
    return 'Rp' . number_format((float) $value, 0, ',', '.');
}

function flash(string $message): void {
    $_SESSION['flash'] = $message;
}

function imageUrl(?string $name, ?string $productName = null): string {
    if ($name && basename($name) === $name && is_file(__DIR__ . '/../uploads/products/' . $name)) {
        return url('uploads/products/' . rawurlencode($name));
    }
    $photos = [
        'Iced Americano' => 'americano.jpeg',
        'Cafe Latte' => 'latte.jpeg',
        'Matcha Latte' => 'matcha.jpeg'
    ];
    return url('assets/images/' . ($photos[$productName ?? ''] ?? 'placeholder.svg'));
}

function pageStart(string $title): void {
    $GLOBALS['pageTitle'] = $title;
    require __DIR__ . '/header.php';
}

function pageEnd(): void {
    require __DIR__ . '/footer.php';
}

function errors(array $errors): void {
    if ($errors) {
        echo '<div class="notice error" role="alert">' .
            implode('<br>', array_map('e', $errors)) .
            '</div>';
    }
}

function cartItems(bool $lock = false): array {
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT * FROM products WHERE id IN ($placeholders) ORDER BY id";

    // Checkout mengunci produk agar harga tidak berubah saat pesanan disimpan.
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }

    $rows = db($sql, $ids)->fetchAll();

    if (count($rows) !== count($ids)) {
        throw new InvalidArgumentException(
            'Produk dalam keranjang tidak ditemukan. Hapus item dan coba lagi.'
        );
    }
    foreach ($rows as &$row) {
        $row['quantity'] = quantity($cart[$row['id']]);
        if (!$row['is_available']) {
            throw new InvalidArgumentException(
                $row['name'] . ' sedang tidak tersedia. Hapus dari keranjang.'
            );
        }
    }
    return $rows;
}

function dateFilters(): array {
    $from = input('from', $_GET) ?: date('Y-m-d', strtotime('-29 days'));
    $to = input('to', $_GET) ?: date('Y-m-d');
    foreach ([$from, $to] as $value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            abortRequest(400, 'Tanggal tidak valid.');
        }
    }
    if ($from > $to) {
        abortRequest(400, 'Rentang tanggal tidak valid.');
    }
    return [$from, $to];
}

// Filter kepemilikan diterapkan dalam query, bukan hanya pada tombol antarmuka.
function orderScope(int $id): array {
    $user = currentUser();
    $sql =
        '
            SELECT
                o.*,
                COALESCE(u.name, o.guest_name) customer_name,
                c.name cashier_name,
                u.email customer_email
            FROM orders o
                LEFT JOIN users u ON u.id = o.user_id
                LEFT JOIN users c ON c.id = o.cashier_id
            WHERE
                o.id = ?
        ';
    $args = [$id];
    if (($user['role'] ?? '') === 'cashier') {
        $sql .= ' AND o.cashier_id=?';
        $args[] = $user['id'];
    } elseif (($user['role'] ?? '') === 'customer') {
        $sql .= ' AND o.user_id=?';
        $args[] = $user['id'];
    } elseif (($user['role'] ?? '') !== 'owner') {
        $sessionToken = $_SESSION['guest_tokens'][$id] ?? '';
        $token = input('token', $_GET) ?: $sessionToken;
        if (!$token) {
            abortRequest(404, 'Pesanan tidak ditemukan.');
        }
        $sql .= ' AND o.user_id IS NULL AND o.guest_token_hash=?';
        $args[] = hash('sha256', $token);
    }
    $order = db($sql, $args)->fetch();
    if (!$order) {
        abortRequest(404, 'Pesanan tidak ditemukan.');
    }
    return $order;
}
