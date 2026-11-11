<?php

require_once __DIR__ . '/exchange.php';

class PeriodReportWorkbook
{
    private $sheets = array();
    private $sheetNames = array();

    public static function sanitizeSheetName(string $name, array $existingNames = array()): string
    {
        $clean = preg_replace('/[\\\\\\/\?\*\:\[\]]/', '_', $name);
        $clean = trim((string) $clean, "' \t\n\r\0\x0B");
        if ($clean === '') {
            $clean = 'Frekuensi';
        }
        $base = mb_substr($clean, 0, 27);
        $candidate = $base;
        $counter = 2;
        $lowerExisting = array_map('mb_strtolower', $existingNames);
        while (in_array(mb_strtolower($candidate), $lowerExisting, true)) {
            $suffix = '-' . $counter;
            $candidate = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $counter++;
        }
        return $candidate;
    }

    public function addSheet(string $title, array $options = array()): int
    {
        $safeName = self::sanitizeSheetName($title, $this->sheetNames);
        $this->sheetNames[] = $safeName;
        $sheetIndex = count($this->sheets);
        $this->sheets[] = array(
            'name' => $safeName,
            'rows' => array(),
            'merges' => array(),
            'options' => $options,
            'max_col' => 1,
        );
        return $sheetIndex;
    }

    public function mergeCells(int $sheetIndex, string $range): void
    {
        if (isset($this->sheets[$sheetIndex])) {
            $this->sheets[$sheetIndex]['merges'][] = $range;
        }
    }

    public function getRowCount(int $sheetIndex): int
    {
        return count($this->sheets[$sheetIndex]['rows'] ?? array());
    }

    public function addRow(int $sheetIndex, array $cells, $height = null): void
    {
        if (!isset($this->sheets[$sheetIndex])) {
            return;
        }
        $rowNum = count($this->sheets[$sheetIndex]['rows']) + 1;
        $cellCount = count($cells);
        if ($cellCount > $this->sheets[$sheetIndex]['max_col']) {
            $this->sheets[$sheetIndex]['max_col'] = $cellCount;
        }
        $this->sheets[$sheetIndex]['rows'][] = array(
            'num' => $rowNum,
            'cells' => $cells,
            'height' => $height,
        );
    }

