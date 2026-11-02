<?php

require_once __DIR__ . '/../app/google_sheet_schedule_import.php';

$rows = iclabs_praktikum_parse_google_sheet_csv(file_get_contents(__DIR__ . '/fixtures/google_sheet_schedule.csv'));
$expected = array(
    'prodi' => 'TI',
    'hari' => 'Senin',
    'jam' => '08.00-10.20',
    'kode_mk' => '130107RP4',
    'mata_kuliah' => 'Algoritma dan Pemrograman',
    'dosen' => 'Dosen Utama / Dosen Cadangan',
    'frekuensi' => 'TI_ALPRO-1',
    'kelas' => 'A1',
    'laboratorium' => 'Laboratorium IoT',
    'asisten_1' => 'Asisten Satu',
    'asisten_2' => 'Asisten Dua',
    'asisten_3' => 'Pendamping Tiga',
    'peserta' => '17+5',
    'sks' => '3',
);

if (count($rows) !== 1 || $rows[0]['baris_sumber'] !== 4 || $rows[0] !== array_merge(array('baris_sumber' => 4), $expected)) {
    fwrite(STDERR, "FAIL: CSV jadwal tidak dipetakan lengkap, termasuk semua slot Asisten.\n");
    exit(1);
}

echo "PASS: header baris ketiga dan seluruh 14 kolom jadwal, termasuk label Kelas dan slot Asisten 1/2/3, dipetakan.\n";

$withoutClass = "Prodi,Hari,Jam,Kode MK,Mata Kuliah,Dosen,Frek,SKS,Peserta,Laboratorium,Asisten 1,Asisten 2,Asisten 3\n"
    . "TI,Senin,08.00-10.20,130107RP4,Algoritma dan Pemrograman,Dosen Utama,TI_ALPRO-1,3,17+5,Laboratorium IoT,Asisten Satu,,\n";
$rowsWithoutClass = iclabs_praktikum_parse_google_sheet_csv($withoutClass);
if (count($rowsWithoutClass) !== 1 || $rowsWithoutClass[0]['kelas'] !== '') {
    fwrite(STDERR, "FAIL: kolom Kelas opsional harus kosong bila tidak ada di Sheet.\n");
    exit(1);
}

echo "PASS: kolom Kelas boleh tidak tersedia dan menghasilkan label kosong.\n";
