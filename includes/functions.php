<?php
/**
 * HabitFlow - Helper Functions
 */

/**
 * Escape string for safe HTML output (Anti-XSS)
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message setter
 */
function flash_set($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Flash message getter
 */
function flash_get() {
    if (!empty($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Render Flash Alert HTML
 */
function flash_render() {
    $flash = flash_get();
    if (!$flash) return '';

    $type = htmlspecialchars($flash['type']);
    $message = htmlspecialchars($flash['message']);

    $icon = match ($type) {
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        default   => 'bi-info-circle-fill',
    };

    return "
    <div class='alert alert-{$type} alert-dismissible fade show d-flex align-items-center shadow-sm rounded-3' role='alert'>
        <i class='bi {$icon} fs-5 me-2 flex-shrink-0'></i>
        <div class='flex-grow-1'>{$message}</div>
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Tutup'></button>
    </div>
    ";
}

/**
 * Format date into Indonesian locale
 * Example: Rabu, 14 Oktober 2026
 */
function format_date_indo($dateStr, $withDay = true) {
    if (empty($dateStr)) return '-';
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return '-';

    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $dayName = $days[(int)date('w', $timestamp)];
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);

    if ($withDay) {
        return "{$dayName}, {$day} {$month} {$year}";
    }
    return "{$day} {$month} {$year}";
}

/**
 * Format time (HH:MM WIB)
 */
function format_time_indo($timeStr) {
    if (empty($timeStr)) return '';
    $timestamp = strtotime($timeStr);
    if (!$timestamp) return '';
    return date('H:i', $timestamp) . ' WIB';
}

/**
 * Greeting based on current time
 */
function get_greeting() {
    $hour = (int)date('G');
    if ($hour >= 4 && $hour < 11) {
        return 'Selamat pagi';
    } elseif ($hour >= 11 && $hour < 15) {
        return 'Selamat siang';
    } elseif ($hour >= 15 && $hour < 18) {
        return 'Selamat sore';
    } else {
        return 'Selamat malam';
    }
}

/**
 * Motivational quote based on completion rate
 */
function get_motivational_quote($rate) {
    if ($rate <= 0) {
        return 'Setiap perjalanan besar dimulai dari satu langkah kecil. Ayo tuntaskan kebiasaan pertamamu hari ini!';
    } elseif ($rate < 50) {
        return 'Awal yang baik! Jaga fokus dan teruskan langkah untuk menyelesaikan kebiasaan berikutnya.';
    } elseif ($rate < 100) {
        return 'Luar biasa! Lebih dari separuh kebiasaan hari ini sudah tuntas. Tinggal sedikit lagi!';
    } else {
        return 'Hebat sekali! Semua kebiasaan hari ini berhasil kamu tuntaskan dengan sempurna. Pertahankan konsistensi ini!';
    }
}

/**
 * Day name in Indonesian
 */
function get_day_name_indo($dayOfWeek) {
    $days = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu'
    ];
    return $days[$dayOfWeek] ?? '';
}

/**
 * Short day name in Indonesian
 */
function get_day_short_indo($dayOfWeek) {
    $days = [
        0 => 'Min',
        1 => 'Sen',
        2 => 'Sel',
        3 => 'Rab',
        4 => 'Kam',
        5 => 'Jum',
        6 => 'Sab'
    ];
    return $days[$dayOfWeek] ?? '';
}

/**
 * Calculate accurate streak (Current Streak and Longest Streak)
 * strictly respecting habit frequency and custom weekly schedules.
 */
function calculate_habit_streak($pdo, $habit_id, $reference_date = null) {
    if ($reference_date === null) {
        $reference_date = date('Y-m-d');
    }

    // Fetch habit info
    $stmt = $pdo->prepare("SELECT frequency_type, start_date, is_active FROM habits WHERE id = ?");
    $stmt->execute([$habit_id]);
    $habit = $stmt->fetch();

    if (!$habit) {
        return ['current_streak' => 0, 'longest_streak' => 0, 'total_scheduled' => 0, 'total_completed' => 0];
    }

    // Determine scheduled days of week (0 = Minggu ... 6 = Sabtu)
    $scheduledDays = [];
    if ($habit['frequency_type'] === 'daily') {
        $scheduledDays = [0, 1, 2, 3, 4, 5, 6];
    } else {
        $stmtSched = $pdo->prepare("SELECT day_of_week FROM habit_schedules WHERE habit_id = ?");
        $stmtSched->execute([$habit_id]);
        $scheduledDays = $stmtSched->fetchAll(PDO::FETCH_COLUMN);
        if (empty($scheduledDays)) {
            $scheduledDays = [0, 1, 2, 3, 4, 5, 6]; // fallback if not configured
        }
    }
    $scheduledDaysMap = array_flip($scheduledDays);

    // Fetch all completed dates on or before reference_date
    $stmtLogs = $pdo->prepare("
        SELECT activity_date 
        FROM habit_logs 
        WHERE habit_id = ? AND is_completed = 1 AND activity_date <= ?
        ORDER BY activity_date ASC
    ");
    $stmtLogs->execute([$habit_id, $reference_date]);
    $completedList = $stmtLogs->fetchAll(PDO::FETCH_COLUMN);
    $completedMap = array_flip($completedList);

    // Generate all scheduled dates from start_date up to reference_date
    $startDateStr = $habit['start_date'];
    if (strtotime($startDateStr) > strtotime($reference_date)) {
        return ['current_streak' => 0, 'longest_streak' => 0, 'total_scheduled' => 0, 'total_completed' => count($completedList)];
    }

    $scheduledDates = [];
    $currentTs = strtotime($startDateStr);
    $endTs = strtotime($reference_date);

    while ($currentTs <= $endTs) {
        $w = (int)date('w', $currentTs);
        if (isset($scheduledDaysMap[$w])) {
            $scheduledDates[] = date('Y-m-d', $currentTs);
        }
        $currentTs = strtotime('+1 day', $currentTs);
    }

    $totalScheduled = count($scheduledDates);
    if ($totalScheduled === 0) {
        return ['current_streak' => 0, 'longest_streak' => 0, 'total_scheduled' => 0, 'total_completed' => 0];
    }

    // 1. Calculate Longest Streak by traversing chronologically
    $longestStreak = 0;
    $runningStreak = 0;

    foreach ($scheduledDates as $d) {
        if (isset($completedMap[$d])) {
            $runningStreak++;
            if ($runningStreak > $longestStreak) {
                $longestStreak = $runningStreak;
            }
        } else {
            // If date is before reference_date, missing it broke the streak
            if ($d < $reference_date) {
                $runningStreak = 0;
            }
        }
    }

    // 2. Calculate Current Streak by traversing backwards from reference_date
    $currentStreak = 0;
    $reversed = array_reverse($scheduledDates);
    $isFirst = true;

    foreach ($reversed as $d) {
        $isCompleted = isset($completedMap[$d]);

        if ($isFirst) {
            $isFirst = false;
            if ($d === $reference_date) {
                if ($isCompleted) {
                    $currentStreak++;
                } else {
                    // Today is scheduled but not completed yet.
                    // The streak from the previous scheduled day is still active and waiting!
                    continue;
                }
            } else {
                // Today wasn't scheduled, most recent scheduled was $d
                if ($isCompleted) {
                    $currentStreak++;
                } else {
                    // Missed the most recent scheduled date
                    break;
                }
            }
        } else {
            if ($isCompleted) {
                $currentStreak++;
            } else {
                // Encountered a missed scheduled day, streak ends
                break;
            }
        }
    }

    return [
        'current_streak'  => $currentStreak,
        'longest_streak'  => $longestStreak,
        'total_scheduled' => $totalScheduled,
        'total_completed' => count($completedList)
    ];
}

/**
 * Get comprehensive stats for user dashboard
 */
function get_user_dashboard_stats($pdo, $user_id, $target_date = null) {
    if ($target_date === null) {
        $target_date = date('Y-m-d');
    }

    $targetDayOfWeek = (int)date('w', strtotime($target_date));

    // 1. Fetch all active habits for the user
    $stmt = $pdo->prepare("
        SELECT h.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color
        FROM habits h
        JOIN categories c ON h.category_id = c.id
        WHERE h.user_id = ? AND h.is_active = 1
        ORDER BY h.id ASC
    ");
    $stmt->execute([$user_id]);
    $activeHabits = $stmt->fetchAll();

    // Total active habits
    $totalHabitsCount = count($activeHabits);

    // 2. Filter habits that are scheduled for target_date
    $scheduledHabits = [];
    $completedCount = 0;
    $bestOverallStreak = 0;

    foreach ($activeHabits as $habit) {
        // Calculate streak for each habit to find best overall
        $streakInfo = calculate_habit_streak($pdo, $habit['id'], $target_date);
        if ($streakInfo['longest_streak'] > $bestOverallStreak) {
            $bestOverallStreak = $streakInfo['longest_streak'];
        }

        // Check if start_date <= target_date
        if (strtotime($habit['start_date']) > strtotime($target_date)) {
            continue;
        }

        $isScheduled = false;
        if ($habit['frequency_type'] === 'daily') {
            $isScheduled = true;
        } else {
            $stmtCheck = $pdo->prepare("SELECT 1 FROM habit_schedules WHERE habit_id = ? AND day_of_week = ?");
            $stmtCheck->execute([$habit['id'], $targetDayOfWeek]);
            if ($stmtCheck->fetchColumn()) {
                $isScheduled = true;
            }
        }

        if ($isScheduled) {
            // Check completion status on target_date
            $stmtLog = $pdo->prepare("SELECT is_completed, completed_at FROM habit_logs WHERE habit_id = ? AND activity_date = ?");
            $stmtLog->execute([$habit['id'], $target_date]);
            $log = $stmtLog->fetch();

            $habit['is_completed'] = ($log && $log['is_completed'] == 1) ? 1 : 0;
            $habit['completed_at'] = $log['completed_at'] ?? null;
            $habit['current_streak'] = $streakInfo['current_streak'];
            $habit['longest_streak'] = $streakInfo['longest_streak'];

            if ($habit['is_completed']) {
                $completedCount++;
            }

            $scheduledHabits[] = $habit;
        }
    }

    $scheduledCount = count($scheduledHabits);
    $pendingCount = max(0, $scheduledCount - $completedCount);
    $completionRate = ($scheduledCount > 0) ? round(($completedCount / $scheduledCount) * 100) : 0;

    // 3. Past 7 days completion trend for Chart.js
    $trendLabels = [];
    $trendCompleted = [];
    $trendTotal = [];
    $trendPercentages = [];

    for ($i = 6; $i >= 0; $i--) {
        $pastDate = date('Y-m-d', strtotime("-{$i} days", strtotime($target_date)));
        $pastDow = (int)date('w', strtotime($pastDate));
        $dayLabel = get_day_short_indo($pastDow) . ' (' . date('d/m', strtotime($pastDate)) . ')';
        $trendLabels[] = $dayLabel;

        // Count how many habits were scheduled on pastDate
        $pastScheduled = 0;
        foreach ($activeHabits as $h) {
            if (strtotime($h['start_date']) > strtotime($pastDate)) continue;
            if ($h['frequency_type'] === 'daily') {
                $pastScheduled++;
            } else {
                $stmtCheck = $pdo->prepare("SELECT 1 FROM habit_schedules WHERE habit_id = ? AND day_of_week = ?");
                $stmtCheck->execute([$h['id'], $pastDow]);
                if ($stmtCheck->fetchColumn()) {
                    $pastScheduled++;
                }
            }
        }

        // Count completed on pastDate
        $stmtPastLog = $pdo->prepare("
            SELECT COUNT(hl.id) 
            FROM habit_logs hl
            JOIN habits h ON hl.habit_id = h.id
            WHERE h.user_id = ? AND hl.activity_date = ? AND hl.is_completed = 1 AND h.is_active = 1
        ");
        $stmtPastLog->execute([$user_id, $pastDate]);
        $pastCompleted = (int)$stmtPastLog->fetchColumn();

        $trendCompleted[] = $pastCompleted;
        $trendTotal[] = $pastScheduled;
        $trendPercentages[] = ($pastScheduled > 0) ? round(($pastCompleted / $pastScheduled) * 100) : 0;
    }

    return [
        'total_habits'        => $totalHabitsCount,
        'scheduled_count'     => $scheduledCount,
        'completed_count'     => $completedCount,
        'pending_count'       => $pendingCount,
        'completion_rate'     => $completionRate,
        'best_overall_streak' => $bestOverallStreak,
        'quote'               => get_motivational_quote($completionRate),
        'scheduled_habits'    => $scheduledHabits,
        'trend' => [
            'labels'      => $trendLabels,
            'completed'   => $trendCompleted,
            'total'       => $trendTotal,
            'percentages' => $trendPercentages
        ]
    ];
}
