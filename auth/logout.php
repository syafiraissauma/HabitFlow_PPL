<?php
/**
 * HabitFlow - Logout Handler
 */
require_once __DIR__ . '/../config/app.php';

logout_user();

session_start();
flash_set('info', 'Anda telah berhasil keluar dari akun.');
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
