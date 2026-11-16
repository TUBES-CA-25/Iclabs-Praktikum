<?php

/**
 * Template dan parser impor absensi multi-pertemuan.
 *
 * Kolom pertemuan dikenali dari nama kolom (P1, P2, "Pertemuan 3", "Pert-4",
 * opsional diikuti tanggal) sehingga satu berkas dapat memuat banyak pertemuan.
 */

require_once __DIR__ . '/exchange.php';

const ATTENDANCE_IMPORT_MAX_ROWS = 500;

function attendance_status_code($value)
{
    $text = mb_strtoupper(trim((string) $value), 'UTF-8');
    if ($text === '' || $text === '-' || $text === '—') {
        return null;
    }
    $map = array(
        'H' => 'HADIR', 'HADIR' => 'HADIR', 'HDR' => 'HADIR',
        'I' => 'IZIN', 'IZIN' => 'IZIN', 'IJIN' => 'IZIN',
        'S' => 'SAKIT', 'SAKIT' => 'SAKIT',
        'A' => 'ALPA', 'ALPA' => 'ALPA', 'ALFA' => 'ALPA', 'ALPHA' => 'ALPA',
        'T' => 'TERLAMBAT', 'TERLAMBAT' => 'TERLAMBAT', 'TELAT' => 'TERLAMBAT',
    );

    return $map[$text] ?? false;
}

function attendance_status_letter($code)
{
    $letters = array('HADIR' => 'H', 'IZIN' => 'I', 'SAKIT' => 'S', 'ALPA' => 'A', 'TERLAMBAT' => 'T');

    return $letters[strtoupper((string) $code)] ?? '';
}

/**
 * Baca nama kolom: kembalikan array nomor_pertemuan => array(header, tanggal|null).
 * Header sudah huruf kecil (read_xlsx). Kolom tak dikenal dikumpulkan ke $errors.
 */
function attendance_parse_headers(array $headers, array &$errors)
{
    $ignored = array('', 'no', 'no.', 'nim', 'nama', 'nama mahasiswa', 'catatan', 'keterangan');
    $columns = array();
    foreach ($headers as $header) {
        $header = trim((string) $header);
        if (in_array($header, $ignored, true)) {
            continue;
        }
        if (preg_match('/^(?:pertemuan|pert|p)[\s._-]*(?:ke[\s._-]*)?0*(\d{1,3})(?![\d])(.*)$/u', $header, $m) !== 1) {
            $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Nama kolom "' . $header . '" tidak dikenali. Gunakan P1, P2, … atau "Pertemuan 1".');
            continue;
        }
        $number = (int) $m[1];
        $date = null;
        $rest = trim($m[2], " \t()[]-:–");
        if ($rest !== '') {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rest, $d) === 1 && checkdate((int) $d[2], (int) $d[3], (int) $d[1])) {
                $date = $rest;
            } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $rest, $d) === 1 && checkdate((int) $d[2], (int) $d[1], (int) $d[3])) {
                $date = sprintf('%04d-%02d-%02d', $d[3], $d[2], $d[1]);
            } else {
                $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Tanggal pada nama kolom "' . $header . '" tidak valid. Gunakan format YYYY-MM-DD atau DD/MM/YYYY.');
                continue;
            }
        }
        if ($number < 1) {
            $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Nomor pertemuan harus ≥ 1.');
            continue;
        }
        if (isset($columns[$number])) {
            $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Pertemuan ' . $number . ' muncul pada lebih dari satu kolom.');
            continue;
        }
        $columns[$number] = array('header' => $header, 'tanggal' => $date);
    }
    ksort($columns);

    return $columns;
}

/**
 * Ubah hasil read_xlsx menjadi payload untuk API pusat.
 * @return array{pertemuan:array,kehadiran:array,errors:array}
 */
