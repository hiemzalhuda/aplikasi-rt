<?php
/**
 * Layout: $title (string), $menu (string, key menu aktif) diset sebelum include.
 * Membutuhkan: includes/auth.php + includes/functions.php sudah di-load.
 */
if (!isset($title)) $title = 'Dashboard';
if (!isset($menu)) $menu = 'dashboard';
$nav = array(
    'dashboard'   => array('Dashboard',   ''),
    'santri'      => array('Santri',      'modules/santri/'),
    'asrama'      => array('Asrama',      'modules/asrama/'),
    'kehadiran'   => array('Kehadiran',   'modules/kehadiran/'),
    'hafalan'     => array('Hafalan',     'modules/hafalan/'),
    'keuangan'    => array('Keuangan',    'modules/keuangan/'),
    'pelanggaran' => array('Pelanggaran', 'modules/pelanggaran/'),
);
$u = current_user();
// Menu manajemen pengguna hanya untuk admin
if ($u && $u['role'] === 'admin') {
    $nav['users'] = array('Pengguna', 'modules/users/');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — <?= e(defined('APP_NAME') ? APP_NAME : 'Manajemen Santri') ?></title>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">S</div>
            <div>
                <div class="brand-name"><?= e(defined('APP_NAME') ? APP_NAME : 'Manajemen Santri') ?></div>
                <div class="brand-sub">Pondok Pesantren</div>
            </div>
        </div>
        <nav class="nav">
            <?php foreach ($nav as $key => $item): ?>
            <a href="<?= url($item[1]) ?>" class="nav-link<?= $key === $menu ? ' active' : '' ?>">
                <span class="nav-dot"></span><?= e($item[0]) ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="side-user">
            <div class="side-user-name"><?= e($u ? $u['nama'] : '-') ?></div>
            <div class="side-user-role"><?= e($u ? $u['role'] : '-') ?></div>
            <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-ghost">Keluar</a>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <h1><?= e($title) ?></h1>
            <div class="topbar-date"><?= e(tgl_indo(date('Y-m-d'))) ?></div>
        </header>
        <div class="content">
            <?= flash_get() ?>
