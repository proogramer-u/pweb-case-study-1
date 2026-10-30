<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (currentUser()) {
    redirect(roleHome(currentUser()));
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    $name = input('name');
    $email = strtolower(input('email'));
    $password = input('password');
    if (!$name || strlen($name) > 150) {
        $errors[] = 'Nama wajib diisi, maksimal 150 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Email tidak valid.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Kata sandi harus 8–72 byte.';
    }
    if (!$errors) {
        try {
            db("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'customer')", [
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            flash('Akun berhasil dibuat. Silakan masuk untuk diskon 10%.');
            redirect('auth/login.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Email sudah terdaftar.';
            } else {
                throw $exception;
            }
        }
    }
}
pageStart('Daftar');
?>

<section class="panel narrow">
    <p class="eyebrow">Teman ngopi baru</p>
    <h1>Kenalan dulu, yuk.</h1>
    <p class="muted">Daftar dan nikmati diskon 10% untuk setiap pesanan.</p>

    <?php errors($errors); ?>

    <form method="post">

        <?= csrfField() ?>
        <label for="name">Nama</label>
        <input id="name" name="name" maxlength="150" autocomplete="name" value="<?= e(input('name')) ?>"
            required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" maxlength="190" autocomplete="email"
            value="<?= e(input('email')) ?>" required>
        <label for="password">Kata sandi</label>
        <input id="password" name="password" type="password" minlength="8" maxlength="72"
            autocomplete="new-password" required>
        <button type="submit">Buat akun</button>
    </form>
    <p>Sudah punya akun? <a href="<?= url('auth/login.php') ?>">Masuk</a>
    </p>
</section>

<?php pageEnd(); ?>
