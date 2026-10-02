<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'ketua', 'sekretaris'));
$title = 'Kegiatan RT';
$menu = 'kegiatan';

/* ---------- Tambah kegiatan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $nama   = trim($_POST['nama'] ?? '');
    $tgl    = $_POST['tanggal'] ?? '';
    $waktu  = trim($_POST['waktu'] ?? '') ?: null;
    $tempat = trim($_POST['tempat'] ?? '') ?: null;
    $desc   = trim($_POST['deskripsi'] ?? '') ?: null;
    if ($nama === '' || $tgl === '') {
        flash_set('Nama kegiatan dan tanggal wajib diisi.', 'err');
    } else {
        $ok = db_exec($koneksi,
            'INSERT INTO kegiatan (nama, tanggal, waktu, tempat, deskripsi) VALUES (?,?,?,?,?)',
            'sssss', array($nama, $tgl, $waktu, $tempat, $desc));
        flash_set($ok ? 'Kegiatan berhasil ditambahkan.' : 'Gagal menambah kegiatan.', $ok ? 'ok' : 'err');
    }
    redirect('modules/kegiatan/');
}

/* ---------- Edit kegiatan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id     = (int) ($_POST['id'] ?? 0);
    $nama   = trim($_POST['nama'] ?? '');
    $tgl    = $_POST['tanggal'] ?? '';
    $waktu  = trim($_POST['waktu'] ?? '') ?: null;
    $tempat = trim($_POST['tempat'] ?? '') ?: null;
    $desc   = trim($_POST['deskripsi'] ?? '') ?: null;
    $row = db_one($koneksi, 'SELECT id FROM kegiatan WHERE id = ?', 'i', array($id));
    if (!$row) {
        flash_set('Kegiatan tidak ditemukan.', 'err');
    } elseif ($nama === '' || $tgl === '') {
        flash_set('Nama kegiatan dan tanggal wajib diisi.', 'err');
    } else {
        db_exec($koneksi,
            'UPDATE kegiatan SET nama=?, tanggal=?, waktu=?, tempat=?, deskripsi=? WHERE id=?',
            'sssssi', array($nama, $tgl, $waktu, $tempat, $desc, $id));
        flash_set('Kegiatan berhasil diperbarui.');
    }
    redirect('modules/kegiatan/');
}

/* ---------- Hapus kegiatan ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id = (int) ($_POST['id'] ?? 0);
    db_exec($koneksi, 'DELETE FROM kegiatan WHERE id = ?', 'i', array($id));
    flash_set('Kegiatan dihapus.');
    redirect('modules/kegiatan/');
}

$rows = db_all($koneksi, 'SELECT * FROM kegiatan ORDER BY tanggal DESC, id DESC');
$today = date('Y-m-d');
$bln = array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
             7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des');

include __DIR__ . '/../../includes/header.php';
?>
<style>
.keg-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 16px; }
@media (max-width: 640px) { .keg-list { grid-template-columns: 1fr; } }
.keg-item { display: flex; gap: 16px; }
.keg-date {
    flex: 0 0 64px; text-align: center; border-radius: 14px;
    background: var(--brand-soft, rgba(53,180,74,.1));
    border: 1px solid var(--border); padding: 10px 4px; align-self: flex-start;
}
.keg-day { font-size: 28px; font-weight: 800; color: var(--brand-hover); line-height: 1; }
.keg-mon { font-size: 12px; font-weight: 700; color: var(--text-soft); margin-top: 4px; }
.keg-yr { font-size: 11px; color: var(--text-faint); }
.keg-meta { font-size: 13px; color: var(--text-soft); margin: 6px 0 4px; display: flex; gap: 14px; flex-wrap: wrap; }
.keg-meta i { margin-right: 5px; color: var(--brand-hover); }
.keg-desc { font-size: 13.5px; color: var(--text); margin: 4px 0 0; white-space: pre-line; }
.keg-acts { margin-top: 12px; display: flex; gap: 8px; }
</style>

<div class="sn-pagebar">
    <h2 class="section-title" style="margin:0"><i class="fas fa-calendar-days sec-ico"></i>Kegiatan RT</h2>
    <button type="button" class="btn" data-snmodal-open="modalTambahKegiatan">
        <i class="fas fa-plus" style="margin-right:6px"></i>Tambah Kegiatan
    </button>
</div>

<div class="keg-list">
<?php if (!$rows): ?>
    <div class="card">Belum ada kegiatan tercatat.</div>
<?php else: foreach ($rows as $r):
    $t = strtotime($r['tanggal']);
    $akan_datang = $r['tanggal'] >= $today;
?>
    <div class="card keg-item">
        <div class="keg-date">
            <div class="keg-day"><?= e(date('d', $t)) ?></div>
            <div class="keg-mon"><?= e($bln[(int) date('n', $t)]) ?></div>
            <div class="keg-yr"><?= e(date('Y', $t)) ?></div>
        </div>
        <div style="flex:1;min-width:0">
            <div>
                <span class="badge <?= $akan_datang ? 'badge-ok' : 'badge-warn' ?>">
                    <?= $akan_datang ? 'Akan Datang' : 'Selesai' ?>
                </span>
            </div>
            <h4 style="margin:8px 0 0;font-size:16px;color:var(--text)"><?= e($r['nama']) ?></h4>
            <div class="keg-meta">
                <?php if (!empty($r['waktu'])): ?>
                    <span><i class="fas fa-clock"></i><?= e($r['waktu']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['tempat'])): ?>
                    <span><i class="fas fa-location-dot"></i><?= e($r['tempat']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($r['deskripsi'])): ?>
                <p class="keg-desc"><?= e($r['deskripsi']) ?></p>
            <?php endif; ?>
            <div class="keg-acts">
                <button type="button" class="btn btn-sm" data-snmodal-open="modalEditKegiatan<?= (int) $r['id'] ?>">
                    <i class="fas fa-pen" style="margin-right:5px"></i>Edit
                </button>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus kegiatan ini?')">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" name="hapus" class="btn btn-sm btn-warn">
                        <i class="fas fa-trash" style="margin-right:5px"></i>Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>

<!-- ======== Modal tambah kegiatan ======== -->
<div class="sn-modal" id="modalTambahKegiatan" role="dialog" aria-modal="true" aria-label="Tambah Kegiatan">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-calendar-plus" style="margin-right:8px;color:var(--brand-active)"></i>Tambah Kegiatan</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <div class="form-grid">
                    <div class="field"><label>Nama Kegiatan</label><input type="text" name="nama" required maxlength="120"></div>
                    <div class="field"><label>Tanggal</label><input type="date" name="tanggal" required value="<?= date('Y-m-d') ?>"></div>
                    <div class="field"><label>Waktu</label><input type="text" name="waktu" maxlength="20" placeholder="mis. 19.30 WIB"></div>
                    <div class="field"><label>Tempat</label><input type="text" name="tempat" maxlength="100" placeholder="mis. Balai RT"></div>
                    <div class="field" style="grid-column:1/-1"><label>Deskripsi</label><textarea name="deskripsi" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="tambah" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======== Modal edit per kegiatan ======== -->
<?php foreach ($rows as $r): ?>
<div class="sn-modal" id="modalEditKegiatan<?= (int) $r['id'] ?>" role="dialog" aria-modal="true" aria-label="Edit Kegiatan">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-calendar-pen" style="margin-right:8px;color:var(--brand-active)"></i>Edit Kegiatan</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <div class="form-grid">
                    <div class="field"><label>Nama Kegiatan</label><input type="text" name="nama" required maxlength="120" value="<?= e($r['nama']) ?>"></div>
                    <div class="field"><label>Tanggal</label><input type="date" name="tanggal" required value="<?= e($r['tanggal']) ?>"></div>
                    <div class="field"><label>Waktu</label><input type="text" name="waktu" maxlength="20" value="<?= e($r['waktu'] ?? '') ?>"></div>
                    <div class="field"><label>Tempat</label><input type="text" name="tempat" maxlength="100" value="<?= e($r['tempat'] ?? '') ?>"></div>
                    <div class="field" style="grid-column:1/-1"><label>Deskripsi</label><textarea name="deskripsi" rows="3"><?= e($r['deskripsi'] ?? '') ?></textarea></div>
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
