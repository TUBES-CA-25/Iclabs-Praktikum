# Sinkronisasi Jadwal Google Sheets

## Alur

Halaman **Sinkron Jadwal** mengirim URL Sheet ke endpoint PHP aplikasi
Praktikum. Server Praktikum mengambil CSV publik, mencari baris header pada 30
baris pertama, lalu mengirim daftar baris JSON ke API pusat. Browser tidak
mengambil Sheet langsung. API pusat tetap menjadi satu-satunya penulis database
dan memvalidasi ulang semua nilai terhadap master canonical.

Endpoint pusat:

```http
POST /api/v1/praktikum/jadwal/sinkronisasi-google-sheet
```

Payload JSON membawa `url`, `id_periode_akademik`, dan `jadwal[]`. Setiap baris
membawa `baris_sumber`, `prodi`, `hari`, `jam`, `kode_mk`, `mata_kuliah`,
`dosen`, `frekuensi`, `laboratorium`, `asisten_1`, `asisten_2`, `asisten_3`,
`peserta`, serta `sks`. Aplikasi boleh mengirim ID pengguna canonical hasil
pilihan manual untuk membedakan personel dengan nama sama. API pusat memvalidasi
ulang bahwa setiap ID masih aktif dan cocok dengan peran yang diperlukan; ID
tidak dapat dipakai untuk melewati validasi master.

## Pratinjau dan validasi manual

Tombol **Ambil & Pratinjau Sheet** memanggil endpoint aplikasi Praktikum
`POST /admin/preview-google-sheet`. Endpoint ini membaca CSV publik dan
mengembalikan baris beserta checksum SHA-256, tanpa mengirim perubahan ke API
pusat atau menulis database. Browser kemudian memuat master canonical melalui
`GET /references` dan menampilkan tabel yang dapat diedit.

Pencocokan tepat yang unik ditandai hijau. Kandidat fuzzy atau nama yang
ambigu ditandai oranye dan tidak dipilih otomatis; administrator harus memilih
kandidat yang benar. Nilai tanpa kandidat ditandai merah, tetapi masih dapat
diedit atau dipilih dari daftar master. Tombol simpan aktif hanya bila semua
baris lolos validasi lokal. Saat **Simpan** ditekan, seluruh baris terkoreksi
dikirim ke endpoint sinkronisasi pusat yang memvalidasi ulang semuanya secara
atomik. Jika personel memang belum ada di master aktif, data master harus
dilengkapi terlebih dahulu—fuzzy matching tidak membuat akun atau personel.

## Kolom dan aturan

Header yang harus tersedia: `Prodi`, `Hari`, `Jam`, `Kode MK`, `Mata Kuliah`,
`Dosen`, `Frek` atau `Frekuensi`, `Laboratorium`, `Asisten 1`, `Asisten 2`,
`Asisten 3`, `Peserta`, dan `SKS`. Sufiks dalam kurung seperti
`Asisten 1 (CA 2025)` diabaikan. Baris `No`, `Angkatan`, dan `Kelas` bukan
identitas master praktikum dan tidak mengubah roster/kelas reguler.

- Asisten 1 wajib; Asisten 2 dan Asisten 3/Pendamping opsional. Ketiga kolom
  tetap dikirim, termasuk nilai kosong untuk slot opsional.
- Jika `Dosen` berisi beberapa nama dipisahkan `/`, API memakai nama pertama.
- `Peserta` seperti `17+5` hanya menjadi ringkasan informasi; angka pertama
  dipakai dan daftar peserta tidak diubah.
- Awalan `Lab ` pada nama laboratorium boleh dicantumkan atau dihilangkan; API
  mencocokkan keduanya ke satu master laboratorium canonical yang aktif.
- Nama dosen boleh menyertakan gelar; API memakai nama inti hanya jika hasilnya
  cocok ke satu dosen aktif. Asisten boleh memakai satu token nama jika token
  itu hanya cocok ke satu asisten aktif; nama ambigu tetap ditolak.
- `Mata Kuliah` dan `SKS` diperiksa terhadap master. `Hari`, `Jam`, lab dan
  penugasan diperbarui pada jadwal canonical untuk periode aktif dan frekuensi
  yang sama.
- Semua baris diproses atomik. Bentrok atau konflik master membatalkan seluruh
  sinkronisasi. Baris Sheet yang hilang tidak menghapus jadwal lama; jadwal
  berhistori atau workflow terkunci tidak ditimpa.

## Uji parser dan proxy

Jalankan dari root project:

```sh
php tests/google_sheet_schedule_parser_test.php
bash tests/api_mode_mock_test.sh
```

Tes parser memastikan judul/baris kosong sebelum header ditangani dan semua 13
field, khususnya tiga slot asisten, sampai pada bentuk JSON lengkap. Tes proxy
memverifikasi aplikasi meneruskan slot Asisten 1/2/3 ke API pusat.
