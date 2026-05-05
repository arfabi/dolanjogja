<?php
/**
 * search.php
 * Fungsi helper untuk query ke Azure AI Search.
 * Di-include oleh chat.php dan foto.php.
 */

// =====================================================
// KONFIGURASI AZURE AI SEARCH
// =====================================================
define('SEARCH_ENDPOINT', 'https://YOUR_SEARCH_SERVICE.search.windows.net');
define('SEARCH_QUERY_KEY', 'YOUR_SEARCH_QUERY_KEY');  // Query key (bukan admin key)
define('SEARCH_INDEX',     '#');
define('SEARCH_API_VER',   '#');

/**
 * Cari destinasi berdasarkan teks bebas (keyword, mood, nama tempat, dll.)
 *
 * @param string $query        Teks pencarian
 * @param int    $top          Jumlah hasil (default 5)
 * @param array  $filters      Filter tambahan, contoh: ['wilayah' => 'Jogja Selatan', 'ramah_anak' => true]
 * @param bool   $hiddenOnly   Jika true, hanya tampilkan hidden gems
 * @return array               Array destinasi yang relevan
 */
function searchDestinasi(string $query, int $top = 5, array $filters = [], bool $hiddenOnly = false): array {
    // Bangun filter string OData
    $filterParts = [];
    if ($hiddenOnly) {
        $filterParts[] = 'hidden_gem eq true';
    }
    foreach ($filters as $field => $value) {
        if (is_bool($value)) {
            $filterParts[] = "{$field} eq " . ($value ? 'true' : 'false');
        } elseif (is_string($value)) {
            $escaped = str_replace("'", "''", $value);
            $filterParts[] = "{$field} eq '{$escaped}'";
        }
    }

    $payload = [
        'search'           => $query,
        'top'              => $top,
        'queryType'        => 'simple',
        'searchMode'       => 'any',
        'searchFields'     => 'nama,deskripsi,tags,mood_tags,ciri_visual,kategori,wilayah',
        'select'           => 'id,nama,kategori,wilayah,deskripsi,jam_buka,harga_tiket,estimasi_durasi,tags,mood_tags,ciri_visual,kuliner_sekitar,transportasi_terdekat,hidden_gem,ramah_anak,lat,lng',
        'scoringStatistics' => 'global',
    ];

    if (!empty($filterParts)) {
        $payload['filter'] = implode(' and ', $filterParts);
    }

    $url = SEARCH_ENDPOINT . '/indexes/' . SEARCH_INDEX . '/docs/search?api-version=' . SEARCH_API_VER;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'api-key: ' . SEARCH_QUERY_KEY,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        return [];
    }

    $data = json_decode($response, true);
    return $data['value'] ?? [];
}

/**
 * Format hasil search menjadi context string untuk diinjeksikan ke prompt GPT.
 *
 * @param array  $results Hasil dari searchDestinasi()
 * @param string $prefix  Label context (default "DATA DESTINASI RELEVAN")
 * @return string
 */
function formatContextForPrompt(array $results, string $prefix = 'DATA DESTINASI RELEVAN'): string {
    if (empty($results)) {
        return '';
    }

    $lines = ["=== {$prefix} ==="];
    foreach ($results as $i => $d) {
        $n = $i + 1;
        $hidden = $d['hidden_gem'] ? ' [HIDDEN GEM]' : '';
        $anak   = $d['ramah_anak'] ? ' [RAMAH ANAK]' : '';

        $lines[] = "\n[{$n}] {$d['nama']}{$hidden}{$anak}";
        $lines[] = "Kategori : {$d['kategori']} | Wilayah: {$d['wilayah']}";
        $lines[] = "Alamat   : {$d['alamat'] ?? '-'}";
        $lines[] = "Jam Buka : {$d['jam_buka']}";
        $lines[] = "Tiket    : {$d['harga_tiket']}";
        $lines[] = "Durasi   : {$d['estimasi_durasi']}";
        $lines[] = "Deskripsi: {$d['deskripsi']}";

        if (!empty($d['kuliner_sekitar'])) {
            $lines[] = "Kuliner  : " . implode(', ', $d['kuliner_sekitar']);
        }
        if (!empty($d['transportasi_terdekat'])) {
            $lines[] = "Transport: " . implode(' | ', $d['transportasi_terdekat']);
        }
    }
    $lines[] = "\n=== AKHIR DATA ===";

    return implode("\n", $lines);
}
