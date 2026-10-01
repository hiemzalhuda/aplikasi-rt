<?php
/**
 * Helper umum: escaping, URL, redirect, query DB, format tanggal.
 */

/** Branding pondok pesantren */
if (!defined('APP_NAME')) define('APP_NAME', 'Fath Darut Tafsir');
if (!defined('APP_FULL')) define('APP_FULL', 'Pondok Pesantren Fath Darut Tafsir');

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

function tgl_indo($date) {
    if (!$date) return '-';
    $bulan = array(1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des');
    $t = strtotime($date);
    if (!$t) return e($date);
    return date('d', $t) . ' ' . $bulan[(int) date('n', $t)] . ' ' . date('Y', $t);
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
