<?php
// Profil per warga — diklik dari kolom nama di Data Warga.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(array('admin', 'ketua', 'sekretaris'));

$id = (int) ($_GET['id'] ?? 0);
$w = $id > 0 ? db_one($koneksi, 'SELECT * FROM warga WHERE id = ?', 'i', array($id)) : null;
if (!$w) {
    flash_set('Data warga tidak ditemukan.', 'err');
    redirect('modules/warga/');
}

$title = 'Profil Warga';
$menu = 'warga';

// Umur
$umur = null;
if (!empty($w['tgl_lahir']) && $w['tgl_lahir'] !== '0000-00-00') {
    try {
        $lahir = new DateTime($w['tgl_lahir']);
        $umur = (new DateTime('today'))->diff($lahir)->y;
    } catch (Exception $e) { $umur = null; }
}

// Inisial avatar
$inisial = '';
foreach (preg_split('/\s+/', trim($w['nama'])) as $i => $kata) {
    if ($i < 2 && $kata !== '') $inisial .= mb_strtoupper(mb_substr($kata, 0, 1, 'UTF-8'), 'UTF-8');
}

// Anggota keluarga serumah (KK sama)
$keluarga = db_all($koneksi,
    "SELECT id, nama, hubungan, jk FROM warga WHERE no_kk = ? AND id <> ? ORDER BY nama ASC",
    'si', array($w['no_kk'], (int) $w['id']));

// Akun yang tertaut ke warga ini
$akun = db_one($koneksi,
    'SELECT username, nama_lengkap, role, aktif FROM users WHERE warga_id = ? LIMIT 1',
    'i', array((int) $w['id']));

// Iuran KK ini (6 periode terakhir)
$iuran_kk = db_all($koneksi,
    'SELECT periode, jumlah, status, tgl_bayar FROM iuran WHERE no_kk = ? ORDER BY periode DESC LIMIT 6',
    's', array($w['no_kk']));

$hub_label = ucwords(strtolower($w['hubungan']));

include __DIR__ . '/../../includes/header.php';
?>

<a href="<?= url('modules/warga/') ?>" class="btn btn-ghost" style="margin-bottom:16px"><i class="fa-solid fa-arrow-left"></i> Kembali ke Data Warga</a>

<div class="card pf-head">
    <div class="pf-avatar"><?= e($inisial) ?></div>
    <div>
        <h2 class="pf-nama"><?= e($w['nama']) ?></h2>
        <div class="pf-sub"><i class="fa-solid fa-id-card"></i> <?= e($w['nik'] ?: '-') ?></div>
        <div class="pf-badges">
            <span class="badge badge-ok"><?= e($hub_label) ?></span>
            <span class="badge"><?= e($w['jk'] === 'P' ? 'Perempuan' : 'Laki-laki') ?></span>
            <span class="badge"><?= e(ucwords(strtolower($w['status_tinggal']))) ?></span>
        </div>
    </div>
</div>

