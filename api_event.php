<?php
// =====================================================
// api_event.php — API Data Event dari Database MySQL
// Endpoint:
//   GET ?action=list [&kategori=Konser] [&jenis=Gratis] [&search=kw]
//                    [&featured=1] [&upcoming=1] [&limit=20] [&offset=0]
//   GET ?action=detail&id=xxx
//   GET ?action=meta
// =====================================================

require_once __DIR__ . '/koneksi.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'list';

// =====================================================
// HELPER FUNCTIONS
// =====================================================

function formatHargaEvent(int $min, int $max, string $jenis): string {
    if ($jenis === 'Gratis' || ($min === 0 && $max === 0)) return 'Gratis';
    if ($min === $max) return 'Rp ' . number_format($min, 0, ',', '.');
    return 'Rp ' . number_format($min, 0, ',', '.') . ' – Rp ' . number_format($max, 0, ',', '.');
}

function formatTanggal(string $mulai, string $selesai): string {
    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $m = new DateTime($mulai);
    $s = new DateTime($selesai);

    if ($mulai === $selesai) {
        return $m->format('d') . ' ' . $bulan[(int)$m->format('m') - 1] . ' ' . $m->format('Y');
    }
    if ($m->format('Y-m') === $s->format('Y-m')) {
        return $m->format('d') . '–' . $s->format('d') . ' ' . $bulan[(int)$m->format('m') - 1] . ' ' . $m->format('Y');
    }
    return $m->format('d') . ' ' . $bulan[(int)$m->format('m') - 1] . ' – '
         . $s->format('d') . ' ' . $bulan[(int)$s->format('m') - 1] . ' ' . $s->format('Y');
}

function formatTanggalPanjang(string $mulai, string $selesai): string {
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $m = new DateTime($mulai);
    $s = new DateTime($selesai);
    if ($mulai === $selesai) {
        return $m->format('d') . ' ' . $bulan[(int)$m->format('m') - 1] . ' ' . $m->format('Y');
    }
    if ($m->format('Y-m') === $s->format('Y-m')) {
        return $m->format('d') . '–' . $s->format('d') . ' ' . $bulan[(int)$m->format('m') - 1] . ' ' . $m->format('Y');
    }
    return $m->format('d') . ' ' . $bulan[(int)$m->format('m') - 1] . ' – '
         . $s->format('d') . ' ' . $bulan[(int)$s->format('m') - 1] . ' ' . $s->format('Y');
}

function formatJamEvent(?string $mulai, ?string $selesai): string {
    if (!$mulai) return 'Sepanjang Hari';
    $m = substr($mulai, 0, 5);
    $s = $selesai ? substr($selesai, 0, 5) : '';
    return $s ? "{$m} – {$s} WIB" : "{$m} WIB";
}

function isEventUpcoming(string $selesai): bool {
    return $selesai >= date('Y-m-d');
}

