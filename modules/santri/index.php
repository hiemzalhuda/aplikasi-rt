<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login();
$title = 'Data Santri';
$menu = 'santri';
$can_edit = in_array($user['role'], array('admin', 'pengasuh'), true);
$upload_dir = __DIR__ . '/../../uploads/santri';

/* Kata kunci pencarian (GET). */
$q = trim($_GET['q'] ?? '');

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
    if (!$can_edit) {
        http_response_code(403);
        die('Akses ditolak untuk role ini.');
    }
    $nis      = trim($_POST['nis'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $jk       = $_POST['jenis_kelamin'] ?? 'L';
    if (!in_array($jk, array('L', 'P'), true)) $jk = 'L';
    $tpl      = trim($_POST['tempat_lahir'] ?? '') ?: null;
    $lahir    = $_POST['tgl_lahir'] ?: null;
    $alamat   = trim($_POST['alamat'] ?? '') ?: null;
    $hp       = trim($_POST['no_hp'] ?? '') ?: null;
    $masuk    = $_POST['tgl_masuk'] ?: date('Y-m-d');
    $kamar_id = trim($_POST['kamar_id'] ?? '');
    if ($nis === '' || $nama === '') {
        flash_set('NIS dan nama wajib diisi.', 'err');
    } elseif ($kamar_id !== '' && !kamar_valid($koneksi, $kamar_id)) {
        flash_set('Kamar yang dipilih tidak valid.', 'err');
    } else {
        list($foto, $ferr) = upload_foto_santri($_FILES['foto'] ?? array(), null, $upload_dir);
        if ($ferr) {
            flash_set($ferr, 'err');
        } else {
            $ok = db_exec($koneksi,
                'INSERT INTO santri (nis, nama, jenis_kelamin, tempat_lahir, tgl_lahir, alamat, no_hp, tgl_masuk, foto)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                'sssssssss', array($nis, $nama, $jk, $tpl, $lahir, $alamat, $hp, $masuk, $foto));
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
    }
    redirect('modules/santri/' . ($q ? '?q=' . urlencode($q) : ''));
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

/* ---------- Pencarian (nama / NIS / alamat) ---------- */
$where = '';
$params = array();
$types = '';
if ($q !== '') {
    $like = '%' . $q . '%';
    $where = "WHERE s.nama LIKE ? OR s.nis LIKE ? OR s.alamat LIKE ?";
    $params = array($like, $like, $like);
    $types = 'sss';
}

$rows = db_all($koneksi,
    'SELECT s.*, k.nama AS kamar, a.nama AS asrama, ps.kamar_id AS kamar_aktif_id
     FROM santri s
     LEFT JOIN penempatan_santri ps ON ps.santri_id = s.id AND ps.tgl_selesai IS NULL
     LEFT JOIN kamar k ON k.id = ps.kamar_id
     LEFT JOIN asrama a ON a.id = k.asrama_id
     ' . $where . '
     ORDER BY s.nama ASC', $types, $params);

include __DIR__ . '/../../includes/header.php';
?>

<div class="sn-pagebar">
    <div class="sn-search" id="snPageSearch">
        <form method="get" action="" role="search" autocomplete="off">
            <span class="sn-search-icon"><i class="fas fa-search"></i></span>
            <input type="text" name="q" id="snPageSearchInput" class="sn-search-input" placeholder="Cari nama, NIS, atau alamat..."
                   value="<?= e($q) ?>" autocomplete="off">
        </form>
        <div class="sn-search-results" id="snPageSearchResults"></div>
    </div>
    <?php if ($can_edit): ?>
    <button type="button" class="btn" data-snmodal-open="modalTambahSantri">
        <i class="fas fa-plus" style="margin-right:6px"></i>Tambah Santri
    </button>
    <?php endif; ?>
</div>

<?php if ($q !== ''): ?>
<p style="margin:-6px 0 14px;color:var(--text-soft);font-size:13.5px">
    Hasil pencarian untuk <strong>"<?= e($q) ?>"</strong> (<?= count($rows) ?> santri)
    <a href="<?= url('modules/santri/') ?>" class="tbl-link" style="margin-left:8px">Atur ulang</a>
</p>
<?php endif; ?>

<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>L/P</th><th>Tgl Lahir</th><th>Alamat</th><th>Kamar</th><th>Tgl Daftar</th><th>Status</th></tr></thead>
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
            <td><?= e(tgl_indo($r['tgl_masuk'])) ?></td>
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

<?php if ($can_edit): ?>
<!-- ======== Modal tambah santri ======== -->
<div class="sn-modal" id="modalTambahSantri" role="dialog" aria-modal="true" aria-label="Tambah Santri">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-user-plus" style="margin-right:8px;color:var(--brand-active)"></i>Tambah Santri</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="field"><label>NIS</label><input type="text" name="nis" required maxlength="20"></div>
                    <div class="field"><label>Nama Lengkap</label><input type="text" name="nama" required maxlength="100"></div>
                    <div class="field"><label>Jenis Kelamin</label>
                        <select name="jenis_kelamin"><option value="L">Laki-laki</option><option value="P">Perempuan</option></select>
                    </div>
                    <div class="field"><label>Tempat Lahir</label><input type="text" name="tempat_lahir" maxlength="60"></div>
                    <div class="field"><label>Tanggal Lahir</label><input type="date" name="tgl_lahir"></div>
                    <div class="field"><label>Alamat</label><input type="text" name="alamat" maxlength="255"></div>
                    <div class="field"><label>No. HP</label><input type="text" name="no_hp" maxlength="20"></div>
                    <div class="field"><label>Tanggal Mendaftar</label><input type="date" name="tgl_masuk" value="<?= date('Y-m-d') ?>"></div>
                    <div class="field"><label>Foto (jpg/png/webp, maks 2MB)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
                    <div class="field"><label>Kamar</label>
                        <select name="kamar_id">
                            <option value="">-- Belum ditempatkan --</option>
                            <?php foreach ($kamar_list as $k): ?>
                                <option value="<?= (int) $k['id'] ?>"><?= e($k['kamar'] . ' / ' . $k['asrama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close>Batal</button>
                    <button type="submit" name="tambah" class="btn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
