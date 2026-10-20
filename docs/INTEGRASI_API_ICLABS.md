# Rencana Integrasi API ICLabs

Dokumen kerja branch `staging`. Checklist utama lintas project ada pada `monitoring-asisten/docs/ROADMAP_UI_SIDEBAR_DAN_ICLABS_PRAKTIKUM.md`. Ini mencatat pekerjaan khusus repo Praktikum agar pengalihan berjalan bertahap tanpa mengubah aturan bisnis.

## Kondisi awal yang telah diperiksa

- [x] Branch lokal `staging` dibuat dari `origin/main` (`7ced4dc`) dalam keadaan working tree bersih.
- [x] Aplikasi saat ini PHP native 7.1.11 dan endpoint `api.php` masih memakai cookie `sid`, token CSRF lokal, fungsi query PDO, serta tabel lokal.
- [x] Alur lama yang terlihat mencakup login/session, periode aktif, profil pengguna, nilai mahasiswa, daftar/detail kelas praktikum, simpan kelas/komponen/workflow, sinkron peserta, ekspor laporan, transfer frekuensi, serta route admin.
- [x] Backend API pusat menyediakan baca dan mutasi inti kelas/kelompok, peserta, presensi/nilai, workflow, jadwal/penugasan; frontend native memakai adapter API-only tanpa PDO lokal pada request mode tersebut.
- [x] Dashboard API mode menampilkan agregat kelas/peserta/presensi/nilai/workflow yang dihitung API sesuai ruang akses role, tanpa identitas pribadi mahasiswa.
- [x] UI mode API memiliki form admin membuat/mengubah kelas/kelompok, mengatur jumlah komponen nilai, dan CRUD jadwal/penugasan; ada tindakan sinkron/pindah peserta.
- [ ] Migrasi histori audit legacy, rekonsiliasi penuh seluruh aturan legacy, dan cutover semua route belum selesai. Konfigurasi nilai global, kalkulasi nilai akhir, ekspor laporan, dan import jadwal ke kelompok canonical tersedia. Jangan menganggap aplikasi siap cutover.
- [x] Simpan sesi, presensi dan nilai tetap menggunakan satu endpoint gabungan atomik; UI API-only memuat hanya field presensi/nilai yang diubah agar tidak menimpa data tanpa perubahan.
- [x] Tutup `setup.php` saat mode API aktif sebelum session/PDO lokal dibuka; pembuatan akun dan role Praktikum tetap melalui API/Sumber Daya dan tidak membuat admin duplikat.

Frontend lokal: branch `staging`; implementasi terbaru mencakup CRUD kelas/kelompok, komponen, jadwal, sinkron/pindah peserta dan workflow. Backend API berada di branch `feature/praktikum-read-api`; deploy dan UAT VPS belum dibuktikan.

## Prinsip integrasi yang disepakati

- API/backend ICLabs menjadi satu-satunya sumber akun, profil, role, Mahasiswa, Dosen, Asisten, Program Studi, Mata Kuliah, Laboratorium, periode, kelompok/frekuensi, jadwal, peserta, dan transaksi Praktikum yang telah dipindahkan.
- Akun Praktikum memakai identitas pusat yang sudah ada. Jangan membuat akun lokal kedua untuk NIM, email, dosen, atau asisten yang sama.
- Praktikum hanya mengelola kelompok dan transaksi Praktikum sesuai domainnya; tidak membuat master kelas reguler sebagai pengganti identitas canonical.
- Pertahankan perilaku kelas/frekuensi, sinkronisasi dan perpindahan peserta, presensi, evaluasi, nilai, verifikasi, ekspor, dan audit. Saat endpoint belum tersedia, catat blocker; jangan fallback diam-diam ke database lokal.
- Integrasi awal dilakukan pada `staging`, tanpa menghapus database atau data lama. Legacy menjadi read-only saat tiap modul sudah cutover.

## Checklist implementasi

### A. Audit dan kontrak API

- [x] Inventaris route dan alur utama Praktikum serta pemisahan master canonical/transaksi; ringkasan dukungan dan gap tiap area dicatat pada "Audit cakupan route native dan gap cutover" di bawah.
- [ ] Cocokkan setiap kolom database legacy dan sampel data dengan schema pusat. Project Praktikum tidak menyertakan dump; workspace integrasi memiliki snapshot lebih lama `db/db_praktikum_fikom_2026-10-13_021627.sql` (SHA-256 `12af7b11fd058ee94649d0017b71a1e7326ea9b6f25afcc976ce342816984b45`). Dump yang disetujui untuk audit transaksi adalah snapshot 16 Oktober (SHA-256 `f53d31d91aa781f2a02c9401d0bccec75583110117d50dbf884d091291ae3ba1`) dan belum ada di workspace lokal; jangan gunakan snapshot 13 Oktober sebagai dasar migrasi atau persetujuan field mapping.
- [x] Cocokkan kontrak autentikasi/session pusat dan pemetaan role Admin, Dosen, Asisten, serta Mahasiswa; endpoint pusat membatasi data Mahasiswa ke akun sendiri.
- [x] Implementasikan API inti untuk referensi, kelas/kelompok, jadwal/penugasan, peserta, pertemuan/presensi/nilai, workflow, dashboard, serta audit Praktikum terbatas untuk admin.
- [ ] Selesaikan hanya migrasi histori `log_aktivitas` legacy setelah kebijakan pemetaan actor/waktu disepakati. Akun dan master canonical dikelola melalui Sumber Daya; kelompok, jadwal, roster, nilai, dan workflow Praktikum tersedia di API.
- [x] Tetapkan pagination, filter periode, validasi, optimistic locking/version, dan pemilihan jadwal untuk konsistensi ekspor pada kontrak API serta adapter.
- [x] Otorisasi mutasi dijalankan ulang API berdasarkan role, penugasan, status workflow, dan versi; suite backend menguji penolakan lintas role/scope.

