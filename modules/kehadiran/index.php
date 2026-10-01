<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login();
$title = 'Kehadiran';
$menu = 'kehadiran';

$waktu_sholat = array(
    'subuh' => 'Subuh', 'dzuhur' => 'Dzuhur', 'ashar' => 'Ashar',
    'maghrib' => 'Maghrib', 'isya' => 'Isya',
);
$kategori_jenis = array(
    'Sholat'   => array('subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'),
    'Madrasah' => array('madrasah'),
    'Ngaji'    => array('ngaji'),
);

/* Kategori bersifat turunan dari jenis (tanpa kolom fisik). */
function kategori_kehadiran($jenis) {
    if (in_array($jenis, array('subuh', 'dzuhur', 'ashar', 'maghrib', 'isya'), true)) return 'Sholat';
    if ($jenis === 'madrasah') return 'Madrasah';
    return 'Ngaji';
}

/* Label sesi: madrasah menyertakan kelas, sesi lama tanpa kelas = "Umum". */
function label_sesi($jenis, $kelas = null) {
    if ($jenis === 'madrasah') {
        $k = (int) $kelas;
        return ($k >= 1 && $k <= 6) ? 'Madrasah — Kelas ' . $k : 'Madrasah (Umum)';
    }
    $map = array(
        'subuh' => 'Sholat Subuh', 'dzuhur' => 'Sholat Dzuhur', 'ashar' => 'Sholat Ashar',
        'maghrib' => 'Sholat Maghrib', 'isya' => 'Sholat Isya', 'ngaji' => 'Ngaji',
    );
    return $map[$jenis] ?? $jenis;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['buat_sesi'])) {
        $tanggal = $_POST['tanggal'] ?: date('Y-m-d');
        $kategori = $_POST['kategori'] ?? 'Sholat';
        if (!isset($kategori_jenis[$kategori])) $kategori = 'Sholat';
        $jenis = 'subuh';
        $kelas = null;
        if ($kategori === 'Sholat') {
            $jenis = $_POST['waktu'] ?? 'subuh';
            if (!isset($waktu_sholat[$jenis])) $jenis = 'subuh';
            $kode = strtoupper(date('ymd', strtotime($tanggal)) . '-' . $jenis . '-' . substr(md5(uniqid('', true)), 0, 4));
        } elseif ($kategori === 'Madrasah') {
            $jenis = 'madrasah';
            $kelas = (int) ($_POST['kelas'] ?? 0);
            if ($kelas < 1 || $kelas > 6) {
                flash_set('Pilih kelas 1–6 untuk sesi Madrasah.', 'err');
                redirect('modules/kehadiran/');
            }
            $kode = strtoupper(date('ymd', strtotime($tanggal)) . '-MADRASAH-K' . $kelas . '-' . substr(md5(uniqid('', true)), 0, 4));
        } else {
            $jenis = 'ngaji';
            $kode = strtoupper(date('ymd', strtotime($tanggal)) . '-NGAJI-' . substr(md5(uniqid('', true)), 0, 4));
        }
        $ok = db_exec($koneksi,
            'INSERT INTO sesi_kehadiran (kode, tanggal, jenis, kelas, created_by) VALUES (?,?,?,?,?)',
            'sssii', array($kode, $tanggal, $jenis, $kelas, $user['id']));
        flash_set($ok ? 'Sesi kehadiran dibuat (kode: ' . $kode . ').' : 'Gagal membuat sesi.', $ok ? 'ok' : 'err');
        redirect('modules/kehadiran/');
    } elseif (isset($_POST['simpan_absen'])) {
        $sesi_id = (int) ($_POST['sesi_id'] ?? 0);
        $status = $_POST['status'] ?? array();
        $sesi = db_one($koneksi, 'SELECT id FROM sesi_kehadiran WHERE id = ?', 'i', array($sesi_id));
        if ($sesi) {
            $valid = array('hadir', 'izin', 'sakit', 'alpa');
            foreach ($status as $santri_id => $st) {
                $santri_id = (int) $santri_id;
                if (!in_array($st, $valid, true)) $st = 'hadir';
                db_exec($koneksi,
                    'INSERT INTO kehadiran (sesi_id, santri_id, status) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE status = VALUES(status)',
                    'iis', array($sesi_id, $santri_id, $st));
            }
            flash_set('Absensi berhasil disimpan.');
        } else {
            flash_set('Sesi tidak ditemukan.', 'err');
        }
        redirect('modules/kehadiran/?sesi=' . $sesi_id);
    }
}

$sesi_aktif = null;
$santri_absen = array();
if (!empty($_GET['sesi'])) {
    $sesi_aktif = db_one($koneksi, 'SELECT * FROM sesi_kehadiran WHERE id = ?', 'i', array((int) $_GET['sesi']));
    if ($sesi_aktif) {
        $santri_absen = db_all($koneksi,
            "SELECT s.id, s.nis, s.nama,
                (SELECT k.status FROM kehadiran k WHERE k.sesi_id = ? AND k.santri_id = s.id) AS status
             FROM santri s WHERE s.status = 'aktif' ORDER BY s.nama",
            'i', array($sesi_aktif['id']));
    }
}

