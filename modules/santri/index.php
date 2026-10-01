<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

require_login();
$title = 'Data Santri';
$menu = 'santri';

/** Daftar kamar untuk dropdown (dikelompokkan per asrama). */
$kamar_list = db_all($koneksi,
    'SELECT k.id, k.nama AS kamar, a.nama AS asrama
     FROM kamar k JOIN asrama a ON a.id = k.asrama_id
     ORDER BY a.nama ASC, k.nama ASC');

/** Validasi: kamar_id harus ada di tabel kamar. */
function kamar_valid($koneksi, $kamar_id) {
    if ($kamar_id === '' || $kamar_id === null) return false;
    $r = db_one($koneksi, 'SELECT id FROM kamar WHERE id = ?', 'i', array((int) $kamar_id));
    return (bool) $r;
}

/* ---------- Tambah santri (sekaligus penempatan kamar bila dipilih) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nis      = trim($_POST['nis'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $jk       = $_POST['jenis_kelamin'] ?? 'L';
    $lahir    = $_POST['tgl_lahir'] ?: null;
    $masuk    = $_POST['tgl_masuk'] ?: date('Y-m-d');
    $alamat   = trim($_POST['alamat'] ?? '');
    $kamar_id = trim($_POST['kamar_id'] ?? '');
    if ($nis === '' || $nama === '') {
        flash_set('NIS dan nama wajib diisi.', 'err');
    } elseif ($kamar_id !== '' && !kamar_valid($koneksi, $kamar_id)) {
        flash_set('Kamar yang dipilih tidak valid.', 'err');
    } else {
        $ok = db_exec($koneksi,
            'INSERT INTO santri (nis, nama, jenis_kelamin, tgl_lahir, alamat, tgl_masuk) VALUES (?,?,?,?,?,?)',
            'ssssss', array($nis, $nama, $jk, $lahir, $alamat, $masuk));
        if ($ok) {
            $sid = (int) $koneksi->insert_id;
            if ($kamar_id !== '') {
                db_exec($koneksi,
                    'INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai) VALUES (?,?,CURDATE())',
                    'ii', array($sid, (int) $kamar_id));
            }
            flash_set('Santri berhasil ditambahkan.', 'ok');
        } else {
            flash_set('Gagal menambah santri (mungkin NIS sudah dipakai).', 'err');
        }
    }
    redirect('modules/santri/');
}

/* ---------- Toggle status aktif/nonaktif ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $s = db_one($koneksi, 'SELECT id, status FROM santri WHERE id = ?', 'i', array($id));
    if (!$s) {
        flash_set('Data santri tidak ditemukan.', 'err');
    } else {
        $baru = ($s['status'] === 'aktif') ? 'nonaktif' : 'aktif';
        db_exec($koneksi, 'UPDATE santri SET status = ? WHERE id = ?', 'si', array($baru, $id));
        if ($baru === 'nonaktif') {
            /* Tutup penempatan aktif yang masih berjalan. */
            db_exec($koneksi,
                'UPDATE penempatan_santri SET tgl_selesai = CURDATE() WHERE santri_id = ? AND tgl_selesai IS NULL',
                'i', array($id));
        }
        flash_set('Status santri diubah menjadi ' . $baru . '.', 'ok');
    }
    redirect('modules/santri/');
}

/* ---------- Pindah / kosongkan kamar santri yang sudah ada ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pindah_kamar'])) {
    $id       = (int) ($_POST['id'] ?? 0);
    $kamar_id = trim($_POST['kamar_id'] ?? '');
    $s = db_one($koneksi, 'SELECT id, status FROM santri WHERE id = ?', 'i', array($id));
    if (!$s) {
        flash_set('Data santri tidak ditemukan.', 'err');
    } elseif ($s['status'] !== 'aktif') {
        flash_set('Santri nonaktif tidak bisa dipindah kamar.', 'err');
    } elseif ($kamar_id !== '' && !kamar_valid($koneksi, $kamar_id)) {
        flash_set('Kamar yang dipilih tidak valid.', 'err');
    } else {
        /* Tutup penempatan lama, buka yang baru bila ada kamar dipilih. */
        db_exec($koneksi,
            'UPDATE penempatan_santri SET tgl_selesai = CURDATE() WHERE santri_id = ? AND tgl_selesai IS NULL',
            'i', array($id));
        if ($kamar_id !== '') {
            db_exec($koneksi,
                'INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai) VALUES (?,?,CURDATE())',
                'ii', array($id, (int) $kamar_id));
        }
        flash_set('Kamar santri diperbarui.', 'ok');
    }
    redirect('modules/santri/');
}

$rows = db_all($koneksi,
    'SELECT s.*, k.nama AS kamar, a.nama AS asrama, ps.kamar_id AS kamar_aktif_id
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
            <div class="field"><label>Tanggal Mendaftar</label><input type="date" name="tgl_masuk" value="<?= date('Y-m-d') ?>"></div>
            <div class="field"><label>Kamar</label>
                <select name="kamar_id">
                    <option value="">-- Belum ditempatkan --</option>
                    <?php foreach ($kamar_list as $k): ?>
                        <option value="<?= (int) $k['id'] ?>"><?= e($k['kamar'] . ' / ' . $k['asrama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions"><button type="submit" name="tambah" class="btn">Simpan</button></div>
    </form>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>L/P</th><th>Tgl Lahir</th><th>Alamat</th><th>Tgl Daftar</th><th>Kamar</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="8" class="empty">Belum ada data santri.</td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><?= profil_link($r['id'], $r['nis']) ?></td>
            <td><strong><?= profil_link($r['id'], $r['nama']) ?></strong></td>
            <td><?= e($r['jenis_kelamin']) ?></td>
            <td><?= e(tgl_indo($r['tgl_lahir'])) ?></td>
            <td><?= e($r['alamat'] ?: '-') ?></td>
            <td><?= e(tgl_indo($r['tgl_masuk'])) ?></td>
            <td>
                <?php if ($r['status'] === 'aktif'): ?>
                <form method="post" action="" style="display:inline">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="pindah_kamar" value="1">
                    <select name="kamar_id" onchange="this.form.submit()" style="max-width:150px;padding:4px 6px;font-size:13px">
                        <option value="">--</option>
                        <?php foreach ($kamar_list as $k): ?>
                            <option value="<?= (int) $k['id'] ?>"<?= ((int) $r['kamar_aktif_id'] === (int) $k['id']) ? ' selected' : '' ?>><?= e($k['kamar'] . ' / ' . $k['asrama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php else: ?>
                    <span style="color:#999">-</span>
                <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <span class="badge <?= $r['status'] === 'aktif' ? 'badge-ok' : 'badge-warn' ?>"><?= e($r['status']) ?></span>
                <form method="post" action="" style="display:inline;margin-left:6px" onsubmit="return confirm('Ubah status santri ini?')">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <?php if ($r['status'] === 'aktif'): ?>
                        <button type="submit" name="toggle_status" class="btn btn-sm btn-warn">Nonaktifkan</button>
                    <?php else: ?>
                        <button type="submit" name="toggle_status" class="btn btn-sm">Aktifkan</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
