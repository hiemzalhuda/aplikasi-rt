<?php
/**
 * Endpoint autocomplete warga untuk pencarian header.
 * Return JSON: [{id, nama, nik}, ...]
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

$user = require_login(); /* semua role yg login boleh */

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$out = array();
if ($q !== '') {
    $like = '%' . $q . '%';
    $rows = db_all($koneksi,
        'SELECT id, nama, nik FROM warga
         WHERE nama LIKE ? OR nik LIKE ?
         ORDER BY nama ASC LIMIT 10',
        'ss', array($like, $like));
    foreach ($rows as $r) {
        $out[] = array('id' => (int) $r['id'], 'nama' => $r['nama'], 'nik' => $r['nik']);
    }
}
echo json_encode($out);
