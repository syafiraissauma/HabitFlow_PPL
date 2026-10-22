<?php
/**
 * HabitFlow - CSRF Protection Helper
 */

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function csrf_verify($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_protect() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!csrf_verify($token)) {
            http_response_code(403);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid atau telah kedaluwarsa. Silakan refresh halaman.']);
                exit;
            }
            die("
                <div style='font-family:sans-serif; max-width:500px; margin:50px auto; padding:20px; border:1px solid #f87171; border-radius:8px; background:#fef2f2; color:#991b1b;'>
                    <h3>Akses Ditolak (403)</h3>
                    <p>Sesi atau token keamanan (CSRF) Anda telah kedaluwarsa. Silakan muat ulang halaman dan coba lagi.</p>
                    <a href='javascript:history.back()' style='color:#15803d; text-decoration:none; font-weight:bold;'>&larr; Kembali</a>
                </div>
            ");
        }
    }
}
