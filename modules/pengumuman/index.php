<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'ketua', 'sekretaris'));
$title = 'Pengumuman';
$menu = 'pengumuman';

/* ---------- Tambah pengumuman ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $judul = trim($_POST['judul'] ?? '');
    $isi   = trim($_POST['isi'] ?? '');
    $tgl   = $_POST['tanggal'] ?: date('Y-m-d');
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    if ($judul === '' || $isi === '') {
        flash_set('Judul dan isi pengumuman wajib diisi.', 'err');
    } else {
        $ok = db_exec($koneksi,
            'INSERT INTO pengumuman (judul, isi, tanggal, aktif, dibuat_oleh) VALUES (?,?,?,?,?)',
            'sssii', array($judul, $isi, $tgl, $aktif, (int) ($user['id'] ?? 0)));
        flash_set($ok ? 'Pengumuman berhasil dibuat.' : 'Gagal membuat pengumuman.', $ok ? 'ok' : 'err');
    }
    redirect('modules/pengumuman/');
}

/* ---------- Edit pengumuman ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id    = (int) ($_POST['id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $isi   = trim($_POST['isi'] ?? '');
    $tgl   = $_POST['tanggal'] ?: date('Y-m-d');
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $row = db_one($koneksi, 'SELECT id FROM pengumuman WHERE id = ?', 'i', array($id));
    if (!$row) {
        flash_set('Pengumuman tidak ditemukan.', 'err');
    } elseif ($judul === '' || $isi === '') {
        flash_set('Judul dan isi pengumuman wajib diisi.', 'err');
    } else {
        db_exec($koneksi,
            'UPDATE pengumuman SET judul=?, isi=?, tanggal=?, aktif=? WHERE id=?',
            'sssii', array($judul, $isi, $tgl, $aktif, $id));
        flash_set('Pengumuman berhasil diperbarui.');
    }
    redirect('modules/pengumuman/');
}

/* ---------- Hapus pengumuman ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id = (int) ($_POST['id'] ?? 0);
    db_exec($koneksi, 'DELETE FROM pengumuman WHERE id = ?', 'i', array($id));
    flash_set('Pengumuman dihapus.');
    redirect('modules/pengumuman/');
}

/* ---------- Toggle aktif / nonaktif (link aksi) ---------- */
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $row = db_one($koneksi, 'SELECT id, aktif FROM pengumuman WHERE id = ?', 'i', array($id));
    if (!$row) {
        flash_set('Pengumuman tidak ditemukan.', 'err');
    } else {
        $baru = $row['aktif'] ? 0 : 1;
        db_exec($koneksi, 'UPDATE pengumuman SET aktif=? WHERE id=?', 'ii', array($baru, $id));
        flash_set('Pengumuman ' . ($baru ? 'diaktifkan.' : 'dinonaktifkan.'));
    }
    redirect('modules/pengumuman/');
}

$rows = db_all($koneksi, 'SELECT * FROM pengumuman ORDER BY tanggal DESC, id DESC');

include __DIR__ . '/../../includes/header.php';
?>

<div class="sn-pagebar">
    <h2 class="section-title" style="margin:0"><i class="fas fa-bullhorn sec-ico"></i>Pengumuman</h2>
    <button type="button" class="btn" data-snmodal-open="modalTambahPengumuman">
        <i class="fas fa-plus" style="margin-right:6px"></i>Buat Pengumuman
    </button>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Judul</th><th>Tanggal</th><th>Status</th><th style="width:250px">Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="4" class="empty">Belum ada pengumuman.</td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= e($r['judul']) ?></strong></td>
            <td><?= e(tgl_indo($r['tanggal'])) ?></td>
            <td>
                <span class="badge <?= $r['aktif'] ? 'badge-ok' : 'badge-warn' ?>">
                    <?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </td>
            <td style="white-space:nowrap">
                <button type="button" class="btn btn-sm" data-snmodal-open="modalEditPengumuman<?= (int) $r['id'] ?>">
                    <i class="fas fa-pen" style="margin-right:5px"></i>Edit
                </button>
                <a href="<?= url('modules/pengumuman/') ?>?toggle=<?= (int) $r['id'] ?>"
                   class="btn btn-sm btn-ghost"
                   onclick="return confirm('<?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?> pengumuman ini?')">
                    <i class="fas fa-<?= $r['aktif'] ? 'eye-slash' : 'eye' ?>" style="margin-right:5px"></i><?= $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                </a>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus pengumuman ini?')">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" name="hapus" class="btn btn-sm btn-warn">
                        <i class="fas fa-trash" style="margin-right:5px"></i>Hapus
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<!-- ======== Modal tambah pengumuman ======== -->
<div class="sn-modal" id="modalTambahPengumuman" role="dialog" aria-modal="true" aria-label="Buat Pengumuman">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-bullhorn" style="margin-right:8px;color:var(--brand-active)"></i>Buat Pengumuman</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <div class="form-grid">
                    <div class="field"><label>Judul</label><input type="text" name="judul" required maxlength="150"></div>
                    <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
                    <div class="field" style="grid-column:1/-1"><label>Isi Pengumuman</label><textarea name="isi" rows="5" required></textarea></div>
                    <div class="field"><label style="display:flex;align-items:center;gap:8px;font-weight:400">
                        <input type="checkbox" name="aktif" value="1" checked style="width:auto">Tampilkan sebagai aktif
                    </label></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="tambah" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======== Modal edit per pengumuman ======== -->
<?php foreach ($rows as $r): ?>
<div class="sn-modal" id="modalEditPengumuman<?= (int) $r['id'] ?>" role="dialog" aria-modal="true" aria-label="Edit Pengumuman">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-pen-to-square" style="margin-right:8px;color:var(--brand-active)"></i>Edit Pengumuman</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <div class="form-grid">
                    <div class="field"><label>Judul</label><input type="text" name="judul" required maxlength="150" value="<?= e($r['judul']) ?>"></div>
                    <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= e($r['tanggal']) ?>"></div>
                    <div class="field" style="grid-column:1/-1"><label>Isi Pengumuman</label><textarea name="isi" rows="5" required><?= e($r['isi']) ?></textarea></div>
                    <div class="field"><label style="display:flex;align-items:center;gap:8px;font-weight:400">
                        <input type="checkbox" name="aktif" value="1"<?= $r['aktif'] ? ' checked' : '' ?> style="width:auto">Tampilkan sebagai aktif
                    </label></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="edit" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
