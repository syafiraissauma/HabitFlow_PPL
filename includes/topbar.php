<?php
/**
 * HabitFlow - Topbar Component
 */
$greeting = get_greeting();
$todayIndo = format_date_indo(date('Y-m-d'));
?>
<header class="app-topbar">
    <div class="d-flex align-items-center">
        <!-- Mobile Toggle Button -->
        <button class="btn btn-outline-secondary d-lg-none me-3" id="sidebarToggleBtn" type="button" aria-label="Toggle Navigation">
            <i class="bi bi-list fs-5"></i>
        </button>

        <!-- Greeting & Date -->
        <div>
            <div class="greeting-text">
                <?= $greeting ?>, <span class="text-success"><?= e(explode(' ', $user['name'] ?? 'Pengguna')[0]) ?>!</span>
            </div>
            <div class="date-text">
                <i class="bi bi-calendar4-week me-1"></i><?= $todayIndo ?>
            </div>
        </div>
    </div>

    <!-- Actions & Profile Dropdown -->
    <div class="d-flex align-items-center gap-3">
        <!-- New Habit Quick Action -->
        <a href="<?= BASE_URL ?>/habits/create.php" class="btn btn-primary-habit d-none d-sm-inline-flex align-items-center">
            <i class="bi bi-plus-lg me-1"></i>
            <span>Tambah Kebiasaan</span>
        </a>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center border rounded-pill py-1 ps-2 pe-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar-circle me-2" style="width: 32px; height: 32px; font-size: 0.85rem;">
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                </div>
                <span class="d-none d-md-inline fw-semibold text-dark me-2" style="font-size: 0.875rem;">
                    <?= e($user['name'] ?? 'Akun') ?>
                </span>
                <i class="bi bi-chevron-down text-muted" style="font-size: 0.75rem;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2" style="min-width: 200px;">
                <li class="px-3 py-2 border-bottom">
                    <p class="mb-0 fw-bold text-dark text-truncate" style="font-size: 0.875rem;"><?= e($user['name'] ?? '') ?></p>
                    <small class="text-muted text-truncate d-block" style="font-size: 0.75rem;"><?= e($user['email'] ?? '') ?></small>
                </li>
                <li>
                    <a class="dropdown-menu-item dropdown-item py-2 d-flex align-items-center" href="<?= BASE_URL ?>/profile/index.php">
                        <i class="bi bi-person me-2 text-secondary"></i> Profil Saya
                    </a>
                </li>
                <li>
                    <a class="dropdown-menu-item dropdown-item py-2 d-flex align-items-center" href="<?= BASE_URL ?>/habits/create.php">
                        <i class="bi bi-plus-circle me-2 text-secondary"></i> Buat Kebiasaan
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a class="dropdown-menu-item dropdown-item py-2 text-danger d-flex align-items-center" href="<?= BASE_URL ?>/auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');">
                        <i class="bi bi-box-arrow-right me-2 text-danger"></i> Keluar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
