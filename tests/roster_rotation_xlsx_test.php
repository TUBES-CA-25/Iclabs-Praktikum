<?php

require dirname(__DIR__).'/app/exchange.php';

$template = [
    'periode' => ['tahun_akademik' => '2026/2027', 'semester' => 'Ganjil'],
    'kelas_tujuan' => [
        ['id' => 10, 'label' => '130107RP4 · TI · TI_ALPRO-1'],
        ['id' => 11, 'label' => '130107RP4 · TI · TI_ALPRO-2'],
    ],
    'roster' => [[
        'nim' => '13020260049',
        'nama' => 'Mahasiswa Uji',
        'kode_mata_kuliah' => '130107RP4',
        'nama_mata_kuliah' => 'Algoritma dan Pemrograman',
        'frekuensi_aktif' => [['label' => '130107RP4 · TI · TI_ALPRO-1']],
        'id_kelas_tujuan_default' => 10,
    ]],
];

ob_start();
xlsx_roster_rotation_download($template);
$bytes = ob_get_clean();
$file = tempnam(sys_get_temp_dir(), 'iclabs_roster_xlsx_test_');
file_put_contents($file, $bytes);
$zip = new ZipArchive();

try {
    if ($zip->open($file) !== true) {
        throw new RuntimeException('Template bukan workbook XLSX yang valid.');
    }
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $rosterXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $classXml = $zip->getFromName('xl/worksheets/sheet2.xml');
    if (!is_string($workbookXml) || strpos($workbookXml, 'FrekuensiTujuan') === false || strpos($workbookXml, 'state="hidden"') === false) {
        throw new RuntimeException('Daftar frekuensi dropdown tidak terpasang sebagai sheet tersembunyi.');
    }
    if (!is_string($rosterXml) || strpos($rosterXml, '<dataValidation type="list"') === false || strpos($rosterXml, 'sqref="E2:E502"') === false) {
        throw new RuntimeException('Dropdown kelas tujuan tidak mencakup baris roster dan ruang input tambahan.');
    }
    if (!is_string($classXml) || strpos($classXml, 'TI_ALPRO-2') === false) {
        throw new RuntimeException('Daftar kelas canonical tidak lengkap.');
    }
    $parsed = read_xlsx(base64_encode($bytes));
    if (($parsed['rows'][0]['values']['nim'] ?? null) !== '13020260049') {
        throw new RuntimeException('NIM tidak dipertahankan sebagai teks.');
    }
    if (($parsed['rows'][0]['values']['kelas tujuan'] ?? null) !== '130107RP4 · TI · TI_ALPRO-1') {
        throw new RuntimeException('Frekuensi aktif tunggal tidak terpilih sebagai tujuan awal.');
    }
    echo "Roster rotation XLSX test passed.\n";
} finally {
    $zip->close();
    @unlink($file);
}
