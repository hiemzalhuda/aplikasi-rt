<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login();
$title = 'Profil Santri';
$menu = 'santri';
$can_edit = in_array($user['role'], array('admin', 'pengasuh'), true);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die('Santri tidak ditemukan.');
}

$upload_dir = __DIR__ . '/../../uploads/santri';

/* ================= POST handlers (admin + pengasuh) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_edit) {
        http_response_code(403);
        die('Akses ditolak untuk role ini.');
    }
    if (isset($_POST['simpan_profil'])) {
        $nama   = trim($_POST['nama'] ?? '');
        $jk     = $_POST['jenis_kelamin'] ?? 'L';
        if (!in_array($jk, array('L', 'P'), true)) $jk = 'L';
        $tpl    = trim($_POST['tempat_lahir'] ?? '') ?: null;
        $lahir  = $_POST['tgl_lahir'] ?? null;
        $alamat = trim($_POST['alamat'] ?? '') ?: null;
        $hp     = trim($_POST['no_hp'] ?? '') ?: null;
        $masuk  = $_POST['tgl_masuk'] ?? null;
        $cur    = db_one($koneksi, 'SELECT foto FROM santri WHERE id = ?', 'i', array($id));
        list($foto, $ferr) = upload_foto_santri($_FILES['foto'] ?? array(), $cur ? $cur['foto'] : null, $upload_dir);
        if ($ferr) {
            flash_set($ferr, 'err');
        } elseif ($nama === '') {
            flash_set('Nama wajib diisi.', 'err');
        } else {
            $ok = db_exec($koneksi,
                'UPDATE santri SET nama = ?, jenis_kelamin = ?, tempat_lahir = ?, tgl_lahir = ?,
                 alamat = ?, no_hp = ?, tgl_masuk = ?, foto = ? WHERE id = ?',
                'ssssssssi', array($nama, $jk, $tpl, $lahir, $alamat, $hp, $masuk, $foto, $id));
            flash_set($ok !== false ? 'Profil santri diperbarui.' : 'Gagal menyimpan profil.', $ok !== false ? 'ok' : 'err');
        }
    } elseif (isset($_POST['tambah_prestasi'])) {
        $judul = trim($_POST['judul'] ?? '');
        $tingkat = trim($_POST['tingkat'] ?? '') ?: null;
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $ket = trim($_POST['keterangan'] ?? '') ?: null;
        if ($judul === '') {
            flash_set('Judul prestasi wajib diisi.', 'err');
        } else {
            db_exec($koneksi,
                'INSERT INTO prestasi (santri_id, judul, tingkat, tanggal, keterangan, created_by)
                 VALUES (?,?,?,?,?,?)',
                'issssi', array($id, $judul, $tingkat, $tanggal, $ket, $user['id']));
            flash_set('Prestasi berhasil ditambahkan.');
        }
    } elseif (isset($_POST['hapus_prestasi'])) {
        $pid = (int) ($_POST['prestasi_id'] ?? 0);
        db_exec($koneksi, 'DELETE FROM prestasi WHERE id = ? AND santri_id = ?', 'ii', array($pid, $id));
        flash_set('Prestasi dihapus.');
    }
    redirect('modules/santri/profil.php?id=' . $id);
}

/* ================= Data profil ================= */
$s = db_one($koneksi,
    'SELECT s.*, k.nama AS kamar, a.nama AS asrama
     FROM santri s
     LEFT JOIN penempatan_santri ps ON ps.santri_id = s.id AND ps.tgl_selesai IS NULL
     LEFT JOIN kamar k ON k.id = ps.kamar_id
     LEFT JOIN asrama a ON a.id = k.asrama_id
     WHERE s.id = ?', 'i', array($id));
if (!$s) {
    http_response_code(404);
    die('Santri tidak ditemukan.');
}

$wali = db_all($koneksi,
    'SELECT nama, hubungan, no_wa FROM wali WHERE santri_id = ? ORDER BY id', 'i', array($id));

$kh = db_all($koneksi,
    'SELECT k.status, COUNT(*) AS c FROM kehadiran k WHERE k.santri_id = ? GROUP BY k.status', 'i', array($id));
$kh_map = array('hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0);
foreach ($kh as $r) { $kh_map[$r['status']] = (int) $r['c']; }
$kh_total = array_sum($kh_map);
$kh_persen = $kh_total > 0 ? round($kh_map['hadir'] / $kh_total * 100) : 0;

$jml_setoran = db_one($koneksi,
    'SELECT COUNT(*) AS c FROM setoran_hafalan WHERE santri_id = ?', 'i', array($id));
$jml_setoran = $jml_setoran ? (int) $jml_setoran['c'] : 0;

$nilai_list = db_all($koneksi,
    'SELECT tanggal, mapel, jenis, nilai, kelas, semester, tahun_ajaran
     FROM nilai WHERE santri_id = ? ORDER BY tanggal DESC, id DESC', 'i', array($id));
$rata_nilai = $nilai_list ? round(array_sum(array_column($nilai_list, 'nilai')) / count($nilai_list), 1) : null;

$prestasi = db_all($koneksi,
    'SELECT * FROM prestasi WHERE santri_id = ? ORDER BY tanggal DESC, id DESC', 'i', array($id));

$jenis_nilai = array('tugas' => 'Tugas', 'UH' => 'UH', 'UTS' => 'UTS', 'UAS' => 'UAS');

/* Inisial untuk avatar bila belum ada foto */
$inisial = '';
foreach (preg_split('/\s+/', trim($s['nama'])) as $i => $kata) {
    if ($i < 2 && $kata !== '') $inisial .= strtoupper(substr($kata, 0, 1));
}
if ($inisial === '') $inisial = '?';
$foto_file = foto_ok($s['foto']) ? $s['foto'] : null;

include __DIR__ . '/../../includes/header.php';
?>

<a href="<?= url('modules/santri/') ?>" class="btn btn-sm btn-ghost" style="margin-bottom:14px">&larr; Kembali ke Data Santri</a>
<?= flash_get() ?>

<!-- ======== Kepala profil ======== -->
<div class="card" style="margin-bottom:18px">
    <div class="profil-head">
        <?php if ($foto_file): ?>
            <img src="<?= e(url('uploads/santri/' . $foto_file)) ?>" alt="Foto <?= e($s['nama']) ?>" class="profil-foto">
        <?php else: ?>
            <div class="profil-avatar"><?= e($inisial) ?></div>
        <?php endif; ?>
        <div>
            <h2><?= e($s['nama']) ?></h2>
            <div class="profil-meta">
                <span class="badge"><?= e($s['nis']) ?></span>
                <span class="badge <?= $s['status'] === 'aktif' ? 'badge-ok' : 'badge-warn' ?>"><?= e($s['status']) ?></span>
                <span class="badge"><?= $s['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></span>
            </div>
            <p class="profil-lama">Menjadi santri selama <strong><?= e(lama_santri($s['tgl_masuk'])) ?></strong></p>
            <?php if ($can_edit): ?>
            <p style="margin:12px 0 0">
                <button type="button" class="btn btn-sm" data-snmodal-open="modalEditSantri">
                    <i class="fas fa-pen" style="margin-right:6px"></i>Edit Data
                </button>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======== Data lengkap + wali ======== -->
<div class="grid grid-2" style="margin-bottom:18px">
    <div class="card">
        <h3>Data Lengkap</h3>
        <div class="profil-data">
            <div class="profil-item"><div class="lbl">NIS</div><div class="val"><?= e($s['nis']) ?></div></div>
            <div class="profil-item"><div class="lbl">Nama Lengkap</div><div class="val"><?= e($s['nama']) ?></div></div>
            <div class="profil-item"><div class="lbl">Tempat, Tanggal Lahir</div><div class="val"><?= e(trim(($s['tempat_lahir'] ? $s['tempat_lahir'] . ', ' : '') . tgl_indo($s['tgl_lahir']))) ?></div></div>
            <div class="profil-item"><div class="lbl">Alamat</div><div class="val"><?= e($s['alamat'] ?: '-') ?></div></div>
            <div class="profil-item"><div class="lbl">No. HP</div><div class="val"><?= e($s['no_hp'] ?: '-') ?></div></div>
            <div class="profil-item"><div class="lbl">Tanggal Mendaftar</div><div class="val"><?= e(tgl_indo($s['tgl_masuk'])) ?></div></div>
            <div class="profil-item"><div class="lbl">Kamar / Asrama</div><div class="val"><?= e($s['kamar'] ? $s['kamar'] . ' / ' . $s['asrama'] : '-') ?></div></div>
            <div class="profil-item"><div class="lbl">Lama Menjadi Santri</div><div class="val"><?= e(lama_santri($s['tgl_masuk'])) ?></div></div>
        </div>
    </div>
    <div class="card">
        <h3>Wali Santri</h3>
        <?php if (!$wali): ?>
            <div class="empty">Belum ada data wali. Tambahkan lewat menu Santri &amp; Wali di halaman utama data santri.</div>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Nama</th><th>Hubungan</th><th>No. WA</th></tr></thead>
            <tbody>
            <?php foreach ($wali as $w): ?>
                <tr>
                    <td><?= e($w['nama']) ?></td>
                    <td><?= e($w['hubungan']) ?></td>
                    <td><?= e($w['no_wa'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- ======== Ringkasan kehadiran & hafalan ======== -->
<div class="grid grid-2" style="margin-bottom:18px">
    <div class="card">
        <h3>Ringkasan Kehadiran</h3>
        <div class="profil-ringkas">
            <div class="rk"><div class="n"><?= $kh_map['hadir'] ?></div><div class="l">Hadir</div></div>
            <div class="rk"><div class="n"><?= $kh_map['izin'] ?></div><div class="l">Izin</div></div>
            <div class="rk"><div class="n"><?= $kh_map['sakit'] ?></div><div class="l">Sakit</div></div>
            <div class="rk"><div class="n"><?= $kh_map['alpa'] ?></div><div class="l">Alpa</div></div>
        </div>
        <p class="profil-lama" style="margin-top:10px">Tingkat kehadiran: <strong><?= $kh_persen ?>%</strong> dari <?= $kh_total ?> catatan</p>
        <p style="margin:8px 0 0"><a class="tbl-link" href="<?= url('modules/kehadiran/') ?>">Lihat modul Kehadiran &rarr;</a></p>
    </div>
    <div class="card">
        <h3>Ringkasan Hafalan</h3>
        <div class="profil-ringkas">
            <div class="rk"><div class="n"><?= $jml_setoran ?></div><div class="l">Total setoran</div></div>
            <div class="rk"><div class="n"><?= count($nilai_list) ?></div><div class="l">Penilaian</div></div>
            <div class="rk"><div class="n"><?= $rata_nilai !== null ? $rata_nilai : '-' ?></div><div class="l">Rata-rata nilai</div></div>
        </div>
        <p style="margin:10px 0 0"><a class="tbl-link" href="<?= url('modules/hafalan/') ?>">Lihat modul Hafalan &rarr;</a></p>
    </div>
</div>

<!-- ======== Riwayat nilai ======== -->
<div class="card" style="margin-bottom:18px">
    <h3>Riwayat Nilai<?= $rata_nilai !== null ? ' <span class="badge badge-ok">Rata-rata: ' . e($rata_nilai) . '</span>' : '' ?></h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Tanggal</th><th>Mapel</th><th>Jenis</th><th>Nilai</th><th>Kelas</th><th>Sem./TA</th></tr></thead>
        <tbody>
        <?php if (!$nilai_list): ?>
            <tr><td colspan="6" class="empty">Belum ada nilai tercatat.</td></tr>
        <?php else: foreach ($nilai_list as $n): ?>
            <tr>
                <td><?= e(tgl_indo($n['tanggal'])) ?></td>
                <td><?= e($n['mapel']) ?></td>
                <td><?= e($jenis_nilai[$n['jenis']] ?? $n['jenis']) ?></td>
                <td><strong><?= rtrim(rtrim(number_format((float) $n['nilai'], 2), '0'), '.') ?></strong></td>
                <td><?= $n['kelas'] ? 'Kelas ' . (int) $n['kelas'] : '<span style="opacity:.5">—</span>' ?></td>
                <td><?= (int) $n['semester'] ?> / <?= e($n['tahun_ajaran']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
    <p style="margin:8px 0 0"><a class="tbl-link" href="<?= url('modules/nilai/') ?>">Lihat modul Nilai &rarr;</a></p>
</div>

<!-- ======== Prestasi ======== -->
<div class="card" style="margin-bottom:18px">
    <h3>Prestasi</h3>
    <?php if (!$prestasi): ?>
        <div class="empty">Belum ada prestasi tercatat.</div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Tanggal</th><th>Prestasi</th><th>Tingkat</th><th>Keterangan</th><?= $can_edit ? '<th>Aksi</th>' : '' ?></tr></thead>
        <tbody>
        <?php foreach ($prestasi as $p): ?>
            <tr>
                <td><?= e(tgl_indo($p['tanggal'])) ?></td>
                <td><strong><?= e($p['judul']) ?></strong></td>
                <td><?= e($p['tingkat'] ?: '-') ?></td>
                <td><?= e($p['keterangan'] ?: '-') ?></td>
                <?php if ($can_edit): ?>
                <td>
                    <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus prestasi ini?')">
                        <input type="hidden" name="prestasi_id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" name="hapus_prestasi" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i>Hapus</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <?php if ($can_edit): ?>
    <h3 style="margin-top:18px">Tambah Prestasi</h3>
    <form method="post" action="">
        <div class="form-grid">
            <div class="field"><label>Judul Prestasi</label><input type="text" name="judul" required maxlength="100" placeholder="mis. Juara 1 MHQ"></div>
            <div class="field"><label>Tingkat</label>
                <input type="text" name="tingkat" maxlength="40" list="tingkat_list" placeholder="mis. Kabupaten">
                <datalist id="tingkat_list">
                    <option value="Pondok"><option value="Kecamatan"><option value="Kabupaten">
                    <option value="Provinsi"><option value="Nasional"><option value="Internasional">
                </datalist>
            </div>
            <div class="field"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
            <div class="field"><label>Keterangan</label><input type="text" name="keterangan" maxlength="255"></div>
        </div>
        <div class="form-actions"><button type="submit" name="tambah_prestasi" class="btn"><i class="fas fa-plus"></i>Tambah</button></div>
    </form>
    <?php endif; ?>
</div>

<!-- ======== Edit data (admin + pengasuh): modal popup ======== -->
<?php if ($can_edit): ?>
<div class="sn-modal" id="modalEditSantri" role="dialog" aria-modal="true" aria-label="Edit Data Santri">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-pen" style="margin-right:8px;color:var(--brand-active)"></i>Edit Data Santri</h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="field"><label>NIS</label><input type="text" value="<?= e($s['nis']) ?>" disabled></div>
                    <div class="field"><label>Nama Lengkap</label><input type="text" name="nama" required maxlength="100" value="<?= e($s['nama']) ?>"></div>
                    <div class="field"><label>Jenis Kelamin</label>
                        <select name="jenis_kelamin">
                            <option value="L"<?= $s['jenis_kelamin'] === 'L' ? ' selected' : '' ?>>Laki-laki</option>
                            <option value="P"<?= $s['jenis_kelamin'] === 'P' ? ' selected' : '' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="field"><label>Tempat Lahir</label><input type="text" name="tempat_lahir" maxlength="60" value="<?= e($s['tempat_lahir']) ?>"></div>
                    <div class="field"><label>Tanggal Lahir</label><input type="date" name="tgl_lahir" value="<?= e($s['tgl_lahir']) ?>"></div>
                    <div class="field"><label>Alamat</label><input type="text" name="alamat" maxlength="255" value="<?= e($s['alamat']) ?>"></div>
                    <div class="field"><label>No. HP</label><input type="text" name="no_hp" maxlength="20" value="<?= e($s['no_hp']) ?>"></div>
                    <div class="field"><label>Tanggal Mendaftar</label><input type="date" name="tgl_masuk" value="<?= e($s['tgl_masuk']) ?>"></div>
                    <div class="field"><label>Foto (jpg/png/webp, maks 2MB)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="simpan_profil" class="btn">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
