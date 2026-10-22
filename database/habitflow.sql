-- HabitFlow Database Schema
-- Sistem Informasi Pemantauan dan Evaluasi Kebiasaan Harian Berbasis Website

CREATE DATABASE IF NOT EXISTS `habitflow_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `habitflow_db`;

-- Drop existing tables in reverse dependency order
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `habit_logs`;
DROP TABLE IF EXISTS `habit_schedules`;
DROP TABLE IF EXISTS `habits`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Table: users
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `bio` VARCHAR(255) DEFAULT 'Membangun kebiasaan baik setiap hari.',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: categories (Master Category data)
CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'bi-bookmark',
    `color` VARCHAR(20) NOT NULL DEFAULT '#4A7C59',
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: habits
CREATE TABLE `habits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'bi-check-circle',
    `color` VARCHAR(20) NOT NULL DEFAULT '#4A7C59',
    `frequency_type` ENUM('daily', 'weekly_custom') NOT NULL DEFAULT 'daily',
    `target_time` TIME NULL,
    `start_date` DATE NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_habits_user` (`user_id`),
    INDEX `idx_habits_category` (`category_id`),
    INDEX `idx_habits_active` (`is_active`),
    CONSTRAINT `fk_habits_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_habits_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table: habit_schedules (For specific days of week: 0 = Minggu, 1 = Senin, ... 6 = Sabtu)
