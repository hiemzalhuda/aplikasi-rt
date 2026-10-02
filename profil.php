<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/koneksi.php';

$me = require_login();
$title = 'Profil Saya';
$menu = 'profil';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan_profil') {
        $nama = trim($_POST['nama_lengkap'] ?? '');
        if ($nama === '') {
            flash_set('Nama lengkap wajib diisi.', 'err');
        } else {
            $ok = db_exec($koneksi, 'UPDATE users SET nama_lengkap = ? WHERE id = ?',
                'si', array($nama, $me['id']));
            if ($ok !== false) {
                $_SESSION['user']['nama'] = $nama;
                $me['nama'] = $nama;
                flash_set('Profil berhasil diperbarui.', 'ok');
            } else {
                flash_set('Gagal menyimpan profil.', 'err');
            }
        }
        redirect('profil.php');
    }

    if ($aksi === 'ganti_password') {
        $lama   = $_POST['password_lama'] ?? '';
        $baru   = $_POST['password_baru'] ?? '';
        $konf   = $_POST['password_konfirmasi'] ?? '';
        $row = db_one($koneksi, 'SELECT password_hash FROM users WHERE id = ? LIMIT 1',
            'i', array($me['id']));
        if (!$row || !password_verify($lama, $row['password_hash'])) {
            flash_set('Password lama salah.', 'err');
        } elseif (strlen($baru) < 6) {
            flash_set('Password baru minimal 6 karakter.', 'err');
        } elseif ($baru !== $konf) {
            flash_set('Konfirmasi password tidak cocok.', 'err');
        } else {
            $ok = db_exec($koneksi, 'UPDATE users SET password_hash = ? WHERE id = ?',
                'si', array(password_hash($baru, PASSWORD_DEFAULT), $me['id']));
            flash_set($ok !== false ? 'Password berhasil diganti.' : 'Gagal mengganti password.',
                $ok !== false ? 'ok' : 'err');
        }
        redirect('profil.php');
    }
}

$akun = db_one($koneksi,
    'SELECT id, username, nama_lengkap, role, aktif, warga_id, created_at FROM users WHERE id = ? LIMIT 1',
    'i', array($me['id']));

$warga = null;
if ($akun && $akun['warga_id']) {
    $warga = db_one($koneksi, 'SELECT * FROM warga WHERE id = ? LIMIT 1', 'i', array($akun['warga_id']));
}

$role_label = array('admin' => 'Admin', 'ketua' => 'Ketua RT', 'sekretaris' => 'Sekretaris', 'bendahara' => 'Bendahara');
$inisial = strtoupper(mb_substr(trim($akun['nama_lengkap'] ?? '?'), 0, 1, 'UTF-8'));

include __DIR__ . '/includes/header.php';
?>

