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
    .fh-stat .stat-rp { font-size: 24px; font-weight: 800; letter-spacing: -.01em; }

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

    /* ============ Daftar ringkas dashboard ============ */
    .fh-list { display: flex; flex-direction: column; gap: 10px; }
    .fh-item {
        display: flex; gap: 12px; align-items: flex-start;
        background: var(--banner); border: 1px solid var(--border);
        border-radius: 12px; padding: 12px 14px;
    }
    .fh-item .fi-ic {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 15px; color: #fff;
        background: linear-gradient(135deg, #75BF43, #5EA332);
    }
    .fh-item .fi-body { min-width: 0; flex: 1; }
    .fh-item .fi-title { font-weight: 700; font-size: 13.5px; }
    .fh-item .fi-sub { font-size: 12px; color: var(--text-soft); margin-top: 2px; }
    .fh-empty { color: var(--text-soft); font-size: 13px; padding: 12px 4px; }
    .badge-st { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
    .badge-st.baru { background: #FFF3CD; color: #8a6d00; }
    .badge-st.diproses { background: #D6E9FF; color: #1d5fb8; }
    .badge-st.selesai { background: #D9F2DF; color: #2F6B23; }

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
        <div class="fh-badge"><?= e(rt_label()) ?></div>
        <h1><?= $sapaan ?>, <?= e($user['nama']) ?></h1>
        <p class="fh-date"><?= e($tgl_indo_full) ?></p>
    </div>
    <div class="fh-actions">
        <a href="<?= url('modules/warga/') ?>" class="fh-btn fh-btn-filled"><i class="fas fa-user-plus"></i>Tambah Warga</a>
        <a href="<?= url('modules/keuangan/') ?>" class="fh-btn fh-btn-tonal"><i class="fas fa-wallet"></i>Catat Kas</a>
        <a href="<?= url('modules/surat/') ?>" class="fh-btn fh-btn-tonal"><i class="fas fa-envelope-open-text"></i>Buat Surat</a>
    </div>
</div>

<!-- ==================== KARTU STATISTIK ==================== -->
<div class="grid grid-4">
    <div class="card fh-reveal" style="--i:1">
        <div class="fh-stat">
            <div class="fh-ic g1"><i class="fas fa-users"></i></div>
            <div class="fh-stat-body">
                <h3>Total Warga</h3>
                <div class="stat-num" data-count="<?= $total_warga ?>">0</div>
                <div class="stat-sub">jiwa terdaftar</div>
            </div>
        </div>
    </div>
    <div class="card fh-reveal" style="--i:2">
        <div class="fh-stat">
            <div class="fh-ic g2"><i class="fas fa-house-user"></i></div>
            <div class="fh-stat-body">
                <h3>Kartu Keluarga</h3>
                <div class="stat-num" data-count="<?= $total_kk ?>">0</div>
                <div class="stat-sub">KK terdaftar</div>
            </div>
        </div>
    </div>
    <div class="card fh-reveal" style="--i:3">
        <div class="fh-stat">
            <div class="fh-ic g3"><i class="fas fa-wallet"></i></div>
            <div class="fh-stat-body">
                <h3>Saldo Kas RT</h3>
                <div class="stat-rp"><?= e(rupiah($saldo)) ?></div>
                <div class="stat-sub">kas saat ini</div>
            </div>
        </div>
    </div>
    <div class="card fh-reveal" style="--i:4">
        <div class="fh-stat">
            <div class="fh-ic g4"><i class="fas fa-hand-holding-dollar"></i></div>
            <div class="fh-stat-body">
                <h3>Iuran <?= e(periode_indo($periode_ini)) ?></h3>
                <div class="stat-num" data-count="<?= $iuran_lunas ?>">0</div>
                <div class="stat-sub">dari <?= $iuran_total ?> KK sudah lunas</div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== GRAFIK ARUS KAS ==================== -->
<h2 class="section-title fh-reveal" style="--i:5"><i class="fas fa-chart-column sec-ico"></i>Arus Kas</h2>
<div class="grid grid-1">
    <div class="card fh-reveal" style="--i:6">
        <h3><i class="fas fa-chart-line card-ico"></i>Kas 6 Bulan Terakhir</h3>
        <div class="fh-chart"><canvas id="chKas"></canvas></div>
        <div class="fh-chart-legend">
            <span><span class="fh-dot" style="background:#5EA332"></span>Pemasukan</span>
            <span><span class="fh-dot" style="background:#E2574C"></span>Pengeluaran</span>
        </div>
    </div>
</div>

<!-- ==================== RINGKASAN ==================== -->
<h2 class="section-title fh-reveal" style="--i:7"><i class="fas fa-clipboard-list sec-ico"></i>Ringkasan</h2>
<div class="grid grid-2">
    <div class="card fh-reveal" style="--i:8">
        <h3><i class="fas fa-calendar-days card-ico"></i>Kegiatan Terdekat</h3>
        <?php if (!$kegiatan): ?>
            <div class="fh-empty">Belum ada kegiatan terjadwal.</div>
        <?php else: ?>
            <div class="fh-list">
            <?php foreach ($kegiatan as $k): ?>
                <div class="fh-item">
                    <div class="fi-ic"><i class="fas fa-calendar-day"></i></div>
                    <div class="fi-body">
                        <div class="fi-title"><?= e($k['nama']) ?></div>
                        <div class="fi-sub"><?= e(tgl_indo($k['tanggal'])) ?><?= $k['waktu'] ? ' &middot; ' . e($k['waktu']) : '' ?><?= $k['tempat'] ? ' &middot; ' . e($k['tempat']) : '' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card fh-reveal" style="--i:9">
        <h3><i class="fas fa-bullhorn card-ico"></i>Pengumuman Terbaru</h3>
        <?php if (!$pengumuman): ?>
            <div class="fh-empty">Belum ada pengumuman.</div>
        <?php else: ?>
            <div class="fh-list">
            <?php foreach ($pengumuman as $p): ?>
                <div class="fh-item">
                    <div class="fi-ic"><i class="fas fa-bullhorn"></i></div>
                    <div class="fi-body">
                        <div class="fi-title"><?= e($p['judul']) ?></div>
                        <div class="fi-sub"><?= e(tgl_indo($p['tanggal'])) ?> &middot; <?= e(mb_substr($p['isi'], 0, 80)) ?><?= mb_strlen($p['isi']) > 80 ? '&hellip;' : '' ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <div class="card fh-reveal" style="--i:10">
        <h3><i class="fas fa-inbox card-ico"></i>Laporan Warga Terbaru
            <?php if ($laporan_baru): ?><span class="badge-st baru"><?= $laporan_baru ?> baru</span><?php endif; ?>
        </h3>
        <?php if (!$laporan): ?>
            <div class="fh-empty">Belum ada laporan.</div>
        <?php else: ?>
            <div class="fh-list">
            <?php foreach ($laporan as $l): ?>
                <div class="fh-item">
                    <div class="fi-ic"><i class="fas fa-inbox"></i></div>
                    <div class="fi-body">
                        <div class="fi-title"><?= e($l['judul']) ?> <span class="badge-st <?= e($l['status']) ?>"><?= e(ucfirst($l['status'])) ?></span></div>
                        <div class="fi-sub"><?= e($l['kategori']) ?> &middot; <?= e(tgl_indo($l['tanggal'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="card fh-reveal" style="--i:11">
        <h3><i class="fas fa-bolt card-ico"></i>Aksi Cepat</h3>
        <div class="fh-quick">
            <a href="<?= url('modules/warga/') ?>"><i class="fas fa-users q-ic" style="color:#5EA332"></i>Warga<span>Kelola data warga</span></a>
            <a href="<?= url('modules/keuangan/') ?>"><i class="fas fa-wallet q-ic" style="color:#D9A021"></i>Keuangan<span>Kas &amp; iuran</span></a>
            <a href="<?= url('modules/surat/') ?>"><i class="fas fa-envelope-open-text q-ic" style="color:#4E9B6A"></i>Surat<span>Keterangan &amp; pengantar</span></a>
            <a href="<?= url('modules/kegiatan/') ?>"><i class="fas fa-calendar-days q-ic" style="color:#7FBF5A"></i>Kegiatan<span>Agenda RT</span></a>
            <a href="<?= url('modules/pengumuman/') ?>"><i class="fas fa-bullhorn q-ic" style="color:#E2574C"></i>Pengumuman<span>Info warga</span></a>
            <a href="<?= url('modules/laporan/') ?>"><i class="fas fa-inbox q-ic" style="color:#1d5fb8"></i>Laporan<span>Aduan warga</span></a>
        </div>
    </div>
</div>

<script>
(function () {
    var data = <?= $json_kas ?>;
    var dark = document.body.classList.contains('dark-mode');
    var gridColor = dark ? 'rgba(255,255,255,.08)' : 'rgba(23,33,17,.08)';
    var tickColor = dark ? '#B9C8A8' : '#5B6B52';
    new Chart(document.getElementById('chKas'), {
        type: 'bar',
        data: {
            labels: data.label,
            datasets: [
                { label: 'Pemasukan', data: data.masuk, backgroundColor: '#5EA332', borderRadius: 6 },
                { label: 'Pengeluaran', data: data.keluar, backgroundColor: '#E2574C', borderRadius: 6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: tickColor, font: { weight: 600 } } },
                y: { grid: { color: gridColor }, ticks: { color: tickColor, callback: function (v) { return v >= 1000 ? (v/1000) + 'rb' : v; } } }
            }
        }
    });
    // Count-up angka statistik
    document.querySelectorAll('[data-count]').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        var t0 = null, dur = 900;
        function step(ts) {
            if (!t0) t0 = ts;
            var p = Math.min((ts - t0) / dur, 1);
            el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