### B. Adapter dan autentikasi aplikasi

- [x] Alihkan dispatcher `api.php` ke adapter HTTP server-side `app/iclabs_api_client.php` yang kompatibel PHP 7.1.11 sebelum bootstrap PDO ketika mode API aktif.
- [x] Tambahkan pembacaan `ICLABS_API_BASE_URL`, timeout, dan TLS verification dari environment di `config.php`. `config.php` mengandalkan environment proses; file `.env` tidak dibaca otomatis oleh PHP native.
- [ ] Atur environment tersebut pada PHP-FPM/Apache staging dan uji health endpoint; jangan menaruh bearer token atau secret dalam JavaScript/log.
- [x] Tukar login lokal ke autentikasi pusat dalam mode opt-in; bearer token hanya berada di session PHP server, `/me` memvalidasi ulang token ke API pusat, logout mencabut session pusat, ganti password diteruskan ke pusat, dan mutasi dilindungi CSRF.
- [x] Alihkan response baca referensi/periode, daftar/detail kelas, dan nilai komponen Mahasiswa ke UI mode API tanpa mengubah aturan domain; response tetap dari API canonical.
- [x] Bedakan data kosong dari error API, sesi 401, penolakan 403, konflik versi, dan validasi; UI tidak mengubah kegagalan API menjadi daftar kosong.

### C. Migrasi bertahap per alur

- [x] Alihkan login, identitas/role canonical, ganti password, dan pembacaan periode. Perubahan akun/profil/master identitas tetap dikelola melalui API/Sumber Daya, bukan diduplikasi di Praktikum.
- [x] Alihkan kelompok/frekuensi dan referensi akademik ke API tanpa menyimpan master canonical ganda di MySQL aplikasi.
- [x] Alihkan jadwal/penugasan dan peserta; pertahankan sinkronisasi additive serta aturan pindah frekuensi dan histori.
- [x] Alihkan pertemuan, presensi, evaluasi/nilai komponen, workflow/verifikasi, serta pembacaan audit Praktikum ke API pusat; operasi penyimpanan sesi/presensi/nilai dilakukan atomik.
- [x] Alihkan konfigurasi nilai akhir, laporan/ekspor, dan import jadwal Praktikum per kelompok setelah validasi mapping legacy; import hanya mengisi kelompok canonical yang sudah ada.
- [x] Alihkan import anggota roster XLSX dengan pratinjau no-write/commit atomik; tidak membuat akun atau master. Histori `log_aktivitas` legacy tetap menunggu keputusan arsip/mapping.
- [x] Alihkan laporan dan ekspor; gunakan periode/kelas/jadwal canonical, aturan kehadiran lama, serta pembatasan role API.
- [x] API-only mode tidak fallback atau menulis ke DB lokal; route yang belum didukung menolak eksplisit dan `setup.php` installer lokal ditutup. DB/data legacy tidak dihapus.

### D. Verifikasi dan cutover staging

- [x] Lengkapi contract test untuk route API-only yang diimplementasikan, otorisasi inti, dan regresi operasi tulis; suite API, mock adapter, PHP/JS lint lulus. Route legacy di luar cakupan sengaja menolak 501.
- [x] Verifikasi ulang lokal 21 Oktober 2026: `bash tests/api_mode_mock_test.sh`, `php -l setup.php`, `php -l app/iclabs_api_mode.php`, `node --check assets/app.js`, dan `git diff --check` lulus. Suite backend pusat pada branch `feature/praktikum-read-api` lulus 255 test / 1.589 assertion dalam container PHP 8.3.
- [ ] Uji Admin, Dosen, Asisten, dan Mahasiswa memakai akun pusat staging; uji pula tidak memiliki role/scope dan token kedaluwarsa.
- [ ] Rekonsiliasi jumlah dan sampel data lama terhadap API untuk satu periode pilot; cek perpindahan mahasiswa tidak menggandakan atau menghapus nilai/presensi.
- [x] Buktikan jalur API-only dapat berjalan tanpa koneksi MySQL lokal: mock memakai nama database yang tidak ada, menolak installer lokal, dan endpoint yang belum dipindahkan fail-closed.
- [x] Dokumentasikan environment PHP-FPM, endpoint, session path, dan panduan UAT. Deploy/rollback dan verifikasi server staging tetap pending.

