<?php
declare(strict_types=1);

require_once __DIR__ . '/membership_helpers.php';

function fitsyncActiveMembership(PDO $pdo, int $userId): ?array
{
    return getActiveMembership($pdo, $userId);
}

function fitsyncAttendanceDates(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT DATE(check_in_at) AS attended_on
         FROM attendance_logs
         WHERE user_id = ?
         ORDER BY attended_on ASC'
    );
    $stmt->execute([$userId]);

    return array_values(array_filter(array_column($stmt->fetchAll(), 'attended_on')));
}

function fitsyncAttendanceTotal(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance_logs WHERE user_id = ?');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function fitsyncCurrentStreak(array $attendanceDates, ?DateTimeImmutable $today = null): int
{
    if (!$attendanceDates) {
        return 0;
    }

    $set = array_fill_keys($attendanceDates, true);
    $today = $today ?: new DateTimeImmutable('today');
    $cursor = isset($set[$today->format('Y-m-d')])
        ? $today
        : $today->modify('-1 day');

    $streak = 0;
    while (isset($set[$cursor->format('Y-m-d')])) {
        $streak++;
        $cursor = $cursor->modify('-1 day');
    }

    return $streak;
}
