<?php
/**
 * foto.php — Destinasi Recognition via Foto
 * Alur: foto user → GPT-4o Vision (identifikasi visual) → Azure AI Search → GPT-4o (info lengkap)
 */

require_once __DIR__ . '/search.php';

// =====================================================
// KONFIGURASI AZURE OPENAI
// =====================================================
define('AZURE_ENDPOINT',  'https://dolanjogja-ai.openai.azure.com/openai/v1');
define('AZURE_API_KEY',   'DTqMatHvTIyXbq8Tcn5CNJJJejHVGyzEJzy5loDEIFhOO4k79nxrJQQJ99CEACYeBjFXJ3w3AAABACOGnFO4');
define('DEPLOYMENT_NAME', 'gpt-4o');

// =====================================================
// HEADERS
// =====================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// =====================================================
// PARSE REQUEST
// Terima: multipart/form-data (file upload) ATAU JSON (base64)
// =====================================================
$imageBase64  = null;
$imageType    = null;

// Mode 1: File upload langsung
if (!empty($_FILES['foto']['tmp_name'])) {
    $file = $_FILES['foto'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $mime = mime_content_type($file['tmp_name']);

    if (!in_array($mime, $allowedMime)) {
        echo json_encode(['error' => 'Format file tidak didukung. Gunakan JPEG, PNG, atau WEBP.']);
        exit;
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        echo json_encode(['error' => 'Ukuran file terlalu besar. Maksimal 4MB.']);
        exit;
    }

    $imageBase64 = base64_encode(file_get_contents($file['tmp_name']));
    $imageType   = $mime;

// Mode 2: JSON dengan base64
} else {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!empty($body['image_base64'])) {
        // Hapus prefix data URL jika ada (data:image/jpeg;base64,...)
        $raw = $body['image_base64'];
        if (preg_match('/^data:(image\/[a-z]+);base64,(.+)$/i', $raw, $m)) {
            $imageType   = $m[1];
            $imageBase64 = $m[2];
        } else {
            $imageBase64 = $raw;
            $imageType   = 'image/jpeg'; // default
        }
    }
}

if (!$imageBase64) {
    echo json_encode(['error' => 'Tidak ada foto yang dikirimkan.']);
    exit;
}

// =====================================================
// STEP 1: GPT-4o VISION — Identifikasi visual dari foto
// =====================================================
$visionMessages = [
    [
        'role'    => 'system',
        'content' => 'Kamu adalah sistem identifikasi lokasi wisata Yogyakarta. Analisis foto dan ekstrak informasi visual secara detail dalam format JSON. Fokus pada: nama tempat (jika terdeteksi), ciri-ciri visual khas (arsitektur, vegetasi, material, warna), jenis lokasi, dan kata kunci yang bisa digunakan untuk mencari di database wisata Jogja.'
    ],
    [
        'role'    => 'user',
        'content' => [
            [
                'type' => 'text',
                'text' => 'Analisis foto ini dan berikan output dalam format JSON saja (tanpa markdown/backtick):
{
  "nama_terdeteksi": "nama tempat jika bisa diidentifikasi, atau null",
  "jenis_lokasi": "candi/pantai/hutan/bukit/jalan/pasar/dll",
  "ciri_visual": ["daftar ciri visual yang terlihat"],
  "keywords_search": "kata kunci untuk cari di database, pisah spasi",
  "confidence": "tinggi/sedang/rendah",
  "deskripsi_singkat": "deskripsi visual 1-2 kalimat"
}'
            ],
            [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => "data:{$imageType};base64,{$imageBase64}",
                    'detail' => 'low'   // hemat token, cukup untuk identifikasi lokasi
                ]
            ]
        ]
    ]
];

$visionPayload = [
    'model'       => DEPLOYMENT_NAME,
    'messages'    => $visionMessages,
    'max_tokens'  => 400,
    'temperature' => 0.3,   // lebih deterministik untuk ekstraksi data
];

$ch = curl_init(AZURE_ENDPOINT . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($visionPayload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AZURE_API_KEY,
    ],
    CURLOPT_TIMEOUT => 30,
]);

$visionResponse = curl_exec($ch);
$visionCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$visionError    = curl_error($ch);
curl_close($ch);

