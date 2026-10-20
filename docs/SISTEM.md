# Analisis dan rancangan sistem

## Kebutuhan dan aktor

Admin mengelola master akademik, akun, peserta, penugasan, konfigurasi huruf, dan pembukaan kunci. Asisten 1/2 mengisi absensi dan nilai hanya di jadwal yang ditugaskan. Dosen memeriksa kelas yang diampu, mengembalikan dengan alasan, memverifikasi, dan mengunci. Role staf dapat melihat dan mengekspor kelas dalam kewenangannya. Mahasiswa login melalui akun yang dihubungkan admin ke satu NIM untuk melihat hanya nilai serta kehadirannya sendiri melalui Nilai Saya. Mahasiswa tidak memiliki akses ekspor kelas, audit staf, atau pengeditan.

## Use case diagram

```mermaid
flowchart LR
  Admin[Admin Laboratorium] --> Master([Kelola master dan akun])
  Mahasiswa[Mahasiswa] --> NilaiSaya([Lihat nilai dan kehadiran sendiri])
  Admin --> Peserta([Import dan sinkron peserta])
  Admin --> Buka([Buka kembali dengan alasan])
  A1[Asisten 1] --> Input([Isi absensi dan nilai])
  A2[Asisten 2] --> Input
  A1 --> Ajukan([Ajukan penilaian])
  A2 --> Ajukan
  Dosen[Dosen pengampu] --> Cek([Cross-check dan histori])
  Dosen --> Revisi([Minta revisi dengan alasan])
  Dosen --> Setuju([Verifikasi dan kunci])
  Admin --> Laporan([Lihat dan ekspor rekap])
  A1 --> Laporan
  A2 --> Laporan
  Dosen --> Laporan
  Input --> Audit([Catat sebelum sesudah dan pengguna])
  Setuju --> Audit
  Revisi --> Audit
  Buka --> Audit
```

## Activity diagram

```mermaid
flowchart TD
  Login[Login] --> Role{Role dan penugasan valid?}
  Role -- Tidak --> Tolak[Tolak akses]
  Role -- Ya --> Kelas[Buka kelas]
  Kelas --> Input[Asisten mengisi data]
  Input --> Valid{Nilai dan versi valid?}
  Valid -- Tidak --> Error[Tampilkan validasi atau konflik]
  Error --> Input
  Valid -- Ya --> Simpan[Transaksi simpan dan audit]
  Simpan --> Lengkap{Seluruh target absensi dan nilai lengkap?}
  Lengkap -- Tidak --> Input
  Lengkap -- Ya --> Ajukan[Diajukan]
  Ajukan --> Cek[Dosen cross-check]
  Cek --> Keputusan{Disetujui?}
  Keputusan -- Tidak --> Revisi[Perlu Revisi dengan alasan]
  Revisi --> Input
  Keputusan -- Ya --> Verifikasi[Terverifikasi]
  Verifikasi --> Kunci[Dikunci]
  Kunci --> Buka{Pembukaan resmi admin?}
  Buka -- Ya --> Draft[Draft dengan audit alasan]
  Draft --> Input
  Buka -- Tidak --> Rekap[Rekap dan ekspor]
```

## ERD

```mermaid
erDiagram
  roles ||--o{ users : memiliki
  users ||--o{ sessions : login
  users ||--o| dosen : profil
  users ||--o| asisten : profil
  users ||--o| akun_mahasiswa : akun
  mahasiswa ||--o| akun_mahasiswa : identitas_login
  users ||--o{ log_aktivitas : mencatat
  prodi ||--o{ kelas : menaungi
  prodi ||--o{ mahasiswa : menaungi
  kelas ||--o{ mahasiswa : beranggotakan
  tahun_akademik ||--o{ jadwal_praktikum : periode
  semester ||--o{ jadwal_praktikum : periode
  mata_kuliah ||--o{ jadwal_praktikum : dijadwalkan
  kelas ||--o{ jadwal_praktikum : mengikuti
  laboratorium ||--o{ jadwal_praktikum : lokasi
  dosen ||--o{ jadwal_praktikum : mengampu
  asisten ||--o{ jadwal_praktikum : mendampingi
  jadwal_praktikum ||--o{ peserta_praktikum : peserta
  mahasiswa ||--o{ peserta_praktikum : mengikuti
  jadwal_praktikum ||--o{ pertemuan_praktikum : pertemuan
  pertemuan_praktikum ||--o{ absensi : mencatat
  peserta_praktikum ||--o{ absensi : kehadiran
  evaluasi ||--o{ nilai_evaluasi : komponen
  peserta_praktikum ||--o{ nilai_evaluasi : evaluasi
  peserta_praktikum ||--o{ nilai_asistensi : asistensi
  peserta_praktikum ||--o{ nilai_ujian : ujian
  peserta_praktikum ||--|| nilai_akhir : view
  jadwal_praktikum ||--o{ verifikasi_nilai : workflow
  users ||--o{ verifikasi_nilai : melakukan
  jadwal_praktikum ||--o{ log_aktivitas : histori
```

`nilai_akhir` merupakan view komponen, lalu pembobotan dihitung server untuk menghindari duplikasi nilai turunan. `konfigurasi_nilai` berisi batas bawah huruf inklusif dengan nilai unik, termasuk batas nol. Tidak ada rentang hardcoded pada fungsi kalkulasi; data awal di-seed satu kali. Asisten 1 dan 2 adalah dua FK berbeda pada jadwal.

## Integritas dan konkurensi

Unik: email, NIM, kode MK, pasangan jadwal/mahasiswa, jadwal/nomor pertemuan, peserta/komponen. FK mencegah referensi yatim. Validasi API membatasi maksimal 14 pertemuan, evaluasi/asistensi dinamis, nilai 0–100, jenis ujian, status absensi, dan penugasan asisten berbeda. API memastikan peserta dan pertemuan berasal dari jadwal sama. Setiap penyimpanan memeriksa versi jadwal dan menaikkan versi. Transaksi menggunakan MySQL InnoDB dan SELECT FOR UPDATE pada jadwal; audit ikut rollback bila input tidak valid. Trigger menolak pengubahan dan penghapusan catatan audit.

## Pemetaan implementasi

| Kebutuhan | Implementasi |
| --- | --- |
| Login dan hak akses | Session server, role, penugasan kelas pada setiap endpoint |
| Dashboard | Ringkasan kelas/peserta, progres, kebutuhan input/revisi/verifikasi |
| Master akademik | Form tambah/edit relasional; aktivasi jadwal dan akun |
| Absensi | maksimal 14 pertemuan, tanggal/materi, H/I/S/A/T, catatan, hadirkan semua |
| Evaluasi dan ujian | evaluasi/asistensi dinamis, MID/UAS; tombol simpan dan validasi |
| Cross-check | Rekap, detail mahasiswa, audit sebelum/sesudah |
| Verifikasi | Draft, Diajukan, Perlu Revisi, Terverifikasi, Dikunci |
| Laporan | Pencarian/filter, detail kelas, Excel lengkap, PDF rekap, print |
| Audit | Identitas, role, waktu UTC, IP, nilai lama/baru |
| UI | Sidebar, tabel sticky, scroll horizontal, breakpoint mobile |

Audit menyimpan waktu UTC, UI menampilkan zona waktu perangkat. Data awal adalah contoh untuk orientasi penggunaan, bukan arsip resmi. Kebutuhan kebijakan kampus yang belum diberikan (misalnya perlakuan terlambat dan pembatalan peserta historis) dinyatakan secara eksplisit dalam README.
