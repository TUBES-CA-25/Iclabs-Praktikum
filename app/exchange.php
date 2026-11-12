<?php

function xml_escape($s)
{
    return htmlspecialchars(
        (string) $s,
        ENT_QUOTES | ENT_XML1,
        'UTF-8'
    );
}

function xml_doc($text)
{
    if (
        $text === false ||
        stripos($text, '<!DOCTYPE') !== false ||
        stripos($text, '<!ENTITY') !== false
    ) {
        reject('XML workbook tidak aman atau tidak valid.');
    }

    $previous = libxml_disable_entity_loader(true);
    $doc = new DOMDocument();

    $ok = @$doc->loadXML(
        $text,
        LIBXML_NONET | LIBXML_NOBLANKS
    );

    libxml_disable_entity_loader($previous);

    if (!$ok) {
        reject('Workbook tidak dapat dibaca.');
    }

    return $doc;
}

function read_xlsx($base64)
{
    if (!is_string($base64) || strlen($base64) > 5500000) {
        reject('File XLSX maksimal 4 MB.');
    }

    $raw = base64_decode($base64, true);

    if ($raw === false || strlen($raw) > 4000000) {
        reject('File XLSX tidak valid.');
    }

    $file = tempnam(
        sys_get_temp_dir(),
        'fikom_xlsx_'
    );

    file_put_contents($file, $raw);

    $zip = new ZipArchive();

    try {
        if ($zip->open($file) !== true) {
            reject('File harus berupa Excel .xlsx.');
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $total += $st['size'];

            if ($total > 30000000 || $zip->numFiles > 2000) {
                reject('Isi workbook terlalu besar.');
            }
        }

        $strings = array();
        $shared = $zip->getFromName('xl/sharedStrings.xml');

        if ($shared !== false) {
            $x = new DOMXPath(
                xml_doc($shared)
            );

            foreach (
                $x->query('//*[local-name()="si"]') as $si
            ) {
                $value = '';

                foreach (
                    $x->query(
                        './/*[local-name()="t"]',
                        $si
                    ) as $t
                ) {
                    $value .= $t->textContent;
                }

                $strings[] = $value;
            }
        }

        $work = new DOMXPath(
            xml_doc(
                $zip->getFromName(
                    'xl/workbook.xml'
                )
            )
        );

        $sheet = $work
            ->query(
                '//*[local-name()="sheets"]/*[local-name()="sheet"]'
            )
            ->item(0);

        if (!$sheet) {
            reject('Workbook tidak memiliki sheet.');
        }

        $rid = $sheet->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id'
        );

        $rels = new DOMXPath(
            xml_doc(
                $zip->getFromName(
                    'xl/_rels/workbook.xml.rels'
                )
            )
        );

        $target = null;

        foreach (
            $rels->query(
                '//*[local-name()="Relationship"]'
            ) as $rel
        ) {
            if ($rel->getAttribute('Id') === $rid) {
                $target = $rel->getAttribute('Target');
            }
        }

        if (
            !$target ||
            strpos($target, '..') !== false ||
            strpos($target, ':') !== false
        ) {
            reject('Lokasi sheet tidak valid.');
        }

        $target = $target[0] === '/'
            ? substr($target, 1)
            : 'xl/' . $target;

        $x = new DOMXPath(
            xml_doc(
                $zip->getFromName($target)
            )
        );

        $headers = null;
        $output = array();
        $rowIndex = 0;

        foreach (
            $x->query(
                '//*[local-name()="sheetData"]/*[local-name()="row"]'
            ) as $r
        ) {
            $rowIndex++;

            $line = $r->getAttribute('r')
                ? (int) $r->getAttribute('r')
                : $rowIndex;

            $values = array();
            $fallback = 0;

            foreach (
                $x->query(
                    './*[local-name()="c"]',
                    $r
                ) as $cell
            ) {
                $ref = $cell->getAttribute('r');
                $col = $fallback;

                if (
                    preg_match(
                        '/^([A-Z]+)\d+$/',
                        $ref,
                        $m
                    )
                ) {
                    $col = 0;

                    foreach (
                        str_split($m[1]) as $char
                    ) {
                        $col = $col * 26 + ord($char) - 64;
                    }

                    $col--;
                }

                $fallback = $col + 1;

                if ($col > 255) {
                    reject(
                        'Workbook terlalu banyak kolom.'
                    );
                }

                if (
                    $x->query(
                        './*[local-name()="f"]',
                        $cell
                    )->length
                ) {
                    reject(
                        'Gunakan nilai teks/angka, bukan formula, pada baris ' .
                        $line
                    );
                }

                $type = $cell->getAttribute('t');
                $value = '';

                if ($type === 'inlineStr') {
                    foreach (
                        $x->query(
                            './/*[local-name()="t"]',
                            $cell
                        ) as $t
                    ) {
                        $value .= $t->textContent;
                    }
                } else {
                    $v = $x
                        ->query(
                            './*[local-name()="v"]',
                            $cell
                        )
                        ->item(0);

                    $value = $v
                        ? $v->textContent
                        : '';

                    if ($type === 's') {
                        $value = $strings[(int) $value] ?? '';
                    }
                }

                $values[$col] = trim($value);
            }

            if ($headers === null) {
                if ($line !== 1) {
                    reject(
                        'Judul kolom harus berada di baris pertama.'
                    );
                }

                $headers = array();

                $last = $values
                    ? max(array_keys($values))
                    : 0;

                for ($i = 0; $i <= $last; $i++) {
                    $headers[] = mb_strtolower(
                        $values[$i] ?? '',
                        'UTF-8'
                    );
                }

                if (
                    count($headers) !==
                    count(array_unique($headers))
                ) {
                    reject(
                        'Judul kolom duplikat.'
                    );
                }

                continue;
            }

            if (!strlen(implode('', $values))) {
                continue;
            }

            $mapped = array();

            foreach ($headers as $i => $header) {
                $mapped[$header] =
                    $values[$i] ?? '';
            }

            $output[] = array(
                'line' => $line,
                'values' => $mapped
            );

            if (count($output) > 2000) {
                reject('Maksimal 2000 baris.');
            }
        }

        return array(
            'headers' => $headers ?: array(),
            'rows' => $output
        );
    } finally {
        $zip->close();
        @unlink($file);
    }
}

