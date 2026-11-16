<?php

/**
 * Template dan parser impor nilai per pertemuan.
 *
 * Setiap pertemuan dapat memiliki bobot/kategori berbeda (umumnya 1–6 kategori),
 * sehingga unduhan template dan impor dibuat khusus per pertemuan.
 * Sel nilai hanya disimpan oleh server bila mahasiswa sudah memiliki label kehadiran
 * pada pertemuan tersebut; sel tanpa label kehadiran diabaikan otomatis.
 */

require_once __DIR__ . '/exchange.php';

const GRADES_IMPORT_MAX_ROWS = 500;

function grades_normalize_component_name($name)
{
    $text = trim((string) $name);
    // Hilangkan suffix bobot seperti " (25%)" atau " · 25%" bila pengguna menyalin header tabel UI
    $text = preg_replace('/(?:\s*·\s*|\s*\()\d+(?:[.,]\d+)?\s*%\)?\s*$/u', '', $text);

    return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)), 'UTF-8');
}

function grades_attendance_label($status)
{
    $map = array(
        'HADIR' => 'Hadir',
        'IZIN' => 'Izin',
        'SAKIT' => 'Sakit',
        'ALPA' => 'Alpa',
        'TERLAMBAT' => 'Terlambat',
    );
    $code = strtoupper(trim((string) $status));

    return $map[$code] ?? 'Belum diisi';
}

/**
 * Parse angka nilai 0..100 (maks 2 desimal). Mengembalikan null jika kosong/'-', atau false jika invalid.
 */
function grades_parse_score($raw)
{
    $text = trim((string) $raw);
    if ($text === '' || $text === '-' || $text === '—') {
        return null;
    }
    $normalized = str_replace(',', '.', $text);
    if (preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) !== 1) {
        return false;
    }
    $value = (float) $normalized;
    if ($value < 0.0 || $value > 100.0) {
        return false;
    }

    return round($value, 2);
}

/**
 * Parse header kolom XLSX untuk satu pertemuan ($meetingNumber).
 * Mendukung nama kategori langsung ("Teori", "Teori (15%)") maupun awalan ("P1 - Teori").
 */
function grades_parse_headers(array $headers, array $schemeComponents, $meetingNumber, array &$errors)
{
    $ignored = array('', 'no', 'no.', 'nim', 'nama', 'nama mahasiswa', 'kehadiran', 'status kehadiran', 'catatan', 'keterangan', 'nilai pertemuan', 'total');
    $compByName = array();
    foreach ($schemeComponents as $component) {
        $id = (int) ($component['id_komponen_skema_penilaian_praktikum'] ?? 0);
        $name = (string) ($component['nama'] ?? '');
        if ($id > 0 && $name !== '') {
            $compByName[grades_normalize_component_name($name)] = array('id' => $id, 'nama' => $name);
        }
    }
    $singleComp = count($compByName) === 1 ? reset($compByName) : null;

    $columns = array();
    $seenComps = array();
    foreach ($headers as $header) {
        $header = trim((string) $header);
        $lower = mb_strtolower($header, 'UTF-8');
        if (in_array($lower, $ignored, true)) {
            continue;
        }

        $compNameCandidate = $header;
        if (preg_match('/^(?:pertemuan|pert|p)[\s._-]*(?:ke[\s._-]*)?0*(\d{1,3})(?![\d])(?:\s*(?:[-–:|]|·)\s*(.+)|\s+(.+))?$/iu', $header, $m) === 1) {
            $headerMeeting = (int) $m[1];
            if ($headerMeeting !== (int) $meetingNumber) {
                $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Kolom "' . $header . '" mengarah ke Pertemuan ' . $headerMeeting . ', sedangkan impor ini untuk Pertemuan ' . (int) $meetingNumber . '.');
                continue;
            }
            $compNameCandidate = trim((string) (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '')));
            if ($compNameCandidate === '') {
                if ($singleComp === null) {
                    $names = implode(', ', array_column($compByName, 'nama'));
                    $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Kolom "' . $header . '" harus menyebut kategori (' . $names . ').');
                    continue;
                }
                $comp = $singleComp;
            }
        }

        if (!isset($comp)) {
            $normComp = grades_normalize_component_name($compNameCandidate);
            if (!isset($compByName[$normComp])) {
                $names = implode(', ', array_column($compByName, 'nama'));
                $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Kategori "' . $header . '" tidak ada di skema Pertemuan ' . (int) $meetingNumber . ' (' . $names . ').');
                unset($comp);
                continue;
            }
            $comp = $compByName[$normComp];
        }

        $compId = $comp['id'];
        $compLabel = $comp['nama'];
        unset($comp);

        if (isset($seenComps[$compId])) {
            $errors[] = array('baris' => 1, 'kolom' => $header, 'pesan' => 'Kategori "' . $compLabel . '" muncul lebih dari sekali.');
            continue;
        }
        $seenComps[$compId] = true;
        $columns[] = array(
            'header' => $lower,
            'label' => $header,
            'id_komponen' => $compId,
            'nama_komponen' => $compLabel,
        );
    }

    return $columns;
}

