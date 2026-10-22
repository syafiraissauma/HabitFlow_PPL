<?php
/**
 * HabitFlow - Profil & Pengaturan Pengguna
 */
require_once __DIR__ . '/../config/app.php';

require_login();

$pdo = get_db_connection();
$user = current_user($pdo);
$userId = (int)$user['id'];

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();
    $action = $_POST['action'] ?? '';

    // 1. Update Profile (Name & Bio)
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $bio = trim($_POST['bio'] ?? '');

        if (empty($name)) {
            $errors['name'] = 'Nama lengkap wajib diisi.';
        } elseif (mb_strlen($name) < 3) {
            $errors['name'] = 'Nama minimal 3 karakter.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, bio = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $bio, $userId]);
            flash_set('success', 'Profil Anda berhasil diperbarui!');
            header('Location: ' . BASE_URL . '/profile/index.php');
            exit;
        }
    }

    // 2. Change Password
    if ($action === 'change_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Fetch current password hash
        $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmtPass->execute([$userId]);
        $currentHash = $stmtPass->fetchColumn();

        if (empty($oldPassword)) {
            $errors['old_password'] = 'Kata sandi saat ini wajib diisi.';
        } elseif (!password_verify($oldPassword, $currentHash)) {
            $errors['old_password'] = 'Kata sandi saat ini tidak cocok.';
        }

        if (empty($newPassword)) {
            $errors['new_password'] = 'Kata sandi baru wajib diisi.';
        } elseif (strlen($newPassword) < 6) {
            $errors['new_password'] = 'Kata sandi baru minimal 6 karakter.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Konfirmasi kata sandi baru tidak sesuai.';
        }

        if (empty($errors)) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$newHash, $userId]);

            flash_set('success', 'Kata sandi Anda berhasil diubah! Silakan gunakan kata sandi baru saat masuk berikutnya.');
            header('Location: ' . BASE_URL . '/profile/index.php');
            exit;
        }
    }
}

// Refresh user info after potential updates
$user = current_user($pdo);

// User Statistics Overview
$stmtHabitsCount = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
$stmtHabitsCount->execute([$userId]);
$totalHabits = (int)$stmtHabitsCount->fetchColumn();

$stmtLogsCount = $pdo->prepare("
    SELECT COUNT(hl.id) 
    FROM habit_logs hl 
    JOIN habits h ON hl.habit_id = h.id 
    WHERE h.user_id = ? AND hl.is_completed = 1
");
$stmtLogsCount->execute([$userId]);
$totalCompletions = (int)$stmtLogsCount->fetchColumn();

$pageTitle = 'Profil Saya';
$activeMenu = 'profile';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<main class="app-main">
    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <div class="app-content">
        <?= flash_render() ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
            <div>
                <h4 class="fw-bold mb-1">Profil & Pengaturan Akun</h4>
                <p class="text-muted small mb-0">Kelola identitas akun dan keamanan kata sandi Anda.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                    <i class="bi bi-box-arrow-right me-1"></i> Keluar Akun
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left: Profile Summary Card -->
            <div class="col-lg-4">
                <div class="card-habit p-4 text-center">
                    <div class="user-avatar-circle mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <h5 class="fw-bold mb-1 text-dark"><?= e($user['name']) ?></h5>
                    <p class="text-muted small mb-3"><?= e($user['email']) ?></p>

                    <div class="p-3 bg-light rounded-3 text-start mb-3">
                        <small class="text-muted fw-semibold d-block mb-1">Bio / Catatan Pribadi:</small>
                        <p class="mb-0 text-dark small fst-italic">"<?= e($user['bio'] ?? 'Belum ada bio.') ?>"</p>
                    </div>

                    <div class="border-top pt-3">
                        <div class="row text-center g-2">
                            <div class="col-6">
                                <div class="fw-bold text-dark fs-5"><?= $totalHabits ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">Total Kebiasaan</div>
                            </div>
                            <div class="col-6 border-start">
                                <div class="fw-bold text-success fs-5"><?= $totalCompletions ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">Aktivitas Tuntas</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-top text-muted small">
                        <i class="bi bi-calendar-event me-1"></i>
                        Bergabung sejak: <?= format_date_indo($user['created_at'], false) ?>
                    </div>
                </div>
            </div>

            <!-- Right: Forms -->
            <div class="col-lg-8">
                <!-- Form 1: Edit Profile -->
                <div class="card-habit p-4 mb-4">
                    <h5 class="fw-bold mb-3">Informasi Akun</h5>
                    <form action="<?= BASE_URL ?>/profile/index.php" method="POST" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <!-- Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($user['name']) ?>" required>
                            <?php if (!empty($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Email (Read-only as per specification) -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Alamat Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control bg-light" id="email" value="<?= e($user['email']) ?>" readonly>
                            </div>
                            <small class="text-muted">Alamat email terdaftar sebagai identitas akun dan tidak dapat diubah secara langsung demi keamanan.</small>
                        </div>

                        <!-- Bio -->
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio / Kalimat Motivasi</label>
                            <textarea class="form-control" id="bio" name="bio" rows="2" placeholder="Tuliskan tujuan produktivitas Anda..."><?= e($user['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary-habit">
                                <i class="bi bi-check-lg me-1"></i> Simpan Profil
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Form 2: Change Password -->
                <div class="card-habit p-4">
                    <h5 class="fw-bold mb-1">Ganti Kata Sandi</h5>
                    <p class="text-muted small mb-3">Pastikan kata sandi baru Anda kuat dan sulit ditebak.</p>

                    <form action="<?= BASE_URL ?>/profile/index.php" method="POST" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <!-- Old Password -->
                        <div class="mb-3">
                            <label for="old_password" class="form-label">Kata Sandi Saat Ini</label>
                            <div class="input-group">
                                <input type="password" class="form-control <?= !empty($errors['old_password']) ? 'is-invalid' : '' ?>" id="old_password" name="old_password" placeholder="Masukkan kata sandi lama" required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="old_password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (!empty($errors['old_password'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['old_password']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Kata Sandi Baru</label>
                            <div class="input-group">
                                <input type="password" class="form-control <?= !empty($errors['new_password']) ? 'is-invalid' : '' ?>" id="new_password" name="new_password" placeholder="Minimal 6 karakter" required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="new_password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (!empty($errors['new_password'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['new_password']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Confirm New Password -->
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Kata Sandi Baru</label>
                            <div class="input-group">
                                <input type="password" class="form-control <?= !empty($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" placeholder="Ulangi kata sandi baru" required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="confirm_password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if (!empty($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary-habit">
                                <i class="bi bi-shield-lock me-1"></i> Perbarui Kata Sandi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
