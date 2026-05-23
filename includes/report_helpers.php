<?php
declare(strict_types=1);

function reportDateRange(array $source): array
{
    $preset = (string) ($source['range'] ?? 'current_month');
    $today = new DateTimeImmutable('today');

    switch ($preset) {
        case 'today':
            $start = $today;
            $end = $today;
            break;
        case 'last_7':
            $start = $today->modify('-6 days');
            $end = $today;
            break;
        case 'last_30':
            $start = $today->modify('-29 days');
            $end = $today;
            break;
        case 'custom':
            $start = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($source['start'] ?? '')) ?: $today->modify('first day of this month');
            $end = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($source['end'] ?? '')) ?: $today;
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }
            break;
        case 'current_month':
        default:
            $preset = 'current_month';
            $start = $today->modify('first day of this month');
            $end = $today;
            break;
    }

    return [
        'preset' => $preset,
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'start_dt' => $start->format('Y-m-d 00:00:00'),
        'end_dt' => $end->format('Y-m-d 23:59:59'),
    ];
}

function reportFetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reportFetchValue(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function memberAnalytics(PDO $pdo, array $range): array
{
    $active = (int) reportFetchValue($pdo, "SELECT COUNT(*) FROM memberships WHERE status = 'active' AND payment_status = 'paid' AND starts_at <= CURDATE() AND ends_at >= CURDATE()");
    $expired = (int) reportFetchValue($pdo, "SELECT COUNT(*) FROM memberships WHERE status = 'expired'");
    $growth = (int) reportFetchValue($pdo, 'SELECT COUNT(*) FROM users WHERE role = "member" AND created_at BETWEEN ? AND ?', [$range['start_dt'], $range['end_dt']]);
    $inactive = (int) reportFetchValue(
        $pdo,
        "SELECT COUNT(*) FROM users u
         WHERE u.role = 'member' AND u.is_active = 1
           AND NOT EXISTS (
             SELECT 1 FROM attendance_logs al
             WHERE al.user_id = u.id AND al.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
           )"
    );
    $avgAttendance = (float) reportFetchValue(
        $pdo,
        'SELECT COALESCE(COUNT(al.id) / NULLIF(COUNT(DISTINCT al.user_id), 0), 0)
         FROM attendance_logs al
         WHERE al.check_in_at BETWEEN ? AND ?',
        [$range['start_dt'], $range['end_dt']]
    );

    return [
        'active_members' => $active,
        'expired_memberships' => $expired,
        'membership_growth' => $growth,
        'inactive_members' => $inactive,
        'active_inactive_ratio' => $inactive > 0 ? round($active / $inactive, 2) : $active,
        'average_attendance_frequency' => round($avgAttendance, 1),
    ];
}

function revenueAnalytics(PDO $pdo, array $range): array
{
    $paidRange = [$range['start'], $range['end']];
    $monthlyRevenue = (float) reportFetchValue(
        $pdo,
        "SELECT COALESCE(SUM(amount_paid), 0) FROM memberships
         WHERE payment_status = 'paid' AND starts_at BETWEEN ? AND ?",
        $paidRange
    );
    $pendingRevenue = (float) reportFetchValue(
        $pdo,
        "SELECT COALESCE(SUM(amount_paid), 0) FROM memberships
         WHERE payment_status = 'pending' AND starts_at BETWEEN ? AND ?",
        $paidRange
    );
    $projected = $monthlyRevenue + $pendingRevenue;
    $renewalRevenue = (float) reportFetchValue(
        $pdo,
        "SELECT COALESCE(SUM(m.amount_paid), 0)
         FROM memberships m
         WHERE m.payment_status = 'paid'
           AND m.starts_at BETWEEN ? AND ?
           AND EXISTS (
             SELECT 1 FROM memberships prev
             WHERE prev.user_id = m.user_id AND prev.id < m.id
           )",
        $paidRange
    );

    return [
        'monthly_revenue' => $monthlyRevenue,
        'pending_revenue' => $pendingRevenue,
        'paid_revenue' => $monthlyRevenue,
        'projected_revenue' => $projected,
        'renewal_revenue' => $renewalRevenue,
        'by_plan' => reportFetchAll(
            $pdo,
            "SELECT p.label, COALESCE(SUM(m.amount_paid), 0) AS revenue, COUNT(*) AS count
             FROM memberships m
             INNER JOIN membership_plans p ON p.id = m.plan_id
             WHERE m.payment_status = 'paid' AND m.starts_at BETWEEN ? AND ?
             GROUP BY p.id, p.label
             ORDER BY revenue DESC",
            $paidRange
        ),
        'by_payment_method' => reportFetchAll(
            $pdo,
            "SELECT payment_method, COALESCE(SUM(amount_paid), 0) AS revenue, COUNT(*) AS count
             FROM memberships
             WHERE payment_status = 'paid' AND starts_at BETWEEN ? AND ?
             GROUP BY payment_method
             ORDER BY revenue DESC",
            $paidRange
        ),
    ];
}

function attendanceAnalytics(PDO $pdo, array $range): array
{
    $params = [$range['start_dt'], $range['end_dt']];
    $currentCount = (int) reportFetchValue($pdo, 'SELECT COUNT(*) FROM attendance_logs WHERE check_in_at BETWEEN ? AND ?', $params);
    $start = new DateTimeImmutable($range['start']);
    $end = new DateTimeImmutable($range['end']);
    $days = max(1, $start->diff($end)->days + 1);
    $previousStart = $start->modify("-{$days} days")->format('Y-m-d 00:00:00');
    $previousEnd = $start->modify('-1 day')->format('Y-m-d 23:59:59');
    $previousCount = (int) reportFetchValue($pdo, 'SELECT COUNT(*) FROM attendance_logs WHERE check_in_at BETWEEN ? AND ?', [$previousStart, $previousEnd]);
    $growth = $previousCount > 0 ? round((($currentCount - $previousCount) / $previousCount) * 100, 1) : ($currentCount > 0 ? 100.0 : 0.0);

    return [
        'attendance_count' => $currentCount,
        'attendance_growth' => $growth,
        'busiest_days' => reportFetchAll(
            $pdo,
            'SELECT DATE(check_in_at) AS attendance_date, COUNT(*) AS visits
             FROM attendance_logs
             WHERE check_in_at BETWEEN ? AND ?
             GROUP BY DATE(check_in_at)
             ORDER BY visits DESC, attendance_date DESC
             LIMIT 7',
            $params
        ),
        'weekly_trends' => reportFetchAll(
            $pdo,
            'SELECT YEARWEEK(check_in_at, 1) AS week_key, MIN(DATE(check_in_at)) AS week_start, COUNT(*) AS visits
             FROM attendance_logs
             WHERE check_in_at BETWEEN ? AND ?
             GROUP BY YEARWEEK(check_in_at, 1)
             ORDER BY week_start ASC',
            $params
        ),
        'monthly_trends' => reportFetchAll(
            $pdo,
            'SELECT DATE_FORMAT(check_in_at, "%Y-%m") AS month_key, COUNT(*) AS visits
             FROM attendance_logs
             WHERE check_in_at BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(check_in_at, "%Y-%m")
             ORDER BY month_key ASC',
            $params
        ),
        'most_active_members' => reportFetchAll(
            $pdo,
            'SELECT u.first_name AS fname, u.last_name AS lname, u.email, COUNT(al.id) AS visits
             FROM attendance_logs al
             INNER JOIN users u ON u.id = al.user_id
             WHERE al.check_in_at BETWEEN ? AND ?
             GROUP BY u.id, u.first_name, u.last_name, u.email
             ORDER BY visits DESC
             LIMIT 8',
            $params
        ),
        'branch_comparison' => reportFetchAll(
            $pdo,
            'SELECT b.name, b.city, COUNT(al.id) AS visits
             FROM branches b
             LEFT JOIN attendance_logs al ON al.branch_id = b.id AND al.check_in_at BETWEEN ? AND ?
             WHERE b.is_active = 1
             GROUP BY b.id, b.name, b.city
             ORDER BY visits DESC, b.name ASC',
            $params
        ),
        'heatmap' => reportFetchAll(
            $pdo,
            'SELECT DATE(check_in_at) AS attendance_date, HOUR(check_in_at) AS hour_key, COUNT(*) AS visits
             FROM attendance_logs
             WHERE check_in_at BETWEEN ? AND ?
             GROUP BY DATE(check_in_at), HOUR(check_in_at)
             ORDER BY attendance_date ASC, hour_key ASC',
            $params
        ),
    ];
}

function reportExportRows(PDO $pdo, string $type, array $range): array
{
    return match ($type) {
        'attendance' => reportFetchAll(
            $pdo,
            'SELECT al.check_in_at, u.first_name, u.last_name, u.email, b.name AS branch, al.notes
             FROM attendance_logs al
             INNER JOIN users u ON u.id = al.user_id
             INNER JOIN branches b ON b.id = al.branch_id
             WHERE al.check_in_at BETWEEN ? AND ?
             ORDER BY al.check_in_at DESC',
            [$range['start_dt'], $range['end_dt']]
        ),
        'memberships' => reportFetchAll(
            $pdo,
            'SELECT u.first_name, u.last_name, u.email, p.label AS plan, b.name AS branch,
                    m.starts_at, m.ends_at, m.amount_paid, m.payment_method, m.payment_status, m.status
             FROM memberships m
             INNER JOIN users u ON u.id = m.user_id
             INNER JOIN membership_plans p ON p.id = m.plan_id
             INNER JOIN branches b ON b.id = m.branch_id
             WHERE m.starts_at BETWEEN ? AND ?
             ORDER BY m.starts_at DESC',
            [$range['start'], $range['end']]
        ),
        'revenue' => reportFetchAll(
            $pdo,
            'SELECT p.label AS plan, m.payment_method, m.payment_status, SUM(m.amount_paid) AS amount, COUNT(*) AS payments
             FROM memberships m
             INNER JOIN membership_plans p ON p.id = m.plan_id
             WHERE m.starts_at BETWEEN ? AND ?
             GROUP BY p.label, m.payment_method, m.payment_status
             ORDER BY amount DESC',
            [$range['start'], $range['end']]
        ),
        default => [],
    };
}
