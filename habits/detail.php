<?php
/**
 * HabitFlow - Detail Kebiasaan & Kalender Konsistensi
 */
require_once __DIR__ . '/../config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

$habitId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$habitId) {
    flash_set('danger', 'ID Kebiasaan tidak valid.');
    header('Location: ' . BASE_URL . '/habits/index.php');
    exit;
}

// Fetch habit details with category
$stmt = $pdo->prepare("
    SELECT h.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
    FROM habits h
    JOIN categories c ON h.category_id = c.id
    WHERE h.id = ? AND h.user_id = ?
");
$stmt->execute([$habitId, $userId]);
$habit = $stmt->fetch();

if (!$habit) {
    flash_set('danger', 'Kebiasaan tidak ditemukan.');
    header('Location: ' . BASE_URL . '/habits/index.php');
    exit;
}

// Streak & stats calculation
$streakInfo = calculate_habit_streak($pdo, $habitId);
$completionRate = ($streakInfo['total_scheduled'] > 0) 
    ? round(($streakInfo['total_completed'] / $streakInfo['total_scheduled']) * 100) 
    : 0;

// Fetch scheduled days
$scheduleDaysText = 'Setiap Hari';
$scheduledDaysArray = [0, 1, 2, 3, 4, 5, 6];
if ($habit['frequency_type'] === 'weekly_custom') {
    $stmtSched = $pdo->prepare("SELECT day_of_week FROM habit_schedules WHERE habit_id = ? ORDER BY day_of_week ASC");
    $stmtSched->execute([$habitId]);
    $days = $stmtSched->fetchAll(PDO::FETCH_COLUMN);
    $scheduledDaysArray = $days;
    if (!empty($days)) {
        $dayNames = array_map(function($d) { return get_day_name_indo($d); }, $days);
        $scheduleDaysText = implode(', ', $dayNames);
    }
}
$scheduledDaysMap = array_flip($scheduledDaysArray);

// Fetch recent 30 days logs for this habit
$today = date('Y-m-d');
$stmtLogs = $pdo->prepare("
    SELECT activity_date, is_completed, completed_at, note 
    FROM habit_logs 
    WHERE habit_id = ? 
    ORDER BY activity_date DESC 
    LIMIT 30
");
$stmtLogs->execute([$habitId]);
$recentLogs = $stmtLogs->fetchAll();
$logsMap = [];
foreach ($recentLogs as $log) {
    $logsMap[$log['activity_date']] = $log;
}

$pageTitle = 'Detail: ' . $habit['name'];
$activeMenu = 'habits';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="app-content">
        <?= flash_render() ?>

        <!-- Back Link -->
        <div class="mb-3">
            <a href="<?= BASE_URL ?>/habits/index.php" class="text-secondary small fw-semibold text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Kebiasaan
            </a>
        </div>

        <!-- Header Card -->
        <div class="card-habit p-4 mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white" style="width: 58px; height: 58px; background-color: <?= e($habit['color']) ?>;">
                        <i class="bi <?= e($habit['icon']) ?> fs-3"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge" style="background-color: <?= e($habit['category_color']) ?>;">
                                <i class="bi <?= e($habit['category_icon']) ?> me-1"></i><?= e($habit['category_name']) ?>
                            </span>
                            <?php if ($habit['is_active']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border rounded-pill px-2">Nonaktif</span>
                            <?php endif; ?>
                        </div>
                        <h4 class="fw-bold mb-1 text-dark"><?= e($habit['name']) ?></h4>
                        <p class="text-muted small mb-0"><?= !empty($habit['description']) ? e($habit['description']) : 'Tidak ada catatan tambahan.' ?></p>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/habits/edit.php?id=<?= $habitId ?>" class="btn btn-outline-habit">
                        <i class="bi bi-pencil me-1"></i> Ubah
                    </a>
                    <button type="button" class="btn btn-light border text-danger" onclick="confirmDeleteHabit(<?= $habitId ?>, '<?= e(addslashes($habit['name'])) ?>')">
                        <i class="bi bi-trash3 me-1"></i> Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- 4 Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Streak Saat Ini</div>
                    <div class="stat-value text-warning"><?= $streakInfo['current_streak'] ?> <span class="fs-6 fw-normal text-muted">hari</span></div>
                    <small class="text-muted"><i class="bi bi-fire text-warning me-1"></i>Konsistensi aktif</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Streak Terbaik</div>
                    <div class="stat-value text-success"><?= $streakInfo['longest_streak'] ?> <span class="fs-6 fw-normal text-muted">hari</span></div>
                    <small class="text-muted"><i class="bi bi-trophy text-success me-1"></i>Rekor terpanjang</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Tingkat Sukses</div>
                    <div class="stat-value text-primary"><?= $completionRate ?>%</div>
                    <small class="text-muted"><?= $streakInfo['total_completed'] ?> dari <?= $streakInfo['total_scheduled'] ?> terjadwal</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Jadwal Rutin</div>
                    <div class="fw-bold text-dark mt-2 mb-1 text-truncate" style="font-size: 1.1rem;"><?= e($scheduleDaysText) ?></div>
                    <small class="text-muted">Mulai: <?= format_date_indo($habit['start_date'], false) ?></small>
                </div>
            </div>
        </div>

        <!-- 30-Day Activity Matrix Tracker -->
        <div class="card-habit p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1">Matriks Konsistensi 30 Hari Terakhir</h5>
                    <p class="text-muted small mb-0">Klik pada kotak tanggal untuk menandai atau membatalkan penyelesaian aktivitas.</p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 pt-2">
                <?php
                for ($i = 29; $i >= 0; $i--):
                    $dateObj = date('Y-m-d', strtotime("-{$i} days"));
                    $dow = (int)date('w', strtotime($dateObj));
                    $isScheduled = isset($scheduledDaysMap[$dow]) && (strtotime($dateObj) >= strtotime($habit['start_date']));
                    $isCompleted = isset($logsMap[$dateObj]) && $logsMap[$dateObj]['is_completed'] == 1;
                    $isToday = ($dateObj === $today);

                    $cellBg = '#F3F4F6';
                    $cellBorder = '#E5E7EB';
                    $cellText = '#9CA3AF';
                    $statusTooltip = 'Tidak dijadwalkan';

                    if ($isScheduled) {
                        if ($isCompleted) {
                            $cellBg = 'var(--color-primary)';
                            $cellBorder = 'var(--color-primary-dark)';
                            $cellText = '#FFFFFF';
                            $statusTooltip = 'Selesai: ' . format_date_indo($dateObj, true);
                        } else {
                            $cellBg = '#FEE2E2';
                            $cellBorder = '#FCA5A5';
                            $cellText = '#DC2626';
                            $statusTooltip = ($dateObj === $today) ? 'Belum selesai (Hari Ini)' : 'Terlewat: ' . format_date_indo($dateObj, true);
                        }
                    }
                ?>
                <div class="text-center">
                    <div 
                        onclick="toggleMatrixDate(<?= $habitId ?>, '<?= $dateObj ?>', this)"
                        style="width: 38px; height: 38px; background-color: <?= $cellBg ?>; border: 1px solid <?= $cellBorder ?>; color: <?= $cellText ?>; border-radius: 8px; cursor: <?= $isScheduled ? 'pointer' : 'default' ?>; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; transition: transform 0.15s ease;"
                        title="<?= e($statusTooltip) ?>"
                        class="<?= $isToday ? 'shadow-sm ring' : '' ?>"
                        <?= $isToday ? 'style="outline: 2px solid #213E2E;"' : '' ?>
                    >
                        <?php if ($isCompleted): ?>
                            <i class="bi bi-check-lg text-white"></i>
                        <?php else: ?>
                            <?= date('j', strtotime($dateObj)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted" style="font-size: 0.65rem; margin-top: 2px;"><?= get_day_short_indo($dow) ?></div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Legend -->
            <div class="d-flex flex-wrap gap-4 mt-4 pt-3 border-top small text-muted">
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 16px; height: 16px; background-color: var(--color-primary); border-radius: 4px;"></div>
                    <span>Selesai</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 16px; height: 16px; background-color: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 4px;"></div>
                    <span>Terlewat / Belum Selesai</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div style="width: 16px; height: 16px; background-color: #F3F4F6; border-radius: 4px;"></div>
                    <span>Tidak Terjadwal</span>
                </div>
            </div>
        </div>

        <!-- Recent Logs Table -->
        <div class="card-habit p-4">
            <h5 class="fw-bold mb-3">Catatan Riwayat Terbaru</h5>
            <?php if (empty($recentLogs)): ?>
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-journal-x fs-3 d-block mb-1"></i>
                    Belum ada riwayat aktivitas tercatat untuk kebiasaan ini.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Tanggal Aktivitas</th>
                                <th>Status</th>
                                <th>Waktu Penyelesaian</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td class="fw-bold"><?= format_date_indo($log['activity_date']) ?></td>
                                    <td>
                                        <?php if ($log['is_completed']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i>Selesai
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border rounded-pill px-2 py-1">
                                                Belum Selesai
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($log['completed_at']) ? format_time_indo($log['completed_at']) : '-' ?></td>
                                    <td><?= !empty($log['note']) ? e($log['note']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteHabitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4 text-center">
                <div class="stat-icon-wrapper mx-auto mb-3 bg-danger bg-opacity-10 text-danger" style="width: 56px; height: 56px; font-size: 1.75rem;">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h5 class="fw-bold mb-2">Hapus Kebiasaan Ini?</h5>
                <p class="text-muted small mb-4">
                    Apakah Anda yakin ingin menghapus kebiasaan <strong id="deleteHabitName" class="text-dark"></strong>? Seluruh data riwayat kebiasaan ini akan hilang permanen.
                </p>
                <form action="<?= BASE_URL ?>/habits/delete.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="habit_id" id="deleteHabitId" value="">
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger px-4">Ya, Hapus Sekarang</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDeleteHabit(id, name) {
    document.getElementById('deleteHabitId').value = id;
    document.getElementById('deleteHabitName').textContent = `"${name}"`;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteHabitModal'));
    deleteModal.show();
}

async function toggleMatrixDate(habitId, dateStr, el) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        const formData = new FormData();
        formData.append('habit_id', habitId);
        formData.append('date', dateStr);
        formData.append('csrf_token', csrfToken);

        const res = await fetch('<?= BASE_URL ?>/api/toggle_habit.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            HabitFlow.toast(data.message, 'success');
            setTimeout(() => window.location.reload(), 700);
        } else {
            HabitFlow.toast(data.message, 'danger');
        }
    } catch (e) {
        console.error(e);
        HabitFlow.toast('Gagal menghubungi server.', 'danger');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
