<?php
// =====================================================
// api_wisata.php — API Data Wisata dari Database MySQL
// Endpoint:
//   GET  ?action=list  [&kategori=Alam] [&klaster=Utara] [&search=keyword] [&hidden=1] [&limit=20] [&offset=0]
//   GET  ?action=detail&id=3001
// =====================================================

require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'list';

// =====================================================
// ACTION: LIST WISATA
// =====================================================
if ($action === 'list') {

    $where  = ['w.is_active = 1'];
    $params = [];
    $types  = '';

    // Filter kategori
    $kategori = trim($_GET['kategori'] ?? '');
    if ($kategori && $kategori !== 'Semua') {
        $where[]  = 'w.jenis_wisata = ?';
        $params[] = $kategori;
        $types   .= 's';
    }

    // Filter klaster
    $klaster = trim($_GET['klaster'] ?? '');
    if ($klaster && $klaster !== 'Semua') {
        $where[]  = 'w.klaster = ?';
        $params[] = $klaster;
        $types   .= 's';
    }

    // Filter hidden gem
    if (isset($_GET['hidden']) && $_GET['hidden'] == '1') {
        $where[] = 'w.is_hidden_gem = 1';
    }

    // Filter ramah anak
    if (isset($_GET['anak']) && $_GET['anak'] == '1') {
        $where[] = 'w.is_ramah_anak = 1';
    }

    // Search keyword
    $search     = trim($_GET['search'] ?? '');
    $searchLike = '';
    if ($search !== '') {
        $like        = '%' . $search . '%';
        $searchLike  = $like;
        $where[]     = '(w.nama_tempat LIKE ? OR w.deskripsi LIKE ? OR w.kabupaten LIKE ?)';
        $params[]    = $like;
        $params[]    = $like;
        $params[]    = $like;
        $types      .= 'sss';
    }

    // Pagination
    $limit  = max(1, min(50, (int)($_GET['limit']  ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $whereStr = implode(' AND ', $where);

    // Hitung total
    $sqlCount  = "SELECT COUNT(*) as total FROM wisata w WHERE {$whereStr}";
    $stmtCount = mysqli_prepare($koneksi, $sqlCount);
    if ($types && $stmtCount) {
        mysqli_stmt_bind_param($stmtCount, $types, ...$params);
    }
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    $total    = (int)(mysqli_fetch_assoc($resCount)['total'] ?? 0);

    // Relevance score — prioritaskan nama_tempat > kabupaten > deskripsi
    if ($searchLike !== '') {
        $relevanceExpr = "
            (CASE WHEN w.nama_tempat LIKE ? THEN 100 ELSE 0 END) +
            (CASE WHEN w.kabupaten   LIKE ? THEN  10 ELSE 0 END) +
            (CASE WHEN w.deskripsi   LIKE ? THEN   1 ELSE 0 END)
        ";
        $orderBy   = "({$relevanceExpr}) DESC, w.stat_stars DESC, w.stat_view DESC";
        $extraParams = [$searchLike, $searchLike, $searchLike];
        $extraTypes  = 'sss';
    } else {
        $orderBy     = 'w.stat_stars DESC, w.stat_view DESC';
        $extraParams = [];
        $extraTypes  = '';
    }

    // Query utama
    $sql = "SELECT
                w.id_wisata,
                w.nama_tempat,
                w.slug,
                w.deskripsi_ai        AS deskripsi_singkat,
                w.lokasi,
                w.klaster,
                w.kabupaten,
                w.jenis_wisata,
                w.jam_buka,
                w.jam_tutup,
                w.harga_tiket_min,
                w.harga_tiket_max,
                w.foto_utama,
                w.is_hidden_gem,
                w.is_ramah_anak,
                w.tingkat_kepadatan,
                w.stat_view,
                w.stat_like,
                w.stat_stars,
                w.stat_ulasan,
                w.link_gmaps,
                w.latitude,
                w.longitude
            FROM wisata w
            WHERE {$whereStr}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?";

    $allParams = array_merge($params, $extraParams, [$limit, $offset]);
    $allTypes  = $types . $extraTypes . 'ii';

    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_hidden_gem']   = (bool)$row['is_hidden_gem'];
        $row['is_ramah_anak']   = (bool)$row['is_ramah_anak'];
        $row['stat_stars']      = (float)$row['stat_stars'];
        $row['harga_tiket_min'] = (int)$row['harga_tiket_min'];
        $row['harga_tiket_max'] = (int)$row['harga_tiket_max'];
        $row['harga_label']     = formatHarga($row['harga_tiket_min'], $row['harga_tiket_max']);
        $row['jam_label']       = formatJam($row['jam_buka'], $row['jam_tutup']);
        $list[] = $row;
    }

    echo json_encode([
        'success' => true,
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset,
        'data'    => $list,
    ]);
    exit;
}

// =====================================================
// ACTION: DETAIL WISATA + GALERI
// =====================================================
if ($action === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['error' => 'ID wisata tidak valid.']);
        exit;
    }

    $sql  = "SELECT * FROM wisata WHERE id_wisata = ? AND is_active = 1 LIMIT 1";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $wisata = mysqli_fetch_assoc($result);

    if (!$wisata) {
        http_response_code(404);
        echo json_encode(['error' => 'Data wisata tidak ditemukan.']);
        exit;
    }

    $wisata['is_hidden_gem']   = (bool)$wisata['is_hidden_gem'];
    $wisata['is_ramah_anak']   = (bool)$wisata['is_ramah_anak'];
    $wisata['is_indoor']       = (bool)$wisata['is_indoor'];
    $wisata['stat_stars']      = (float)$wisata['stat_stars'];
    $wisata['harga_tiket_min'] = (int)$wisata['harga_tiket_min'];
    $wisata['harga_tiket_max'] = (int)$wisata['harga_tiket_max'];
    $wisata['harga_label']     = formatHarga($wisata['harga_tiket_min'], $wisata['harga_tiket_max']);
    $wisata['jam_label']       = formatJam($wisata['jam_buka'], $wisata['jam_tutup']);

    $sqlGaleri  = "SELECT id_galeri, link_foto, caption, urutan FROM wisata_galeri WHERE id_wisata = ? ORDER BY urutan ASC";
    $stmtGaleri = mysqli_prepare($koneksi, $sqlGaleri);
    mysqli_stmt_bind_param($stmtGaleri, 'i', $id);
    mysqli_stmt_execute($stmtGaleri);
    $resGaleri = mysqli_stmt_get_result($stmtGaleri);

    $galeri = [];
    while ($g = mysqli_fetch_assoc($resGaleri)) {
        $galeri[] = $g;
    }

    $wisata['fasilitas'] = getFasilitas($wisata['jenis_wisata'], $wisata['is_indoor']);
    $wisata['tips']      = getTips($wisata['jenis_wisata']);

    echo json_encode([
        'success' => true,
        'wisata'  => $wisata,
        'galeri'  => $galeri,
    ]);
    exit;
}