function attendance_build_import_payload(array $file)
{
    $errors = array();
    $columns = attendance_parse_headers($file['headers'], $errors);
    if (!in_array('nim', $file['headers'], true)) {
        $errors[] = array('baris' => 1, 'kolom' => 'NIM', 'pesan' => 'Kolom NIM wajib ada pada baris pertama.');
    }
    if ($columns === array() && $errors === array()) {
        $errors[] = array('baris' => 1, 'kolom' => '-', 'pesan' => 'Tidak ada kolom pertemuan (P1, P2, …) pada berkas.');
    }
    if (count($file['rows']) > ATTENDANCE_IMPORT_MAX_ROWS) {
        $errors[] = array('baris' => 0, 'kolom' => '-', 'pesan' => 'Berkas maksimal ' . ATTENDANCE_IMPORT_MAX_ROWS . ' baris mahasiswa.');
    }

    $meetings = array();
    foreach ($columns as $number => $column) {
        $meeting = array('nomor_pertemuan' => $number);
        if ($column['tanggal'] !== null) {
            $meeting['tanggal_sesi'] = $column['tanggal'];
        }
        $meetings[$number] = $meeting;
    }

    $entries = array();
    $used = array();
    foreach ($file['rows'] as $row) {
        $line = (int) $row['line'];
        $nim = trim((string) ($row['values']['nim'] ?? ''));
        $hasCell = false;
        foreach ($columns as $number => $column) {
            $raw = $row['values'][$column['header']] ?? '';
            $status = attendance_status_code($raw);
            if ($status === false) {
                $errors[] = array('baris' => $line, 'kolom' => strtoupper($column['header']), 'pesan' => 'Status "' . $raw . '" tidak dikenali. Gunakan H, I, S, A, T atau kosongkan.');
                continue;
            }
            if ($status === null) {
                continue;
            }
            $hasCell = true;
            if ($nim === '') {
                continue;
            }
            $entries[] = array('nim' => $nim, 'nomor_pertemuan' => $number, 'status' => $status, 'baris' => $line, 'kolom' => strtoupper($column['header']));
            $used[$number] = true;
        }
        if ($hasCell && $nim === '') {
            $errors[] = array('baris' => $line, 'kolom' => 'NIM', 'pesan' => 'NIM kosong sementara status kehadiran terisi.');
        }
    }
    if ($entries === array() && $errors === array()) {
        $errors[] = array('baris' => 0, 'kolom' => '-', 'pesan' => 'Tidak ada status kehadiran yang terisi pada berkas.');
    }

    // Kirim hanya pertemuan yang benar-benar memiliki isian.
    $meetings = array_values(array_intersect_key($meetings, $used));

    return array('pertemuan' => $meetings, 'kehadiran' => $entries, 'errors' => $errors);
}

function attendance_errors_message(array $errors, $limit = 15)
{
    $lines = array();
    foreach (array_slice($errors, 0, $limit) as $error) {
        $where = (int) ($error['baris'] ?? 0) > 0 ? 'Baris ' . (int) $error['baris'] . ', kolom ' . ($error['kolom'] ?? '-') : 'Berkas';
        $lines[] = $where . ': ' . ($error['pesan'] ?? 'Tidak valid.');
    }
    $extra = count($errors) - count($lines);
    if ($extra > 0) {
        $lines[] = '… dan ' . $extra . ' masalah lain.';
    }

    return "Import dibatalkan. Perbaiki masalah berikut lalu coba kembali:\n" . implode("\n", $lines);
}

