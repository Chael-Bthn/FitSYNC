<?php
declare(strict_types=1);

require_once __DIR__ . '/attendance_helpers.php';
require_once __DIR__ . '/membership_helpers.php';

function memberDirectoryFilters(array $source): array
{
    $allowedStatus = ['active', 'expired', 'frozen', 'pending', 'inactive'];
    $status = in_array(($source['status'] ?? ''), $allowedStatus, true) ? (string) $source['status'] : '';

    return [
        'q' => trim((string) ($source['q'] ?? '')),
        'status' => $status,
        'branch_id' => max(0, (int) ($source['branch_id'] ?? 0)),
        'plan_id' => max(0, (int) ($source['plan_id'] ?? 0)),
        'page' => max(1, (int) ($source['page_num'] ?? $source['p'] ?? 1)),
        'per_page' => 12,
    ];
}

function memberDirectory(PDO $pdo, array $filters): array
{
    $where = ["u.role = 'member'"];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR CONCAT(u.first_name, " ", u.last_name) LIKE ?)';
        $term = '%' . $filters['q'] . '%';
        array_push($params, $term, $term, $term, $term);
    }
    if ($filters['branch_id'] > 0) {
        $where[] = 'lm.branch_id = ?';
        $params[] = $filters['branch_id'];
    }
    if ($filters['plan_id'] > 0) {
        $where[] = 'lm.plan_id = ?';
        $params[] = $filters['plan_id'];
    }

    match ($filters['status']) {
        'active' => $where[] = "lm.status = 'active' AND lm.payment_status = 'paid' AND lm.ends_at >= CURDATE()",
        'expired' => $where[] = "lm.status = 'expired'",
        'frozen' => $where[] = "lm.status = 'frozen'",
        'pending' => $where[] = "lm.payment_status = 'pending'",
        'inactive' => $where[] = "NOT EXISTS (SELECT 1 FROM attendance_logs al WHERE al.user_id = u.id AND al.check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))",
        default => null,
    };

    $whereSql = implode(' AND ', $where);
    $baseJoin = 'FROM users u
        LEFT JOIN memberships lm ON lm.id = (
            SELECT m2.id FROM memberships m2
            WHERE m2.user_id = u.id
            ORDER BY m2.ends_at DESC, m2.id DESC
            LIMIT 1
        )
        LEFT JOIN membership_plans p ON p.id = lm.plan_id
        LEFT JOIN branches b ON b.id = lm.branch_id';

    $countStmt = $pdo->prepare("SELECT COUNT(*) {$baseJoin} WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $offset = ($filters['page'] - 1) * $filters['per_page'];
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.gender, u.is_active, u.created_at, u.last_login_at,
                lm.id AS membership_id, lm.starts_at, lm.ends_at, lm.status AS membership_status,
                lm.payment_status, p.label AS plan_label, b.name AS branch_name,
                MAX(al.check_in_at) AS last_check_in,
                COUNT(al.id) AS total_visits
            {$baseJoin}
            LEFT JOIN attendance_logs al ON al.user_id = u.id
            WHERE {$whereSql}
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.gender, u.is_active, u.created_at, u.last_login_at,
                lm.id, lm.starts_at, lm.ends_at, lm.status, lm.payment_status, p.label, b.name
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([...$params, $filters['per_page'], $offset]);

    return [
        'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $filters['per_page'])),
    ];
}

function memberProfile(PDO $pdo, int $memberId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.*, lm.id AS membership_id, lm.branch_id, lm.status AS membership_status,
                lm.payment_status, lm.starts_at, lm.ends_at,
                p.label AS plan_label, b.name AS branch_name, b.city AS branch_city
         FROM users u
         LEFT JOIN memberships lm ON lm.id = (
            SELECT m2.id FROM memberships m2
            WHERE m2.user_id = u.id
            ORDER BY m2.ends_at DESC, m2.id DESC
            LIMIT 1
         )
         LEFT JOIN membership_plans p ON p.id = lm.plan_id
         LEFT JOIN branches b ON b.id = lm.branch_id
         WHERE u.id = ? AND u.role = "member"
         LIMIT 1'
    );
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();

    return $member ?: null;
}

