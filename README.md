# Aplikasi Manajemen Santri — Pondok Pesantren Fath Darut Tafsir

Sistem informasi Pondok Pesantren Fath Darut Tafsir: data santri, asrama, kehadiran, hafalan (tahfidz),
keuangan (SPP + tabungan), dan pelanggaran/tata tertib. PHP + MySQL, gaya app PHP hiemz
(mysqli prosedural, tanpa framework). UI gaya FINO ceria (hijau) ala WMS 8090.

## Fitur (6 modul inti)

| Modul | Isi |
|---|---|
| **Santri** | Profil santri (NIS, nama, TTL, alamat, status) + daftar wali |
| **Asrama** | Data asrama & kamar, kapasitas, penempatan santri |
| **Kehadiran** | Sesi absensi per kegiatan (sholat 5 waktu, ngaji, madrasah) + kode unik sesi (siap QR) |
| **Hafalan** | Catat setoran/murojaah/tasmi', target juz per santri, progres hafalan |
| **Keuangan** | Tagihan SPP (satuan/massal), pembayaran, tabungan/uang saku + saldo |
| **Pelanggaran** | Master jenis pelanggaran + poin, catatan pelanggaran, rekap poin |

Plus: login multi-role (`admin`, `pengasuh`, `keuangan`, `wali`), dashboard ringkasan,
tabel `wali_akun` sebagai fondasi portal wali.

## Struktur folder

```
santri-app/
├── assets/style.css        # gaya FINO sage, responsif
├── config/
│   ├── database.example.php# contoh konfigurasi (salin jadi database.php)
│   └── koneksi.php         # koneksi mysqli
├── database/schema.sql     # skema MySQL lengkap + seed awal
├── includes/
│   ├── auth.php            # session, require_login(), role
│   ├── functions.php       # helper DB, url, flash, format
│   ├── header.php / footer.php  # layout + sidebar
├── modules/
│   ├── santri/ asrama/ kehadiran/
│   ├── hafalan/ keuangan/ pelanggaran/  # index.php per modul
├── uploads/                # upload foto (di-gitignore)
├── login.php / logout.php / index.php
└── README.md
```

## Instalasi

1. Buat database & import skema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE db_santri CHARACTER SET utf8mb4;"
   mysql -u root -p db_santri < database/schema.sql
   ```
2. Salin & isi konfigurasi:
   ```bash
   cp config/database.example.php config/database.php
   # edit DB_HOST, DB_USER, DB_PASS, DB_NAME
   ```
3. Arahkan web server ke folder ini (atau `php -S localhost:8000` untuk coba-coba).
4. Login default: **admin / admin123** — segera ganti setelah login pertama
   (atau buat user baru dan hapus user bawaan).

## Docker (server Armbian)

Container `santri-web`, port host `8094` -> `80`, volume bind ke folder app,
database `santri` di MariaDB server. Lihat riwayat deploy di memori/chat.

## Uji

```bash
# cek sintaks semua file PHP
find . -name '*.php' -exec php -l {} \;
```

## Roadmap

- Portal wali (login orang tua, lihat progres anak) — tabel `wali_akun` sudah siap
- Notifikasi WhatsApp otomatis (SPP jatuh tempo, pelanggaran, izin) via WA bot
- Absensi QR code per sesi kehadiran
- Modul perizinan/pulangan & kesehatan (UKS)