function column_name($n)
{
    $text = '';

    while ($n > 0) {
        $n--;
        $text =
            chr(65 + $n % 26) .
            $text;

        $n = (int) floor($n / 26);
    }

    return $text;
}

function xlsx_download(
    $rows,
    $filename,
    $headerRow = 1
) {
    $width = 1;

    foreach ($rows as $r) {
        $width = max(
            $width,
            count($r)
        );
    }

    $xml = '';

    foreach ($rows as $i => $r) {
        $n = $i + 1;

        $xml .=
            '<row r="' .
            $n .
            '" ht="' .
            ($n === $headerRow ? 32 : 23) .
            '" customHeight="1">';

        foreach ($r as $j => $v) {
            $ref =
                column_name($j + 1) .
                $n;

            $style =
                $n === $headerRow
                    ? 1
                    : 0;

            if ($v === null) {
                $xml .=
                    '<c r="' .
                    $ref .
                    '"/>';
            } elseif (
                is_int($v) ||
                is_float($v)
            ) {
                $xml .=
                    '<c r="' .
                    $ref .
                    '" s="2"><v>' .
                    $v .
                    '</v></c>';
            } else {
                $xml .=
                    '<c r="' .
                    $ref .
                    '" s="' .
                    $style .
                    '" t="inlineStr"><is><t xml:space="preserve">' .
                    xml_escape($v) .
                    '</t></is></c>';
            }
        }

        $xml .= '</row>';
    }

    $sheet =
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetViews>' .
                '<sheetView workbookViewId="0">' .
                    '<pane ' .
                        'xSplit="3" ' .
                        'ySplit="' . $headerRow . '" ' .
                        'topLeftCell="D' . ($headerRow + 1) . '" ' .
                        'activePane="bottomRight" ' .
                        'state="frozen"' .
                    '/>' .
                '</sheetView>' .
            '</sheetViews>' .
            '<cols>' .
                '<col min="1" max="' . $width . '" width="12" customWidth="1"/>' .
                '<col min="2" max="2" width="22" customWidth="1"/>' .
                '<col min="3" max="3" width="30" customWidth="1"/>' .
            '</cols>' .
            '<sheetData>' .
                $xml .
            '</sheetData>' .
            (
                $headerRow > 1
                    ? '<mergeCells count="2">' .
                        '<mergeCell ref="A1:' . column_name($width) . '1"/>' .
                        '<mergeCell ref="A2:' . column_name($width) . '2"/>' .
                      '</mergeCells>'
                    : ''
            ) .
            '<pageMargins ' .
                'left="0.25" ' .
                'right="0.25" ' .
                'top="0.4" ' .
                'bottom="0.4" ' .
                'header="0.2" ' .
                'footer="0.2"' .
            '/>' .
            '<pageSetup ' .
                'paperSize="8" ' .
                'orientation="landscape" ' .
                'fitToWidth="1" ' .
                'fitToHeight="0"' .
            '/>' .
        '</worksheet>';

    $file = tempnam(
        sys_get_temp_dir(),
        'fikom_export_'
    );

    $zip = new ZipArchive();

    try {
        if (
            $zip->open(
                $file,
                ZipArchive::OVERWRITE
            ) !== true
        ) {
            reject(
                'Tidak dapat membuat file Excel.',
                500
            );
        }

        $zip->addFromString(
            '[Content_Types].xml',
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
                '<Default Extension="xml" ContentType="application/xml"/>' .
                '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
                '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
                '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>'
        );

        $zip->addFromString(
            '_rels/.rels',
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship ' .
                    'Id="rId1" ' .
                    'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" ' .
                    'Target="xl/workbook.xml"' .
                '/>' .
            '</Relationships>'
        );

        $zip->addFromString(
            'xl/workbook.xml',
            '<workbook ' .
                'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
                'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
                '<sheets>' .
                    '<sheet name="Praktikum" sheetId="1" r:id="rId1"/>' .
                '</sheets>' .
            '</workbook>'
        );

        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
                '<Relationship ' .
                    'Id="rId1" ' .
                    'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" ' .
                    'Target="worksheets/sheet1.xml"' .
                '/>' .
                '<Relationship ' .
                    'Id="rId2" ' .
                    'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" ' .
                    'Target="styles.xml"' .
                '/>' .
            '</Relationships>'
        );

        $zip->addFromString(
            'xl/styles.xml',
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
                '<fonts count="2">' .
                    '<font>' .
                        '<sz val="10"/>' .
                        '<name val="Calibri"/>' .
                    '</font>' .
                    '<font>' .
                        '<b/>' .
                        '<sz val="11"/>' .
                        '<name val="Calibri"/>' .
                    '</font>' .
                '</fonts>' .
                '<fills count="3">' .
                    '<fill>' .
                        '<patternFill patternType="none"/>' .
                    '</fill>' .
                    '<fill>' .
                        '<patternFill patternType="gray125"/>' .
                    '</fill>' .
                    '<fill>' .
                        '<patternFill patternType="solid">' .
                            '<fgColor rgb="FFF4CF45"/>' .
                        '</patternFill>' .
                    '</fill>' .
                '</fills>' .
                '<borders count="1">' .
                    '<border/>' .
                '</borders>' .
                '<cellStyleXfs count="1">' .
                    '<xf/>' .
                '</cellStyleXfs>' .
                '<cellXfs count="3">' .
                    '<xf fontId="0" fillId="0" borderId="0" xfId="0"/>' .
                    '<xf fontId="1" fillId="2" borderId="0" xfId="0">' .
                        '<alignment wrapText="1"/>' .
                    '</xf>' .
                    '<xf fontId="0" fillId="0" borderId="0" numFmtId="2" xfId="0"/>' .
                '</cellXfs>' .
                '<cellStyles count="1">' .
                    '<cellStyle name="Normal" xfId="0" builtinId="0"/>' .
                '</cellStyles>' .
            '</styleSheet>'
        );

        $zip->addFromString(
            'xl/worksheets/sheet1.xml',
            $sheet
        );

        $zip->close();

        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );

        readfile($file);
    } finally {
        @unlink($file);
    }
}