// ---- Filter kategori (GET) ----
$f_kategori = $_GET['kategori'] ?? '';
if (!isset($kategori_jenis[$f_kategori])) $f_kategori = '';

$where = '';
$types = '';
$params = array();
if ($f_kategori !== '') {
    $jl = $kategori_jenis[$f_kategori];
    $where = 'WHERE sk.jenis IN (' . implode(',', array_fill(0, count($jl), '?')) . ')';
    $types = str_repeat('s', count($jl));
    $params = $jl;
}

$sesi_list = db_all($koneksi,
    'SELECT sk.*, u.nama_lengkap AS pencatat,
        (SELECT COUNT(*) FROM kehadiran k WHERE k.sesi_id = sk.id) AS tercatat
     FROM sesi_kehadiran sk LEFT JOIN users u ON u.id = sk.created_by
     ' . $where . '
     ORDER BY sk.tanggal DESC, sk.id DESC LIMIT 30',
    $types, $params);

include __DIR__ . '/../../includes/header.php';
?>

<div class="form-card">
    <h2 class="section-title" style="margin-top:0">Buat Sesi Kehadiran</h2>
    <form method="post" action="">
        <div class="form-grid">
            <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
            <div class="field"><label>Kategori</label>
                <select name="kategori" id="katSelect">
                    <?php foreach (array_keys($kategori_jenis) as $kat): ?>
                    <option value="<?= e($kat) ?>"><?= e($kat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" id="waktuWrap"><label>Waktu Sholat</label>
                <select name="waktu">
                    <?php foreach ($waktu_sholat as $k => $v): ?>
                    <option value="<?= e($k) ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" id="kelasWrap" style="display:none"><label>Kelas Madrasah</label>
                <select name="kelas">
                    <option value="">-- pilih kelas --</option>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?= $i ?>">Kelas <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="form-actions"><button type="submit" name="buat_sesi" class="btn">Buat Sesi</button></div>
    </form>
</div>
<script>
(function () {
    var kat = document.getElementById('katSelect');
    var w = document.getElementById('waktuWrap');
    var k = document.getElementById('kelasWrap');
    function sync() {
        w.style.display = kat.value === 'Sholat' ? '' : 'none';
        k.style.display = kat.value === 'Madrasah' ? '' : 'none';
    }
    kat.addEventListener('change', sync);
    sync();
})();
</script>

<?php if ($sesi_aktif): ?>
<div class="form-card">
    <h2 class="section-title" style="margin-top:0">
        Absensi: <?= e(label_sesi($sesi_aktif['jenis'], $sesi_aktif['kelas'] ?? null)) ?> —
        <?= e(tgl_indo($sesi_aktif['tanggal'])) ?>
        <span class="badge badge-ok"><?= e($sesi_aktif['kode']) ?></span>
    </h2>
    <form method="post" action="">
        <input type="hidden" name="sesi_id" value="<?= (int) $sesi_aktif['id'] ?>">
        <div class="table-wrap">
        <table>
            <thead><tr><th>NIS</th><th>Nama</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($santri_absen as $s): ?>
                <tr>
                    <td><?= profil_link($s['id'], $s['nis']) ?></td>
                    <td><?= profil_link($s['id'], $s['nama']) ?></td>
                    <td>
                        <select name="status[<?= (int) $s['id'] ?>]">
                            <?php foreach (array('hadir','izin','sakit','alpa') as $st): ?>
                            <option value="<?= $st ?>"<?= ($s['status'] ?? 'hadir') === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="form-actions"><button type="submit" name="simpan_absen" class="btn">Simpan Absensi</button></div>
    </form>
</div>
<?php endif; ?>

<h2 class="section-title">Sesi Terakhir</h2>
<div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap">
    <a class="btn btn-sm<?= $f_kategori === '' ? '' : ' btn-ghost' ?>" href="<?= url('modules/kehadiran/') ?>">Semua</a>
    <?php foreach (array_keys($kategori_jenis) as $kat): ?>
    <a class="btn btn-sm<?= $f_kategori === $kat ? '' : ' btn-ghost' ?>" href="<?= url('modules/kehadiran/?kategori=' . $kat) ?>"><?= e($kat) ?></a>
    <?php endforeach; ?>
</div>
<div class="table-wrap">
<table>
    <thead><tr><th>Kode</th><th>Tanggal</th><th>Kategori</th><th>Jenis</th><th>Tercatat</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$sesi_list): ?>
        <tr><td colspan="6" class="empty">Belum ada sesi kehadiran<?= $f_kategori !== '' ? ' untuk kategori ' . e($f_kategori) : '' ?>.</td></tr>
    <?php else: foreach ($sesi_list as $s): ?>
        <tr>
            <td><span class="badge badge-ok"><?= e($s['kode']) ?></span></td>
            <td><?= e(tgl_indo($s['tanggal'])) ?></td>
            <td><span class="badge"><?= e(kategori_kehadiran($s['jenis'])) ?></span></td>
            <td><?= e(label_sesi($s['jenis'], $s['kelas'] ?? null)) ?></td>
            <td><?= (int) $s['tercatat'] ?></td>
            <td><a class="btn btn-sm" href="<?= url('modules/kehadiran/?sesi=' . (int) $s['id']) ?>">Absen</a></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
