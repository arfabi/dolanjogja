<?php
/**
 * chat.php  — RAG Chatbot DolanJogja
 * Alur: pesan user → Azure AI Search → inject context → Azure OpenAI GPT-4o → respons
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
// =====================================================
$body        = json_decode(file_get_contents('php://input'), true);
$userMessage = trim($body['message']     ?? '');
$history     = $body['history']          ?? [];
$mode        = $body['mode']             ?? 'chat';       // 'chat' | 'itinerary'
$preferences = $body['preferences']      ?? [];            // preferensi user untuk itinerary

if (empty($userMessage)) {
    echo json_encode(['error' => 'Pesan tidak boleh kosong.']);
    exit;
}

// =====================================================
// STEP 1: RETRIEVAL — cari data relevan dari Azure AI Search
// =====================================================

// Deteksi kebutuhan khusus dari pesan user
$isHiddenGemRequest = preg_match('/hidden.gem|tersembunyi|sepi|alternatif|jarang/i', $userMessage);
$isChildFriendly    = preg_match('/anak|keluarga|family|ramah anak/i', $userMessage);
$isBudgetRequest    = preg_match('/murah|budget|hemat|gratis/i', $userMessage);

// Tentukan filter
$filters = [];
if ($isChildFriendly) $filters['ramah_anak'] = true;

// Ambil hasil search
$searchResults = searchDestinasi($userMessage, 5, $filters, (bool)$isHiddenGemRequest);

// Jika mode itinerary, ambil lebih banyak data
if ($mode === 'itinerary') {
    $searchResults = searchDestinasi($userMessage, 10, $filters, false);
    // Tambahkan hidden gems jika tidak terlalu banyak hasil
    if (count($searchResults) < 8) {
        $hiddenGems = searchDestinasi($userMessage, 3, [], true);
        $searchResults = array_merge($searchResults, $hiddenGems);
    }
}

$context = formatContextForPrompt($searchResults);

// =====================================================
// STEP 2: BUILD PROMPT
// =====================================================

$systemPrompt = <<<PROMPT
Kamu adalah DolanJogja AI — asisten wisata cerdas untuk Yogyakarta. Kamu membantu wisatawan merencanakan perjalanan yang efisien, personal, dan bebas zig-zag.

IDENTITASMU:
- Berbicara dengan ramah, antusias, dan seperti teman lokal yang tahu Jogja luar dalam
- Jawab dalam bahasa yang digunakan user (Indonesia atau Inggris)
- Selalu berbasis data dari database DolanJogja (diberikan di bawah), TIDAK mengarang info

ATURAN ITINERARY (wajib saat menyusun jadwal):
- Klasterisasi wilayah: susun destinasi per area (Jogja Pusat → Jogja Utara → dst) — TIDAK boleh zig-zag lintas wilayah
- Pertimbangkan jam buka/tutup setiap destinasi
- Sisipkan hidden gems secara natural di antara destinasi populer
- Selalu sertakan estimasi biaya dan pilihan transportasi di setiap perpindahan
- Format itinerary: Hari X → Pagi/Siang/Sore → Nama Tempat → Info singkat → Transport

ATURAN REKOMENDASI:
- Jika user minta "santai/adem/tenang" → prioritaskan alam + hidden gems
- Jika user minta "dengan anak" → filter hanya yang ramah_anak = true
- Jika user minta "hemat/budget" → utamakan destinasi gratis/murah
- Selalu sebutkan kuliner lokal yang ada di dekat destinasi yang direkomendasikan

PROMPT;

// Tambahkan context dari Azure AI Search jika ada
if (!empty($context)) {
    $systemPrompt .= "\n\n" . $context;
}

// Tambahkan instruksi mode itinerary
if ($mode === 'itinerary' && !empty($preferences)) {
    $pref = json_encode($preferences, JSON_UNESCAPED_UNICODE);
    $systemPrompt .= "\n\nPREFERENSI USER UNTUK ITINERARY:\n{$pref}";
}

// =====================================================
// STEP 3: KIRIM KE AZURE OPENAI
// =====================================================
$messages = [['role' => 'system', 'content' => $systemPrompt]];

foreach ($history as $msg) {
    if (isset($msg['role'], $msg['content'])) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

$payload = [
    'model'       => DEPLOYMENT_NAME,
    'messages'    => $messages,
    'max_tokens'  => $mode === 'itinerary' ? 2000 : 1000,
    'temperature' => 0.7,
];

$ch = curl_init(AZURE_ENDPOINT . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AZURE_API_KEY,
    ],
    CURLOPT_TIMEOUT => 45,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => "cURL error: {$curlError}"]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $errMsg = $data['error']['message'] ?? "Terjadi kesalahan API (HTTP {$httpCode}).";
    http_response_code($httpCode);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$reply = $data['choices'][0]['message']['content'] ?? 'Maaf, tidak ada respons.';

echo json_encode([
    'reply'           => $reply,
    'usage'           => $data['usage'] ?? null,
    'context_count'   => count($searchResults),   // debug: berapa destinasi yang di-inject
    'mode'            => $mode,
]);