function xlsx_roster_rotation_download(array $template, $filename = 'template-roster-rotasi.xlsx')
{
    if (!class_exists('ZipArchive')) {
        reject('Ekstensi ZIP PHP diperlukan untuk membuat template Excel.', 503);
    }

    $roster = $template['roster'] ?? array();
    $classes = $template['kelas_tujuan'] ?? array();
    $period = $template['periode'] ?? array();
    $headers = array('NIM', 'Nama Mahasiswa (informasi)', 'Mata Kuliah (informasi)', 'Frekuensi Aktif (informasi)', 'Kelas Tujuan', 'Tanggal Efektif', 'Alasan Perpindahan');
    $optionsById = array();
    $classLabels = array();
    foreach ($classes as $class) {
        $optionsById[(int) ($class['id'] ?? 0)] = (string) ($class['label'] ?? '');
        if (($class['label'] ?? '') !== '') {
            $classLabels[] = (string) $class['label'];
        }
    }
    if ($classLabels === array()) {
        reject('Belum ada kelas Praktikum aktif untuk dijadikan pilihan roster.', 409);
    }

    $rows = array($headers);
    foreach ($roster as $student) {
        $groupLabels = array_map(static function (array $group): string {
            return (string) ($group['label'] ?? $group['kode_kelompok'] ?? '');
        }, $student['frekuensi_aktif'] ?? array());
        $targetId = $student['id_kelas_tujuan_default'] ?? null;
        $rows[] = array(
            (string) ($student['nim'] ?? ''),
            (string) ($student['nama'] ?? ''),
            trim((string) ($student['kode_mata_kuliah'] ?? '').' · '.(string) ($student['nama_mata_kuliah'] ?? ''), ' ·'),
            implode(' / ', array_filter($groupLabels)),
            $targetId === null ? '' : ($optionsById[(int) $targetId] ?? ''),
            '',
            '',
        );
    }

    // Leave blank data rows with the same dropdown so administrators can append students.
    $headerCount = count($rows);
    $maxDataRow = max($headerCount + 500, 501);
    $rosterXml = '';
    for ($rowNumber = 1; $rowNumber <= $maxDataRow; $rowNumber++) {
        $values = $rows[$rowNumber - 1] ?? array();
        if ($values === array()) {
            continue;
        }
        $rosterXml .= '<row r="'.$rowNumber.'" ht="24" customHeight="1">';
        foreach ($values as $index => $value) {
            $column = column_name($index + 1);
            $style = $rowNumber === 1 ? 1 : 0;
            $rosterXml .= '<c r="'.$column.$rowNumber.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.xml_escape($value).'</t></is></c>';
        }
        $rosterXml .= '</row>';
    }

    $classXml = '<row r="1"><c r="A1" s="1" t="inlineStr"><is><t>Daftar kelas tujuan</t></is></c></row>';
    foreach ($classLabels as $index => $label) {
        $rowNumber = $index + 2;
        $classXml .= '<row r="'.$rowNumber.'"><c r="A'.$rowNumber.'" t="inlineStr"><is><t xml:space="preserve">'.xml_escape($label).'</t></is></c></row>';
    }

    $rosterSheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        .'<cols><col min="1" max="1" width="18" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="34" customWidth="1"/><col min="4" max="4" width="48" customWidth="1"/><col min="5" max="5" width="44" customWidth="1"/><col min="6" max="6" width="18" customWidth="1"/><col min="7" max="7" width="42" customWidth="1"/></cols>'
        .'<sheetData>'.$rosterXml.'</sheetData><autoFilter ref="A1:G'.$maxDataRow.'"/>'
        .'<dataValidations count="1"><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorStyle="stop" errorTitle="Frekuensi tidak valid" error="Pilih frekuensi dari daftar kelas aktif." sqref="E2:E'.$maxDataRow.'"><formula1>FrekuensiTujuan</formula1></dataValidation></dataValidations>'
        .'<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/></worksheet>';
    $classSheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData>'.$classXml.'</sheetData></worksheet>';

    $file = tempnam(sys_get_temp_dir(), 'iclabs_roster_');
    $zip = new ZipArchive();
    try {
        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            reject('Tidak dapat membuat template Excel roster.', 500);
        }
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Roster" sheetId="1" r:id="rId1"/><sheet name="Daftar Kelas" sheetId="2" state="hidden" r:id="rId2"/></sheets><definedNames><definedName name="FrekuensiTujuan">\'Daftar Kelas\'!$A$2:$A$'.(count($classLabels) + 1).'</definedName></definedNames></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $rosterSheet);
        $zip->addFromString('xl/worksheets/sheet2.xml', $classSheet);
        $zip->addFromString('xl/styles.xml', '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123B5D"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0" fillId="0" borderId="0" xfId="0"/><xf fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.filesize($file));
        readfile($file);
    } finally {
        @unlink($file);
    }
}

