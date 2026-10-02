<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'ketua', 'bendahara'));
$title = 'Keuangan RT';
$menu = 'keuangan';

$tab = $_GET['tab'] ?? 'kas';
if (!in_array($tab, array('kas', 'iuran'), true)) $tab = 'kas';

/* ================= POST handlers ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_transaksi'])) {
        $tanggal = trim($_POST['tanggal'] ?? '');
        $jenis = $_POST['jenis'] ?? '';
        if (!in_array($jenis, array('masuk', 'keluar'), true)) $jenis = '';
        $kategori = trim($_POST['kategori'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $jumlah = max(0, (int) ($_POST['jumlah'] ?? 0));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) && $jenis && $kategori !== '' && $jumlah > 0) {
            $ok = db_exec($koneksi,
                'INSERT INTO kas_transaksi (tanggal, jenis, kategori, keterangan, jumlah, dibuat_oleh)
                 VALUES (?,?,?,?,?,?)',
                'ssssii', array($tanggal, $jenis, $kategori, $keterangan, $jumlah, $user['id']));
            flash_set($ok ? 'Transaksi kas dicatat.' : 'Gagal mencatat transaksi.', $ok ? 'ok' : 'err');
        } else {
            flash_set('Data transaksi belum lengkap/valid.', 'err');
        }
        redirect('modules/keuangan/');
    } elseif (isset($_POST['hapus_transaksi'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $n = db_exec($koneksi, 'DELETE FROM kas_transaksi WHERE id = ?', 'i', array($id));
        flash_set($n ? 'Transaksi kas dihapus.' : 'Gagal menghapus transaksi.', $n ? 'ok' : 'err');
        redirect('modules/keuangan/');
    } elseif (isset($_POST['generate_iuran'])) {
        $periode = trim($_POST['periode'] ?? '');
        $jumlah = max(0, (int) ($_POST['jumlah'] ?? 0));
        if (preg_match('/^\d{4}-\d{2}$/', $periode) && $jumlah > 0) {
            $n = db_exec($koneksi,
                "INSERT IGNORE INTO iuran (no_kk, periode, jumlah, status)
                 SELECT DISTINCT no_kk, ?, ?, 'belum' FROM warga WHERE no_kk <> ''",
                'si', array($periode, $jumlah));
            flash_set('Iuran ' . periode_indo($periode) . ' digenerate untuk ' . (int) $n . ' KK.');
        } else {
            flash_set('Periode/nominal iuran belum valid.', 'err');
        }
        redirect('modules/keuangan/?tab=iuran&periode=' . ($periode !== '' ? $periode : date('Y-m')));
    } elseif (isset($_POST['bayar_iuran'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $periode = trim($_POST['periode'] ?? date('Y-m'));
        $r = db_one($koneksi, 'SELECT * FROM iuran WHERE id = ?', 'i', array($id));
        if ($r && $r['status'] === 'belum') {
            $today = date('Y-m-d');
            $ok = db_exec($koneksi, "UPDATE iuran SET status = 'lunas', tgl_bayar = ? WHERE id = ?",
                'si', array($today, $id));
            if ($ok) {
                db_exec($koneksi,
                    'INSERT INTO kas_transaksi (tanggal, jenis, kategori, keterangan, jumlah, dibuat_oleh)
                     VALUES (?,?,?,?,?,?)',
                    'ssssii', array($today, 'masuk', 'Iuran',
                        'Iuran ' . periode_indo($r['periode']) . ' - KK ' . $r['no_kk'],
                        (int) $r['jumlah'], $user['id']));
            }
            flash_set($ok ? 'Iuran ditandai lunas & kas bertambah.' : 'Gagal menandai lunas.', $ok ? 'ok' : 'err');
        } else {
            flash_set('Data iuran tidak valid / sudah lunas.', 'err');
        }
        redirect('modules/keuangan/?tab=iuran&periode=' . $periode);
    } elseif (isset($_POST['hapus_iuran'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $periode = trim($_POST['periode'] ?? date('Y-m'));
        $r = db_one($koneksi, 'SELECT status FROM iuran WHERE id = ?', 'i', array($id));
        if ($r && $r['status'] === 'belum') {
            $n = db_exec($koneksi, 'DELETE FROM iuran WHERE id = ?', 'i', array($id));
            flash_set($n ? 'Iuran dihapus.' : 'Gagal menghapus iuran.', $n ? 'ok' : 'err');
        } else {
            flash_set('Hanya iuran berstatus belum yg boleh dihapus.', 'err');
        }
        redirect('modules/keuangan/?tab=iuran&periode=' . $periode);
    }
}

/* ================= Data ================= */
$kategori_kas = array('Iuran', 'Dana Sosial', 'Bantuan', 'Operasional', 'Kegiatan', 'Lainnya');

