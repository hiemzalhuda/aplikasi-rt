<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login();
$title = 'Pelanggaran & Tata Tertib';
$menu = 'pelanggaran';

$kategori = array('ringan' => 'Ringan', 'sedang' => 'Sedang', 'berat' => 'Berat');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_master'])) {
        $nama = trim($_POST['nama'] ?? '');
        $poin = max(0, (int) ($_POST['poin'] ?? 0));
        $kat = $_POST['kategori'] ?? 'ringan';
        if (!isset($kategori[$kat])) $kat = 'ringan';
        if ($nama !== '') {
            db_exec($koneksi, 'INSERT INTO master_pelanggaran (nama, poin, kategori) VALUES (?,?,?)', 'sis', array($nama, $poin, $kat));
            flash_set('Jenis pelanggaran ditambahkan.');
        } else {
            flash_set('Nama pelanggaran wajib diisi.', 'err');
        }
    } elseif (isset($_POST['catat'])) {
        $santri_id = (int) ($_POST['santri_id'] ?? 0);
        $pelanggaran_id = (int) ($_POST['pelanggaran_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?: date('Y-m-d');
        $sanksi = trim($_POST['sanksi'] ?? '');
        if ($santri_id > 0 && $pelanggaran_id > 0) {
            db_exec($koneksi,
                'INSERT INTO catatan_pelanggaran (santri_id, pelanggaran_id, tanggal, sanksi, dicatat_oleh) VALUES (?,?,?,?,?)',
                'iissi', array($santri_id, $pelanggaran_id, $tanggal, $sanksi, $user['id']));
            flash_set('Pelanggaran dicatat.');
        } else {
            flash_set('Pilih santri dan jenis pelanggaran.', 'err');
        }
    } elseif (isset($_POST['selesaikan'])) {
        $id = (int) ($_POST['id'] ?? 0);
        db_exec($koneksi, "UPDATE catatan_pelanggaran SET status = 'selesai' WHERE id = ?", 'i', array($id));
        flash_set('Status pembinaan diselesaikan.');
    }
    redirect('modules/pelanggaran/');
}

$santri = db_all($koneksi, "SELECT id, nis, nama FROM santri WHERE status = 'aktif' ORDER BY nama");
$master = db_all($koneksi, 'SELECT * FROM master_pelanggaran ORDER BY kategori, poin DESC');
$catatan = db_all($koneksi,
    'SELECT cp.*, s.nis, s.nama AS santri, mp.nama AS pelanggaran, mp.poin
     FROM catatan_pelanggaran cp
     JOIN santri s ON s.id = cp.santri_id
     JOIN master_pelanggaran mp ON mp.id = cp.pelanggaran_id
     ORDER BY cp.tanggal DESC, cp.id DESC LIMIT 50');
$rekap = db_all($koneksi,
    "SELECT s.id AS sid, s.nis, s.nama, COALESCE(SUM(mp.poin),0) AS total_poin, COUNT(cp.id) AS jml
     FROM santri s
     LEFT JOIN catatan_pelanggaran cp ON cp.santri_id = s.id
     LEFT JOIN master_pelanggaran mp ON mp.id = cp.pelanggaran_id
     WHERE s.status = 'aktif'
     GROUP BY s.id HAVING total_poin > 0 ORDER BY total_poin DESC LIMIT 20");

include __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-2">
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0"><i class="fas fa-triangle-exclamation sec-ico"></i>Catat Pelanggaran</h2>
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
                <div class="field"><label>Jenis Pelanggaran</label>
                    <select name="pelanggaran_id" required>
                        <option value="">-- pilih --</option>
                        <?php foreach ($master as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= e($m['nama'] . ' (' . $m['poin'] . ' poin)') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
                <div class="field"><label>Sanksi / Pembinaan</label><input type="text" name="sanksi" maxlength="255"></div>
            </div>
            <div class="form-actions"><button type="submit" name="catat" class="btn btn-danger"><i class="fas fa-pen"></i>Catat</button></div>
        </form>
    </div>
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0"><i class="fas fa-list-check sec-ico"></i>Master Jenis Pelanggaran</h2>
        <form method="post" action="">
            <div class="form-grid">
                <div class="field"><label>Nama Pelanggaran</label><input type="text" name="nama" required maxlength="100"></div>
                <div class="field"><label>Poin</label><input type="number" name="poin" min="0" value="10"></div>
                <div class="field"><label>Kategori</label>
                    <select name="kategori">
                        <?php foreach ($kategori as $k => $v): ?>
                        <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions"><button type="submit" name="tambah_master" class="btn"><i class="fas fa-plus"></i>Tambah</button></div>
        </form>
    </div>
</div>

<h2 class="section-title"><i class="fas fa-trophy sec-ico"></i>Rekap Poin Tertinggi</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>Jumlah Kasus</th><th>Total Poin</th></tr></thead>
    <tbody>
    <?php if (!$rekap): ?>
        <tr><td colspan="4" class="empty">Belum ada pelanggaran tercatat.</td></tr>
    <?php else: foreach ($rekap as $r): ?>
        <tr>
            <td><?= profil_link($r['sid'], $r['nis']) ?></td>
            <td><?= profil_link($r['sid'], $r['nama']) ?></td>
            <td><?= (int) $r['jml'] ?></td>
            <td><span class="badge <?= (int) $r['total_poin'] >= 100 ? 'badge-err' : 'badge-warn' ?>"><?= (int) $r['total_poin'] ?></span></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<h2 class="section-title"><i class="fas fa-clock-rotate-left sec-ico"></i>Catatan Terakhir</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Tanggal</th><th>Santri</th><th>Pelanggaran</th><th>Poin</th><th>Sanksi</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$catatan): ?>
        <tr><td colspan="7" class="empty">Belum ada catatan.</td></tr>
    <?php else: foreach ($catatan as $c): ?>
        <tr>
            <td><?= e(tgl_indo($c['tanggal'])) ?></td>
            <td><?= profil_link($c['santri_id'], $c['santri']) ?></td>
            <td><?= e($c['pelanggaran']) ?></td>
            <td><?= (int) $c['poin'] ?></td>
            <td><?= e($c['sanksi'] ?: '-') ?></td>
            <td><span class="badge <?= $c['status'] === 'selesai' ? 'badge-ok' : 'badge-warn' ?>"><?= e($c['status']) ?></span></td>
            <td>
                <?php if ($c['status'] !== 'selesai'): ?>
                <form method="post" action="">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" name="selesaikan" class="btn btn-sm"><i class="fas fa-check-double"></i>Selesaikan</button>
                </form>
                <?php else: ?>-<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
