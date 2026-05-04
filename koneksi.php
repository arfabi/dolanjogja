<?php
// =====================================================
// koneksi.php — Koneksi Database DolanJogja
// =====================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dolanjogja');

$koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$koneksi) {
    http_response_code(500);
    die(json_encode(['error' => 'Koneksi database gagal: ' . mysqli_connect_error()]));
}

mysqli_set_charset($koneksi, 'utf8mb4');
