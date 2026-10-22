<?php
/**
 * HabitFlow - Login Page & Handler
 */
require_once __DIR__ . '/../config/app.php';

require_guest();

$pdo = get_db_connection();
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email)) {
        $errors['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }

    if (empty($password)) {
        $errors['password'] = 'Kata sandi wajib diisi.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Authentication Success
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            flash_set('success', 'Selamat datang kembali, ' . e($user['name']) . '!');
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $errors['auth'] = 'Email atau kata sandi yang Anda masukkan salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= APP_VERSION ?>">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Logo & Title -->
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.5rem;">
                <i class="bi bi-check2-circle"></i>
            </div>
            <h3 class="fw-bold mb-1" style="color: var(--color-primary-dark);"><?= APP_NAME ?></h3>
            <p class="text-muted small"><?= APP_TAGLINE ?></p>
        </div>

        <?= flash_render() ?>

        <?php if (!empty($errors['auth'])): ?>
            <div class="alert alert-danger py-2 small d-flex align-items-center rounded-3">
                <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                <div><?= e($errors['auth']) ?></div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="<?= BASE_URL ?>/auth/login.php" method="POST" novalidate>
            <?= csrf_field() ?>

            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label">Alamat Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control border-start-0 <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e($email) ?>" placeholder="nama@email.com" required autocomplete="email">
                    <?php if (!empty($errors['email'])): ?>
                        <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label mb-0">Kata Sandi</label>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0 <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                    <button class="btn btn-outline-secondary border-start-0 btn-toggle-password" type="button" data-target="password" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary-habit w-100 py-2 mt-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
            </button>
        </form>

        <!-- Demo Account Box -->
        <div class="mt-4 p-3 rounded-3" style="background-color: var(--color-primary-light); border: 1px dashed var(--color-primary);">
            <div class="d-flex align-items-center mb-1">
                <i class="bi bi-info-circle-fill text-success me-2"></i>
                <strong class="small text-success">Akun Demo Praktikum:</strong>
            </div>
            <div class="small text-secondary">
                Email: <code>syafira@habitflow.local</code><br>
                Password: <code>password123</code>
            </div>
        </div>

        <!-- Register Link -->
        <div class="text-center mt-4">
            <span class="text-muted small">Belum memiliki akun?</span>
            <a href="<?= BASE_URL ?>/auth/register.php" class="fw-bold small ms-1">Daftar Akun Baru</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
