<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

require_login();
$title = 'Data Santri';
$menu = 'santri';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nis   = trim($_POST['nis'] ?? '');
    $nama  = trim($_POST['nama'] ?? '');
    $jk    = $_POST['jenis_kelamin'] ?? 'L';
    $lahir = $_POST['tgl_lahir'] ?: null;
    $alamat = trim($_POST['alamat'] ?? '');
    if ($nis === '' || $nama === '') {
        flash_set('NIS dan nama wajib diisi.', 'err');
    } else {
        $ok = db_exec($koneksi,
            'INSERT INTO santri (nis, nama, jenis_kelamin, tgl_lahir, alamat, tgl_masuk) VALUES (?,?,?,?,?,CURDATE())',
            'sssss', array($nis, $nama, $jk, $lahir, $alamat));
        flash_set($ok ? 'Santri berhasil ditambahkan.' : 'Gagal menambah santri (mungkin NIS sudah dipakai).', $ok ? 'ok' : 'err');
    }
    redirect('modules/santri/');
}

$rows = db_all($koneksi,
    'SELECT s.*, k.nama AS kamar, a.nama AS asrama
     FROM santri s
     LEFT JOIN penempatan_santri ps ON ps.santri_id = s.id AND ps.tgl_selesai IS NULL
     LEFT JOIN kamar k ON k.id = ps.kamar_id
     LEFT JOIN asrama a ON a.id = k.asrama_id
     ORDER BY s.nama ASC');

include __DIR__ . '/../../includes/header.php';
?>

<div class="form-card">
    <h2 class="section-title" style="margin-top:0">Tambah Santri</h2>
    <form method="post" action="">
        <div class="form-grid">
            <div class="field"><label>NIS</label><input type="text" name="nis" required maxlength="20"></div>
            <div class="field"><label>Nama Lengkap</label><input type="text" name="nama" required maxlength="100"></div>
            <div class="field"><label>Jenis Kelamin</label>
                <select name="jenis_kelamin"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
            </div>
            <div class="field"><label>Tanggal Lahir</label><input type="date" name="tgl_lahir"></div>
            <div class="field"><label>Alamat</label><input type="text" name="alamat" maxlength="255"></div>
        </div>
        <div class="form-actions"><button type="submit" name="tambah" class="btn">Simpan</button></div>
    </form>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>L/P</th><th>Tgl Lahir</th><th>Kamar</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="6" class="empty">Belum ada data santri.</td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><?= e($r['nis']) ?></td>
            <td><strong><?= e($r['nama']) ?></strong></td>
            <td><?= e($r['jenis_kelamin']) ?></td>
            <td><?= e(tgl_indo($r['tgl_lahir'])) ?></td>
            <td><?= e($r['kamar'] ? $r['kamar'] . ' / ' . $r['asrama'] : '-') ?></td>
            <td><span class="badge <?= $r['status'] === 'aktif' ? 'badge-ok' : 'badge-warn' ?>"><?= e($r['status']) ?></span></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
