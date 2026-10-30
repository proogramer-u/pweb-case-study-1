<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (currentUser()) {
    redirect(roleHome(currentUser()));
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    $user = db('SELECT * FROM users WHERE email=?', [strtolower(input('email'))])->fetch();
    $valid = password_verify(
        input('password'),
        $user['password'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi'
    );
    if (!$user || !$valid || strlen(input('password')) > 72) {
        $errors[] = 'Email atau kata sandi salah.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['checkout_key']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        redirect(roleHome($user));
    }
}
pageStart('Masuk');
?>

<section class="panel narrow">
    <p class="eyebrow">Selamat datang kembali</p>
    <h1>Waktunya ngopi.</h1>
    <p class="muted">Masuk untuk menikmati diskon dan melihat pesananmu.</p>

    <?php errors($errors); ?>

    <form method="post">

        <?= csrfField() ?>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="email" maxlength="190"
            value="<?= e(input('email')) ?>" required>
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" autocomplete="current-password"
            required>
        <button type="submit">Masuk</button>
    </form>
    <p>Belum punya akun? <a href="<?= url('auth/register.php') ?>">Daftar</a>
    </p>
    <a href="<?= url('products.php') ?>">Lanjut sebagai tamu</a>
</section>

<?php pageEnd(); ?>
