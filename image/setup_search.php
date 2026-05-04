<?php
/**
 * setup_search.php
 * Jalankan SEKALI untuk:
 * 1. Membuat index di Azure AI Search
 * 2. Upload semua data destinasi ke index
 *
 * Usage: php setup_search.php
 */

// =====================================================
// KONFIGURASI
// =====================================================
define('SEARCH_ENDPOINT', 'https://YOUR_SEARCH_SERVICE.search.windows.net');
define('SEARCH_API_KEY',  'YOUR_SEARCH_ADMIN_KEY');  // Admin key (bukan query key)
define('SEARCH_INDEX',    'dolanjogja-destinasi');
define('SEARCH_API_VER',  '2024-07-01');

// =====================================================
// HELPER: cURL request ke Azure AI Search
// =====================================================
function searchRequest(string $method, string $url, array $body = []): array {
    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
        'api-key: ' . SEARCH_API_KEY,
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);
    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => $curlError, 'status' => 0];
    }
    return ['status' => $httpCode, 'body' => json_decode($response, true)];
}

// =====================================================
// STEP 1: BUAT INDEX
// =====================================================
echo "=== STEP 1: Membuat index '{$_ENV['SEARCH_INDEX'] ?? SEARCH_INDEX}' ===\n";

$indexSchema = [
    'name'   => SEARCH_INDEX,
    'fields' => [
        ['name' => 'id',                 'type' => 'Edm.String',              'key' => true,  'searchable' => false, 'filterable' => true],
        ['name' => 'nama',               'type' => 'Edm.String',              'key' => false, 'searchable' => true,  'filterable' => true,  'analyzer' => 'id.microsoft'],
        ['name' => 'kategori',           'type' => 'Edm.String',              'key' => false, 'searchable' => true,  'filterable' => true,  'facetable' => true],
        ['name' => 'wilayah',            'type' => 'Edm.String',              'key' => false, 'searchable' => true,  'filterable' => true,  'facetable' => true],
        ['name' => 'deskripsi',          'type' => 'Edm.String',              'key' => false, 'searchable' => true,  'analyzer' => 'id.microsoft'],
        ['name' => 'alamat',             'type' => 'Edm.String',              'key' => false, 'searchable' => true],
        ['name' => 'jam_buka',           'type' => 'Edm.String',              'key' => false, 'searchable' => false],
        ['name' => 'harga_tiket',        'type' => 'Edm.String',              'key' => false, 'searchable' => false],
        ['name' => 'estimasi_durasi',    'type' => 'Edm.String',              'key' => false, 'searchable' => false],
        ['name' => 'tags',               'type' => 'Collection(Edm.String)',  'key' => false, 'searchable' => true,  'filterable' => true],
        ['name' => 'mood_tags',          'type' => 'Collection(Edm.String)',  'key' => false, 'searchable' => true,  'filterable' => true],
        ['name' => 'ciri_visual',        'type' => 'Collection(Edm.String)',  'key' => false, 'searchable' => true],
        ['name' => 'kuliner_sekitar',    'type' => 'Collection(Edm.String)',  'key' => false, 'searchable' => true],
        ['name' => 'transportasi_terdekat', 'type' => 'Collection(Edm.String)', 'key' => false, 'searchable' => true],
        ['name' => 'hidden_gem',         'type' => 'Edm.Boolean',             'key' => false, 'filterable' => true,  'facetable' => true],
        ['name' => 'ramah_anak',         'type' => 'Edm.Boolean',             'key' => false, 'filterable' => true],
        ['name' => 'lat',                'type' => 'Edm.Double',              'key' => false, 'sortable' => true],
        ['name' => 'lng',                'type' => 'Edm.Double',              'key' => false, 'sortable' => true],
    ],
];

$url    = SEARCH_ENDPOINT . '/indexes/' . SEARCH_INDEX . '?api-version=' . SEARCH_API_VER;
$result = searchRequest('PUT', $url, $indexSchema);

if (in_array($result['status'], [200, 201])) {
    echo "✓ Index berhasil dibuat/diperbarui.\n\n";
} else {
    echo "✗ Gagal membuat index. HTTP {$result['status']}\n";
    print_r($result['body']);
    exit(1);
}

// =====================================================
// STEP 2: UPLOAD DOKUMEN
// =====================================================
echo "=== STEP 2: Upload data destinasi ===\n";

$dataPath = __DIR__ . '/data/destinasi.json';
if (!file_exists($dataPath)) {
    echo "✗ File data/destinasi.json tidak ditemukan!\n";
    exit(1);
}

$destinasi = json_decode(file_get_contents($dataPath), true);
if (!$destinasi) {
    echo "✗ JSON tidak valid!\n";
    exit(1);
}

// Transformasi data ke format Azure AI Search
$documents = [];
foreach ($destinasi as $d) {
    $documents[] = [
        '@search.action'         => 'mergeOrUpload',
        'id'                     => $d['id'],
        'nama'                   => $d['nama'],
        'kategori'               => $d['kategori'],
        'wilayah'                => $d['wilayah'],
        'deskripsi'              => $d['deskripsi'],
        'alamat'                 => $d['alamat'],
        'jam_buka'               => $d['jam_buka'],
        'harga_tiket'            => $d['harga_tiket'],
        'estimasi_durasi'        => $d['estimasi_durasi'],
        'tags'                   => $d['tags'] ?? [],
        'mood_tags'              => $d['mood_tags'] ?? [],
        'ciri_visual'            => $d['ciri_visual'] ?? [],
        'kuliner_sekitar'        => $d['kuliner_sekitar'] ?? [],
        'transportasi_terdekat'  => $d['transportasi_terdekat'] ?? [],
        'hidden_gem'             => $d['hidden_gem'] ?? false,
        'ramah_anak'             => $d['ramah_anak'] ?? false,
        'lat'                    => $d['koordinat']['lat'] ?? 0.0,
        'lng'                    => $d['koordinat']['lng'] ?? 0.0,
    ];
}

// Upload dalam batch (maks 1000 per request, tapi kita batch 100)
$batches = array_chunk($documents, 100);
$url     = SEARCH_ENDPOINT . '/indexes/' . SEARCH_INDEX . '/docs/index?api-version=' . SEARCH_API_VER;

foreach ($batches as $i => $batch) {
    $result = searchRequest('POST', $url, ['value' => $batch]);
    if (in_array($result['status'], [200, 201])) {
        $count = count($batch);
        echo "✓ Batch " . ($i + 1) . ": {$count} dokumen berhasil diupload.\n";
    } else {
        echo "✗ Batch " . ($i + 1) . " gagal. HTTP {$result['status']}\n";
        print_r($result['body']);
    }
}

echo "\n=== Selesai! Total " . count($documents) . " destinasi diupload ke Azure AI Search. ===\n";
echo "Index URL: " . SEARCH_ENDPOINT . "/indexes/" . SEARCH_INDEX . "\n";
