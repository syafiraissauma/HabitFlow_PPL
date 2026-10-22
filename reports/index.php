<?php
/**
 * HabitFlow - Laporan & Evaluasi Perkembangan Kebiasaan
 */
require_once __DIR__ . '/../config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

// Filter Period: 'daily', 'weekly', 'monthly'
$period = $_GET['period'] ?? 'weekly';
if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
    $period = 'weekly';
}

$filterCategory = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$filterHabit = filter_input(INPUT_GET, 'habit', FILTER_VALIDATE_INT);

$today = date('Y-m-d');
$startDate = match ($period) {
    'daily'   => $today,
    'weekly'  => date('Y-m-d', strtotime('-6 days')),
    'monthly' => date('Y-m-d', strtotime('-29 days')),
};
$endDate = $today;

// 1. Fetch User Active Habits with category
$sqlHabits = "
    SELECT h.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
    FROM habits h
    JOIN categories c ON h.category_id = c.id
    WHERE h.user_id = ?
";
$paramsHabits = [$userId];
if ($filterCategory) {
    $sqlHabits .= " AND h.category_id = ?";
    $paramsHabits[] = $filterCategory;
}
if ($filterHabit) {
    $sqlHabits .= " AND h.id = ?";
    $paramsHabits[] = $filterHabit;
}
$stmtH = $pdo->prepare($sqlHabits);
$stmtH->execute($paramsHabits);
$activeHabits = $stmtH->fetchAll();

// 2. Calculate Evaluation Metrics for each habit within the period
$habitPerformance = [];
$totalScheduledPeriod = 0;
$totalCompletedPeriod = 0;
$categoryCounts = [];

// Fetch scheduled days for weekly habits
$habitSchedulesMap = [];
$stmtAllSched = $pdo->query("SELECT habit_id, day_of_week FROM habit_schedules");
while ($row = $stmtAllSched->fetch()) {
    $habitSchedulesMap[$row['habit_id']][] = (int)$row['day_of_week'];
}

// Generate all dates in period
$periodDates = [];
$curTs = strtotime($startDate);
$endTs = strtotime($endDate);
while ($curTs <= $endTs) {
    $periodDates[] = date('Y-m-d', $curTs);
    $curTs = strtotime('+1 day', $curTs);
}

// Fetch all completed logs in period for current user
$sqlLogs = "
    SELECT hl.habit_id, hl.activity_date, c.name AS category_name, c.color AS category_color
    FROM habit_logs hl
    JOIN habits h ON hl.habit_id = h.id
    JOIN categories c ON h.category_id = c.id
    WHERE h.user_id = ? AND hl.is_completed = 1 AND hl.activity_date BETWEEN ? AND ?
";
$paramsLogs = [$userId, $startDate, $endDate];
if ($filterCategory) {
    $sqlLogs .= " AND h.category_id = ?";
    $paramsLogs[] = $filterCategory;
}
if ($filterHabit) {
    $sqlLogs .= " AND h.id = ?";
    $paramsLogs[] = $filterHabit;
}
$stmtL = $pdo->prepare($sqlLogs);
$stmtL->execute($paramsLogs);
$allLogs = $stmtL->fetchAll();

// Map logs by habit_id and activity_date
$logsByHabitDate = [];
foreach ($allLogs as $lg) {
    $logsByHabitDate[$lg['habit_id']][$lg['activity_date']] = true;
    
    // Category distribution count
    $catName = $lg['category_name'];
    if (!isset($categoryCounts[$catName])) {
        $categoryCounts[$catName] = ['count' => 0, 'color' => $lg['category_color']];
    }
    $categoryCounts[$catName]['count']++;
}