// =====================================================
// ACTION: LIST EVENT
// =====================================================
if ($action === 'list') {
    $where  = ['is_active = 1'];
    $params = [];
    $types  = '';

    // Filter kategori
    $kategori = trim($_GET['kategori'] ?? '');
    if ($kategori && $kategori !== 'Semua') {
        $where[]  = 'kategori = ?';
        $params[] = $kategori;
        $types   .= 's';
    }

    // Filter jenis tiket
    $jenis = trim($_GET['jenis'] ?? '');
    if ($jenis && $jenis !== 'Semua') {
        $where[]  = 'jenis_event = ?';
        $params[] = $jenis;
        $types   .= 's';
    }

    // Filter featured
    if (isset($_GET['featured']) && $_GET['featured'] == '1') {
        $where[] = 'is_featured = 1';
    }

    // Filter upcoming (default: tampilkan semua termasuk lewat; set ?upcoming=1 untuk hanya mendatang)
    if (isset($_GET['upcoming']) && $_GET['upcoming'] == '1') {
        $where[] = 'tanggal_selesai >= CURDATE()';
    }

    // Search keyword
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $like     = '%' . $search . '%';
        $where[]  = '(nama_event LIKE ? OR deskripsi LIKE ? OR lokasi LIKE ? OR penyelenggara LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'ssss';
    }

    // Pagination
    $limit  = max(1, min(50, (int)($_GET['limit']  ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));

    $whereStr = implode(' AND ', $where);

    // Hitung total
    $stmtCount = mysqli_prepare($koneksi, "SELECT COUNT(*) as total FROM event WHERE {$whereStr}");
    if ($types && $stmtCount) mysqli_stmt_bind_param($stmtCount, $types, ...$params);
    mysqli_stmt_execute($stmtCount);
    $total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCount))['total'] ?? 0);

    // Query utama — urutkan berdasarkan tanggal terdekat, lalu featured
    $sql = "SELECT
                id_event, nama_event, slug, deskripsi,
                tanggal_mulai, tanggal_selesai,
                jam_mulai, jam_selesai,
                jenis_event, harga_min, harga_max,
                lokasi, link_gmaps, latitude, longitude,
                kategori, penyelenggara, link_tiket,
                foto_utama, stat_view, stat_like,
                is_featured
            FROM event
            WHERE {$whereStr}
            ORDER BY tanggal_mulai ASC, is_featured DESC, stat_view DESC
            LIMIT ? OFFSET ?";

    $allParams = array_merge($params, [$limit, $offset]);
    $allTypes  = $types . 'ii';

    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_featured']    = (bool)$row['is_featured'];
        $row['harga_min']      = (int)$row['harga_min'];
        $row['harga_max']      = (int)$row['harga_max'];
        $row['stat_view']      = (int)$row['stat_view'];
        $row['stat_like']      = (int)$row['stat_like'];
        $row['harga_label']    = formatHargaEvent($row['harga_min'], $row['harga_max'], $row['jenis_event']);
        $row['tanggal_label']  = formatTanggal($row['tanggal_mulai'], $row['tanggal_selesai']);
        $row['jam_label']      = formatJamEvent($row['jam_mulai'], $row['jam_selesai']);
        $row['is_upcoming']    = isEventUpcoming($row['tanggal_selesai']);
        $row['link_tiket_valid'] = (!empty($row['link_tiket']) && $row['link_tiket'] !== '-');
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
// ACTION: DETAIL EVENT
// =====================================================
if ($action === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID event tidak valid.']);
        exit;
    }

    $stmt = mysqli_prepare($koneksi, "SELECT * FROM event WHERE id_event = ? AND is_active = 1 LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event tidak ditemukan.']);
        exit;
    }

    $event['is_featured']      = (bool)$event['is_featured'];
    $event['is_active']        = (bool)$event['is_active'];
    $event['harga_min']        = (int)$event['harga_min'];
    $event['harga_max']        = (int)$event['harga_max'];
    $event['stat_view']        = (int)$event['stat_view'];
    $event['stat_like']        = (int)$event['stat_like'];
    $event['harga_label']      = formatHargaEvent($event['harga_min'], $event['harga_max'], $event['jenis_event']);
    $event['tanggal_label']    = formatTanggal($event['tanggal_mulai'], $event['tanggal_selesai']);
    $event['tanggal_panjang']  = formatTanggalPanjang($event['tanggal_mulai'], $event['tanggal_selesai']);
    $event['jam_label']        = formatJamEvent($event['jam_mulai'], $event['jam_selesai']);
    $event['is_upcoming']      = isEventUpcoming($event['tanggal_selesai']);
    $event['link_tiket_valid'] = (!empty($event['link_tiket']) && $event['link_tiket'] !== '-');
    $event['link_gmaps_valid'] = (!empty($event['link_gmaps']));

    echo json_encode([
        'success' => true,
        'event'   => $event,
    ]);
    exit;
}

// =====================================================
// ACTION: META (untuk filter chips)
// =====================================================
if ($action === 'meta') {
    echo json_encode([
        'success'  => true,
        'kategori' => ['Semua','Konser','Budaya','Festival','Pameran','Olahraga','Kuliner','Tradisi','Lainnya'],
        'jenis'    => ['Semua','Gratis','Berbayar'],
    ]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Action tidak dikenal.']);
