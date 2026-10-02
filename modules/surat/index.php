<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'ketua', 'sekretaris'));
$title = 'Surat';
$menu = 'surat';

$jenis_list = surat_jenis_list();
$warga_list = db_all($koneksi, 'SELECT id, nama, nik FROM warga ORDER BY nama ASC');

/* ---------- Buat surat baru ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buat_surat'])) {
    $jenis      = trim($_POST['jenis'] ?? '');
    $warga_id   = (int) ($_POST['warga_id'] ?? 0);
    $keperluan  = trim($_POST['keperluan'] ?? '');
    $tgl_terbit = $_POST['tgl_terbit'] ?: date('Y-m-d');

    if (!array_key_exists($jenis, $jenis_list)) {
        flash_set('Jenis surat tidak valid.', 'err');
    } elseif ($warga_id <= 0 || !db_one($koneksi, 'SELECT id FROM warga WHERE id = ?', 'i', array($warga_id))) {
        flash_set('Warga yang dipilih tidak valid.', 'err');
    } elseif ($keperluan === '') {
        flash_set('Keperluan wajib diisi.', 'err');
    } else {
        $no_surat = nomor_surat_berikutnya($koneksi, $jenis);
        $ok = db_exec($koneksi,
            'INSERT INTO surat (no_surat, jenis, warga_id, keperluan, tgl_terbit, dibuat_oleh)
             VALUES (?,?,?,?,?,?)',
            'ssissi', array($no_surat, $jenis, $warga_id, $keperluan, $tgl_terbit, (int) $user['id']));
        if ($ok) {
            flash_set('Surat ' . $no_surat . ' berhasil dibuat.', 'ok');
        } else {
            flash_set('Gagal membuat surat (mungkin nomor sudah dipakai).', 'err');
        }
    }
    redirect('modules/surat/');
}

/* ---------- Hapus surat ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_surat'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $s = db_one($koneksi, 'SELECT id FROM surat WHERE id = ?', 'i', array($id));
    if (!$s) {
        flash_set('Surat tidak ditemukan.', 'err');
    } else {
        db_exec($koneksi, 'DELETE FROM surat WHERE id = ?', 'i', array($id));
        flash_set('Surat berhasil dihapus.', 'ok');
    }
    redirect('modules/surat/');
}

$rows = db_all($koneksi, 'SELECT * FROM surat ORDER BY tgl_terbit DESC, id DESC');

include __DIR__ . '/../../includes/header.php';
?>

<?= flash_get() ?>

<div class="sn-pagebar">
    <div>
        <h2 style="margin:0"><i class="fas fa-envelope-open-text" style="margin-right:8px;color:var(--brand-active)"></i>Surat Keterangan / Pengantar</h2>
        <p style="margin:4px 0 0;color:var(--text-soft);font-size:13.5px"><?= count($rows) ?> surat tercatat</p>
    </div>
    <button type="button" class="btn" data-snmodal-open="modalBuatSurat">
        <i class="fas fa-plus" style="margin-right:6px"></i>Buat Surat
    </button>
</div>

<div class="table-wrap">
<table>
    <thead><tr>
        <th>No. Surat</th><th>Jenis</th><th>Nama Warga</th><th>Keperluan</th><th>Tgl Terbit</th><th>Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="6" class="empty">Belum ada surat. Klik "Buat Surat" untuk membuat surat baru.</td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= e($r['no_surat']) ?></strong></td>
            <td><?= e($r['jenis']) ?></td>
            <td><?= e(warga_nama($koneksi, $r['warga_id'])) ?></td>
            <td><?= e($r['keperluan'] ?: '-') ?></td>
            <td><?= e(tgl_indo($r['tgl_terbit'])) ?></td>
            <td style="white-space:nowrap">
                <a href="<?= url('modules/surat/cetak.php?id=' . (int) $r['id']) ?>" target="_blank" rel="noopener"
                   class="btn btn-sm" title="Cetak surat"><i class="fas fa-print"></i>Cetak</a>
                <form method="post" action="" style="display:inline;margin-left:6px"
                      onsubmit="return confirm('Hapus surat <?= e(addslashes($r['no_surat'])) ?>? Tindakan ini tidak bisa dibatalkan.')">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" name="hapus_surat" class="btn btn-sm btn-warn" title="Hapus surat">
                        <i class="fas fa-trash"></i>Hapus
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<!-- ======== Modal buat surat ======== -->
<div class="sn-modal" id="modalBuatSurat" role="dialog" aria-modal="true" aria-label="Buat Surat">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-envelope-open-text" style="margin-right:8px;color:var(--brand-active)"></i>Buat Surat</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <div class="form-grid">
                    <div class="field"><label>Jenis Surat</label>
                        <select name="jenis" required>
                            <?php foreach ($jenis_list as $nama => $kode): ?>
                                <option value="<?= e($nama) ?>"><?= e($nama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Warga</label>
                        <select name="warga_id" required>
                            <option value="">-- Pilih warga --</option>
                            <?php foreach ($warga_list as $w): ?>
                                <option value="<?= (int) $w['id'] ?>"><?= e($w['nama']) ?> &mdash; <?= e($w['nik']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Keperluan</label>
                        <input type="text" name="keperluan" required maxlength="200" placeholder="cth. Pengurusan KTP baru">
                    </div>
                    <div class="field"><label>Tanggal Terbit</label>
                        <input type="date" name="tgl_terbit" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <p style="color:var(--text-soft);font-size:13px;margin:10px 0 0">
                    <i class="fas fa-info-circle"></i> Nomor surat dibuat otomatis sesuai urutan &amp; jenis.
                </p>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="buat_surat" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
