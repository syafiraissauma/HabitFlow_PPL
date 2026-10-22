<?php
/**
 * HabitFlow - Sidebar Navigation Component
 */
$activeMenu = $activeMenu ?? 'dashboard';
?>
<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-none" style="z-index: 1025;"></div>

<!-- Sidebar -->
<aside class="app-sidebar" id="appSidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="<?= BASE_URL ?>/dashboard.php" class="d-flex align-items-center text-decoration-none">
            <div class="brand-icon">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div class="d-flex align-items-center">
                <span class="brand-text"><?= APP_NAME ?></span>
                <span class="brand-badge">PPL</span>
            </div>
        </a>
    </div>

    <!-- Navigation List -->
    <div class="sidebar-nav">
        <div class="nav-section-title">Menu Utama</div>
        <ul class="nav flex-column mb-3">
            <li class="nav-item">
                <a class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard.php">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeMenu === 'habits' ? 'active' : '' ?>" href="<?= BASE_URL ?>/habits/index.php">
                    <i class="bi bi-card-checklist"></i>
                    <span>Kebiasaan Saya</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeMenu === 'history' ? 'active' : '' ?>" href="<?= BASE_URL ?>/history/index.php">
                    <i class="bi bi-calendar3"></i>
                    <span>Riwayat & Kalender</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeMenu === 'reports' ? 'active' : '' ?>" href="<?= BASE_URL ?>/reports/index.php">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Laporan & Evaluasi</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-title">Akun & Preferensi</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= $activeMenu === 'profile' ? 'active' : '' ?>" href="<?= BASE_URL ?>/profile/index.php">
                    <i class="bi bi-person-gear"></i>
                    <span>Profil Saya</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-danger" href="<?= BASE_URL ?>/auth/logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari akun?');">
                    <i class="bi bi-box-arrow-right text-danger"></i>
                    <span>Keluar</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- User Quick Card at Bottom -->
    <?php if ($user): ?>
    <div class="sidebar-footer">
        <div class="user-quick-profile">
            <div class="user-avatar-circle me-3">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <div class="fw-bold text-white text-truncate" style="font-size: 0.875rem;"><?= e($user['name']) ?></div>
                <div class="text-white-50 text-truncate" style="font-size: 0.75rem;"><?= e($user['email']) ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</aside>
