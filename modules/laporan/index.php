<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'ketua', 'sekretaris'));
$title = 'Laporan Warga';
$menu = 'laporan';

$kategori_list = array('Keamanan', 'Kebersihan', 'Fasilitas', 'Sosial', 'Lainnya');
$status_list   = array('baru', 'diproses', 'selesai');

/* ---------- Catat laporan baru ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $pelapor  = trim($_POST['pelapor'] ?? '');
    $kategori = $_POST['kategori'] ?? 'Lainnya';
    if (!in_array($kategori, $kategori_list, true)) $kategori = 'Lainnya';
    $judul    = trim($_POST['judul'] ?? '');
    $isi      = trim($_POST['isi'] ?? '');
    $tanggal  = $_POST['tanggal'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d');
    if ($judul === '' || $isi === '') {
        flash_set('Judul dan isi laporan wajib diisi.', 'err');
    } else {
        $ok = db_exec($koneksi,
            'INSERT INTO laporan (pelapor, kategori, judul, isi, tanggal, status) VALUES (?,?,?,?,?,?)',
            'ssssss', array($pelapor !== '' ? $pelapor : null, $kategori, $judul, $isi, $tanggal, 'baru'));
        flash_set($ok ? 'Laporan berhasil dicatat.' : 'Gagal mencatat laporan.', $ok ? 'ok' : 'err');
    }
    redirect('modules/laporan/' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

/* ---------- Tindak lanjuti (update status + tanggapan) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tindak'])) {
    $id       = (int) ($_POST['id'] ?? 0);
    $status   = $_POST['status'] ?? 'diproses';
    if (!in_array($status, $status_list, true)) $status = 'diproses';
    $tanggapan = trim($_POST['tanggapan'] ?? '') ?: null;
    $r = db_one($koneksi, 'SELECT id FROM laporan WHERE id = ?', 'i', array($id));
    if (!$r) {
        flash_set('Laporan tidak ditemukan.', 'err');
    } else {
        db_exec($koneksi, 'UPDATE laporan SET status = ?, tanggapan = ? WHERE id = ?',
            'ssi', array($status, $tanggapan, $id));
        flash_set('Laporan #' . $id . ' diperbarui menjadi "' . $status . '".', 'ok');
    }
    redirect('modules/laporan/' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

/* ---------- Hapus ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $r = db_one($koneksi, 'SELECT id FROM laporan WHERE id = ?', 'i', array($id));
    if (!$r) {
        flash_set('Laporan tidak ditemukan.', 'err');
    } else {
        db_exec($koneksi, 'DELETE FROM laporan WHERE id = ?', 'i', array($id));
        flash_set('Laporan #' . $id . ' dihapus.', 'ok');
    }
    redirect('modules/laporan/');
}

/* ---------- Filter status (GET) ---------- */
$fstatus = $_GET['status'] ?? 'semua';
if (!in_array($fstatus, array('semua', 'baru', 'diproses', 'selesai'), true)) $fstatus = 'semua';

$rows = db_all($koneksi,
    'SELECT * FROM laporan' . ($fstatus === 'semua' ? '' : ' WHERE status = ?') .
    ' ORDER BY tanggal DESC, id DESC',
    $fstatus === 'semua' ? '' : 's', $fstatus === 'semua' ? array() : array($fstatus));

$cnt = array('semua' => 0, 'baru' => 0, 'diproses' => 0, 'selesai' => 0);
foreach (db_all($koneksi, 'SELECT status, COUNT(*) AS n FROM laporan GROUP BY status') as $c) {
    $cnt[$c['status']] = (int) $c['n'];
    $cnt['semua'] += (int) $c['n'];
}

$badge = array('baru' => 'badge-warn', 'diproses' => 'badge-info', 'selesai' => 'badge-ok');

