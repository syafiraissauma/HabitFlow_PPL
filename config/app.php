<?php
/**
 * HabitFlow - Application Core Config & Initialization
 */

// Timezone
date_default_timezone_set('Asia/Jakarta');

// App Info
define('APP_NAME', 'HabitFlow');
define('APP_TAGLINE', 'Build Better Habits, One Day at a Time.');
define('APP_TITLE', 'Sistem Informasi Pemantauan dan Evaluasi Kebiasaan Harian Berbasis Website');
define('APP_VERSION', '1.0.0');

// Base URL detection
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Calculate base directory path relative to htdocs
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // If inside a subfolder (like /habits, /auth), find root /HabitFlowPPL
    $parts = explode('/', trim($scriptDir, '/'));
    $projectFolder = '';
    if (!empty($parts[0])) {
        $projectFolder = '/' . $parts[0];
    }
    
    // Default fallback to /HabitFlowPPL
    if (empty($projectFolder) || $projectFolder === '/') {
        $projectFolder = '/HabitFlowPPL';
    }
    
    define('BASE_URL', rtrim($protocol . $host . $projectFolder, '/'));
}

// Start Session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Include database & helpers
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
