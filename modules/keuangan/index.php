<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'keuangan'));
$title = 'Keuangan';
$menu = 'keuangan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_tagihan'])) {
        $santri_id = (int) ($_POST['santri_id'] ?? 0);
        $bulan = trim($_POST['bulan'] ?? '');
        $nominal = max(0, (int) ($_POST['nominal'] ?? 0));
        if ($santri_id > 0 && preg_match('/^\d{4}-\d{2}$/', $bulan) && $nominal > 0) {
            $ok = db_exec($koneksi, 'INSERT INTO tagihan_spp (santri_id, bulan, nominal) VALUES (?,?,?)', 'isi', array($santri_id, $bulan, $nominal));
            flash_set($ok ? 'Tagihan ditambahkan.' : 'Gagal (tagihan bulan itu mungkin sudah ada).', $ok ? 'ok' : 'err');
        } else {
            flash_set('Data tagihan belum lengkap/valid.', 'err');
        }
    } elseif (isset($_POST['generate_tagihan'])) {
        $bulan = trim($_POST['bulan'] ?? '');
        $nominal = max(0, (int) ($_POST['nominal'] ?? 0));
        if (preg_match('/^\d{4}-\d{2}$/', $bulan) && $nominal > 0) {
            $n = db_exec($koneksi,
                'INSERT IGNORE INTO tagihan_spp (santri_id, bulan, nominal)
                 SELECT id, ?, ? FROM santri WHERE status = \'aktif\'',
                'si', array($bulan, $nominal));
            flash_set('Tagihan massal dibuat untuk ' . (int) $n . ' santri aktif.');
        } else {
            flash_set('Bulan/nominal belum valid.', 'err');
        }
    } elseif (isset($_POST['bayar'])) {
        $tagihan_id = (int) ($_POST['tagihan_id'] ?? 0);
        $jumlah = max(0, (int) ($_POST['jumlah'] ?? 0));
        $t = db_one($koneksi, 'SELECT * FROM tagihan_spp WHERE id = ?', 'i', array($tagihan_id));
        if ($t && $jumlah > 0) {
            $terbayar = db_one($koneksi, 'SELECT COALESCE(SUM(jumlah),0) AS s FROM pembayaran_spp WHERE tagihan_id = ?', 'i', array($tagihan_id));
            $terbayar = (int) $terbayar['s'];
            $sisa = (int) $t['nominal'] - $terbayar;
            $jumlah = min($jumlah, max(0, $sisa));
            if ($jumlah > 0) {
                db_exec($koneksi,
                    'INSERT INTO pembayaran_spp (tagihan_id, tanggal, jumlah, metode, diterima_oleh) VALUES (?,CURDATE(),?,?,?)',
                    'issi', array($tagihan_id, $jumlah, trim($_POST['metode'] ?? 'tunai'), $user['id']));
                $baru = $terbayar + $jumlah;
                $status = $baru >= (int) $t['nominal'] ? 'lunas' : 'sebagian';
                db_exec($koneksi, 'UPDATE tagihan_spp SET status = ? WHERE id = ?', 'si', array($status, $tagihan_id));
                flash_set('Pembayaran ' . rupiah($jumlah) . ' dicatat.');
            } else {
                flash_set('Tagihan sudah lunas.', 'err');
            }
        } else {
            flash_set('Data pembayaran tidak valid.', 'err');
        }
    } elseif (isset($_POST['mutasi_tabungan'])) {
        $santri_id = (int) ($_POST['santri_id'] ?? 0);
        $jenis = $_POST['jenis'] ?? 'masuk';
        if (!in_array($jenis, array('masuk', 'keluar'), true)) $jenis = 'masuk';
        $jumlah = max(0, (int) ($_POST['jumlah'] ?? 0));
        if ($santri_id > 0 && $jumlah > 0) {
            db_exec($koneksi,
                'INSERT INTO tabungan (santri_id, jenis, jumlah, keterangan, dicatat_oleh) VALUES (?,?,?,?,?)',
                'isisi', array($santri_id, $jenis, $jumlah, trim($_POST['keterangan'] ?? ''), $user['id']));
            flash_set('Mutasi tabungan dicatat.');
        } else {
            flash_set('Data mutasi belum lengkap.', 'err');
        }
    }
    redirect('modules/keuangan/');
}

