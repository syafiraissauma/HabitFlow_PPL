<?php
/**
 * HabitFlow - Database Connection
 * Menggunakan PDO dengan Prepared Statements & Error Handling
 * Mendukung MySQL (Local XAMPP / Cloud) & Otomatis Fallback ke SQLite (Vercel Serverless)
 */

defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_PORT') or define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'habitflow_db');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

function get_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // 1. Coba koneksi MySQL terlebih dahulu (untuk Local XAMPP atau Cloud MySQL via DB_HOST)
    $hasRemoteHost = !empty(getenv('DB_HOST')) && getenv('DB_HOST') !== 'localhost';
    $isVercel = !empty(getenv('VERCEL')) || !empty(getenv('AWS_LAMBDA_FUNCTION_NAME'));

    // Di Vercel tanpa remote DB_HOST, langsung gunakan SQLite agar cepat dan tanpa lag
    if (!$isVercel || $hasRemoteHost) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Koneksi MySQL gagal: " . $e->getMessage() . ". Beralih ke SQLite fallback.");
        }
    }

    // 2. Fallback Otomatis ke SQLite (Zero-Config untuk Vercel Serverless)
    try {
        $sqliteDir = $isVercel ? sys_get_temp_dir() : (__DIR__ . '/../database');
        if (!is_dir($sqliteDir)) {
            @mkdir($sqliteDir, 0777, true);
        }
        $sqliteFile = $sqliteDir . '/habitflow.sqlite';
        $isNewDb = !file_exists($sqliteFile) || filesize($sqliteFile) === 0;

        $pdo = new PDO("sqlite:" . $sqliteFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec("PRAGMA foreign_keys = ON;");

        // Daftarkan fungsi SQL kompatibilitas MySQL ke SQLite
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('NOW', function() { return date('Y-m-d H:i:s'); });
            $pdo->sqliteCreateFunction('CURDATE', function() { return date('Y-m-d'); });
        }

        // Jika file database SQLite baru dibuat, inisialisasi tabel dan seed data demo
        if ($isNewDb) {
            init_sqlite_database_schema($pdo);
        }

        return $pdo;
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        die("
            <div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:24px; border:1px solid #e2e8f0; border-radius:12px; background:#fff7ed; color:#9a3412;'>
                <h2 style='margin-top:0;'>Gagal Menginisialisasi Database</h2>
                <p>Terjadi kesalahan saat menghubungkan ke database: " . htmlspecialchars($e->getMessage()) . "</p>
            </div>
        ");
    }
}

/**
 * Inisialisasi skema tabel dan data awal untuk SQLite
 */
