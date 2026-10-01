<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$me = require_login(array('admin'));
$title = 'Manajemen Pengguna';
$menu = 'users';

$roles_valid = array('admin', 'pengasuh', 'keuangan', 'wali');
$role_label  = array('admin' => 'Admin', 'pengasuh' => 'Pengasuh', 'keuangan' => 'Keuangan', 'wali' => 'Wali');

function last_admin_guard($db, $target_id) {
    // True jika $target_id adalah satu-satunya admin aktif -> aksi dilarang.
    $row = db_one($db, "SELECT COUNT(*) AS c FROM users WHERE role='admin' AND aktif=1 AND id <> ?", 'i', array($target_id));
    return $row && (int) $row['c'] < 1;
}

function valid_username($u) {
    return (bool) preg_match('/^[a-zA-Z0-9_.\-]{3,30}$/', $u);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $nama     = trim($_POST['nama_lengkap'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'pengasuh';
        $aktif    = isset($_POST['aktif']) ? 1 : 0;
        if (!valid_username($username)) {
            flash_set('Username 3-30 karakter: huruf, angka, titik, strip, underscore.', 'err');
        } elseif ($nama === '') {
            flash_set('Nama lengkap wajib diisi.', 'err');
        } elseif (strlen($password) < 6) {
            flash_set('Password minimal 6 karakter.', 'err');
        } elseif (!in_array($role, $roles_valid, true)) {
            flash_set('Role tidak valid.', 'err');
        } elseif (db_one($koneksi, 'SELECT id FROM users WHERE username = ? LIMIT 1', 's', array($username))) {
            flash_set('Username sudah dipakai.', 'err');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ok = db_exec($koneksi,
                'INSERT INTO users (username, password_hash, nama_lengkap, role, aktif) VALUES (?,?,?,?,?)',
                'ssssi', array($username, $hash, $nama, $role, $aktif));
            flash_set($ok ? 'Pengguna berhasil ditambahkan.' : 'Gagal menambah pengguna.', $ok ? 'ok' : 'err');
        }
        redirect('modules/users/');
    }

    if ($aksi === 'simpan_edit') {
        $id    = (int) ($_POST['id'] ?? 0);
        $nama  = trim($_POST['nama_lengkap'] ?? '');
        $role  = $_POST['role'] ?? 'pengasuh';
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $row = db_one($koneksi, 'SELECT * FROM users WHERE id = ? LIMIT 1', 'i', array($id));
        if (!$row) {
            flash_set('Pengguna tidak ditemukan.', 'err');
        } elseif ($nama === '') {
            flash_set('Nama lengkap wajib diisi.', 'err');
        } elseif (!in_array($role, $roles_valid, true)) {
            flash_set('Role tidak valid.', 'err');
        } elseif ($id === $me['id'] && !$aktif) {
            flash_set('Tidak bisa menonaktifkan akun sendiri.', 'err');
        } elseif ($row['role'] === 'admin' && $row['aktif'] && (!$aktif || $role !== 'admin') && last_admin_guard($koneksi, $id)) {
            flash_set('Tidak bisa: ini satu-satunya admin aktif.', 'err');
        } else {
            $ok = db_exec($koneksi,
                'UPDATE users SET nama_lengkap = ?, role = ?, aktif = ? WHERE id = ?',
                'ssii', array($nama, $role, $aktif, $id));
            flash_set($ok !== false ? 'Perubahan disimpan.' : 'Gagal menyimpan perubahan.', $ok !== false ? 'ok' : 'err');
        }
        redirect('modules/users/');
    }

    if ($aksi === 'hapus') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = db_one($koneksi, 'SELECT * FROM users WHERE id = ? LIMIT 1', 'i', array($id));
        if (!$row) {
            flash_set('Pengguna tidak ditemukan.', 'err');
        } elseif ($id === $me['id']) {
            flash_set('Tidak bisa menghapus akun sendiri.', 'err');
        } elseif ($row['role'] === 'admin' && $row['aktif'] && last_admin_guard($koneksi, $id)) {
            flash_set('Tidak bisa: ini satu-satunya admin aktif.', 'err');
        } else {
            $ok = db_exec($koneksi, 'DELETE FROM users WHERE id = ? LIMIT 1', 'i', array($id));
            flash_set($ok ? 'Pengguna dihapus.' : 'Gagal menghapus pengguna.', $ok ? 'ok' : 'err');
        }
        redirect('modules/users/');
    }

    if ($aksi === 'simpan_reset') {
        $id       = (int) ($_POST['id'] ?? 0);
        $password = $_POST['password_baru'] ?? '';
        $row = db_one($koneksi, 'SELECT id, username FROM users WHERE id = ? LIMIT 1', 'i', array($id));
        if (!$row) {
            flash_set('Pengguna tidak ditemukan.', 'err');
        } elseif (strlen($password) < 6) {
            flash_set('Password baru minimal 6 karakter.', 'err');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ok = db_exec($koneksi, 'UPDATE users SET password_hash = ? WHERE id = ?', 'si', array($hash, $id));
            flash_set($ok !== false ? 'Password pengguna "' . $row['username'] . '" berhasil direset.' : 'Gagal mereset password.', $ok !== false ? 'ok' : 'err');
        }
        redirect('modules/users/');
    }
}

