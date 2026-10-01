<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login();
$title = 'Hafalan';
$menu = 'hafalan';

$jenis_setoran = array('setoran' => 'Setoran', 'murojaah' => 'Murojaah', 'tasmi' => "Tasmi'");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_setoran'])) {
        $santri_id = (int) ($_POST['santri_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?: date('Y-m-d');
        $jenis = $_POST['jenis'] ?? 'setoran';
        if (!isset($jenis_setoran[$jenis])) $jenis = 'setoran';
        $juz = $_POST['juz'] !== '' ? (int) $_POST['juz'] : null;
        $surah = trim($_POST['surah'] ?? '');
        $dari = trim($_POST['ayat_dari'] ?? '');
        $sampai = trim($_POST['ayat_sampai'] ?? '');
        $nilai = $_POST['nilai'] !== '' ? max(0, min(100, (int) $_POST['nilai'])) : null;
        $penyimak = trim($_POST['penyimak'] ?? '');
        if ($santri_id > 0) {
            db_exec($koneksi,
                'INSERT INTO setoran_hafalan (santri_id, tanggal, jenis, juz, surah, ayat_dari, ayat_sampai, nilai, penyimak, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                'ississsisi', array($santri_id, $tanggal, $jenis, $juz, $surah, $dari, $sampai, $nilai, $penyimak, $user['id']));
            flash_set('Setoran berhasil dicatat.');
        } else {
            flash_set('Pilih santri dulu.', 'err');
        }
    } elseif (isset($_POST['simpan_target'])) {
        $santri_id = (int) ($_POST['santri_id'] ?? 0);
        $target = max(0, (float) ($_POST['target_juz'] ?? 0));
        $periode = trim($_POST['periode'] ?? '') ?: null;
        if ($santri_id > 0) {
            db_exec($koneksi,
                'INSERT INTO target_hafalan (santri_id, target_juz, periode) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE target_juz = VALUES(target_juz)',
                'ids', array($santri_id, $target, $periode));
            flash_set('Target hafalan disimpan.');
        } else {
            flash_set('Pilih santri dulu.', 'err');
        }
    }
    redirect('modules/hafalan/');
}

$santri = db_all($koneksi, "SELECT id, nis, nama FROM santri WHERE status = 'aktif' ORDER BY nama");
$setoran = db_all($koneksi,
    'SELECT sh.*, s.nis, s.nama
     FROM setoran_hafalan sh JOIN santri s ON s.id = sh.santri_id
     ORDER BY sh.tanggal DESC, sh.id DESC LIMIT 50');
$progres = db_all($koneksi,
    "SELECT s.nis, s.nama, COUNT(DISTINCT sh.juz) AS juz_disetor,
        (SELECT th.target_juz FROM target_hafalan th WHERE th.santri_id = s.id ORDER BY th.id DESC LIMIT 1) AS target
     FROM santri s LEFT JOIN setoran_hafalan sh ON sh.santri_id = s.id AND sh.jenis = 'setoran' AND sh.juz IS NOT NULL
     WHERE s.status = 'aktif' GROUP BY s.id ORDER BY s.nama LIMIT 50");

include __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-2">
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0">Catat Setoran</h2>
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
                <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
                <div class="field"><label>Jenis</label>
                    <select name="jenis">
                        <?php foreach ($jenis_setoran as $k => $v): ?>
                        <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Juz</label><input type="number" name="juz" min="1" max="30" placeholder="cth: 1"></div>
                <div class="field"><label>Surah</label><input type="text" name="surah" maxlength="60" placeholder="cth: Al-Baqarah"></div>
                <div class="field"><label>Ayat Dari</label><input type="text" name="ayat_dari" maxlength="10"></div>
                <div class="field"><label>Ayat Sampai</label><input type="text" name="ayat_sampai" maxlength="10"></div>
                <div class="field"><label>Nilai (0-100)</label><input type="number" name="nilai" min="0" max="100"></div>
                <div class="field"><label>Penyimak</label><input type="text" name="penyimak" maxlength="100"></div>
            </div>
            <div class="form-actions"><button type="submit" name="tambah_setoran" class="btn">Simpan</button></div>
        </form>
    </div>
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0">Target Hafalan Santri</h2>
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
                <div class="field"><label>Target (juz)</label><input type="number" name="target_juz" step="0.5" min="0" max="30" value="1"></div>
                <div class="field"><label>Periode</label><input type="text" name="periode" maxlength="20" placeholder="cth: 2026/2027"></div>
            </div>
            <div class="form-actions"><button type="submit" name="simpan_target" class="btn">Simpan Target</button></div>
        </form>
    </div>
</div>

<h2 class="section-title">Setoran Terakhir</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Tanggal</th><th>Santri</th><th>Jenis</th><th>Materi</th><th>Nilai</th><th>Penyimak</th></tr></thead>
    <tbody>
    <?php if (!$setoran): ?>
        <tr><td colspan="6" class="empty">Belum ada setoran tercatat.</td></tr>
    <?php else: foreach ($setoran as $r):
        $materi = array();
        if ($r['juz']) $materi[] = 'Juz ' . (int) $r['juz'];
        if ($r['surah']) $materi[] = $r['surah'];
        if ($r['ayat_dari']) $materi[] = 'ayat ' . $r['ayat_dari'] . ($r['ayat_sampai'] ? '-' . $r['ayat_sampai'] : '');
    ?>
        <tr>
            <td><?= e(tgl_indo($r['tanggal'])) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= e($jenis_setoran[$r['jenis']] ?? $r['jenis']) ?></td>
            <td><?= e($materi ? implode(', ', $materi) : '-') ?></td>
            <td><?= $r['nilai'] !== null ? (int) $r['nilai'] : '-' ?></td>
            <td><?= e($r['penyimak'] ?: '-') ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<h2 class="section-title">Progres Hafalan</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>Juz Disetor</th><th>Target</th></tr></thead>
    <tbody>
    <?php if (!$progres): ?>
        <tr><td colspan="4" class="empty">Belum ada data.</td></tr>
    <?php else: foreach ($progres as $p): ?>
        <tr>
            <td><?= e($p['nis']) ?></td>
            <td><?= e($p['nama']) ?></td>
            <td><?= (int) $p['juz_disetor'] ?> juz</td>
            <td><?= $p['target'] !== null ? rtrim(rtrim(number_format((float) $p['target'], 1), '0'), '.') . ' juz' : '-' ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
