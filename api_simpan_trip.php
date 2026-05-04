<?php
// =====================================================
// api_simpan_trip.php — Simpan Itinerary AI ke DB
// POST JSON body: { itinerary: {...}, input: {...} }
// Tanpa login — id_user default 0 (guest)
// =====================================================

require_once __DIR__ . '/koneksi.php';

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

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['itinerary'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Body tidak valid atau itinerary kosong.']);
    exit;
}

$it    = $body['itinerary'];  // data JSON dari AI
$input = $body['input']  ?? [];  // data form user
$lsKey = $body['ls_key'] ?? null; // localStorage key untuk sinkronisasi

// ── Sanitasi nilai input ──────────────────────────────
$judul         = substr(trim($it['judul']    ?? 'Itinerary Jogja'), 0, 200);
$ringkasan     = trim($it['ringkasan'] ?? '');
$tglBerangkat  = $input['tanggalTiba']   ?? date('Y-m-d');
$tglPulang     = $input['tanggalPulang'] ?? date('Y-m-d');
$jamTiba       = $input['jamTiba']       ?? '08:00';
$jamPulang     = $input['jamPulang']     ?? '14:00';
$lokasiTiba    = substr($input['lokasiTiba'] ?? 'Stasiun Tugu', 0, 50);
$transportRaw  = $input['transportasi']  ?? 'Transportasi Umum';
$cerita        = $input['cerita']        ?? '';
$antiZigzag    = !empty($input['antiZigzag']) ? 1 : 0;
$modeGenerate  = ($input['mode'] ?? 'cepat') === 'kustom' ? 'Kustom' : 'Cepat';

// Petakan transport ke enum DB
$transportEnum  = 'Transportasi Umum';
$transportDetail = null;
$tLower = strtolower($transportRaw);
if (str_contains($tLower, 'motor')) {
    $transportEnum   = 'Motor';
    $transportDetail = str_contains($tLower, 'pribadi') ? 'Pribadi' : 'Rental';
} elseif (str_contains($tLower, 'mobil')) {
    $transportEnum   = 'Mobil';
    $transportDetail = str_contains($tLower, 'pribadi') ? 'Pribadi' : 'Rental';
} else {
    $transportEnum   = 'Transportasi Umum';
    $transportDetail = str_contains($tLower, 'grab') || str_contains($tLower, 'gojek') ? 'Ojek Online' : 'Trans Jogja';
}

// Petakan lokasi tiba ke enum DB
$lokasiEnum = 'Lainnya';
if (str_contains($lokasiTiba, 'YIA') || str_contains($lokasiTiba, 'Bandara')) $lokasiEnum = 'Bandara YIA';
elseif (str_contains($lokasiTiba, 'Tugu'))        $lokasiEnum = 'Stasiun Tugu';
elseif (str_contains($lokasiTiba, 'Lempuyangan')) $lokasiEnum = 'Stasiun Lempuyangan';
elseif (str_contains($lokasiTiba, 'Giwangan'))    $lokasiEnum = 'Terminal Giwangan';

// Budget
$budget     = $it['estimasiBudget'] ?? [];
$budgetTotal = 0;
if (!empty($budget['total'])) {
    preg_match('/[\d.,]+/', str_replace('.', '', $budget['total']), $m);
    $budgetTotal = (int)($m[0] ?? 0);
}

// Status berdasarkan tanggal
$today  = date('Y-m-d');
$status = 'Upcoming';
if ($tglBerangkat <= $today && $tglPulang >= $today) $status = 'Berlangsung';
elseif ($tglPulang < $today)                         $status = 'Selesai';

// Share token unik
$shareToken = 'dj_' . bin2hex(random_bytes(12));

// ── Simpan tabel trip (guest: id_user = 0) ───────────
$sql = "INSERT INTO trip
    (id_user, judul_trip, deskripsi, tanggal_berangkat, tanggal_pulang,
     jam_tiba, jam_pulang, lokasi_tiba, lokasi_pulang, transport, transport_detail,
     est_total_budget, anti_zigzag, mode_generate, prompt_user, status, share_token)
    VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, 'ssssssssssiissss',
    $judul, $ringkasan, $tglBerangkat, $tglPulang,
    $jamTiba, $jamPulang, $lokasiEnum, $lokasiEnum,
    $transportEnum, $transportDetail,
    $budgetTotal, $antiZigzag, $modeGenerate, $cerita, $status, $shareToken
);

