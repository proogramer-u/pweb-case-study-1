<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole('owner');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePost();
    $name = input('name');
    $email = strtolower(input('email'));
    $password = input('password');
    if (!$name || strlen($name) > 150) {
        $errors[] = 'Nama harus 1–150 karakter.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Email tidak valid.';
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        $errors[] = 'Kata sandi kasir harus 12–72 byte.';
    }
    if (!$errors) {
        try {
            db("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'cashier')", [
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            flash('Akun kasir dibuat.');
            redirect('admin/cashiers.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $errors[] = 'Email sudah terdaftar.';
            } else {
                throw $exception;
            }
        }
    }
}
$cashiers = db(
    "SELECT id,name,email,created_at FROM users WHERE role='cashier' ORDER BY name"
)->fetchAll();
pageStart('Kasir');
?>

<h1>Tim kasir.</h1>
<p class="muted">Setiap kasir hanya dapat membuka pesanan yang ditugaskan kepadanya.</p>

<?php errors($errors); ?>

<div class="split">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($cashiers as $cashier): ?>
                    <tr>
                        <td><?= e($cashier['name']) ?></td>
                        <td><?= e($cashier['email']) ?></td>
                    </tr>
                <?php endforeach;
                if (!$cashiers): ?>

                <tr>
                    <td colspan="2">Belum ada akun kasir.</td>
                </tr>
                <?php endif;
                ?>

            </tbody>
        </table>
    </div>
    <section class="panel">
        <h2>Akun kasir baru</h2>
        <form method="post">

            <?= csrfField() ?>
            <label for="name">Nama</label>
            <input id="name" name="name" maxlength="150" value="<?= e(input('name')) ?>" required>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="190" value="<?= e(input('email')) ?>"
                required>
            <label for="password">Kata sandi awal (12+ karakter)</label>
            <input id="password" name="password" type="password" minlength="12" maxlength="72"
                autocomplete="new-password" required>
            <button type="submit">Buat akun kasir</button>
        </form>
    </section>
</div>

<?php pageEnd(); ?>
