<?php
/**
 * Helper umum: escaping, URL, redirect, query DB, format tanggal.
 */

/** Branding Sistem Informasi RT */
if (!defined('APP_NAME')) define('APP_NAME', 'SI-RT');
if (!defined('APP_FULL')) define('APP_FULL', 'Sistem Informasi RT');
/** Identitas wilayah (ubah sesuai RT/RW setempat) */
if (!defined('RT_NO')) define('RT_NO', '01');
if (!defined('RW_NO')) define('RW_NO', ''); // kosongkan bila belum tahu no. RW
if (!defined('RT_WILAYAH')) define('RT_WILAYAH', 'Grand Harmoni 2, Balaraja');
/** Versi aplikasi (tampil di footer sidebar) */
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.0');
/** Nominal iuran bulanan default per KK (Rp) */
if (!defined('IURAN_DEFAULT')) define('IURAN_DEFAULT', 20000);

/** Daftar jenis surat + kode singkat untuk penomoran. */
function surat_jenis_list() {
    return array(
        'Keterangan Domisili'  => 'DOM',
        'Pengantar KTP'        => 'KTP',
        'Pengantar Kartu Keluarga' => 'KK',
        'Pengantar SKCK'       => 'SKCK',
        'Keterangan Kelahiran' => 'LAHIR',
        'Keterangan Kematian'  => 'MATI',
        'Keterangan Pindah'    => 'PINDAH',
        'Keterangan Usaha'     => 'USAHA',
        'Keterangan Tidak Mampu' => 'TM',
        'Lainnya'              => 'LAIN',
    );
}

/** Label identitas RT untuk tampilan, mis. "RT 01 · Grand Harmoni 2, Balaraja". */
function rt_label() {
    $s = 'RT ' . RT_NO;
    if (RW_NO !== '') $s .= '/RW ' . RW_NO;
    if (defined('RT_WILAYAH') && RT_WILAYAH !== '') $s .= ' · ' . RT_WILAYAH;
    return $s;
}

/** Label singkat untuk badan surat, mis. "RT 01" atau "RT 01/RW 02". */
function rt_label_singkat() {
    $s = 'RT ' . RT_NO;
    if (RW_NO !== '') $s .= '/RW ' . RW_NO;
    return $s;
}

/** Nomor surat berikutnya: 001/DOM/RT.01/X/2026 (segmen RW hanya bila diisi) */
function nomor_surat_berikutnya($db, $jenis) {
    $map = surat_jenis_list();
    $kode = isset($map[$jenis]) ? $map[$jenis] : 'LAIN';
    $tahun = date('Y');
    $bln_romawi = array(1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII');
    $r = db_one($db, 'SELECT COUNT(*) AS c FROM surat WHERE YEAR(tgl_terbit) = ?', 's', array($tahun));
    $seq = $r ? ((int) $r['c'] + 1) : 1;
    $seg = 'RT.' . RT_NO;
    if (RW_NO !== '') $seg .= '/RW.' . RW_NO;
    return sprintf('%03d', $seq) . '/' . $kode . '/' . $seg . '/'
        . $bln_romawi[(int) date('n')] . '/' . $tahun;
}

/** Nama warga dari id (untuk relasi surat/laporan). */
function warga_nama($db, $id) {
    if (!$id) return '-';
    $r = db_one($db, 'SELECT nama FROM warga WHERE id = ?', 'i', array((int) $id));
    return $r ? $r['nama'] : '-';
}

/** Nama kepala keluarga dari no KK. */
function kk_kepala($db, $no_kk) {
    $r = db_one($db, "SELECT nama FROM warga WHERE no_kk = ? AND hubungan = 'KEPALA KELUARGA' LIMIT 1",
        's', array($no_kk));
    if (!$r) $r = db_one($db, 'SELECT nama FROM warga WHERE no_kk = ? ORDER BY id ASC LIMIT 1',
        's', array($no_kk));
    return $r ? $r['nama'] : '-';
}

/** Saldo kas RT = total masuk - total keluar. */
function saldo_kas($db) {
    $m = db_one($db, "SELECT COALESCE(SUM(jumlah),0) AS s FROM kas_transaksi WHERE jenis = 'masuk'");
    $k = db_one($db, "SELECT COALESCE(SUM(jumlah),0) AS s FROM kas_transaksi WHERE jenis = 'keluar'");
    return (int) ($m['s'] ?? 0) - (int) ($k['s'] ?? 0);
}

/** '2026-10' -> 'Oktober 2026'. */
function periode_indo($periode) {
    $bulan = array(1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
        7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember');
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $periode, $m)) return (string) $periode;
    $b = (int) $m[2];
    return ($bulan[$b] ?? $m[2]) . ' ' . $m[1];
}

/** Label tampilan role pengguna. */
function role_label($r) {
    $map = array('admin' => 'Admin', 'ketua' => 'Ketua RT',
        'sekretaris' => 'Sekretaris', 'bendahara' => 'Bendahara');
    return $map[$r] ?? ucfirst((string) $r);
}

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
    $u = url($p);
    /* Bila klien tidak membawa cookie sesi (mis. di-strip di tengah jalan),
       teruskan ID sesi via URL agar tetap login setelah pindah halaman.
       Klien yang cookie-nya normal tetap dapat URL bersih. */
    if (session_id() !== '' && !isset($_COOKIE[session_name()])) {
        $u .= (strpos($u, '?') === false ? '?' : '&')
            . urlencode(session_name()) . '=' . urlencode(session_id());
    }
    header('Location: ' . $u);
    /* Fallback: ada proxy/CDN yang menghilangkan header Location sehingga
       browser hanya menampilkan halaman kosong. Body ini memastikan browser
       tetap pindah walau header-nya hilang di tengah jalan. */
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
        . '<meta http-equiv="refresh" content="0;url=' . e($u) . '">'
        . '<title>Mengalihkan...</title></head><body>'
        . '<script>location.replace(' . json_encode($u) . ');</script>'
        . '<p>Mengalihkan, klik <a href="' . e($u) . '">di sini</a> bila tidak berpindah otomatis.</p>'
        . '</body></html>';
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