if ($visionError || $visionCode !== 200) {
    $errData = json_decode($visionResponse, true);
    echo json_encode(['error' => $errData['error']['message'] ?? 'Gagal menganalisis foto.']);
    exit;
}

$visionData   = json_decode($visionResponse, true);
$visionText   = $visionData['choices'][0]['message']['content'] ?? '{}';
$visionResult = json_decode($visionText, true) ?? [];

// =====================================================
// STEP 2: AZURE AI SEARCH — Cari destinasi berdasarkan hasil vision
// =====================================================
$searchQuery = '';

// Prioritas 1: nama yang terdeteksi langsung
if (!empty($visionResult['nama_terdeteksi'])) {
    $searchQuery = $visionResult['nama_terdeteksi'];
}
// Prioritas 2: keywords dari hasil vision
elseif (!empty($visionResult['keywords_search'])) {
    $searchQuery = $visionResult['keywords_search'];
}
// Fallback: ciri visual
elseif (!empty($visionResult['ciri_visual'])) {
    $searchQuery = implode(' ', array_slice($visionResult['ciri_visual'], 0, 5));
}

$searchResults = [];
if ($searchQuery) {
    $searchResults = searchDestinasi($searchQuery, 3);
}

$context = formatContextForPrompt($searchResults, 'DESTINASI YANG MUNGKIN SESUAI');

// =====================================================
// STEP 3: GPT-4o — Hasilkan respons lengkap dan informatif
// =====================================================
$systemPrompt = <<<PROMPT
Kamu adalah DolanJogja AI, asisten wisata Yogyakarta. User mengirimkan foto sebuah lokasi.
Berdasarkan analisis visual dan data database di bawah, berikan informasi yang helpful dan menarik.

Format respons WAJIB:
1. 📍 **Lokasi**: Nama tempat (dengan tingkat keyakinan jika tidak pasti)
2. 📝 **Tentang**: Deskripsi singkat dan menarik (2-3 kalimat)
3. 🕐 **Jam Buka & Tiket**: Info praktis
4. 🍜 **Kuliner Terdekat**: 2-3 rekomendasi
5. 🚗 **Cara Menuju**: Pilihan transportasi
6. 💡 **Tips**: 1-2 tips berguna untuk kunjungan
7. 📌 **Destinasi Serupa**: 1-2 tempat dengan vibe serupa yang mungkin disukai user

Jika tidak bisa mengidentifikasi dengan pasti, tetap berikan respons yang helpful berdasarkan ciri visual yang terdeteksi.
Jawab dalam Bahasa Indonesia yang ramah dan antusias.
PROMPT;

if (!empty($context)) {
    $systemPrompt .= "\n\n" . $context;
}

$finalMessages = [
    ['role' => 'system', 'content' => $systemPrompt],
    [
        'role'    => 'user',
        'content' => [
            ['type' => 'text', 'text' => 'Tolong identifikasi dan berikan informasi lengkap tentang lokasi di foto ini.'],
            [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => "data:{$imageType};base64,{$imageBase64}",
                    'detail' => 'low'
                ]
            ]
        ]
    ]
];

$finalPayload = [
    'model'       => DEPLOYMENT_NAME,
    'messages'    => $finalMessages,
    'max_tokens'  => 800,
    'temperature' => 0.7,
];

$ch = curl_init(AZURE_ENDPOINT . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($finalPayload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AZURE_API_KEY,
    ],
    CURLOPT_TIMEOUT => 45,
]);

$finalResponse = curl_exec($ch);
$finalCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$finalData  = json_decode($finalResponse, true);
$finalReply = $finalData['choices'][0]['message']['content'] ?? 'Maaf, tidak bisa menganalisis foto ini.';

// =====================================================
// RESPONSE
// =====================================================
echo json_encode([
    'reply'          => $finalReply,
    'vision_result'  => $visionResult,          // hasil analisis vision (untuk debug)
    'search_query'   => $searchQuery,
    'matches_found'  => count($searchResults),
    'usage'          => [
        'vision'  => $visionData['usage']  ?? null,
        'final'   => $finalData['usage']   ?? null,
    ],
]);
