<?php
/**
 * HabitFlow - Vercel Serverless Entrypoint / Router
 * Menangani dan merutekan semua request ke file PHP yang sesuai pada Vercel
 */

$rootDir = dirname(__DIR__);

// Set current working directory ke root project
chdir($rootDir);

// Ambil path URL dari request
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = urldecode($path ?: '/');
$trimmed = ltrim($path, '/');

// 1. Root URL -> jalankan index.php utama
if ($path === '/' || $path === '' || $trimmed === 'index.php') {
    require $rootDir . '/index.php';
    exit;
}

// 2. Blokir akses ke file/folder sensitif
$blocked = ['.git', '.gitignore', 'database/habitflow.sql', 'config/database.example.php', 'test_suite.php'];
foreach ($blocked as $b) {
    if (strpos($trimmed, $b) === 0) {
        http_response_code(403);
        echo "403 Forbidden";
        exit;
    }
}

// 3. Cek file langsung (misal: dashboard.php, auth/login.php, api/toggle_habit.php)
$target = $rootDir . '/' . $trimmed;

if (is_file($target)) {
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    
    // Jika file PHP, eksekusi
    if ($ext === 'php') {
        require $target;
        exit;
    }
    
    // Jika file statis yang lolos ke router
    $mimeTypes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'json'  => 'application/json',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($target);
    exit;
}

// 4. Cek rute tanpa ekstensi .php (misal: /dashboard -> /dashboard.php)
if (is_file($target . '.php')) {
    require $target . '.php';
    exit;
}

// 5. Cek folder dengan index.php (misal: /habits -> /habits/index.php)
if (is_dir($target) && is_file($target . '/index.php')) {
    require $target . '/index.php';
    exit;
}

// 6. 404 Not Found jika file tidak ditemukan
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Tidak Ditemukan - HabitFlow</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #1e293b; text-align: center; }
        .card { max-width: 450px; padding: 40px; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        h1 { font-size: 3.5rem; margin: 0; color: #6366f1; }
        p { color: #64748b; margin: 12px 0 24px; line-height: 1.5; }
        a { display: inline-block; background: #6366f1; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; }
        a:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="card">
        <h1>404</h1>
        <h2>Halaman Tidak Ditemukan</h2>
        <p>Halaman <code><?= htmlspecialchars($path) ?></code> yang Anda cari tidak tersedia.</p>
        <a href="/">Kembali ke HabitFlow</a>
    </div>
</body>
</html>
<?php
exit;