<div class="pf-grid">
    <div class="card">
        <h3><i class="fa-solid fa-address-card card-ico"></i>Biodata Lengkap</h3>
        <dl class="pf-dl">
            <div class="pf-row"><dt>NIK</dt><dd><code><?= e($w['nik'] ?: '-') ?></code></dd></div>
            <div class="pf-row"><dt>Nomor KK</dt><dd><code><?= e($w['no_kk']) ?></code></dd></div>
            <div class="pf-row"><dt>Nama Lengkap</dt><dd><?= e($w['nama']) ?></dd></div>
            <?php
            $ttl = ($w['tempat_lahir'] ?: '-');
            if (!empty($w['tgl_lahir']) && $w['tgl_lahir'] !== '0000-00-00') $ttl .= ', ' . tgl_indo($w['tgl_lahir']);
            if ($umur !== null) $ttl .= ' (' . $umur . ' th)';
            ?>
            <div class="pf-row"><dt>Tempat, Tanggal Lahir</dt><dd><?= e($ttl) ?></dd></div>
            <div class="pf-row"><dt>Jenis Kelamin</dt><dd><?= e($w['jk'] === 'P' ? 'Perempuan' : 'Laki-laki') ?></dd></div>
            <div class="pf-row"><dt>Agama</dt><dd><?= e($w['agama'] ?: '-') ?></dd></div>
            <div class="pf-row"><dt>Pendidikan</dt><dd><?= e($w['pendidikan'] ?: '-') ?></dd></div>
            <div class="pf-row"><dt>Pekerjaan</dt><dd><?= e($w['pekerjaan'] ?: '-') ?></dd></div>
            <div class="pf-row"><dt>Status Kawin</dt><dd><?= e($w['status_kawin'] ?: '-') ?></dd></div>
            <div class="pf-row"><dt>Hubungan Keluarga</dt><dd><?= e($hub_label) ?></dd></div>
            <div class="pf-row"><dt>Alamat</dt><dd><?= e($w['alamat'] ?: '-') ?></dd></div>
            <div class="pf-row"><dt>No. HP</dt><dd><?= e($w['no_hp'] ?: '-') ?></dd></div>
            <div class="pf-row"><dt>Status Tinggal</dt><dd><?= e(ucwords(strtolower($w['status_tinggal']))) ?></dd></div>
            <?php if (!empty($w['keterangan'])): ?>
            <div class="pf-row"><dt>Keterangan</dt><dd><?= e($w['keterangan']) ?></dd></div>
            <?php endif; ?>
        </dl>
    </div>

    <div>
        <div class="card" style="margin-bottom:18px">
            <h3><i class="fa-solid fa-people-roof card-ico"></i>Keluarga Serumah</h3>
            <?php if ($keluarga): ?>
            <div class="todo-list">
                <?php foreach ($keluarga as $k): ?>
                <a class="todo-item pf-fam" href="<?= url('modules/warga/profil.php?id=' . (int) $k['id']) ?>">
                    <span class="td-dot" style="background:<?= $k['jk'] === 'P' ? '#C86DD7' : '#4F46E5' ?>"></span>
                    <div>
                        <div class="td-title"><?= e($k['nama']) ?></div>
                        <div class="td-sub"><?= e(ucwords(strtolower($k['hubungan']))) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-right pg-arrow"></i>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-empty">Tidak ada anggota lain di KK ini.</div>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-bottom:18px">
            <h3><i class="fa-solid fa-user-check card-ico"></i>Akun Tertaut</h3>
            <?php if ($akun): ?>
            <div class="pf-row"><dt>Username</dt><dd><code><?= e($akun['username']) ?></code></dd></div>
            <div class="pf-row"><dt>Nama Akun</dt><dd><?= e($akun['nama_lengkap']) ?></dd></div>
            <div class="pf-row"><dt>Role</dt><dd><?= e(ucfirst($akun['role'])) ?></dd></div>
            <div class="pf-row"><dt>Status</dt><dd><span class="badge <?= (int) $akun['aktif'] === 1 ? 'badge-ok' : 'badge-warn' ?>"><?= (int) $akun['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></dd></div>
            <?php else: ?>
            <div class="dash-empty">Belum punya akun. Tautkan dari menu Pengguna.</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-hand-holding-dollar card-ico"></i>Iuran KK</h3>
            <?php if ($iuran_kk): ?>
            <div class="todo-list">
                <?php foreach ($iuran_kk as $iu): ?>
                <div class="todo-item">
                    <span class="td-dot" style="background:<?= $iu['status'] === 'lunas' ? '#1C9A52' : '#E5484D' ?>"></span>
                    <div>
                        <div class="td-title"><?= e($iu['periode']) ?> &middot; <?= e(rupiah($iu['jumlah'])) ?></div>
                        <div class="td-sub"><?= $iu['status'] === 'lunas' ? 'Lunas' . ($iu['tgl_bayar'] ? ' &middot; ' . e(tgl_indo($iu['tgl_bayar'])) : '') : 'Belum bayar' ?></div>
                    </div>
                    <span class="badge <?= $iu['status'] === 'lunas' ? 'badge-ok' : 'badge-err' ?>" style="margin-left:auto"><?= e(ucfirst($iu['status'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="dash-empty">Belum ada data iuran untuk KK ini.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