## Kontrak API Praktikum yang tersedia di branch kerja

Prefix publik VPS adalah `/api`, sehingga URL lengkap memakai
`https://iclabs.fikom.umi.ac.id/api/v1/...`. Semua endpoint berikut perlu Bearer
token hasil login pusat. Saat ini baru ada di branch lokal backend
`feature/praktikum-read-api`; jangan dipakai dari VPS sebelum rilis branch itu.

| Endpoint | Akses dan isi | Status |
| --- | --- | --- |
| `GET /v1/praktikum/ringkasan` | Admin/Dosen/Asisten; agregat kelas dan peserta aktif, progres presensi/nilai, pertemuan tercatat, serta jadwal yang perlu perhatian. Mengikuti scope akses kelas dan tidak mengembalikan NIM/nama. | Implementasi lokal; diuji memakai fixture kelas, peserta, sesi, presensi, nilai, dan workflow. |
| `GET /v1/praktikum/audit?limit=1000` | Hanya `ADMIN_ICLABS`/`SUPER_ADMIN`; maksimal 1.000 audit terbaru untuk entitas transaksi Praktikum. Mengambil nama dari profil canonical, menampilkan aksi serta data sebelum/sesudah, dan tidak mengubah audit lama. Audit non-Praktikum tidak ikut ditampilkan. | Endpoint pusat dan halaman Log Aktivitas API-only tersedia; regresi role dan adapter mock diuji. Riwayat `log_aktivitas` legacy belum diimpor. |
| `GET /v1/praktikum/konfigurasi-nilai` / `PUT /v1/praktikum/konfigurasi-nilai` | Hanya Admin pusat; membaca atau mengubah sembilan ambang global A–E. PUT memerlukan `versi`, semua predikat tepat satu kali, ambang unik 0–100, dan nilai nol. Perubahan ditolak jika jadwal mana pun sudah `TERVERIFIKASI`/`DIKUNCI`; perubahan diaudit. | Migrasi menambah referensi kanonik, seeder mengisi nilai legacy, API dan halaman admin tersedia. Uji update/version, otorisasi, dan lock lulus. |
| `GET /v1/praktikum/referensi` | Admin pusat, Dosen, Asisten, Mahasiswa; periode, prodi, mata kuliah, lab Praktikum, status/pola jadwal. Data picker Dosen/Asisten aktif hanya disertakan untuk admin. | Implementasi lokal; query dan mapping referensi diuji dengan fixture. |
| `GET /v1/praktikum/kelas` | Admin pusat seluruh kelas; Dosen/Asisten hanya kelas dengan penugasan; Mahasiswa hanya kelas dengan peserta aktif miliknya. Query opsional: `id_periode_akademik`, `status`, `per_page` (maksimum 100). | Implementasi lokal dan diuji dengan fixture role/kelas. |
| `GET /v1/praktikum/kelas/{classId}` | Detail kelas yang dapat diakses pemanggil: jadwal, sesi, komponen nilai. Admin/Dosen/Asisten menerima roster peserta aktif beserta kehadiran, nilai komponen, ringkasan nilai akhir, status workflow, dan histori per jadwal; Mahasiswa hanya menerima sesi serta kehadiran/nilai/ringkasan miliknya. | Nilai akhir mengikuti rata-rata EVALUASI × 20%, rata-rata ASISTENSI × 20%, MID × 30%, UAS × 30%; predikat memakai ambang global. Data tidak lengkap menghasilkan nilai akhir/predikat kosong seperti perilaku lama. |
| `GET /v1/praktikum/nilai/saya` | Hanya Mahasiswa; nilai komponen dan ringkasan nilai akhir/predikat untuk kelas milik akun sendiri. | Implementasi API dan UI; suite menguji perhitungan legacy dan batas predikat. |
| `POST /v1/praktikum/kelas` | Hanya `ADMIN_ICLABS`/`SUPER_ADMIN`; membuat kelas berjenis `PRAKTIKUM` dan kelompok terkait memakai ID periode, prodi, dan mata kuliah canonical. Status awal kelas `DRAF`, jumlah pertemuan 1–14. | Implementasi lokal, diuji dengan fixture dan audit log. |
| `PATCH /v1/praktikum/kelas/{classId}` | Hanya admin pusat; ubah kode/nama kelompok, jumlah pertemuan, atau catatan dengan `versi` wajib. Menolak stale version, kode ganda dalam periode/prodi/mata kuliah, jumlah pertemuan di bawah sesi yang sudah tercatat, dan workflow yang sudah maju. | Implementasi lokal, diuji untuk update/version conflict dan null catatan. |
| `POST /v1/praktikum/kelas/{classId}/peserta/sinkronisasi` | Admin pusat atau Asisten yang ditugaskan pada kelas; menambahkan peserta dari anggota kelompok berstatus aktif dan Mahasiswa aktif. Idempotent; tidak menghapus peserta lama dan tidak mengaktifkan ulang status nonaktif. Ditolak jika workflow jadwal sudah diajukan; response mengembalikan `versi` kelompok terbaru. | Implementasi lokal; diuji untuk idempotensi, preservasi peserta mengundurkan diri, dan audit. |
| `GET /v1/praktikum/kelas/{classId}/calon-peserta?cari={nim_atau_nama}` | Hanya Admin pusat; cari minimal 2 karakter. Mengembalikan maksimal 20 Mahasiswa canonical berstatus aktif dalam Program Studi kelas, yang belum memiliki keanggotaan aktif untuk Mata Kuliah dan periode yang sama. Tidak membuat atau mengubah akun/master. | Implementasi API dan adapter frontend selesai; filter role, pencarian, serta flow form diuji dengan fixture. |
| `POST /v1/praktikum/kelas/{classId}/anggota` | Hanya Admin pusat. Payload: `versi`, `id_mahasiswa`, tanggal `tanggal_mulai` wajib eksplisit dan berada dalam periode canonical, `catatan` opsional. Dalam satu transaksi membuat keanggotaan kelompok aktif dan peserta kelas aktif; tidak membuat Mahasiswa/akun, menolak beda Prodi, nonaktif, duplikasi/riwayat, keanggotaan aktif pada frekuensi lain untuk Mata Kuliah-periode yang sama, stale version, atau workflow terkunci. Versi kelompok naik dan audit dicatat. | Implementasi API dan form Admin selesai; API memastikan relasi anggota dan roster tercatat atomik. Tidak memakai waktu import/tanggal default sebagai riwayat. |
| `POST /v1/praktikum/kelas/{classId}/anggota/import` | Hanya Admin pusat; payload `versi`, `anggota` (1–500 baris berisi `nim`, tanggal `tanggal_mulai`, opsional `catatan`), dan `pratinjau`. `pratinjau=true` read-only mengembalikan hasil validasi setiap baris; `false` mengulang validasi dan menulis seluruh batch atomik. NIM harus sudah ada sebagai Mahasiswa canonical aktif dan satu Prodi; tidak membuat akun/master. Menolak NIM duplikat, mahasiswa yang sudah punya keanggotaan/riwayat untuk mata kuliah-periode, tanggal di luar periode, stale version, atau workflow terkunci. Sukses mencatat audit dan menaikkan versi sekali. | UI menyediakan template XLSX dan pratinjau/commit; mock adapter dan uji API mencakup no-write preview, import sukses, dan rollback seluruh batch saat ada baris konflik. Chrome mock mengonfirmasi tombol tambah/import tampil di kartu Roster peserta dan dialog import dapat dibuka. |
| `POST /v1/praktikum/kelas/{classId}/peserta/pindah` | Admin pusat; memindahkan satu Mahasiswa ke kelas Praktikum dengan periode/Prodi/Mata Kuliah yang sama. Payload wajib `versi_sumber`, `versi_tujuan`, `id_kelas_tujuan`, `id_mahasiswa`, `tanggal_efektif`, `alasan`. Menutup keanggotaan asal sehari sebelum tanggal efektif, mempertahankan peserta lama berstatus `DIPINDAHKAN` (presensi/nilai tidak disentuh), membuat relasi peserta/anggota baru, menaikkan dua versi, dan menulis audit. Menolak status/workflow tak dapat diedit, Mahasiswa nonaktif, atau riwayat di kelas tujuan. | Adapter UI Admin mengirim perpindahan dengan kedua versi; mock bridge test lulus. UAT API server belum dilakukan. |
| `PUT /v1/praktikum/kelas/{classId}/pertemuan/{meetingNumber}/kehadiran` | Admin pusat atau Asisten yang ditugaskan; menyimpan tanggal/materi sesi dan batch status kehadiran dalam satu transaksi. Jika kelas memiliki beberapa jadwal, wajib menyertakan `id_jadwal_kelas_kuliah`. Menerima status `HADIR`, `IZIN`, `SAKIT`, `ALPA`, `TERLAMBAT`; `status: null` menghapus catatan kehadiran yang dipilih, mengikuti perilaku simpan lama. Memerlukan `versi` kelompok dan menolak workflow yang sudah diajukan. | Implementasi API tersedia; UI memilih endpoint gabungan atomik di bawah. |
| `PUT /v1/praktikum/kelas/{classId}/perubahan` | Operasi simpan atomik meniru `/classes/{id}/save`. Memerlukan `versi`; dapat membawa objek `pertemuan` (`nomor_pertemuan`, `tanggal_sesi`, `materi`, opsional `id_jadwal_kelas_kuliah`), array `kehadiran` dan/atau array `nilai`. Jika satu bagian gagal validasi, semua bagian dalam permintaan di-rollback. Versi kelompok naik satu kali untuk keseluruhan permintaan. Otorisasi mengikuti admin pusat atau Asisten yang ditugaskan; workflow yang telah diajukan menolak perubahan. | Adapter UI menulis sesi, hanya presensi/nilai yang berubah, dalam satu request; mock bridge dan regresi tanpa PDO lulus. |
| `PUT /v1/praktikum/kelas/{classId}/komponen-nilai` | Admin pusat atau Asisten yang ditugaskan; payload `versi`, `jumlah_evaluasi`, `jumlah_asistensi`. Membentuk `EVALUASI_N` dan `ASISTENSI_N` dengan bobot total masing-masing 20%; `MID` dan `UAS` tetap 30%. Komponen berisi nilai tidak bisa dinonaktifkan. Ditolak bila workflow telah diajukan. | Endpoint API dan form UI Admin tersedia pada branch kerja lokal; konfigurasi produksi/VPS belum diverifikasi. |
| `PUT /v1/praktikum/kelas/{classId}/nilai` | Admin pusat atau Asisten yang ditugaskan; payload `versi` dan array `perubahan` berisi `id_mahasiswa`, `kode_komponen`, `nilai` (0–100 atau `null` untuk menghapus), opsional `catatan`. Semua peserta dan komponen divalidasi sebelum perubahan; stale version ditolak. | Penyimpanan nilai melalui endpoint gabungan atomik tersedia di UI. Endpoint terpisah belum dipakai frontend. |
| `PATCH /v1/praktikum/kelas/{classId}/jadwal/{scheduleId}/workflow` | Admin/Asisten yang ditugaskan dapat mengajukan; Dosen pengampu jadwal dapat memberi `PERLU_REVISI`/`TERVERIFIKASI`, lalu mengunci; Admin dapat mengembalikan status ke `DRAF` dengan alasan. Payload wajib `versi`, `versi_workflow`, `status`; alasan wajib untuk revisi/reset. Pengajuan menolak data tidak lengkap dan semua perubahan masuk histori append-only. | UI menawarkan transisi sesuai role/status dan memakai versi yang dibaca; mock bridge lulus. UAT server belum dilakukan. |
| `POST /v1/praktikum/kelas/{classId}/jadwal` | Hanya admin; membuat jadwal dari ruang lab canonical, pola ulangan, status, tanggal dalam periode, Dosen pengampu dan Asisten utama/opsional pendamping. Menolak personel tanpa master/role aktif atau konflik ruang/pengajar/jadwal kuliah pribadi Asisten. Request wajib `versi`; simpan jadwal, assignment, audit, dan kenaikan versi satu transaksi. | Form UI Admin membuat jadwal/penugasan dan mock bridge lulus; UAT API server belum dilakukan. |
| `PATCH /v1/praktikum/kelas/{classId}/jadwal/{scheduleId}` | Hanya admin; mengubah subset detail jadwal dan/atau assignment dengan `versi` wajib. Menolak jadwal bukan milik kelas, stale version, tanggal/jam tidak sah, konflik, atau workflow yang sudah diajukan. Detail kelas mengembalikan pola, status, dan assignment aktif. | Form UI Admin mengubah jadwal/penugasan dan mock bridge lulus; UAT API server belum dilakukan. |
| `POST /v1/praktikum/jadwal/import` | Hanya Admin; menerima 1–2.000 baris jadwal yang masing-masing menunjuk `id_kelas_kuliah` canonical yang sudah ada, versi kelompok, laboratorium canonical, hari/waktu, batas periode, pola/status, dan ID akun Dosen/Asisten pusat. Semua baris dibuat dalam satu transaksi; satu konflik atau baris invalid membatalkan seluruh batch. Menolak kelompok yang sudah memiliki jadwal agar import tidak menimpa jadwal. | UI API-only menyediakan template XLSX, pratinjau tanpa menulis, lalu commit seluruh batch. Pemetaan jadwal legacy ke kelompok canonical tetap tanggung jawab operator dan import tidak membuat kelas/kelompok baru. Uji mock dan fitur API tersedia; UAT VPS belum dilakukan. |