function init_sqlite_database_schema($pdo) {
    $schema = "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        bio TEXT DEFAULT 'Membangun kebiasaan baik setiap hari.',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        icon TEXT NOT NULL DEFAULT 'bi-bookmark',
        color TEXT NOT NULL DEFAULT '#4A7C59',
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS habits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        category_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        icon TEXT NOT NULL DEFAULT 'bi-check-circle',
        color TEXT NOT NULL DEFAULT '#4A7C59',
        frequency_type TEXT NOT NULL DEFAULT 'daily',
        target_time TEXT,
        start_date DATE NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
    );

    CREATE TABLE IF NOT EXISTS habit_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        habit_id INTEGER NOT NULL,
        day_of_week INTEGER NOT NULL,
        UNIQUE (habit_id, day_of_week),
        FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS habit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        habit_id INTEGER NOT NULL,
        activity_date DATE NOT NULL,
        is_completed INTEGER NOT NULL DEFAULT 1,
        completed_at DATETIME,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (habit_id, activity_date),
        FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
    );
    ";

    $pdo->exec($schema);

    // Seed Categories
    $pdo->exec("
    INSERT INTO categories (id, name, icon, color, description) VALUES
    (1, 'Kesehatan', 'bi-heart-pulse-fill', '#2E7D32', 'Aktivitas fisik, nutrisi, hidrasi, dan kesehatan umum'),
    (2, 'Pendidikan', 'bi-mortarboard-fill', '#1976D2', 'Belajar materi kuliah, membaca jurnal, dan mengerjakan tugas'),
    (3, 'Produktivitas', 'bi-lightning-charge-fill', '#E65100', 'Manajemen waktu, fokus kerja, dan penyelesaian proyek'),
    (4, 'Spiritual', 'bi-moon-stars-fill', '#5E35B1', 'Ibadah harian, doa, refleksi diri, dan ketenangan jiwa'),
    (5, 'Keuangan', 'bi-wallet2', '#00796B', 'Mencatat pengeluaran harian, menabung, dan evaluasi anggaran'),
    (6, 'Pengembangan Diri', 'bi-flower1', '#4A7C59', 'Membaca buku, melatih keterampilan baru, dan jurnal harian'),
    (7, 'Lainnya', 'bi-star-fill', '#546E7A', 'Kebiasaan personal dan hobi lainnya');
    ");

    // Seed Demo User (Password: password123)
    $passwordHash = password_hash('password123', PASSWORD_BCRYPT);
    $stmtUser = $pdo->prepare("INSERT INTO users (id, name, email, password, bio) VALUES (1, 'Syafira Putri', 'syafira@habitflow.local', ?, 'Mahasiswi yang berkomitmen membangun kebiasaan produktif setiap hari.')");
    $stmtUser->execute([$passwordHash]);

    // Seed Sample Habits
    $pdo->exec("
    INSERT INTO habits (id, user_id, category_id, name, description, icon, color, frequency_type, start_date, is_active) VALUES
    (1, 1, 1, 'Minum air putih 2 liter', 'Menjaga hidrasi tubuh dengan minum minimal 8 gelas air mineral per hari', 'bi-droplet-fill', '#2E7D32', 'daily', '2026-10-01', 1),
    (2, 1, 6, 'Membaca buku non-fiksi 15 menit', 'Membaca minimal 1 bab buku pengembangan diri atau materi ilmiah', 'bi-book-half', '#4A7C59', 'daily', '2026-10-01', 1),
    (3, 1, 2, 'Belajar & review materi kuliah 1 jam', 'Mengerjakan tugas atau mereview catatan perkuliahan minggu ini', 'bi-laptop', '#1976D2', 'daily', '2026-10-01', 1),
    (4, 1, 1, 'Olahraga ringan & stretching 20 menit', 'Peregangan tubuh, jogging santai atau senam kardio ringan', 'bi-activity', '#2E7D32', 'weekly_custom', '2026-10-01', 1),
    (5, 1, 5, 'Catat pengeluaran & tabungan harian', 'Mencatat mutasi uang keluar dan menyisihkan uang ke tabungan', 'bi-cash-coin', '#00796B', 'daily', '2026-10-01', 1),
    (6, 1, 4, 'Refleksi dan ibadah malam', 'Merenungkan hari yang telah lewat dan berdoa sebelum beristirahat', 'bi-moon-stars-fill', '#5E35B1', 'daily', '2026-10-01', 1);

    INSERT INTO habit_schedules (habit_id, day_of_week) VALUES
    (4, 1), (4, 3), (4, 5), (4, 6);
    ");

    // Seed sample recent logs
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

    $pdo->exec("
    INSERT INTO habit_logs (habit_id, activity_date, is_completed, completed_at) VALUES
    (1, '{$twoDaysAgo}', 1, '{$twoDaysAgo} 19:30:00'),
    (2, '{$twoDaysAgo}', 1, '{$twoDaysAgo} 21:00:00'),
    (1, '{$yesterday}', 1, '{$yesterday} 18:00:00'),
    (2, '{$yesterday}', 1, '{$yesterday} 20:30:00'),
    (3, '{$yesterday}', 1, '{$yesterday} 15:45:00'),
    (1, '{$today}', 1, '{$today} 09:15:00');
    ");
}