function xlsx_schedule_template_download(array $references = array(), array $classes = array(), $filename = 'template-jadwal.xlsx')
{
    if (!class_exists('ZipArchive')) {
        reject('Ekstensi ZIP PHP diperlukan untuk membuat template Excel.', 503);
    }

    $headers = array(
        'tahun akademik',
        'semester',
        'prodi',
        'kode mk',
        'laboratorium',
        'email dosen',
        'email asisten 1',
        'email asisten 2',
        'email pendamping',
        'hari',
        'jam',
        'aktif',
        'frekuensi',
    );

    // Build reference lists with sensible defaults so named ranges are never empty.
    $years = array();
    $semesters = array();
    foreach ((array) ($references['periode_akademik'] ?? array()) as $p) {
        if (!empty($p['tahun_akademik'])) {
            $years[] = trim((string) $p['tahun_akademik']);
        }
        $sem = trim((string) ($p['nama_semester'] ?? $p['semester'] ?? ''));
        if ($sem !== '') {
            $semesters[] = $sem;
        }
    }
    foreach ($classes as $c) {
        if (!empty($c['periode']['tahun_akademik'])) {
            $years[] = trim((string) $c['periode']['tahun_akademik']);
        }
        if (!empty($c['periode']['semester'])) {
            $semesters[] = trim((string) $c['periode']['semester']);
        }
    }
    $years = array_values(array_unique(array_filter($years)));
    if ($years === array()) {
        $years = array('2026/2027');
    }
    $semesters = array_values(array_unique(array_merge($semesters, array('Ganjil', 'Genap'))));

    $programs = array();
    foreach ((array) ($references['program_studi'] ?? array()) as $pr) {
        $name = trim((string) ($pr['nama'] ?? ''));
        if ($name !== '') {
            $programs[] = array(
                'nama' => $name,
                'kode' => trim((string) ($pr['kode'] ?? '')),
            );
        }
    }
    if ($programs === array()) {
        $programs = array(
            array('nama' => 'Teknik Informatika', 'kode' => 'TI'),
            array('nama' => 'Sistem Informasi', 'kode' => 'SI'),
        );
    }

    $courses = array();
    foreach ((array) ($references['mata_kuliah'] ?? array()) as $mk) {
        $code = trim((string) ($mk['kode'] ?? ''));
        if ($code !== '') {
            $courses[] = array(
                'kode' => $code,
                'nama' => trim((string) ($mk['nama'] ?? '')),
            );
        }
    }
    if ($courses === array()) {
        $courses = array(
            array('kode' => '130106RP4', 'nama' => 'Pemrograman'),
            array('kode' => '130107RP4', 'nama' => 'Algoritma dan Pemrograman'),
        );
    }

    $labs = array();
    foreach ((array) ($references['laboratorium'] ?? array()) as $lab) {
        $name = trim((string) ($lab['nama'] ?? ''));
        if ($name !== '') {
            $labs[] = array(
                'nama' => $name,
                'kode' => trim((string) ($lab['kode'] ?? '')),
            );
        }
    }
    if ($labs === array()) {
        $labs = array(
            array('nama' => 'Lab Startup', 'kode' => 'LAB-STARTUP'),
        );
    }

    $lecturers = array();
    foreach ((array) ($references['dosen'] ?? array()) as $d) {
        $email = trim((string) ($d['email'] ?? ''));
        if ($email !== '') {
            $lecturers[] = array(
                'email' => $email,
                'nama' => trim((string) ($d['nama'] ?? '')),
            );
        }
    }
    if ($lecturers === array()) {
        $lecturers = array(
            array('email' => 'dosen@fikom.umi.ac.id', 'nama' => 'Dosen Pengampu Contoh'),
        );
    }

    $assistants = array();
    $seenAssistantEmails = array();
    foreach ((array) ($references['asisten'] ?? array()) as $a) {
        $email = trim((string) ($a['email'] ?? ''));
        if ($email !== '') {
            $seenAssistantEmails[mb_strtolower($email, 'UTF-8')] = true;
            $assistants[] = array(
                'email' => $email,
                'nama' => trim((string) ($a['nama'] ?? '')),
            );
        }
    }
    foreach (array(
        array('email' => '13020230049@student.umi.ac.id', 'nama' => 'Asisten 1 (Contoh)'),
        array('email' => '13020230100@student.umi.ac.id', 'nama' => 'Asisten 2 (Contoh)'),
    ) as $defaultAssistant) {
        $key = mb_strtolower($defaultAssistant['email'], 'UTF-8');
        if (!isset($seenAssistantEmails[$key])) {
            $seenAssistantEmails[$key] = true;
            $assistants[] = $defaultAssistant;
        }
    }

    $classItems = array();
    $seenFreqs = array();
    foreach ($classes as $c) {
        $freqCode = trim((string) ($c['kelompok']['kode'] ?? ''));
        if ($freqCode === '') {
            continue;
        }
        $infoParts = array_filter(array(
            trim((string) ($c['program_studi']['nama'] ?? '')),
            trim((string) ($c['mata_kuliah']['kode'] ?? '')),
            trim((string) ($c['mata_kuliah']['nama'] ?? '')),
            ((int) ($c['jumlah_jadwal'] ?? 0) > 0) ? 'Sudah terjadwal' : 'Belum terjadwal',
        ));
        if (!isset($seenFreqs[$freqCode])) {
            $seenFreqs[$freqCode] = true;
            $classItems[] = array(
                'frekuensi' => $freqCode,
                'info' => implode(' · ', $infoParts),
            );
        }
    }
    if ($classItems === array()) {
        $classItems = array(
            array('frekuensi' => 'TI_ALPRO-1', 'info' => 'Teknik Informatika · 130106RP4 · Contoh Frekuensi'),
        );
    }

    // Select 1 valid example record (preferring an unscheduled class with complete period dates).
    $exampleYear = '2026/2027';
    $exampleSemester = 'Ganjil';
    $exampleProdi = 'Teknik Informatika';
    $exampleCourseCode = '130106RP4';
    $exampleLab = 'Lab Startup';
    $exampleLecturer = $lecturers[0]['email'];
    $exampleAssistant1 = '13020230049@student.umi.ac.id';
    $exampleAssistant2 = '13020230100@student.umi.ac.id';
    $exampleFreq = 'TI_ALPRO-1';

    $chosenClass = null;
    foreach ($classes as $c) {
        if ((int) ($c['jumlah_jadwal'] ?? 0) === 0 && !empty($c['periode']['tanggal_mulai']) && !empty($c['periode']['tanggal_selesai'])) {
            $chosenClass = $c;
            break;
        }
    }
    if ($chosenClass === null) {
        foreach ($classes as $c) {
            if ((int) ($c['jumlah_jadwal'] ?? 0) === 0) {
                $chosenClass = $c;
                break;
            }
        }
    }
    if ($chosenClass !== null) {
        $exampleYear = trim((string) ($chosenClass['periode']['tahun_akademik'] ?? $exampleYear)) ?: $exampleYear;
        $exampleSemester = trim((string) ($chosenClass['periode']['semester'] ?? $exampleSemester)) ?: $exampleSemester;
        $exampleProdi = trim((string) ($chosenClass['program_studi']['nama'] ?? $exampleProdi)) ?: $exampleProdi;
        $exampleCourseCode = trim((string) ($chosenClass['mata_kuliah']['kode'] ?? $exampleCourseCode)) ?: $exampleCourseCode;
        $exampleFreq = trim((string) ($chosenClass['kelompok']['kode'] ?? $exampleFreq)) ?: $exampleFreq;
    } else {
        $exampleYear = $years[0] ?? $exampleYear;
        $exampleSemester = $semesters[0] ?? $exampleSemester;
        $exampleProdi = $programs[0]['nama'] ?? $exampleProdi;
        $exampleCourseCode = $courses[0]['kode'] ?? $exampleCourseCode;
        $exampleFreq = $classItems[0]['frekuensi'] ?? $exampleFreq;
    }

    $labNames = array_column($labs, 'nama');
    if (!in_array($exampleLab, $labNames, true)) {
        $exampleLab = $labs[0]['nama'];
    }

    $exampleRow = array(
        $exampleYear,
        $exampleSemester,
        $exampleProdi,
        $exampleCourseCode,
        $exampleLab,
        $exampleLecturer,
        $exampleAssistant1,
        $exampleAssistant2,
        '',
        'Selasa',
        '07.00-10.20',
        'Aktif',
        $exampleFreq,
    );

    $sheetRows = array($headers, $exampleRow);
    $maxDataRow = 200;
    $sheet1Xml = '';
    foreach ($sheetRows as $rIndex => $rowValues) {
        $rowNumber = $rIndex + 1;
        $height = $rowNumber === 1 ? '28' : '22';
        $style = $rowNumber === 1 ? 1 : 2;
        $sheet1Xml .= '<row r="'.$rowNumber.'" ht="'.$height.'" customHeight="1">';
        foreach ($rowValues as $cIndex => $val) {
            $col = column_name($cIndex + 1);
            $sheet1Xml .= '<c r="'.$col.$rowNumber.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.xml_escape($val).'</t></is></c>';
        }
        $sheet1Xml .= '</row>';
    }

    // Build Sheet 2 (Referensi Master) so dropdown named ranges work & users can view names alongside codes/emails.
    $refHeaders = array(
        'Tahun Akademik',
        'Semester',
        'Program Studi',
        'Kode MK',
        'Nama Mata Kuliah (Info)',
        'Laboratorium',
        'Email Dosen',
        'Nama Dosen (Info)',
        'Email Asisten',
        'Nama Asisten (Info)',
        'Kode Frekuensi Kelas',
        'Detail Kelas / Status Jadwal (Info)',
    );
    $maxRefRows = max(
        count($years),
        count($semesters),
        count($programs),
        count($courses),
        count($labs),
        count($lecturers),
        count($assistants),
        count($classItems),
        1
    );

    $sheet2Xml = '<row r="1" ht="26" customHeight="1">';
    foreach ($refHeaders as $cIndex => $hText) {
        $col = column_name($cIndex + 1);
        $sheet2Xml .= '<c r="'.$col.'1" s="1" t="inlineStr"><is><t xml:space="preserve">'.xml_escape($hText).'</t></is></c>';
    }
    $sheet2Xml .= '</row>';

    for ($i = 0; $i < $maxRefRows; $i++) {
        $rowNumber = $i + 2;
        $cells = array(
            0 => $years[$i] ?? '',
            1 => $semesters[$i] ?? '',
            2 => $programs[$i]['nama'] ?? '',
            3 => $courses[$i]['kode'] ?? '',
            4 => $courses[$i]['nama'] ?? '',
            5 => $labs[$i]['nama'] ?? '',
            6 => $lecturers[$i]['email'] ?? '',
            7 => $lecturers[$i]['nama'] ?? '',
            8 => $assistants[$i]['email'] ?? '',
            9 => $assistants[$i]['nama'] ?? '',
            10 => $classItems[$i]['frekuensi'] ?? '',
            11 => $classItems[$i]['info'] ?? '',
        );
        $sheet2Xml .= '<row r="'.$rowNumber.'" ht="20" customHeight="1">';
        foreach ($cells as $cIndex => $val) {
            if ($val === '') {
                continue;
            }
            $col = column_name($cIndex + 1);
            $sheet2Xml .= '<c r="'.$col.$rowNumber.'" s="2" t="inlineStr"><is><t xml:space="preserve">'.xml_escape($val).'</t></is></c>';
        }
        $sheet2Xml .= '</row>';
    }

    $validations = array(
        array('sqref' => 'A2:A'.$maxDataRow, 'formula' => 'RefTahunAkademik', 'title' => 'Tahun Akademik', 'prompt' => 'Pilih tahun akademik periode (contoh: 2026/2027).', 'stop' => false),
        array('sqref' => 'B2:B'.$maxDataRow, 'formula' => 'RefSemester', 'title' => 'Semester', 'prompt' => 'Pilih Ganjil atau Genap.', 'stop' => true),
        array('sqref' => 'C2:C'.$maxDataRow, 'formula' => 'RefProdi', 'title' => 'Program Studi', 'prompt' => 'Pilih nama Program Studi sesuai daftar master.', 'stop' => false),
        array('sqref' => 'D2:D'.$maxDataRow, 'formula' => 'RefKodeMk', 'title' => 'Kode Mata Kuliah', 'prompt' => 'Pilih Kode MK (cek nama MK di sheet Referensi Master).', 'stop' => false),
        array('sqref' => 'E2:E'.$maxDataRow, 'formula' => 'RefLaboratorium', 'title' => 'Laboratorium', 'prompt' => 'Pilih nama Laboratorium pelaksanaan praktikum.', 'stop' => false),
        array('sqref' => 'F2:F'.$maxDataRow, 'formula' => 'RefEmailDosen', 'title' => 'Email Dosen Pengampu', 'prompt' => 'Pilih email Dosen (cek nama dosen di sheet Referensi Master).', 'stop' => false),
        array('sqref' => 'G2:G'.$maxDataRow, 'formula' => 'RefEmailAsisten', 'title' => 'Email Asisten 1 (Utama)', 'prompt' => 'Wajib: Pilih email Asisten Utama.', 'stop' => false),
        array('sqref' => 'H2:H'.$maxDataRow, 'formula' => 'RefEmailAsisten', 'title' => 'Email Asisten 2 (Opsional)', 'prompt' => 'Opsional: Pilih email Asisten Pengajar Pendukung atau kosongkan.', 'stop' => false),
        array('sqref' => 'I2:I'.$maxDataRow, 'formula' => 'RefEmailAsisten', 'title' => 'Email Pendamping (Opsional)', 'prompt' => 'Opsional: Pilih email Pendamping Teknis / Asisten 3 atau kosongkan.', 'stop' => false),
        array('sqref' => 'J2:J'.$maxDataRow, 'formula' => '"Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu"', 'title' => 'Hari Praktikum', 'prompt' => 'Pilih hari pelaksanaan (Senin s/d Minggu).', 'stop' => true),
        array('sqref' => 'L2:L'.$maxDataRow, 'formula' => '"Aktif,Nonaktif"', 'title' => 'Status Jadwal', 'prompt' => 'Pilih Aktif atau Nonaktif.', 'stop' => true),
        array('sqref' => 'M2:M'.$maxDataRow, 'formula' => 'RefFrekuensi', 'title' => 'Kode Frekuensi Kelas', 'prompt' => 'Pilih atau ketik kode frekuensi kelas praktikum (contoh: TI_ALPRO-1).', 'stop' => false),
    );

    $dvXml = '<dataValidations count="'.(count($validations) + 1).'">';
    foreach ($validations as $dv) {
        $errorAttr = $dv['stop']
            ? 'showErrorMessage="1" errorStyle="stop" errorTitle="Pilihan tidak sesuai" error="Pilih nilai dari daftar dropdown."'
            : 'showErrorMessage="0"';
        $dvXml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" '.$errorAttr.' promptTitle="'.xml_escape($dv['title']).'" prompt="'.xml_escape($dv['prompt']).'" sqref="'.$dv['sqref'].'"><formula1>'.xml_escape($dv['formula']).'</formula1></dataValidation>';
    }
    $dvXml .= '<dataValidation type="none" allowBlank="1" showInputMessage="1" promptTitle="Format Jam Praktikum" prompt="Ketik jam mulai dan selesai dengan format HH.MM-HH.MM (contoh: 07.00-10.20)." sqref="K2:K'.$maxDataRow.'"/>';
    $dvXml .= '</dataValidations>';

    $sheet1 = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        .'<cols>'
        .'<col min="1" max="1" width="18" customWidth="1"/>'
        .'<col min="2" max="2" width="14" customWidth="1"/>'
        .'<col min="3" max="3" width="26" customWidth="1"/>'
        .'<col min="4" max="4" width="18" customWidth="1"/>'
        .'<col min="5" max="5" width="26" customWidth="1"/>'
        .'<col min="6" max="9" width="30" customWidth="1"/>'
        .'<col min="10" max="10" width="15" customWidth="1"/>'
        .'<col min="11" max="11" width="18" customWidth="1"/>'
        .'<col min="12" max="12" width="14" customWidth="1"/>'
        .'<col min="13" max="13" width="22" customWidth="1"/>'
        .'</cols>'
        .'<sheetData>'.$sheet1Xml.'</sheetData>'
        .'<autoFilter ref="A1:M'.$maxDataRow.'"/>'
        .$dvXml
        .'<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/></worksheet>';

    $sheet2 = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        .'<cols>'
        .'<col min="1" max="2" width="16" customWidth="1"/>'
        .'<col min="3" max="3" width="26" customWidth="1"/>'
        .'<col min="4" max="4" width="18" customWidth="1"/>'
        .'<col min="5" max="5" width="34" customWidth="1"/>'
        .'<col min="6" max="6" width="26" customWidth="1"/>'
        .'<col min="7" max="10" width="28" customWidth="1"/>'
        .'<col min="11" max="11" width="22" customWidth="1"/>'
        .'<col min="12" max="12" width="46" customWidth="1"/>'
        .'</cols>'
        .'<sheetData>'.$sheet2Xml.'</sheetData>'
        .'<autoFilter ref="A1:L'.($maxRefRows + 1).'"/>'
        .'</worksheet>';

    $definedNames = '<definedNames>'
        .'<definedName name="RefTahunAkademik">\'Referensi Master\'!$A$2:$A$'.(count($years) + 1).'</definedName>'
        .'<definedName name="RefSemester">\'Referensi Master\'!$B$2:$B$'.(count($semesters) + 1).'</definedName>'
        .'<definedName name="RefProdi">\'Referensi Master\'!$C$2:$C$'.(count($programs) + 1).'</definedName>'
        .'<definedName name="RefKodeMk">\'Referensi Master\'!$D$2:$D$'.(count($courses) + 1).'</definedName>'
        .'<definedName name="RefLaboratorium">\'Referensi Master\'!$F$2:$F$'.(count($labs) + 1).'</definedName>'
        .'<definedName name="RefEmailDosen">\'Referensi Master\'!$G$2:$G$'.(count($lecturers) + 1).'</definedName>'
        .'<definedName name="RefEmailAsisten">\'Referensi Master\'!$I$2:$I$'.(count($assistants) + 1).'</definedName>'
        .'<definedName name="RefFrekuensi">\'Referensi Master\'!$K$2:$K$'.(count($classItems) + 1).'</definedName>'
        .'</definedNames>';

    $stylesXml = '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<fonts count="2">'
        .'<font><sz val="10"/><name val="Calibri"/><color rgb="FF0F172A"/></font>'
        .'<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
        .'</fonts>'
        .'<fills count="4">'
        .'<fill><patternFill patternType="none"/></fill>'
        .'<fill><patternFill patternType="gray125"/></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FF123B5D"/><bgColor indexed="64"/></patternFill></fill>'
        .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>'
        .'</fills>'
        .'<borders count="2">'
        .'<border/>'
        .'<border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom></border>'
        .'</borders>'
        .'<cellStyleXfs count="1"><xf/></cellStyleXfs>'
        .'<cellXfs count="3">'
        .'<xf fontId="0" fillId="0" borderId="0" xfId="0"/>'
        .'<xf fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        .'<xf fontId="0" fillId="3" borderId="1" xfId="0" applyAlignment="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>'
        .'</cellXfs>'
        .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        .'</styleSheet>';

    $file = tempnam(sys_get_temp_dir(), 'iclabs_sched_');
    $zip = new ZipArchive();
    try {
        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            reject('Tidak dapat membuat template Excel jadwal.', 500);
        }
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Jadwal Praktikum" sheetId="1" r:id="rId1"/><sheet name="Referensi Master" sheetId="2" r:id="rId2"/></sheets>'.$definedNames.'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
        $zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);
        $zip->addFromString('xl/styles.xml', $stylesXml);
        $zip->close();

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.filesize($file));
        readfile($file);
    } finally {
        @unlink($file);
    }
}