function memberMembershipHistory(PDO $pdo, int $memberId): array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, p.label AS plan_label, b.name AS branch_name
         FROM memberships m
         INNER JOIN membership_plans p ON p.id = m.plan_id
         INNER JOIN branches b ON b.id = m.branch_id
         WHERE m.user_id = ?
         ORDER BY m.created_at DESC, m.id DESC'
    );
    $stmt->execute([$memberId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function memberAttendanceInsights(PDO $pdo, int $memberId): array
{
    $dates = fitsyncAttendanceDates($pdo, $memberId);
    $lastVisit = $dates ? end($dates) : null;
    $monthly = (int) reportScalar($pdo, 'SELECT COUNT(*) FROM attendance_logs WHERE user_id = ? AND check_in_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")', [$memberId]);
    $daysSince = $lastVisit ? (new DateTimeImmutable($lastVisit))->diff(new DateTimeImmutable('today'))->days : null;
    $frequency = (float) reportScalar(
        $pdo,
        'SELECT COALESCE(COUNT(*) / NULLIF(COUNT(DISTINCT DATE(check_in_at)), 0), 0)
         FROM attendance_logs WHERE user_id = ? AND check_in_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
        [$memberId]
    );

    return [
        'total_visits' => count($dates),
        'current_streak' => fitsyncCurrentStreak($dates),
        'last_attendance' => $lastVisit,
        'days_since_last' => $daysSince,
        'monthly_attendance' => $monthly,
        'attendance_frequency' => round($frequency, 1),
    ];
}

function memberNotes(PDO $pdo, int $memberId): array
{
    $stmt = $pdo->prepare(
        'SELECT n.*, a.first_name AS admin_first, a.last_name AS admin_last
         FROM member_notes n
         INNER JOIN users a ON a.id = n.admin_id
         WHERE n.member_id = ?
         ORDER BY n.created_at ASC'
    );
    $stmt->execute([$memberId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function memberTimeline(PDO $pdo, int $memberId): array
{
    $events = [];
    $member = memberProfile($pdo, $memberId);
    if ($member) {
        $events[] = ['date' => $member['created_at'], 'type' => 'Registration', 'body' => 'Member account created'];
    }

    foreach (memberMembershipHistory($pdo, $memberId) as $m) {
        $events[] = ['date' => $m['created_at'], 'type' => 'Membership', 'body' => $m['plan_label'] . ' membership ' . $m['status']];
        if ($m['payment_status'] === 'paid') {
            $events[] = ['date' => $m['updated_at'], 'type' => 'Payment', 'body' => 'Payment marked paid'];
        }
    }

    $stmt = $pdo->prepare(
        'SELECT al.check_in_at, b.name AS branch_name
         FROM attendance_logs al
         INNER JOIN branches b ON b.id = al.branch_id
         WHERE al.user_id = ?
         ORDER BY al.check_in_at DESC
         LIMIT 20'
    );
    $stmt->execute([$memberId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $events[] = ['date' => $row['check_in_at'], 'type' => 'Attendance', 'body' => 'Checked into ' . $row['branch_name']];
    }

    usort($events, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
    return array_slice($events, 0, 30);
}

function memberRetentionIndicators(array $member, array $insights): array
{
    $flags = [];
    if ($insights['days_since_last'] === null) {
        $flags[] = ['level' => 'warning', 'label' => 'No attendance recorded'];
    } elseif ($insights['days_since_last'] >= 14) {
        $flags[] = ['level' => 'warning', 'label' => 'No attendance in 14+ days'];
    }
    if (!empty($member['ends_at'])) {
        $daysLeft = (new DateTimeImmutable('today'))->diff(new DateTimeImmutable($member['ends_at']))->format('%r%a');
        if ((int) $daysLeft >= 0 && (int) $daysLeft <= 7) {
            $flags[] = ['level' => 'warning', 'label' => 'Membership expiring soon'];
        }
    }
    if (($member['payment_status'] ?? '') === 'pending') {
        $flags[] = ['level' => 'danger', 'label' => 'Pending payment risk'];
    }
    if ($insights['monthly_attendance'] > 0 && $insights['monthly_attendance'] < 4) {
        $flags[] = ['level' => 'info', 'label' => 'Low monthly attendance'];
    }
    return $flags;
}

function reportScalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}
