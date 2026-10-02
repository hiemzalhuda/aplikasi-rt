<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/koneksi.php';

$user = require_login();
$title = 'Dashboard';
$menu = 'dashboard';

$today = date('Y-m-d');
$periode_ini = date('Y-m');

// ---- Sapaan berdasar jam ----
$jam = (int) date('H');
if ($jam >= 5 && $jam < 11) {
    $sapaan = 'Selamat Pagi';
} elseif ($jam >= 11 && $jam < 15) {
    $sapaan = 'Selamat Siang';
} elseif ($jam >= 15 && $jam < 18) {
    $sapaan = 'Selamat Sore';
} else {
    $sapaan = 'Selamat Malam';
}

// ---- Tanggal Indonesia lengkap ----
$hari_id = array('Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu');
$bulan_id = array(1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember');
$tgl_indo_full = $hari_id[(int) date('w')] . ', ' . date('d') . ' '
    . $bulan_id[(int) date('n')] . ' ' . date('Y');

// ---- Kartu statistik ----
$total_warga = db_one($koneksi, 'SELECT COUNT(*) AS c FROM warga');
$total_warga = $total_warga ? (int) $total_warga['c'] : 0;

$total_kk = db_one($koneksi, 'SELECT COUNT(DISTINCT no_kk) AS c FROM warga');
$total_kk = $total_kk ? (int) $total_kk['c'] : 0;

$saldo = saldo_kas($koneksi);

$iuran = db_one($koneksi,
    "SELECT COUNT(*) AS total, SUM(status = 'lunas') AS lunas FROM iuran WHERE periode = ?",
    's', array($periode_ini));
$iuran_total = $iuran ? (int) $iuran['total'] : 0;
$iuran_lunas = $iuran ? (int) $iuran['lunas'] : 0;

// ---- Grafik arus kas 6 bulan terakhir ----
$kas_labels = array(); $kas_masuk = array(); $kas_keluar = array();
$bln_pendek = array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
    7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des');
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i month"));
    $kas_labels[] = $bln_pendek[(int) date('n', strtotime($ym . '-01'))];
    $rm = db_one($koneksi,
        "SELECT COALESCE(SUM(jumlah),0) AS s FROM kas_transaksi WHERE jenis='masuk' AND DATE_FORMAT(tanggal,'%Y-%m') = ?",
        's', array($ym));
    $rk = db_one($koneksi,
        "SELECT COALESCE(SUM(jumlah),0) AS s FROM kas_transaksi WHERE jenis='keluar' AND DATE_FORMAT(tanggal,'%Y-%m') = ?",
        's', array($ym));
    $kas_masuk[]  = (int) ($rm['s'] ?? 0);
    $kas_keluar[] = (int) ($rk['s'] ?? 0);
}
$json_kas = json_encode(array('label' => $kas_labels, 'masuk' => $kas_masuk, 'keluar' => $kas_keluar));

// ---- Kegiatan terdekat ----
$kegiatan = db_all($koneksi,
    'SELECT id, nama, tanggal, waktu, tempat FROM kegiatan WHERE tanggal >= ? ORDER BY tanggal ASC LIMIT 5',
    's', array($today));

// ---- Pengumuman aktif terbaru ----
$pengumuman = db_all($koneksi,
    'SELECT id, judul, isi, tanggal FROM pengumuman WHERE aktif = 1 ORDER BY tanggal DESC, id DESC LIMIT 3');

// ---- Laporan terbaru ----
$laporan_baru = db_one($koneksi, "SELECT COUNT(*) AS c FROM laporan WHERE status = 'baru'");
$laporan_baru = $laporan_baru ? (int) $laporan_baru['c'] : 0;
$laporan = db_all($koneksi,
    'SELECT id, judul, kategori, tanggal, status FROM laporan ORDER BY tanggal DESC, id DESC LIMIT 5');

