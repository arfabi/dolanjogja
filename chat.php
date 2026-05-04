<?php
// =====================================================
// DolanJogja AI — chat.php  (v2)
// Azure OpenAI Backend — Itinerary Generator
// =====================================================

require_once __DIR__ . '/env.php';

$AZURE_RESOURCE  = env('AZURE_RESOURCE');
$AZURE_API_KEY   = env('AZURE_API_KEY');
$API_VERSION     = env('AZURE_API_VERSION', '2024-08-01-preview');

// Parse mode early so we can pick the right model before reading full body
$rawBody  = file_get_contents("php://input");
$bodyPeek = json_decode($rawBody, true);
$modePeek = $bodyPeek["mode"] ?? "itinerary";

// Itinerary generator  → GPT-4o   (reasoning kuat, output JSON kompleks)
// Tanya AI chat        → GPT-4o mini (hemat ~97% biaya, cukup untuk percakapan)
$DEPLOYMENT_NAME = ($modePeek === "itinerary")
    ? env('AZURE_DEPLOYMENT_GPT4O', 'gpt-4o')
    : env('AZURE_DEPLOYMENT_GPT4O_MINI', 'gpt-4o-mini');

$AZURE_ENDPOINT = "https://{$AZURE_RESOURCE}.openai.azure.com/openai/deployments/{$DEPLOYMENT_NAME}/chat/completions?api-version={$API_VERSION}";

$corsOrigin = env('CORS_ALLOW_ORIGIN', '*');
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: {$corsOrigin}");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$body = $bodyPeek; // reuse already-read body
if (!$body) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON body"]);
    exit;
}

$mode = $body["mode"] ?? "itinerary"; // "itinerary" | "chat"

// =====================================================
// MODE: TANYA AI CHAT (conversation mode)
// =====================================================
if ($mode === "chat") {
    $userMessage      = trim($body["message"] ?? "");
    $history          = $body["history"]      ?? [];
    $itinerarySummary = $body["itinerarySummary"] ?? "";

    if (empty($userMessage)) {
        echo json_encode(["error" => "Pesan tidak boleh kosong."]);
        exit;
    }

    $systemPrompt = <<<PROMPT
Kamu adalah DolanJogja AI — asisten wisata cerdas dan ramah khusus Yogyakarta.

KONTEKS: Wisatawan sudah memiliki itinerary berikut:
{$itinerarySummary}

TUGASMU:
- Jawab pertanyaan seputar itinerary: estimasi biaya, tips, kondisi destinasi, alternatif tempat, dll.
- Jika ditanya hal lain seputar wisata Jogja, tetap bantu dengan informatif dan berguna.
- Bicara seperti teman lokal Jogja yang asik, hangat, dan berpengetahuan luas.
- Gunakan emoji secukupnya agar terasa hidup dan menyenangkan.
- Jawab dalam bahasa yang sama dengan pertanyaan pengguna (Indonesia / Inggris).
- Berikan info praktis: jam buka, harga terkini, tips hindari antrean, rekomendasi parkir, kuliner terdekat.
- Jika ditanya soal biaya, berikan range realistis dalam Rupiah.
- Jika user tampak bingung atau ragu, berikan saran dengan penjelasan yang singkat dan jelas.
PROMPT;

    $messages = [["role" => "system", "content" => $systemPrompt]];
    foreach ($history as $msg) {
        if (isset($msg["role"], $msg["content"])) {
            $messages[] = ["role" => $msg["role"], "content" => $msg["content"]];
        }
    }
    $messages[] = ["role" => "user", "content" => $userMessage];

    $payload = [
        "messages"    => $messages,
        "max_tokens"  => 700,
        "temperature" => 0.75,
    ];

    $result = callAzure($AZURE_ENDPOINT, $AZURE_API_KEY, $payload);
    if (isset($result["error"])) {
        http_response_code(500);
        echo json_encode($result);
        exit;
    }

    echo json_encode([
        "reply" => $result["choices"][0]["message"]["content"] ?? "Maaf, tidak ada respons.",
        "usage" => $result["usage"] ?? null,
        "model" => $DEPLOYMENT_NAME,
    ]);
    exit;
}

// =====================================================
// MODE: GENERATE ITINERARY
// =====================================================
$input = $body["input"] ?? [];

