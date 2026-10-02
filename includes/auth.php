<?php
/**
 * Autentikasi session + proteksi role.
 */
/* Sesi tahan proxy: cookie sesi kadang di-strip di jalur Cloudflare,
   jadi izinkan ID sesi lewat URL sebagai cadangan (trans_sid). Cookie
   tetap diutamakan; URL hanya dipakai bila klien tidak membawa cookie. */
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '0');
ini_set('session.use_trans_sid', '1');
ini_set('session.use_strict_mode', '1');
/* Jangan bocorkan URL (yang bisa berisi ID sesi) via Referer ke CDN. */
header('Referrer-Policy: no-referrer');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Wajibkan login. $roles = daftar role yg boleh, kosong = semua role.
 */
function require_login($roles = array()) {
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        die('Akses ditolak untuk role ini.');
    }
    return $u;
}

function login_user($row) {
    $_SESSION['user'] = array(
        'id'       => (int) $row['id'],
        'username' => $row['username'],
        'nama'     => $row['nama_lengkap'],
        'role'     => $row['role'],
    );
}

function logout_user() {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
