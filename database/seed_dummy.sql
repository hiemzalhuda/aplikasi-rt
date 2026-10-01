-- =====================================================================
-- DATA DUMMY — Aplikasi Manajemen Santri
-- *** SELURUH DATA DI FILE INI ADALAH DUMMY UNTUK TESTING/DEMO ***
-- Bukan data santri sungguhan. Hapus baris-baris ini sebelum produksi,
-- atau ganti dengan data asli.
--
-- Aman dijalankan berulang (idempotent): setiap INSERT memakai
-- ON DUPLICATE KEY UPDATE atau cek NOT EXISTS sehingga tidak dobel.
--
-- Cara pakai:
--   mysql -u <user> -p db_santri < database/seed_dummy.sql
-- =====================================================================

USE db_santri;

-- ---------------------------------------------------------------------
-- DUMMY: Asrama
-- ---------------------------------------------------------------------
INSERT INTO asrama (nama, jenis, pembina)
SELECT 'Asrama Al-Falah', 'putra', 'Ust. Abdullah'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM asrama WHERE nama = 'Asrama Al-Falah');

INSERT INTO asrama (nama, jenis, pembina)
SELECT 'Asrama An-Nur', 'putri', 'Ustzh. Maryam'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM asrama WHERE nama = 'Asrama An-Nur');

-- ---------------------------------------------------------------------
-- DUMMY: Kamar
-- ---------------------------------------------------------------------
INSERT INTO kamar (asrama_id, nama, kapasitas)
SELECT a.id, 'A-1', 4 FROM asrama a
WHERE a.nama = 'Asrama Al-Falah'
  AND NOT EXISTS (SELECT 1 FROM kamar k WHERE k.asrama_id = a.id AND k.nama = 'A-1');

INSERT INTO kamar (asrama_id, nama, kapasitas)
SELECT a.id, 'A-2', 4 FROM asrama a
WHERE a.nama = 'Asrama Al-Falah'
  AND NOT EXISTS (SELECT 1 FROM kamar k WHERE k.asrama_id = a.id AND k.nama = 'A-2');

INSERT INTO kamar (asrama_id, nama, kapasitas)
SELECT a.id, 'B-1', 4 FROM asrama a
WHERE a.nama = 'Asrama An-Nur'
  AND NOT EXISTS (SELECT 1 FROM kamar k WHERE k.asrama_id = a.id AND k.nama = 'B-1');

-- ---------------------------------------------------------------------
-- DUMMY: 5 santri
-- ---------------------------------------------------------------------
INSERT INTO santri (nis, nama, jenis_kelamin, tempat_lahir, tgl_lahir, alamat, no_hp, status, tgl_masuk) VALUES
('2026001', 'Ahmad Fauzi Ramadhan',   'L', 'Cikupa',   '2012-03-15', 'Kp. Cibadak RT 03/07, Cikupa, Tangerang',   '081234560001', 'aktif', '2026-07-14'),
('2026002', 'Muhammad Rizky Pratama', 'L', 'Tigaraksa','2011-11-02', 'Jl. Syekh Nawawi No. 12, Tigaraksa',          '081234560002', 'aktif', '2026-07-14'),
('2026003', 'Budi Santoso',           'L', 'Curug',    '2012-07-21', 'Kp. Cukanggalih RT 01/05, Curug, Tangerang',  '081234560003', 'aktif', '2026-07-14'),
('2026004', 'Siti Aisyah Putri',      'P', 'Balaraja', '2012-01-30', 'Kp. Saga RT 02/04, Balaraja, Tangerang',      '081234560004', 'aktif', '2026-07-14'),
('2026005', 'Dewi Lestari',           'P', 'Cisoka',   '2011-09-12', 'Jl. Raya Cisoka KM 3, Cisoka, Tangerang',     '081234560005', 'aktif', '2026-07-14')
ON DUPLICATE KEY UPDATE
  nama          = VALUES(nama),
  jenis_kelamin = VALUES(jenis_kelamin),
  tempat_lahir  = VALUES(tempat_lahir),
  tgl_lahir     = VALUES(tgl_lahir),
  alamat        = VALUES(alamat),
  no_hp         = VALUES(no_hp),
  status        = VALUES(status),
  tgl_masuk     = VALUES(tgl_masuk);