include __DIR__ . '/../../includes/header.php';
?>
<style>
.badge-info { background: #DCEBFF; color: #1E63C4; }
body.dark-mode .badge-info { background: #223048; color: #8FB4FF; }
.pillbar { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 16px; }
.pillbar a { padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 700;
    text-decoration: none; color: var(--text-soft); background: var(--card);
    border: 1px solid var(--border); transition: all .2s; }
.pillbar a:hover { color: var(--text); border-color: var(--brand-active); }
.pillbar a.active { background: var(--brand-active); border-color: var(--brand-active); color: #fff; }
.tbl-link { color: var(--brand-active); font-weight: 700; text-decoration: none; cursor: pointer; background: none; border: none; padding: 0; font-size: inherit; }
.tbl-link:hover { text-decoration: underline; }
.tbl-act { white-space: nowrap; }
.dl { display: grid; grid-template-columns: 130px 1fr; gap: 6px 12px; font-size: 14px; }
.dl dt { color: var(--text-soft); font-weight: 600; }
.dl dd { margin: 0; white-space: pre-wrap; }
</style>

<div class="sn-pagebar">
    <h2 class="section-title" style="margin:0"><i class="fas fa-bullhorn sec-ico"></i>Laporan Warga</h2>
    <button type="button" class="btn" data-snmodal-open="modalTambahLaporan">
        <i class="fas fa-plus" style="margin-right:6px"></i>Catat Laporan
    </button>
</div>

<div class="pillbar">
    <?php foreach (array('semua' => 'Semua', 'baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai') as $k => $label): ?>
        <a href="<?= url('modules/laporan/') . ($k === 'semua' ? '' : '?status=' . $k) ?>"
           class="<?= $fstatus === $k ? 'active' : '' ?>"><?= e($label) ?> (<?= (int) $cnt[$k] ?>)</a>
    <?php endforeach; ?>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Tanggal</th><th>Pelapor</th><th>Kategori</th><th>Judul</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="6" class="empty">Belum ada laporan pada filter ini.</td></tr>
    <?php else: foreach ($rows as $r): $rid = (int) $r['id']; ?>
        <tr>
            <td><?= e(tgl_indo($r['tanggal'])) ?></td>
            <td><?= e($r['pelapor'] !== null && $r['pelapor'] !== '' ? $r['pelapor'] : 'Anonim') ?></td>
            <td><?= e($r['kategori']) ?></td>
            <td>
                <button type="button" class="tbl-link" data-snmodal-open="modalDetail<?= $rid ?>"><strong><?= e($r['judul']) ?></strong></button>
            </td>
            <td><span class="badge <?= $badge[$r['status']] ?? 'badge-warn' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            <td class="tbl-act">
                <button type="button" class="btn btn-sm" data-snmodal-open="modalTindak<?= $rid ?>">
                    <i class="fas fa-wrench" style="margin-right:5px"></i>Tindak Lanjuti
                </button>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus laporan ini?')">
                    <input type="hidden" name="id" value="<?= $rid ?>">
                    <button type="submit" name="hapus" class="btn btn-sm btn-ghost"><i class="fas fa-trash" style="margin-right:5px"></i>Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<!-- ======== Modal catat laporan ======== -->
<div class="sn-modal" id="modalTambahLaporan" role="dialog" aria-modal="true" aria-label="Catat Laporan">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-bullhorn" style="margin-right:8px;color:var(--brand-active)"></i>Catat Laporan</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <div class="form-grid">
                    <div class="field"><label>Pelapor <small>(boleh kosong = anonim)</small></label>
                        <input type="text" name="pelapor" maxlength="100" placeholder="Nama pelapor..."></div>
                    <div class="field"><label>Kategori</label>
                        <select name="kategori">
                            <?php foreach ($kategori_list as $k): ?>
                                <option value="<?= e($k) ?>"><?= e($k) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="field"><label>Judul Laporan</label>
                        <input type="text" name="judul" required maxlength="150"></div>
                    <div class="field"><label>Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="field" style="grid-column:1/-1"><label>Isi Laporan</label>
                        <textarea name="isi" rows="4" required></textarea></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="tambah" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======== Modal detail + tindak lanjut per baris ======== -->
<?php foreach ($rows as $r): $rid = (int) $r['id']; ?>
<div class="sn-modal" id="modalDetail<?= $rid ?>" role="dialog" aria-modal="true" aria-label="Detail Laporan">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-file-lines" style="margin-right:8px;color:var(--brand-active)"></i>Detail Laporan #<?= $rid ?></h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <dl class="dl">
                <dt>Tanggal</dt><dd><?= e(tgl_indo($r['tanggal'])) ?></dd>
                <dt>Pelapor</dt><dd><?= e($r['pelapor'] !== null && $r['pelapor'] !== '' ? $r['pelapor'] : 'Anonim') ?></dd>
                <dt>Kategori</dt><dd><?= e($r['kategori']) ?></dd>
                <dt>Judul</dt><dd><?= e($r['judul']) ?></dd>
                <dt>Isi</dt><dd><?= e($r['isi']) ?></dd>
                <dt>Status</dt><dd><span class="badge <?= $badge[$r['status']] ?? 'badge-warn' ?>"><?= e(ucfirst($r['status'])) ?></span></dd>
                <dt>Tanggapan</dt><dd><?= e($r['tanggapan'] !== null && $r['tanggapan'] !== '' ? $r['tanggapan'] : '-') ?></dd>
            </dl>
            <div class="form-actions" style="margin-top:18px">
                <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Tutup</button>
                <button type="button" class="btn" data-snmodal-open="modalTindak<?= $rid ?>"><i class="fas fa-wrench"></i>Tindak Lanjuti</button>
            </div>
        </div>
    </div>
</div>

<div class="sn-modal" id="modalTindak<?= $rid ?>" role="dialog" aria-modal="true" aria-label="Tindak Lanjuti">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-wrench" style="margin-right:8px;color:var(--brand-active)"></i>Tindak Lanjuti #<?= $rid ?></h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <input type="hidden" name="id" value="<?= $rid ?>">
                <div class="form-grid">
                    <div class="field"><label>Status</label>
                        <select name="status">
                            <?php foreach ($status_list as $s): ?>
                                <option value="<?= e($s) ?>"<?= $r['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="field" style="grid-column:1/-1"><label>Tanggapan</label>
                        <textarea name="tanggapan" rows="4" placeholder="Tulis tanggapan/tindak lanjut..."><?= e($r['tanggapan'] ?? '') ?></textarea></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="tindak" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
