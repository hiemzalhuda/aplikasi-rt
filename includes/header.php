<?php
/**
 * Layout: $title (string), $menu (string, key menu aktif) diset sebelum include.
 * Membutuhkan: includes/auth.php + includes/functions.php sudah di-load.
 */
if (!isset($title)) $title = 'Dashboard';
if (!isset($menu)) $menu = 'dashboard';
$nav = array(
    'dashboard'   => array('Dashboard',   '',                     'fa-solid fa-house'),
    'warga'       => array('Warga',       'modules/warga/',       'fa-solid fa-users'),
    'keuangan'    => array('Keuangan',    'modules/keuangan/',    'fa-solid fa-wallet'),
    'surat'       => array('Surat',       'modules/surat/',       'fa-solid fa-envelope-open-text'),
    'kegiatan'    => array('Kegiatan',    'modules/kegiatan/',    'fa-solid fa-calendar-days'),
    'pengumuman'  => array('Pengumuman',  'modules/pengumuman/',  'fa-solid fa-bullhorn'),
    'laporan'     => array('Laporan',     'modules/laporan/',     'fa-solid fa-inbox'),
);
$u = current_user();
// Menu manajemen pengguna hanya untuk admin
if ($u && $u['role'] === 'admin') {
    $nav['users'] = array('Pengguna', 'modules/users/', 'fa-solid fa-user-gear');
}
/* Lacak user online: catat aktivitas + hitung yg aktif 5 mnt terakhir */
$sn_online = 0;
if ($u && isset($koneksi)) {
    sentuh_online($koneksi, (int) $u['id']);
    $sn_online = hitung_online($koneksi);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> &mdash; <?= e(defined('APP_FULL') ? APP_FULL : 'Sistem Informasi RT') ?></title>
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
            <div class="brand-logo">R</div>
            <div>
                <div class="brand-name"><?= e(defined('APP_NAME') ? APP_NAME : 'SI-RT') ?></div>
                <div class="brand-sub"><?= e(rt_label()) ?></div>
            </div>
        </div>
        <nav class="nav">
            <?php foreach ($nav as $key => $item): ?>
            <a href="<?= url($item[1]) ?>" class="nav-link<?= $key === $menu ? ' active' : '' ?>">
                <i class="nav-ico <?= e($item[2]) ?>"></i><?= e($item[0]) ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="side-user">
            <div class="side-user-name"><?= e($u ? $u['nama'] : '-') ?></div>
            <div class="side-user-role"><?= e($u ? role_label($u['role']) : '-') ?></div>
            <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-ghost">Keluar</a>
        </div>
        <div class="side-foot">
            <span class="side-ver"><i class="fas fa-code-branch"></i>v<?= e(APP_VERSION) ?></span>
            <span class="side-online"><span class="dot-online"></span><?= (int) $sn_online ?> online</span>
        </div>
    </aside>
    <div class="sn-scrim" id="snScrim"></div>
    <main class="main">
        <header class="topbar">
            <button class="icon-btn sn-menu-btn" id="snMenuBtn" title="Menu" aria-label="Menu"><i class="fas fa-bars"></i></button>
            <h1><?= e($title) ?></h1>
            <div class="sn-search" id="snGlobalSearch">
                <form method="get" action="<?= url('modules/warga/') ?>" role="search" autocomplete="off">
                    <span class="sn-search-icon"><i class="fas fa-search"></i></span>
                    <input type="text" name="q" id="snGlobalSearchInput" class="sn-search-input"
                           placeholder="Cari warga (nama/NIK)..." autocomplete="off" aria-label="Cari warga">
                </form>
                <div class="sn-search-results" id="snGlobalSearchResults"></div>
            </div>
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