### Import jadwal dari XLSX (API-only)

Admin dapat mengunduh template, mengunggah file, memeriksa pratinjau, lalu
menjalankan commit setelah hasil pemetaan disetujui. Pratinjau hanya membaca
referensi dan kelas dari API; tidak membuat/mengubah data. Nama mata kuliah,
program studi, periode, frekuensi, laboratorium, dan personel dicocokkan ke ID
canonical dari API. Setiap baris harus mengarah ke kelompok yang telah dibuat
di API; import tidak membuat master atau kelompok baru. Kelompok yang sudah
memiliki jadwal ditolak agar jadwal tidak tertimpa.

Sumber XLSX legacy memuat jadwal mingguan tanpa tanggal mulai/selesai. Adapter
mengirim batas awal/akhir periode akademik canonical dan pola `MINGGUAN`; jangan
menggunakan tanggal import sebagai tanggal historis. Pastikan file dan hasil
pratinjau sesuai periode sebelum commit. Commit mengirim seluruh baris ke
`POST /v1/praktikum/jadwal/import`; API melakukan transaksi all-or-nothing.
Jika satu baris gagal, tidak ada jadwal dari batch yang disimpan. Import ini
tidak memindahkan peserta, nilai, presensi, atau histori.

Route frontend:

- `GET api.php?route=/admin/schedule-template` — unduh template XLSX (Admin).
- `POST api.php?route=/admin/import-schedule` dengan `mode=preview` — validasi
  dan pratinjau, tanpa mutasi.
