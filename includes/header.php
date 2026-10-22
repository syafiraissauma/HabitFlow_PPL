<?php
/**
 * HabitFlow - Layout Header
 */
if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/app.php';
}

$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME . ' | ' . APP_TAGLINE;
$activeMenu = $activeMenu ?? '';
$user = current_user(get_db_connection());
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="description" content="HabitFlow - Sistem Informasi Pemantauan dan Evaluasi Kebiasaan Harian Berbasis Website. Bangun kebiasaan positif setiap hari.">
    
    <title><?= e($pageTitle) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= APP_VERSION ?>">

    <!-- Base URL for Frontend JS -->
    <script>
        window.HABITFLOW_BASE_URL = "<?= BASE_URL ?>";
    </script>
</head>
<body>
<div class="app-wrapper">
