<?php

/**
 * Fetch and normalize the public schedule matrix on the Praktikum application
 * server. The central API remains responsible for canonical matching/writes.
 */
function iclabs_praktikum_google_sheet_csv_url($url)
{
    $parts = parse_url(trim((string) $url));
    if (!is_array($parts)
        || ($parts['scheme'] ?? '') !== 'https'
        || ($parts['host'] ?? '') !== 'docs.google.com'
        || isset($parts['user'])
        || isset($parts['pass'])
        || isset($parts['port'])
        || !preg_match('#\A/spreadsheets/d/([A-Za-z0-9_-]+)(?:/|\z)#', $parts['path'] ?? '', $matches)) {
        throw new InvalidArgumentException('Gunakan URL Google Sheets dengan format https://docs.google.com/spreadsheets/d/{ID}/edit.');
    }

    parse_str($parts['query'] ?? '', $query);
    parse_str($parts['fragment'] ?? '', $fragment);
    $gid = $query['gid'] ?? $fragment['gid'] ?? '0';
    if (!is_string($gid) || !preg_match('/\A[0-9]+\z/', $gid)) {
        throw new InvalidArgumentException('Parameter gid pada URL Google Sheets tidak valid.');
    }

    return 'https://docs.google.com/spreadsheets/d/' . $matches[1] . '/export?' . http_build_query(array(
        'format' => 'csv',
        'gid' => $gid,
    ));
}

function iclabs_praktikum_fetch_google_sheet_csv($url)
{
    if (!function_exists('curl_init') || !defined('CURLPROTO_HTTPS')) {
        throw new RuntimeException('Ekstensi cURL PHP harus aktif untuk mengambil Google Sheets.');
    }

    $currentUrl = iclabs_praktikum_google_sheet_csv_url($url);
    $allowedHosts = array('docs.google.com', 'drive.google.com', 'googleusercontent.com');

    for ($redirect = 0; $redirect <= 3; $redirect++) {
        $responseBody = '';
        $location = null;
        $tooLarge = false;
        $handle = curl_init($currentUrl);
        if ($handle === false) {
            throw new RuntimeException('Google Sheets tidak dapat dijangkau dari server Praktikum.');
        }

        curl_setopt_array($handle, array(
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'ICLabs-Praktikum-Sheet-Sync/1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, $line) use (&$location) {
                if (stripos($line, 'Location:') === 0) {
                    $location = trim(substr($line, strlen('Location:')));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, $chunk) use (&$responseBody, &$tooLarge) {
                if (strlen($responseBody) + strlen($chunk) > 2000000) {
                    $tooLarge = true;
                    return 0;
                }
                $responseBody .= $chunk;
                return strlen($chunk);
            },
        ));

        $success = curl_exec($handle);
        $curlError = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($tooLarge) {
            throw new RuntimeException('CSV Google Sheets melebihi batas 2 MB.');
        }
        if ($success === false) {
            throw new RuntimeException('Google Sheets gagal diambil dari server Praktikum: ' . $curlError);
        }
        if ($status >= 300 && $status < 400 && is_string($location) && $location !== '' && $redirect < 3) {
            if (substr($location, 0, 1) === '/') {
                $location = 'https://docs.google.com' . $location;
            } elseif (substr($location, 0, 2) === '//') {
                $location = 'https:' . $location;
            }
            $parts = parse_url($location);
            $host = strtolower((string) ($parts['host'] ?? ''));
            $hostAllowed = in_array($host, $allowedHosts, true)
                || substr($host, -strlen('.googleusercontent.com')) === '.googleusercontent.com';
            if (!is_array($parts)
                || ($parts['scheme'] ?? '') !== 'https'
                || !$hostAllowed
                || isset($parts['user'])
                || isset($parts['pass'])
                || isset($parts['port'])) {
                throw new RuntimeException('Google Sheets mengarahkan ke host yang tidak diizinkan.');
            }
            $currentUrl = $location;
            continue;
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Google Sheets menolak permintaan CSV. Pastikan akses Sheet dapat dilihat publik.');
        }
        $trimmed = ltrim($responseBody);
        if ($responseBody === '' || substr($trimmed, 0, 9) === '<!DOCTYPE' || stripos($trimmed, '<html') === 0) {
            throw new RuntimeException('Google mengembalikan halaman kosong/HTML, bukan CSV. Periksa akses publik dan gid Sheet.');
        }

        return $responseBody;
    }

    throw new RuntimeException('Google Sheets terlalu banyak mengalihkan permintaan.');
}