$edit_row = null;
$reset_row = null;
if (isset($_GET['edit'])) {
    $edit_row = db_one($koneksi, 'SELECT * FROM users WHERE id = ? LIMIT 1', 'i', array((int) $_GET['edit']));
} elseif (isset($_GET['reset'])) {
    $reset_row = db_one($koneksi, 'SELECT id, username, nama_lengkap FROM users WHERE id = ? LIMIT 1', 'i', array((int) $_GET['reset']));
}

$rows = db_all($koneksi, 'SELECT id, username, nama_lengkap, role, aktif, created_at FROM users ORDER BY FIELD(role, "admin", "pengasuh", "keuangan", "wali"), username ASC');

include __DIR__ . '/../../includes/header.php';
?>

<?php if ($edit_row): ?>
<div class="form-card">
    <h2 class="section-title" style="margin-top:0"><i class="fas fa-user-pen sec-ico"></i>Edit Pengguna: <?= e($edit_row['username']) ?></h2>
    <form method="post" action="">
        <input type="hidden" name="aksi" value="simpan_edit">
        <input type="hidden" name="id" value="<?= (int) $edit_row['id'] ?>">
        <div class="form-grid">
            <div class="field"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required maxlength="100" value="<?= e($edit_row['nama_lengkap']) ?>"></div>
            <div class="field"><label>Role</label>
                <select name="role">
                    <?php foreach ($roles_valid as $r): ?>
                    <option value="<?= e($r) ?>"<?= $edit_row['role'] === $r ? ' selected' : '' ?>><?= e($role_label[$r]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Status</label>
                <label style="font-weight:normal"><input type="checkbox" name="aktif" value="1"<?= $edit_row['aktif'] ? ' checked' : '' ?>> Akun aktif</label>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn"><i class="fas fa-check"></i>Simpan</button>
            <a href="<?= url('modules/users/') ?>" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($reset_row): ?>
<div class="form-card">
    <h2 class="section-title" style="margin-top:0"><i class="fas fa-key sec-ico"></i>Reset Password: <?= e($reset_row['username']) ?> (<?= e($reset_row['nama_lengkap']) ?>)</h2>
    <form method="post" action="">
        <input type="hidden" name="aksi" value="simpan_reset">
        <input type="hidden" name="id" value="<?= (int) $reset_row['id'] ?>">
        <div class="form-grid">
            <div class="field"><label>Password Baru</label><input type="password" name="password_baru" required minlength="6" autocomplete="new-password"></div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-warn"><i class="fas fa-key"></i>Reset Password</button>
            <a href="<?= url('modules/users/') ?>" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="form-card">
    <h2 class="section-title" style="margin-top:0"><i class="fas fa-user-plus sec-ico"></i>Tambah Pengguna</h2>
    <form method="post" action="">
        <input type="hidden" name="aksi" value="tambah">
        <div class="form-grid">
            <div class="field"><label>Username</label><input type="text" name="username" required maxlength="30" pattern="[a-zA-Z0-9_.\-]{3,30}" title="3-30 karakter: huruf, angka, titik, strip, underscore"></div>
            <div class="field"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required maxlength="100"></div>
            <div class="field"><label>Password</label><input type="password" name="password" required minlength="6" autocomplete="new-password"></div>
            <div class="field"><label>Role</label>
                <select name="role">
                    <?php foreach ($roles_valid as $r): ?>
                    <option value="<?= e($r) ?>"><?= e($role_label[$r]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>&nbsp;</label><label style="font-weight:normal"><input type="checkbox" name="aktif" value="1" checked> Akun aktif</label></div>
        </div>
        <div class="form-actions"><button type="submit" class="btn"><i class="fas fa-plus"></i>Tambah</button></div>
    </form>
    <p style="color:#777;font-size:13px;margin:8px 0 0">Catatan: akun role "Wali" untuk portal wali nantinya dihubungkan ke data santri lewat tabel <code>wali_akun</code> (roadmap).</p>
</div>

<div class="table-wrap">
<table>
    <thead><tr><th>Username</th><th>Nama Lengkap</th><th>Role</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="6" class="empty">Belum ada pengguna.</td></tr>
    <?php else: foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= e($r['username']) ?></strong><?= $r['id'] === $me['id'] ? ' <span class="badge badge-ok">Anda</span>' : '' ?></td>
            <td><?= e($r['nama_lengkap']) ?></td>
            <td><span class="badge <?= $r['role'] === 'admin' ? 'badge-err' : 'badge-warn' ?>"><?= e($role_label[$r['role']] ?? $r['role']) ?></span></td>
            <td><span class="badge <?= $r['aktif'] ? 'badge-ok' : 'badge-err' ?>"><?= $r['aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td><?= e(tgl_indo(substr($r['created_at'], 0, 10))) ?></td>
            <td style="white-space:nowrap">
                <a href="<?= url('modules/users/?edit=' . (int) $r['id']) ?>" class="btn btn-sm">Edit</a>
                <a href="<?= url('modules/users/?reset=' . (int) $r['id']) ?>" class="btn btn-sm btn-warn">Reset PW</a>
                <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus pengguna <?= e($r['username']) ?>?')">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i>Hapus</button>
                </form>
            </td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