// Evaluate each habit
foreach ($activeHabits as $h) {
    $schedCount = 0;
    $doneCount = 0;

    $hSchedDays = ($h['frequency_type'] === 'daily') 
        ? [0, 1, 2, 3, 4, 5, 6] 
        : ($habitSchedulesMap[$h['id']] ?? []);
    $schedMap = array_flip($hSchedDays);

    foreach ($periodDates as $d) {
        if (strtotime($h['start_date']) > strtotime($d)) continue;
        $dow = (int)date('w', strtotime($d));
        if (isset($schedMap[$dow])) {
            $schedCount++;
            if (!empty($logsByHabitDate[$h['id']][$d])) {
                $doneCount++;
            }
        }
    }

    $rate = ($schedCount > 0) ? round(($doneCount / $schedCount) * 100) : 0;
    $streak = calculate_habit_streak($pdo, $h['id']);

    $habitPerformance[] = [
        'id'             => $h['id'],
        'name'           => $h['name'],
        'category_name'  => $h['category_name'],
        'category_color' => $h['category_color'],
        'scheduled'      => $schedCount,
        'completed'      => $doneCount,
        'rate'           => $rate,
        'current_streak' => $streak['current_streak'],
        'longest_streak' => $streak['longest_streak']
    ];

    $totalScheduledPeriod += $schedCount;
    $totalCompletedPeriod += $doneCount;
}

$overallPeriodRate = ($totalScheduledPeriod > 0) ? round(($totalCompletedPeriod / $totalScheduledPeriod) * 100) : 0;

// Find highest & lowest consistent habits
$mostConsistent = null;
$leastConsistent = null;

if (!empty($habitPerformance)) {
    $sortedByRate = $habitPerformance;
    usort($sortedByRate, function ($a, $b) {
        if ($b['rate'] === $a['rate']) {
            return $b['completed'] <=> $a['completed'];
        }
        return $b['rate'] <=> $a['rate'];
    });

    $mostConsistent = $sortedByRate[0];
    $leastConsistent = end($sortedByRate);
}

// 3. Trend Data for Chart.js across period dates
$trendLabels = [];
$trendRates = [];
$trendCounts = [];

foreach ($periodDates as $d) {
    $dow = (int)date('w', strtotime($d));
    $trendLabels[] = ($period === 'daily') ? 'Hari Ini' : (get_day_short_indo($dow) . ' ' . date('d/m', strtotime($d)));

    $daySched = 0;
    $dayDone = 0;

    foreach ($activeHabits as $h) {
        if (strtotime($h['start_date']) > strtotime($d)) continue;
        $hSchedDays = ($h['frequency_type'] === 'daily') ? [0, 1, 2, 3, 4, 5, 6] : ($habitSchedulesMap[$h['id']] ?? []);
        if (in_array($dow, $hSchedDays)) {
            $daySched++;
            if (!empty($logsByHabitDate[$h['id']][$d])) {
                $dayDone++;
            }
        }
    }

    $trendCounts[] = $dayDone;
    $trendRates[] = ($daySched > 0) ? round(($dayDone / $daySched) * 100) : 0;
}

// Categories list for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$userHabitsList = $pdo->prepare("SELECT id, name FROM habits WHERE user_id = ? ORDER BY name ASC");
$userHabitsList->execute([$userId]);
$userHabits = $userHabitsList->fetchAll();

