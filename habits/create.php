<?php
/**
 * HabitFlow - Tambah Kebiasaan Baru
 */
require_once __DIR__ . '/../config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

// Preset icons & colors
$availableIcons = [
    'bi-check-circle' => 'Centang',
    'bi-droplet-fill' => 'Air / Minum',
    'bi-book-half' => 'Buku / Membaca',
    'bi-activity' => 'Olahraga / Fitness',
    'bi-laptop' => 'Belajar / Kerja',
    'bi-moon-stars-fill' => 'Tidur / Doa',
    'bi-cash-coin' => 'Keuangan / Nabung',
    'bi-heart-pulse-fill' => 'Kesehatan',
    'bi-lightning-charge-fill' => 'Fokus / Produktivitas',
    'bi-bicycle' => 'Gowes / Jalan',
    'bi-flower1' => 'Meditasi / Alam',
    'bi-cup-hot' => 'Nutrisi / Sarapan',
    'bi-pencil-square' => 'Menulis Jurnal'
];

$availableColors = [
    '#4A7C59' => 'Sage Green',
    '#1E3A2B' => 'Forest Green',
    '#1976D2' => 'Sapphire Blue',
    '#00796B' => 'Deep Teal',
    '#E65100' => 'Vibrant Amber',
    '#5E35B1' => 'Royal Purple',
    '#C2185B' => 'Rose Pink',
    '#374151' => 'Slate Dark'
];

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

$errors = [];
$name = '';
$description = '';
$categoryId = 1;
$icon = 'bi-check-circle';
$color = '#4A7C59';
$frequencyType = 'daily';
$scheduleDays = [1, 2, 3, 4, 5]; // Default Mon-Fri
$startDate = date('Y-m-d');
$isActive = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $icon = trim($_POST['icon'] ?? 'bi-check-circle');
    $color = trim($_POST['color'] ?? '#4A7C59');
    $frequencyType = in_array($_POST['frequency_type'] ?? '', ['daily', 'weekly_custom']) ? $_POST['frequency_type'] : 'daily';
    $scheduleDaysInput = $_POST['schedule_days'] ?? [];
    $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($name)) {
        $errors['name'] = 'Nama kebiasaan wajib diisi.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'Nama kebiasaan minimal 3 karakter.';
    }

    if (empty($categoryId)) {
        $errors['category_id'] = 'Pilih kategori yang valid.';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $errors['start_date'] = 'Format tanggal mulai tidak valid.';
    }

    if ($frequencyType === 'weekly_custom') {
        if (empty($scheduleDaysInput) || !is_array($scheduleDaysInput)) {
            $errors['schedule_days'] = 'Pilih minimal satu hari pelaksanaan jika memilih jadwal tertentu.';
        } else {
            $scheduleDays = array_map('intval', $scheduleDaysInput);
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO habits (user_id, category_id, name, description, icon, color, frequency_type, start_date, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $categoryId,
                $name,
                $description,
                $icon,
                $color,
                $frequencyType,
                $startDate,
                $isActive
            ]);

            $habitId = (int)$pdo->lastInsertId();

            // Insert custom schedules if applicable
            if ($frequencyType === 'weekly_custom' && !empty($scheduleDays)) {
                $stmtSched = $pdo->prepare("INSERT INTO habit_schedules (habit_id, day_of_week) VALUES (?, ?)");
                foreach ($scheduleDays as $dow) {
                    if ($dow >= 0 && $dow <= 6) {
                        $stmtSched->execute([$habitId, $dow]);
                    }
                }
            }

            $pdo->commit();

            flash_set('success', 'Kebiasaan baru "' . e($name) . '" berhasil ditambahkan!');
            header('Location: ' . BASE_URL . '/habits/index.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to create habit: " . $e->getMessage());
            $errors['general'] = 'Gagal menyimpan kebiasaan. Silakan periksa kembali input Anda.';
        }
    }
}