- Route yang sama dengan `mode=commit` — kirim batch tervalidasi ke API pusat.

Jika frekuensi sumber tidak bisa dipetakan, kelompok canonical belum tersedia,
atau ada jadwal canonical yang sudah ada, hentikan import dan rekonsiliasi
mapping terlebih dahulu. Jangan mengatasi konflik dengan menambah kelompok
duplikat atau menulis ke database lokal.

### Import anggota frekuensi (API-only)

Pada detail kelas/frekuensi, Admin dapat mengunduh template anggota, mengunggah
XLSX, memeriksa NIM/tanggal tanpa menulis, lalu melakukan commit. Header wajib
`nim` dan `tanggal_mulai`; `catatan` opsional. Isi tanggal sebagai teks ISO
`YYYY-MM-DD` di dalam periode canonical. Semua NIM harus sudah menjadi Mahasiswa
aktif canonical dan sesuai Prodi. File tidak boleh digunakan untuk membuat akun
atau master Mahasiswa. Commit mengulang seluruh validasi dalam transaksi dan
membatalkan seluruh batch jika ada satu konflik.

Route frontend `GET api.php?route=/admin/frequency-template` mengunduh template;
`POST api.php?route=/admin/import-frequency` menerima `id_kelas_kuliah`, `versi`,
file `base64`, dan `preview` boolean. Versi yang sama dipakai saat preview dan
commit; bila ada perubahan setelah preview, API menolak stale version dan Admin
harus memuat ulang kelas serta memeriksa file lagi.

