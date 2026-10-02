<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'ketua', 'sekretaris'));
$title = 'Data Warga';
$menu = 'warga';

$tab = (isset($_GET['tab']) && $_GET['tab'] === 'kk') ? 'kk' : 'data';
$q = trim($_GET['q'] ?? '');
$back = 'modules/warga/' . ($tab === 'kk' ? '?tab=kk' : '');

/** Daftar opsi select. */
$opt_agama = array('Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu');
$opt_pendidikan = array('Tidak Sekolah', 'SD', 'SMP', 'SMA', 'Diploma', 'Sarjana');
$opt_kawin = array('Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati');
$opt_hubungan = array('KEPALA KELUARGA', 'SUAMI', 'ISTRI', 'ANAK', 'ORANG TUA', 'FAMILI LAIN', 'LAINNYA');
$opt_tinggal = array('TETAP', 'KONTRAK', 'KOS');

/** Ambil + validasi field form warga. */
function warga_input(&$err) {
    $nik   = trim($_POST['nik'] ?? '');
    $no_kk = trim($_POST['no_kk'] ?? '');
    $nama  = trim($_POST['nama'] ?? '');
    if (!preg_match('/^\d{16}$/', $nik)) $err = 'NIK harus 16 digit angka.';
    elseif ($no_kk === '' || $nama === '') $err = 'Nama dan No. KK wajib diisi.';
    $jk = $_POST['jk'] ?? 'L';
    if (!in_array($jk, array('L', 'P'), true)) $jk = 'L';
    $hub = $_POST['hubungan'] ?? 'KEPALA KELUARGA';
    global $opt_hubungan;
    if (!in_array($hub, $opt_hubungan, true)) $hub = 'KEPALA KELUARGA';
    $tgl = $_POST['status_tinggal'] ?? 'TETAP';
    global $opt_tinggal;
    if (!in_array($tgl, $opt_tinggal, true)) $tgl = 'TETAP';
    $agm = trim($_POST['agama'] ?? '') ?: null;
    $did = trim($_POST['pendidikan'] ?? '') ?: null;
    $kw = trim($_POST['status_kawin'] ?? '') ?: null;
    return array(
        'nik' => $nik, 'no_kk' => $no_kk, 'nama' => $nama,
        'tempat_lahir' => trim($_POST['tempat_lahir'] ?? '') ?: null,
        'tgl_lahir' => ($_POST['tgl_lahir'] ?? '') !== '' ? $_POST['tgl_lahir'] : null,
        'jk' => $jk, 'agama' => $agm, 'pendidikan' => $did,
        'pekerjaan' => trim($_POST['pekerjaan'] ?? '') ?: null,
        'status_kawin' => $kw, 'hubungan' => $hub,
        'alamat' => trim($_POST['alamat'] ?? '') ?: null,
        'no_hp' => trim($_POST['no_hp'] ?? '') ?: null,
        'status_tinggal' => $tgl,
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* ---------- Hapus warga ---------- */
    if (isset($_POST['hapus'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $ada = db_one($koneksi, 'SELECT id FROM warga WHERE id = ?', 'i', array($id));
        if (!$ada) {
            flash_set('Data warga tidak ditemukan.', 'err');
        } else {
            $ok = db_exec($koneksi, 'DELETE FROM warga WHERE id = ?', 'i', array($id));
            flash_set($ok ? 'Data warga dihapus.' : 'Gagal menghapus data warga.', $ok ? 'ok' : 'err');
        }
        redirect($back);
    }
    /* ---------- Tambah / edit warga ---------- */
    if (isset($_POST['simpan'])) {
        $err = '';
        $d = warga_input($err);
        $edit_id = (int) ($_POST['edit_id'] ?? 0);
        if ($err === '') {
            $cek = db_one($koneksi,
                'SELECT id FROM warga WHERE nik = ?' . ($edit_id > 0 ? ' AND id <> ?' : ''),
                $edit_id > 0 ? 'si' : 's',
                $edit_id > 0 ? array($d['nik'], $edit_id) : array($d['nik']));
            if ($cek) $err = 'NIK sudah terdaftar untuk warga lain.';
        }
        if ($err === '' && $edit_id > 0) {
            $ada = db_one($koneksi, 'SELECT id FROM warga WHERE id = ?', 'i', array($edit_id));
            if (!$ada) $err = 'Data warga tidak ditemukan.';
        }
        if ($err !== '') {
            flash_set($err, 'err');
        } else {
            $types = 'ssssssssssssss' . ($edit_id > 0 ? 'i' : '');
            $params = array(
                $d['nik'], $d['no_kk'], $d['nama'], $d['tempat_lahir'], $d['tgl_lahir'],
                $d['jk'], $d['agama'], $d['pendidikan'], $d['pekerjaan'], $d['status_kawin'],
                $d['hubungan'], $d['alamat'], $d['no_hp'], $d['status_tinggal'],
            );
            if ($edit_id > 0) {
                $params[] = $edit_id;
                $ok = db_exec($koneksi,
                    'UPDATE warga SET nik=?, no_kk=?, nama=?, tempat_lahir=?, tgl_lahir=?, jk=?,
                     agama=?, pendidikan=?, pekerjaan=?, status_kawin=?, hubungan=?, alamat=?,
                     no_hp=?, status_tinggal=? WHERE id=?',
                    $types, $params);
                flash_set($ok !== false ? 'Data warga diperbarui.' : 'Gagal memperbarui data warga.', $ok !== false ? 'ok' : 'err');
            } else {
                $ok = db_exec($koneksi,
                    'INSERT INTO warga (nik, no_kk, nama, tempat_lahir, tgl_lahir, jk, agama,
                     pendidikan, pekerjaan, status_kawin, hubungan, alamat, no_hp, status_tinggal)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    $types, $params);
                flash_set($ok ? 'Warga baru berhasil ditambahkan.' : 'Gagal menambah warga (mungkin NIK sudah dipakai).', $ok ? 'ok' : 'err');
            }
        }
        redirect($back);
    }
}

/* ---------- Ambil data warga (dengan pencarian ?q=) ---------- */
$where = '';
$params = array();
$types = '';
if ($q !== '') {
    $like = '%' . $q . '%';
    $where = 'WHERE nama LIKE ? OR nik LIKE ? OR no_kk LIKE ? OR alamat LIKE ?';
    $params = array($like, $like, $like, $like);
    $types = 'ssss';
}
$warga = db_all($koneksi,
    'SELECT * FROM warga ' . $where . ' ORDER BY nama ASC', $types, $params);

/* ---------- Daftar Kartu Keluarga ---------- */
$kk_list = array();
if ($tab === 'kk') {
    $kk_list = db_all($koneksi,
        'SELECT no_kk, COUNT(*) AS anggota FROM warga GROUP BY no_kk ORDER BY no_kk ASC');
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <a href="<?= url('modules/warga/') ?>" class="btn<?= $tab === 'data' ? '' : ' btn-ghost' ?>">
        <i class="fas fa-users"></i>Data Warga</a>
    <a href="<?= url('modules/warga/?tab=kk') ?>" class="btn<?= $tab === 'kk' ? '' : ' btn-ghost' ?>">
        <i class="fas fa-id-card"></i>Kartu Keluarga</a>
</div>

<?php if ($tab === 'data'): ?>
<h2 style="margin:0 0 14px"><i class="fas fa-users sec-ico"></i> Data Warga</h2>
<div class="sn-pagebar">
    <div class="sn-search" id="snPageSearch">
        <form method="get" action="" role="search" autocomplete="off">
            <span class="sn-search-icon"><i class="fas fa-search"></i></span>
            <input type="text" name="q" id="snPageSearchInput" class="sn-search-input"
                   placeholder="Cari nama, NIK, atau alamat..." value="<?= e($q) ?>" autocomplete="off">
        </form>
    </div>
    <button type="button" class="btn" onclick="bukaTambahWarga()">
        <i class="fas fa-user-plus"></i>Tambah Warga
    </button>
</div>

<?php if ($q !== ''): ?>
<p style="margin:-6px 0 14px;color:var(--text-soft);font-size:13.5px">
    Hasil pencarian untuk <strong>&ldquo;<?= e($q) ?>&rdquo;</strong> (<?= count($warga) ?> warga)
    <a href="<?= url('modules/warga/') ?>" class="tbl-link" style="margin-left:8px">Atur ulang</a>
</p>
<?php endif; ?>

<div class="table-wrap">
<table>
    <thead><tr>
        <th>NIK</th><th>Nama</th><th>L/P</th><th>No. KK</th><th>Hubungan Keluarga</th>
        <th>Alamat</th><th>No. HP</th><th>Aksi</th>
    </tr></thead>
    <tbody id="snWargaBody">
    <?php if (!$warga): ?>
        <tr><td colspan="8" class="empty">Belum ada data warga.</td></tr>
    <?php else: foreach ($warga as $r): ?>
        <tr data-search="<?= e(strtolower($r['nik'] . ' ' . $r['nama'] . ' ' . $r['no_kk'] . ' ' . ($r['alamat'] ?? ''))) ?>">
            <td><code><?= e($r['nik']) ?></code></td>
            <td><strong><a href="<?= url('modules/warga/profil.php?id=' . (int) $r['id']) ?>" class="w-link"><?= e($r['nama']) ?></a></strong></td>
            <td><?= e($r['jk']) ?></td>
            <td><code><?= e($r['no_kk']) ?></code></td>
            <td><?= e(ucwords(strtolower($r['hubungan']))) ?></td>
            <td><?= e($r['alamat'] ?: '-') ?></td>
            <td><?= e($r['no_hp'] ?: '-') ?></td>
            <td style="white-space:nowrap">
                <button type="button" class="btn btn-sm"
                        data-id="<?= (int) $r['id'] ?>"
                        data-nik="<?= e($r['nik']) ?>"
                        data-no_kk="<?= e($r['no_kk']) ?>"
                        data-nama="<?= e($r['nama']) ?>"
                        data-tempat_lahir="<?= e($r['tempat_lahir'] ?? '') ?>"
                        data-tgl_lahir="<?= e($r['tgl_lahir'] ?? '') ?>"
                        data-jk="<?= e($r['jk']) ?>"
                        data-agama="<?= e($r['agama'] ?? '') ?>"
                        data-pendidikan="<?= e($r['pendidikan'] ?? '') ?>"
                        data-pekerjaan="<?= e($r['pekerjaan'] ?? '') ?>"
                        data-status_kawin="<?= e($r['status_kawin'] ?? '') ?>"
                        data-hubungan="<?= e($r['hubungan']) ?>"
                        data-alamat="<?= e($r['alamat'] ?? '') ?>"
                        data-no_hp="<?= e($r['no_hp'] ?? '') ?>"
                        data-status_tinggal="<?= e($r['status_tinggal']) ?>"
                        onclick="bukaEditWarga(this)">
                    <i class="fas fa-pen"></i>Edit
                </button>
                <form method="post" action="" style="display:inline;margin-left:6px"
                      onsubmit="return confirm('Hapus data <?= e(addslashes($r['nama'])) ?>?')">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" name="hapus" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i>Hapus
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<!-- ======== Modal tambah / edit warga (satu modal dipakai ulang) ======== -->
<div class="sn-modal" id="modalWarga" role="dialog" aria-modal="true" aria-label="Form Warga">
    <div class="sn-modal-box">
        <div class="sn-modal-head">
            <h3><i class="fas fa-user-pen" style="margin-right:8px;color:var(--brand-active)"></i><span id="modalWargaTitleText">Tambah Warga</span></h3>
            <button type="button" class="sn-modal-close" data-snmodal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        </div>
        <div class="sn-modal-body">
            <form method="post" action="">
                <input type="hidden" name="edit_id" id="f_edit_id" value="">
                <div class="form-grid">
                    <div class="field"><label>NIK (16 digit)</label>
                        <input type="text" name="nik" id="f_nik" required maxlength="16" pattern="\d{16}" inputmode="numeric"></div>
                    <div class="field"><label>No. Kartu Keluarga</label>
                        <input type="text" name="no_kk" id="f_no_kk" required maxlength="16"></div>
                    <div class="field"><label>Nama Lengkap</label>
                        <input type="text" name="nama" id="f_nama" required maxlength="100"></div>
                    <div class="field"><label>Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" id="f_tempat_lahir" maxlength="60"></div>
                    <div class="field"><label>Tanggal Lahir</label>
                        <input type="date" name="tgl_lahir" id="f_tgl_lahir"></div>
                    <div class="field"><label>Jenis Kelamin</label>
                        <select name="jk" id="f_jk">
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select></div>
                    <div class="field"><label>Agama</label>
                        <select name="agama" id="f_agama">
                            <?php foreach ($opt_agama as $o): ?><option value="<?= e($o) ?>"><?= e($o) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label>Pendidikan</label>
                        <select name="pendidikan" id="f_pendidikan">
                            <?php foreach ($opt_pendidikan as $o): ?><option value="<?= e($o) ?>"><?= e($o) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label>Pekerjaan</label>
                        <input type="text" name="pekerjaan" id="f_pekerjaan" maxlength="50"></div>
                    <div class="field"><label>Status Kawin</label>
                        <select name="status_kawin" id="f_status_kawin">
                            <?php foreach ($opt_kawin as $o): ?><option value="<?= e($o) ?>"><?= e($o) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label>Hubungan Keluarga</label>
                        <select name="hubungan" id="f_hubungan">
                            <?php foreach ($opt_hubungan as $o): ?><option value="<?= e($o) ?>"><?= e(ucwords(strtolower($o))) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="field"><label>Alamat</label>
                        <input type="text" name="alamat" id="f_alamat" maxlength="150"></div>
                    <div class="field"><label>No. HP</label>
                        <input type="text" name="no_hp" id="f_no_hp" maxlength="20"></div>
                    <div class="field"><label>Status Tinggal</label>
                        <select name="status_tinggal" id="f_status_tinggal">
                            <?php foreach ($opt_tinggal as $o): ?><option value="<?= e($o) ?>"><?= e(ucwords(strtolower($o))) ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-actions" style="margin-top:18px">
                    <button type="button" class="btn btn-ghost" data-snmodal-close><i class="fas fa-xmark"></i>Batal</button>
                    <button type="submit" name="simpan" class="btn"><i class="fas fa-check"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* Satu modal dipakai ulang untuk tambah & edit warga. */
(function () {
    var FIELDS = ['nik','no_kk','nama','tempat_lahir','tgl_lahir','jk','agama',
        'pendidikan','pekerjaan','status_kawin','hubungan','alamat','no_hp','status_tinggal'];
    window.bukaTambahWarga = function () {
        document.getElementById('modalWargaTitleText').textContent = 'Tambah Warga';
        document.getElementById('f_edit_id').value = '';
        FIELDS.forEach(function (k) {
            var el = document.getElementById('f_' + k);
            if (el) el.value = (k === 'jk') ? 'L' : (k === 'hubungan' ? 'KEPALA KELUARGA' : (k === 'status_tinggal' ? 'TETAP' : ''));
        });
        snOpenModal(document.getElementById('modalWarga'));
    };
    window.bukaEditWarga = function (btn) {
        document.getElementById('modalWargaTitleText').textContent = 'Edit Data Warga';
        var d = btn.dataset;
        document.getElementById('f_edit_id').value = d.id || '';
        FIELDS.forEach(function (k) {
            var el = document.getElementById('f_' + k);
            if (el) el.value = (k === 'edit_id') ? '' : (d[k] || '');
        });
        snOpenModal(document.getElementById('modalWarga'));
    };
})();
</script>

<?php else: /* ============ Tab Kartu Keluarga ============ */ ?>
<h2 style="margin:0 0 14px"><i class="fas fa-id-card sec-ico"></i> Kartu Keluarga</h2>
<div class="table-wrap">
<table>
    <thead><tr>
        <th>No. KK</th><th>Kepala Keluarga</th><th>Jumlah Anggota</th><th>Aksi</th>
    </tr></thead>
    <tbody>
    <?php if (!$kk_list): ?>
        <tr><td colspan="4" class="empty">Belum ada data kartu keluarga.</td></tr>
    <?php else: foreach ($kk_list as $kk): ?>
        <tr>
            <td><code><?= e($kk['no_kk']) ?></code></td>
            <td><strong><?= e(kk_kepala($koneksi, $kk['no_kk'])) ?></strong></td>
            <td><?= (int) $kk['anggota'] ?> jiwa</td>
            <td>
                <a href="<?= url('modules/warga/?q=' . $kk['no_kk']) ?>" class="btn btn-sm">
                    <i class="fas fa-eye"></i>Lihat
                </a>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