CREATE TABLE `habit_schedules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `habit_id` INT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=Minggu, 1=Senin, 2=Selasa, 3=Rabu, 4=Kamis, 5=Jumat, 6=Sabtu',
    INDEX `idx_schedules_habit` (`habit_id`),
    INDEX `idx_schedules_day` (`day_of_week`),
    UNIQUE KEY `unique_habit_day` (`habit_id`, `day_of_week`),
    CONSTRAINT `fk_schedules_habit` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table: habit_logs (Daily completion logs)
CREATE TABLE `habit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `habit_id` INT UNSIGNED NOT NULL,
    `activity_date` DATE NOT NULL,
    `is_completed` TINYINT(1) NOT NULL DEFAULT 1,
    `completed_at` DATETIME NULL,
    `note` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_habit_date` (`habit_id`, `activity_date`),
    INDEX `idx_logs_habit` (`habit_id`),
    INDEX `idx_logs_date` (`activity_date`),
    INDEX `idx_logs_completed` (`is_completed`),
    CONSTRAINT `fk_logs_habit` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Categories
INSERT INTO `categories` (`id`, `name`, `icon`, `color`, `description`) VALUES
(1, 'Kesehatan', 'bi-heart-pulse-fill', '#2E7D32', 'Aktivitas fisik, nutrisi, hidrasi, dan kesehatan umum'),
(2, 'Pendidikan', 'bi-mortarboard-fill', '#1976D2', 'Belajar materi kuliah, membaca jurnal, dan mengerjakan tugas'),
(3, 'Produktivitas', 'bi-lightning-charge-fill', '#E65100', 'Manajemen waktu, fokus kerja, dan penyelesaian proyek'),
(4, 'Spiritual', 'bi-moon-stars-fill', '#5E35B1', 'Ibadah harian, doa, refleksi diri, dan ketenangan jiwa'),
(5, 'Keuangan', 'bi-wallet2', '#00796B', 'Mencatat pengeluaran harian, menabung, dan evaluasi anggaran'),
(6, 'Pengembangan Diri', 'bi-flower1', '#4A7C59', 'Membaca buku, melatih keterampilan baru, dan jurnal harian'),
(7, 'Lainnya', 'bi-star-fill', '#546E7A', 'Kebiasaan personal dan hobi lainnya');

-- Seed Sample Demo User (Password: password123)
-- Hash generated using password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `bio`) VALUES
(1, 'Syafira Putri', 'syafira@habitflow.local', '$2y$10$Rjqf/C988pLfCJ5xbX1/nOu9Y6NHr2eh9MWIg2Rg/0tSzOsn7LBki', 'Mahasiswi yang berkomitmen membangun kebiasaan produktif setiap hari.');

-- Seed Sample Habits for Demo User
INSERT INTO `habits` (`id`, `user_id`, `category_id`, `name`, `description`, `icon`, `color`, `frequency_type`, `start_date`, `is_active`) VALUES
(1, 1, 1, 'Minum air putih 2 liter', 'Menjaga hidrasi tubuh dengan minum minimal 8 gelas air mineral per hari', 'bi-droplet-fill', '#2E7D32', 'daily', '2026-10-01', 1),
(2, 1, 6, 'Membaca buku non-fiksi 15 menit', 'Membaca minimal 1 bab buku pengembangan diri atau materi ilmiah', 'bi-book-half', '#4A7C59', 'daily', '2026-10-01', 1),
(3, 1, 2, 'Belajar & review materi kuliah 1 jam', 'Mengerjakan tugas atau mereview catatan perkuliahan minggu ini', 'bi-laptop', '#1976D2', 'daily', '2026-10-01', 1),
(4, 1, 1, 'Olahraga ringan & stretching 20 menit', 'Peregangan tubuh, jogging santai atau senam kardio ringan', 'bi-activity', '#2E7D32', 'weekly_custom', '2026-10-01', 1),
(5, 1, 5, 'Catat pengeluaran & tabungan harian', 'Mencatat mutasi uang keluar dan menyisihkan uang ke tabungan', 'bi-cash-coin', '#00796B', 'daily', '2026-10-01', 1),
(6, 1, 4, 'Refleksi dan ibadah malam', 'Merenungkan hari yang telah lewat dan berdoa sebelum beristirahat', 'bi-moon-stars-fill', '#5E35B1', 'daily', '2026-10-01', 1);

-- Schedule for Habit #4 (Olahraga: Senin, Rabu, Jumat, Sabtu -> 1, 3, 5, 6)
INSERT INTO `habit_schedules` (`habit_id`, `day_of_week`) VALUES
(4, 1),
(4, 3),
(4, 5),
(4, 6);

-- Seed Sample Logs for the past 7 days to give instant realistic visualization on first run
-- (Current date reference: 2026-10-14)
INSERT INTO `habit_logs` (`habit_id`, `activity_date`, `is_completed`, `completed_at`) VALUES
-- 2026-10-08
(1, '2026-10-08', 1, '2026-10-08 19:30:00'),
(2, '2026-10-08', 1, '2026-10-08 21:00:00'),
(3, '2026-10-08', 1, '2026-10-08 16:45:00'),
(5, '2026-10-08', 1, '2026-10-08 20:15:00'),
-- 2026-10-09 (Jumat - Habit 4 Olahraga scheduled)
(1, '2026-10-09', 1, '2026-10-09 18:20:00'),
(2, '2026-10-09', 1, '2026-10-09 22:10:00'),
(3, '2026-10-09', 1, '2026-10-09 15:30:00'),
(4, '2026-10-09', 1, '2026-10-09 07:15:00'),
(5, '2026-10-09', 1, '2026-10-09 21:00:00'),
(6, '2026-10-09', 1, '2026-10-09 23:00:00'),
-- 2026-10-10 (Sabtu)
(1, '2026-10-10', 1, '2026-10-10 19:00:00'),
(2, '2026-10-10', 1, '2026-10-10 20:30:00'),
(4, '2026-10-10', 1, '2026-10-10 08:00:00'),
(5, '2026-10-10', 1, '2026-10-10 21:10:00'),
-- 2026-10-11 (Minggu)
(1, '2026-10-11', 1, '2026-10-11 18:45:00'),
(2, '2026-10-11', 1, '2026-10-11 21:40:00'),
(5, '2026-10-11', 1, '2026-10-11 20:00:00'),
(6, '2026-10-11', 1, '2026-10-11 22:30:00'),
-- 2026-10-12 (Senin)
(1, '2026-10-12', 1, '2026-10-12 17:30:00'),
(2, '2026-10-12', 1, '2026-10-12 21:15:00'),
(3, '2026-10-12', 1, '2026-10-12 14:00:00'),
(4, '2026-10-12', 1, '2026-10-12 06:45:00'),
(5, '2026-10-12', 1, '2026-10-12 20:30:00'),
(6, '2026-10-12', 1, '2026-10-12 22:50:00'),
-- 2026-10-13 (Selasa)
(1, '2026-10-13', 1, '2026-10-13 18:10:00'),
(2, '2026-10-13', 1, '2026-10-13 20:50:00'),
(3, '2026-10-13', 1, '2026-10-13 16:00:00'),
(5, '2026-10-13', 1, '2026-10-13 21:15:00'),
(6, '2026-10-13', 1, '2026-10-13 23:10:00'),
-- 2026-10-14 (Hari ini - Rabu: Habit 1 & 4 selesai)
(1, '2026-10-14', 1, '2026-10-14 09:15:00'),
(4, '2026-10-14', 1, '2026-10-14 07:00:00');
