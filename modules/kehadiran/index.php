<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login();
$title = 'Kehadiran';
$menu = 'kehadiran';

$jenis_list = array(
    'subuh' => 'Sholat Subuh', 'dzuhur' => 'Sholat Dzuhur', 'ashar' => 'Sholat Ashar',
    'maghrib' => 'Sholat Maghrib', 'isya' => 'Sholat Isya',
    'ngaji' => 'Ngaji', 'madrasah' => 'Madrasah',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['buat_sesi'])) {
        $tanggal = $_POST['tanggal'] ?: date('Y-m-d');
        $jenis = $_POST['jenis'] ?? 'subuh';
        if (!isset($jenis_list[$jenis])) $jenis = 'subuh';
        $kode = strtoupper(date('ymd', strtotime($tanggal)) . '-' . $jenis . '-' . substr(md5(uniqid('', true)), 0, 4));
        $ok = db_exec($koneksi,
            'INSERT INTO sesi_kehadiran (kode, tanggal, jenis, created_by) VALUES (?,?,?,?)',
            'sssi', array($kode, $tanggal, $jenis, $user['id']));
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

$sesi_list = db_all($koneksi,
    'SELECT sk.*, u.nama_lengkap AS pencatat,
        (SELECT COUNT(*) FROM kehadiran k WHERE k.sesi_id = sk.id) AS tercatat
     FROM sesi_kehadiran sk LEFT JOIN users u ON u.id = sk.created_by
     ORDER BY sk.tanggal DESC, sk.id DESC LIMIT 30');

include __DIR__ . '/../../includes/header.php';
?>

<div class="form-card">
    <h2 class="section-title" style="margin-top:0">Buat Sesi Kehadiran</h2>
    <form method="post" action="">
        <div class="form-grid">
            <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
            <div class="field"><label>Jenis Kegiatan</label>
                <select name="jenis">
                    <?php foreach ($jenis_list as $k => $v): ?>
                    <option value="<?= e($k) ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions"><button type="submit" name="buat_sesi" class="btn">Buat Sesi</button></div>
    </form>
</div>

<?php if ($sesi_aktif): ?>
<div class="form-card">
    <h2 class="section-title" style="margin-top:0">
        Absensi: <?= e($jenis_list[$sesi_aktif['jenis']] ?? $sesi_aktif['jenis']) ?> —
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
                    <td><?= e($s['nis']) ?></td>
                    <td><?= e($s['nama']) ?></td>
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
<div class="table-wrap">
<table>
    <thead><tr><th>Kode</th><th>Tanggal</th><th>Jenis</th><th>Tercatat</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$sesi_list): ?>
        <tr><td colspan="5" class="empty">Belum ada sesi kehadiran.</td></tr>
    <?php else: foreach ($sesi_list as $s): ?>
        <tr>
            <td><span class="badge badge-ok"><?= e($s['kode']) ?></span></td>
            <td><?= e(tgl_indo($s['tanggal'])) ?></td>
            <td><?= e($jenis_list[$s['jenis']] ?? $s['jenis']) ?></td>
            <td><?= (int) $s['tercatat'] ?></td>
            <td><a class="btn btn-sm" href="<?= url('modules/kehadiran/?sesi=' . (int) $s['id']) ?>">Absen</a></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
