<?php
// =====================================================
// ai_event.php — AI Chat dengan Konteks Event Spesifik
// POST body JSON:
//   { "message": "...", "event": {...}, "history": [...] }
// =====================================================

header('Content-Type: application/json; charset=utf-8');
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
// KONFIGURASI AZURE OPENAI
// =====================================================
$AZURE_RESOURCE  = "dolanjogja-ai";
$AZURE_API_KEY   = "DTqMatHvTIyXbq8Tcn5CNJJJejHVGyzEJzy5loDEIFhOO4k79nxrJQQJ99CEACYeBjFXJ3w3AAABACOGnFO4";
$DEPLOYMENT_NAME = "gpt-4o";
$API_VERSION     = "2024-08-01-preview";
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
$event       = $body['event']   ?? [];
$history     = $body['history'] ?? [];

if (empty($userMessage)) {
    echo json_encode(['error' => 'Pesan tidak boleh kosong.']);
    exit;
}

// =====================================================
// BANGUN KONTEKS DARI DATA EVENT
// =====================================================
$eventContext = '';
if (!empty($event)) {
    $harga = $event['harga_label'] ?? 'Gratis';
    $tanggal = $event['tanggal_panjang'] ?? ($event['tanggal_label'] ?? '');
    $jam     = $event['jam_label'] ?? '';
    $featured = (!empty($event['is_featured'])) ? '✅ Ya (event unggulan)' : 'Tidak';
    $upcoming = (!empty($event['is_upcoming'])) ? '✅ Akan datang' : '⚠️ Sudah selesai';
    $tiket = (!empty($event['link_tiket_valid'])) ? $event['link_tiket'] : 'Tidak tersedia / langsung di lokasi';

    $eventContext = <<<CTX

=== DATA EVENT YANG SEDANG DILIHAT USER ===
Nama Event    : {$event['nama_event']}
Kategori      : {$event['kategori']}
Penyelenggara : {$event['penyelenggara']}
Tanggal       : {$tanggal}
Jam           : {$jam}
Lokasi        : {$event['lokasi']}
Harga Tiket   : {$harga}
Jenis         : {$event['jenis_event']}
Link Tiket    : {$tiket}
Status        : {$upcoming}
Featured      : {$featured}
Views         : {$event['stat_view']}
Likes         : {$event['stat_like']}
Deskripsi     : {$event['deskripsi']}
=== AKHIR DATA EVENT ===
CTX;
}

// =====================================================
// SYSTEM PROMPT
// =====================================================
$systemPrompt = <<<PROMPT
Kamu adalah DolanJogja AI — asisten wisata cerdas, hangat, dan berpengetahuan luas tentang Yogyakarta.

KONTEKS: User sedang melihat halaman detail event di aplikasi DolanJogja. Data lengkap event tersedia di bawah.
{$eventContext}

TUGASMU:
- Jawab pertanyaan apapun tentang event ini secara spesifik dan akurat berdasarkan data di atas
- Berikan informasi praktis: cara menuju lokasi, parkir, tips agar lebih nyaman menikmati event
- Jika ditanya tentang harga, bantu kalkulasi estimasi biaya total (tiket + transport + kuliner)
- Jika ditanya event serupa, rekomendasikan event lain di Jogja dengan kategori/suasana yang sama
- Jika event sudah selesai, beri tahu user dan sarankan event mendatang yang mirip
- Berikan info kontekstual: cuaca saat event, apakah ramai, apa yang perlu dibawa, dress code, dll.
- Bantu user memutuskan apakah event ini cocok untuk mereka (keluarga, pasangan, solo, dll.)
- Gunakan Bahasa Indonesia yang ramah dan natural seperti teman lokal Jogja
- Gunakan emoji secukupnya untuk mempercantik respons
- Jika ditanya hal tidak relevan, tetap bantu namun arahkan kembali ke topik event Jogja
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