<style>
.pf-head { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
.pf-avatar {
    width: 84px; height: 84px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; font-weight: 800; color: #fff;
    background: linear-gradient(135deg, #6366F1, #4F46E5);
    box-shadow: 0 8px 20px rgba(79, 70, 229, .3);
}
.pf-head h2 { margin: 0 0 4px; font-size: 22px; letter-spacing: -.01em; }
.pf-meta { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 8px; }
.pf-data { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px 26px; margin-top: 4px; }
.pf-item .lbl { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--text-faint); margin-bottom: 3px; }
.pf-item .val { font-weight: 600; font-size: 14.5px; }
</style>

<div class="card" style="margin-bottom:18px">
    <div class="pf-head">
        <div class="pf-avatar"><?= e($inisial) ?></div>
        <div>
            <h2><?= e($akun['nama_lengkap']) ?></h2>
            <div style="color:var(--text-soft);font-size:13.5px">@<?= e($akun['username']) ?></div>
            <div class="pf-meta">
                <span class="badge <?= $akun['role'] === 'admin' ? 'badge-err' : 'badge-warn' ?>"><?= e($role_label[$akun['role']] ?? $akun['role']) ?></span>
                <span class="badge <?= $akun['aktif'] ? 'badge-ok' : 'badge-err' ?>"><?= $akun['aktif'] ? 'Aktif' : 'Nonaktif' ?></span>
                <?php if ($warga): ?><span class="badge badge-ok"><i class="fas fa-link"></i> Tertaut ke data warga</span><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h3><i class="fa-solid fa-id-card card-ico"></i>Akun</h3>
        <div class="pf-data">
            <div class="pf-item"><div class="lbl">Username</div><div class="val"><?= e($akun['username']) ?></div></div>
            <div class="pf-item"><div class="lbl">Nama Lengkap</div><div class="val"><?= e($akun['nama_lengkap']) ?></div></div>
            <div class="pf-item"><div class="lbl">Role</div><div class="val"><?= e($role_label[$akun['role']] ?? $akun['role']) ?></div></div>
            <div class="pf-item"><div class="lbl">Terdaftar Sejak</div><div class="val"><?= e(tgl_indo(substr($akun['created_at'], 0, 10))) ?></div></div>
        </div>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-house-user card-ico"></i>Data Warga</h3>
        <?php if ($warga): ?>
        <div class="pf-data">
            <div class="pf-item"><div class="lbl">NIK</div><div class="val"><?= e($warga['nik']) ?></div></div>
            <div class="pf-item"><div class="lbl">No. KK</div><div class="val"><?= e($warga['no_kk']) ?></div></div>
            <div class="pf-item"><div class="lbl">Tempat, Tgl Lahir</div><div class="val"><?= e(trim(($warga['tempat_lahir'] ?: '-') . ', ' . ($warga['tgl_lahir'] ? tgl_indo($warga['tgl_lahir']) : '-'))) ?></div></div>
            <div class="pf-item"><div class="lbl">Jenis Kelamin</div><div class="val"><?= $warga['jk'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></div></div>
            <div class="pf-item"><div class="lbl">Hubungan Keluarga</div><div class="val"><?= e(ucwords(strtolower($warga['hubungan']))) ?></div></div>
            <div class="pf-item"><div class="lbl">No. HP</div><div class="val"><?= e($warga['no_hp'] ?: '-') ?></div></div>
            <div class="pf-item" style="grid-column:1/-1"><div class="lbl">Alamat</div><div class="val"><?= e($warga['alamat'] ?: '-') ?></div></div>
        </div>
        <?php else: ?>
        <div class="dash-empty">Akun ini belum ditautkan ke data warga. Hubungi admin untuk menautkan.</div>
        <?php endif; ?>
    </div>
</div>

<h2 class="section-title"><i class="fas fa-user-pen sec-ico"></i>Ubah Profil</h2>
<div class="grid grid-2">
    <div class="form-card" style="margin-bottom:0">
        <form method="post" action="">
            <input type="hidden" name="aksi" value="simpan_profil">
            <div class="form-grid">
                <div class="field"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required maxlength="100" value="<?= e($akun['nama_lengkap']) ?>"></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn"><i class="fas fa-check"></i>Simpan</button></div>
        </form>
    </div>
    <div class="form-card" style="margin-bottom:0">
        <form method="post" action="">
            <input type="hidden" name="aksi" value="ganti_password">
            <div class="form-grid">
                <div class="field"><label>Password Lama</label><input type="password" name="password_lama" required autocomplete="current-password"></div>
                <div class="field"><label>Password Baru (min 6)</label><input type="password" name="password_baru" required minlength="6" autocomplete="new-password"></div>
                <div class="field"><label>Konfirmasi Password Baru</label><input type="password" name="password_konfirmasi" required minlength="6" autocomplete="new-password"></div>
            </div>
            <div class="form-actions"><button type="submit" class="btn btn-warn"><i class="fas fa-key"></i>Ganti Password</button></div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
