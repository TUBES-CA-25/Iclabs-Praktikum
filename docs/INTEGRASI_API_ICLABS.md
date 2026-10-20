# Rencana Integrasi API ICLabs

Dokumen kerja branch `staging`. Checklist utama lintas project ada pada `monitoring-asisten/docs/ROADMAP_UI_SIDEBAR_DAN_ICLABS_PRAKTIKUM.md`. Ini mencatat pekerjaan khusus repo Praktikum agar pengalihan berjalan bertahap tanpa mengubah aturan bisnis.

## Kondisi awal yang telah diperiksa

- [x] Branch lokal `staging` dibuat dari `origin/main` (`7ced4dc`) dalam keadaan working tree bersih.
- [x] Aplikasi saat ini PHP native 7.1.11 dan endpoint `api.php` masih memakai cookie `sid`, token CSRF lokal, fungsi query PDO, serta tabel lokal.
- [x] Alur lama yang terlihat mencakup login/session, periode aktif, profil pengguna, nilai mahasiswa, daftar/detail kelas praktikum, simpan kelas/komponen/workflow, sinkron peserta, ekspor laporan, transfer frekuensi, serta route admin.
- [x] API pusat sudah memiliki schema Praktikum tahap P3-A; model, policy, service, request, resource, dan Feature Test P3-B masih belum tersedia menurut `backend-iclabs/api/docs/PRAKTIKUM_IMPLEMENTATION_PLAN.md`.
- [x] Pada checkout API saat audit tidak ditemukan route `v1/praktikum`; kontrak endpoint belum tersedia. Jangan mulai mengganti call site dengan URL rekaan.

## Prinsip integrasi yang disepakati

- API/backend ICLabs menjadi satu-satunya sumber akun, profil, role, Mahasiswa, Dosen, Asisten, Program Studi, Mata Kuliah, Laboratorium, periode, kelompok/frekuensi, jadwal, peserta, dan transaksi Praktikum yang telah dipindahkan.
- Akun Praktikum memakai identitas pusat yang sudah ada. Jangan membuat akun lokal kedua untuk NIM, email, dosen, atau asisten yang sama.
- Praktikum hanya mengelola kelompok dan transaksi Praktikum sesuai domainnya; tidak membuat master kelas reguler sebagai pengganti identitas canonical.
- Pertahankan perilaku kelas/frekuensi, sinkronisasi dan perpindahan peserta, presensi, evaluasi, nilai, verifikasi, ekspor, dan audit. Saat endpoint belum tersedia, catat blocker; jangan fallback diam-diam ke database lokal.
- Integrasi awal dilakukan pada `staging`, tanpa menghapus database atau data lama. Legacy menjadi read-only saat tiap modul sudah cutover.

## Checklist implementasi

### A. Audit dan kontrak API

- [ ] Inventaris seluruh halaman, query PDO, form, import/export, proses cron, dan relasi tabel; bedakan master canonical dari transaksi Praktikum.
- [ ] Cocokkan setiap field legacy dengan schema pusat; catat transformasi, data yang tidak punya padanan, dan keputusan yang memerlukan pemilik proses.
- [ ] Sepakati kontrak autentikasi/session pusat untuk Admin, Dosen, Asisten, dan Mahasiswa; akses Mahasiswa harus terbatas pada datanya sendiri.
- [ ] Minta/implementasikan API pusat untuk: referensi akademik; pencarian Mahasiswa/Dosen/Asisten; kelompok Praktikum; jadwal dan penugasan; peserta; pertemuan dan presensi; komponen/evaluasi dan nilai; workflow verifikasi; laporan/ekspor; audit.
- [ ] Tetapkan pagination, filter periode, sort order, bentuk error validasi, optimistic locking/version, serta konsistensi ekspor pada kontrak API.
- [ ] Pastikan semua endpoint mutasi melakukan otorisasi ulang di backend berdasarkan role dan relasi aktual, bukan hanya menu yang disembunyikan di UI.

### B. Adapter dan autentikasi aplikasi

- [ ] Buat adapter HTTP server-side yang kompatibel PHP 7.1.11; hindari syntax/API PHP yang lebih baru dari runtime project.
- [ ] Simpan base URL, timeout, TLS verification, dan konfigurasi environment di luar Git; jangan menaruh secret/token dalam JavaScript atau log.
- [ ] Tukar login lokal ke autentikasi pusat dan simpan bearer token hanya pada session server yang aman; terapkan expiry, logout/revocation, CSRF, dan cookie aman.
- [ ] Normalisasi response API pada adapter tanpa mengubah aturan bisnis/controller lebih dari yang diperlukan.
- [ ] Tampilkan keadaan kosong yang sah secara berbeda dari API timeout, 401/403, dan error validasi; jangan menyamarkan gangguan sebagai data kosong.

### C. Migrasi bertahap per alur

- [ ] Alihkan login, profil, role, dan periode setelah kontraknya siap.
- [ ] Alihkan master kelompok/frekuensi dan referensi akademik tanpa menyimpan master canonical ganda di MySQL aplikasi.
- [ ] Alihkan jadwal/penugasan dan peserta; pertahankan urutan sinkronisasi serta aturan pindah frekuensi dan histori.
- [ ] Alihkan pertemuan, presensi, evaluasi, nilai, penguncian/verifikasi, serta audit secara transaksional.
- [ ] Alihkan laporan dan ekspor setelah aturan filter/status/periodenya sama dengan aplikasi lama.
- [ ] Hapus akses tulis lokal per modul hanya setelah UAT modul tersebut lulus; jangan hapus database atau data historis dalam tahap ini.

### D. Verifikasi dan cutover staging

- [ ] Tambahkan unit/contract test adapter, test authorization per role, serta regression test alur lama.
- [ ] Uji Admin, Dosen, Asisten, dan Mahasiswa memakai akun pusat staging; uji pula tidak memiliki role/scope dan token kedaluwarsa.
- [ ] Rekonsiliasi jumlah dan sampel data lama terhadap API untuk satu periode pilot; cek perpindahan mahasiswa tidak menggandakan atau menghapus nilai/presensi.
- [ ] Buktikan aplikasi dapat berjalan tanpa koneksi MySQL lokal hanya setelah seluruh route pada cakupan cutover memakai API.
- [ ] Lengkapi dokumentasi `.env`, endpoint, deploy/rollback, dan checklist UAT sebelum meminta pull ke server.

## Gerbang yang tidak boleh dilewati

1. Jangan ubah frontend ke API endpoint yang belum tercantum dalam kontrak dan implementasi backend.
2. Jangan menjalankan migrasi destruktif, `migrate:fresh`, atau menghapus database legacy.
3. Jangan mengaktifkan fallback baca/tulis ke database lokal ketika API gagal; tampilkan error yang dapat ditindaklanjuti.
4. Jangan menandai modul selesai hanya karena halaman berhasil dirender; mutasi, hak akses, audit, dan rekonsiliasi harus lulus.