$tanggalTiba   = trim($input["tanggalTiba"]   ?? "");
$tanggalPulang = trim($input["tanggalPulang"] ?? "");
$jamTiba       = trim($input["jamTiba"]       ?? "08:00");
$jamPulang     = trim($input["jamPulang"]     ?? "14:00");
$lokasiTiba    = trim($input["lokasiTiba"]    ?? "Stasiun Tugu");
$vibe          = $input["vibe"]               ?? [];
$preferensi    = $input["preferensi"]         ?? [];
$transportasi  = trim($input["transportasi"]  ?? "Transportasi Umum");
$wisataWajib   = $input["wisataWajib"]        ?? [];
$antiZigzag    = $input["antiZigzag"]         ?? true;
$cerita        = trim($input["cerita"]        ?? "");
$modeInput     = trim($input["mode"]          ?? "cepat");

// Hitung jumlah hari
$jumlahHari = 1;
if ($tanggalTiba && $tanggalPulang) {
    try {
        $tiba   = new DateTime($tanggalTiba);
        $pulang = new DateTime($tanggalPulang);
        $diff   = $tiba->diff($pulang)->days;
        $jumlahHari = max(1, $diff + 1); // +1 karena hari tiba dan hari pulang keduanya dihitung sebagai hari liburan
    } catch (Exception $e) {
        $jumlahHari = 1;
    }
}

// Format array ke string
$vibeStr    = is_array($vibe)        ? implode(", ", array_filter($vibe))        : $vibe;
$prefStr    = is_array($preferensi)  ? implode(", ", array_filter($preferensi))  : $preferensi;
$wajibStr   = is_array($wisataWajib) ? implode(", ", array_filter($wisataWajib)) : $wisataWajib;
$zigzagNote = $antiZigzag
    ? "AKTIF — klasterisasi wilayah per hari, tidak bolak-balik lintas area"
    : "NONAKTIF — fleksibel, tidak perlu klasterisasi ketat";

if (empty($vibeStr))  $vibeStr  = "Beragam (campuran alam, budaya, kuliner)";
if (empty($prefStr))  $prefStr  = "Umum (tidak ada preferensi khusus)";
if (empty($wajibStr)) $wajibStr = "Tidak ada, sesuaikan dengan vibe dan preferensi";

// =====================================================
// SYSTEM PROMPT
// =====================================================
$systemPrompt = <<<PROMPT
Kamu adalah DolanJogja AI — asisten wisata cerdas khusus Yogyakarta yang membantu wisatawan merencanakan perjalanan efisien, personal, menyenangkan, dan bebas zig-zag.

IDENTITAS & GAYA:
- Berbicara ramah, antusias, seperti teman lokal Jogja yang tahu kota luar dalam
- Jawab dalam bahasa yang digunakan user (Indonesia atau Inggris)
- Selalu berbasis destinasi NYATA di Yogyakarta — JANGAN mengarang nama tempat, harga palsu, atau jam operasional fiktif

ATURAN TRANSPORTASI (WAJIB):
- Jika wisatawan menggunakan MOTOR PRIBADI atau MOBIL PRIBADI, SEMUA rekomendasi transport di field "transport" harus menggunakan kendaraan pribadi tersebut (contoh: "Motor pribadi (~10 menit)", "Mobil pribadi (~15 menit)"). DILARANG KERAS menyebut Grab, Gojek, ojek online, atau ojek pangkalan untuk user yang membawa kendaraan pribadi.
- Jika wisatawan menggunakan MOTOR RENTAL atau MOBIL RENTAL, transport menggunakan kendaraan tersebut (contoh: "Motor rental (~10 menit)", "Mobil rental (~15 menit)"). DILARANG menyebut ojek online kecuali sebagai alternatif darurat.
- Jika wisatawan menggunakan Transportasi Umum, barulah rekomendasikan Trans Jogja, DAMRI, JogjaKita, Grab, Gojek, atau kombinasinya.
- Jika wisatawan TIDAK berangkat dari stasiun/bandara/terminal (alias pakai kendaraan pribadi), JANGAN buat item "transit" atau "sampai di stasiun/bandara" di hari pertama. Langsung mulai dari destinasi wisata pertama di area sesuai lokasiTiba.
- Sesuaikan rekomendasi transport secara konsisten di SETIAP item itinerary tanpa pengecualian.