function component_value(
    $items,
    $number
) {
    foreach ($items as $x) {
        if ((int) $x['number'] === $number) {
            return $x['value'];
        }
    }

    return null;
}

function export_report($d, $format)
{
    $c = $d['class'];

    if ($format === 'pdf') {
        pdf_report($d);
        return;
    }

    if ($format !== 'xlsx') {
        reject('Format tidak valid.');
    }

    $rows = array(
        array(
            'Absensi & Nilai Praktikum Semester ' .
            $c['semester'] .
            ' ' .
            $c['year']
        ),
        array('ICLabs Praktikum'),
        array(
            'KODE MATAKULIAH',
            $c['code']
        ),
        array(
            'NAMA MATAKULIAH',
            $c['course']
        ),
        array(
            'FREKUENSI',
            $c['class']
        ),
        array(
            'HARI/JAM',
            $c['day'] . ' / ' . $c['time']
        ),
        array(
            'RUANGAN',
            $c['lab']
        ),
        array(
            'DOSEN',
            $c['lecturer']
        ),
        array(
            'ASISTEN 1',
            $c['assistant1']
        ),
        array(
            'ASISTEN 2',
            $c['assistant2']
        ),
        array()
    );

    $headers = array(
        'NO',
        'STAMBUK',
        'NAMA MAHASISWA',
        'KELAS'
    );

    for (
        $i = 1;
        $i <= $c['meeting_count'];
        $i++
    ) {
        $headers[] = 'P' . $i;
    }

    foreach (($c['assessment_columns'] ?? array()) as $column) {
        $headers[] = $column['label'];
    }

    for (
        $i = 1;
        $i <= $c['evaluation_count'];
        $i++
    ) {
        $headers[] = 'E' . $i;
    }

    $headers[] =
        'RATA-RATA EVALUASI';

    for (
        $i = 1;
        $i <= $c['assistance_count'];
        $i++
    ) {
        $headers[] = 'A' . $i;
    }

    $headers = array_merge(
        $headers,
        array(
            'RATA-RATA ASISTENSI',
            'MID',
            'UAS',
            'NA',
            'NILAI HURUF'
        )
    );

    $rows[] = $headers;

    foreach (
        $d['students'] as $index => $s
    ) {
        $r = array(
            $index + 1,
            $s['nim'],
            $s['name'],
            $c['class']
        );

        foreach (
            array(
                'attendance' => 'meeting_count',
                'evaluations' => 'evaluation_count'
            ) as $k => $count
        ) {
            for (
                $i = 1;
                $i <= $c[$count];
                $i++
            ) {
                $r[] = component_value(
                    $s[$k],
                    $i
                );
            }

            if ($k === 'attendance') {
                foreach (($c['assessment_columns'] ?? array()) as $column) {
                    $r[] = $s['meeting_assessments'][$column['key']] ?? null;
                }
            }
        }

        $r[] = $s['ev'];

        for (
            $i = 1;
            $i <= $c['assistance_count'];
            $i++
        ) {
            $r[] = component_value(
                $s['assistance'],
                $i
            );
        }

        $r = array_merge(
            $r,
            array(
                $s['as'],
                $s['mid'],
                $s['uas'],
                $s['na'],
                $s['letter']
            )
        );

        $rows[] = $r;
    }

    xlsx_download(
        $rows,
        'praktikum-' . $c['id'] . '.xlsx',
        12
    );
}