$pageTitle = 'Tambah Kebiasaan';
$activeMenu = 'habits';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="app-content">
        <!-- Back Breadcrumb -->
        <div class="mb-3">
            <a href="<?= BASE_URL ?>/habits/index.php" class="text-secondary small fw-semibold text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Kebiasaan Saya
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card-habit p-4 p-md-5">
                    <div class="border-bottom pb-3 mb-4">
                        <h4 class="fw-bold mb-1">Tambah Kebiasaan Baru</h4>
                        <p class="text-muted small mb-0">Tentukan target dan jadwal pelaksanaan kebiasaan yang ingin Anda rutinkan.</p>
                    </div>

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger py-2 small rounded-3"><?= e($errors['general']) ?></div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/habits/create.php" method="POST" novalidate>
                        <?= csrf_field() ?>

                        <!-- Habit Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Kebiasaan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($name) ?>" placeholder="Contoh: Olahraga kardio 20 menit" required>
                            <?php if (!empty($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi & Catatan (Opsional)</label>
                            <textarea class="form-control" id="description" name="description" rows="2" placeholder="Catat detail target, panduan, atau motivasi kebiasaan ini..."><?= e($description) ?></textarea>
                        </div>

                        <!-- Category -->
                        <div class="mb-4">
                            <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select <?= !empty($errors['category_id']) ? 'is-invalid' : '' ?>" id="category_id" name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($errors['category_id'])): ?>
                                <div class="invalid-feedback"><?= e($errors['category_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Icon & Color Picker Row -->
                        <div class="row g-3 mb-4">
                            <!-- Icon Picker -->
                            <div class="col-md-6">
                                <label class="form-label">Pilih Ikon Penanda</label>
                                <div class="d-flex flex-wrap gap-2 p-2 bg-light rounded-3 border" style="max-height: 140px; overflow-y: auto;">
                                    <?php foreach ($availableIcons as $iconClass => $iconLabel): ?>
                                        <div>
                                            <input type="radio" class="btn-check" name="icon" id="icon_<?= $iconClass ?>" value="<?= $iconClass ?>" <?= $icon === $iconClass ? 'checked' : '' ?>>
                                            <label class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center p-2 rounded-3" for="icon_<?= $iconClass ?>" title="<?= e($iconLabel) ?>" style="width: 38px; height: 38px;">
                                                <i class="bi <?= $iconClass ?> fs-6"></i>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Color Picker -->
                            <div class="col-md-6">
                                <label class="form-label">Pilih Warna Penanda</label>
                                <div class="d-flex flex-wrap gap-2 p-2 bg-light rounded-3 border" style="max-height: 140px;">
                                    <?php foreach ($availableColors as $hex => $colorLabel): ?>
                                        <div>
                                            <input type="radio" class="btn-check" name="color" id="col_<?= substr($hex, 1) ?>" value="<?= $hex ?>" <?= $color === $hex ? 'checked' : '' ?>>
                                            <label class="btn btn-sm border-0 d-flex align-items-center justify-content-center rounded-circle" for="col_<?= substr($hex, 1) ?>" title="<?= e($colorLabel) ?>" style="width: 36px; height: 36px; background-color: <?= $hex ?>; cursor: pointer;">
                                                <?php if ($color === $hex): ?>
                                                    <i class="bi bi-check text-white fw-bold"></i>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Frequency Type -->
                        <div class="mb-3">
                            <label class="form-label d-block">Target Frekuensi Pelaksanaan <span class="text-danger">*</span></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="frequency_type" id="freqDaily" value="daily" <?= $frequencyType === 'daily' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="freqDaily">Setiap Hari (Senin - Minggu)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="frequency_type" id="freqWeekly" value="weekly_custom" <?= $frequencyType === 'weekly_custom' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="freqWeekly">Hari Tertentu dalam Seminggu</label>
                            </div>
                        </div>

                        <!-- Specific Days Selector (Conditional) -->
                        <div class="mb-4 <?= $frequencyType === 'daily' ? 'd-none' : '' ?>" id="scheduleDaysContainer">
                            <label class="form-label text-muted small">Pilih hari aktif pelaksanaan:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                $daysOfWeek = [
                                    1 => 'Senin',
                                    2 => 'Selasa',
                                    3 => 'Rabu',
                                    4 => 'Kamis',
                                    5 => 'Jumat',
                                    6 => 'Sabtu',
                                    0 => 'Minggu'
                                ];
                                foreach ($daysOfWeek as $dowNum => $dowLabel):
                                    $isChecked = in_array($dowNum, $scheduleDays);
                                ?>
                                    <div class="form-check form-check-inline bg-light px-3 py-2 rounded-3 border">
                                        <input class="form-check-input" type="checkbox" name="schedule_days[]" id="dow_<?= $dowNum ?>" value="<?= $dowNum ?>" <?= $isChecked ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-semibold" for="dow_<?= $dowNum ?>"><?= $dowLabel ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!empty($errors['schedule_days'])): ?>
                                <div class="text-danger small mt-1"><?= e($errors['schedule_days']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Start Date -->
                        <div class="mb-4">
                            <label for="start_date" class="form-label">Tanggal Mulai Berlaku</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= e($startDate) ?>" required>
                            <small class="text-muted">Perhitungan streak dan evaluasi dimulai sejak tanggal ini.</small>
                        </div>

                        <!-- Active Status Switch -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= $isActive ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="is_active">Aktifkan kebiasaan ini sekarang</label>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <a href="<?= BASE_URL ?>/habits/index.php" class="btn btn-light border px-3">Batal</a>
                            <button type="submit" class="btn btn-primary-habit px-4">
                                <i class="bi bi-check-lg me-1"></i> Simpan Kebiasaan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
