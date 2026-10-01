<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

require_login();
$title = 'Asrama & Kamar';
$menu = 'asrama';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_asrama'])) {
        $nama = trim($_POST['nama'] ?? '');
        $jenis = $_POST['jenis'] ?? 'putra';
        if ($nama !== '') {
            db_exec($koneksi, 'INSERT INTO asrama (nama, jenis) VALUES (?,?)', 'ss', array($nama, $jenis));
            flash_set('Asrama berhasil ditambahkan.');
        } else {
            flash_set('Nama asrama wajib diisi.', 'err');
        }
    } elseif (isset($_POST['tambah_kamar'])) {
        $asrama_id = (int) ($_POST['asrama_id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $kap = max(1, (int) ($_POST['kapasitas'] ?? 4));
        if ($asrama_id > 0 && $nama !== '') {
            $ok = db_exec($koneksi, 'INSERT INTO kamar (asrama_id, nama, kapasitas) VALUES (?,?,?)', 'isi', array($asrama_id, $nama, $kap));
            flash_set($ok ? 'Kamar berhasil ditambahkan.' : 'Gagal (nama kamar mungkin sudah ada di asrama ini).', $ok ? 'ok' : 'err');
        } else {
            flash_set('Asrama dan nama kamar wajib diisi.', 'err');
        }
    } elseif (isset($_POST['tempatkan'])) {
        $santri_id = (int) ($_POST['santri_id'] ?? 0);
        $kamar_id = (int) ($_POST['kamar_id'] ?? 0);
        if ($santri_id > 0 && $kamar_id > 0) {
            db_exec($koneksi, "UPDATE penempatan_santri SET tgl_selesai = CURDATE() WHERE santri_id = ? AND tgl_selesai IS NULL", 'i', array($santri_id));
            db_exec($koneksi, 'INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai) VALUES (?,?,CURDATE())', 'ii', array($santri_id, $kamar_id));
            flash_set('Santri berhasil ditempatkan.');
        } else {
            flash_set('Pilih santri dan kamar.', 'err');
        }
    }
    redirect('modules/asrama/');
}

$asrama = db_all($koneksi, 'SELECT * FROM asrama ORDER BY nama');
$kamar = db_all($koneksi,
    'SELECT k.*, a.nama AS asrama,
        (SELECT COUNT(*) FROM penempatan_santri ps WHERE ps.kamar_id = k.id AND ps.tgl_selesai IS NULL) AS terisi
     FROM kamar k JOIN asrama a ON a.id = k.asrama_id ORDER BY a.nama, k.nama');
$santri = db_all($koneksi, "SELECT id, nis, nama FROM santri WHERE status = 'aktif' ORDER BY nama");
$penghuni = db_all($koneksi,
    'SELECT s.nis, s.nama, k.nama AS kamar, a.nama AS asrama
     FROM penempatan_santri ps
     JOIN santri s ON s.id = ps.santri_id
     JOIN kamar k ON k.id = ps.kamar_id
     JOIN asrama a ON a.id = k.asrama_id
     WHERE ps.tgl_selesai IS NULL ORDER BY a.nama, k.nama, s.nama');

include __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-2">
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0"><i class="fas fa-building sec-ico"></i>Tambah Asrama</h2>
        <form method="post" action="">
            <div class="form-grid">
                <div class="field"><label>Nama Asrama</label><input type="text" name="nama" required maxlength="60"></div>
                <div class="field"><label>Jenis</label>
                    <select name="jenis"><option value="putra">Putra</option><option value="putri">Putri</option></select>
                </div>
            </div>
            <div class="form-actions"><button type="submit" name="tambah_asrama" class="btn"><i class="fas fa-check"></i>Simpan</button></div>
        </form>
    </div>
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0"><i class="fas fa-bed sec-ico"></i>Tambah Kamar</h2>
        <form method="post" action="">
            <div class="form-grid">
                <div class="field"><label>Asrama</label>
                    <select name="asrama_id" required>
                        <option value="">-- pilih --</option>
                        <?php foreach ($asrama as $a): ?>
                        <option value="<?= (int) $a['id'] ?>"><?= e($a['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Nama Kamar</label><input type="text" name="nama" required maxlength="30"></div>
                <div class="field"><label>Kapasitas</label><input type="number" name="kapasitas" value="4" min="1" max="40"></div>
            </div>
            <div class="form-actions"><button type="submit" name="tambah_kamar" class="btn"><i class="fas fa-check"></i>Simpan</button></div>
        </form>
    </div>
</div>

<div class="form-card">
    <h2 class="section-title" style="margin-top:0"><i class="fas fa-user-check sec-ico"></i>Tempatkan Santri</h2>
    <form method="post" action="">
        <div class="form-grid">
            <div class="field"><label>Santri</label>
                <select name="santri_id" required>
                    <option value="">-- pilih --</option>
                    <?php foreach ($santri as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= e($s['nis'] . ' - ' . $s['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Kamar</label>
                <select name="kamar_id" required>
                    <option value="">-- pilih --</option>
                    <?php foreach ($kamar as $k): ?>
                    <option value="<?= (int) $k['id'] ?>"><?= e($k['asrama'] . ' / ' . $k['nama'] . ' (' . $k['terisi'] . '/' . $k['kapasitas'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions"><button type="submit" name="tempatkan" class="btn"><i class="fas fa-user-check"></i>Tempatkan</button></div>
    </form>
</div>

<h2 class="section-title"><i class="fas fa-list sec-ico"></i>Daftar Kamar</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Asrama</th><th>Kamar</th><th>Kapasitas</th><th>Terisi</th></tr></thead>
    <tbody>
    <?php if (!$kamar): ?>
        <tr><td colspan="4" class="empty">Belum ada kamar.</td></tr>
    <?php else: foreach ($kamar as $k): ?>
        <tr>
            <td><?= e($k['asrama']) ?></td>
            <td><strong><?= e($k['nama']) ?></strong></td>
            <td><?= (int) $k['kapasitas'] ?></td>
            <td><?= (int) $k['terisi'] ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<h2 class="section-title"><i class="fas fa-users sec-ico"></i>Penghuni Aktif</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>Asrama</th><th>Kamar</th></tr></thead>
    <tbody>
    <?php if (!$penghuni): ?>
        <tr><td colspan="4" class="empty">Belum ada penempatan.</td></tr>
    <?php else: foreach ($penghuni as $p): ?>
        <tr>
            <td><?= e($p['nis']) ?></td>
            <td><?= e($p['nama']) ?></td>
            <td><?= e($p['asrama']) ?></td>
            <td><?= e($p['kamar']) ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
