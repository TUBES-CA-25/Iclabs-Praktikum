# Rencana Integrasi API ICLabs

Dokumen kerja branch `staging`. Checklist utama lintas project ada pada `monitoring-asisten/docs/ROADMAP_UI_SIDEBAR_DAN_ICLABS_PRAKTIKUM.md`. Ini mencatat pekerjaan khusus repo Praktikum agar pengalihan berjalan bertahap tanpa mengubah aturan bisnis.

## Kondisi awal yang telah diperiksa

- [x] Branch lokal `staging` dibuat dari `origin/main` (`7ced4dc`) dalam keadaan working tree bersih.
- [x] Aplikasi saat ini PHP native 7.1.11 dan endpoint `api.php` masih memakai cookie `sid`, token CSRF lokal, fungsi query PDO, serta tabel lokal.
- [x] Alur lama yang terlihat mencakup login/session, periode aktif, profil pengguna, nilai mahasiswa, daftar/detail kelas praktikum, simpan kelas/komponen/workflow, sinkron peserta, ekspor laporan, transfer frekuensi, serta route admin.
- [x] API pusat sudah memiliki schema Praktikum tahap P3-A; backend kini mulai memiliki fondasi baca P3-B di branch kerja, tetapi operasi mutasi dan sebagian besar test domain belum tersedia.
- [x] Backend lokal menyediakan API baca, kelas/kelompok, sinkronisasi peserta, kehadiran/nilai, simpan gabungan atomik, workflow, serta CRUD jadwal/penugasan dalam branch terpisah `feature/praktikum-read-api`; belum tersedia di VPS sampai branch ini ditinjau dan dirilis.
- [ ] Pindah peserta sudah tersedia di API branch, tetapi UI belum dipasang. Perubahan status peserta lain, konfigurasi nilai akhir, laporan/ekspor, mutasi transaksi dari UI, dan cutover seluruh aplikasi belum selesai. Jangan menganggap aplikasi siap cutover.
- [x] API menyediakan endpoint atomik gabungan untuk sesi, kehadiran, dan nilai yang meniru transaksi penyimpanan legacy. Mode API frontend kini memiliki halaman baca terpisah; alur tulis legacy belum dialihkan.

Status branch API lokal terakhir saat kontrak ini diperbarui: `feature/praktikum-read-api`, HEAD `24b502c` (termasuk CRUD jadwal, pindah peserta, dan validasi konflik jadwal berulang).
API tersebut belum dapat dipakai staging VPS sampai branch ditinjau dan dirilis.

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
- [x] Cocokkan kontrak autentikasi/session pusat dan pemetaan role Admin, Dosen, Asisten, serta Mahasiswa; endpoint pusat membatasi data Mahasiswa ke akun sendiri.
- [ ] Minta/implementasikan API pusat untuk: referensi akademik; pencarian Mahasiswa/Dosen/Asisten; kelompok Praktikum; jadwal dan penugasan; peserta; pertemuan dan presensi; komponen/evaluasi dan nilai; workflow verifikasi; laporan/ekspor; audit.
- [ ] Tetapkan pagination, filter periode, sort order, bentuk error validasi, optimistic locking/version, serta konsistensi ekspor pada kontrak API.
- [ ] Pastikan semua endpoint mutasi melakukan otorisasi ulang di backend berdasarkan role dan relasi aktual, bukan hanya menu yang disembunyikan di UI.

### B. Adapter dan autentikasi aplikasi

- [x] Buat fondasi adapter HTTP server-side `app/iclabs_api_client.php` yang kompatibel PHP 7.1.11; endpoint belum dialihkan ke adapter.
- [x] Tambahkan pembacaan `ICLABS_API_BASE_URL`, timeout, dan TLS verification dari environment di `config.php`. `config.php` mengandalkan environment proses; file `.env` tidak dibaca otomatis oleh PHP native.
- [ ] Atur environment tersebut pada PHP-FPM/Apache staging dan uji health endpoint; jangan menaruh bearer token atau secret dalam JavaScript/log.
- [x] Tukar login lokal ke autentikasi pusat dalam mode opt-in; bearer token hanya berada di session PHP server, `/me` memvalidasi ulang token ke API pusat, logout mencabut session pusat, ganti password diteruskan ke pusat, dan mutasi dilindungi CSRF.
- [x] Alihkan response baca referensi/periode, daftar/detail kelas, dan nilai komponen Mahasiswa ke UI mode API tanpa mengubah aturan domain; response tetap dari API canonical.
- [ ] Tampilkan keadaan kosong yang sah secara berbeda dari API timeout, 401/403, dan error validasi; jangan menyamarkan gangguan sebagai data kosong.

### C. Migrasi bertahap per alur