function pdf_text(
    $text,
    $x,
    $y,
    $size = 9
) {
    $text = iconv(
        'UTF-8',
        'Windows-1252//TRANSLIT',
        str_replace(
            array('—', '–'),
            '-',
            (string) $text
        )
    );

    $text = str_replace(
        array(
            '\\',
            '(',
            ')',
            "\r",
            "\n"
        ),
        array(
            '\\\\',
            '\\(',
            '\\)',
            ' ',
            ' '
        ),
        $text
    );

    return
        "BT /F1 $size Tf $x $y Td ($text) Tj ET\n";
}

function pdf_report($d)
{
    $c = $d['class'];

    $chunks = array_chunk(
        $d['students'],
        17
    );

    if (!$chunks) {
        $chunks = array(
            array()
        );
    }

    $assessmentPageGroups = array();
    foreach (($c['assessment_sheets'] ?? array()) as $sheet) {
        if (($sheet['komponen'] ?? array()) === array()) {
            continue;
        }
        $legendParts = array();
        foreach ($sheet['komponen'] as $index => $component) {
            $legendParts[] = 'C' . ($index + 1) . ' ' . $component['nama'] . ' (' . number_format((float) $component['bobot_persen'], 2) . '%)';
        }
        $legendLines = explode("\n", wordwrap(implode(' · ', $legendParts), 100, "\n", true));
        $headerY = 499 - count($legendLines) * 12 - 3;
        $rowsPerPage = max(1, min(16, (int) floor(($headerY - 60) / 20)));
        $assessmentPageGroups[] = array(
            'sheet' => $sheet,
            'chunks' => array_chunk($d['students'], $rowsPerPage),
            'legend_lines' => $legendLines,
            'header_y' => $headerY,
            'rows_per_page' => $rowsPerPage,
        );
    }
    $totalPageCount = count($chunks);
    foreach ($assessmentPageGroups as $group) {
        $totalPageCount += count($group['chunks']);
    }

    $streams = array();

    $xs = array(
        30,
        58,
        155,
        330,
        405,
        464,
        520,
        580,
        630,
        686,
        743
    );

    foreach (
        $chunks as $page => $students
    ) {
        $stream = pdf_text(
            'Absensi & Nilai Praktikum - ' .
            $c['semester'] .
            ' ' .
            $c['year'],
            30,
            560,
            15
        );

        $stream .= pdf_text(
            'ICLabs Praktikum - Universitas Muslim Indonesia',
            30,
            539,
            10
        );

        $stream .= pdf_text(
            $c['code'] .
            ' | ' .
            $c['course'] .
            ' | Frekuensi ' .
            $c['class'],
            30,
            519,
            10
        );

        $stream .= pdf_text(
            $c['day'] .
            ' ' .
            $c['time'] .
            ' | ' .
            $c['lab'] .
            ' | Dosen: ' .
            $c['lecturer'],
            30,
            500
        );

        $stream .= pdf_text(
            'Asisten: ' .
            $c['assistant1'] .
            ' / ' .
            $c['assistant2'] .
            ' | Status: ' .
            $c['state'],
            30,
            483
        );

        $stream .=
            "0.957 0.812 0.271 rg 26 452 789 22 re f 0 0 0 rg\n";

        $head = array(
            'No',
            'NIM',
            'Nama mahasiswa',
            'H/I/S/A/T',
            'Hadir %',
            'Evaluasi',
            'Asistensi',
            'MID',
            'UAS',
            'NA',
            'Huruf'
        );

        foreach ($head as $i => $h) {
            $stream .= pdf_text(
                $h,
                $xs[$i],
                459
            );
        }

        $y = 434;

        foreach (
            $students as $i => $s
        ) {
            $counts = array();

            foreach (
                array(
                    'H',
                    'I',
                    'S',
                    'A',
                    'T'
                ) as $status
            ) {
                $counts[] = count(
                    array_filter(
                        $s['attendance'],
                        function ($a) use ($status) {
                            return $a['value'] === $status;
                        }
                    )
                );
            }

            $vals = array(
                $page * 17 + $i + 1,
                $s['nim'],
                mb_strimwidth(
                    $s['name'],
                    0,
                    31,
                    '...',
                    'UTF-8'
                ),
                implode(
                    '/',
                    $counts
                ),
                number_format(
                    $s['presence'],
                    1
                )
            );

            foreach (
                array(
                    'ev',
                    'as',
                    'mid',
                    'uas',
                    'na'
                ) as $key
            ) {
                $vals[] =
                    $s[$key] === null
                        ? '-'
                        : number_format(
                            $s[$key],
                            1
                        );
            }

            $vals[] = $s['letter'];

            foreach ($vals as $j => $v) {
                $stream .= pdf_text(
                    $v,
                    $xs[$j],
                    $y
                );
            }

            $stream .=
                "0.86 G 26 " .
                ($y - 8) .
                " m 815 " .
                ($y - 8) .
                " l S 0 G\n";

            $y -= 21;
        }

        $stream .= pdf_text(
            'NA = Evaluasi 20% + Asistensi 20% + MID 30% + UAS 30%.',
            30,
            35
        );

        $stream .= pdf_text(
            'Halaman ' .
            ($page + 1) .
            ' / ' .
            $totalPageCount,
            725,
            35
        );

        $streams[] = $stream;
    }

    foreach ($assessmentPageGroups as $group) {
        $sheet = $group['sheet'];
        $meetingNumber = (int) ($sheet['nomor_pertemuan'] ?? 0);
        $components = $sheet['komponen'];

        foreach ($group['chunks'] as $detailPage => $students) {
            $pageNumber = count($streams) + 1;
            $stream = pdf_text(
                'Detail Nilai Pertemuan ' . $meetingNumber,
                30,
                560,
                15
            );
            $stream .= pdf_text(
                $c['code'] . ' | ' . $c['course'] . ' | Frekuensi ' . $c['class'],
                30,
                539,
                10
            );
            $stream .= pdf_text(
                'Tanggal sesi: ' . ($sheet['tanggal_sesi'] ?? 'belum tersedia') . ' | Total dihitung dari bobot kategori pada skema lembar.',
                30,
                520,
                9
            );

            $legendY = 502;
            foreach ($group['legend_lines'] as $legendLine) {
                $stream .= pdf_text($legendLine, 30, $legendY, 8);
                $legendY -= 12;
            }

            $headers = array('No', 'NIM', 'Nama');
            foreach ($components as $index => $component) {
                $headers[] = 'C' . ($index + 1);
            }
            $headers[] = 'Total';
            $headerY = $group['header_y'];
            $stream .= "0.957 0.812 0.271 rg 26 " . ($headerY - 7) . " 789 20 re f 0 0 0 rg\n";

            $columnPositions = array(30, 58, 155);
            $startX = 310;
            $columnStep = max(20, min(42, (int) floor(480 / (count($components) + 1))));
            foreach ($components as $index => $component) {
                $columnPositions[] = $startX + $index * $columnStep;
            }
            $columnPositions[] = $startX + count($components) * $columnStep + 2;
            foreach ($headers as $index => $header) {
                $stream .= pdf_text($header, $columnPositions[$index], $headerY, 8);
            }

            $y = $headerY - 25;
            foreach ($students as $index => $student) {
                $values = array(
                    $detailPage * $group['rows_per_page'] + $index + 1,
                    $student['nim'],
                    mb_strimwidth($student['name'], 0, 25, '...', 'UTF-8'),
                );
                foreach ($components as $component) {
                    $score = $student['meeting_assessments'][$component['key']] ?? null;
                    $values[] = $score === null ? '-' : number_format((float) $score, 2);
                }
                $total = $student['meeting_assessments'][$meetingNumber . ':total'] ?? null;
                $values[] = $total === null ? '-' : number_format((float) $total, 2);

                foreach ($values as $valueIndex => $value) {
                    $stream .= pdf_text($value, $columnPositions[$valueIndex], $y, 8);
                }
                $stream .= "0.86 G 26 " . ($y - 8) . " m 815 " . ($y - 8) . " l S 0 G\n";
                $y -= 20;
            }

            $stream .= pdf_text('Halaman ' . $pageNumber . ' / ' . $totalPageCount, 725, 35);
            $streams[] = $stream;
        }
    }

    $objects = array(
        1 =>
            '<< /Type /Catalog /Pages 2 0 R >>',
        2 =>
            '',
        3 =>
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'
    );

    $kids = array();

    foreach (
        $streams as $i => $stream
    ) {
        $p = 4 + $i * 2;
        $content = $p + 1;

        $kids[] = "$p 0 R";

        $objects[$p] =
            '<< /Type /Page ' .
            '/Parent 2 0 R ' .
            '/MediaBox [0 0 842 595] ' .
            '/Resources << ' .
                '/Font << /F1 3 0 R >> ' .
            '>> ' .
            "/Contents $content 0 R >>";

        $objects[$content] =
            '<< /Length ' .
            strlen($stream) .
            ">>\nstream\n" .
            $stream .
            'endstream';
    }

    $objects[2] =
        '<< /Type /Pages /Kids [' .
        implode(' ', $kids) .
        '] /Count ' .
        count($kids) .
        ' >>';

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = array(0);

    foreach (
        $objects as $id => $object
    ) {
        $offsets[$id] =
            strlen($pdf);

        $pdf .=
            "$id 0 obj\n" .
            $object .
            "\nendobj\n";
    }

    $start = strlen($pdf);

    $pdf .=
        "xref\n0 " .
        (count($objects) + 1) .
        "\n0000000000 65535 f \n";

    foreach (
        $objects as $id => $unused
    ) {
        $pdf .= sprintf(
            '%010d 00000 n ',
            $offsets[$id]
        ) . "\n";
    }

    $pdf .=
        'trailer << /Size ' .
        (count($objects) + 1) .
        ' /Root 1 0 R >>' .
        "\nstartxref\n" .
        $start .
        "\n%%EOF";

    header(
        'Content-Type: application/pdf'
    );

    header(
        'Content-Disposition: attachment; filename="praktikum-' .
        $c['id'] .
        '.pdf"'
    );

    echo $pdf;
}