function attendance_sheet_xml(array $rows, array $widths, $headerStyle = 1, $freeze = null, $validation = null)
{
    $xml = '';
    foreach ($rows as $i => $row) {
        $n = $i + 1;
        $xml .= '<row r="' . $n . '">';
        foreach ($row as $j => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $style = ($n === 1 && $headerStyle !== null) ? $headerStyle : 0;
            $xml .= '<c r="' . column_name($j + 1) . $n . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . xml_escape($value) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    $cols = '';
    foreach ($widths as $k => $w) {
        $cols .= '<col min="' . ($k + 1) . '" max="' . ($k + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $view = $freeze === null ? '<sheetView workbookViewId="0"/>' : '<sheetView workbookViewId="0"><pane xSplit="' . $freeze[0] . '" ySplit="' . $freeze[1] . '" topLeftCell="' . column_name($freeze[0] + 1) . ($freeze[1] + 1) . '" activePane="bottomRight" state="frozen"/></sheetView>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews>' . $view . '</sheetViews><cols>' . $cols . '</cols>'
        . '<sheetData>' . $xml . '</sheetData>'
        . ($validation !== null
            ? '<dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Status tidak valid" error="Gunakan H, I, S, A, atau T." sqref="' . $validation . '"><formula1>"H,I,S,A,T"</formula1></dataValidation></dataValidations>'
            : '')
        . '</worksheet>';
}

/**
 * Template absensi per kelas: sheet Absensi (mahasiswa aktif + kolom P1..Pn),
 * Petunjuk, dan Info (sesi yang sudah ada).
 */
function xlsx_attendance_template_download(array $class, $filename, $scheduleId = null)
{
    if (!class_exists('ZipArchive')) {
        reject('Ekstensi ZIP PHP diperlukan untuk membuat template Excel.', 503);
    }
    $meetings = max(1, (int) ($class['jumlah_pertemuan_rencana'] ?? 0));
    $students = is_array($class['peserta'] ?? null) ? $class['peserta'] : array();
    usort($students, static function ($a, $b) {
        return strcmp((string) ($a['nim'] ?? ''), (string) ($b['nim'] ?? ''));
    });

    $header = array('NIM', 'Nama');
    for ($n = 1; $n <= $meetings; $n++) {
        $header[] = 'P' . $n;
    }
    $rows = array($header);
    foreach ($students as $student) {
        $line = array((string) ($student['nim'] ?? ''), (string) ($student['nama'] ?? ''));
        $byMeeting = array();
        foreach ((array) ($student['kehadiran'] ?? array()) as $record) {
            if ($scheduleId !== null && isset($record['id_jadwal_kelas_kuliah']) && (int) $record['id_jadwal_kelas_kuliah'] !== (int) $scheduleId) {
                continue;
            }
            $byMeeting[(int) ($record['nomor_pertemuan'] ?? 0)] = attendance_status_letter($record['status'] ?? '');
        }
        for ($n = 1; $n <= $meetings; $n++) {
            $line[] = $byMeeting[$n] ?? '';
        }
        $rows[] = $line;
    }

    $widths = array(16, 34);
    for ($n = 1; $n <= $meetings; $n++) {
        $widths[] = 6;
    }
    $lastColumn = column_name(count($header));
    $lastRow = max(2, count($rows));
    $sheet1 = attendance_sheet_xml($rows, $widths, 1, array(2, 1), 'C2:' . $lastColumn . ($lastRow + 200));

    $guide = array(
        array('Petunjuk pengisian'),
        array('1. Isi sel pada kolom P1, P2, … sesuai pertemuan. Satu berkas boleh memuat banyak pertemuan.'),
        array('2. Kode status: H = Hadir, I = Izin, S = Sakit, A = Alpa, T = Terlambat. Sel kosong atau "-" = tidak diubah.'),
        array('3. Jangan mengubah NIM dan nama kolom pertemuan (P1, P2, …). Kolom lain yang tidak dikenali akan ditolak.'),
        array('4. Opsional: tulis tanggal pada nama kolom, mis. "P3 (2026-09-28)", untuk menetapkan tanggal sesi.'),
        array('5. Pertemuan yang belum punya sesi dibuat otomatis: tanggal mengikuti jadwal (mingguan), materi "Pertemuan n".'),
        array('6. Seluruh berkas ditolak jika ada satu baris bermasalah; tidak ada data yang tersimpan sebagian.'),
    );
    $sheet2 = attendance_sheet_xml($guide, array(120));

    $sessions = array();
    foreach ((array) ($class['sesi'] ?? array()) as $session) {
        $sessions[(int) ($session['nomor_pertemuan'] ?? 0)] = $session;
    }
    $info = array(
        array('Informasi kelas', ''),
        array('Mata kuliah', trim((string) ($class['mata_kuliah']['kode'] ?? '') . ' ' . (string) ($class['mata_kuliah']['nama'] ?? ''))),
        array('Frekuensi', (string) ($class['kelompok']['kode'] ?? '')),
        array('Periode', trim((string) ($class['periode']['tahun_akademik'] ?? '') . ' ' . (string) ($class['periode']['semester'] ?? ''))),
        array('Jumlah pertemuan', (string) $meetings),
        array('Versi data saat diunduh', (string) ($class['kelompok']['versi'] ?? '')),
        array('', ''),
        array('Pertemuan', 'Tanggal sesi / Materi (yang sudah tercatat)'),
    );
    for ($n = 1; $n <= $meetings; $n++) {
        $s = $sessions[$n] ?? null;
        $info[] = array('P' . $n, $s === null ? 'Belum ada sesi' : trim((string) ($s['tanggal_sesi'] ?? '') . ' · ' . (string) ($s['materi'] ?? '')));
    }
    $sheet3 = attendance_sheet_xml($info, array(28, 80), null);

    $file = tempnam(sys_get_temp_dir(), 'iclabs_att_');
    $zip = new ZipArchive();
    try {
        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            reject('Tidak dapat membuat file Excel.', 500);
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Absensi" sheetId="1" r:id="rId1"/><sheet name="Petunjuk" sheetId="2" r:id="rId2"/><sheet name="Info" sheetId="3" r:id="rId3"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4CF45"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0" xfId="0" numFmtId="49" applyNumberFormat="1"/><xf fontId="1" fillId="2" borderId="0" xfId="0" numFmtId="49" applyNumberFormat="1"><alignment wrapText="1" horizontal="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);
        $zip->addFromString('xl/worksheets/sheet3.xml', $sheet3);
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
    } finally {
        @unlink($file);
    }
}