/**
 * Ubah hasil read_xlsx menjadi payload nilai untuk satu pertemuan ($meetingNumber).
 * @return array{nilai:array,errors:array}
 */
function grades_build_import_payload(array $file, array $schemeComponents, $meetingNumber)
{
    $errors = array();
    $columns = grades_parse_headers($file['headers'], $schemeComponents, $meetingNumber, $errors);
    if (!in_array('nim', $file['headers'], true)) {
        $errors[] = array('baris' => 1, 'kolom' => 'NIM', 'pesan' => 'Kolom NIM wajib ada pada baris pertama.');
    }
    if ($columns === array() && $errors === array()) {
        $names = implode(', ', array_map(static fn ($c) => $c['nama'] ?? '', $schemeComponents));
        $errors[] = array('baris' => 1, 'kolom' => '-', 'pesan' => 'Tidak ada kolom kategori nilai (' . $names . ') pada berkas.');
    }
    if (count($file['rows']) > GRADES_IMPORT_MAX_ROWS) {
        $errors[] = array('baris' => 0, 'kolom' => '-', 'pesan' => 'Berkas maksimal ' . GRADES_IMPORT_MAX_ROWS . ' baris mahasiswa.');
    }

    $entries = array();
    foreach ($file['rows'] as $row) {
        $line = (int) $row['line'];
        $nim = trim((string) ($row['values']['nim'] ?? ''));
        $hasCell = false;
        foreach ($columns as $col) {
            $raw = $row['values'][$col['header']] ?? '';
            $score = grades_parse_score($raw);
            if ($score === false) {
                $errors[] = array('baris' => $line, 'kolom' => strtoupper($col['label']), 'pesan' => 'Nilai "' . $raw . '" tidak valid. Gunakan angka 0–100 (maks. 2 desimal) atau kosongkan.');
                continue;
            }
            if ($score === null) {
                continue;
            }
            $hasCell = true;
            if ($nim === '') {
                continue;
            }
            $entries[] = array(
                'nim' => $nim,
                'id_komponen_skema_penilaian_praktikum' => $col['id_komponen'],
                'nilai' => $score,
                'baris' => $line,
                'kolom' => strtoupper($col['label']),
            );
        }
        if ($hasCell && $nim === '') {
            $errors[] = array('baris' => $line, 'kolom' => 'NIM', 'pesan' => 'NIM kosong sementara sel nilai terisi.');
        }
    }
    if ($entries === array() && $errors === array()) {
        $errors[] = array('baris' => 0, 'kolom' => '-', 'pesan' => 'Tidak ada nilai yang terisi pada berkas.');
    }

    return array('nilai' => $entries, 'errors' => $errors);
}

function grades_errors_message(array $errors, $limit = 15)
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

    return "Import nilai dibatalkan. Perbaiki masalah berikut lalu coba kembali:\n" . implode("\n", $lines);
}

