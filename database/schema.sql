-- =====================================================================
-- Sistem Informasi RT — skema database MySQL
-- (pengganti Aplikasi Manajemen Santri; tabel santri lama tidak dipakai
-- lagi tapi tidak dihapus agar data lama tetap aman)
-- Cara pakai:
--   mysql -u root -p -e "CREATE DATABASE db_santri CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p db_santri < database/schema.sql
-- Login awal: admin / admin123  (SEGERA GANTI setelah login pertama!)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_santri
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_santri;

-- ---------------------------------------------------------------------
-- Pengguna & role (admin, ketua, sekretaris, bendahara)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama_lengkap  VARCHAR(100) NOT NULL,
  role          ENUM('admin','ketua','sekretaris','bendahara') NOT NULL DEFAULT 'sekretaris',
  aktif         TINYINT(1) NOT NULL DEFAULT 1,
  last_seen     TIMESTAMP NULL DEFAULT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 1. Data warga (satu baris = satu jiwa; KK dikelompokkan via no_kk)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS warga (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nik           VARCHAR(16) NOT NULL UNIQUE,
  no_kk         VARCHAR(16) NOT NULL,
  nama          VARCHAR(100) NOT NULL,
  tempat_lahir  VARCHAR(60) NULL,
  tgl_lahir     DATE NULL,
  jk            ENUM('L','P') NOT NULL DEFAULT 'L',
  agama         VARCHAR(20) NULL,
  pendidikan    VARCHAR(30) NULL,
  pekerjaan     VARCHAR(50) NULL,
  status_kawin  VARCHAR(20) NULL,
  hubungan      ENUM('KEPALA KELUARGA','SUAMI','ISTRI','ANAK','ORANG TUA','FAMILI LAIN','LAINNYA')
                NOT NULL DEFAULT 'KEPALA KELUARGA',
  alamat        VARCHAR(150) NULL,
  no_hp         VARCHAR(20) NULL,
  status_tinggal ENUM('TETAP','KONTRAK','KOS') NOT NULL DEFAULT 'TETAP',
  keterangan    TEXT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_warga_kk (no_kk),
  INDEX idx_warga_nama (nama)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Kas RT (transaksi masuk / keluar)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kas_transaksi (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tanggal     DATE NOT NULL,
  jenis       ENUM('masuk','keluar') NOT NULL,
  kategori    VARCHAR(40) NOT NULL,
  keterangan  VARCHAR(200) NULL,
  jumlah      BIGINT NOT NULL DEFAULT 0,
  dibuat_oleh INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_kas_tanggal (tanggal),
  INDEX idx_kas_jenis (jenis)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Iuran warga per KK per periode (YYYY-MM)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS iuran (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  no_kk      VARCHAR(16) NOT NULL,
  periode    CHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
  jumlah     BIGINT NOT NULL DEFAULT 0,
  status     ENUM('belum','lunas') NOT NULL DEFAULT 'belum',
  tgl_bayar  DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_iuran_kk_periode (no_kk, periode),
  INDEX idx_iuran_periode (periode)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Surat keterangan / pengantar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS surat (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  no_surat    VARCHAR(80) NOT NULL UNIQUE,
  jenis       VARCHAR(60) NOT NULL,
  warga_id    INT UNSIGNED NULL,
  keperluan   VARCHAR(200) NULL,
  tgl_terbit  DATE NOT NULL,
  status      ENUM('draft','terbit') NOT NULL DEFAULT 'terbit',
  dibuat_oleh INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_surat_tgl (tgl_terbit)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Kegiatan / agenda RT
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kegiatan (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama      VARCHAR(120) NOT NULL,
  tanggal   DATE NOT NULL,
  waktu     VARCHAR(20) NULL,
  tempat    VARCHAR(100) NULL,
  deskripsi TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_kegiatan_tanggal (tanggal)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Pengumuman warga
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengumuman (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  judul       VARCHAR(150) NOT NULL,
  isi         TEXT NOT NULL,
  tanggal     DATE NOT NULL,
  aktif       TINYINT(1) NOT NULL DEFAULT 1,
  dibuat_oleh INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. Laporan / aduan warga
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS laporan (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pelapor   VARCHAR(100) NULL,
  kategori  ENUM('Keamanan','Kebersihan','Fasilitas','Sosial','Lainnya') NOT NULL DEFAULT 'Lainnya',
  judul     VARCHAR(150) NOT NULL,
  isi       TEXT NOT NULL,
  tanggal   DATE NOT NULL,
  status    ENUM('baru','diproses','selesai') NOT NULL DEFAULT 'baru',
  tanggapan TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_laporan_status (status)
) ENGINE=InnoDB;
