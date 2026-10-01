-- =====================================================================
-- Aplikasi Manajemen Santri — skema database MySQL
-- Cara pakai:
--   mysql -u root -p -e "CREATE DATABASE db_santri CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p db_santri < database/schema.sql
-- Login awal: admin / admin123  (SEGERA GANTI setelah login pertama!)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_santri
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_santri;

-- ---------------------------------------------------------------------
-- Pengguna & role
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama_lengkap  VARCHAR(100) NOT NULL,
  role          ENUM('admin','pengasuh','keuangan','wali') NOT NULL DEFAULT 'pengasuh',
  aktif         TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 1. Santri & wali
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS santri (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nis           VARCHAR(20) NOT NULL UNIQUE,
  nama          VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL DEFAULT 'L',
  tempat_lahir  VARCHAR(60) NULL,
  tgl_lahir     DATE NULL,
  alamat        TEXT NULL,
  foto          VARCHAR(255) NULL,
  no_hp         VARCHAR(20) NULL,
  status        ENUM('aktif','nonaktif','alumni','keluar','cuti') NOT NULL DEFAULT 'aktif',
  tgl_masuk     DATE NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_santri_status (status),
  INDEX idx_santri_nama (nama)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wali (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id INT UNSIGNED NOT NULL,
  nama      VARCHAR(100) NOT NULL,
  hubungan  VARCHAR(30) NOT NULL DEFAULT 'wali',
  no_wa     VARCHAR(20) NULL,
  alamat    TEXT NULL,
  CONSTRAINT fk_wali_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  INDEX idx_wali_santri (santri_id)
) ENGINE=InnoDB;

-- Fondasi portal wali: akun login role 'wali' yg terikat ke santri
CREATE TABLE IF NOT EXISTS wali_akun (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id   INT UNSIGNED NOT NULL UNIQUE,
  santri_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_wa_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_wa_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Asrama, kamar, penempatan
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS asrama (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama    VARCHAR(60) NOT NULL,
  jenis   ENUM('putra','putri') NOT NULL DEFAULT 'putra',
  pembina VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kamar (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asrama_id INT UNSIGNED NOT NULL,
  nama      VARCHAR(30) NOT NULL,
  kapasitas INT NOT NULL DEFAULT 4,
  CONSTRAINT fk_kamar_asrama FOREIGN KEY (asrama_id)
    REFERENCES asrama (id) ON DELETE CASCADE,
  UNIQUE KEY uq_kamar (asrama_id, nama)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS penempatan_santri (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id   INT UNSIGNED NOT NULL,
  kamar_id    INT UNSIGNED NOT NULL,
  tgl_mulai   DATE NOT NULL,
  tgl_selesai DATE NULL,
  CONSTRAINT fk_ps_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_kamar FOREIGN KEY (kamar_id)
    REFERENCES kamar (id) ON DELETE RESTRICT,
  INDEX idx_ps_santri (santri_id),
  INDEX idx_ps_kamar (kamar_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Kehadiran (sesi + detail per santri)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesi_kehadiran (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode        VARCHAR(40) NOT NULL UNIQUE COMMENT 'kode unik sesi, siap untuk QR',
  tanggal     DATE NOT NULL,
  jenis       ENUM('subuh','dzuhur','ashar','maghrib','isya','ngaji','madrasah') NOT NULL,
  keterangan  VARCHAR(120) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sk_user FOREIGN KEY (created_by)
    REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_sesi_tgl_jenis (tanggal, jenis)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kehadiran (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesi_id    INT UNSIGNED NOT NULL,
  santri_id  INT UNSIGNED NOT NULL,
  status     ENUM('hadir','izin','sakit','alpa') NOT NULL DEFAULT 'hadir',
  keterangan VARCHAR(120) NULL,
  dicatat_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_kh_sesi FOREIGN KEY (sesi_id)
    REFERENCES sesi_kehadiran (id) ON DELETE CASCADE,
  CONSTRAINT fk_kh_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  UNIQUE KEY uq_kehadiran (sesi_id, santri_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Hafalan (setoran + target)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS target_hafalan (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id  INT UNSIGNED NOT NULL,
  target_juz DECIMAL(4,1) NOT NULL DEFAULT 0,
  periode    VARCHAR(20) NULL COMMENT 'mis. 2026/2027',
  CONSTRAINT fk_th_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  INDEX idx_th_santri (santri_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS setoran_hafalan (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id   INT UNSIGNED NOT NULL,
  tanggal     DATE NOT NULL,
  jenis       ENUM('setoran','murojaah','tasmi') NOT NULL DEFAULT 'setoran',
  juz         TINYINT UNSIGNED NULL,
  surah       VARCHAR(60) NULL,
  ayat_dari   VARCHAR(10) NULL,
  ayat_sampai VARCHAR(10) NULL,
  nilai       TINYINT UNSIGNED NULL COMMENT '0-100',
  penyimak    VARCHAR(100) NULL,
  catatan     VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sh_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  CONSTRAINT fk_sh_user FOREIGN KEY (created_by)
    REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_sh_santri_tgl (santri_id, tanggal)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4b. Nilai akademik santri
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nilai (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id    INT UNSIGNED NOT NULL,
  mapel        VARCHAR(60) NOT NULL,
  jenis        ENUM('tugas','UH','UTS','UAS') NOT NULL DEFAULT 'tugas',
  nilai        DECIMAL(5,2) NOT NULL COMMENT '0-100',
  semester     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=ganjil, 2=genap',
  tahun_ajaran VARCHAR(9) NOT NULL COMMENT 'mis. 2026/2027',
  tanggal      DATE NOT NULL,
  keterangan   VARCHAR(255) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_nilai_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  CONSTRAINT fk_nilai_user FOREIGN KEY (created_by)
    REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_nilai_santri_ta (santri_id, tahun_ajaran, semester),
  INDEX idx_nilai_mapel (mapel)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Keuangan (SPP + tabungan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tagihan_spp (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id INT UNSIGNED NOT NULL,
  bulan     CHAR(7) NOT NULL COMMENT 'format YYYY-MM',
  nominal   INT NOT NULL,
  status    ENUM('belum','sebagian','lunas') NOT NULL DEFAULT 'belum',
  CONSTRAINT fk_tsp_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  UNIQUE KEY uq_tagihan (santri_id, bulan),
  INDEX idx_tsp_bulan (bulan)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pembayaran_spp (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tagihan_id   INT UNSIGNED NOT NULL,
  tanggal      DATE NOT NULL,
  jumlah       INT NOT NULL,
  metode       VARCHAR(30) NULL COMMENT 'tunai/transfer',
  diterima_oleh INT UNSIGNED NULL,
  catatan      VARCHAR(120) NULL,
  CONSTRAINT fk_psp_tagihan FOREIGN KEY (tagihan_id)
    REFERENCES tagihan_spp (id) ON DELETE CASCADE,
  CONSTRAINT fk_psp_user FOREIGN KEY (diterima_oleh)
    REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_psp_tagihan (tagihan_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tabungan (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id   INT UNSIGNED NOT NULL,
  tanggal     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  jenis       ENUM('masuk','keluar') NOT NULL,
  jumlah      INT NOT NULL,
  keterangan  VARCHAR(120) NULL,
  dicatat_oleh INT UNSIGNED NULL,
  CONSTRAINT fk_tb_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  CONSTRAINT fk_tb_user FOREIGN KEY (dicatat_oleh)
    REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_tb_santri (santri_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. Pelanggaran & tata tertib
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS master_pelanggaran (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama     VARCHAR(100) NOT NULL,
  poin     INT NOT NULL DEFAULT 0,
  kategori VARCHAR(40) NULL COMMENT 'ringan/sedang/berat'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS catatan_pelanggaran (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  santri_id      INT UNSIGNED NOT NULL,
  pelanggaran_id INT UNSIGNED NOT NULL,
  tanggal        DATE NOT NULL,
  sanksi         VARCHAR(255) NULL,
  status         ENUM('proses','selesai') NOT NULL DEFAULT 'proses',
  dicatat_oleh   INT UNSIGNED NULL,
  catatan        VARCHAR(255) NULL,
  CONSTRAINT fk_cp_santri FOREIGN KEY (santri_id)
    REFERENCES santri (id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_master FOREIGN KEY (pelanggaran_id)
    REFERENCES master_pelanggaran (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cp_user FOREIGN KEY (dicatat_oleh)
    REFERENCES users (id) ON DELETE SET NULL,
  INDEX idx_cp_santri_tgl (santri_id, tanggal)
) ENGINE=InnoDB;

-- =====================================================================
-- Data awal
-- =====================================================================

-- User admin bawaan (password: admin123 — SEGERA GANTI!)
INSERT INTO users (username, password_hash, nama_lengkap, role) VALUES
('admin', '$2y$10$zBDBGrujH0dBFFOOstre7.8QD5wPlnea3tc42oh0DdnPJNhwyFfn.', 'Administrator', 'admin');

-- Contoh master pelanggaran
INSERT INTO master_pelanggaran (nama, poin, kategori) VALUES
('Terlambat sholat berjamaah', 5, 'ringan'),
('Tidak mengikuti ngaji tanpa izin', 10, 'ringan'),
('Keluar pondok tanpa izin', 25, 'sedang'),
('Membawa HP tanpa izin', 20, 'sedang'),
('Merokok di area pondok', 50, 'berat');