function grades_sheet_xml(array $rows, array $widths, $headerRowStyle = 1, $freeze = null, array $cellStyles = array(), array $numericCols = array())
{
    $xml = '';
    foreach ($rows as $i => $row) {
        $n = $i + 1;
        $xml .= '<row r="' . $n . '">';
        foreach ($row as $j => $value) {
            $ref = column_name($j + 1) . $n;
            $style = $cellStyles[$i][$j] ?? (($n === 1 && $headerRowStyle !== null) ? $headerRowStyle : 0);
            if ($value === null || $value === '') {
                if ($style !== 0) {
                    $xml .= '<c r="' . $ref . '" s="' . $style . '"/>';
                }
                continue;
            }
            if ($n > 1 && isset($numericCols[$j]) && (is_int($value) || is_float($value))) {
                $xml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . xml_escape($value) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }
    $cols = '';
    foreach ($widths as $k => $w) {
        $cols .= '<col min="' . ($k + 1) . '" max="' . ($k + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $view = $freeze === null
        ? '<sheetView workbookViewId="0"/>'
        : '<sheetView workbookViewId="0"><pane xSplit="' . $freeze[0] . '" ySplit="' . $freeze[1] . '" topLeftCell="' . column_name($freeze[0] + 1) . ($freeze[1] + 1) . '" activePane="bottomRight" state="frozen"/></sheetView>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews>' . $view . '</sheetViews><cols>' . $cols . '</cols>'
        . '<sheetData>' . $xml . '</sheetData>'
        . '</worksheet>';
}

/**
 * Buat template XLSX nilai untuk SATU pertemuan ($meetingNumber):
 * - Sheet Nilai: NIM, Nama, Kehadiran, diikuti kolom kategori skema pertemuan tersebut.
 *   Sel mahasiswa tanpa label kehadiran diberi arsir abu-abu muda (tetap terbuka).
 * - Sheet Petunjuk & Sheet Info.
 */
function xlsx_grades_template_download(array $class, $meetingNumber, array $scheme, ?array $existingSheet, $filename)
{
    if (!class_exists('ZipArchive')) {
        reject('Ekstensi ZIP PHP diperlukan untuk membuat template Excel.', 503);
    }
    $meetingNumber = (int) $meetingNumber;
    $components = is_array($scheme['komponen'] ?? null) ? $scheme['komponen'] : array();
    $students = is_array($class['peserta'] ?? null) ? $class['peserta'] : array();
    usort($students, static function ($a, $b) {
        return strcmp((string) ($a['nim'] ?? ''), (string) ($b['nim'] ?? ''));
    });

    // Status kehadiran mahasiswa pada pertemuan ini
    $attendanceByStudent = array();
    foreach ($students as $student) {
        $sid = (int) ($student['id_mahasiswa'] ?? 0);
        foreach ((array) ($student['kehadiran'] ?? array()) as $att) {
            if ((int) ($att['nomor_pertemuan'] ?? 0) === $meetingNumber && trim((string) ($att['status'] ?? '')) !== '') {
                $attendanceByStudent[$sid] = strtoupper(trim((string) $att['status']));
            }
        }
    }

    // Nilai existing pada lembar pertemuan ini (bila sudah ada)
    $existingGrades = array();
    if ($existingSheet !== null) {
        foreach ((array) ($existingSheet['peserta'] ?? array()) as $p) {
            $sid = (int) ($p['id_mahasiswa'] ?? 0);
            foreach ((array) ($p['nilai'] ?? array()) as $g) {
                $cid = (int) ($g['id_komponen_skema_penilaian_praktikum'] ?? 0);
                if (isset($g['nilai']) && $g['nilai'] !== null) {
                    $existingGrades[$sid][$cid] = (float) $g['nilai'];
                }
            }
        }
    }

    $header = array('NIM', 'Nama', 'Kehadiran');
    $widths = array(16, 32, 14);
    $numericCols = array();
    $colMeta = array();
    foreach ($components as $idx => $comp) {
        $cName = (string) ($comp['nama'] ?? 'Komponen');
        $header[] = $cName;
        $colMeta[] = (int) ($comp['id_komponen_skema_penilaian_praktikum'] ?? 0);
        $widths[] = max(16, min(30, mb_strlen($cName, 'UTF-8') + 4));
        $numericCols[3 + $idx] = true;
    }

    $rows = array($header);
    $cellStyles = array();
    $labeledCount = 0;
    foreach ($students as $rIdx => $student) {
        $sid = (int) ($student['id_mahasiswa'] ?? 0);
        $attCode = $attendanceByStudent[$sid] ?? null;
        $hasAtt = $attCode !== null;
        if ($hasAtt) {
            $labeledCount++;
        }
        $line = array(
            (string) ($student['nim'] ?? ''),
            (string) ($student['nama'] ?? ''),
            grades_attendance_label($attCode),
        );
        $rowStyle = array(0 => 0, 1 => 0, 2 => $hasAtt ? 0 : 3);
        foreach ($colMeta as $cOffset => $cid) {
            $val = $existingGrades[$sid][$cid] ?? '';
            $line[] = $val === '' ? '' : (float) $val;
            $rowStyle[3 + $cOffset] = $hasAtt ? 2 : 3;
        }
        $rows[] = $line;
        $cellStyles[$rIdx + 1] = $rowStyle;
    }

    $sheet1 = grades_sheet_xml($rows, $widths, 1, array(3, 1), $cellStyles, $numericCols);

    $guide = array(
        array('Petunjuk Pengisian Template Nilai Pertemuan ' . $meetingNumber),
        array('1. Isi nilai berupa angka 0 sampai 100 (maksimal 2 desimal, mis. 85 atau 87.5). Sel kosong atau "-" tidak mengubah nilai lama.'),
        array('2. Aturan Label Kehadiran: Hanya mahasiswa yang SUDAH memiliki label kehadiran (Hadir/Izin/Sakit/Alpa/Terlambat) yang nilainya akan disimpan.'),
        array('3. Kolom "Kehadiran" bersifat informasi. Sel berlatar abu-abu ("Belum diisi") diabaikan otomatis bila tetap diisi di Excel.'),
        array('4. Jangan mengubah NIM maupun nama kolom kategori nilai. Anda dapat menghapus kolom kategori tertentu jika hanya ingin memperbarui sebagian kategori.'),
        array('5. Impor berlaku khusus untuk Pertemuan ' . $meetingNumber . ' dan bersifat atomik untuk validasi NIM dan angka nilai.'),
    );
    $sheet2 = grades_sheet_xml($guide, array(125));

    $session = null;
    foreach ((array) ($class['sesi'] ?? array()) as $s) {
        if ((int) ($s['nomor_pertemuan'] ?? 0) === $meetingNumber) {
            $session = $s;
            break;
        }
    }
    $compLines = array();
    foreach ($components as $c) {
        $compLines[] = ($c['nama'] ?? '') . ' (' . (float) ($c['bobot_persen'] ?? 0) . '%)';
    }
    $info = array(
        array('Informasi Penilaian Pertemuan ' . $meetingNumber, ''),
        array('Mata kuliah', trim((string) ($class['mata_kuliah']['kode'] ?? '') . ' ' . (string) ($class['mata_kuliah']['nama'] ?? ''))),
        array('Frekuensi', (string) ($class['kelompok']['kode'] ?? '')),
        array('Periode', trim((string) ($class['periode']['tahun_akademik'] ?? '') . ' ' . (string) ($class['periode']['semester'] ?? ''))),
        array('Pertemuan', 'Pertemuan ' . $meetingNumber . ($session !== null ? ' (' . ($session['tanggal_sesi'] ?? '') . ' · ' . ($session['materi'] ?? '') . ')' : ' (Sesi belum dibuat)')),
        array('Berlabel kehadiran', $labeledCount . ' / ' . count($students) . ' mahasiswa aktif'),
        array('Skema aktif', 'Versi ' . (int) ($scheme['nomor_versi'] ?? 1) . ': ' . implode(', ', $compLines)),
        array('Status lembar', $existingSheet !== null ? ($existingSheet['status'] ?? 'DRAF') : 'Belum ada lembar (otomatis dibuat saat impor)'),
    );
    $sheet3 = grades_sheet_xml($info, array(26, 90), null);

    $sheetName = 'Nilai P' . $meetingNumber;
    $file = tempnam(sys_get_temp_dir(), 'iclabs_grd_');
    $zip = new ZipArchive();
    try {
        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            reject('Tidak dapat membuat file Excel.', 500);
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . xml_escape($sheetName) . '" sheetId="1" r:id="rId1"/><sheet name="Petunjuk" sheetId="2" r:id="rId2"/><sheet name="Info" sheetId="3" r:id="rId3"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4CF45"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom></border></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="4"><xf fontId="0" fillId="0" borderId="0" xfId="0" numFmtId="49" applyNumberFormat="1"/><xf fontId="1" fillId="2" borderId="0" xfId="0" numFmtId="49" applyNumberFormat="1"><alignment wrapText="1" horizontal="center"/></xf><xf fontId="0" fillId="0" borderId="1" xfId="0" numFmtId="0" applyBorder="1"/><xf fontId="2" fillId="3" borderId="1" xfId="0" numFmtId="0" applyFill="1" applyBorder="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
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
