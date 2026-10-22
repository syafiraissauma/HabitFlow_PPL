<?php
/**
 * HabitFlow - Ekspor Laporan Aktivitas ke CSV
 */
require_once __DIR__ . '/../config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

// Filter Period
$period = $_GET['period'] ?? 'weekly';
$filterCategory = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$filterHabit = filter_input(INPUT_GET, 'habit', FILTER_VALIDATE_INT);

$today = date('Y-m-d');
$startDate = match ($period) {
    'daily'   => $today,
    'weekly'  => date('Y-m-d', strtotime('-6 days')),
    'monthly' => date('Y-m-d', strtotime('-29 days')),
    default   => date('Y-m-d', strtotime('-6 days'))
};
$endDate = $today;

// Query matching logs
$sql = "
    SELECT hl.activity_date, h.name AS habit_name, c.name AS category_name,
           hl.is_completed, hl.completed_at, hl.note
    FROM habit_logs hl
    JOIN habits h ON hl.habit_id = h.id
    JOIN categories c ON h.category_id = c.id
    WHERE h.user_id = ? AND hl.activity_date BETWEEN ? AND ?
";
$params = [$userId, $startDate, $endDate];

if ($filterCategory) {
    $sql .= " AND h.category_id = ?";
    $params[] = $filterCategory;
}
if ($filterHabit) {
    $sql .= " AND h.id = ?";
    $params[] = $filterHabit;
}

$sql .= " ORDER BY hl.activity_date DESC, h.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// CSV Filename
$filename = 'habitflow_laporan_' . $period . '_' . date('Ymd_His') . '.csv';

// Output Headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Header row
fputcsv($output, [
    'No',
    'Tanggal Aktivitas',
    'Nama Kebiasaan',
    'Kategori',
    'Status Penyelesaian',
    'Waktu Selesai',
    'Catatan / Evaluasi'
]);

// Write Rows
$no = 1;
foreach ($logs as $row) {
    fputcsv($output, [
        $no++,
        $row['activity_date'],
        $row['habit_name'],
        $row['category_name'],
        ($row['is_completed'] == 1 ? 'Selesai' : 'Belum Selesai'),
        !empty($row['completed_at']) ? $row['completed_at'] : '-',
        !empty($row['note']) ? $row['note'] : '-'
    ]);
}

fclose($output);
exit;
