<?php
/**
 * HabitFlow - API Toggle Habit Completion Status
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/app.php';

// Authentication check
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk terlebih dahulu.']);
    exit;
}

// CSRF check
$csrfToken = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_verify($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid. Silakan refresh halaman.']);
    exit;
}

$pdo = get_db_connection();
$userId = (int)$_SESSION['user_id'];

$habitId = filter_input(INPUT_POST, 'habit_id', FILTER_VALIDATE_INT);
$activityDate = filter_input(INPUT_POST, 'date', FILTER_DEFAULT);

if (!$habitId || empty($activityDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activityDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
    exit;
}

// Verify habit ownership
$stmtCheck = $pdo->prepare("SELECT id, name, frequency_type, start_date FROM habits WHERE id = ? AND user_id = ? AND is_active = 1");
$stmtCheck->execute([$habitId, $userId]);
$habit = $stmtCheck->fetch();

if (!$habit) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Kebiasaan tidak ditemukan atau tidak aktif.']);
    exit;
}

// Check existing log
$stmtLog = $pdo->prepare("SELECT id, is_completed FROM habit_logs WHERE habit_id = ? AND activity_date = ?");
$stmtLog->execute([$habitId, $activityDate]);
$existingLog = $stmtLog->fetch();

$now = date('Y-m-d H:i:s');
$isCompleted = 1;
$completedAtFormatted = '';

if ($existingLog) {
    $newStatus = ($existingLog['is_completed'] == 1) ? 0 : 1;
    $completedAt = ($newStatus == 1) ? $now : null;

    $stmtUpdate = $pdo->prepare("
        UPDATE habit_logs 
        SET is_completed = ?, completed_at = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmtUpdate->execute([$newStatus, $completedAt, $existingLog['id']]);
    $isCompleted = $newStatus;
    if ($newStatus == 1) {
        $completedAtFormatted = format_time_indo($now);
    }
} else {
    $stmtInsert = $pdo->prepare("
        INSERT INTO habit_logs (habit_id, activity_date, is_completed, completed_at) 
        VALUES (?, ?, 1, ?)
    ");
    $stmtInsert->execute([$habitId, $activityDate, $now]);
    $isCompleted = 1;
    $completedAtFormatted = format_time_indo($now);
}

// Fetch refreshed stats for the target date
$stats = get_user_dashboard_stats($pdo, $userId, $activityDate);

$message = ($isCompleted == 1) 
    ? "Hebat! Kebiasaan \"" . $habit['name'] . "\" berhasil diselesaikan." 
    : "Status kebiasaan \"" . $habit['name'] . "\" dibatalkan.";

echo json_encode([
    'success'                => true,
    'is_completed'           => (bool)$isCompleted,
    'completed_at_formatted' => $completedAtFormatted,
    'message'                => $message,
    'stats'                  => [
        'completed_count' => $stats['completed_count'],
        'pending_count'   => $stats['pending_count'],
        'completion_rate' => $stats['completion_rate'],
        'quote'           => $stats['quote']
    ]
]);
exit;
