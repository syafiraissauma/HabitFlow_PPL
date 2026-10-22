<?php
/**
 * HabitFlow - Authentication Middleware & Functions
 */

function is_logged_in() {
    return !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        flash_set('warning', 'Silakan masuk terlebih dahulu untuk mengakses halaman tersebut.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_guest() {
    if (is_logged_in()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function current_user($pdo) {
    static $user = null;
    if ($user !== null) {
        return $user;
    }

    if (!is_logged_in()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, name, email, bio, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // User record was deleted from database
        logout_user();
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }

    return $user;
}

function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