Ekspor laporan tidak membaca DB legacy dan tidak menambah salinan data: adapter
API-only memuat `GET /v1/praktikum/kelas/{classId}` menggunakan bearer token
server-side, lalu memakai generator XLSX/PDF yang sama seperti mode lama. Di
browser, tombol ekspor ada per jadwal dan memanggil
`GET api.php?route=/classes/{classId}/export&format=xlsx|pdf&schedule_id={id}`.
API pusat tetap menjadi otoritas scope; roster yang disertakan hanya roster
aktif yang boleh diakses akun tersebut. Mahasiswa ditolak untuk ekspor laporan.
Jika satu kelompok memiliki beberapa jadwal, `schedule_id` wajib dipilih agar
metadata dosen/asisten, ruang, hari, dan waktu laporan tidak ambigu.
Persentase kehadiran memakai jumlah sesi yang benar-benar tercatat (bukan jumlah
pertemuan yang direncanakan), sama seperti implementasi legacy.

Contoh payload pembuatan jadwal (ID user adalah ID akun pusat; jangan mengirim email/NIM sebagai identitas relasi):

```json
{
  "versi": 4,
  "id_laboratorium": 1,
  "hari_ke": 2,
  "jam_mulai": "08:00",
  "jam_selesai": "10:00",
  "tanggal_mulai": "2026-09-07",
  "tanggal_selesai": "2026-12-12",
  "pola_perulangan": "MINGGUAN",
  "status": "AKTIF",
  "id_user_dosen": 20,
  "id_user_asisten_utama": 21,
  "id_user_asisten_pendamping": null
}
```

Gunakan `GET /api/v1/praktikum/referensi` untuk daftar referensi; pilihan
`dosen`/`asisten` hanya tersedia untuk admin. `POST` dan `PATCH` jadwal
mengembalikan `data.id_jadwal_kelas_kuliah` serta `data.versi` kelompok baru.
Setelah sukses, muat ulang detail kelas (`GET /api/v1/praktikum/kelas/{id}`)
agar UI mengambil jadwal, penugasan aktif, dan versi terbaru. PATCH menerima
subset field selain `versi`; untuk menghapus pendamping kirim field pendamping
dengan nilai `null`.

Endpoint kehadiran mengandalkan referensi baru `mst_status_kehadiran_mahasiswa.KODE = TERLAMBAT`.
Di server, endpoint ini belum siap sampai kode API dirilis dan referensi ditambahkan
secara idempotent melalui `php8.3 artisan db:seed --class=ReferenceDataSeeder --force`.
Tidak ada migration schema baru untuk slice ini.

### Contoh simpan satu formulir Praktikum secara atomik

Untuk meniru satu submit lama yang mengubah materi sesi, presensi, dan nilai
sekaligus, kirim seluruh perubahan melalui endpoint gabungan. Jangan pecah
formulir yang sama menjadi beberapa HTTP request karena antarkeduanya tidak
akan berbagi satu transaksi.

```http
PUT /api/v1/praktikum/kelas/10/perubahan
Authorization: Bearer <token-sesi-pusat>
Content-Type: application/json
```

