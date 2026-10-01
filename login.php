<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/config/koneksi.php';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $row = db_one($koneksi, 'SELECT * FROM users WHERE username = ? AND aktif = 1 LIMIT 1', 's', array($username));
    if ($row && password_verify($password, $row['password_hash'])) {
        login_user($row);
        redirect('index.php');
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — <?= e(defined('APP_NAME') ? APP_NAME : 'Manajemen Santri') ?></title>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
</head>
<body>
<div class="login-wrap">
    <form class="login-card" method="post" action="">
        <h2><?= e(defined('APP_NAME') ? APP_NAME : 'Manajemen Santri') ?></h2>
        <p>Sistem informasi pondok pesantren</p>
        <?php if ($error): ?>
            <div class="alert alert-err"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus autocomplete="username">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn">Masuk</button>
    </form>
</div>
</body>
</html>
