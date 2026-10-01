<?php
/**
 * Endpoint JSON autocomplete pencarian santri (dipakai search header).
 * GET ?q=... -> [{id, nis, nama}], maks 8 hasil.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(array());
    exit;
}

$like = '%' . $q . '%';
$rows = db_all($koneksi,
    'SELECT id, nis, nama FROM santri
     WHERE nama LIKE ? OR nis LIKE ?
     ORDER BY nama ASC LIMIT 8',
    'ss', array($like, $like));

$out = array();
foreach ($rows as $r) {
    $out[] = array(
        'id'   => (int) $r['id'],
        'nis'  => $r['nis'],
        'nama' => $r['nama'],
    );
}
echo json_encode($out);
