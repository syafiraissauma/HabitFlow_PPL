<?php
/**
 * HabitFlow - Daftar Seluruh Kebiasaan
 */
require_once __DIR__ . '/../config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

// Filters
$search = trim($_GET['search'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT);
$status = $_GET['status'] ?? 'all'; // 'all', 'active', 'inactive'

// Build Query
$sql = "
    SELECT h.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
    FROM habits h
    JOIN categories c ON h.category_id = c.id
    WHERE h.user_id = ?
";
$params = [$userId];

if (!empty($search)) {
    $sql .= " AND (h.name LIKE ? OR h.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if (!empty($categoryId)) {
    $sql .= " AND h.category_id = ?";
    $params[] = $categoryId;
}

if ($status === 'active') {
    $sql .= " AND h.is_active = 1";
} elseif ($status === 'inactive') {
    $sql .= " AND h.is_active = 0";
}

$sql .= " ORDER BY h.is_active DESC, h.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$habits = $stmt->fetchAll();

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = 'Kebiasaan Saya';
$activeMenu = 'habits';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="app-content">
        <?= flash_render() ?>

        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold mb-1">Kebiasaan Saya</h4>
                <p class="text-muted small mb-0">Kelola daftar kebiasaan positif yang ingin Anda bangun dan pertahankan.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/habits/create.php" class="btn btn-primary-habit">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Kebiasaan Baru
                </a>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="card-habit p-3 mb-4">
            <form action="<?= BASE_URL ?>/habits/index.php" method="GET" class="row g-2 align-items-center">
                <!-- Search -->
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Cari nama atau deskripsi..." value="<?= e($search) ?>">
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Hanya Aktif</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <!-- Filter Buttons -->
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-habit flex-grow-1">Filter</button>
                    <?php if (!empty($search) || !empty($categoryId) || $status !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/habits/index.php" class="btn btn-light border text-muted" title="Reset filter">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Habit Cards Grid -->
        <?php if (empty($habits)): ?>
            <div class="card-habit p-5 text-center">
                <div class="stat-icon-wrapper mx-auto mb-3" style="width: 64px; height: 64px; font-size: 2rem; background: var(--color-primary-light); color: var(--color-primary);">
                    <i class="bi bi-clipboard2-x"></i>
                </div>
                <h5 class="fw-bold">Tidak ada kebiasaan ditemukan</h5>
                <p class="text-muted small mx-auto" style="max-width: 400px;">
                    <?= (!empty($search) || !empty($categoryId) || $status !== 'all') ? 'Tidak ada kebiasaan yang sesuai dengan kriteria filter Anda.' : 'Anda belum membuat kebiasaan apapun. Mulailah perjalanan produktif Anda hari ini!' ?>
                </p>
                <div class="mt-2">
                    <?php if (!empty($search) || !empty($categoryId) || $status !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/habits/index.php" class="btn btn-outline-habit btn-sm me-2">Reset Filter</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/habits/create.php" class="btn btn-primary-habit btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Buat Kebiasaan Baru
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($habits as $h): 
                    // Calculate streak & consistency
                    $streak = calculate_habit_streak($pdo, $h['id']);

                    // Fetch schedule days if weekly
                    $scheduleDaysText = 'Setiap Hari';
                    if ($h['frequency_type'] === 'weekly_custom') {
                        $stmtSched = $pdo->prepare("SELECT day_of_week FROM habit_schedules WHERE habit_id = ? ORDER BY day_of_week ASC");
                        $stmtSched->execute([$h['id']]);
                        $days = $stmtSched->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($days)) {
                            $dayNames = array_map(function($d) { return get_day_short_indo($d); }, $days);
                            $scheduleDaysText = implode(', ', $dayNames);
                        }
                    }
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card-habit p-4 h-100 d-flex flex-column position-relative <?= !$h['is_active'] ? 'opacity-75' : '' ?>">
                        <!-- Header with Category & Active Badge -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge" style="background-color: <?= e($h['category_color']) ?>; font-size: 0.75rem;">
                                <i class="bi <?= e($h['category_icon']) ?> me-1"></i><?= e($h['category_name']) ?>
                            </span>
                            <?php if ($h['is_active']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 6px;"></i>Aktif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                    Nonaktif
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Habit Title & Description -->
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="rounded-3 p-2 d-flex align-items-center justify-content-center text-white flex-shrink-0" style="width: 44px; height: 44px; background-color: <?= e($h['color']) ?>;">
                                <i class="bi <?= e($h['icon']) ?> fs-5"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h6 class="fw-bold mb-1 text-dark text-truncate">
                                    <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $h['id'] ?>" class="text-dark text-decoration-none">
                                        <?= e($h['name']) ?>
                                    </a>
                                </h6>
                                <p class="text-muted small mb-0 text-truncate" style="font-size: 0.8rem;">
                                    <?= !empty($h['description']) ? e($h['description']) : 'Tidak ada deskripsi.' ?>
                                </p>
                            </div>
                        </div>

                        <!-- Habit Stats & Schedule Info -->
                        <div class="bg-light p-2 rounded-3 mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted"><i class="bi bi-clock-history me-1"></i>Jadwal:</span>
                                <span class="fw-semibold text-dark text-truncate" style="max-width: 140px;"><?= e($scheduleDaysText) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="bi bi-fire me-1 text-warning"></i>Streak:</span>
                                <span class="fw-bold text-dark">
                                    <?= $streak['current_streak'] ?> hari <span class="text-muted fw-normal">(Rekor: <?= $streak['longest_streak'] ?>)</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center">
                            <a href="<?= BASE_URL ?>/habits/detail.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-habit py-1 px-2" style="font-size: 0.8rem;">
                                <i class="bi bi-eye me-1"></i>Detail
                            </a>
                            <div class="d-flex gap-1">
                                <a href="<?= BASE_URL ?>/habits/edit.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-light border py-1 px-2 text-primary" title="Ubah Kebiasaan">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-light border py-1 px-2 text-danger" title="Hapus Kebiasaan" onclick="confirmDeleteHabit(<?= $h['id'] ?>, '<?= e(addslashes($h['name'])) ?>')">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteHabitModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-body p-4 text-center">
                <div class="stat-icon-wrapper mx-auto mb-3 bg-danger bg-opacity-10 text-danger" style="width: 56px; height: 56px; font-size: 1.75rem;">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h5 class="fw-bold mb-2">Hapus Kebiasaan Ini?</h5>
                <p class="text-muted small mb-4">
                    Apakah Anda yakin ingin menghapus kebiasaan <strong id="deleteHabitName" class="text-dark"></strong>? Seluruh riwayat catatan aktivitas terkait kebiasaan ini juga akan dihapus.
                </p>
                <form action="<?= BASE_URL ?>/habits/delete.php" method="POST" id="deleteHabitForm">
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
