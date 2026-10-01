<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'pengasuh'));
$title = 'Nilai';
$menu = 'nilai';

$jenis_nilai = array('tugas' => 'Tugas', 'UH' => 'Ulangan Harian', 'UTS' => 'UTS', 'UAS' => 'UAS');

// Tahun ajaran berjalan (Juli–Juni)
$y = (int) date('Y');
$m = (int) date('n');
$ta_default = $m >= 7 ? $y . '/' . ($y + 1) : ($y - 1) . '/' . $y;

function predikat_nilai($rata) {
    if ($rata >= 90) return 'A';
    if ($rata >= 80) return 'B';
    if ($rata >= 70) return 'C';
    return 'D';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah_nilai'])) {
        $santri_id = (int) ($_POST['santri_id'] ?? 0);
        $mapel = trim($_POST['mapel'] ?? '');
        $jenis = $_POST['jenis'] ?? 'tugas';
        if (!isset($jenis_nilai[$jenis])) $jenis = 'tugas';
        $nilai = (isset($_POST['nilai']) && $_POST['nilai'] !== '') ? (float) $_POST['nilai'] : null;
        $semester = (int) ($_POST['semester'] ?? 1);
        if (!in_array($semester, array(1, 2), true)) $semester = 1;
        $ta = trim($_POST['tahun_ajaran'] ?? '') ?: $ta_default;
        $kelas = (int) ($_POST['kelas'] ?? 0);
        if ($kelas < 1 || $kelas > 6) $kelas = null;
        $tanggal = $_POST['tanggal'] ?: date('Y-m-d');
        $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
        if ($santri_id > 0 && $mapel !== '' && $nilai !== null && $nilai >= 0 && $nilai <= 100) {
            $cek = db_one($koneksi, "SELECT id FROM santri WHERE id = ? AND status = 'aktif'", 'i', array($santri_id));
            if ($cek) {
                db_exec($koneksi,
                    'INSERT INTO nilai (santri_id, mapel, jenis, nilai, semester, tahun_ajaran, kelas, tanggal, keterangan, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?)',
                    'issdisissi', array($santri_id, $mapel, $jenis, $nilai, $semester, $ta, $kelas, $tanggal, $keterangan, $user['id']));
                flash_set('Nilai berhasil dicatat.');
            } else {
                flash_set('Santri tidak valid / tidak aktif.', 'err');
            }
        } else {
            flash_set('Data belum lengkap: santri, mapel, dan nilai 0–100 wajib diisi.', 'err');
        }
    } elseif (isset($_POST['hapus_nilai'])) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            db_exec($koneksi, 'DELETE FROM nilai WHERE id = ?', 'i', array($id));
            flash_set('Data nilai dihapus.');
        }
    }
    redirect('modules/nilai/');
}

// ---- Filter (GET) ----
$f_santri = (int) ($_GET['santri_id'] ?? 0);
$f_semester = (int) ($_GET['semester'] ?? 0);
$f_ta = trim($_GET['tahun_ajaran'] ?? $ta_default);
$f_mapel = trim($_GET['mapel'] ?? '');
$f_kelas = (int) ($_GET['kelas'] ?? 0);
if ($f_kelas < 1 || $f_kelas > 6) $f_kelas = 0;