include __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    /* ============ FINO HERO (greeting) — adaptasi dashboard WMS ============ */
    .fh-hero {
        background: linear-gradient(135deg, #C4E69A 0%, #B0DC80 55%, #9CD165 100%);
        border-radius: 22px;
        padding: 26px 30px;
        color: #172111;
        position: relative;
        overflow: hidden;
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: var(--shadow-card);
    }
    .fh-orbs { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
    .fh-orb { position: absolute; border-radius: 50%; background: rgba(255,255,255,.38); }
    .fh-orb.o1 { width: 260px; height: 260px; top: -110px; left: -70px; animation: fhFloat1 9s ease-in-out infinite alternate; }
    .fh-orb.o2 { width: 380px; height: 380px; bottom: -190px; right: 8%; opacity: .55; animation: fhFloat2 12s ease-in-out infinite alternate-reverse; }
    .fh-orb.o3 { width: 110px; height: 110px; top: 26%; left: 44%; animation: fhFloat3 7s ease-in-out infinite alternate; }
    .fh-orb.o4 { width: 170px; height: 170px; bottom: -70px; left: 24%; opacity: .6; animation: fhFloat4 11s ease-in-out infinite alternate; }

<?php
// ---- Data turunan untuk tampilan baru ----
$nama_depan = strtok(trim($user['nama'] ?? 'Pengguna'), ' ');
$tgl_caps = mb_strtoupper($tgl_indo_full, 'UTF-8');
$iuran_persen = $iuran_total > 0 ? (int) round($iuran_lunas / $iuran_total * 100) : 0;
$bulan_nama = $bulan_id[(int) date('n')];

// Kalender bulan berjalan
$cal_y = (int) date('Y'); $cal_m = (int) date('n'); $cal_today = (int) date('j');
$cal_first = (int) date('w', mktime(0, 0, 0, $cal_m, 1, $cal_y));
$cal_days = (int) date('t', mktime(0, 0, 0, $cal_m, 1, $cal_y));
$nama_hari_pendek = array('S', 'S', 'R', 'K', 'J', 'S', 'M');

$role = $user['role'] ?? '';
$boleh_keuangan = in_array($role, array('admin', 'ketua', 'bendahara'), true);
$boleh_warga = in_array($role, array('admin', 'ketua', 'sekretaris'), true);

include __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dash-date"><?= e($tgl_caps) ?></div>
<h1 class="dash-greet"><?= e($sapaan) ?>, <?= e($nama_depan) ?>.</h1>

<div class="dash-grid">
    <div>
        <div class="dash-row2">
            <div class="card dash-clock">
                <div class="ck-ic"><i class="fa-regular fa-clock"></i></div>
                <div>
                    <h3>Waktu saat ini</h3>
                    <div class="ck-time" id="dashClockTime">--:--:--</div>
                    <div class="ck-sub"><?= e($tgl_indo_full) ?></div>
                </div>
            </div>
            <div class="card dash-prog">
                <h3>Progress iuran</h3>
                <div class="pg-top"><span class="pg-num"><?= $iuran_persen ?>%</span></div>
                <div class="pg-bar"><div class="pg-fill" style="width: <?= $iuran_persen ?>%"></div></div>
                <div class="pg-sub"><?= $iuran_lunas ?> dari <?= $iuran_total ?> KK lunas &middot; <?= e($bulan_nama) ?> <?= e(date('Y')) ?></div>
            </div>
        </div>

        <div class="dash-sec">Ringkasan</div>
        <div class="dash-stats">
            <div class="card dash-stat">
                <div class="st-ic c-blue"><i class="fa-solid fa-users"></i></div>
                <div><div class="st-num"><?= number_format($total_warga) ?></div><div class="st-lbl">Total Warga</div></div>
            </div>
            <div class="card dash-stat">
                <div class="st-ic c-green"><i class="fa-solid fa-house-user"></i></div>
                <div><div class="st-num"><?= number_format($total_kk) ?></div><div class="st-lbl">Kepala Keluarga</div></div>
            </div>
            <div class="card dash-stat">
                <div class="st-ic c-amber"><i class="fa-solid fa-wallet"></i></div>
                <div><div class="st-num" style="font-size:20px;padding-top:4px"><?= e(rupiah($saldo)) ?></div><div class="st-lbl">Saldo Kas</div></div>
            </div>
            <div class="card dash-stat">
                <div class="st-ic c-red"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <div><div class="st-num"><?= $iuran_lunas ?>/<?= $iuran_total ?></div><div class="st-lbl">Iuran Lunas</div></div>
            </div>
        </div>

        <?php if ($boleh_keuangan): ?>
        <div class="dash-sec">Arus Kas</div>
        <div class="card">
            <h3><i class="fa-solid fa-chart-column card-ico"></i>Kas 6 bulan terakhir</h3>
            <div class="dash-chart"><canvas id="kasChart"></canvas></div>
            <div class="dash-legend">
                <span><span class="dash-dot" style="background:#4F46E5"></span>Masuk</span>
                <span><span class="dash-dot" style="background:#E5484D"></span>Keluar</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="dash-sec">Aksi Cepat</div>
        <div class="dash-quick">
            <?php if ($boleh_warga): ?>
            <a href="<?= url('modules/warga/') ?>"><span class="q-ic" style="color:#4F46E5"><i class="fa-solid fa-user-plus"></i></span>Kelola Warga</a>
            <?php endif; ?>
            <?php if ($boleh_keuangan): ?>
            <a href="<?= url('modules/keuangan/') ?>"><span class="q-ic" style="color:#1C9A52"><i class="fa-solid fa-coins"></i></span>Catat Kas</a>
            <?php endif; ?>
            <?php if ($boleh_warga): ?>
            <a href="<?= url('modules/surat/') ?>"><span class="q-ic" style="color:#C07E10"><i class="fa-solid fa-file-lines"></i></span>Buat Surat</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="rail">
        <div class="card dash-cal">
            <h3><?= e($bulan_nama) ?> <?= e($cal_y) ?></h3>
            <table>
                <tr><?php foreach ($nama_hari_pendek as $h): ?><th><?= $h ?></th><?php endforeach; ?></tr>
                <?php
                $d = 1;
                echo '<tr>';
                for ($i = 0; $i < $cal_first; $i++) echo '<td class="dim"></td>';
                while ($d <= $cal_days) {
                    if (($cal_first + $d - 1) % 7 === 0 && $d > 1) echo '</tr><tr>';
                    $cls = $d === $cal_today ? 'today' : '';
                    echo '<td class="' . $cls . '"><span>' . $d . '</span></td>';
                    $d++;
                }
                $sisa = (7 - (($cal_first + $cal_days) % 7)) % 7;
                for ($i = 0; $i < $sisa; $i++) echo '<td class="dim"></td>';
                echo '</tr>';
                ?>
            </table>
        </div>

        <?php if ($boleh_warga): ?>
        <div class="card">
            <h3><i class="fa-solid fa-calendar-days card-ico"></i>Kegiatan Terdekat</h3>
            <?php if ($kegiatan): ?>
            <div class="todo-list">
                <?php foreach ($kegiatan as $k): ?>
                <div class="todo-item">
                    <span class="td-dot"></span>
                    <div>
                        <div class="td-title"><?= e($k['nama']) ?></div>
                        <div class="td-sub"><?= e(tgl_indo($k['tanggal'])) ?><?= $k['waktu'] ? ' &middot; ' . e(substr($k['waktu'], 0, 5)) : '' ?><?= $k['tempat'] ? ' &middot; ' . e($k['tempat']) : '' ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-empty">Belum ada kegiatan terjadwal.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-bullhorn card-ico"></i>Pengumuman</h3>
            <?php if ($pengumuman): ?>
            <div class="todo-list">
                <?php foreach ($pengumuman as $p): ?>
                <div class="todo-item">
                    <span class="td-dot" style="background:#C07E10"></span>
                    <div>
                        <div class="td-title"><?= e($p['judul']) ?></div>
                        <div class="td-sub"><?= e(tgl_indo($p['tanggal'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-empty">Belum ada pengumuman.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-inbox card-ico"></i>Laporan Warga
                <?php if ($laporan_baru > 0): ?><span class="badge badge-warn" style="margin-left:8px"><?= $laporan_baru ?> baru</span><?php endif; ?>
            </h3>
            <?php if ($laporan): ?>
            <div class="todo-list">
                <?php foreach ($laporan as $l): ?>
                <div class="todo-item">
                    <span class="td-dot" style="background:<?= $l['status'] === 'baru' ? '#E5484D' : '#1C9A52' ?>"></span>
                    <div>
                        <div class="td-title"><?= e($l['judul']) ?></div>
                        <div class="td-sub"><?= e(ucfirst($l['kategori'])) ?> &middot; <?= e(tgl_indo($l['tanggal'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-empty">Belum ada laporan.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    // Jam live
    var el = document.getElementById('dashClockTime');
    function tick() {
        var d = new Date();
        var p = function (n) { return (n < 10 ? '0' : '') + n; };
        el.textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }
    tick(); setInterval(tick, 1000);

    <?php if ($boleh_keuangan): ?>
    // Grafik kas
    var dark = document.body.classList.contains('dark-mode');
    var kd = <?= $json_kas ?>;
    new Chart(document.getElementById('kasChart'), {
        type: 'bar',
        data: {
            labels: kd.label,
            datasets: [
                { label: 'Masuk', data: kd.masuk, backgroundColor: '#4F46E5', borderRadius: 6, barPercentage: .6, categoryPercentage: .6 },
                { label: 'Keluar', data: kd.keluar, backgroundColor: '#E5484D', borderRadius: 6, barPercentage: .6, categoryPercentage: .6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: dark ? '#8E99B5' : '#6E7689', font: { size: 11 } } },
                y: { grid: { color: dark ? '#232C47' : '#EDEFF4' }, ticks: { color: dark ? '#8E99B5' : '#6E7689', font: { size: 11 }, maxTicksLimit: 5 } }
            }
        }
    });
    <?php endif; ?>
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
