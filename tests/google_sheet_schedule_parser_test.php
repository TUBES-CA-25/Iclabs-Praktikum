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

echo "PASS: header baris ketiga dan seluruh 13 kolom jadwal, termasuk Asisten 1/2/3, dipetakan.\n";
