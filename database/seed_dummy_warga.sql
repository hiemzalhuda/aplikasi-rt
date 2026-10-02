-- ============================================================
-- Data dummy SI-RT: 19 warga (6 KK) + 3 pengumuman + 2 kegiatan
-- Dijalankan sekali di database produksi (db_santri).
-- ============================================================

INSERT INTO warga (nik, no_kk, nama, tempat_lahir, tgl_lahir, jk, agama, pendidikan, pekerjaan, status_kawin, hubungan, alamat, no_hp, status_tinggal, keterangan) VALUES
-- KK 1
('3603011501800001','3603010101000001','Budi Hartono','Tangerang','1980-01-15','L','Islam','SMA','Wiraswasta','Kawin','KEPALA KELUARGA','Jl. Harmoni Raya Blok A1 No. 5, Grand Harmoni 2, Balaraja','081212340001','TETAP',NULL),
('3603012005820002','3603010101000001','Siti Aminah','Tangerang','1982-05-20','P','Islam','SMA','Ibu Rumah Tangga','Kawin','ISTRI','Jl. Harmoni Raya Blok A1 No. 5, Grand Harmoni 2, Balaraja','081212340002','TETAP',NULL),
('3603011008050003','3603010101000001','Andi Pratama','Jakarta','2005-08-10','L','Islam','SMA','Pelajar/Mahasiswa','Belum Kawin','ANAK','Jl. Harmoni Raya Blok A1 No. 5, Grand Harmoni 2, Balaraja','081212340003','TETAP',NULL),
('3603012503080004','3603010101000001','Dewi Lestari','Tangerang','2008-03-25','P','Islam','SMP','Pelajar/Mahasiswa','Belum Kawin','ANAK','Jl. Harmoni Raya Blok A1 No. 5, Grand Harmoni 2, Balaraja',NULL,'TETAP',NULL),
-- KK 2
('3603010211780005','3603010101000002','Agus Setiawan','Bogor','1978-11-02','L','Islam','S1','Karyawan Swasta','Kawin','KEPALA KELUARGA','Jl. Harmoni Raya Blok A2 No. 12, Grand Harmoni 2, Balaraja','081212340005','TETAP',NULL),
('3603011407850006','3603010101000002','Rina Wulandari','Tangerang','1985-07-14','P','Islam','D3','Karyawan Swasta','Kawin','ISTRI','Jl. Harmoni Raya Blok A2 No. 12, Grand Harmoni 2, Balaraja','081212340006','TETAP',NULL),
('3603010112100007','3603010101000002','Fajar Nugroho','Tangerang','2010-12-01','L','Islam','SD','Pelajar/Mahasiswa','Belum Kawin','ANAK','Jl. Harmoni Raya Blok A2 No. 12, Grand Harmoni 2, Balaraja',NULL,'TETAP',NULL),
-- KK 3
('3603011704600008','3603010101000003','H. Mahmud','Serang','1960-04-17','L','Islam','SMA','Pensiunan','Kawin','KEPALA KELUARGA','Jl. Harmoni Indah Blok B1 No. 3, Grand Harmoni 2, Balaraja','081212340008','TETAP',NULL),
('3603010909650009','3603010101000003','Hj. Fatimah','Serang','1965-09-09','P','Islam','SMA','Ibu Rumah Tangga','Kawin','ISTRI','Jl. Harmoni Indah Blok B1 No. 3, Grand Harmoni 2, Balaraja','081212340009','TETAP',NULL),
-- KK 4
('3603012802830010','3603010101000004','Joko Susilo','Solo','1983-02-28','L','Islam','SMA','Buruh','Kawin','KEPALA KELUARGA','Jl. Harmoni Indah Blok B2 No. 8, Grand Harmoni 2, Balaraja','081212340010','TETAP',NULL),
('3603011106860011','3603010101000004','Maya Putri','Tangerang','1986-06-11','P','Kristen','SMA','Ibu Rumah Tangga','Kawin','ISTRI','Jl. Harmoni Indah Blok B2 No. 8, Grand Harmoni 2, Balaraja','081212340011','TETAP',NULL),
('3603011309070012','3603010101000004','Rizky Ramadhan','Tangerang','2007-09-13','L','Islam','SMP','Pelajar/Mahasiswa','Belum Kawin','ANAK','Jl. Harmoni Indah Blok B2 No. 8, Grand Harmoni 2, Balaraja',NULL,'TETAP',NULL),
('3603013001120013','3603010101000004','Putri Ayu','Tangerang','2012-01-30','P','Kristen','SD','Pelajar/Mahasiswa','Belum Kawin','ANAK','Jl. Harmoni Indah Blok B2 No. 8, Grand Harmoni 2, Balaraja',NULL,'TETAP',NULL),
('3603010510950014','3603010101000004','Dedi Kurniawan','Bandung','1995-10-05','L','Islam','SMA','Karyawan Swasta','Belum Kawin','FAMILI LAIN','Jl. Harmoni Indah Blok B2 No. 8, Grand Harmoni 2, Balaraja','081212340014','TETAP','Keponakan kepala keluarga'),
-- KK 5
('3603012212750015','3603010101000005','Slamet Riyadi','Yogyakarta','1975-12-22','L','Islam','SMA','Wiraswasta','Kawin','KEPALA KELUARGA','Jl. Harmoni Asri Blok C1 No. 2, Grand Harmoni 2, Balaraja','081212340015','KONTRAK',NULL),
('3603010808790016','3603010101000005','Nur Halimah','Yogyakarta','1979-08-08','P','Islam','SMA','Ibu Rumah Tangga','Kawin','ISTRI','Jl. Harmoni Asri Blok C1 No. 2, Grand Harmoni 2, Balaraja','081212340016','KONTRAK',NULL),
('3603011905030017','3603010101000005','Bambang Sutrisno','Tangerang','2003-05-19','L','Islam','SMK','Karyawan Swasta','Belum Kawin','ANAK','Jl. Harmoni Asri Blok C1 No. 2, Grand Harmoni 2, Balaraja','081212340017','KONTRAK',NULL),
-- KK 6
('3603010303900018','3603010101000006','Wahyu Hidayat','Tangerang','1990-03-03','L','Islam','S1','Karyawan Swasta','Kawin','KEPALA KELUARGA','Jl. Harmoni Asri Blok C2 No. 9, Grand Harmoni 2, Balaraja','081212340018','TETAP',NULL),
('3603012711920019','3603010101000006','Sari Melati','Tangerang','1992-11-27','P','Islam','S1','Karyawan Swasta','Kawin','ISTRI','Jl. Harmoni Asri Blok C2 No. 9, Grand Harmoni 2, Balaraja','081212340019','TETAP',NULL);

