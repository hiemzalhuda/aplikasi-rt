<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/koneksi.php';

$user = require_login();
$title = 'Dashboard';
$menu = 'dashboard';

$today = date('Y-m-d');
$bulan_ini = date('Y-m');

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
$bln_pendek = array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
    7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des');
$tgl_indo_full = $hari_id[(int) date('w')] . ', ' . date('d') . ' '
    . $bulan_id[(int) date('n')] . ' ' . date('Y');

// ---- Kartu statistik (query existing, dipertahankan) ----
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

// ---- Seri 7 hari: kehadiran + setoran (aditif, default 0) ----
$chart_labels = array(); $chart_hadir = array(); $chart_setoran = array();
$map_hadir = array(); $map_setor = array();
$rows_h = db_all($koneksi,
    "SELECT s.tanggal AS tgl, COUNT(DISTINCT k.santri_id) AS c
     FROM kehadiran k
     JOIN sesi_kehadiran s ON s.id = k.sesi_id
     WHERE s.tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND k.status = 'hadir'
     GROUP BY s.tanggal");
foreach ($rows_h as $r) { $map_hadir[$r['tgl']] = (int) $r['c']; }
$rows_s = db_all($koneksi,
    "SELECT tanggal AS tgl, COUNT(*) AS c
     FROM setoran_hafalan
     WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY tanggal");
foreach ($rows_s as $r) { $map_setor[$r['tgl']] = (int) $r['c']; }
for ($d = 6; $d >= 0; $d--) {
    $dt = date('Y-m-d', strtotime("-$d days"));
    $ts = strtotime($dt);
    $chart_labels[]  = date('d', $ts) . ' ' . $bln_pendek[(int) date('n', $ts)];
    $chart_hadir[]   = isset($map_hadir[$dt]) ? $map_hadir[$dt] : 0;
    $chart_setoran[] = isset($map_setor[$dt]) ? $map_setor[$dt] : 0;
}
$json_aktivitas = json_encode(array(
    'label'   => $chart_labels,
    'hadir'   => $chart_hadir,
    'setoran' => $chart_setoran,
));

// ---- Donat: komposisi santri aktif per asrama (aditif) ----
$asrama_labels = array(); $asrama_data = array();
$rows_a = db_all($koneksi,
    "SELECT a.nama AS nama, COUNT(DISTINCT ps.santri_id) AS c
     FROM penempatan_santri ps
     JOIN kamar k ON k.id = ps.kamar_id
     JOIN asrama a ON a.id = k.asrama_id
     JOIN santri s ON s.id = ps.santri_id
     WHERE ps.tgl_selesai IS NULL AND s.status = 'aktif'
     GROUP BY a.id, a.nama
     ORDER BY c DESC");
foreach ($rows_a as $r) {
    $asrama_labels[] = $r['nama'];
    $asrama_data[]   = (int) $r['c'];
}
$json_asrama = json_encode(array('label' => $asrama_labels, 'data' => $asrama_data));
$asrama_kosong = empty($asrama_data);

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
    @keyframes fhFloat1 { from { transform: translate(0,0) scale(1); } to { transform: translate(24px,30px) scale(1.06); } }
    @keyframes fhFloat2 { from { transform: translate(0,0) scale(1); } to { transform: translate(-30px,-22px) scale(1.08); } }
    @keyframes fhFloat3 { from { transform: translate(0,0); } to { transform: translate(-18px,20px); } }
    @keyframes fhFloat4 { from { transform: translate(0,0) scale(1); } to { transform: translate(20px,-18px) scale(1.05); } }
    .fh-left { position: relative; z-index: 2; }
    .fh-badge {
        display: inline-block;
        background: rgba(255,255,255,.45);
        border: 1px solid rgba(23,33,17,.12);
        color: #2F5B22;
        font-size: 11px; font-weight: 800;
        letter-spacing: .12em; text-transform: uppercase;
        padding: 6px 14px; border-radius: 999px;
        margin-bottom: 10px;
    }
    .fh-left h1 { margin: 0 0 6px; font-size: 28px; font-weight: 800; letter-spacing: -.02em; color: #172111; }
    .fh-date { margin: 0; font-size: 14px; font-weight: 600; color: #3E5A34; }
    .fh-actions { position: relative; z-index: 2; display: flex; gap: 10px; flex-wrap: wrap; }
    .fh-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 20px; border-radius: 14px;
        font-family: var(--font); font-size: 14px; font-weight: 700;
        text-decoration: none; cursor: pointer;
        transition: transform .35s var(--ease-spring), box-shadow .3s var(--ease-out), background .2s;
    }
    .fh-btn:active { transform: scale(.94); }
    .fh-btn-filled { background: #fff; color: #2F6B23; box-shadow: 0 4px 14px rgba(46,90,30,.22); }
    .fh-btn-filled:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(46,90,30,.28); }
    .fh-btn-tonal { background: rgba(255,255,255,.28); color: #172111; border: 1px solid rgba(23,33,17,.22); }
    .fh-btn-tonal:hover { background: rgba(255,255,255,.45); transform: translateY(-2px); }

    /* ============ Kartu stat ala FINO (ikon + count-up) ============ */
    .fh-stat { display: flex; gap: 14px; align-items: flex-start; }
    .fh-ic {
        width: 48px; height: 48px; border-radius: 16px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; font-weight: 800; color: #fff;
    }
    .fh-ic.g1 { background: linear-gradient(135deg, #75BF43, #5EA332); box-shadow: 0 4px 12px rgba(117,191,67,.35); }
    .fh-ic.g2 { background: linear-gradient(135deg, #8FD0A8, #4E9B6A); box-shadow: 0 4px 12px rgba(78,155,106,.35); }
    .fh-ic.g3 { background: linear-gradient(135deg, #B9DE96, #7FBF5A); box-shadow: 0 4px 12px rgba(127,191,90,.35); }
    .fh-ic.g4 { background: linear-gradient(135deg, #F2C14E, #D9A021); box-shadow: 0 4px 12px rgba(217,160,33,.35); }
    .fh-stat-body { min-width: 0; }
    .fh-stat .stat-num { font-size: 32px; }

    /* ============ Grafik ============ */
    .fh-chart { position: relative; height: 260px; }
    .fh-chart-legend { display: flex; gap: 16px; margin-top: 12px; font-size: 12.5px; font-weight: 600; color: var(--text-soft); }
    .fh-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; vertical-align: baseline; }

    /* ============ Stagger reveal M3 ============ */
    .fh-reveal { animation: fhIn .55s var(--ease-spring) both; animation-delay: calc(var(--i, 0) * .07s); }
    @keyframes fhIn { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }

    /* ============ Aksi cepat ============ */
    .fh-quick { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .fh-quick a {
        display: flex; flex-direction: column; gap: 6px;
        background: var(--banner); border: 1px solid var(--border);
        border-radius: 14px; padding: 16px;
        text-decoration: none; color: var(--text);
        font-weight: 700; font-size: 14px;
        transition: transform .35s var(--ease-spring), box-shadow .3s var(--ease-out), background .2s;
    }
    .fh-quick a:hover { transform: translateY(-3px); box-shadow: var(--shadow-pop); background: var(--card); }
    .fh-quick a span { font-size: 12px; font-weight: 600; color: var(--text-soft); }
    .fh-quick .q-ic { font-size: 24px; }

    /* ============ Dark mode: hero tetap hijau tapi lebih pekat ============ */
    body.dark-mode .fh-hero { background: linear-gradient(135deg, #33511F 0%, #3E6327 55%, #2C471C 100%); }
    body.dark-mode .fh-left h1 { color: #F2F7EC; }
    body.dark-mode .fh-date { color: #D9E8C9; }
    body.dark-mode .fh-badge { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.22); color: #EAF3DC; }
    body.dark-mode .fh-btn-filled { background: #EDF4EA; color: #2F5B22; }
    body.dark-mode .fh-btn-tonal { background: rgba(255,255,255,.12); color: #F2F7EC; border-color: rgba(255,255,255,.28); }
    body.dark-mode .fh-btn-tonal:hover { background: rgba(255,255,255,.22); }

    @media (max-width: 640px) {
        .fh-hero { padding: 22px 20px; }
        .fh-left h1 { font-size: 22px; }
        .fh-actions { width: 100%; }
        .fh-btn { flex: 1; justify-content: center; }
        .fh-chart { height: 220px; }
    }
</style>

<!-- ==================== HERO GREETING ==================== -->
<div class="fh-hero fh-reveal" style="--i:0">
    <div class="fh-orbs">
        <div class="fh-orb o1"></div>
        <div class="fh-orb o2"></div>
        <div class="fh-orb o3"></div>
        <div class="fh-orb o4"></div>
    </div>
    <div class="fh-left">
        <div class="fh-badge">Pondok Pesantren Fath Darut Tafsir</div>
        <h1><?= $sapaan ?>, <?= e($user['nama']) ?></h1>
        <p class="fh-date"><?= e($tgl_indo_full) ?></p>
    </div>
    <div class="fh-actions">
        <a href="<?= url('modules/santri/') ?>" class="fh-btn fh-btn-filled">+ Tambah Santri</a>
        <a href="<?= url('modules/kehadiran/') ?>" class="fh-btn fh-btn-tonal">Absensi Hari Ini</a>
    </div>
</div>

<!-- ==================== KARTU STATISTIK ==================== -->
<div class="grid grid-4">
    <div class="card fh-reveal" style="--i:1">
        <div class="fh-stat">
            <div class="fh-ic g1">&#9679;</div>
            <div class="fh-stat-body">
                <h3>Total Santri Aktif</h3>
                <div class="stat-num" data-count="<?= $total_santri ?>">0</div>
                <div class="stat-sub">santri terdaftar aktif</div>
            </div>
        </div>
    </div>
    <div class="card fh-reveal" style="--i:2">
        <div class="fh-stat">
            <div class="fh-ic g2">&#9679;</div>
            <div class="fh-stat-body">
                <h3>Kehadiran Hari Ini</h3>
                <div class="stat-num" data-count="<?= $hadir_hari_ini ?>">0</div>
                <div class="stat-sub">santri tercatat hadir</div>
            </div>
        </div>
    </div>
    <div class="card fh-reveal" style="--i:3">
        <div class="fh-stat">
            <div class="fh-ic g3">&#9679;</div>
            <div class="fh-stat-body">
                <h3>Setoran Hafalan Hari Ini</h3>
                <div class="stat-num" data-count="<?= $setoran_hari_ini ?>">0</div>
                <div class="stat-sub">setoran tercatat</div>
            </div>
        </div>
    </div>
    <div class="card fh-reveal" style="--i:4">
        <div class="fh-stat">
            <div class="fh-ic g4">&#9679;</div>
            <div class="fh-stat-body">
                <h3>Tunggakan SPP</h3>
                <div class="stat-num" data-count="<?= $tunggakan_c ?>">0</div>
                <div class="stat-sub"><?= e(rupiah($tunggakan_rp)) ?> &middot; bulan ini</div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== GRAFIK ==================== -->
<h2 class="section-title fh-reveal" style="--i:5">Grafik</h2>
<div class="grid grid-2">
    <div class="card fh-reveal" style="--i:6">
        <h3>Aktivitas 7 Hari Terakhir</h3>
        <div class="fh-chart"><canvas id="chAktivitas"></canvas></div>
        <div class="fh-chart-legend">
            <span><span class="fh-dot" style="background:#75BF43"></span>Kehadiran</span>
            <span><span class="fh-dot" style="background:#2E8B57"></span>Setoran Hafalan</span>
        </div>
    </div>
    <div class="card fh-reveal" style="--i:7">
        <h3>Komposisi Santri per Asrama</h3>
        <?php if ($asrama_kosong): ?>
            <div class="empty">Belum ada data penempatan asrama.</div>
        <?php else: ?>
            <div class="fh-chart"><canvas id="chAsrama"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== RINGKASAN ==================== -->
<h2 class="section-title fh-reveal" style="--i:8">Ringkasan</h2>
<div class="grid grid-2">
    <div class="card fh-reveal" style="--i:9">
        <h3>Pelanggaran Bulan Ini</h3>
        <div class="stat-num" data-count="<?= $pelanggaran_bulan ?>">0</div>
        <div class="stat-sub">catatan pelanggaran tercatat</div>
    </div>
    <div class="card fh-reveal" style="--i:10">
        <h3>Aksi Cepat</h3>
        <div class="fh-quick">
            <a href="<?= url('modules/santri/') ?>"><span class="q-ic">+</span>Santri<span>Kelola data santri</span></a>
            <a href="<?= url('modules/kehadiran/') ?>"><span class="q-ic">&#10003;</span>Kehadiran<span>Absensi harian</span></a>
            <a href="<?= url('modules/hafalan/') ?>"><span class="q-ic">&#9733;</span>Hafalan<span>Setoran &amp; target</span></a>
            <a href="<?= url('modules/keuangan/') ?>"><span class="q-ic">Rp</span>Keuangan<span>SPP &amp; tabungan</span></a>
        </div>
    </div>
</div>

<script>
// ---------- Count-up M3 ----------
(function () {
    function countUp(el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        var dur = 900, t0 = null;
        function step(ts) {
            if (!t0) t0 = ts;
            var p = Math.min((ts - t0) / dur, 1);
            var e = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * e).toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    document.querySelectorAll('[data-count]').forEach(countUp);
})();

// ---------- Grafik (dark-aware) ----------
function santriChartColors() {
    var dark = document.body.classList.contains('dark-mode');
    return {
        tick: dark ? '#8B9C83' : '#6F7F62',
        grid: dark ? '#2B3824' : '#EAF3E1',
        donutBorder: dark ? '#1C2418' : '#FFFFFF'
    };
}
window.__santriCharts = [];
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif";
    var cc = santriChartColors();
    Chart.defaults.color = cc.tick;

    var elA = document.getElementById('chAktivitas');
    if (elA) {
        var d = <?= $json_aktivitas ?>;
        var chA = new Chart(elA, {
            data: {
                labels: d.label,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Kehadiran',
                        data: d.hadir,
                        backgroundColor: '#75BF43',
                        hoverBackgroundColor: '#5EA332',
                        borderRadius: 7,
                        maxBarThickness: 30
                    },
                    {
                        type: 'line',
                        label: 'Setoran Hafalan',
                        data: d.setoran,
                        borderColor: '#2E8B57',
                        backgroundColor: '#2E8B57',
                        borderWidth: 2.5,
                        tension: 0.4,
                        pointRadius: 3,
                        pointBackgroundColor: '#2E8B57'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, color: cc.tick }, grid: { color: cc.grid } },
                    x: { grid: { display: false }, ticks: { color: cc.tick } }
                },
                animation: { duration: 1100, easing: 'easeOutQuart' }
            }
        });
        window.__santriCharts.push(chA);
    }

    var elD = document.getElementById('chAsrama');
    if (elD) {
        var a = <?= $json_asrama ?>;
        var chD = new Chart(elD, {
            type: 'doughnut',
            data: {
                labels: a.label,
                datasets: [{
                    data: a.data,
                    backgroundColor: ['#75BF43','#4E8C2B','#A3D977','#2E8B57','#D7E8C3','#8FCE62'],
                    borderWidth: 3,
                    borderColor: cc.donutBorder,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, boxHeight: 12, borderRadius: 6, useBorderRadius: true, padding: 14, color: cc.tick } }
                },
                animation: { animateRotate: true, duration: 1200, easing: 'easeOutQuart' }
            }
        });
        window.__santriCharts.push(chD);
    }

    // Perbarui warna grafik saat dark mode di-toggle (event dari footer.php)
    document.addEventListener('santri:theme', function () {
        var c2 = santriChartColors();
        Chart.defaults.color = c2.tick;
        window.__santriCharts.forEach(function (ch) {
            if (ch.config.type === 'doughnut') {
                ch.data.datasets[0].borderColor = c2.donutBorder;
                if (ch.options.plugins && ch.options.plugins.legend && ch.options.plugins.legend.labels) {
                    ch.options.plugins.legend.labels.color = c2.tick;
                }
            } else {
                if (ch.options.scales.y) {
                    ch.options.scales.y.grid.color = c2.grid;
                    ch.options.scales.y.ticks.color = c2.tick;
                }
                if (ch.options.scales.x && ch.options.scales.x.ticks) ch.options.scales.x.ticks.color = c2.tick;
            }
            ch.update();
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
