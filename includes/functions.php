<?php
function redirect($url) { // sends browoser to  a new page and stops executing the current script
    header('Location: ' . $url);
    exit;
}

function calculateAndSaveStreak(PDO $pdo, int $habitId): void { // calculates and saves the streak for a given habit
    $stmt = $pdo->prepare('SELECT DISTINCT log_date FROM HABIT_LOG WHERE habit_id = ? AND status = ? ORDER BY log_date ASC');
    $stmt->execute([$habitId, 'done']);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $longestStreak = 0;
    $runLength = 0;
    $previousDate = null;

    foreach ($dates as $dateStr) { // it will calculate the longest streak and current streak based on the dates when the habit was marked as done
        $date = new DateTime($dateStr);
        if ($previousDate !== null && (int) $previousDate->diff($date)->days === 1) {
            $runLength++;
        } else {
            $runLength = 1;
        }
        $longestStreak = max($longestStreak, $runLength);
        $previousDate = $date;
    }

    $currentStreak = 0; // Initialize current streak to 0
    if ($previousDate !== null) {
        $daysSinceLast = (int) $previousDate->diff(new DateTime('today'))->days;
        if ($daysSinceLast <= 1) {
            $currentStreak = $runLength;
        }
    }
   // Upsert the streak data into the STREAK table 
   // it take the data from database and use for streak calculation
    $upsert = $pdo->prepare('   
        INSERT INTO STREAK (habit_id, current_streak, longest_streak)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            current_streak = VALUES(current_streak),
            longest_streak = GREATEST(longest_streak, VALUES(longest_streak)) 
    ');
    $upsert->execute([$habitId, $currentStreak, $longestStreak]);
    
}