ATURAN ITINERARY (WAJIB DIIKUTI SEMUA):
1. KLASTERISASI WILAYAH: Setiap hari fokus 1-2 area berdekatan — contoh: Hari 1: Pusat Kota (Malioboro, Keraton, Alun-alun) | Hari 2: Sleman Utara (Kaliurang, Merapi, Ullen Sentalu) | Hari 3: Bantul (Parangtritis, Imogiri) | Hari 4: Gunungkidul (Pantai-pantai, Gua) | Hari 5: Kulon Progo (YIA, Kalibiru). TIDAK BOLEH lompat-lompat jauh dalam satu hari.
2. URUTAN KRONOLOGIS: Jadwal dari pagi (07.00) hingga malam (21.00). Pertimbangkan jam buka tutup tiap destinasi.
3. ESTIMASI BIAYA REALISTIS: Tiket wisata Rp 5.000-100.000, kuliner Rp 15.000-80.000/orang, transport Rp 5.000-50.000/perjalanan. Cantumkan di setiap item.
4. TRANSPORT DI SETIAP PERPINDAHAN: Rekomendasikan moda transport dan estimasi waktu tempuh (Grab/Gojek, Motor, Trans Jogja, Jalan Kaki).
5. HIDDEN GEMS: Minimal 1 hidden gem per hari — tempat indah/unik tapi less-crowded yang tidak ada di panduan wisata mainstream.
6. TIPS LOKAL SPESIFIK: Tips praktis per hari (jam terbaik, info parkir, hindari hari tertentu, dll).
7. KULINER KHAS: Minimal 1 stop kuliner khas Jogja per hari (gudeg, bakpia, soto, dll) di warung/resto yang nyata dan dikenal.
8. DURASI REALISTIS: Wisata budaya/candi 1.5-2 jam, pantai 2-3 jam, museum 1-1.5 jam, kuliner 30-45 menit, belanja 1-2 jam.

ATURAN adaCCTV & adaTiket:
- adaCCTV = true HANYA untuk: Malioboro, Keraton Yogyakarta, Candi Prambanan, Candi Borobudur, Alun-alun Kidul, Alun-alun Utara, Pantai Parangtritis, Stasiun Tugu, Bandara YIA
- adaTiket = true untuk: candi (Prambanan, Borobudur, Ratu Boko), museum, pantai berbayar, taman hiburan, wisata alam berbayar
- adaTiket = false untuk: Malioboro (jalan), alun-alun publik, taman kota, masjid/gereja