if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan trip: ' . mysqli_error($koneksi)]);
    exit;
}
$idTrip = mysqli_insert_id($koneksi);

// ── Simpan tabel trip_hari + trip_destinasi ──────────
$hariList = $it['hari'] ?? [];

foreach ($hariList as $h) {
    $hariKe    = (int)($h['hari'] ?? 1);
    $klasterRaw = $h['klaster'] ?? '';
    $tipsHari  = $h['tips'] ?? null;

    // Hitung tanggal hari ke-N
    $tglHari = date('Y-m-d', strtotime($tglBerangkat . ' + ' . ($hariKe - 1) . ' days'));

    // Petakan klaster ke enum DB
    $klasterEnum = null;
    $kl = strtolower($klasterRaw);
    if (str_contains($kl, 'utara'))   $klasterEnum = 'Utara';
    elseif (str_contains($kl, 'selatan')) $klasterEnum = 'Selatan';
    elseif (str_contains($kl, 'timur'))   $klasterEnum = 'Timur';
    elseif (str_contains($kl, 'barat'))   $klasterEnum = 'Barat';
    elseif (str_contains($kl, 'pusat') || str_contains($kl, 'kota')) $klasterEnum = 'Pusat';

    $stmtH = mysqli_prepare($koneksi,
        "INSERT INTO trip_hari (id_trip, hari_ke, tanggal, klaster, catatan_hari) VALUES (?,?,?,?,?)"
    );
    mysqli_stmt_bind_param($stmtH, 'iisss', $idTrip, $hariKe, $tglHari, $klasterEnum, $tipsHari);
    mysqli_stmt_execute($stmtH);
    $idTripHari = mysqli_insert_id($koneksi);

    // Destinasi per hari
    $items = $h['items'] ?? [];
    foreach ($items as $urutan => $item) {
        $namaTempat = substr($item['nama']       ?? '',        0, 200);
        $deskripsi  = $item['deskripsi']          ?? null;
        $waktu      = $item['waktu']              ?? '';       // "07.30 – 09.00"
        $kategoriRaw= strtolower($item['kategori'] ?? 'wisata');
        $biayaRaw   = $item['estimasiBiaya']      ?? '';
        $transport  = substr($item['transport']   ?? '', 0, 100);
        $highlight  = $item['highlight']          ?? null;

        // Tipe ke enum
        $tipeEnum = 'Wisata';
        if ($kategoriRaw === 'kuliner')  $tipeEnum = 'Kuliner';
        elseif ($kategoriRaw === 'hotel') $tipeEnum = 'Hotel';
        elseif ($kategoriRaw === 'transit') $tipeEnum = 'Transit';
        else $tipeEnum = 'Wisata';

        // Parse jam dari "07.30 – 09.00"
        $jamMulai = null; $jamSelesai = null;
        if (preg_match('/(\d{1,2})[.\:](\d{2})/', $waktu, $m)) {
            $jamMulai = sprintf('%02d:%02d:00', $m[1], $m[2]);
        }
        if (preg_match('/–\s*(\d{1,2})[.\:](\d{2})/', $waktu, $m)) {
            $jamSelesai = sprintf('%02d:%02d:00', $m[1], $m[2]);
        }

        // Parse biaya
        $estBiaya = 0;
        preg_match('/[\d.,]+/', str_replace('.', '', $biayaRaw), $mb);
        $estBiaya = (int)($mb[0] ?? 0);

        // Gabung catatan: highlight sebagai catatan
        $catatan = $highlight;

        $stmtD = mysqli_prepare($koneksi,
            "INSERT INTO trip_destinasi
             (id_trip_hari, tipe, nama_tempat, deskripsi, jam_mulai, jam_selesai,
              est_biaya, transport_ke, urutan, catatan)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmtD, 'isssssisis',
            $idTripHari, $tipeEnum, $namaTempat, $deskripsi,
            $jamMulai, $jamSelesai, $estBiaya, $transport, $urutan, $catatan
        );
        mysqli_stmt_execute($stmtD);
    }
}

echo json_encode([
    'success'     => true,
    'id_trip'     => $idTrip,
    'share_token' => $shareToken,
    'message'     => 'Trip berhasil disimpan!',
]);