$santri = db_all($koneksi, "SELECT id, nis, nama FROM santri WHERE status = 'aktif' ORDER BY nama");
$tagihan = db_all($koneksi,
    'SELECT t.*, s.nis, s.nama,
        COALESCE((SELECT SUM(jumlah) FROM pembayaran_spp p WHERE p.tagihan_id = t.id),0) AS terbayar
     FROM tagihan_spp t JOIN santri s ON s.id = t.santri_id
     ORDER BY t.bulan DESC, s.nama ASC LIMIT 100');
$saldo = db_all($koneksi,
    "SELECT s.id AS sid, s.nis, s.nama,
        COALESCE(SUM(CASE WHEN tb.jenis='masuk' THEN tb.jumlah ELSE -tb.jumlah END),0) AS saldo
     FROM santri s LEFT JOIN tabungan tb ON tb.santri_id = s.id
     WHERE s.status = 'aktif' GROUP BY s.id ORDER BY s.nama LIMIT 100");
$mutasi = db_all($koneksi,
    'SELECT tb.*, s.nama FROM tabungan tb JOIN santri s ON s.id = tb.santri_id
     ORDER BY tb.tanggal DESC LIMIT 30');

include __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-2">
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0"><i class="fas fa-file-invoice sec-ico"></i>Tagihan SPP Massal</h2>
        <form method="post" action="">
            <div class="form-grid">
                <div class="field"><label>Bulan (YYYY-MM)</label><input type="month" name="bulan" value="<?= date('Y-m') ?>" required></div>
                <div class="field"><label>Nominal (Rp)</label><input type="number" name="nominal" min="1" required></div>
            </div>
            <div class="form-actions"><button type="submit" name="generate_tagihan" class="btn"><i class="fas fa-users"></i>Generate ke Semua Santri Aktif</button></div>
        </form>
    </div>
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0"><i class="fas fa-money-bill-transfer sec-ico"></i>Mutasi Tabungan / Uang Saku</h2>
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
                <div class="field"><label>Jenis</label>
                    <select name="jenis"><option value="masuk">Setor (masuk)</option><option value="keluar">Tarik (keluar)</option></select>
                </div>
                <div class="field"><label>Jumlah (Rp)</label><input type="number" name="jumlah" min="1" required></div>
                <div class="field"><label>Keterangan</label><input type="text" name="keterangan" maxlength="120"></div>
            </div>
            <div class="form-actions"><button type="submit" name="mutasi_tabungan" class="btn"><i class="fas fa-pen"></i>Catat</button></div>
        </form>
    </div>
</div>

<h2 class="section-title"><i class="fas fa-file-invoice-dollar sec-ico"></i>Tagihan SPP</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Bulan</th><th>Santri</th><th>Nominal</th><th>Terbayar</th><th>Sisa</th><th>Status</th><th>Bayar</th></tr></thead>
    <tbody>
    <?php if (!$tagihan): ?>
        <tr><td colspan="7" class="empty">Belum ada tagihan.</td></tr>
    <?php else: foreach ($tagihan as $t):
        $sisa = (int) $t['nominal'] - (int) $t['terbayar'];
    ?>
        <tr>
            <td><?= e($t['bulan']) ?></td>
            <td><?= profil_link($t['santri_id'], $t['nama']) ?></td>
            <td><?= e(rupiah($t['nominal'])) ?></td>
            <td><?= e(rupiah($t['terbayar'])) ?></td>
            <td><?= e(rupiah($sisa)) ?></td>
            <td><span class="badge <?= $t['status'] === 'lunas' ? 'badge-ok' : ($t['status'] === 'sebagian' ? 'badge-warn' : 'badge-err') ?>"><?= e($t['status']) ?></span></td>
            <td>
                <?php if ($sisa > 0): ?>
                <form method="post" action="" style="display:flex;gap:6px">
                    <input type="hidden" name="tagihan_id" value="<?= (int) $t['id'] ?>">
                    <input type="number" name="jumlah" min="1" max="<?= $sisa ?>" value="<?= $sisa ?>" style="width:110px;padding:6px 8px;border:1px solid var(--line);border-radius:8px">
                    <button type="submit" name="bayar" class="btn btn-sm">Bayar</button>
                </form>
                <?php else: ?>-<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<h2 class="section-title"><i class="fas fa-piggy-bank sec-ico"></i>Saldo Tabungan Santri</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>Saldo</th></tr></thead>
    <tbody>
    <?php if (!$saldo): ?>
        <tr><td colspan="3" class="empty">Belum ada data.</td></tr>
    <?php else: foreach ($saldo as $s): ?>
        <tr>
            <td><?= profil_link($s['sid'], $s['nis']) ?></td>
            <td><?= profil_link($s['sid'], $s['nama']) ?></td>
            <td><strong><?= e(rupiah($s['saldo'])) ?></strong></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<h2 class="section-title"><i class="fas fa-clock-rotate-left sec-ico"></i>Mutasi Terakhir</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Waktu</th><th>Santri</th><th>Jenis</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
    <tbody>
    <?php if (!$mutasi): ?>
        <tr><td colspan="5" class="empty">Belum ada mutasi.</td></tr>
    <?php else: foreach ($mutasi as $m): ?>
        <tr>
            <td><?= e($m['tanggal']) ?></td>
            <td><?= profil_link($m['santri_id'], $m['nama']) ?></td>
            <td><span class="badge <?= $m['jenis'] === 'masuk' ? 'badge-ok' : 'badge-warn' ?>"><?= e($m['jenis']) ?></span></td>
            <td><?= e(rupiah($m['jumlah'])) ?></td>
            <td><?= e($m['keterangan'] ?: '-') ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
