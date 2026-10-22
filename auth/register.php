<?php
/**
 * HabitFlow - Registration Page & Handler
 */
require_once __DIR__ . '/../config/app.php';

require_guest();

$pdo = get_db_connection();
$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_protect();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($name)) {
        $errors['name'] = 'Nama lengkap wajib diisi.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'Nama minimal 3 karakter.';
    }

    if (empty($email)) {
        $errors['email'] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    } else {
        // Check uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email ini sudah terdaftar. Silakan gunakan email lain atau masuk.';
        }
    }

    if (empty($password)) {
        $errors['password'] = 'Kata sandi wajib diisi.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Kata sandi minimal 6 karakter.';
    }

    if ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Konfirmasi kata sandi tidak cocok.';
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $stmtInsert = $pdo->prepare("
            INSERT INTO users (name, email, password, bio, created_at) 
            VALUES (?, ?, ?, 'Membangun kebiasaan baik setiap hari.', NOW())
        ");
        $stmtInsert->execute([$name, $email, $hashedPassword]);
        $newUserId = (int)$pdo->lastInsertId();

        // Auto create 2 starter habits for the new user so they have something right away
        $starterHabits = [
            [
                'cat_id' => 1,
                'name' => 'Minum air putih 8 gelas',
                'desc' => 'Menjaga hidrasi tubuh sepanjang hari',
                'icon' => 'bi-droplet-fill',
                'color' => '#2E7D32',
                'freq' => 'daily'
            ],
            [
                'cat_id' => 6,
                'name' => 'Membaca buku 15 menit',
                'desc' => 'Meningkatkan wawasan dan fokus berpikir',
                'icon' => 'bi-book-half',
                'color' => '#4A7C59',
                'freq' => 'daily'
            ]
        ];

        $stmtHabit = $pdo->prepare("
            INSERT INTO habits (user_id, category_id, name, description, icon, color, frequency_type, start_date, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 1)
        ");
        foreach ($starterHabits as $sh) {
            $stmtHabit->execute([
                $newUserId,
                $sh['cat_id'],
                $sh['name'],
                $sh['desc'],
                $sh['icon'],
                $sh['color'],
                $sh['freq']
            ]);
        }

        // Auto login
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;

        flash_set('success', 'Pendaftaran berhasil! Selamat datang di HabitFlow, ' . e($name) . '. Kebiasaan awal telah disiapkan untuk Anda.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru - <?= APP_NAME ?></title>
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
            <p class="text-muted small">Buat akun untuk memulai pemantauan kebiasaan Anda</p>
        </div>

        <?= flash_render() ?>

        <!-- Form -->
        <form action="<?= BASE_URL ?>/auth/register.php" method="POST" novalidate>
            <?= csrf_field() ?>

            <!-- Name -->
            <div class="mb-3">
                <label for="name" class="form-label">Nama Lengkap</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0 <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($name) ?>" placeholder="Contoh: Syafira Putri" required autocomplete="name">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

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
                <label for="password" class="form-label">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0 <?= !empty($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Minimal 6 karakter" required autocomplete="new-password">
                    <button class="btn btn-outline-secondary border-start-0 btn-toggle-password" type="button" data-target="password" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-3">
                <label for="password_confirm" class="form-label">Konfirmasi Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-shield-check"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0 <?= !empty($errors['password_confirm']) ? 'is-invalid' : '' ?>" id="password_confirm" name="password_confirm" placeholder="Ulangi kata sandi" required autocomplete="new-password">
                    <button class="btn btn-outline-secondary border-start-0 btn-toggle-password" type="button" data-target="password_confirm" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                    <?php if (!empty($errors['password_confirm'])): ?>
                        <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary-habit w-100 py-2 mt-2">
                <i class="bi bi-person-plus me-1"></i> Buat Akun Baru
            </button>
        </form>

        <!-- Login Link -->
        <div class="text-center mt-4">
            <span class="text-muted small">Sudah memiliki akun?</span>
            <a href="<?= BASE_URL ?>/auth/login.php" class="fw-bold small ms-1">Masuk Sekarang</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