-- ---------------------------------------------------------------------
-- DUMMY: wali (1 per santri)
-- ---------------------------------------------------------------------
INSERT INTO wali (santri_id, nama, hubungan, no_wa, alamat)
SELECT s.id, 'H. Muhammad Ridwan', 'ayah', '081234567801', 'Kp. Cibadak RT 03/07, Cikupa, Tangerang'
FROM santri s WHERE s.nis = '2026001'
  AND NOT EXISTS (SELECT 1 FROM wali w WHERE w.santri_id = s.id AND w.hubungan = 'ayah');

INSERT INTO wali (santri_id, nama, hubungan, no_wa, alamat)
SELECT s.id, 'Siti Aminah', 'ibu', '081234567802', 'Jl. Syekh Nawawi No. 12, Tigaraksa'
FROM santri s WHERE s.nis = '2026002'
  AND NOT EXISTS (SELECT 1 FROM wali w WHERE w.santri_id = s.id AND w.hubungan = 'ibu');

INSERT INTO wali (santri_id, nama, hubungan, no_wa, alamat)
SELECT s.id, 'Joko Santoso', 'ayah', '081234567803', 'Kp. Cukanggalih RT 01/05, Curug, Tangerang'
FROM santri s WHERE s.nis = '2026003'
  AND NOT EXISTS (SELECT 1 FROM wali w WHERE w.santri_id = s.id AND w.hubungan = 'ayah');

INSERT INTO wali (santri_id, nama, hubungan, no_wa, alamat)
SELECT s.id, 'Hj. Fatimah Zahra', 'ibu', '081234567804', 'Kp. Saga RT 02/04, Balaraja, Tangerang'
FROM santri s WHERE s.nis = '2026004'
  AND NOT EXISTS (SELECT 1 FROM wali w WHERE w.santri_id = s.id AND w.hubungan = 'ibu');

INSERT INTO wali (santri_id, nama, hubungan, no_wa, alamat)
SELECT s.id, 'Sri Wahyuni', 'ibu', '081234567805', 'Jl. Raya Cisoka KM 3, Cisoka, Tangerang'
FROM santri s WHERE s.nis = '2026005'
  AND NOT EXISTS (SELECT 1 FROM wali w WHERE w.santri_id = s.id AND w.hubungan = 'ibu');

-- ---------------------------------------------------------------------
-- DUMMY: penempatan kamar (3 santri putra -> A-1/A-2, 2 santri putri -> B-1)
-- ---------------------------------------------------------------------
INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai)
SELECT s.id, k.id, '2026-07-14'
FROM santri s
JOIN kamar k ON k.nama = 'A-1'
JOIN asrama a ON a.id = k.asrama_id AND a.nama = 'Asrama Al-Falah'
WHERE s.nis = '2026001'
  AND NOT EXISTS (SELECT 1 FROM penempatan_santri ps WHERE ps.santri_id = s.id AND ps.tgl_selesai IS NULL);

INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai)
SELECT s.id, k.id, '2026-07-14'
FROM santri s
JOIN kamar k ON k.nama = 'A-1'
JOIN asrama a ON a.id = k.asrama_id AND a.nama = 'Asrama Al-Falah'
WHERE s.nis = '2026002'
  AND NOT EXISTS (SELECT 1 FROM penempatan_santri ps WHERE ps.santri_id = s.id AND ps.tgl_selesai IS NULL);

INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai)
SELECT s.id, k.id, '2026-07-14'
FROM santri s
JOIN kamar k ON k.nama = 'A-2'
JOIN asrama a ON a.id = k.asrama_id AND a.nama = 'Asrama Al-Falah'
WHERE s.nis = '2026003'
  AND NOT EXISTS (SELECT 1 FROM penempatan_santri ps WHERE ps.santri_id = s.id AND ps.tgl_selesai IS NULL);

INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai)
SELECT s.id, k.id, '2026-07-14'
FROM santri s
JOIN kamar k ON k.nama = 'B-1'
JOIN asrama a ON a.id = k.asrama_id AND a.nama = 'Asrama An-Nur'
WHERE s.nis = '2026004'
  AND NOT EXISTS (SELECT 1 FROM penempatan_santri ps WHERE ps.santri_id = s.id AND ps.tgl_selesai IS NULL);

INSERT INTO penempatan_santri (santri_id, kamar_id, tgl_mulai)
SELECT s.id, k.id, '2026-07-14'
FROM santri s
JOIN kamar k ON k.nama = 'B-1'
JOIN asrama a ON a.id = k.asrama_id AND a.nama = 'Asrama An-Nur'
WHERE s.nis = '2026005'
  AND NOT EXISTS (SELECT 1 FROM penempatan_santri ps WHERE ps.santri_id = s.id AND ps.tgl_selesai IS NULL);
