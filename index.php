<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/koneksi.php';

$user = require_login();
$title = 'Dashboard';
$menu = 'dashboard';

$today = date('Y-m-d');
$bulan_ini = date('Y-m');

$total_santri = db_one($koneksi,
    "SELECT COUNT(*) AS c FROM santri WHERE status = 'aktif'");
$total_santri = $total_santri ? (int) $total_santri['c'] : 0;

$hadir_hari_ini = db_one($koneksi,
    "SELECT COUNT(DISTINCT k.santri_id) AS c
     FROM kehadiran k
     JOIN sesi_kehadiran s ON s.id = k.sesi_id
     WHERE s.tanggal = ? AND k.status = 'hadir'", 's', array($today));
$hadir_hari_ini = $hadir_hari_ini ? (int) $hadir_hari_ini['c'] : 0;

$setoran_hari_ini = db_one($koneksi,
    "SELECT COUNT(*) AS c FROM setoran_hafalan WHERE tanggal = ?", 's', array($today));
$setoran_hari_ini = $setoran_hari_ini ? (int) $setoran_hari_ini['c'] : 0;

$tunggakan = db_one($koneksi,
    "SELECT COUNT(*) AS c, COALESCE(SUM(nominal),0) AS total
     FROM tagihan_spp WHERE bulan = ? AND status <> 'lunas'", 's', array($bulan_ini));
$tunggakan_c = $tunggakan ? (int) $tunggakan['c'] : 0;
$tunggakan_rp = $tunggakan ? (int) $tunggakan['total'] : 0;

$pelanggaran_bulan = db_one($koneksi,
    "SELECT COUNT(*) AS c FROM catatan_pelanggaran WHERE DATE_FORMAT(tanggal,'%Y-%m') = ?", 's', array($bulan_ini));
$pelanggaran_bulan = $pelanggaran_bulan ? (int) $pelanggaran_bulan['c'] : 0;

include __DIR__ . '/includes/header.php';
?>

<div class="grid grid-4">
    <div class="card">
        <h3>Total Santri Aktif</h3>
        <div class="stat-num"><?= $total_santri ?></div>
        <div class="stat-sub">santri terdaftar aktif</div>
    </div>
    <div class="card">
        <h3>Kehadiran Hari Ini</h3>
        <div class="stat-num"><?= $hadir_hari_ini ?></div>
        <div class="stat-sub">santri tercatat hadir</div>
    </div>
    <div class="card">
        <h3>Setoran Hafalan Hari Ini</h3>
        <div class="stat-num"><?= $setoran_hari_ini ?></div>
        <div class="stat-sub">setoran tercatat</div>
    </div>
    <div class="card">
        <h3>Tunggakan SPP Bulan Ini</h3>
        <div class="stat-num"><?= $tunggakan_c ?></div>
        <div class="stat-sub"><?= e(rupiah($tunggakan_rp)) ?></div>
    </div>
</div>

<h2 class="section-title">Ringkasan Cepat</h2>
<div class="grid grid-2">
    <div class="card">
        <h3>Pelanggaran Bulan Ini</h3>
        <div class="stat-num"><?= $pelanggaran_bulan ?></div>
        <div class="stat-sub">catatan pelanggaran tercatat</div>
    </div>
    <div class="card">
        <h3>Selamat datang, <?= e($user['nama']) ?></h3>
        <div class="stat-sub">
            Anda login sebagai <strong><?= e($user['role']) ?></strong>.
            Gunakan menu di samping untuk mengelola data santri, kehadiran,
            hafalan, keuangan, asrama, dan pelanggaran.
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
