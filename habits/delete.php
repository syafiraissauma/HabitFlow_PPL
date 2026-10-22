<?php
/**
 * HabitFlow - Hapus Kebiasaan Handler
 */
require_once __DIR__ . '/../config/app.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/habits/index.php');
    exit;
}

csrf_protect();

$pdo = get_db_connection();
$userId = (int)$_SESSION['user_id'];
$habitId = filter_input(INPUT_POST, 'habit_id', FILTER_VALIDATE_INT);

if (!$habitId) {
    flash_set('danger', 'ID Kebiasaan tidak valid.');
    header('Location: ' . BASE_URL . '/habits/index.php');
    exit;
}

// Verify ownership
$stmt = $pdo->prepare("SELECT id, name FROM habits WHERE id = ? AND user_id = ?");
$stmt->execute([$habitId, $userId]);
$habit = $stmt->fetch();

if (!$habit) {
    flash_set('danger', 'Kebiasaan tidak ditemukan atau Anda tidak memiliki akses.');
    header('Location: ' . BASE_URL . '/habits/index.php');
    exit;
}

try {
    $stmtDel = $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?");
    $stmtDel->execute([$habitId, $userId]);

    flash_set('success', 'Kebiasaan "' . e($habit['name']) . '" beserta riwayatnya berhasil dihapus.');
} catch (Exception $e) {
    error_log("Delete habit error: " . $e->getMessage());
    flash_set('danger', 'Terjadi kesalahan saat menghapus kebiasaan.');
}

header('Location: ' . BASE_URL . '/habits/index.php');
exit;
