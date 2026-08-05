<?php
declare(strict_types=1);

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function calculateAndSaveStreak(mysqli $conn, int $habitId): void {
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

    $longestStreak = 0;
    $runLength = 0;
    $previousDate = null;

    foreach ($dates as $dateStr) {
        $date = new DateTime($dateStr);
        if ($previousDate !== null && (int) $previousDate->diff($date)->days === 1) {
            $runLength++;
        } else {
            $runLength = 1;
        }
        $longestStreak = max($longestStreak, $runLength);
        $previousDate = $date;
    }

    $currentStreak = 0;
    if ($previousDate !== null) {
        $daysSinceLast = (int) $previousDate->diff(new DateTime('today'))->days;
        if ($daysSinceLast <= 1) {
            $currentStreak = $runLength;
        }
    }

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