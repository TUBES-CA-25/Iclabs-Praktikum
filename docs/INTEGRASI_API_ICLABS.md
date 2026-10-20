# Rencana Integrasi API ICLabs

Dokumen kerja branch `staging`. Checklist utama lintas project ada pada `monitoring-asisten/docs/ROADMAP_UI_SIDEBAR_DAN_ICLABS_PRAKTIKUM.md`. Ini mencatat pekerjaan khusus repo Praktikum agar pengalihan berjalan bertahap tanpa mengubah aturan bisnis.

## Kondisi awal yang telah diperiksa

- [x] Branch lokal `staging` dibuat dari `origin/main` (`7ced4dc`) dalam keadaan working tree bersih.
- [x] Aplikasi saat ini PHP native 7.1.11 dan endpoint `api.php` masih memakai cookie `sid`, token CSRF lokal, fungsi query PDO, serta tabel lokal.
- [x] Alur lama yang terlihat mencakup login/session, periode aktif, profil pengguna, nilai mahasiswa, daftar/detail kelas praktikum, simpan kelas/komponen/workflow, sinkron peserta, ekspor laporan, transfer frekuensi, serta route admin.
- [x] API pusat sudah memiliki schema Praktikum tahap P3-A; backend kini mulai memiliki fondasi baca P3-B di branch kerja, tetapi operasi mutasi dan sebagian besar test domain belum tersedia.
- [x] Backend lokal menyediakan empat GET, create/update identitas kelas/kelompok, dan sinkronisasi peserta pada branch terpisah `feature/praktikum-read-api`, HEAD `2e95cc7`; belum tersedia di VPS sampai branch ini ditinjau, digabungkan, dan dirilis.
- [ ] Mutasi jadwal, pengajar, pengelolaan/perpindahan peserta, presensi, nilai, workflow, dan laporan belum tersedia. Jangan menganggap aplikasi siap cutover.

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

- [x] Buat fondasi adapter HTTP server-side `app/iclabs_api_client.php` yang kompatibel PHP 7.1.11; endpoint belum dialihkan ke adapter.
- [x] Tambahkan pembacaan `ICLABS_API_BASE_URL`, timeout, dan TLS verification dari environment di `config.php`. `config.php` mengandalkan environment proses; file `.env` tidak dibaca otomatis oleh PHP native.
- [ ] Atur environment tersebut pada PHP-FPM/Apache staging dan uji health endpoint; jangan menaruh bearer token atau secret dalam JavaScript/log.
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

## Kontrak API Praktikum yang tersedia di branch kerja

Prefix publik VPS adalah `/api`, sehingga URL lengkap memakai
`https://iclabs.fikom.umi.ac.id/api/v1/...`. Semua endpoint berikut perlu Bearer
token hasil login pusat. Saat ini baru ada di branch lokal backend
`feature/praktikum-read-api`; jangan dipakai dari VPS sebelum rilis branch itu.

| Endpoint | Akses dan isi | Status |
| --- | --- | --- |
| `GET /v1/praktikum/referensi` | Admin pusat, Dosen, Asisten, Mahasiswa; periode, prodi, mata kuliah, lab Praktikum, dan status referensi. | Implementasi lokal; query dan mapping referensi diuji dengan fixture. Health API pusat merespons HTTP 200 dari host lokal. |
| `GET /v1/praktikum/kelas` | Admin pusat seluruh kelas; Dosen/Asisten hanya kelas dengan penugasan; Mahasiswa hanya kelas dengan peserta aktif miliknya. Query opsional: `id_periode_akademik`, `status`, `per_page` (maksimum 100). | Implementasi lokal dan diuji dengan fixture role/kelas. |
| `GET /v1/praktikum/kelas/{classId}` | Detail kelas yang dapat diakses pemanggil. Data peserta disembunyikan untuk role Mahasiswa. | Implementasi lokal; detail kosong diuji, detail dengan jadwal dan peserta belum diuji. |
| `GET /v1/praktikum/nilai/saya` | Hanya Mahasiswa; nilai komponen mentah dan metadata periode/kelas milik akun sendiri. API belum menghitung nilai akhir karena kontrak konfigurasi/bobot dan rentang nilai masih perlu validasi terhadap aturan lama. | Implementasi lokal; detail fixture nilai masih perlu dilengkapi. |
| `POST /v1/praktikum/kelas` | Hanya `ADMIN_ICLABS`/`SUPER_ADMIN`; membuat kelas berjenis `PRAKTIKUM` dan kelompok terkait memakai ID periode, prodi, dan mata kuliah canonical. Status awal kelas `DRAF`, jumlah pertemuan 1–14. | Implementasi lokal, diuji dengan fixture dan audit log. |
| `PATCH /v1/praktikum/kelas/{classId}` | Hanya admin pusat; ubah kode/nama kelompok, jumlah pertemuan, atau catatan dengan `versi` wajib. Menolak stale version, kode ganda dalam periode/prodi/mata kuliah, jumlah pertemuan di bawah sesi yang sudah tercatat, dan workflow yang sudah maju. | Implementasi lokal, diuji untuk update/version conflict dan null catatan. |
| `POST /v1/praktikum/kelas/{classId}/peserta/sinkronisasi` | Admin pusat atau Asisten yang ditugaskan pada kelas; menambahkan peserta dari anggota kelompok berstatus aktif dan Mahasiswa aktif. Idempotent; tidak menghapus peserta lama dan tidak mengaktifkan ulang status nonaktif. Ditolak jika workflow jadwal sudah diajukan. | Implementasi lokal; diuji untuk idempotensi, preservasi peserta mengundurkan diri, dan audit. |

POST/PATCH mengembalikan resource detail kelas. Bentuk response menggunakan
Laravel JSON Resource (`data`; daftar juga
memuat `links`/`meta` pagination). Error mengikuti HTTP `401`, `403`, `404`,
dan `422`. Pada tahap ini aplikasi legacy tetap berjalan seperti sebelumnya;
tidak ada akses atau perubahan database yang dialihkan ke API.

Suite backend pada container lulus 225 tests / 1.401 assertions; Pint dan
route registration juga lulus. Tes ini bukan UAT VPS maupun pengujian seluruh
route Praktikum.

Contoh konfigurasi proses PHP (bukan isi file `.env` yang dibaca otomatis):

```text
ICLABS_API_BASE_URL=https://iclabs.fikom.umi.ac.id/api/v1
ICLABS_API_CONNECT_TIMEOUT_SECONDS=3
ICLABS_API_TIMEOUT_SECONDS=10
ICLABS_API_TLS_VERIFY=true
```

Kredensial aplikasi belum diperlukan untuk endpoint baca ini: setiap permintaan
terproteksi menggunakan Bearer token akun pusat. Setelah alur login dipindahkan,
token harus disimpan di session server dengan cookie aman; jangan pernah
menyisipkannya pada bundle/browser JavaScript. Endpoint aktivasi/reset tetap
memakai mekanisme email pusat.

## Gerbang yang tidak boleh dilewati

1. Jangan ubah frontend ke API endpoint yang belum tercantum dalam kontrak dan implementasi backend.
2. Jangan menjalankan migrasi destruktif, `migrate:fresh`, atau menghapus database legacy.
3. Jangan mengaktifkan fallback baca/tulis ke database lokal ketika API gagal; tampilkan error yang dapat ditindaklanjuti.
4. Jangan menandai modul selesai hanya karena halaman berhasil dirender; mutasi, hak akses, audit, dan rekonsiliasi harus lulus.