// =====================================================
// ACTION: KATEGORI & KLASTER (untuk filter chips)
// =====================================================
if ($action === 'meta') {
    $kategori = ['Semua', 'Alam', 'Sejarah', 'Budaya', 'Rekreasi', 'Belanja', 'Museum', 'Kuliner'];
    $klaster  = ['Semua', 'Pusat', 'Utara', 'Selatan', 'Timur', 'Barat'];

    echo json_encode([
        'success'  => true,
        'kategori' => $kategori,
        'klaster'  => $klaster,
    ]);
    exit;
}

// =====================================================
// HELPER FUNCTIONS
// =====================================================

function formatHarga(int $min, int $max): string {
    if ($min === 0 && $max === 0) return 'Gratis';
    if ($min === $max) return 'Rp ' . number_format($min, 0, ',', '.');
    return 'Rp ' . number_format($min, 0, ',', '.') . ' – Rp ' . number_format($max, 0, ',', '.');
}

function formatJam(?string $buka, ?string $tutup): string {
    if (!$buka) return 'Buka 24 Jam';
    $b = substr($buka, 0, 5);
    $t = $tutup ? substr($tutup, 0, 5) : '18:00';
    return "{$b} – {$t} WIB";
}

function getFasilitas(string $jenis, bool $indoor): array {
    $base = ['Area Parkir', 'Toilet Umum', 'Warung Makan'];

    $byJenis = [
        'Alam'     => ['Gazebo / Tempat Istirahat', 'Jalur Trekking', 'Spot Foto'],
        'Sejarah'  => ['Pemandu Wisata', 'Ruang Pamer', 'Toko Souvenir'],
        'Budaya'   => ['Pemandu Wisata', 'Area Pertunjukan', 'Toko Souvenir'],
        'Museum'   => ['Pemandu Wisata', 'Ruang Pamer AC', 'Toko Souvenir', 'Auditorium'],
        'Rekreasi' => ['Wahana Permainan', 'Food Court', 'Mushola', 'Loker'],
        'Belanja'  => ['ATM', 'Food Court', 'Mushola'],
        'Kuliner'  => ['Meja & Kursi', 'Takeaway', 'WiFi'],
    ];

    $extra = $byJenis[$jenis] ?? [];
    if ($indoor) {
        $extra[] = 'Ruangan Ber-AC';
    } else {
        $extra[] = 'Mushola';
    }

    return array_unique(array_merge($base, $extra));
}

function getTips(string $jenis): string {
    $tips = [
        'Alam'     => 'Datang pagi hari (06.00–08.00) untuk menghindari keramaian dan mendapatkan cahaya terbaik untuk foto.',
        'Sejarah'  => 'Siapkan pakaian sopan dan nyaman. Sewa pemandu lokal untuk pengalaman yang lebih berkesan.',
        'Budaya'   => 'Periksa jadwal pertunjukan atau upacara sebelum berkunjung agar tidak ketinggalan momen spesial.',
        'Museum'   => 'Siapkan 1.5–2 jam waktu kunjungan. Tidak diperkenankan memotret beberapa koleksi — perhatikan rambu.',
        'Rekreasi' => 'Beli tiket online untuk menghindari antrean panjang di loket, terutama saat akhir pekan.',
        'Belanja'  => 'Tawar harga dengan ramah — ini bagian dari budaya belanja tradisional Jogja.',
        'Kuliner'  => 'Kunjungi saat jam makan untuk menikmati hidangan segar langsung dari dapur.',
    ];
    return $tips[$jenis] ?? 'Periksa jam operasional sebelum berkunjung agar tidak kehabisan waktu.';
}
