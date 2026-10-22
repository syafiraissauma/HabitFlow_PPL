<?php
/**
 * HabitFlow - Root Router
 */
require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