if ($tab === 'kas') {
    $bulan = trim($_GET['bulan'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) $bulan = date('Y-m');
    $dari = $bulan . '-01';
    $sampai = date('Y-m-d', strtotime($dari . ' +1 month'));

    $masuk = db_one($koneksi, "SELECT COALESCE(SUM(jumlah),0) AS s FROM kas_transaksi WHERE jenis = 'masuk'");
    $keluar = db_one($koneksi, "SELECT COALESCE(SUM(jumlah),0) AS s FROM kas_transaksi WHERE jenis = 'keluar'");
    $tot_masuk = (int) ($masuk['s'] ?? 0);
    $tot_keluar = (int) ($keluar['s'] ?? 0);
    $saldo = saldo_kas($koneksi);

    $transaksi = db_all($koneksi,
        'SELECT * FROM kas_transaksi WHERE tanggal >= ? AND tanggal < ? ORDER BY tanggal DESC, id DESC',
        'ss', array($dari, $sampai));
} else {
    $periode = trim($_GET['periode'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $periode)) $periode = date('Y-m');

    $stat = db_one($koneksi,
        "SELECT COUNT(*) AS total,
                SUM(status = 'lunas') AS lunas,
                COALESCE(SUM(CASE WHEN status = 'lunas' THEN jumlah ELSE 0 END), 0) AS terkumpul,
                COALESCE(SUM(jumlah), 0) AS tagihan
         FROM iuran WHERE periode = ?",
        's', array($periode));
    $kk_total = (int) ($stat['total'] ?? 0);
    $kk_lunas = (int) ($stat['lunas'] ?? 0);
    $terkumpul = (int) ($stat['terkumpul'] ?? 0);
    $menunggu = max(0, (int) ($stat['tagihan'] ?? 0) - $terkumpul);

    $iuran = db_all($koneksi, 'SELECT * FROM iuran WHERE periode = ? ORDER BY no_kk ASC',
        's', array($periode));
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.sn-tabbar { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.sn-tab { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 999px;
    background: var(--card); border: 1px solid var(--border); font-weight: 700;
    color: var(--text-soft); text-decoration: none; font-size: 14px; transition: all .18s ease; }
.sn-tab.active { background: var(--brand); color: #fff; border-color: var(--brand); }
.sn-stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
@media (max-width: 640px) { .sn-stat-row { grid-template-columns: 1fr; } }
.sn-stat { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 18px 20px; }
.sn-stat .stat-num { font-weight: 800; font-size: 28px; letter-spacing: -.02em; color: var(--text); line-height: 1.15; }
.sn-stat .stat-num.ok { color: #2e9e44; }
.sn-stat .stat-num.warn { color: #d04545; }
.sn-stat .stat-sub { font-size: 13px; color: var(--text-soft); margin-top: 6px; }
.sn-num { text-align: right; font-variant-numeric: tabular-nums; }
</style>

<div class="sn-tabbar">
    <a href="<?= url('modules/keuangan/') ?>" class="sn-tab<?= $tab === 'kas' ? ' active' : '' ?>">
        <i class="fas fa-wallet"></i>Kas RT</a>
    <a href="<?= url('modules/keuangan/?tab=iuran') ?>" class="sn-tab<?= $tab === 'iuran' ? ' active' : '' ?>">
        <i class="fas fa-hand-holding-dollar"></i>Iuran Warga</a>
</div>

<?php if ($tab === 'kas'): ?>

<div class="sn-stat-row">
    <div class="sn-stat">
        <div class="stat-num ok"><?= e(rupiah($tot_masuk)) ?></div>
        <div class="stat-sub"><i class="fas fa-arrow-down"></i> Total Pemasukan</div>
    </div>
    <div class="sn-stat">
        <div class="stat-num warn"><?= e(rupiah($tot_keluar)) ?></div>
        <div class="stat-sub"><i class="fas fa-arrow-up"></i> Total Pengeluaran</div>
    </div>
    <div class="sn-stat">
        <div class="stat-num"><?= e(rupiah($saldo)) ?></div>
        <div class="stat-sub"><i class="fas fa-piggy-bank"></i> Saldo Kas</div>
    </div>
</div>

<div class="form-card" style="margin-bottom:20px">
    <h2 class="section-title" style="margin-top:0"><i class="fas fa-wallet sec-ico"></i>Kas RT</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;justify-content:space-between">
        <form method="get" action="" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div class="field" style="margin:0">
                <label>Bulan</label>
                <input type="month" name="bulan" value="<?= e($bulan) ?>">
            </div>
            <button type="submit" class="btn btn-sm"><i class="fas fa-filter"></i>Tampilkan</button>
        </form>
        <button type="button" class="btn" data-snmodal-open="modalKas"><i class="fas fa-plus"></i>Catat Transaksi</button>
    </div>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Keterangan</th><th class="sn-num">Jumlah</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$transaksi): ?>
        <tr><td colspan="6" class="empty">Belum ada transaksi bulan <?= e(periode_indo($bulan)) ?>.</td></tr>
    <?php else: foreach ($transaksi as $t): ?>
        <tr>
            <td><?= e(tgl_indo($t['tanggal'])) ?></td>
            <td><span class="badge <?= $t['jenis'] === 'masuk' ? 'badge-ok' : 'badge-err' ?>"><?= e($t['jenis']) ?></span></td>
            <td><?= e($t['kategori']) ?></td>
            <td><?= e($t['keterangan'] ?: '-') ?></td>
            <td class="sn-num"><strong><?= e(rupiah($t['jumlah'])) ?></strong></td>
            <td>
                <form method="post" action="" onsubmit="return confirm('Hapus transaksi ini?')" style="display:inline">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button type="submit" name="hapus_transaksi" class="btn btn-sm btn-ghost"><i class="fas fa-trash"></i></button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<!-- Modal catat transaksi -->
<div class="sn-modal" id="modalKas">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-pen sec-ico"></i>Catat Transaksi Kas</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <div class="form-grid">
                    <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="field"><label>Jenis</label>
                        <select name="jenis" required>
                            <option value="masuk">Pemasukan</option>
                            <option value="keluar">Pengeluaran</option>
                        </select>
                    </div>
                    <div class="field"><label>Kategori</label>
                        <select name="kategori" required>
                            <?php foreach ($kategori_kas as $k): ?>
                            <option value="<?= e($k) ?>"><?= e($k) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label>Jumlah (Rp)</label><input type="number" name="jumlah" min="1" required></div>
                    <div class="field" style="grid-column:1/-1"><label>Keterangan</label><input type="text" name="keterangan" maxlength="200"></div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-times"></i>Batal</button>
                    <button type="submit" name="tambah_transaksi" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php else: ?>

<div class="sn-stat-row">
    <div class="sn-stat">
        <div class="stat-num"><?= e($kk_lunas) ?> <span style="font-size:16px;color:var(--text-soft)">/ <?= e($kk_total) ?></span></div>
        <div class="stat-sub"><i class="fas fa-house-user"></i> KK lunas (<?= e(periode_indo($periode)) ?>)</div>
    </div>
    <div class="sn-stat">
        <div class="stat-num ok"><?= e(rupiah($terkumpul)) ?></div>
        <div class="stat-sub"><i class="fas fa-coins"></i> Total terkumpul bulan ini</div>
    </div>
    <div class="sn-stat">
        <div class="stat-num warn"><?= e(rupiah($menunggu)) ?></div>
        <div class="stat-sub"><i class="fas fa-hourglass-half"></i> Menunggu pembayaran</div>
    </div>
</div>

<div class="form-card" style="margin-bottom:20px">
    <h2 class="section-title" style="margin-top:0"><i class="fas fa-hand-holding-dollar sec-ico"></i>Iuran Warga</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;justify-content:space-between">
        <form method="get" action="" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="tab" value="iuran">
            <div class="field" style="margin:0">
                <label>Periode</label>
                <input type="month" name="periode" value="<?= e($periode) ?>">
            </div>
            <button type="submit" class="btn btn-sm"><i class="fas fa-filter"></i>Tampilkan</button>
        </form>
        <button type="button" class="btn" data-snmodal-open="modalIuran"><i class="fas fa-users"></i>Generate Iuran</button>
    </div>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>No. KK</th><th>Kepala Keluarga</th><th class="sn-num">Jumlah</th><th>Status</th><th>Tgl Bayar</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$iuran): ?>
        <tr><td colspan="6" class="empty">Belum ada data iuran periode <?= e(periode_indo($periode)) ?>. Klik "Generate Iuran" untuk membuat.</td></tr>
    <?php else: foreach ($iuran as $r): ?>
        <tr>
            <td><?= e($r['no_kk']) ?></td>
            <td><?= e(kk_kepala($koneksi, $r['no_kk'])) ?></td>
            <td class="sn-num"><strong><?= e(rupiah($r['jumlah'])) ?></strong></td>
            <td><span class="badge <?= $r['status'] === 'lunas' ? 'badge-ok' : 'badge-warn' ?>"><?= e($r['status']) ?></span></td>
            <td><?= e(tgl_indo($r['tgl_bayar'])) ?></td>
            <td style="white-space:nowrap">
                <?php if ($r['status'] === 'belum'): ?>
                <form method="post" action="" style="display:inline">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="periode" value="<?= e($periode) ?>">
                    <button type="submit" name="bayar_iuran" class="btn btn-sm"><i class="fas fa-check"></i>Tandai Lunas</button>
                </form>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus iuran ini?')">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="periode" value="<?= e($periode) ?>">
                    <button type="submit" name="hapus_iuran" class="btn btn-sm btn-ghost"><i class="fas fa-trash"></i></button>
                </form>
                <?php else: ?>-<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<!-- Modal generate iuran -->
<div class="sn-modal" id="modalIuran">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-users sec-ico"></i>Generate Iuran</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <div class="form-grid">
                    <div class="field"><label>Periode</label><input type="month" name="periode" value="<?= e($periode) ?>" required></div>
                    <div class="field"><label>Jumlah per KK (Rp)</label><input type="number" name="jumlah" min="1" value="<?= (int) IURAN_DEFAULT ?>" required></div>
                </div>
                <p style="font-size:13px;color:var(--text-soft)">Iuran dibuat untuk semua KK di data warga (satu baris per KK per periode). KK yg sudah punya iuran di periode itu tidak diduplikasi.</p>
                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-times"></i>Batal</button>
                    <button type="submit" name="generate_iuran" class="btn"><i class="fas fa-bolt"></i>Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
