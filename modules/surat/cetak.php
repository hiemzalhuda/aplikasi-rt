<?php
/**
 * Cetak surat keterangan / pengantar — halaman mandiri (tanpa header/footer aplikasi).
 * Dibuka via target _blank dari daftar surat.
 */
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/koneksi.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
$surat = db_one($koneksi,
    'SELECT s.*, w.nik, w.no_kk, w.nama, w.tempat_lahir, w.tgl_lahir, w.jk, w.pekerjaan, w.alamat
     FROM surat s LEFT JOIN warga w ON w.id = s.warga_id
     WHERE s.id = ?', 'i', array($id));

if (!$surat) {
    die('Surat tidak ditemukan.');
}

$judul   = 'SURAT ' . strtoupper($surat['jenis']);
$jk      = ($surat['jk'] === 'P') ? 'Perempuan' : 'Laki-laki';
$ttl     = ($surat['tempat_lahir'] ?: '') !== '' || $surat['tgl_lahir']
    ? trim(($surat['tempat_lahir'] ?: '-') . ', ' . tgl_indo($surat['tgl_lahir']), ' ,')
    : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($surat['no_surat']) ?> — <?= e($judul) ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Georgia, 'Times New Roman', serif; font-size: 14pt; line-height: 1.6;
           color: #111; background: #f2f2f2; margin: 0; padding: 24px; }
    .toolbar { max-width: 800px; margin: 0 auto 20px; display: flex; gap: 10px; justify-content: flex-end; }
    .btn-print { font-family: system-ui, sans-serif; font-size: 14px; font-weight: 700; cursor: pointer;
                 background: #2e7d32; color: #fff; border: none; border-radius: 8px; padding: 10px 22px; }
    .btn-print:hover { background: #256729; }
    .sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 56px 60px;
             box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    /* ---- Kop surat ---- */
    .kop { text-align: center; margin-bottom: 6px; }
    .kop h2 { font-size: 17pt; margin: 0; letter-spacing: .5px; }
    .kop p { font-size: 12.5pt; margin: 3px 0 0; }
    .kop-line { border: none; border-top: 3px double #111; margin: 10px 0 22px; }
    /* ---- Isi ---- */
    .nomor { text-align: center; margin-bottom: 4px; }
    .judul-surat { text-align: center; font-size: 16pt; font-weight: bold; text-decoration: underline;
                   letter-spacing: 1px; margin: 0 0 18px; }
    p.just { text-align: justify; }
    table.data { width: 100%; border-collapse: collapse; margin: 12px 0 12px 24px; }
    table.data td { vertical-align: top; padding: 2px 6px; }
    table.data td.k { width: 210px; }
    /* ---- Tanda tangan ---- */
    .ttd { width: 300px; margin: 46px 0 0 auto; text-align: center; }
    .ttd .spasi { height: 86px; }
    .ttd .nama-terang { text-decoration: underline; font-weight: bold; }
    @media print {
        body { background: #fff; padding: 0; }
        .toolbar { display: none; }
        .sheet { box-shadow: none; max-width: none; padding: 0 8mm; }
    }
</style>
</head>
<body>

<div class="toolbar no-print">
    <button type="button" class="btn-print" onclick="window.print()">&#9113; Cetak Surat</button>
</div>

<div class="sheet">
    <div class="kop">
        <h2>RUKUN TETANGGA <?= e(RT_NO) ?> / RUKUN WARGA <?= e(RW_NO) ?></h2>
        <p>Alamat Sekretariat RT</p>
    </div>
    <hr class="kop-line">

    <p class="nomor">Nomor: <?= e($surat['no_surat']) ?></p>
    <h1 class="judul-surat"><?= e($judul) ?></h1>

    <p class="just">Yang bertanda tangan di bawah ini, Ketua RT <?= e(RT_NO) ?>/RW <?= e(RW_NO) ?>, menerangkan bahwa:</p>

    <table class="data">
        <tr><td class="k">Nama</td><td>: <strong><?= e($surat['nama'] ?: '-') ?></strong></td></tr>
        <tr><td class="k">NIK</td><td>: <?= e($surat['nik'] ?: '-') ?></td></tr>
        <tr><td class="k">No. KK</td><td>: <?= e($surat['no_kk'] ?: '-') ?></td></tr>
        <tr><td class="k">Tempat / Tanggal Lahir</td><td>: <?= e($ttl) ?></td></tr>
        <tr><td class="k">Jenis Kelamin</td><td>: <?= e($jk) ?></td></tr>
        <tr><td class="k">Pekerjaan</td><td>: <?= e($surat['pekerjaan'] ?: '-') ?></td></tr>
        <tr><td class="k">Alamat</td><td>: <?= e($surat['alamat'] ?: '-') ?></td></tr>
    </table>

    <p class="just">Nama tersebut di atas adalah benar warga RT <?= e(RT_NO) ?>/RW <?= e(RW_NO) ?>.
    Surat ini dibuat untuk keperluan: <strong><?= e($surat['keperluan'] ?: '-') ?></strong>.</p>

    <p class="just">Demikian surat ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

    <div class="ttd">
        <p style="margin:0">Jakarta, <?= e(tgl_indo($surat['tgl_terbit'])) ?></p>
        <p style="margin:0">Ketua RT <?= e(RT_NO) ?>/RW <?= e(RW_NO) ?></p>
        <div class="spasi"></div>
        <p class="nama-terang">( Nama Terang )</p>
    </div>
</div>

</body>
</html>