```json
{
  "versi": 4,
  "pertemuan": {
    "nomor_pertemuan": 1,
    "tanggal_sesi": "2026-10-21",
    "materi": "Pengenalan Praktikum",
    "id_jadwal_kelas_kuliah": 21
  },
  "kehadiran": [
    { "id_mahasiswa": 40, "status": "HADIR", "catatan": null },
    { "id_mahasiswa": 41, "status": "TERLAMBAT", "catatan": "Terlambat 10 menit" }
  ],
  "nilai": [
    { "id_mahasiswa": 40, "kode_komponen": "EVALUASI_1", "nilai": 85 },
    { "id_mahasiswa": 41, "kode_komponen": "MID", "nilai": 0 }
  ]
}
```

`pertemuan` dapat dihilangkan untuk simpan nilai saja. `nilai[].nilai: null`
menghapus nilai; `kehadiran[].status: null` menghapus catatan kehadiran sesi
tersebut. `versi` adalah versi kelompok dari detail kelas terakhir yang dibaca;
response mengembalikan versi baru. Pada kegagalan satu bagian, tidak ada bagian
permintaan yang tersimpan. Endpoint tetap memerlukan otorisasi pusat dan belum
dipasang ke route PHP native.

### Contoh workflow verifikasi

```http
PATCH /api/v1/praktikum/kelas/10/jadwal/21/workflow
Authorization: Bearer <token-sesi-pusat>
Content-Type: application/json
```

```json
{
  "versi": 5,
  "versi_workflow": 0,
  "status": "DIAJUKAN"
}
```

Asisten hanya dapat mengajukan jadwal yang memang menugaskannya; Dosen pengampu
yang ditugaskan dapat mengembalikan ke `PERLU_REVISI`, menetapkan
`TERVERIFIKASI`, lalu `DIKUNCI`. Admin pusat dapat mengajukan dan mengembalikan
status ke `DRAF` dengan alasan. Pengajuan otomatis menolak sesi/presensi/nilai
yang belum lengkap. Detail kelas mengembalikan `status_workflow`,
`versi_workflow`, serta histori untuk staf. Setiap transisi menaikkan versi
workflow dan versi kelompok; setelah mutasi, muat ulang detail sebelum mutasi
berikutnya.

## Audit cakupan route native dan gap cutover

Pemetaan berikut berasal dari `api.php`, `app/classes.php`, `app/admin.php`,
`app/frequency.php`, `app/period.php`, dan `app/transfer.php`. Mode default masih
menjalankan database lokal. Mode API opt-in memisahkan dispatcher sebelum
`app/bootstrap.php`, sehingga request API-only tidak membuka koneksi PDO; installer
`setup.php` juga ditutup sebelum bootstrap lokal. Route
yang belum dimigrasikan merespons 501 secara eksplisit dan tidak fallback ke DB.

| Area native | Perilaku yang harus dipertahankan | Dukungan API saat ini / gap |
| --- | --- | --- |
| Login, `/me`, logout, ganti password | Sesi, identitas, role, dan CSRF | Implementasi API-only meneruskan ke endpoint pusat, menyimpan bearer token pada session server, memetakan role pusat ke role UI, dan gagal tertutup jika role tidak didukung. Belum ada UI alur aktivasi/reset password. |
| Periode aktif dan referensi | Prodi, semester, mata kuliah, lab, periode, status | `GET /praktikum/referensi` dipakai untuk membaca periode aktif pada mode API. Pengubahan periode tetap tidak tersedia di Praktikum (periode dikelola pusat). |
| Daftar/detail `/classes` | Ringkasan pertemuan, kehadiran, nilai, indikator monitoring | Adapter memuat daftar berhalaman dan detail canonical. UI menampilkan jadwal/sesi/komponen serta roster yang diizinkan; Mahasiswa hanya melihat data sendiri. |
| Simpan kelas, komponen, workflow | Optimistic version, komponen nilai, aturan verifikasi/dikunci | UI API-only mendukung buat/edit kelompok, atur jumlah komponen, simpan atomik sesi/presensi/nilai dan workflow role-based. Otorisasi final ada di API. |
| Sinkron/pindah peserta | Tambahkan peserta dari anggota aktif tanpa menghapus histori; perpindahan satu mahasiswa ke kelas paralel | UI menyediakan sinkronisasi additive untuk Admin/Asisten dan pemindahan untuk Admin; API tetap memvalidasi versi, periode, prodi, mata kuliah, histori dan tanggal efektif. |
| Jadwal, penugasan, pertemuan, presensi | Penugasan Dosen/Asisten, sesi berurutan, foto/absensi dan audit | Admin dapat membuat/mengedit jadwal/penugasan; penulisan sesi/presensi memakai transaksi gabungan. Jangan gunakan jadwal tugas Monitoring Asisten sebagai pengganti jadwal kelas Praktikum. |
| Nilai, evaluasi, laporan/export | Rumus nilai lama, evaluasi, verifikasi, ekspor XLSX/PDF | UI API-only menampilkan nilai akhir/predikat, dan staf dapat mengunduh laporan XLSX/PDF per jadwal. Data diambil dari detail kelas API pusat yang sudah membatasi scope; generator laporan legacy dipakai ulang tanpa koneksi DB lokal. Mock menguji validitas ZIP/PDF dan penolakan ekspor untuk Mahasiswa. |
| Administrasi, import, transfer, audit | Master peran aplikasi lama, impor jadwal/peserta, transfer frekuensi, audit | Import jadwal tersedia untuk kelompok canonical yang telah ada; Admin dapat mencari/menambah Mahasiswa satu per satu atau mengimpor XLSX ke frekuensi canonical, menyinkronkan roster, dan memindahkan Mahasiswa dengan histori; audit transaksi Praktikum canonical tersedia. Administrasi akun/master canonical tetap dikelola Sumber Daya; migrasi historis `log_aktivitas` belum dialihkan dan tidak dicampur ke audit canonical baru. |