- [ ] Alihkan login, profil, role, dan periode setelah kontraknya siap.
- [ ] Alihkan master kelompok/frekuensi dan referensi akademik tanpa menyimpan master canonical ganda di MySQL aplikasi.
- [ ] Alihkan jadwal/penugasan dan peserta; pertahankan urutan sinkronisasi serta aturan pindah frekuensi dan histori.
- [ ] Alihkan pertemuan, presensi, evaluasi, nilai, penguncian/verifikasi, serta audit secara transaksional.
- [ ] Alihkan laporan dan ekspor setelah aturan filter/status/periodenya sama dengan aplikasi lama.
- [ ] Hapus akses tulis lokal per modul hanya setelah UAT modul tersebut lulus; jangan hapus database atau data historis dalam tahap ini.

### D. Verifikasi dan cutover staging

- [ ] Lengkapi contract test seluruh route, authorization per role, dan regression test alur tulis lama. Smoke test mock untuk jalur baca saat ini tersedia di `bash tests/api_mode_mock_test.sh`.
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
| `GET /v1/praktikum/referensi` | Admin pusat, Dosen, Asisten, Mahasiswa; periode, prodi, mata kuliah, lab Praktikum, status/pola jadwal. Data picker Dosen/Asisten aktif hanya disertakan untuk admin. | Implementasi lokal; query dan mapping referensi diuji dengan fixture. |
| `GET /v1/praktikum/kelas` | Admin pusat seluruh kelas; Dosen/Asisten hanya kelas dengan penugasan; Mahasiswa hanya kelas dengan peserta aktif miliknya. Query opsional: `id_periode_akademik`, `status`, `per_page` (maksimum 100). | Implementasi lokal dan diuji dengan fixture role/kelas. |
| `GET /v1/praktikum/kelas/{classId}` | Detail kelas yang dapat diakses pemanggil: jadwal, sesi, komponen nilai. Admin/Dosen/Asisten menerima roster peserta aktif beserta kehadiran, nilai komponen, status workflow dan histori per jadwal; Mahasiswa hanya menerima sesi serta kehadiran/nilai miliknya. | Implementasi lokal; diuji untuk detail kosong dan detail terisi Mahasiswa/staf. Nilai akhir/huruf belum dihitung sampai konfigurasi rentang nilai canonical disepakati. |
| `GET /v1/praktikum/nilai/saya` | Hanya Mahasiswa; nilai komponen mentah dan metadata periode/kelas milik akun sendiri. API belum menghitung nilai akhir karena kontrak konfigurasi/bobot dan rentang nilai masih perlu validasi terhadap aturan lama. | Implementasi lokal; detail fixture nilai masih perlu dilengkapi. |
| `POST /v1/praktikum/kelas` | Hanya `ADMIN_ICLABS`/`SUPER_ADMIN`; membuat kelas berjenis `PRAKTIKUM` dan kelompok terkait memakai ID periode, prodi, dan mata kuliah canonical. Status awal kelas `DRAF`, jumlah pertemuan 1–14. | Implementasi lokal, diuji dengan fixture dan audit log. |
| `PATCH /v1/praktikum/kelas/{classId}` | Hanya admin pusat; ubah kode/nama kelompok, jumlah pertemuan, atau catatan dengan `versi` wajib. Menolak stale version, kode ganda dalam periode/prodi/mata kuliah, jumlah pertemuan di bawah sesi yang sudah tercatat, dan workflow yang sudah maju. | Implementasi lokal, diuji untuk update/version conflict dan null catatan. |
| `POST /v1/praktikum/kelas/{classId}/peserta/sinkronisasi` | Admin pusat atau Asisten yang ditugaskan pada kelas; menambahkan peserta dari anggota kelompok berstatus aktif dan Mahasiswa aktif. Idempotent; tidak menghapus peserta lama dan tidak mengaktifkan ulang status nonaktif. Ditolak jika workflow jadwal sudah diajukan; response mengembalikan `versi` kelompok terbaru. | Implementasi lokal; diuji untuk idempotensi, preservasi peserta mengundurkan diri, dan audit. |
| `POST /v1/praktikum/kelas/{classId}/peserta/pindah` | Admin pusat; memindahkan satu Mahasiswa ke kelas Praktikum dengan periode/Prodi/Mata Kuliah yang sama. Payload wajib `versi_sumber`, `versi_tujuan`, `id_kelas_tujuan`, `id_mahasiswa`, `tanggal_efektif`, `alasan`. Menutup keanggotaan asal sehari sebelum tanggal efektif, mempertahankan peserta lama berstatus `DIPINDAHKAN` (presensi/nilai tidak disentuh), membuat relasi peserta/anggota baru, menaikkan dua versi, dan menulis audit. Menolak status/workflow tak dapat diedit, Mahasiswa nonaktif, atau riwayat di kelas tujuan. | Implementasi lokal dan test preservasi histori lulus. Belum dipasang ke adapter/aplikasi atau dirilis VPS. |
| `PUT /v1/praktikum/kelas/{classId}/pertemuan/{meetingNumber}/kehadiran` | Admin pusat atau Asisten yang ditugaskan; menyimpan tanggal/materi sesi dan batch status kehadiran dalam satu transaksi. Jika kelas memiliki beberapa jadwal, wajib menyertakan `id_jadwal_kelas_kuliah`. Menerima status `HADIR`, `IZIN`, `SAKIT`, `ALPA`, `TERLAMBAT`; `status: null` menghapus catatan kehadiran yang dipilih, mengikuti perilaku simpan lama. Memerlukan `versi` kelompok dan menolak workflow yang sudah diajukan. | Implementasi lokal; diuji untuk atomicity, peserta valid, optimistic version, scope Asisten, audit, dan status `TERLAMBAT`. Belum dipasang ke adapter/aplikasi. |
| `PUT /v1/praktikum/kelas/{classId}/perubahan` | Operasi simpan atomik meniru `/classes/{id}/save`. Memerlukan `versi`; dapat membawa objek `pertemuan` (`nomor_pertemuan`, `tanggal_sesi`, `materi`, opsional `id_jadwal_kelas_kuliah`), array `kehadiran` dan/atau array `nilai`. Jika satu bagian gagal validasi, semua bagian dalam permintaan di-rollback. Versi kelompok naik satu kali untuk keseluruhan permintaan. Otorisasi mengikuti admin pusat atau Asisten yang ditugaskan; workflow yang telah diajukan menolak perubahan. | Implementasi lokal; test membuktikan perubahan sesi/presensi rollback bersama ketika batch nilai invalid. Belum dipasang ke adapter/aplikasi. |
| `PUT /v1/praktikum/kelas/{classId}/komponen-nilai` | Admin pusat atau Asisten yang ditugaskan; payload `versi`, `jumlah_evaluasi`, `jumlah_asistensi`. Membentuk `EVALUASI_N` dan `ASISTENSI_N` dengan bobot total masing-masing 20%; `MID` dan `UAS` tetap 30%. Komponen berisi nilai tidak bisa dinonaktifkan. Ditolak bila workflow telah diajukan. | Implementasi lokal dan lulus test; belum dipasang ke adapter/aplikasi. |
| `PUT /v1/praktikum/kelas/{classId}/nilai` | Admin pusat atau Asisten yang ditugaskan; payload `versi` dan array `perubahan` berisi `id_mahasiswa`, `kode_komponen`, `nilai` (0–100 atau `null` untuk menghapus), opsional `catatan`. Semua peserta dan komponen divalidasi sebelum perubahan; stale version ditolak. | Implementasi lokal dan lulus test; belum dipasang ke adapter/aplikasi. |
| `PATCH /v1/praktikum/kelas/{classId}/jadwal/{scheduleId}/workflow` | Admin/Asisten yang ditugaskan dapat mengajukan; Dosen pengampu jadwal dapat memberi `PERLU_REVISI`/`TERVERIFIKASI`, lalu mengunci; Admin dapat mengembalikan status ke `DRAF` dengan alasan. Payload wajib `versi`, `versi_workflow`, `status`; alasan wajib untuk revisi/reset. Pengajuan menolak data tidak lengkap dan semua perubahan masuk histori append-only. | Implementasi lokal; diuji untuk kelengkapan, transisi role, stale versions, audit/histori, serta pembacaan status/riwayat pada detail. Belum dipasang ke adapter/aplikasi. |
| `POST /v1/praktikum/kelas/{classId}/jadwal` | Hanya admin; membuat jadwal dari ruang lab canonical, pola ulangan, status, tanggal dalam periode, Dosen pengampu dan Asisten utama/opsional pendamping. Menolak personel tanpa master/role aktif atau konflik ruang/pengajar/jadwal kuliah pribadi Asisten. Request wajib `versi`; simpan jadwal, assignment, audit, dan kenaikan versi satu transaksi. | Implementasi lokal pada branch API; test create, tanggal periode, jadwal pribadi Asisten, dan versi lulus. Belum dipasang ke adapter/aplikasi atau dirilis VPS. |
| `PATCH /v1/praktikum/kelas/{classId}/jadwal/{scheduleId}` | Hanya admin; mengubah subset detail jadwal dan/atau assignment dengan `versi` wajib. Menolak jadwal bukan milik kelas, stale version, tanggal/jam tidak sah, konflik, atau workflow yang sudah diajukan. Detail kelas mengembalikan pola, status, dan assignment aktif. | Implementasi lokal pada branch API; test create/update/version dan detail. Belum dipasang ke adapter/aplikasi atau dirilis VPS. |

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
`app/bootstrap.php`, sehingga request API-only tidak membuka koneksi PDO. Route
yang belum dimigrasikan merespons 501 secara eksplisit dan tidak fallback ke DB.

