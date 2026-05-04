<?php
// =====================================================
// env.php — Loader file .env untuk DolanJogja
// Include sekali di atas file yang butuh env var.
// =====================================================

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        http_response_code(500);
        die(json_encode(['error' => 'File .env tidak ditemukan. Salin .env.example menjadi .env dan isi konfigurasinya.']));
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Lewati baris komentar atau kosong
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Hanya proses baris yang mengandung '='
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Hapus tanda kutip opsional di value
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Set ke environment & $_ENV jika belum ada
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

/**
 * Ambil nilai env. Jika tidak ditemukan, kembalikan $default.
 */
function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ── Auto-load .env dari root project ─────────────────
// Asumsi env.php ada di folder yang sama dengan .env
loadEnv(__DIR__ . '/.env');