    public function buildZip(string $targetFile): void
    {
        $zip = new ZipArchive();
        if ($zip->open($targetFile, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Tidak dapat membuat arsip Excel.');
        }

        $sheetCount = count($this->sheets);
        if ($sheetCount === 0) {
            $this->addSheet('Rekap');
            $sheetCount = 1;
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // xl/workbook.xml & xl/_rels/workbook.xml.rels
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>';
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($this->sheets as $idx => $s) {
            $sId = $idx + 1;
            $rId = 'rId' . $sId;
            $workbookXml .= '<sheet name="' . xml_escape($s['name']) . '" sheetId="' . $sId . '" r:id="' . $rId . '"/>';
            $wbRels .= '<Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sId . '.xml"/>';
        }
        $workbookXml .= '</sheets></workbook>';
        $wbRels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbRels .= '</Relationships>';

        $zip->addFromString('xl/workbook.xml', $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/styles.xml
        $stylesXml = $this->buildStylesXml();
        $zip->addFromString('xl/styles.xml', $stylesXml);

        // Each worksheet
        foreach ($this->sheets as $idx => $sheetData) {
            $sId = $idx + 1;
            $sheetXml = $this->buildSheetXml($sheetData);
            $zip->addFromString('xl/worksheets/sheet' . $sId . '.xml', $sheetXml);
        }

        $zip->close();
    }

    private function buildStylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<numFmts count="2">' .
                '<numFmt numFmtId="164" formatCode="#,##0.00"/>' .
                '<numFmt numFmtId="165" formatCode="0.0%"/>' .
            '</numFmts>' .
            '<fonts count="8">' .
                '<font><sz val="10"/><name val="Calibri"/><color rgb="FF0F172A"/></font>' . // 0: Normal
                '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF0F172A"/></font>' . // 1: Bold
                '<font><b/><sz val="13"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>' . // 2: Title Bold White
                '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>' . // 3: Header Bold White
                '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF991B1B"/></font>' . // 4: Red Alert Bold
                '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF854D0E"/></font>' . // 5: Yellow Alert Bold
                '<font><b/><sz val="10"/><name val="Calibri"/><color rgb="FF166534"/></font>' . // 6: Green Pass Bold
                '<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF1E3A5F"/></font>' . // 7: Navy Subtitle
            '</fonts>' .
            '<fills count="10">' .
                '<fill><patternFill patternType="none"/></fill>' . // 0
                '<fill><patternFill patternType="gray125"/></fill>' . // 1
                '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/><bgColor indexed="64"/></patternFill></fill>' . // 2: Navy Primary
                '<fill><patternFill patternType="solid"><fgColor rgb="FF334155"/><bgColor indexed="64"/></patternFill></fill>' . // 3: Slate Dark
                '<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill>' . // 4: Soft Slate Header
                '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF9C3"/><bgColor indexed="64"/></patternFill></fill>' . // 5: Yellow Alert Fill (3 Alpa)
                '<fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill>' . // 6: Red Alert Fill (4+ Alpa)
                '<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/><bgColor indexed="64"/></patternFill></fill>' . // 7: Green Pass Fill
                '<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>' . // 8: Zebra Light
                '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>' . // 9: Meta Gray Fill
            '</fills>' .
            '<borders count="4">' .
                '<border><left/><right/><top/><bottom/><diagonal/></border>' . // 0: None
                '<border>' . // 1: Thin Box
                    '<left style="thin"><color rgb="FFCBD5E1"/></left>' .
                    '<right style="thin"><color rgb="FFCBD5E1"/></right>' .
                    '<top style="thin"><color rgb="FFCBD5E1"/></top>' .
                    '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>' .
                '</border>' .
                '<border>' . // 2: Summary Row (top thin, bottom double)
                    '<left style="thin"><color rgb="FFCBD5E1"/></left>' .
                    '<right style="thin"><color rgb="FFCBD5E1"/></right>' .
                    '<top style="thin"><color rgb="FF0F172A"/></top>' .
                    '<bottom style="double"><color rgb="FF0F172A"/></bottom>' .
                '</border>' .
                '<border>' . // 3: Header Row (darker bottom)
                    '<left style="thin"><color rgb="FF334155"/></left>' .
                    '<right style="thin"><color rgb="FF334155"/></right>' .
                    '<top style="thin"><color rgb="FF334155"/></top>' .
                    '<bottom style="medium"><color rgb="FF0F172A"/></bottom>' .
                '</border>' .
            '</borders>' .
            '<cellStyleXfs count="1">' .
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' .
            '</cellStyleXfs>' .
            '<cellXfs count="21">' .
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . // 0: Default
                '<xf numFmtId="0" fontId="3" fillId="2" borderId="3" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . // 1: Header Primary Navy
                '<xf numFmtId="0" fontId="3" fillId="3" borderId="3" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . // 2: Header Secondary Slate
                '<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . // 3: Header Light Slate
                '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>' . // 4: Title Banner
                '<xf numFmtId="0" fontId="1" fillId="9" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . // 5: Meta Label
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . // 6: Meta Value
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . // 7: Cell Text Left
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 8: Cell Text Center
                '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 9: Cell Text Center Bold
                '<xf numFmtId="1" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 10: Cell Number Integer
                '<xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 11: Cell Number Decimal (0.00)
                '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 12: Cell Percentage (0.0%)
                '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 13: Cell Status Center Normal
                '<xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 14: Cell Alert Yellow (3 Alpa)
                '<xf numFmtId="0" fontId="4" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 15: Cell Alert Red (4+ Alpa)
                '<xf numFmtId="0" fontId="6" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 16: Cell Pass Green
                '<xf numFmtId="0" fontId="1" fillId="9" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>' . // 17: Summary Label
                '<xf numFmtId="2" fontId="1" fillId="9" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 18: Summary Decimal
                '<xf numFmtId="1" fontId="1" fillId="9" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 19: Summary Integer
                '<xf numFmtId="165" fontId="1" fillId="9" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . // 20: Summary Percentage
            '</cellXfs>' .
            '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
            '</styleSheet>';
    }

    private function buildSheetXml(array $sheetData): string
    {
        $options = $sheetData['options'] ?? array();
        $rowsXml = '';

        foreach ($sheetData['rows'] as $r) {
            $rowNum = $r['num'];
            $heightAttr = !empty($r['height']) ? ' ht="' . (float) $r['height'] . '" customHeight="1"' : '';
            $rowsXml .= '<row r="' . $rowNum . '"' . $heightAttr . '>';
            foreach ($r['cells'] as $colIdx => $cell) {
                $colLetter = column_name($colIdx + 1);
                $ref = $colLetter . $rowNum;

                if (!is_array($cell)) {
                    $val = $cell;
                    $style = 0;
                    $formula = null;
                } else {
                    $val = $cell['v'] ?? null;
                    $style = (int) ($cell['s'] ?? 0);
                    $formula = $cell['f'] ?? null;
                }

                if ($formula !== null) {
                    $fVal = $val !== null ? '<v>' . (is_numeric($val) ? $val : xml_escape($val)) . '</v>' : '';
                    $rowsXml .= '<c r="' . $ref . '" s="' . $style . '"><f>' . xml_escape($formula) . '</f>' . $fVal . '</c>';
                } elseif ($val === null || $val === '') {
                    $rowsXml .= '<c r="' . $ref . '" s="' . $style . '"/>';
                } elseif (is_int($val) || is_float($val)) {
                    $rowsXml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $val . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . xml_escape($val) . '</t></is></c>';
                }
            }
            $rowsXml .= '</row>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Freeze pane
        if (!empty($options['freeze_pane'])) {
            $fy = (int) ($options['freeze_pane']['y'] ?? 0);
            $fx = (int) ($options['freeze_pane']['x'] ?? 0);
            $topCell = column_name($fx + 1) . ($fy + 1);
            $xml .= '<sheetViews><sheetView workbookViewId="0">' .
                '<pane xSplit="' . $fx . '" ySplit="' . $fy . '" topLeftCell="' . $topCell . '" activePane="bottomRight" state="frozen"/>' .
                '</sheetView></sheetViews>';
        } else {
            $xml .= '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        }

        // Column widths
        if (!empty($options['col_widths']) && is_array($options['col_widths'])) {
            $xml .= '<cols>';
            foreach ($options['col_widths'] as $cIdx => $w) {
                $cNum = is_numeric($cIdx) ? (int) $cIdx : 1;
                $xml .= '<col min="' . $cNum . '" max="' . $cNum . '" width="' . (float) $w . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>' . $rowsXml . '</sheetData>';

        // Autofilter
        if (!empty($options['autofilter'])) {
            $xml .= '<autoFilter ref="' . xml_escape($options['autofilter']) . '"/>';
        }

        // Merges
        if (!empty($sheetData['merges'])) {
            $xml .= '<mergeCells count="' . count($sheetData['merges']) . '">';
            foreach ($sheetData['merges'] as $m) {
                $xml .= '<mergeCell ref="' . xml_escape($m) . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>';
        $xml .= '<pageSetup paperSize="9" orientation="' . (!empty($options['landscape']) ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>';
        $xml .= '</worksheet>';

        return $xml;
    }

    public function download(string $filename): void
    {
        $file = tempnam(sys_get_temp_dir(), 'iclabs_rekap_');
        try {
            $this->buildZip($file);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
            header('Pragma: public');
            readfile($file);
        } finally {
            @unlink($file);
        }
    }
}

/**
 * Builds and streams the comprehensive Academic Period Study Results Excel.
 */
function download_academic_period_recap_xlsx(array $period, array $verifiedClasses, string $filename = null, string $saveToPath = null): void
{
    $year = $period['tahun_akademik'] ?? ($period['academic_year'] ?? 'Periode');
    $semester = $period['semester']['nama'] ?? ($period['semester'] ?? '');
    if ($filename === null) {
        $safeYear = preg_replace('/[^0-9A-Za-z]+/', '-', (string) $year);
        $safeSem = preg_replace('/[^0-9A-Za-z]+/', '-', (string) $semester);
        $filename = 'Rekap-Hasil-Studi-' . $safeYear . '-' . $safeSem . '.xlsx';
    }

    $wb = new PeriodReportWorkbook();
    if (empty($verifiedClasses)) {
        $sEmpty = $wb->addSheet('Belum Ada Data', array('landscape' => true));
        $wb->addRow($sEmpty, array(
            array('v' => 'LEMBAR HASIL STUDI PRAKTIKUM LABORATORIUM KOMPUTER', 's' => 4),
        ), 28);
        $wb->addRow($sEmpty, array(
            array('v' => 'Periode: ' . $year . ' ' . $semester, 's' => 7),
        ));
        $wb->addRow($sEmpty, array());
        $wb->addRow($sEmpty, array(
            array('v' => 'Tidak ada frekuensi praktikum dengan status Terverifikasi pada periode ini.', 's' => 7),
        ));
    }

    // =========================================================================
    // SATU SHEET UNTUK SETIAP FREKUENSI (Misal: 65 frekuensi = 65 sheet)
    // =========================================================================
    $weekdayNames = array(1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu');

    foreach ($verifiedClasses as $cl) {
        $freqCode = $cl['kelompok']['kode'] ?? ($cl['kelompok']['nama'] ?? 'Frekuensi');
        $mkName = $cl['mata_kuliah']['nama'] ?? 'Mata Kuliah';
        $mkKode = $cl['mata_kuliah']['kode'] ?? '';
        $prodi = $cl['program_studi']['nama'] ?? ($cl['program_studi']['kode'] ?? '');
        $plannedMeetings = max(1, (int) ($cl['jumlah_pertemuan_rencana'] ?? 12));
        $sessions = $cl['sesi'] ?? array();
        $totalMeetings = max($plannedMeetings, count($sessions) ?: 12);

        // Schedules & teachers
        $schedules = $cl['jadwal'] ?? array();
        $primarySchedule = $schedules[0] ?? array();
        $hariStr = $weekdayNames[(int) ($primarySchedule['hari_ke'] ?? 0)] ?? 'Hari belum ditentukan';
        $jamStr = substr((string) ($primarySchedule['jam_mulai'] ?? ''), 0, 5) . ' - ' . substr((string) ($primarySchedule['jam_selesai'] ?? ''), 0, 5);
        $labStr = $primarySchedule['nama_laboratorium'] ?? ($primarySchedule['nama_ruang'] ?? ($primarySchedule['kode_ruang'] ?? 'Lab Komputer'));

        $dosenName = '—';
        $asistenName = '—';
        $asisten2Name = '';
        $pendampingName = '';
        $workflowStatus = $primarySchedule['status_workflow'] ?? ($cl['status'] ?? 'AKTIF');
        foreach ($primarySchedule['pengajar'] ?? array() as $p) {
            $role = $p['peran'] ?? '';
            if ($role === 'DOSEN_PENGAMPU' && !empty($p['nama'])) $dosenName = $p['nama'];
            elseif ($role === 'ASISTEN_UTAMA' && !empty($p['nama'])) $asistenName = $p['nama'];
            elseif (($role === 'ASISTEN_PENGAJAR_PENDUKUNG' || $role === 'ASISTEN_PENDAMPING') && !empty($p['nama'])) $asisten2Name = $p['nama'];
            elseif ($role === 'PENDAMPING_TEKNIS' && !empty($p['nama'])) $pendampingName = $p['nama'];
        }
        $asistenPendampingParts = array_filter(array(
            $asisten2Name,
            $pendampingName !== '' ? ('Pendamping: ' . $pendampingName) : '',
        ));
        $asistenPendampingStr = !empty($asistenPendampingParts) ? implode(' · ', $asistenPendampingParts) : '—';

        // Grade components in this class
        $evaluations = array();
        $assistances = array();
        $otherComponents = array();
        $hasMid = false;
        $hasUts = false;
        foreach ($cl['komponen_nilai'] ?? array() as $comp) {
            if (empty($comp['aktif'])) continue;
            $code = strtoupper((string) ($comp['kode'] ?? ''));
            if (preg_match('/^EVALUASI_([1-9][0-9]*)$/', $code, $m)) {
                $evaluations[] = array('num' => (int) $m[1], 'code' => $comp['kode'], 'nama' => $comp['nama'] ?? ('Evaluasi ' . $m[1]), 'bobot' => $comp['bobot_persen'] ?? 0);
            } elseif (preg_match('/^ASISTENSI_([1-9][0-9]*)$/', $code, $m)) {
                $assistances[] = array('num' => (int) $m[1], 'code' => $comp['kode'], 'nama' => $comp['nama'] ?? ('Asistensi ' . $m[1]), 'bobot' => $comp['bobot_persen'] ?? 0);
            } elseif ($code === 'MID') {
                $hasMid = true;
            } elseif ($code === 'UTS') {
                $hasUts = true;
            } elseif ($code !== 'UAS') {
                $otherComponents[] = array('code' => $comp['kode'], 'nama' => $comp['nama'] ?? $code, 'bobot' => $comp['bobot_persen'] ?? 0);
            }
        }
        usort($evaluations, static function ($a, $b) { return $a['num'] <=> $b['num']; });
        usort($assistances, static function ($a, $b) { return $a['num'] <=> $b['num']; });

        $colWidths = array(
            1 => 5,   // No
            2 => 14,  // NIM
            3 => 26,  // Nama Mahasiswa
        );
        $cCol = 4;
        for ($mIdx = 1; $mIdx <= $totalMeetings; $mIdx++) {
            $colWidths[$cCol++] = 4.5;
        }
        $colWidths[$cCol++] = 4.5; // H
        $colWidths[$cCol++] = 4.5; // I
        $colWidths[$cCol++] = 4.5; // S
        $colWidths[$cCol++] = 4.5; // A
        $colWidths[$cCol++] = 4.5; // T
        $colWidths[$cCol++] = 9.5; // % HADIR

        foreach ($evaluations as $ev) {
            $colWidths[$cCol++] = 8.5; // EV x
        }
        $colWidths[$cCol++] = 10.5; // RATA EV

        foreach ($assistances as $as) {
            $colWidths[$cCol++] = 8.5; // AS x
        }
        $colWidths[$cCol++] = 10.5; // RATA AS

        foreach ($otherComponents as $oc) {
            $colWidths[$cCol++] = 10;
        }

        if ($hasMid && $hasUts) {
            $colWidths[$cCol++] = 10;
            $colWidths[$cCol++] = 10;
        } else {
            $colWidths[$cCol++] = 11;
        }
        $colWidths[$cCol++] = 10;  // UAS
        $colWidths[$cCol++] = 14;  // NILAI AKHIR
        $colWidths[$cCol++] = 9;   // HURUF
        $colWidths[$cCol++] = 14;  // STATUS

        $totalCols = $cCol - 1;
        $lastColLetter = column_name($totalCols);

        // Add sheet
        $sheetTitle = $freqCode;
        $sFreq = $wb->addSheet($sheetTitle, array(
            'freeze_pane' => array('y' => 6, 'x' => 3),
            'col_widths' => $colWidths,
            'autofilter' => 'A6:' . $lastColLetter . '6',
            'landscape' => true,
        ));

        // Row 1: Header Banner
        $wb->addRow($sFreq, array(
            array('v' => 'LEMBAR HASIL STUDI PRAKTIKUM LABORATORIUM KOMPUTER', 's' => 4),
        ), 28);
        $wb->mergeCells($sFreq, 'A1:' . $lastColLetter . '1');

        // Row 2: MK info
        $wb->addRow($sFreq, array(
            array('v' => 'MATA KULIAH', 's' => 5),
            array('v' => $mkKode . ' - ' . $mkName, 's' => 6),
            array('v' => '', 's' => 6),
            array('v' => 'PROGRAM STUDI', 's' => 5),
            array('v' => $prodi, 's' => 6),
            array('v' => 'PERIODE', 's' => 5),
            array('v' => $year . ' ' . $semester, 's' => 6),
        ), 20);
        $wb->mergeCells($sFreq, 'B2:C2');

        // Row 3: Class info
        $wb->addRow($sFreq, array(
            array('v' => 'FREKUENSI', 's' => 5),
            array('v' => $freqCode, 's' => 6),
            array('v' => '', 's' => 6),
            array('v' => 'JADWAL / LAB', 's' => 5),
            array('v' => $hariStr . ', ' . $jamStr . ' (' . $labStr . ')', 's' => 6),
            array('v' => 'STATUS', 's' => 5),
            array('v' => $workflowStatus, 's' => 16),
        ), 20);
        $wb->mergeCells($sFreq, 'B3:C3');

        // Row 4: Teachers
        $wb->addRow($sFreq, array(
            array('v' => 'DOSEN PENGAMPU', 's' => 5),
            array('v' => $dosenName, 's' => 6),
            array('v' => '', 's' => 6),
            array('v' => 'ASISTEN UTAMA', 's' => 5),
            array('v' => $asistenName, 's' => 6),
            array('v' => 'ASISTEN 2 / PENDAMPING', 's' => 5),
            array('v' => $asistenPendampingStr, 's' => 6),
            array('v' => 'TGL CETAK', 's' => 5),
            array('v' => date('d/m/Y H:i'), 's' => 6),
        ), 20);
        $wb->mergeCells($sFreq, 'B4:C4');

        // Row 5: Empty line
        $wb->addRow($sFreq, array());

        // Row 6: Main Table Column Headers
        $headerRow = array(
            array('v' => 'NO', 's' => 1),
            array('v' => 'NIM', 's' => 1),
            array('v' => 'NAMA MAHASISWA', 's' => 1),
        );

        // Attendance columns: P1 .. Pn
        for ($mIdx = 1; $mIdx <= $totalMeetings; $mIdx++) {
            $headerRow[] = array('v' => 'P' . $mIdx, 's' => 2);
        }
        $headerRow[] = array('v' => 'H', 's' => 2);
        $headerRow[] = array('v' => 'I', 's' => 2);
        $headerRow[] = array('v' => 'S', 's' => 2);
        $headerRow[] = array('v' => 'A', 's' => 2);
        $headerRow[] = array('v' => 'T', 's' => 2);
        $headerRow[] = array('v' => '% HADIR', 's' => 2);

        // Evaluation columns
        foreach ($evaluations as $ev) {
            $headerRow[] = array('v' => 'EV ' . $ev['num'], 's' => 3);
        }
        $headerRow[] = array('v' => 'RATA EV', 's' => 1);

        // Assistance columns
        foreach ($assistances as $as) {
            $headerRow[] = array('v' => 'AS ' . $as['num'], 's' => 3);
        }
        $headerRow[] = array('v' => 'RATA AS', 's' => 1);

        // Other components
        foreach ($otherComponents as $oc) {
            $headerRow[] = array('v' => $oc['nama'], 's' => 3);
        }

        // Exam & Final Grade columns
        if ($hasMid && $hasUts) {
            $headerRow[] = array('v' => 'MID', 's' => 1);
            $headerRow[] = array('v' => 'UTS', 's' => 1);
        } elseif ($hasUts) {
            $headerRow[] = array('v' => 'UTS', 's' => 1);
        } elseif ($hasMid) {
            $headerRow[] = array('v' => 'MID', 's' => 1);
        } else {
            $headerRow[] = array('v' => 'MID / UTS', 's' => 1);
        }

        $headerRow[] = array('v' => 'UAS', 's' => 1);
        $headerRow[] = array('v' => 'NILAI AKHIR', 's' => 1);
        $headerRow[] = array('v' => 'HURUF', 's' => 1);
        $headerRow[] = array('v' => 'STATUS', 's' => 1);

        $wb->addRow($sFreq, $headerRow, 26);

        // Students rows
        $students = $cl['peserta'] ?? array();
        $firstDataRow = 7;
        $lastDataRow = 6;

        foreach ($students as $stIdx => $st) {
            $rowNum = $stIdx + 1;
            $nim = $st['nim'] ?? '—';
            $nama = $st['nama'] ?? '—';

            // Map attendance by meeting number
            $attMap = array();
            $cntH = 0; $cntI = 0; $cntS = 0; $cntA = 0; $cntT = 0;
            foreach ($st['kehadiran'] ?? array() as $kh) {
                $mNum = (int) ($kh['nomor_pertemuan'] ?? 0);
                $stCode = strtoupper((string) ($kh['status'] ?? ''));
                $short = $stCode === 'HADIR' ? 'H' : ($stCode === 'IZIN' ? 'I' : ($stCode === 'SAKIT' ? 'S' : ($stCode === 'ALPA' ? 'A' : ($stCode === 'TERLAMBAT' ? 'T' : '—'))));
                $attMap[$mNum] = $short;
                if ($stCode === 'HADIR') $cntH++;
                elseif ($stCode === 'IZIN') $cntI++;
                elseif ($stCode === 'SAKIT') $cntS++;
                elseif ($stCode === 'ALPA') $cntA++;
                elseif ($stCode === 'TERLAMBAT') $cntT++;
            }
            $pctHadir = round(($cntH + $cntT) / $totalMeetings, 3);

            // Alpa tone
            if ($cntA >= 4) {
                $alpaStyle = 15;
                $nameStyle = 15;
            } elseif ($cntA === 3) {
                $alpaStyle = 14;
                $nameStyle = 14;
            } else {
                $alpaStyle = 8;
                $nameStyle = 7;
            }

            // Grades map
            $gradeMap = array();
            foreach ($st['nilai'] ?? array() as $sc) {
                $cCode = strtoupper((string) ($sc['kode_komponen'] ?? ''));
                if (isset($sc['nilai']) && is_numeric($sc['nilai'])) {
                    $gradeMap[$cCode] = (float) $sc['nilai'];
                }
            }

            $sr = $st['ringkasan_nilai'] ?? array();
            $evAvg = isset($sr['rata_evaluasi']) && is_numeric($sr['rata_evaluasi']) ? (float) $sr['rata_evaluasi'] : null;
            $asAvg = isset($sr['rata_asistensi']) && is_numeric($sr['rata_asistensi']) ? (float) $sr['rata_asistensi'] : null;
            $naVal = isset($sr['nilai_akhir']) && is_numeric($sr['nilai_akhir']) ? (float) $sr['nilai_akhir'] : null;
            $predikat = strtoupper((string) ($sr['predikat'] ?? '—'));
            $uasVal = $gradeMap['UAS'] ?? null;

            $isLulus = ($predikat !== '' && $predikat !== 'E' && $predikat !== '—' && $cntA < 4);
            $statusLabel = $isLulus ? 'LULUS' : 'TIDAK LULUS';
            $statusStyle = $isLulus ? 16 : 15;

            $dataRow = array(
                array('v' => $rowNum, 's' => 8),
                array('v' => $nim, 's' => 8),
                array('v' => $nama, 's' => $nameStyle),
            );

            // P1 .. Pn
            for ($mIdx = 1; $mIdx <= $totalMeetings; $mIdx++) {
                $char = $attMap[$mIdx] ?? '—';
                $cellStyle = $char === 'A' ? 15 : ($char === 'H' ? 8 : 14);
                if ($char === '—') $cellStyle = 8;
                $dataRow[] = array('v' => $char, 's' => $cellStyle);
            }

            // H, I, S, A, T, % Hadir
            $dataRow[] = array('v' => $cntH, 's' => 10);
            $dataRow[] = array('v' => $cntI, 's' => 10);
            $dataRow[] = array('v' => $cntS, 's' => 10);
            $dataRow[] = array('v' => $cntA, 's' => $alpaStyle);
            $dataRow[] = array('v' => $cntT, 's' => 10);
            $dataRow[] = array('v' => $pctHadir, 's' => 12);

            // Evaluations
            foreach ($evaluations as $ev) {
                $val = $gradeMap[$ev['code']] ?? null;
                $dataRow[] = array('v' => $val, 's' => 11);
            }
            $dataRow[] = array('v' => $evAvg, 's' => 11);

            // Assistances
            foreach ($assistances as $as) {
                $val = $gradeMap[$as['code']] ?? null;
                $dataRow[] = array('v' => $val, 's' => 11);
            }
            $dataRow[] = array('v' => $asAvg, 's' => 11);

            // Other components
            foreach ($otherComponents as $oc) {
                $val = $gradeMap[$oc['code']] ?? null;
                $dataRow[] = array('v' => $val, 's' => 11);
            }

            // MID / UTS
            if ($hasMid && $hasUts) {
                $dataRow[] = array('v' => $gradeMap['MID'] ?? null, 's' => 11);
                $dataRow[] = array('v' => $gradeMap['UTS'] ?? null, 's' => 11);
            } else {
                $midVal = $gradeMap['MID'] ?? ($gradeMap['UTS'] ?? null);
                $dataRow[] = array('v' => $midVal, 's' => 11);
            }

            // UAS, NA, Huruf, Status
            $dataRow[] = array('v' => $uasVal, 's' => 11);
            $dataRow[] = array('v' => $naVal, 's' => 11);
            $dataRow[] = array('v' => $predikat, 's' => 9);
            $dataRow[] = array('v' => $statusLabel, 's' => $statusStyle);

            $wb->addRow($sFreq, $dataRow, 20);
            $lastDataRow = $firstDataRow + $stIdx;
        }

        // Summary Statistics at Bottom (if students exist)
        if (!empty($students)) {
            $colCount = count($headerRow);
            $colLetters = array();
            for ($c = 1; $c <= $colCount; $c++) {
                $colLetters[$c] = column_name($c);
            }

            $pctColIdx = 3 + $totalMeetings + 6;
            $statusColIdx = $colCount;
            $hurufColIdx = $colCount - 1;
            $naColIdx = $colCount - 2;

            // RATA-RATA KELAS row
            $avgRow = array();
            for ($c = 1; $c <= $colCount; $c++) {
                $letter = $colLetters[$c];
                $range = $letter . $firstDataRow . ':' . $letter . $lastDataRow;

                if ($c === 1) {
                    $avgRow[] = array('v' => 'RATA-RATA KELAS', 's' => 17);
                } elseif ($c === 2 || $c === 3) {
                    $avgRow[] = array('v' => '', 's' => 17);
                } elseif ($c >= 4 && $c <= (3 + $totalMeetings + 5)) {
                    $avgRow[] = array('v' => '', 's' => 17);
                } elseif ($c === $pctColIdx) {
                    $avgRow[] = array('f' => 'AVERAGE(' . $range . ')', 's' => 20);
                } elseif ($c === $hurufColIdx || $c === $statusColIdx) {
                    $avgRow[] = array('v' => '', 's' => 17);
                } else {
                    $avgRow[] = array('f' => 'AVERAGE(' . $range . ')', 's' => 18);
                }
            }
            $rNumAvg = $wb->getRowCount($sFreq) + 1;
            $wb->addRow($sFreq, $avgRow, 22);
            $wb->mergeCells($sFreq, 'A' . $rNumAvg . ':C' . $rNumAvg);

            // NILAI TERTINGGI (MAX) row
            $maxRow = array();
            for ($c = 1; $c <= $colCount; $c++) {
                $letter = $colLetters[$c];
                $range = $letter . $firstDataRow . ':' . $letter . $lastDataRow;
                if ($c === 1) {
                    $maxRow[] = array('v' => 'NILAI TERTINGGI (MAX)', 's' => 17);
                } elseif ($c === 2 || $c === 3) {
                    $maxRow[] = array('v' => '', 's' => 17);
                } elseif ($c === $pctColIdx) {
                    $maxRow[] = array('f' => 'MAX(' . $range . ')', 's' => 20);
                } elseif ($c > (3 + $totalMeetings + 6) && $c <= $naColIdx) {
                    $maxRow[] = array('f' => 'MAX(' . $range . ')', 's' => 18);
                } else {
                    $maxRow[] = array('v' => '', 's' => 17);
                }
            }
            $rNumMax = $wb->getRowCount($sFreq) + 1;
            $wb->addRow($sFreq, $maxRow, 22);
            $wb->mergeCells($sFreq, 'A' . $rNumMax . ':C' . $rNumMax);

            // NILAI TERENDAH (MIN) row
            $minRow = array();
            for ($c = 1; $c <= $colCount; $c++) {
                $letter = $colLetters[$c];
                $range = $letter . $firstDataRow . ':' . $letter . $lastDataRow;
                if ($c === 1) {
                    $minRow[] = array('v' => 'NILAI TERENDAH (MIN)', 's' => 17);
                } elseif ($c === 2 || $c === 3) {
                    $minRow[] = array('v' => '', 's' => 17);
                } elseif ($c === $pctColIdx) {
                    $minRow[] = array('f' => 'MIN(' . $range . ')', 's' => 20);
                } elseif ($c > (3 + $totalMeetings + 6) && $c <= $naColIdx) {
                    $minRow[] = array('f' => 'MIN(' . $range . ')', 's' => 18);
                } else {
                    $minRow[] = array('v' => '', 's' => 17);
                }
            }
            $rNumMin = $wb->getRowCount($sFreq) + 1;
            $wb->addRow($sFreq, $minRow, 22);
            $wb->mergeCells($sFreq, 'A' . $rNumMin . ':C' . $rNumMin);

            // JUMLAH MAHASISWA LULUS row
            $passRow = array();
            $statusLetter = $colLetters[$statusColIdx];
            $statusRange = $statusLetter . $firstDataRow . ':' . $statusLetter . $lastDataRow;
            for ($c = 1; $c <= $colCount; $c++) {
                if ($c === 1) {
                    $passRow[] = array('v' => 'JUMLAH MAHASISWA LULUS', 's' => 17);
                } elseif ($c === 2 || $c === 3) {
                    $passRow[] = array('v' => '', 's' => 17);
                } elseif ($c === $statusColIdx) {
                    $passRow[] = array('f' => 'COUNTIF(' . $statusRange . ',"LULUS")', 's' => 19);
                } else {
                    $passRow[] = array('v' => '', 's' => 17);
                }
            }
            $rNumPass = $wb->getRowCount($sFreq) + 1;
            $wb->addRow($sFreq, $passRow, 22);
            $wb->mergeCells($sFreq, 'A' . $rNumPass . ':C' . $rNumPass);
        }
    }

    if ($saveToPath !== null) {
        $wb->buildZip($saveToPath);
    } else {
        $wb->download($filename);
    }
}
