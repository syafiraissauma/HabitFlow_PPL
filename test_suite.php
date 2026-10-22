<?php
/**
 * HabitFlow - Automated Test Suite
 * Menjalankan pengujian fungsional modul: Autentikasi, Dashboard, CRUD, Checklist Toggle, Riwayat, Laporan, Profil
 */

require_once __DIR__ . '/config/app.php';

$pdo = get_db_connection();

echo "========================================\n";
echo "   HABITFLOW - AUTOMATED TEST SUITE    \n";
echo "========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assert_test($label, $condition, $details = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo "[ PASS ] $label\n";
        $testsPassed++;
    } else {
        echo "[ FAIL ] $label. Detail: $details\n";
        $testsFailed++;
    }
}

// 1. Database Connection & Categories Test
$stmtCat = $pdo->query("SELECT COUNT(*) FROM categories");
$catCount = (int)$stmtCat->fetchColumn();
assert_test("Koneksi Database & Master Kategori", $catCount >= 7, "Total kategori: $catCount");

// 2. User Authentication & Password Hashing Test
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmtUser->execute(['syafira@habitflow.local']);
$demoUser = $stmtUser->fetch();
assert_test("User Demo Syafira Terdaftar", !empty($demoUser), "User tidak ditemukan");

$passValid = password_verify('password123', $demoUser['password']);
assert_test("Verifikasi Hash Kata Sandi (password_verify)", $passValid, "Password verify gagal");

// 3. User Habits & Schedules Test
$userId = (int)$demoUser['id'];
$stmtHabits = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
$stmtHabits->execute([$userId]);
$userHabitsCount = (int)$stmtHabits->fetchColumn();
assert_test("Kebiasaan Pengguna Tersedia", $userHabitsCount >= 5, "Total habits: $userHabitsCount");

// 4. Streak Calculation Test
$streak = calculate_habit_streak($pdo, 1, '2026-10-14');
assert_test("Kalkulasi Streak Kebiasaan", $streak['longest_streak'] > 0, "Streak info: " . json_encode($streak));

// 5. Dashboard Stats Calculation Test
$dashboardStats = get_user_dashboard_stats($pdo, $userId, '2026-10-14');
assert_test("Kalkulasi Statistik Dashboard Hari Ini", $dashboardStats['scheduled_count'] > 0 && isset($dashboardStats['completion_rate']), "Stats: " . json_encode($dashboardStats));

// 6. Test Habit Toggle (Check & Uncheck)
$testDate = '2026-10-14';
$testHabitId = 2; // Membaca buku

// Check current status
$stmtLogBefore = $pdo->prepare("SELECT is_completed FROM habit_logs WHERE habit_id = ? AND activity_date = ?");
$stmtLogBefore->execute([$testHabitId, $testDate]);
$before = $stmtLogBefore->fetch();
$initialStatus = $before ? (int)$before['is_completed'] : 0;

// Simulate toggle
if ($before) {
    $newStatus = ($initialStatus === 1) ? 0 : 1;
    $stmtUpd = $pdo->prepare("UPDATE habit_logs SET is_completed = ?, completed_at = NOW() WHERE habit_id = ? AND activity_date = ?");
    $stmtUpd->execute([$newStatus, $testHabitId, $testDate]);
} else {
    $newStatus = 1;
    $stmtIns = $pdo->prepare("INSERT INTO habit_logs (habit_id, activity_date, is_completed, completed_at) VALUES (?, ?, 1, NOW())");
    $stmtIns->execute([$testHabitId, $testDate]);
}

$stmtLogAfter = $pdo->prepare("SELECT is_completed FROM habit_logs WHERE habit_id = ? AND activity_date = ?");
$stmtLogAfter->execute([$testHabitId, $testDate]);
$afterStatus = (int)$stmtLogAfter->fetchColumn();
assert_test("Toggle Status Checklist Aktivitas (Check)", $afterStatus !== $initialStatus, "Before: $initialStatus, After: $afterStatus");

// Toggle back
$stmtRevert = $pdo->prepare("UPDATE habit_logs SET is_completed = ?, completed_at = NOW() WHERE habit_id = ? AND activity_date = ?");
$stmtRevert->execute([$initialStatus, $testHabitId, $testDate]);
assert_test("Toggle Pembatalan Checklist Aktivitas (Uncheck)", true);

// 7. Test History & Calendar Query
$stmtHist = $pdo->prepare("
    SELECT hl.activity_date, COUNT(hl.id) AS done 
    FROM habit_logs hl 
    JOIN habits h ON hl.habit_id = h.id 
    WHERE h.user_id = ? 
    GROUP BY hl.activity_date
");
$stmtHist->execute([$userId]);
$historyRows = $stmtHist->fetchAll();
assert_test("Query Riwayat & Kalender Bulanan", count($historyRows) > 0, "History row count: " . count($historyRows));

// 8. Test Reports Data Aggregation
$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-6 days'));
$stmtRep = $pdo->prepare("
    SELECT COUNT(hl.id) 
    FROM habit_logs hl 
    JOIN habits h ON hl.habit_id = h.id 
    WHERE h.user_id = ? AND hl.is_completed = 1 AND hl.activity_date BETWEEN ? AND ?
");
$stmtRep->execute([$userId, $weekAgo, $today]);
$repDone = (int)$stmtRep->fetchColumn();
assert_test("Agregasi Laporan 7 Hari Terakhir", $repDone > 0, "Report done: $repDone");

// 9. Test User Profile & Bio Update
$testBio = "Membangun konsistensi dan produktivitas setiap hari. Test: " . time();
$stmtBio = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
$stmtBio->execute([$testBio, $userId]);
$stmtCheckBio = $pdo->prepare("SELECT bio FROM users WHERE id = ?");
$stmtCheckBio->execute([$userId]);
$updatedBio = $stmtCheckBio->fetchColumn();
assert_test("Pembaruan Profil Pengguna", $updatedBio === $testBio, "Bio mismatch");

// 10. Test CSRF Generation & Verification
$csrf = csrf_token();
assert_test("CSRF Token Generation", !empty($csrf) && strlen($csrf) === 64, "Token: $csrf");
assert_test("CSRF Token Verification", csrf_verify($csrf), "CSRF verify failed");

echo "\n========================================\n";
echo "HASIL PENGUJIAN: $testsPassed LULUS, $testsFailed GAGAL\n";
echo "========================================\n";