Urutan cutover: autentikasi/session → baca referensi dan daftar/detail →
jadwal/penugasan/pertemuan → peserta dan perpindahan → kehadiran → komponen/nilai
→ workflow/verifikasi → laporan/export → admin/import. Tiap area baru dicentang
setelah kontrak, otorisasi, test regresi terhadap aturan legacy, dan UAT staging
lulus. Jangan mematikan database lokal atau menambahkan dual-write di tengah cutover.

POST/PATCH mengembalikan resource detail kelas. Bentuk response menggunakan
Laravel JSON Resource (`data`; daftar juga
memuat `links`/`meta` pagination). Error mengikuti HTTP `401`, `403`, `404`,
dan `422`. Mode legacy tetap default dan tidak diubah. Jika mode API diaktifkan,
jalur baca yang telah dimigrasikan memakai API pusat; jalur lain gagal tertutup.

Suite backend API di image test `iclabs-test-php83:local` lulus **255 tests / 1.589 assertions**, termasuk pencarian calon Mahasiswa, penolakan duplikasi lintas frekuensi, penambahan/import anggota-roster atomik, import jadwal, dan rollback batch. Frontend lulus `bash tests/api_mode_mock_test.sh`, PHP lint adapter/fixture/setup, dan `node --check assets/app.js`; mock mencakup pratinjau/commit import jadwal serta anggota, ekspor XLSX/PDF, workflow, peserta, nilai Mahasiswa, serta penolakan installer lokal tanpa PDO. Bukti mock bukan UAT VPS maupun regresi seluruh route legacy Praktikum.

Contoh konfigurasi proses PHP-FPM/Apache (native PHP tidak membaca `.env` otomatis):

```text
ICLABS_API_BASE_URL=https://iclabs.fikom.umi.ac.id/api/v1
ICLABS_API_CONNECT_TIMEOUT_SECONDS=3
ICLABS_API_TIMEOUT_SECONDS=10
ICLABS_API_TLS_VERIFY=true
ICLABS_PRAKTIKUM_API_MODE=true
ICLABS_PRAKTIKUM_SESSION_PATH=/var/lib/iclabs-praktikum/sessions
ICLABS_PRAKTIKUM_SECURE_COOKIE=true
```

`ICLABS_PRAKTIKUM_API_MODE` harus tetap `false` sampai seluruh alur yang dibutuhkan
dan UAT staging yang disepakati selesai. Saat `true`, buat direktori session lebih dulu,
batasi aksesnya hanya untuk user PHP-FPM (mode `0700`), dan pastikan direktori
dapat ditulis oleh proses PHP. Jangan gunakan folder publik. Dalam tahap ini
autentikasi, referensi/periode, kelas, jadwal, komponen, tambah/import/sinkron/pindah peserta,
nilai, workflow, laporan XLSX/PDF, serta import jadwal dan anggota roster sudah
terhubung. Pengelolaan akun/master tetap di Sumber Daya; histori audit legacy
belum dimigrasikan. Route Praktikum legacy di luar kontrak ini sengaja mengembalikan
`501` tanpa mengakses database lokal.
`tests/fixtures/iclabs_api_mock_router.php` menyediakan mock kontrak untuk
pengujian adapter lokal; ini bukan pengganti UAT pada API staging.

Tidak diperlukan API key aplikasi untuk endpoint ini. Login memakai identitas akun
pusat, dan bearer token disimpan hanya di session server dengan cookie aman; token
tidak boleh masuk bundle/browser JavaScript atau log. Endpoint aktivasi/reset tetap
memakai mekanisme email pusat.

## Gerbang yang tidak boleh dilewati

1. Jangan ubah frontend ke API endpoint yang belum tercantum dalam kontrak dan implementasi backend.
2. Jangan menjalankan migrasi destruktif, `migrate:fresh`, atau menghapus database legacy.
3. Jangan mengaktifkan fallback baca/tulis ke database lokal ketika API gagal; tampilkan error yang dapat ditindaklanjuti.
4. Jangan menandai modul selesai hanya karena halaman berhasil dirender; mutasi, hak akses, audit, dan rekonsiliasi harus lulus.
