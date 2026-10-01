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
    'nilai'       => array('Nilai',       'modules/nilai/'),
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
<title><?= e($title) ?> &mdash; <?= e(defined('APP_FULL') ? APP_FULL : 'Pondok Pesantren') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?= asset_v('assets/style.css') ?>">
</head>
<body>
<script>
/* Terapkan dark mode sebelum render (hindari flash) — pola WMS */
try { if (localStorage.getItem('santriDark') === '1') document.body.classList.add('dark-mode'); } catch (e) {}
</script>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">F</div>
            <div>
                <div class="brand-name"><?= e(defined('APP_NAME') ? APP_NAME : 'Fath Darut Tafsir') ?></div>
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
            <div class="topbar-widgets">
                <div class="topbar-date"><?= e(tgl_indo(date('Y-m-d'))) ?></div>
                <div class="tb-clock" title="Waktu sekarang (WIB)"><i class="fas fa-clock"></i><span id="tbClockTime">--:--</span></div>
                <div class="tb-prayer" id="tbPrayer" title="Jadwal sholat — klik untuk detail">
                    <i class="fas fa-mosque"></i>
                    <span class="tb-prayer-in"><small id="tbPrayerName">Memuat&hellip;</small><strong id="tbPrayerTime">--:--</strong></span>
                </div>
                <button class="icon-btn" id="darkModeToggle" title="Mode Gelap/Terang" aria-label="Mode Gelap/Terang"><i class="fas fa-moon" id="darkIcon"></i></button>
            </div>
        </header>
        <div class="content">
            <?= flash_get() ?>
