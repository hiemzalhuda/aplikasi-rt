<?php
/**
 * Autentikasi session + proteksi role.
 */
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