INSERT INTO pengumuman (judul, isi, tanggal, aktif, dibuat_oleh) VALUES
('Jadwal Ronda Malam Minggu','Diberitahukan kepada seluruh warga RT 01 bahwa jadwal ronda malam minggu ini dilaksanakan seperti biasa mulai pukul 22.00 WIB di Pos Ronda Blok A. Dimohon kehadiran bapak-bapak sesuai daftar regu. Atas perhatiannya kami ucapkan terima kasih.','2026-10-02',1,1),
('Pembayaran Iuran Bulan Oktober 2026','Iuran warga bulan Oktober 2026 sudah dapat dibayarkan melalui bendahara RT atau transfer. Bagi yang belum melunasi iuran bulan lalu, dimohon segera diselesaikan. Terima kasih atas partisipasinya.','2026-10-01',1,1),
('Kerja Bakti Rutin Hari Minggu','Mengundang seluruh warga untuk mengikuti kerja bakti rutin membersihkan selokan dan jalan lingkungan pada hari Minggu pukul 07.00 WIB. Titik kumpul di depan Pos Ronda Blok A.','2026-09-28',1,1);

INSERT INTO kegiatan (nama, tanggal, waktu, tempat, deskripsi) VALUES
('Ronda Malam Regu 2','2026-10-03','22:00','Pos Ronda Blok A','Ronda malam rutin regu 2: Bpk. Agus Setiawan, Bpk. Joko Susilo, Bpk. Wahyu Hidayat.'),
('Kerja Bakti Lingkungan','2026-10-04','07:00','Jl. Harmoni Raya','Kerja bakti rutin: pembersihan selokan dan jalan lingkungan.');