$pageTitle = 'Laporan & Evaluasi';
$activeMenu = 'reports';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="app-content">
        <?= flash_render() ?>

        <!-- Page Header & Export Action -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold mb-1">Laporan & Evaluasi Kebiasaan</h4>
                <p class="text-muted small mb-0">Evaluasi performa, konsistensi, dan analisis pencapaian target kebiasaan Anda.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/reports/export_csv.php?period=<?= $period ?>&category=<?= $filterCategory ?>&habit=<?= $filterHabit ?>" class="btn btn-outline-habit">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor Laporan (CSV)
                </a>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="card-habit p-3 mb-4">
            <form action="<?= BASE_URL ?>/reports/index.php" method="GET" class="row g-2 align-items-center">
                <!-- Period Selector -->
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Periode Evaluasi</label>
                    <div class="btn-group w-100" role="group">
                        <a href="<?= BASE_URL ?>/reports/index.php?period=daily&category=<?= $filterCategory ?>&habit=<?= $filterHabit ?>" class="btn btn-sm <?= $period === 'daily' ? 'btn-primary-habit' : 'btn-light border' ?>">Harian</a>
                        <a href="<?= BASE_URL ?>/reports/index.php?period=weekly&category=<?= $filterCategory ?>&habit=<?= $filterHabit ?>" class="btn btn-sm <?= $period === 'weekly' ? 'btn-primary-habit' : 'btn-light border' ?>">7 Hari</a>
                        <a href="<?= BASE_URL ?>/reports/index.php?period=monthly&category=<?= $filterCategory ?>&habit=<?= $filterHabit ?>" class="btn btn-sm <?= $period === 'monthly' ? 'btn-primary-habit' : 'btn-light border' ?>">30 Hari</a>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Kategori</label>
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Habit Filter -->
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Kebiasaan</label>
                    <select name="habit" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Kebiasaan</option>
                        <?php foreach ($userHabits as $uh): ?>
                            <option value="<?= $uh['id'] ?>" <?= $filterHabit == $uh['id'] ? 'selected' : '' ?>>
                                <?= e($uh['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Reset -->
                <div class="col-md-2 d-flex align-items-end">
                    <input type="hidden" name="period" value="<?= $period ?>">
                    <a href="<?= BASE_URL ?>/reports/index.php?period=<?= $period ?>" class="btn btn-sm btn-light border w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- 4 Summary Metric Cards -->
        <div class="row g-3 mb-4">
            <!-- Total Selesai -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Total Selesai</div>
                    <div class="stat-value text-success"><?= $totalCompletedPeriod ?></div>
                    <small class="text-muted">Dari <?= $totalScheduledPeriod ?> jadwal periode ini</small>
                </div>
            </div>

            <!-- Tingkat Penyelesaian -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Rata-rata Penyelesaian</div>
                    <div class="stat-value text-primary"><?= $overallPeriodRate ?>%</div>
                    <small class="text-muted">Konsistensi keseluruhan</small>
                </div>
            </div>

            <!-- Paling Konsisten -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Paling Konsisten</div>
                    <div class="stat-value text-truncate" style="font-size: 1.25rem;">
                        <?= $mostConsistent ? e($mostConsistent['name']) : '-' ?>
                    </div>
                    <small class="text-success fw-bold">
                        <?= $mostConsistent ? ($mostConsistent['rate'] . '% sukses (' . $mostConsistent['completed'] . 'x)') : '-' ?>
                    </small>
                </div>
            </div>

            <!-- Butuh Perhatian -->
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-label">Butuh Perhatian</div>
                    <div class="stat-value text-truncate text-danger" style="font-size: 1.25rem;">
                        <?= $leastConsistent ? e($leastConsistent['name']) : '-' ?>
                    </div>
                    <small class="text-danger fw-bold">
                        <?= $leastConsistent ? ($leastConsistent['rate'] . '% sukses') : '-' ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Chart Grid -->
        <div class="row g-4 mb-4">
            <!-- 1. Trend Over Time Line Chart -->
            <div class="col-lg-8">
                <div class="card-habit p-4 h-100 d-flex flex-column">
                    <div class="mb-3">
                        <h5 class="fw-bold mb-1">Tren Progres Konsistensi</h5>
                        <p class="text-muted small mb-0">Perkembangan tingkat penyelesaian kebiasaan dari hari ke hari dalam periode terpilih.</p>
                    </div>
                    <div class="flex-grow-1 position-relative" style="min-height: 280px;">
                        <canvas id="periodTrendChart"></canvas>
                    </div>
                    <!-- Chart Explanation -->
                    <div class="mt-3 pt-3 border-top small text-muted">
                        <i class="bi bi-lightbulb text-warning me-1"></i>
                        <strong>Insight:</strong> Tren yang stabil di atas 70% menunjukkan ritme kebiasaan yang mulai terbentuk menjadi gaya hidup alami.
                    </div>
                </div>
            </div>

            <!-- 2. Category Distribution Doughnut Chart -->
            <div class="col-lg-4">
                <div class="card-habit p-4 h-100 d-flex flex-column">
                    <div class="mb-3">
                        <h5 class="fw-bold mb-1">Distribusi Kategori</h5>
                        <p class="text-muted small mb-0">Sebaran aktivitas yang berhasil dituntaskan berdasarkan kategori.</p>
                    </div>
                    <div class="flex-grow-1 position-relative d-flex align-items-center justify-content-center" style="min-height: 240px;">
                        <?php if (empty($categoryCounts)): ?>
                            <div class="text-center text-muted small py-4">
                                <i class="bi bi-pie-chart fs-2 d-block mb-1"></i>
                                Belum ada aktivitas selesai pada periode ini.
                            </div>
                        <?php else: ?>
                            <canvas id="categoryDistChart"></canvas>
                        <?php endif; ?>
                    </div>
                    <!-- Chart Explanation -->
                    <div class="mt-3 pt-3 border-top small text-muted">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        Grafik ini memvisualisasikan area kehidupan mana yang paling banyak mendapat perhatian Anda.
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Habit Performance Comparison Table & Horizontal Bar Chart -->
        <div class="card-habit p-4 mb-4">
            <div class="mb-3">
                <h5 class="fw-bold mb-1">Perbandingan Performa Antar Kebiasaan</h5>
                <p class="text-muted small mb-0">Tingkat keberhasilan masing-masing kebiasaan selama <?= $period === 'daily' ? 'hari ini' : ($period === 'weekly' ? '7 hari terakhir' : '30 hari terakhir') ?>.</p>
            </div>

            <?php if (empty($habitPerformance)): ?>
                <div class="text-center py-4 text-muted small">
                    Tidak ada data kebiasaan yang sesuai dengan filter yang dipilih.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Nama Kebiasaan</th>
                                <th>Kategori</th>
                                <th class="text-center">Jadwal Terlaksana</th>
                                <th class="text-center">Streak Terkini</th>
                                <th>Tingkat Penyelesaian</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php foreach ($habitPerformance as $hp): ?>
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $hp['id'] ?>" class="text-dark text-decoration-none">
                                            <?= e($hp['name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?= e($hp['category_color']) ?>;">
                                            <?= e($hp['category_name']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?= $hp['completed'] ?></strong> / <?= $hp['scheduled'] ?> hari
                                    </td>
                                    <td class="text-center">
                                        <span class="streak-badge">
                                            <i class="bi bi-fire"></i><?= $hp['current_streak'] ?> hari
                                        </span>
                                    </td>
                                    <td style="min-width: 180px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress progress-habit flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar <?= $hp['rate'] >= 75 ? 'bg-success' : ($hp['rate'] >= 50 ? 'bg-primary' : 'bg-warning') ?>" style="width: <?= $hp['rate'] ?>%;"></div>
                                            </div>
                                            <span class="fw-bold" style="min-width: 40px;"><?= $hp['rate'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $hp['id'] ?>" class="btn btn-sm btn-outline-habit py-1 px-2" title="Detail">
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Chart.js Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Period Trend Chart
    const trendCtx = document.getElementById('periodTrendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'Tingkat Sukses (%)',
                    data: <?= json_encode($trendRates) ?>,
                    borderColor: '#4A7C59',
                    backgroundColor: 'rgba(74, 124, 89, 0.12)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#1E3A2B',
                    pointBorderColor: '#FFFFFF',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: val => val + '%'
                        },
                        grid: { color: '#EEF2EF' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Category Distribution Chart
    const catCtx = document.getElementById('categoryDistChart');
    if (catCtx) {
        const catLabels = <?= json_encode(array_keys($categoryCounts)) ?>;
        const catData = <?= json_encode(array_column($categoryCounts, 'count')) ?>;
        const catColors = <?= json_encode(array_column($categoryCounts, 'color')) ?>;

        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: catLabels,
                datasets: [{
                    data: catData,
                    backgroundColor: catColors,
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11 }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
});
</script>
