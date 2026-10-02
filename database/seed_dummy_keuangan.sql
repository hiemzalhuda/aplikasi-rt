-- ============================================================
-- Data dummy keuangan SI-RT: iuran 6 KK (3 periode) + kas 6 bulan
-- Dijalankan sekali di database produksi (db_santri).
-- Nominal iuran: Rp 50.000 / KK / bulan
-- ============================================================

INSERT INTO iuran (no_kk, periode, jumlah, status, tgl_bayar) VALUES
-- Agustus 2026: semua lunas
('3603010101000001','2026-08',50000,'lunas','2026-08-05'),
('3603010101000002','2026-08',50000,'lunas','2026-08-06'),
('3603010101000003','2026-08',50000,'lunas','2026-08-05'),
('3603010101000004','2026-08',50000,'lunas','2026-08-08'),
('3603010101000005','2026-08',50000,'lunas','2026-08-09'),
('3603010101000006','2026-08',50000,'lunas','2026-08-07'),
-- September 2026: 5 lunas, 1 belum (KK4)
('3603010101000001','2026-09',50000,'lunas','2026-09-04'),
('3603010101000002','2026-09',50000,'lunas','2026-09-06'),
('3603010101000003','2026-09',50000,'lunas','2026-09-05'),
('3603010101000004','2026-09',50000,'belum',NULL),
('3603010101000005','2026-09',50000,'lunas','2026-09-10'),
('3603010101000006','2026-09',50000,'lunas','2026-09-03'),
-- Oktober 2026: 4 lunas, 2 belum (KK4, KK5)
('3603010101000001','2026-10',50000,'lunas','2026-10-02'),
('3603010101000002','2026-10',50000,'lunas','2026-10-01'),
('3603010101000003','2026-10',50000,'lunas','2026-10-02'),
('3603010101000004','2026-10',50000,'belum',NULL),
('3603010101000005','2026-10',50000,'belum',NULL),
('3603010101000006','2026-10',50000,'lunas','2026-10-01');

INSERT INTO kas_transaksi (tanggal, jenis, kategori, keterangan, jumlah, dibuat_oleh) VALUES
-- Mei 2026
('2026-05-05','masuk','Iuran','Iuran warga Mei 2026',300000,1),
('2026-05-10','keluar','Operasional','Operasional pos ronda',150000,1),
('2026-05-18','keluar','Operasional','Kebersihan lingkungan',100000,1),
-- Juni 2026
('2026-06-05','masuk','Iuran','Iuran warga Juni 2026',300000,1),
('2026-06-12','masuk','Dana Sosial','Sumbangan warga untuk kas RT',200000,1),
('2026-06-20','keluar','Operasional','Perbaikan lampu jalan Blok A',450000,1),
('2026-06-25','keluar','Kegiatan','Konsumsi rapat RT',120000,1),
-- Juli 2026
('2026-07-05','masuk','Iuran','Iuran warga Juli 2026',300000,1),
('2026-07-15','keluar','Operasional','Kebersihan lingkungan',100000,1),
('2026-07-22','keluar','Lainnya','ATK sekretariat RT',75000,1),
-- Agustus 2026
('2026-08-05','masuk','Iuran','Iuran warga Agustus 2026',300000,1),
('2026-08-14','keluar','Operasional','Operasional pos ronda',150000,1),
('2026-08-28','keluar','Kegiatan','Konsumsi kerja bakti',100000,1),
-- September 2026
('2026-09-05','masuk','Iuran','Iuran warga September 2026',250000,1),
('2026-09-12','keluar','Operasional','Perbaikan selokan Blok B',300000,1),
('2026-09-20','keluar','Operasional','Kebersihan lingkungan',100000,1),
-- Oktober 2026
('2026-10-02','masuk','Iuran','Iuran warga Oktober 2026 (parsial)',200000,1),
('2026-10-02','keluar','Operasional','Operasional pos ronda',150000,1);