| Area native | Perilaku yang harus dipertahankan | Dukungan API saat ini / gap |
| --- | --- | --- |
| Login, `/me`, logout, ganti password | Sesi, identitas, role, dan CSRF | Implementasi API-only meneruskan ke endpoint pusat, menyimpan bearer token pada session server, memetakan role pusat ke role UI, dan gagal tertutup jika role tidak didukung. Belum ada UI alur aktivasi/reset password. |
| Periode aktif dan referensi | Prodi, semester, mata kuliah, lab, periode, status | `GET /praktikum/referensi` dipakai untuk membaca periode aktif pada mode API. Pengubahan periode tetap tidak tersedia di Praktikum (periode dikelola pusat). |
| Daftar/detail `/classes` | Ringkasan pertemuan, kehadiran, nilai, indikator monitoring | Adapter memuat daftar kelas berhalaman dan detail canonical; UI mode API menampilkan jadwal, sesi, komponen nilai, roster yang diizinkan, dan ringkasan Mahasiswa. Operasi tulis belum dipasang pada UI. |
| Simpan kelas, komponen, workflow | Optimistic version, komponen nilai, aturan verifikasi/dikunci | UI API-only menyimpan sesi, batch presensi, dan nilai melalui endpoint gabungan atomik dengan versi kelompok; UI workflow mendukung pengajuan, revisi, verifikasi, penguncian, dan reset sesuai role/status. Pembuatan/edit kelompok serta konfigurasi jumlah komponen belum dipasang ke UI. |
| Sinkron peserta | Tambah peserta dari anggota frekuensi tanpa duplikasi; jangan menghapus peserta lama | Endpoint additive sinkronisasi dan pindah peserta yang mempertahankan histori tersedia di backend API. UI mode API belum menyediakan tindakan tersebut. |
| Jadwal, penugasan, pertemuan, presensi | Penugasan Dosen/Asisten, sesi berurutan, foto/absensi dan audit | Baca jadwal/sesi dan CRUD jadwal serta penugasan tersedia di backend API; tulis sesi/presensi juga tersedia. Adapter UI baru membaca. Jangan gunakan jadwal tugas Monitoring Asisten sebagai pengganti jadwal kelas Praktikum. |
| Nilai, evaluasi, laporan/export | Rumus nilai lama, evaluasi, verifikasi, ekspor XLSX/PDF | UI API-only dapat menyimpan nilai komponen untuk Admin/Asisten yang berwenang dan menampilkan workflow role-based. Kalkulasi nilai akhir/huruf masih menunggu padanan kanonik `konfigurasi_nilai`; agregat laporan dan ekspor belum tersedia. |
| Administrasi, import, transfer | Master peran aplikasi lama, impor jadwal/peserta, transfer frekuensi, audit | API pindah peserta tersedia, tetapi belum dipasang pada UI; workflow import, administrasi domain, laporan, dan ekspor belum dialihkan ke mode API. Master canonical tidak boleh diduplikasi di database aplikasi. |

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

Suite backend pada container lulus 240 tests / 1.503 assertions; Pint dan
route registration juga lulus. `bash tests/api_mode_mock_test.sh` lulus untuk
login Admin/Mahasiswa, sesi, periode, daftar/detail kelas, simpan atomik
sesi-presensi-nilai, workflow, nilai Mahasiswa, logout, serta penolakan route yang belum
tersedia tanpa PDO. UI daftar/detail dan simpan diperiksa di Chrome headless
dengan mock API. Bukti ini bukan UAT VPS maupun pengujian seluruh route Praktikum.

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
untuk UAT selesai dialihkan. Saat `true`, buat direktori session lebih dulu,
batasi aksesnya hanya untuk user PHP-FPM (mode `0700`), dan pastikan direktori
dapat ditulis oleh proses PHP. Jangan gunakan folder publik. Dalam tahap ini
autentikasi, referensi/periode baca, daftar/detail kelas, nilai komponen
Mahasiswa, simpan gabungan sesi-presensi-nilai, serta transisi workflow
sudah terhubung. Route jadwal, konfigurasi nilai, pindah/sinkron peserta, admin, import,
laporan, dan ekspor yang belum dialihkan sengaja mengembalikan `501` tanpa
mengakses database lokal.
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
