<?php
// =====================================================
// ai_wisata.php — AI Chat dengan Konteks Wisata Spesifik
// POST body JSON:
//   { "message": "...", "wisata": {...}, "history": [...] }
// =====================================================

require_once __DIR__ . '/env.php';

$corsOrigin = env('CORS_ALLOW_ORIGIN', '*');
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: {$corsOrigin}");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// =====================================================
// KONFIGURASI AZURE OPENAI — dari .env
// =====================================================
$AZURE_RESOURCE  = env('AZURE_RESOURCE');
$AZURE_API_KEY   = env('AZURE_API_KEY');
$DEPLOYMENT_NAME = env('AZURE_DEPLOYMENT_GPT4O', 'gpt-4o');
$API_VERSION     = env('AZURE_API_VERSION', '2024-08-01-preview');
$AZURE_ENDPOINT  = "https://{$AZURE_RESOURCE}.openai.azure.com/openai/deployments/{$DEPLOYMENT_NAME}/chat/completions?api-version={$API_VERSION}";

// =====================================================
// PARSE REQUEST
// =====================================================
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Body tidak valid.']);
    exit;
}

$userMessage = trim($body['message'] ?? '');
$wisata      = $body['wisata']   ?? [];
$history     = $body['history']  ?? [];

if (empty($userMessage)) {
    echo json_encode(['error' => 'Pesan tidak boleh kosong.']);
    exit;
}

// =====================================================
// BANGUN KONTEKS DARI DATA WISATA
// =====================================================
$wisataContext = '';
if (!empty($wisata)) {
    $harga = 'Gratis';
    if (!empty($wisata['harga_tiket_min'])) {
        $min = number_format((int)$wisata['harga_tiket_min'], 0, ',', '.');
        $max = number_format((int)$wisata['harga_tiket_max'], 0, ',', '.');
        $harga = ($wisata['harga_tiket_min'] == $wisata['harga_tiket_max'])
            ? "Rp {$min}"
            : "Rp {$min} – Rp {$max}";
    }

    $jamBuka = '';
    if (!empty($wisata['jam_buka'])) {
        $jamBuka = substr($wisata['jam_buka'], 0, 5) . ' – ' . substr($wisata['jam_tutup'] ?? '18:00:00', 0, 5) . ' WIB';
    }

    $fasilitas = '';
    if (!empty($wisata['fasilitas']) && is_array($wisata['fasilitas'])) {
        $fasilitas = implode(', ', $wisata['fasilitas']);
    }

    $hiddenGem = (!empty($wisata['is_hidden_gem'])) ? '✅ Ya (hidden gem)' : 'Tidak';
    $ramahAnak = (!empty($wisata['is_ramah_anak'])) ? '✅ Ya'           : 'Tidak';

    $wisataContext = <<<CTX

=== DATA DESTINASI WISATA YANG SEDANG DILIHAT USER ===
Nama          : {$wisata['nama_tempat']}
Kategori      : {$wisata['jenis_wisata']}
Wilayah/Klaster: {$wisata['klaster']} — {$wisata['kabupaten']}
Lokasi        : {$wisata['lokasi']}
Jam Buka      : {$jamBuka}
Harga Tiket   : {$harga}
Tingkat Ramai : {$wisata['tingkat_kepadatan']}
Hidden Gem    : {$hiddenGem}
Ramah Anak    : {$ramahAnak}
Rating        : {$wisata['stat_stars']}/5 ({$wisata['stat_ulasan']} ulasan)
Google Maps   : {$wisata['link_gmaps']}
Deskripsi     : {$wisata['deskripsi']}
Fasilitas     : {$fasilitas}
=== AKHIR DATA DESTINASI ===
CTX;
}

// =====================================================
// SYSTEM PROMPT
// =====================================================
$systemPrompt = <<<PROMPT
Kamu adalah DolanJogja AI — asisten wisata cerdas, hangat, dan berpengetahuan luas tentang Yogyakarta.

KONTEKS: User sedang melihat halaman detail destinasi wisata di aplikasi DolanJogja. Data lengkap destinasi tersebut sudah tersedia di bawah.
{$wisataContext}

TUGASMU:
- Jawab pertanyaan apapun tentang destinasi ini secara spesifik dan akurat berdasarkan data di atas
- Berikan tips praktis: jam terbaik kunjungan, cara menuju, parkir, kuliner terdekat, dll.
- Jika user bertanya tentang biaya total perjalanan, bantu kalkulasi dengan data tiket + estimasi transport + kuliner
- Jika ditanya destinasi serupa, rekomendasikan wisata lain di Jogja dengan kategori/vibe yang sama
- Berikan info kondisi terkini jika relevan (musim hujan, akhir pekan ramai, dll.)
- Gunakan Bahasa Indonesia yang ramah dan natural seperti teman lokal Jogja
- Gunakan emoji secukupnya untuk mempercantik respons
- Jika pertanyaan tidak relevan dengan wisata Jogja, tetap bantu dengan sopan namun arahkan kembali ke topik wisata
PROMPT;

// =====================================================
// BUILD MESSAGES
// =====================================================
$messages = [['role' => 'system', 'content' => $systemPrompt]];

foreach ($history as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

$payload = [
    'messages'    => $messages,
    'max_tokens'  => 700,
    'temperature' => 0.75,
];

// =====================================================
// CALL AZURE OPENAI
// =====================================================
$ch = curl_init($AZURE_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "api-key: {$AZURE_API_KEY}",
    ],
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => "Koneksi gagal: {$curlError}"]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $msg = $data['error']['message'] ?? "HTTP error {$httpCode}";
    if ($httpCode === 429) $msg = 'Terlalu banyak permintaan. Coba lagi dalam beberapa detik.';
    http_response_code($httpCode);
    echo json_encode(['error' => $msg]);
    exit;
}

$reply = $data['choices'][0]['message']['content'] ?? 'Maaf, tidak ada respons dari AI.';

echo json_encode([
    'success' => true,
    'reply'   => $reply,
    'usage'   => $data['usage'] ?? null,
]);
