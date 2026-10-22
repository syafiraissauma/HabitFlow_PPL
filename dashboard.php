<?php
/**
 * HabitFlow - Dashboard Utama
 */
require_once __DIR__ . '/config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

$today = date('Y-m-d');
$stats = get_user_dashboard_stats($pdo, $userId, $today);

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="app-main">
    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="app-content">
        <!-- Flash Alert -->
        <?= flash_render() ?>

        <!-- Motivation & Daily Summary Banner -->
        <div class="motivation-banner mb-4">
            <div class="row align-items-center position-relative" style="z-index: 2;">
                <div class="col-lg-8 mb-3 mb-lg-0">
                    <span class="badge bg-white bg-opacity-25 text-white mb-2 px-3 py-1 rounded-pill">
                        <i class="bi bi-stars me-1 text-warning"></i> Refleksi Harian
                    </span>
                    <h4 class="fw-bold mb-2 text-white">
                        <?= e(get_greeting()) ?>, <?= e(explode(' ', $user['name'])[0]) ?>!
                    </h4>
                    <p class="mb-0 text-white-50" id="stat-motivation-quote" style="max-width: 650px; font-size: 0.95rem;">
                        <?= e($stats['quote']) ?>
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="bg-white bg-opacity-10 p-3 rounded-3 d-inline-block text-start w-100" style="backdrop-filter: blur(8px);">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-white small fw-semibold">Progres Hari Ini</span>
                            <span class="text-white fw-bold fs-6" id="stat-completion-rate"><?= $stats['completion_rate'] ?>%</span>
                        </div>
                        <div class="progress progress-habit bg-dark bg-opacity-25" style="height: 8px;">
                            <div class="progress-bar bg-warning" id="stat-progress-bar" role="progressbar" style="width: <?= $stats['completion_rate'] ?>%;" aria-valuenow="<?= $stats['completion_rate'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="text-white-50 small mt-2">
                            <i class="bi bi-check2-all me-1 text-white"></i>
                            <span id="stat-completed-count"><?= $stats['completed_count'] ?></span> dari <?= $stats['scheduled_count'] ?> kebiasaan selesai
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 Statistics Cards -->
        <div class="row g-3 mb-4">
            <!-- Total Kebiasaan -->
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Total Kebiasaan</div>
                            <div class="stat-value"><?= $stats['total_habits'] ?></div>
                            <small class="text-muted">Aktif dipantau</small>
                        </div>
                        <div class="stat-icon-wrapper" style="background-color: var(--color-primary-light); color: var(--color-primary);">
                            <i class="bi bi-collection"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selesai Hari Ini -->
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Selesai Hari Ini</div>
                            <div class="stat-value text-success" id="stat-card-completed"><?= $stats['completed_count'] ?></div>
                            <small class="text-success"><i class="bi bi-check-circle me-1"></i>Tuntas</small>
                        </div>
                        <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Belum Selesai -->
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Belum Selesai</div>
                            <div class="stat-value text-secondary" id="stat-pending-count"><?= $stats['pending_count'] ?></div>
                            <small class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Menunggu aksi</small>
                        </div>
                        <div class="stat-icon-wrapper bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-hourglass"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Streak Terbaik -->
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">Streak Terbaik</div>
                            <div class="stat-value text-warning"><?= $stats['best_overall_streak'] ?> <span style="font-size: 1rem; font-weight: 500;">hari</span></div>
                            <small class="text-muted"><i class="bi bi-fire text-warning me-1"></i>Rekor konsistensi</small>
                        </div>
                        <div class="stat-icon-wrapper" style="background-color: var(--color-accent-amber-light); color: var(--color-accent-amber);">
                            <i class="bi bi-fire"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Section: Today's Checklist & 7-Day Chart -->
        <div class="row g-4 mb-4">
            <!-- Left: Today's Habit Checklist -->
            <div class="col-lg-7">
                <div class="card-habit p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Checklist Aktivitas Hari Ini</h5>
                            <p class="text-muted small mb-0">Tandai kebiasaan yang berhasil Anda lakukan hari ini.</p>
                        </div>
                        <a href="<?= BASE_URL ?>/habits/create.php" class="btn btn-sm btn-outline-habit">
                            <i class="bi bi-plus-lg me-1"></i>Tambah
                        </a>
                    </div>

                    <?php if (empty($stats['scheduled_habits'])): ?>
                        <!-- Empty State -->
                        <div class="text-center py-5 my-2">
                            <div class="stat-icon-wrapper mx-auto mb-3" style="width: 64px; height: 64px; font-size: 2rem; background: var(--color-primary-light); color: var(--color-primary);">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Tidak ada jadwal kebiasaan hari ini</h6>
                            <p class="text-muted small mx-auto" style="max-width: 380px;">
                                Anda belum memiliki kebiasaan aktif yang terjadwal untuk hari ini. Mulai tambahkan kebiasaan baru Anda sekarang!
                            </p>
                            <a href="<?= BASE_URL ?>/habits/create.php" class="btn btn-primary-habit btn-sm">
                                <i class="bi bi-plus-circle me-1"></i> Buat Kebiasaan Pertama
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- List of scheduled habits -->
                        <div class="habit-checklist-list mt-3">
                            <?php foreach ($stats['scheduled_habits'] as $item): ?>
                                <div class="habit-checklist-item <?= $item['is_completed'] ? 'is-done' : '' ?>" id="habit-item-<?= $item['id'] ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <!-- Custom Checkbox -->
                                        <div class="habit-checkbox <?= $item['is_completed'] ? 'checked' : '' ?>" 
                                             onclick="HabitFlow.toggleHabit(<?= $item['id'] ?>, '<?= $today ?>', this)"
                                             role="button"
                                             title="Tandai selesai / belum selesai">
                                            <?php if ($item['is_completed']): ?>
                                                <i class="bi bi-check-lg fw-bold"></i>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Habit Information -->
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <span class="badge" style="background-color: <?= e($item['category_color']) ?>; font-size: 0.7rem;">
                                                    <i class="bi <?= e($item['category_icon']) ?> me-1"></i><?= e($item['category_name']) ?>
                                                </span>
                                                <span class="streak-badge">
                                                    <i class="bi bi-fire"></i><?= $item['current_streak'] ?> hari
                                                </span>
                                            </div>
                                            <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $item['id'] ?>" class="habit-name fw-bold text-dark text-decoration-none d-block">
                                                <?= e($item['name']) ?>
                                            </a>
                                            <?php if (!empty($item['description'])): ?>
                                                <div class="text-muted small text-truncate" style="max-width: 320px;">
                                                    <?= e($item['description']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Right Meta & Actions -->
                                    <div class="text-end ps-2">
                                        <div class="completion-time-container mb-1">
                                            <?php if ($item['is_completed'] && !empty($item['completed_at'])): ?>
                                                <span class="badge bg-light text-secondary border">
                                                    <i class="bi bi-clock me-1"></i><?= format_time_indo($item['completed_at']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-light text-muted border-0 p-1" title="Lihat Detail Kebiasaan">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: 7-Day Completion Trend Chart -->
            <div class="col-lg-5">
                <div class="card-habit p-4 h-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Tren 7 Hari Terakhir</h5>
                            <p class="text-muted small mb-0">Persentase konsistensi harian Anda</p>
                        </div>
                        <a href="<?= BASE_URL ?>/reports/index.php" class="btn btn-sm btn-outline-habit" title="Lihat Evaluasi Lengkap">
                            Laporan <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <!-- Chart Container -->
                    <div class="flex-grow-1 position-relative" style="min-height: 250px;">
                        <canvas id="weeklyTrendChart"></canvas>
                    </div>

                    <!-- Chart Explanatory Note -->
                    <div class="mt-3 pt-3 border-top small text-muted d-flex align-items-center">
                        <i class="bi bi-info-circle me-2 text-success fs-6"></i>
                        <span>Grafik menunjukkan rasio kebiasaan yang tuntas terhadap total kebiasaan yang dijadwalkan pada hari tersebut.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mini Weekly / Recent Activity Quick Matrix -->
        <div class="card-habit p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Pintasan Riwayat & Kalender</h5>
                    <p class="text-muted small mb-0">Pantau catatan kebiasaan Anda bulan ini</p>
                </div>
                <a href="<?= BASE_URL ?>/history/index.php" class="btn btn-outline-habit btn-sm">
                    <i class="bi bi-calendar3 me-1"></i> Buka Kalender Lengkap
                </a>
            </div>

            <div class="row g-2 text-center">
                <?php
                // Display current 7 days window with completed/total stats
                for ($d = 6; $d >= 0; $d--):
                    $dateObj = date('Y-m-d', strtotime("-{$d} days"));
                    $dayName = get_day_name_indo((int)date('w', strtotime($dateObj)));
                    $dateLabel = date('d M', strtotime($dateObj));
                    $isToday = ($dateObj === $today);

                    // Fetch stats for this date
                    $stmtCount = $pdo->prepare("
                        SELECT COUNT(hl.id) 
                        FROM habit_logs hl
                        JOIN habits h ON hl.habit_id = h.id
                        WHERE h.user_id = ? AND hl.activity_date = ? AND hl.is_completed = 1
                    ");
                    $stmtCount->execute([$userId, $dateObj]);
                    $done = (int)$stmtCount->fetchColumn();
                ?>
                <div class="col">
                    <div class="p-3 rounded-3 border <?= $isToday ? 'border-success' : 'bg-light' ?>" style="<?= $isToday ? 'background-color: var(--color-primary-light);' : '' ?>">
                        <div class="small fw-semibold <?= $isToday ? 'text-success' : 'text-muted' ?>"><?= $dayName ?></div>
                        <div class="fw-bold text-dark my-1"><?= $dateLabel ?></div>
                        <div>
                            <?php if ($done > 0): ?>
                                <span class="badge bg-success bg-opacity-75 rounded-pill px-2 py-1">
                                    <i class="bi bi-check-lg me-1"></i><?= $done ?> selesai
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-25 text-secondary rounded-pill px-2 py-1">
                                    0 selesai
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Chart.js Script for 7-Day Trend -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('weeklyTrendChart');
    if (!ctx) return;

    const labels = <?= json_encode($stats['trend']['labels']) ?>;
    const percentages = <?= json_encode($stats['trend']['percentages']) ?>;
    const completed = <?= json_encode($stats['trend']['completed']) ?>;
    const total = <?= json_encode($stats['trend']['total']) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Penyelesaian (%)',
                data: percentages,
                borderColor: '#4A7C59',
                backgroundColor: 'rgba(74, 124, 89, 0.12)',
                borderWidth: 3,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#213E2E',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const index = context.dataIndex;
                            return `Tingkat: ${context.parsed.y}% (${completed[index]}/${total[index]} kebiasaan)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 25,
                        callback: function (val) {
                            return val + '%';
                        }
                    },
                    grid: {
                        color: '#EEF2EF'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});
</script>