function iclabs_praktikum_normalize_sheet_header($value)
{
    $value = preg_replace('/\s*\([^)]*\)/u', '', trim((string) $value));
    $value = str_replace("\xc2\xa0", ' ', (string) $value);
    $value = preg_replace('/\s+/u', ' ', trim((string) $value));

    return mb_strtolower((string) $value, 'UTF-8');
}

function iclabs_praktikum_parse_google_sheet_csv($csv)
{
    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('CSV Sheet tidak dapat dibaca oleh server Praktikum.');
    }
    fwrite($stream, (string) $csv);
    rewind($stream);

    $required = array('prodi', 'hari', 'jam', 'kode mk', 'mata kuliah', 'dosen', 'frekuensi', 'laboratorium', 'asisten 1', 'asisten 2', 'asisten 3', 'peserta', 'sks');
    $aliases = array(
        'program studi' => 'prodi',
        'frek' => 'frekuensi',
        'frek praktikum' => 'frekuensi',
        'nama laboratorium' => 'laboratorium',
        'lab' => 'laboratorium',
    );
    $headerIndexes = null;
    $bestMissing = $required;
    $bestCoverage = -1;
    $lineNumber = 0;

    try {
        while ($lineNumber < 30 && ($headers = fgetcsv($stream, 2000001, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if ($headers === array()) {
                continue;
            }
            $headers[0] = preg_replace('/^\xef\xbb\xbf/', '', (string) $headers[0]);
            $indexes = array();
            $duplicate = null;
            foreach ($headers as $index => $header) {
                $key = iclabs_praktikum_normalize_sheet_header($header);
                if ($key === '') {
                    continue;
                }
                if (isset($aliases[$key])) {
                    $key = $aliases[$key];
                }
                if (array_key_exists($key, $indexes)) {
                    $duplicate = $key;
                } else {
                    $indexes[$key] = $index;
                }
            }

            $missing = array_values(array_diff($required, array_keys($indexes)));
            $coverage = count($required) - count($missing);
            if ($coverage > $bestCoverage) {
                $bestCoverage = $coverage;
                $bestMissing = $missing;
            }
            if ($missing === array() && $duplicate !== null) {
                throw new InvalidArgumentException('Header Sheet duplikat atau ambigu: ' . $duplicate . '.');
            }
            if ($missing === array()) {
                $headerIndexes = $indexes;
                break;
            }
        }

        if ($headerIndexes === null) {
            throw new InvalidArgumentException('Baris header tidak ditemukan pada 30 baris pertama Sheet. Kolom yang belum dikenali: ' . implode(', ', $bestMissing) . '.');
        }

        $fieldMap = array(
            'prodi' => 'prodi',
            'hari' => 'hari',
            'jam' => 'jam',
            'kode mk' => 'kode_mk',
            'mata kuliah' => 'mata_kuliah',
            'dosen' => 'dosen',
            'frekuensi' => 'frekuensi',
            'laboratorium' => 'laboratorium',
            'asisten 1' => 'asisten_1',
            'asisten 2' => 'asisten_2',
            'asisten 3' => 'asisten_3',
            'peserta' => 'peserta',
            'sks' => 'sks',
        );
        $rows = array();
        while (($values = fgetcsv($stream, 2000001, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if (count(array_filter($values, static function ($value) { return trim((string) $value) !== ''; })) === 0) {
                continue;
            }
            $row = array('baris_sumber' => $lineNumber);
            foreach ($fieldMap as $source => $target) {
                $row[$target] = trim((string) ($values[$headerIndexes[$source]] ?? ''));
            }
            $rows[] = $row;
            if (count($rows) > 500) {
                throw new InvalidArgumentException('Sheet melebihi batas 500 baris jadwal.');
            }
        }
        if ($rows === array()) {
            throw new InvalidArgumentException('Sheet tidak memiliki baris jadwal.');
        }

        return $rows;
    } finally {
        fclose($stream);
    }
}