OUTPUT — WAJIB JSON VALID PERSIS FORMAT INI:
Respons HANYA berupa JSON murni. Tanpa markdown, tanpa backtick ```, tanpa komentar //, tanpa teks apapun di luar JSON.

{
  "judul": "string — judul itinerary yang menarik dan personal, maksimal 65 karakter",
  "ringkasan": "string — deskripsi singkat 1-2 kalimat yang menggugah selera perjalanan",
  "totalHari": number,
  "estimasiBudget": {
    "total": "string — contoh: Rp 1.350.000",
    "transport": "string — contoh: Rp 220.000",
    "tiket": "string — contoh: Rp 280.000",
    "kuliner": "string — contoh: Rp 850.000",
    "catatan": "string — contoh: Per orang, termasuk transport, tiket, dan kuliner. Belanja tidak termasuk."
  },
  "hari": [
    {
      "hari": number,
      "judul": "string — contoh: Hari 1 — Pesona Pusat Kota Yogyakarta",
      "klaster": "string — contoh: Pusat Kota Yogyakarta",
      "tips": "string — tips lokal spesifik untuk hari ini, 1-2 kalimat bermakna",
      "items": [
        {
          "waktu": "string — contoh: 07.30 – 09.00",
          "nama": "string — nama tempat atau aktivitas",
          "kategori": "string — PILIH SATU SAJA: wisata | kuliner | hotel | transit | belanja | hiburan",
          "deskripsi": "string — deskripsi menarik 1-2 kalimat dengan info faktual",
          "estimasiBiaya": "string — contoh: Rp 25.000/orang atau Gratis",
          "transport": "string atau null — dari tempat sebelumnya, contoh: Grab/Gojek (~10 menit, Rp 8.000)",
          "adaTiket": boolean,
          "adaCCTV": boolean,
          "highlight": "string atau null — tips khusus, info hidden gem, atau fakta menarik tentang tempat ini"
        }
      ]
    }
  ]
}
PROMPT;

// =====================================================
// USER PROMPT
// =====================================================
$userPrompt = <<<PROMPT
Buatkan itinerary wisata Yogyakarta yang detail dan personal berdasarkan data berikut:

📋 INFO PERJALANAN:
- Mode: {$modeInput}
- Tiba: {$tanggalTiba} pukul {$jamTiba} di {$lokasiTiba}
- Pulang: {$tanggalPulang} pukul {$jamPulang} dari {$lokasiTiba}
- Durasi: {$jumlahHari} hari

🎯 PREFERENSI USER:
- Vibe Liburan: {$vibeStr}
- Preferensi Wisata: {$prefStr}
- Transportasi: {$transportasi}
- CATATAN TRANSPORT KETAT: Seluruh field "transport" di setiap item HARUS konsisten dengan "{$transportasi}". 
  * Motor Pribadi / Mobil Pribadi → tulis "Motor pribadi (~Xmnt)" atau "Mobil pribadi (~Xmnt)". TIDAK BOLEH ada Grab/Gojek/ojek sama sekali.
  * Motor Rental / Mobil Rental → tulis "Motor rental (~Xmnt)" atau "Mobil rental (~Xmnt)". 
  * Transportasi Umum → Trans Jogja, Grab, Gojek diperbolehkan.
- CATATAN LOKASI TIBA: Jika lokasiTiba berisi kata "kendaraan pribadi" atau nama kota/area (bukan stasiun/bandara), artinya user sudah membawa kendaraan sendiri dari awal. Jangan buat item "transit dari stasiun" atau sejenisnya di hari pertama.
- Wisata Wajib: {$wajibStr}
- Anti Zig-zag Mode: {$zigzagNote}

💬 CERITA & KEINGINAN USER:
{$cerita}

INSTRUKSI PENTING:
1. Hari pertama dimulai dari {$lokasiTiba} — sesuaikan klaster area dengan lokasi tiba
2. Hari terakhir harus kembali ke {$lokasiTiba} sebelum jam {$jamPulang}
3. Masukkan semua wisata wajib di atas pada hari yang paling strategis geografis
4. Jika cerita user menyebut kondisi khusus (anak kecil, lansia, vegetarian, budget ketat, honeymoon, dll), sesuaikan SELURUH itinerary dengan kebutuhan itu
5. Buat setiap hari punya "tema" yang berbeda dan kuat
6. Jumlah item per hari: minimal 5, maksimal 9 (termasuk makan pagi, siang, dan malam)
PROMPT;

$messages = [
    ["role" => "system", "content" => $systemPrompt],
    ["role" => "user",   "content" => $userPrompt],
];

$payload = [
    "messages"        => $messages,
    "max_tokens"      => 4500,
    "temperature"     => 0.8,
    "response_format" => ["type" => "json_object"],
];

$result = callAzure($AZURE_ENDPOINT, $AZURE_API_KEY, $payload);

if (isset($result["error"])) {
    http_response_code(500);
    echo json_encode(["error" => $result["error"]]);
    exit;
}

$rawContent = $result["choices"][0]["message"]["content"] ?? "";

// Bersihkan sisa markdown
$rawContent = trim($rawContent);
$rawContent = preg_replace('/^```json\s*/i', '', $rawContent);
$rawContent = preg_replace('/^```\s*/i',    '', $rawContent);
$rawContent = preg_replace('/```\s*$/i',    '', $rawContent);
$rawContent = trim($rawContent);

$itinerary = json_decode($rawContent, true);

if (!$itinerary || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        "error"   => "Gagal memparse respons AI. Error: " . json_last_error_msg() . ". Silakan coba lagi.",
        "rawData" => substr($rawContent, 0, 500),
    ]);
    exit;
}

if (!isset($itinerary["hari"]) || !is_array($itinerary["hari"])) {
    http_response_code(500);
    echo json_encode(["error" => "Format itinerary tidak valid. Silakan coba lagi."]);
    exit;
}

echo json_encode([
    "success"   => true,
    "itinerary" => $itinerary,
    "usage"     => $result["usage"] ?? null,
    "model"     => $DEPLOYMENT_NAME,
]);

// =====================================================
// HELPER: cURL ke Azure OpenAI
// =====================================================
function callAzure(string $url, string $apiKey, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "api-key: {$apiKey}",
        ],
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) return ["error" => "Koneksi gagal: {$curlError}"];
    if (!$response) return ["error" => "Tidak ada respons dari server AI."];

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $data["error"]["message"] ?? "HTTP error {$httpCode}";
        if ($httpCode === 401) $msg = "API key tidak valid.";
        if ($httpCode === 429) $msg = "Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.";
        if ($httpCode === 503) $msg = "Layanan AI sedang sibuk. Silakan coba lagi dalam beberapa detik.";
        return ["error" => $msg];
    }

    return $data;
}
