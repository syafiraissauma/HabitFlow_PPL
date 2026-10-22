<?php
/**
 * HabitFlow - Riwayat Aktivitas & Kalender Bulanan
 */
require_once __DIR__ . '/../config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

// Month & Year selection
$month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT) ?: (int)date('n');
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year < 2020 || $year > 2035) $year = (int)date('Y');

// Selected date
$selectedDate = filter_input(INPUT_GET, 'date', FILTER_DEFAULT);
if (empty($selectedDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    // If current month & year matches today, default to today; else 1st of month
    if ($month == (int)date('n') && $year == (int)date('Y')) {
        $selectedDate = date('Y-m-d');
    } else {
        $selectedDate = sprintf('%04d-%02d-01', $year, $month);
    }
}

// Filters
$filterCategory = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$filterHabit = filter_input(INPUT_GET, 'habit', FILTER_VALIDATE_INT);

// Calculate Prev & Next Month
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Month info
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayTs = strtotime("{$year}-{$month}-01");
$firstDow = (int)date('w', $firstDayTs); // 0 = Minggu, 1 = Senin, ... 6 = Sabtu

// Fetch monthly completion counts per date for calendar display
$startDateMonth = sprintf('%04d-%02d-01', $year, $month);
$endDateMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$sqlMonthly = "
    SELECT hl.activity_date, COUNT(hl.id) AS completed_count
    FROM habit_logs hl
    JOIN habits h ON hl.habit_id = h.id
    WHERE h.user_id = ? 
      AND hl.is_completed = 1
      AND hl.activity_date BETWEEN ? AND ?
";
$paramsMonthly = [$userId, $startDateMonth, $endDateMonth];

if ($filterCategory) {
    $sqlMonthly .= " AND h.category_id = ?";
    $paramsMonthly[] = $filterCategory;
}
if ($filterHabit) {
    $sqlMonthly .= " AND h.id = ?";
    $paramsMonthly[] = $filterHabit;
}

$sqlMonthly .= " GROUP BY hl.activity_date";

$stmtMonth = $pdo->prepare($sqlMonthly);
$stmtMonth->execute($paramsMonthly);
$monthlyLogCounts = $stmtMonth->fetchAll(PDO::FETCH_KEY_PAIR); // ['2026-10-01' => 3, ...]

// Fetch Activities for the Selected Date
$selectedDow = (int)date('w', strtotime($selectedDate));

$sqlSelected = "
    SELECT h.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
           hl.is_completed, hl.completed_at, hl.note
    FROM habits h
    JOIN categories c ON h.category_id = c.id
    LEFT JOIN habit_logs hl ON hl.habit_id = h.id AND hl.activity_date = ?
    WHERE h.user_id = ? AND h.is_active = 1 AND h.start_date <= ?
";
$paramsSelected = [$selectedDate, $userId, $selectedDate];

if ($filterCategory) {
    $sqlSelected .= " AND h.category_id = ?";
    $paramsSelected[] = $filterCategory;
}
if ($filterHabit) {
    $sqlSelected .= " AND h.id = ?";
    $paramsSelected[] = $filterHabit;
}

$sqlSelected .= " ORDER BY hl.is_completed DESC, h.name ASC";

$stmtSelected = $pdo->prepare($sqlSelected);
$stmtSelected->execute($paramsSelected);
$allActiveCandidates = $stmtSelected->fetchAll();

// Filter candidate habits that are scheduled for $selectedDow
$selectedDayHabits = [];
$selectedCompletedCount = 0;

foreach ($allActiveCandidates as $cand) {
    $isScheduled = false;
    if ($cand['frequency_type'] === 'daily') {
        $isScheduled = true;
    } else {
        $stmtCheck = $pdo->prepare("SELECT 1 FROM habit_schedules WHERE habit_id = ? AND day_of_week = ?");
        $stmtCheck->execute([$cand['id'], $selectedDow]);
        if ($stmtCheck->fetchColumn()) {
            $isScheduled = true;
        }
    }

    if ($isScheduled) {
        $cand['is_completed'] = ($cand['is_completed'] == 1) ? 1 : 0;
        if ($cand['is_completed']) {
            $selectedCompletedCount++;
        }
        $selectedDayHabits[] = $cand;
    }
}

$selectedScheduledCount = count($selectedDayHabits);
$selectedRate = ($selectedScheduledCount > 0) ? round(($selectedCompletedCount / $selectedScheduledCount) * 100) : 0;

// Fetch filter options
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$userHabits = $pdo->prepare("SELECT id, name FROM habits WHERE user_id = ? AND is_active = 1 ORDER BY name ASC");
$userHabits->execute([$userId]);
$userHabitsList = $userHabits->fetchAll();

$monthNames = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$pageTitle = 'Riwayat & Kalender Aktivitas';
$activeMenu = 'history';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="app-content">
        <?= flash_render() ?>

        <!-- Title -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Riwayat & Kalender Aktivitas</h4>
                <p class="text-muted small mb-0">Lihat rekap pemenuhan kebiasaan bulanan dan catatan aktivitas harian.</p>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card-habit p-3 mb-4">
            <form action="<?= BASE_URL ?>/history/index.php" method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="month" value="<?= $month ?>">
                <input type="hidden" name="year" value="<?= $year ?>">
                <input type="hidden" name="date" value="<?= e($selectedDate) ?>">

                <!-- Filter Kategori -->
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">Filter Kategori</label>
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Kebiasaan Spesifik -->
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">Filter Kebiasaan</label>
                    <select name="habit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Kebiasaan</option>
                        <?php foreach ($userHabitsList as $uh): ?>
                            <option value="<?= $uh['id'] ?>" <?= $filterHabit == $uh['id'] ? 'selected' : '' ?>>
                                <?= e($uh['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Reset -->
                <div class="col-md-2 d-flex align-items-end">
                    <a href="<?= BASE_URL ?>/history/index.php?month=<?= $month ?>&year=<?= $year ?>&date=<?= e($selectedDate) ?>" class="btn btn-sm btn-light border w-100" title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <!-- Left: Calendar Grid -->
            <div class="col-lg-7">
                <div class="card-habit p-4">
                    <!-- Calendar Month Navigator -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-calendar2-month text-success me-2"></i>
                            <?= $monthNames[$month] ?> <?= $year ?>
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= BASE_URL ?>/history/index.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>&category=<?= $filterCategory ?>&habit=<?= $filterHabit ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/history/index.php?month=<?= date('n') ?>&year=<?= date('Y') ?>&date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary px-2">
                                Bulan Ini
                            </a>
                            <a href="<?= BASE_URL ?>/history/index.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>&category=<?= $filterCategory ?>&habit=<?= $filterHabit ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Calendar Day Names Header -->
                    <div class="calendar-grid mb-2">
                        <div class="calendar-day-header text-danger">Min</div>
                        <div class="calendar-day-header">Sen</div>
                        <div class="calendar-day-header">Sel</div>
                        <div class="calendar-day-header">Rab</div>
                        <div class="calendar-day-header">Kam</div>
                        <div class="calendar-day-header">Jum</div>
                        <div class="calendar-day-header">Sab</div>
                    </div>

                    <!-- Calendar Cells -->
                    <div class="calendar-grid">
                        <?php
                        // Empty leading cells before the 1st of month
                        for ($pad = 0; $pad < $firstDow; $pad++):
                        ?>
                            <div class="calendar-cell is-other-month"></div>
                        <?php endfor; ?>

                        <?php
                        // Days in month
                        $todayStr = date('Y-m-d');
                        for ($dayNum = 1; $dayNum <= $daysInMonth; $dayNum++):
                            $curDateStr = sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
                            $isToday = ($curDateStr === $todayStr);
                            $isSelected = ($curDateStr === $selectedDate);
                            $completedOnDate = $monthlyLogCounts[$curDateStr] ?? 0;
                        ?>
                            <a href="<?= BASE_URL ?>/history/index.php?month=<?= $month ?>&year=<?= $year ?>&date=<?= $curDateStr ?>&category=<?= $filterCategory ?>&habit=<?= $filterHabit ?>" 
                               class="calendar-cell <?= $isToday ? 'is-today' : '' ?> <?= $isSelected ? 'is-selected' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="calendar-cell-date <?= $isToday ? 'text-success' : 'text-dark' ?>"><?= $dayNum ?></span>
                                    <?php if ($isToday): ?>
                                        <span class="badge bg-success rounded-pill" style="font-size: 0.55rem; padding: 2px 4px;">Kini</span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto">
                                    <?php if ($completedOnDate > 0): ?>
                                        <span class="calendar-cell-badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 d-inline-block">
                                            <i class="bi bi-check-lg"></i> <?= $completedOnDate ?> tuntas
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.65rem;">-</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <!-- Calendar Legend -->
                    <div class="d-flex flex-wrap gap-3 mt-4 pt-3 border-top small text-muted">
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-success bg-opacity-25 text-success border border-success">Contoh</span>
                            <span>Ada kebiasaan tuntas</span>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge border border-2 border-primary bg-light text-primary">Border</span>
                            <span>Tanggal yang sedang dipilih</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Activity List for Selected Date -->
            <div class="col-lg-5">
                <div class="card-habit p-4 h-100 d-flex flex-column">
                    <!-- Selected Date Header -->
                    <div class="border-bottom pb-3 mb-3">
                        <span class="badge bg-light text-secondary border mb-1">Rincian Tanggal</span>
                        <h5 class="fw-bold mb-1 text-dark"><?= format_date_indo($selectedDate) ?></h5>
                        <p class="text-muted small mb-0">
                            Penyelesaian: <strong><?= $selectedCompletedCount ?></strong> dari <strong><?= $selectedScheduledCount ?></strong> kebiasaan (<strong><?= $selectedRate ?>%</strong>)
                        </p>
                        <div class="progress progress-habit mt-2" style="height: 6px;">
                            <div class="progress-bar" style="width: <?= $selectedRate ?>%;"></div>
                        </div>
                    </div>

                    <!-- List of Habits on Selected Date -->
                    <div class="flex-grow-1 overflow-auto" style="max-height: 480px;">
                        <?php if (empty($selectedDayHabits)): ?>
                            <div class="text-center py-5">
                                <div class="stat-icon-wrapper mx-auto mb-2 bg-light text-muted" style="width: 48px; height: 48px;">
                                    <i class="bi bi-calendar-x fs-4"></i>
                                </div>
                                <h6 class="fw-semibold text-dark">Tidak ada aktivitas terjadwal</h6>
                                <p class="text-muted small">Tidak ada kebiasaan yang dijadwalkan pada tanggal ini.</p>
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($selectedDayHabits as $hab): ?>
                                    <div class="habit-checklist-item py-2 px-3 <?= $hab['is_completed'] ? 'is-done' : '' ?>" id="history-item-<?= $hab['id'] ?>">
                                        <div class="d-flex align-items-center gap-3">
                                            <!-- Checkbox toggle for this date -->
                                            <div class="habit-checkbox <?= $hab['is_completed'] ? 'checked' : '' ?>"
                                                 onclick="HabitFlow.toggleHabit(<?= $hab['id'] ?>, '<?= $selectedDate ?>', this)"
                                                 role="button"
                                                 title="Ubah status penyelesaian">
                                                <?php if ($hab['is_completed']): ?>
                                                    <i class="bi bi-check-lg fw-bold"></i>
                                                <?php endif; ?>
                                            </div>

                                            <div>
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="badge" style="background-color: <?= e($hab['category_color']) ?>; font-size: 0.65rem;">
                                                        <?= e($hab['category_name']) ?>
                                                    </span>
                                                    <?php if ($hab['is_completed'] && !empty($hab['completed_at'])): ?>
                                                        <span class="badge bg-light text-secondary border" style="font-size: 0.65rem;">
                                                            <i class="bi bi-clock me-1"></i><?= format_time_indo($hab['completed_at']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $hab['id'] ?>" class="habit-name fw-bold text-dark text-decoration-none small d-block">
                                                    <?= e($hab['name']) ?>
                                                </a>
                                            </div>
                                        </div>

                                        <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $hab['id'] ?>" class="btn btn-sm btn-light border-0 p-1 text-muted">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
