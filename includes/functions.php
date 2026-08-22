<?php
declare(strict_types=1);

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function computeStreak(array $logDates, string $targetType, string $now = 'today'): array
{
    $longestStreak = 0;
    $currentStreak = 0;

    if ($targetType === 'daily') {
        // Consecutive days — a streak is any run of days with a 'done'
        // log, and it stays alive while the last log was today or yesterday.
        $runLength = 0;
        $previousDate = null;

        foreach ($logDates as $dateStr) {
            $date = new DateTime($dateStr);
            if ($previousDate !== null && (int) $previousDate->diff($date)->days === 1) {
                $runLength++;
            } else {
                $runLength = 1;
            }
            $longestStreak = max($longestStreak, $runLength);
            $previousDate = $date;
        }

        if ($previousDate !== null) {
            $daysSinceLast = (int) $previousDate->diff(new DateTime($now))->days;
            if ($daysSinceLast <= 1) {
                $currentStreak = $runLength;
            }
        }

        return [$currentStreak, $longestStreak];
    }

    // twice-a-week and weekly both measure streaks in whole weeks, so the
    // only difference is how many completions a week needs:
    // weekly => at least 1, twice a week => at least 2.
    $minPerWeek = $targetType === 'twice a week' ? 2 : 1;

    // Bucket distinct log dates into ISO weeks and count how many
    // qualifying days each week contains.
    $weekCounts = [];
    foreach ($logDates as $dateStr) {
        $dt = new DateTime($dateStr);
        $weekKey = sprintf('%04d-%02d', (int) $dt->format('o'), (int) $dt->format('W'));
        $weekCounts[$weekKey] = ($weekCounts[$weekKey] ?? 0) + 1;
    }

    // Keep only the weeks that met the cadence, keyed by their Monday —
    // so a week with too few logs is dropped before the run scan.
    $satisfiedMondays = [];
    foreach ($weekCounts as $weekKey => $count) {
        if ($count >= $minPerWeek) {
            [$year, $week] = explode('-', $weekKey);
            $monday = new DateTime();
            $monday->setISODate((int) $year, (int) $week);
            $satisfiedMondays[] = $monday;
        }
    }
    usort($satisfiedMondays, function ($a, $b) {
        return $a <=> $b;
    });

    // A streak run spans consecutive weeks whose Mondays are 7 days apart.
    $runLength = 0;
    $previousMonday = null;
    foreach ($satisfiedMondays as $monday) {
        if ($previousMonday !== null && (int) $previousMonday->diff($monday)->days === 7) {
            $runLength++;
        } else {
            $runLength = 1;
        }
        $longestStreak = max($longestStreak, $runLength);
        $previousMonday = $monday;
    }

    // The streak stays "live" while the last satisfied week is this week
    // or the week before — mirrors the daily rule's "today or yesterday" grace.
    if ($previousMonday !== null) {
        $today = new DateTime($now);
        $todayMonday = new DateTime();
        $todayMonday->setISODate((int) $today->format('o'), (int) $today->format('W'));
        $weeksSinceLast = (int) round($todayMonday->diff($previousMonday)->days / 7);
        if ($weeksSinceLast <= 1) {
            $currentStreak = $runLength;
        }
    }

    return [$currentStreak, $longestStreak];
}

function calculateAndSaveStreak(mysqli $conn, int $habitId): void
{
    // The streak cadence depends on the habit's target_type — a streak
    // means different things for daily, twice-a-week, and weekly habits,
    // so the frequency has to be read before counting anything.
    $typeStmt = mysqli_prepare($conn, 'SELECT target_type FROM HABIT WHERE habit_id = ?');
    mysqli_stmt_bind_param($typeStmt, 'i', $habitId);
    mysqli_stmt_execute($typeStmt);
    $typeResult = mysqli_stmt_get_result($typeStmt);
    $habitRow = mysqli_fetch_assoc($typeResult);
    mysqli_stmt_close($typeStmt);
    $targetType = $habitRow ? (string) $habitRow['target_type'] : 'daily';

    $status = 'done';
    $stmt = mysqli_prepare($conn, 'SELECT DISTINCT log_date FROM HABIT_LOG WHERE habit_id = ? AND status = ? ORDER BY log_date ASC');
    mysqli_stmt_bind_param($stmt, 'is', $habitId, $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // mysqli has no fetchAll(FETCH_COLUMN) equivalent — collect the
    // single column manually while walking the result set.
    $dates = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $dates[] = $row['log_date'];
    }
    mysqli_stmt_close($stmt);

    [$currentStreak, $longestStreak] = computeStreak($dates, $targetType);

    $upsert = mysqli_prepare($conn, '
        INSERT INTO STREAK (habit_id, current_streak, longest_streak)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            current_streak = VALUES(current_streak),
            longest_streak = GREATEST(longest_streak, VALUES(longest_streak))
    ');
    mysqli_stmt_bind_param($upsert, 'iii', $habitId, $currentStreak, $longestStreak);
    mysqli_stmt_execute($upsert);
    mysqli_stmt_close($upsert);
}