$where = array();
$types = '';
$params = array();
if ($f_santri > 0) { $where[] = 'n.santri_id = ?'; $types .= 'i'; $params[] = $f_santri; }
if ($f_semester > 0) { $where[] = 'n.semester = ?'; $types .= 'i'; $params[] = $f_semester; }
if ($f_ta !== '') { $where[] = 'n.tahun_ajaran = ?'; $types .= 's'; $params[] = $f_ta; }
if ($f_mapel !== '') { $where[] = 'n.mapel = ?'; $types .= 's'; $params[] = $f_mapel; }
if ($f_kelas > 0) { $where[] = 'n.kelas = ?'; $types .= 'i'; $params[] = $f_kelas; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$santri = db_all($koneksi, "SELECT id, nis, nama FROM santri WHERE status = 'aktif' ORDER BY nama");
$daftar_mapel = db_all($koneksi, 'SELECT DISTINCT mapel FROM nilai ORDER BY mapel');
$daftar_ta = db_all($koneksi, 'SELECT DISTINCT tahun_ajaran FROM nilai ORDER BY tahun_ajaran DESC');

$riwayat = db_all($koneksi,
    'SELECT n.*, s.nis, s.nama FROM nilai n
     JOIN santri s ON s.id = n.santri_id
     ' . $where_sql . '
     ORDER BY n.tanggal DESC, n.id DESC LIMIT 100',
    $types, $params);

$rekap = db_all($koneksi,
    'SELECT s.nis, s.nama, n.kelas, COUNT(*) AS jml, ROUND(AVG(n.nilai), 1) AS rata
     FROM nilai n JOIN santri s ON s.id = n.santri_id
     ' . $where_sql . '
     GROUP BY n.santri_id, n.kelas ORDER BY rata DESC, s.nama',
    $types, $params);

include __DIR__ . '/../../includes/header.php';
?>

<div class="grid grid-2">
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0">Catat Nilai</h2>
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
                <div class="field"><label>Mata Pelajaran</label>
                    <input type="text" name="mapel" maxlength="60" required list="datalistMapel" placeholder="cth: Matematika">
                    <datalist id="datalistMapel">
                        <?php foreach ($daftar_mapel as $dm): ?>
                        <option value="<?= e($dm['mapel']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="field"><label>Jenis Penilaian</label>
                    <select name="jenis">
                        <?php foreach ($jenis_nilai as $k => $v): ?>
                        <option value="<?= e($k) ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Nilai (0–100)</label>
                    <input type="number" name="nilai" min="0" max="100" step="0.5" required placeholder="cth: 85">
                </div>
                <div class="field"><label>Semester</label>
                    <select name="semester">
                        <option value="1">1 (Ganjil)</option>
                        <option value="2">2 (Genap)</option>
                    </select>
                </div>
                <div class="field"><label>Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" maxlength="9" value="<?= e($ta_default) ?>" placeholder="cth: 2026/2027">
                </div>
                <div class="field"><label>Kelas Madrasah <span style="opacity:.6">(opsional)</span></label>
                    <select name="kelas">
                        <option value="">—</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>">Kelas <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="field"><label>Tanggal</label>
                    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="field"><label>Keterangan <span style="opacity:.6">(opsional)</span></label>
                    <input type="text" name="keterangan" maxlength="255" placeholder="cth: Bab pecahan">
                </div>
            </div>
            <div class="form-actions"><button type="submit" name="tambah_nilai" class="btn">Simpan Nilai</button></div>
        </form>
    </div>
    <div class="form-card">
        <h2 class="section-title" style="margin-top:0">Filter</h2>
        <form method="get" action="">
            <div class="form-grid">
                <div class="field"><label>Santri</label>
                    <select name="santri_id">
                        <option value="0">Semua santri</option>
                        <?php foreach ($santri as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"<?= $f_santri === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Mata Pelajaran</label>
                    <select name="mapel">
                        <option value="">Semua mapel</option>
                        <?php foreach ($daftar_mapel as $dm): ?>
                        <option value="<?= e($dm['mapel']) ?>"<?= $f_mapel === $dm['mapel'] ? ' selected' : '' ?>><?= e($dm['mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>Semester</label>
                    <select name="semester">
                        <option value="0">Semua</option>
                        <option value="1"<?= $f_semester === 1 ? ' selected' : '' ?>>1 (Ganjil)</option>
                        <option value="2"<?= $f_semester === 2 ? ' selected' : '' ?>>2 (Genap)</option>
                    </select>
                </div>
                <div class="field"><label>Tahun Ajaran</label>
                    <select name="tahun_ajaran">
                        <option value="">Semua</option>
                        <?php foreach ($daftar_ta as $dt): ?>
                        <option value="<?= e($dt['tahun_ajaran']) ?>"<?= $f_ta === $dt['tahun_ajaran'] ? ' selected' : '' ?>><?= e($dt['tahun_ajaran']) ?></option>
                        <?php endforeach; ?>
                        <?php if ($f_ta !== '' && !in_array($f_ta, array_column($daftar_ta, 'tahun_ajaran'), true)): ?>
                        <option value="<?= e($f_ta) ?>" selected><?= e($f_ta) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="field"><label>Kelas</label>
                    <select name="kelas">
                        <option value="0">Semua</option>
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>"<?= $f_kelas === $i ? ' selected' : '' ?>>Kelas <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Terapkan</button>
                <a href="<?= url('modules/nilai/') ?>" class="btn btn-ghost">Atur Ulang</a>
            </div>
        </form>
    </div>
</div>

<h2 class="section-title">Rekap Rata-rata<?= $f_ta !== '' ? ' — ' . e($f_ta) : '' ?><?= $f_semester > 0 ? ' · Semester ' . $f_semester : '' ?><?= $f_kelas > 0 ? ' · Kelas ' . $f_kelas : '' ?></h2>
<div class="table-wrap">
<table>
    <thead><tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Jml Penilaian</th><th>Rata-rata</th><th>Predikat</th></tr></thead>
    <tbody>
    <?php if (!$rekap): ?>
        <tr><td colspan="6" class="empty">Belum ada data nilai untuk filter ini.</td></tr>
    <?php else: foreach ($rekap as $r):
        $pred = predikat_nilai((float) $r['rata']);
    ?>
        <tr>
            <td><?= e($r['nis']) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= $r['kelas'] ? 'Kelas ' . (int) $r['kelas'] : '<span style="opacity:.5">—</span>' ?></td>
            <td><?= (int) $r['jml'] ?></td>
            <td><strong><?= e($r['rata']) ?></strong></td>
            <td><span class="badge"><?= e($pred) ?></span></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<h2 class="section-title">Riwayat Nilai</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>Tanggal</th><th>Santri</th><th>Mapel</th><th>Jenis</th><th>Nilai</th><th>Kelas</th><th>Sem./TA</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$riwayat): ?>
        <tr><td colspan="8" class="empty">Belum ada nilai tercatat.</td></tr>
    <?php else: foreach ($riwayat as $r): ?>
        <tr>
            <td><?= e(tgl_indo($r['tanggal'])) ?></td>
            <td><?= e($r['nama']) ?></td>
            <td><?= e($r['mapel']) ?></td>
            <td><?= e($jenis_nilai[$r['jenis']] ?? $r['jenis']) ?></td>
            <td><strong><?= rtrim(rtrim(number_format((float) $r['nilai'], 2), '0'), '.') ?></strong></td>
            <td><?= $r['kelas'] ? 'Kelas ' . (int) $r['kelas'] : '<span style="opacity:.5">—</span>' ?></td>
            <td><?= (int) $r['semester'] ?> / <?= e($r['tahun_ajaran']) ?></td>
            <td>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus nilai <?= e($r['mapel']) ?> (<?= e($r['nama']) ?>)?')">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" name="hapus_nilai" class="btn btn-sm btn-danger">Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
