<?php
/**
 * Koneksi database (mysqli prosedural, gaya app PHP hiemz).
 * Kredensial dibaca dari config/database.php (di-gitignore).
 */
$dbconf = __DIR__ . '/database.php';
if (!file_exists($dbconf)) {
    http_response_code(500);
    die('Konfigurasi database belum ada. Salin <code>config/database.example.php</code> menjadi <code>config/database.php</code> lalu isi kredensial MySQL.');
}
require_once $dbconf;

$koneksi = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
if ($koneksi->connect_error) {
    http_response_code(500);
    die('Gagal konek database: ' . htmlspecialchars($koneksi->connect_error, ENT_QUOTES, 'UTF-8')
        . '. Periksa <code>config/database.php</code>.');
}
$koneksi->set_charset('utf8mb4');
