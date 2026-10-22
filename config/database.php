<?php
/**
 * HabitFlow - Database Connection
 * Menggunakan PDO dengan Prepared Statements & Error Handling
 */

defined('DB_HOST') or define('DB_HOST', 'localhost');
defined('DB_NAME') or define('DB_NAME', 'habitflow_db');
defined('DB_USER') or define('DB_USER', 'root');
defined('DB_PASS') or define('DB_PASS', '');
defined('DB_CHARSET') or define('DB_CHARSET', 'utf8mb4');

function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error internally and show friendly message without leaking credentials
            error_log("Database connection error: " . $e->getMessage());
            die("
                <div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:24px; border:1px solid #e2e8f0; border-radius:12px; background:#fff7ed; color:#9a3412;'>
                    <h2 style='margin-top:0;'>Gagal Terhubung ke Database</h2>
                    <p>HabitFlow tidak dapat terhubung ke database MySQL. Pastikan layanan MySQL di XAMPP sudah berjalan dan database <strong>" . htmlspecialchars(DB_NAME) . "</strong> sudah diimpor.</p>
                    <p style='font-size:0.875rem; color:#78350f;'>Silakan periksa konfigurasi pada file <code>config/database.php</code>.</p>
                </div>
            ");
        }
    }

    return $pdo;
}
