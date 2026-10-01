<?php
/**
 * Helper umum: escaping, URL, redirect, query DB, format tanggal.
 */

/** Branding pondok pesantren */
if (!defined('APP_NAME')) define('APP_NAME', 'Fath Darut Tafsir');
if (!defined('APP_FULL')) define('APP_FULL', 'Pondok Pesantren Fath Darut Tafsir');
/** Versi aplikasi (tampil di footer sidebar) */
if (!defined('APP_VERSION')) define('APP_VERSION', '1.4.0');

function e($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Path aplikasi relatif terhadap document root (mendukung sub-folder). */
function base_path() {
    static $base = null;
    if ($base === null) {
        $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : '';
        $approot = realpath(__DIR__ . '/..');
        $rel = ($docroot && $approot) ? str_replace($docroot, '', $approot) : '';
        $base = rtrim(str_replace('\\', '/', (string) $rel), '/');
    }
    return $base;
}

function url($p = '') {
    return base_path() . '/' . ltrim($p, '/');
}

/* URL aset dengan cache-buster: ?v=filemtime agar browser selalu ambil
   CSS terbaru setiap kali file berubah (tidak lagi tertahan cache lama). */
function asset_v($rel) {
    $rel = ltrim($rel, '/');
    $fs = __DIR__ . '/../' . $rel;
    $v = @filemtime($fs);
    return url($rel) . ($v ? '?v=' . $v : '');
}

function redirect($p = '') {
    header('Location: ' . url($p));
    exit;
}

/** Query SELECT -> array assoc. */
function db_all($db, $sql, $types = '', $params = array()) {
    $stmt = $db->prepare($sql);
    if (!$stmt) return array();
    if ($types !== '' && $params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
    $stmt->close();
    return $rows;
}

/** Query SELECT -> satu baris assoc atau null. */
function db_one($db, $sql, $types = '', $params = array()) {
    $rows = db_all($db, $sql, $types, $params);
    return $rows ? $rows[0] : null;
}

/** Query INSERT/UPDATE/DELETE. Return affected rows atau false. */
function db_exec($db, $sql, $types = '', $params = array()) {
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    if ($types !== '' && $params) $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $aff = $ok ? $stmt->affected_rows : false;
    $stmt->close();
    return $aff;
}

/** Catat aktivitas user (dipanggil tiap halaman dimuat). */
function sentuh_online($db, $uid) {
    db_exec($db, 'UPDATE users SET last_seen = NOW() WHERE id = ?', 'i', array((int) $uid));
}

/** Jumlah user yg aktif dalam 5 menit terakhir. */
function hitung_online($db) {
    $r = db_one($db, "SELECT COUNT(*) AS n FROM users WHERE aktif = 1 AND last_seen >= NOW() - INTERVAL 5 MINUTE");
    return $r ? (int) $r['n'] : 0;
}

function tgl_indo($date) {
    if (!$date) return '-';
    $bulan = array(1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des');
    $t = strtotime($date);
    if (!$t) return e($date);
    return date('d', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
}

/** Link nama santri ke halaman profil. */
function profil_link($id, $label) {
    return '<a class="tbl-link" href="' . e(url('modules/santri/profil.php?id=' . (int) $id)) . '">' . e($label) . '</a>';
}

/** Durasi "X tahun Y bulan Z hari" dari tanggal masuk sampai hari ini. */
function lama_santri($tgl_masuk) {
    if (!$tgl_masuk) return '-';
    try { $a = new DateTime($tgl_masuk); } catch (Exception $e) { return '-'; }
    $b = new DateTime(date('Y-m-d'));
    if ($a > $b) return '0 hari';
    $d = $a->diff($b);
    $parts = array();
    if ($d->y) $parts[] = $d->y . ' tahun';
    if ($d->m) $parts[] = $d->m . ' bulan';
    if ($d->d || !$parts) $parts[] = $d->d . ' hari';
    return implode(' ', $parts);
}

function rupiah($n) {
    return 'Rp ' . number_format((int) $n, 0, ',', '.');
}

function flash_set($msg, $type = 'ok') {
    $_SESSION['flash'] = array('msg' => $msg, 'type' => $type);
}

function flash_get() {
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cls = $f['type'] === 'err' ? 'alert-err' : 'alert-ok';
    return '<div class="alert ' . $cls . '">' . e($f['msg']) . '</div>';
}

/** Validasi nama file foto yg tersimpan di DB (anti path traversal). */
function foto_ok($f) {
    return $f && preg_match('/^[A-Za-z0-9._-]+$/', $f);
}

/** Proses upload foto santri baru; return array(nama_file, pesan_error). */
function upload_foto_santri($file, $old, $upload_dir) {
    if (empty($file['name'])) return array($old, null);
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return array($old, 'Upload foto gagal.');
    }
    $allowed = array('jpg' => 1, 'jpeg' => 1, 'png' => 1, 'webp' => 1);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) return array($old, 'Format foto harus jpg, png, atau webp.');
    if ($file['size'] > 2 * 1024 * 1024) return array($old, 'Ukuran foto maksimal 2 MB.');
    if (!@getimagesize($file['tmp_name'])) return array($old, 'File bukan gambar yang valid.');
    if (!is_dir($upload_dir) && !@mkdir($upload_dir, 0775, true)) {
        return array($old, 'Direktori upload tidak tersedia.');
    }
    $name = 'santri-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!@move_uploaded_file($file['tmp_name'], $upload_dir . '/' . $name)) {
        return array($old, 'Gagal menyimpan foto.');
    }
    if (foto_ok($old) && $old !== $name && file_exists($upload_dir . '/' . $old)) {
        @unlink($upload_dir . '/' . $old);
    }
    return array($name, null);
}
