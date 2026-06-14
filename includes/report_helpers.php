<?php
declare(strict_types=1);

function reportDateRange(array $source): array
{
    $today = new DateTimeImmutable('today');
    $start = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($source['start'] ?? '')) ?: $today->modify('first day of this month');
    $end = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($source['end'] ?? '')) ?: $today;
    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'start_dt' => $start->format('Y-m-d 00:00:00'),
        'end_dt' => $end->format('Y-m-d 23:59:59'),
    ];
}

function reportFilters(array $source): array
{
    return [
        'range' => reportDateRange($source),
        'branch_id' => max(0, (int) ($source['branch_id'] ?? 0)),
        'plan_id' => max(0, (int) ($source['plan_id'] ?? 0)),
        'status' => trim((string) ($source['status'] ?? '')),
        'class_id' => max(0, (int) ($source['class_id'] ?? 0)),
    ];
}

function reportRows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reportValue(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function reportMoney(float|int|string|null $value): string
{
    return '₱' . number_format((float) $value, 2);
}

function reportPercent(float|int|string|null $value): string
{
    return number_format((float) $value, 1) . '%';
}

function reportPaymentDateSql(string $alias = 'm'): string
{
    return "COALESCE(NULLIF({$alias}.updated_at, {$alias}.created_at), {$alias}.created_at)";
}

function reportMembershipWhere(array $filters, string $alias = 'm'): array
{
    $where = [];
    $params = [];
    if ($filters['branch_id'] > 0) {
        $where[] = "{$alias}.branch_id = ?";
        $params[] = $filters['branch_id'];
    }
    if ($filters['plan_id'] > 0) {
        $where[] = "{$alias}.plan_id = ?";
        $params[] = $filters['plan_id'];
    }
    if ($filters['status'] !== '') {
        $where[] = "{$alias}.status = ?";
        $params[] = $filters['status'];
    }

    return [$where, $params];
}

function reportAttendanceWhere(array $filters, string $alias = 'al'): array
{
    $where = ["{$alias}.check_in_at BETWEEN ? AND ?"];
    $params = [$filters['range']['start_dt'], $filters['range']['end_dt']];
    if ($filters['branch_id'] > 0) {
        $where[] = "{$alias}.branch_id = ?";
        $params[] = $filters['branch_id'];
    }

    return [$where, $params];
}

function reportClassWhere(array $filters, string $scheduleAlias = 'cs', string $classAlias = 'c'): array
{
    $where = ["{$scheduleAlias}.scheduled_date BETWEEN ? AND ?"];
    $params = [$filters['range']['start'], $filters['range']['end']];
    if ($filters['branch_id'] > 0) {
        $where[] = "{$scheduleAlias}.branch_id = ?";
        $params[] = $filters['branch_id'];
    }
    if ($filters['class_id'] > 0) {
        $where[] = "{$classAlias}.id = ?";
        $params[] = $filters['class_id'];
    }

    return [$where, $params];
}

function reportsBuild(PDO $pdo, array $filters): array
{
    return [
        'overview' => reportOverview($pdo),
        'memberships' => reportMemberships($pdo, $filters),
        'revenue' => reportRevenue($pdo, $filters),
        'attendance' => reportAttendance($pdo, $filters),
        'classes' => reportClasses($pdo, $filters),
    ];
}

function reportOverview(PDO $pdo): array
{
    $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');
    $yearStart = (new DateTimeImmutable('first day of January'))->format('Y-m-d 00:00:00');
    $todayEnd = (new DateTimeImmutable('today'))->format('Y-m-d 23:59:59');
    $paymentDate = reportPaymentDateSql('m');

    return [
        'metrics' => [
            'total_members' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM users WHERE role = "member"'),
            'active_members' => (int) reportValue($pdo, 'SELECT COUNT(DISTINCT user_id) FROM memberships WHERE status = "active" AND payment_status = "paid" AND starts_at <= CURDATE() AND ends_at >= CURDATE()'),
            'expired_members' => (int) reportValue($pdo, 'SELECT COUNT(DISTINCT user_id) FROM memberships WHERE status = "expired" OR ends_at < CURDATE()'),
            'pending_renewals' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM memberships WHERE status = "pending" OR payment_status = "pending"'),
            'revenue_month' => (float) reportValue($pdo, "SELECT COALESCE(SUM(amount_paid), 0) FROM memberships m WHERE payment_status = 'paid' AND {$paymentDate} BETWEEN ? AND ?", [$monthStart, $todayEnd])
                            + (float) reportValue($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE (payment_status='paid' OR (status='completed' AND payment_method IN ('cash_on_pickup','cash_on_delivery'))) AND status!='cancelled' AND created_at BETWEEN ? AND ?", [$monthStart, $todayEnd]),
            'revenue_year'  => (float) reportValue($pdo, "SELECT COALESCE(SUM(amount_paid), 0) FROM memberships m WHERE payment_status = 'paid' AND {$paymentDate} BETWEEN ? AND ?", [$yearStart, $todayEnd])
                            + (float) reportValue($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE (payment_status='paid' OR (status='completed' AND payment_method IN ('cash_on_pickup','cash_on_delivery'))) AND status!='cancelled' AND created_at BETWEEN ? AND ?", [$yearStart, $todayEnd]),
            'attendance_month' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM attendance_logs WHERE check_in_at BETWEEN ? AND ?', [$monthStart, $todayEnd]),
            'upcoming_classes' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM class_schedules WHERE status = "scheduled" AND TIMESTAMP(scheduled_date, start_time) >= NOW()'),
        ],
        'membership_status' => reportRows(
            $pdo,
            'SELECT status, COUNT(*) AS total
             FROM memberships
             GROUP BY status
             ORDER BY total DESC, status ASC'
        ),
        'revenue_trend' => reportRows(
            $pdo,
            "SELECT DATE_FORMAT({$paymentDate}, '%Y-%m') AS month_key, COALESCE(SUM(m.amount_paid), 0) AS revenue
             FROM memberships m
             WHERE m.payment_status = 'paid' AND {$paymentDate} >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
             GROUP BY DATE_FORMAT({$paymentDate}, '%Y-%m')
             ORDER BY month_key ASC"
        ),
        'attendance_summary' => reportRows(
            $pdo,
            'SELECT DATE(check_in_at) AS attendance_date, COUNT(*) AS visits, COUNT(DISTINCT user_id) AS visitors
             FROM attendance_logs
             WHERE check_in_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(check_in_at)
             ORDER BY attendance_date ASC'
        ),
    ];
}

function reportMemberships(PDO $pdo, array $filters): array
{
    [$filterWhere, $filterParams] = reportMembershipWhere($filters, 'm');
    $createdWhere = array_merge(['u.created_at BETWEEN ? AND ?'], $filterWhere);
    $createdParams = array_merge([$filters['range']['start_dt'], $filters['range']['end_dt']], $filterParams);
    $whereSql = $filterWhere ? ' AND ' . implode(' AND ', $filterWhere) : '';

    return [
        'metrics' => [
            'active' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM memberships m WHERE m.status = "active" AND m.payment_status = "paid"' . $whereSql, $filterParams),
            'expired' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM memberships m WHERE (m.status = "expired" OR m.ends_at < CURDATE())' . $whereSql, $filterParams),
            'frozen' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM memberships m WHERE m.status = "frozen"' . $whereSql, $filterParams),
            'cancelled' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM memberships m WHERE m.status = "cancelled"' . $whereSql, $filterParams),
            'new_this_period' => (int) reportValue($pdo, 'SELECT COUNT(DISTINCT u.id) FROM users u LEFT JOIN memberships m ON m.user_id = u.id WHERE u.role = "member" AND ' . implode(' AND ', $createdWhere), $createdParams),
            'expiring_soon' => (int) reportValue($pdo, 'SELECT COUNT(*) FROM memberships m WHERE m.status = "active" AND m.payment_status = "paid" AND m.ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)' . $whereSql, $filterParams),
        ],
        'recent_members' => reportRows(
            $pdo,
            'SELECT u.first_name, u.last_name, u.email, u.created_at, p.label AS plan, b.name AS branch, m.status
             FROM users u
             LEFT JOIN memberships m ON m.id = (
                SELECT m2.id FROM memberships m2 WHERE m2.user_id = u.id ORDER BY m2.created_at DESC, m2.id DESC LIMIT 1
             )
             LEFT JOIN membership_plans p ON p.id = m.plan_id
             LEFT JOIN branches b ON b.id = m.branch_id
             WHERE u.role = "member" AND u.created_at BETWEEN ? AND ?
             ORDER BY u.created_at DESC
             LIMIT 10',
            [$filters['range']['start_dt'], $filters['range']['end_dt']]
        ),
        'expiring_soon' => reportRows(
            $pdo,
            'SELECT u.first_name, u.last_name, u.email, p.label AS plan, b.name AS branch, m.ends_at
             FROM memberships m
             INNER JOIN users u ON u.id = m.user_id
             INNER JOIN membership_plans p ON p.id = m.plan_id
             INNER JOIN branches b ON b.id = m.branch_id
             WHERE m.status = "active" AND m.payment_status = "paid"
               AND m.ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)' . $whereSql . '
             ORDER BY m.ends_at ASC
             LIMIT 10',
            $filterParams
        ),
        'recent_renewals' => reportRows(
            $pdo,
            'SELECT u.first_name, u.last_name, u.email, p.label AS plan, b.name AS branch, m.amount_paid, m.payment_status, m.status, m.created_at
             FROM memberships m
             INNER JOIN users u ON u.id = m.user_id
             INNER JOIN membership_plans p ON p.id = m.plan_id
             INNER JOIN branches b ON b.id = m.branch_id
             WHERE m.created_at BETWEEN ? AND ?' . $whereSql . '
             ORDER BY m.created_at DESC
             LIMIT 10',
            array_merge([$filters['range']['start_dt'], $filters['range']['end_dt']], $filterParams)
        ),
    ];
}

function reportRevenue(PDO $pdo, array $filters): array
{
    // ── Membership revenue ─────────────────────────────────────
    [$filterWhere, $filterParams] = reportMembershipWhere($filters, 'm');
    $paymentDate = reportPaymentDateSql('m');
    $rangeWhere  = ["m.payment_status = 'paid'", "{$paymentDate} BETWEEN ? AND ?"];
    $rangeParams = [$filters['range']['start_dt'], $filters['range']['end_dt']];
    $where       = array_merge($rangeWhere, $filterWhere);
    $params      = array_merge($rangeParams, $filterParams);
    $base        = ' FROM memberships m INNER JOIN membership_plans p ON p.id = m.plan_id INNER JOIN branches b ON b.id = m.branch_id WHERE ' . implode(' AND ', $where);
    $memberBase  = ' FROM memberships m INNER JOIN users u ON u.id = m.user_id INNER JOIN membership_plans p ON p.id = m.plan_id INNER JOIN branches b ON b.id = m.branch_id WHERE ' . implode(' AND ', $where);

    // ── Shop order revenue (Option C) ──────────────────────────
    // Counts: online-paid orders + COD/COP orders marked completed
    $oc       = "(o.payment_status = 'paid' OR (o.status = 'completed' AND o.payment_method IN ('cash_on_pickup','cash_on_delivery'))) AND o.status != 'cancelled'";
    $ocGlobal = "(payment_status = 'paid' OR (status = 'completed' AND payment_method IN ('cash_on_pickup','cash_on_delivery'))) AND status != 'cancelled'";
    $shopParams = [$filters['range']['start_dt'], $filters['range']['end_dt']];

    $memTotal  = (float) reportValue($pdo, "SELECT COALESCE(SUM(m.amount_paid), 0){$base}", $params);
    $shopTotal = (float) reportValue($pdo, "SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE {$oc} AND o.created_at BETWEEN ? AND ?", $shopParams);

    // ── Live quick-stats (membership + shop combined) ──────────
    $pd = $paymentDate;
    return [
        'metrics' => [
            'period_total'     => $memTotal + $shopTotal,
            'membership_total' => $memTotal,
            'shop_total'       => $shopTotal,
            'today'  => (float) reportValue($pdo, "SELECT COALESCE(SUM(amount_paid),0) FROM memberships m WHERE payment_status='paid' AND {$pd} BETWEEN CURDATE() AND CONCAT(CURDATE(),' 23:59:59')")
                       + (float) reportValue($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE {$ocGlobal} AND created_at BETWEEN CURDATE() AND CONCAT(CURDATE(),' 23:59:59')"),
            'week'   => (float) reportValue($pdo, "SELECT COALESCE(SUM(amount_paid),0) FROM memberships m WHERE payment_status='paid' AND {$pd} >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)")
                       + (float) reportValue($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE {$ocGlobal} AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)"),
            'month'  => (float) reportValue($pdo, "SELECT COALESCE(SUM(amount_paid),0) FROM memberships m WHERE payment_status='paid' AND {$pd} >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")
                       + (float) reportValue($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE {$ocGlobal} AND created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')"),
            'year'   => (float) reportValue($pdo, "SELECT COALESCE(SUM(amount_paid),0) FROM memberships m WHERE payment_status='paid' AND {$pd} >= MAKEDATE(YEAR(CURDATE()),1)")
                       + (float) reportValue($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE {$ocGlobal} AND created_at >= MAKEDATE(YEAR(CURDATE()),1)"),
        ],
        'by_plan'        => reportRows($pdo, "SELECT p.label, COALESCE(SUM(m.amount_paid), 0) AS revenue, COUNT(*) AS payments {$base} GROUP BY p.id, p.label ORDER BY revenue DESC", $params),
        'by_branch'      => reportRows($pdo, "SELECT b.name, b.city, COALESCE(SUM(m.amount_paid), 0) AS revenue, COUNT(*) AS payments {$base} GROUP BY b.id, b.name, b.city ORDER BY revenue DESC", $params),
        'by_month'       => reportRows($pdo, "SELECT DATE_FORMAT({$paymentDate}, '%Y-%m') AS month_key, COALESCE(SUM(m.amount_paid), 0) AS revenue, COUNT(*) AS payments {$base} GROUP BY DATE_FORMAT({$paymentDate}, '%Y-%m') ORDER BY month_key ASC", $params),
        'shop_by_month'  => reportRows($pdo, "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS month_key, COALESCE(SUM(o.total_amount), 0) AS revenue, COUNT(*) AS payments FROM orders o WHERE {$oc} AND o.created_at BETWEEN ? AND ? GROUP BY DATE_FORMAT(o.created_at, '%Y-%m') ORDER BY month_key ASC", $shopParams),
        'recent_payments' => reportRows($pdo, "SELECT CONCAT(u.first_name, ' ', u.last_name) AS member_name, u.email, p.label AS plan, b.name AS branch, m.amount_paid, m.payment_method, {$paymentDate} AS paid_at {$memberBase} ORDER BY paid_at DESC LIMIT 12", $params),
        'recent_renewals' => reportRows($pdo, "SELECT CONCAT(u.first_name, ' ', u.last_name) AS member_name, p.label AS plan, b.name AS branch, m.amount_paid, {$paymentDate} AS paid_at {$memberBase} AND EXISTS (SELECT 1 FROM memberships prev WHERE prev.user_id = m.user_id AND prev.id < m.id) ORDER BY paid_at DESC LIMIT 12", $params),
        'shop_recent'    => reportRows($pdo, "SELECT o.id AS order_id, CONCAT(u.first_name,' ',u.last_name) AS member_name, u.email, o.total_amount, o.payment_method, o.status, o.created_at AS paid_at FROM orders o JOIN users u ON u.id = o.user_id WHERE {$oc} AND o.created_at BETWEEN ? AND ? ORDER BY o.created_at DESC LIMIT 12", $shopParams),
        'summary'        => reportRows($pdo, "SELECT p.label AS plan, b.name AS branch, COUNT(*) AS payments, COALESCE(SUM(m.amount_paid), 0) AS revenue {$base} GROUP BY p.id, p.label, b.id, b.name ORDER BY revenue DESC LIMIT 12", $params),
    ];
}

function reportAttendance(PDO $pdo, array $filters): array
{
    [$where, $params] = reportAttendanceWhere($filters, 'al');
    $whereSql = implode(' AND ', $where);
    $days = max(1, (new DateTimeImmutable($filters['range']['start']))->diff(new DateTimeImmutable($filters['range']['end']))->days + 1);

    return [
        'metrics' => [
            'total_checkins' => (int) reportValue($pdo, "SELECT COUNT(*) FROM attendance_logs al WHERE {$whereSql}", $params),
            'unique_visitors' => (int) reportValue($pdo, "SELECT COUNT(DISTINCT al.user_id) FROM attendance_logs al WHERE {$whereSql}", $params),
            'average_daily' => round(((int) reportValue($pdo, "SELECT COUNT(*) FROM attendance_logs al WHERE {$whereSql}", $params)) / $days, 1),
            'peak_day' => reportRows($pdo, "SELECT DATE(al.check_in_at) AS day_key, COUNT(*) AS visits FROM attendance_logs al WHERE {$whereSql} GROUP BY DATE(al.check_in_at) ORDER BY visits DESC, day_key DESC LIMIT 1", $params)[0] ?? null,
            'peak_hour' => reportRows($pdo, "SELECT HOUR(al.check_in_at) AS hour_key, COUNT(*) AS visits FROM attendance_logs al WHERE {$whereSql} GROUP BY HOUR(al.check_in_at) ORDER BY visits DESC, hour_key ASC LIMIT 1", $params)[0] ?? null,
        ],
        'most_active_members' => reportRows($pdo, "SELECT CONCAT(u.first_name, ' ', u.last_name) AS member_name, u.email, COUNT(al.id) AS visits, MAX(al.check_in_at) AS last_visit FROM attendance_logs al INNER JOIN users u ON u.id = al.user_id WHERE {$whereSql} GROUP BY u.id, u.first_name, u.last_name, u.email ORDER BY visits DESC LIMIT 10", $params),
        'by_branch' => reportRows($pdo, "SELECT b.name, b.city, COUNT(al.id) AS visits, COUNT(DISTINCT al.user_id) AS visitors FROM branches b LEFT JOIN attendance_logs al ON al.branch_id = b.id AND al.check_in_at BETWEEN ? AND ?" . ($filters['branch_id'] > 0 ? ' AND al.branch_id = ?' : '') . " WHERE b.is_active = 1 GROUP BY b.id, b.name, b.city ORDER BY visits DESC, b.name ASC", $filters['branch_id'] > 0 ? [$filters['range']['start_dt'], $filters['range']['end_dt'], $filters['branch_id']] : [$filters['range']['start_dt'], $filters['range']['end_dt']]),
        'by_date' => reportRows($pdo, "SELECT DATE(al.check_in_at) AS attendance_date, COUNT(*) AS visits, COUNT(DISTINCT al.user_id) AS visitors FROM attendance_logs al WHERE {$whereSql} GROUP BY DATE(al.check_in_at) ORDER BY attendance_date DESC LIMIT 14", $params),
        'trend' => reportRows($pdo, "SELECT DATE(al.check_in_at) AS attendance_date, COUNT(*) AS visits FROM attendance_logs al WHERE {$whereSql} GROUP BY DATE(al.check_in_at) ORDER BY attendance_date ASC", $params),
        'by_day_of_week' => reportRows($pdo, "SELECT DAYNAME(al.check_in_at) AS day_name, WEEKDAY(al.check_in_at) AS day_order, COUNT(*) AS visits FROM attendance_logs al WHERE {$whereSql} GROUP BY DAYNAME(al.check_in_at), WEEKDAY(al.check_in_at) ORDER BY day_order ASC", $params),
    ];
}

function reportClasses(PDO $pdo, array $filters): array
{
    [$where, $params] = reportClassWhere($filters, 'cs', 'c');
    $whereSql = implode(' AND ', $where);
    $base = " FROM class_schedules cs INNER JOIN classes c ON c.id = cs.class_id INNER JOIN branches b ON b.id = cs.branch_id LEFT JOIN class_bookings cb ON cb.class_schedule_id = cs.id WHERE {$whereSql}";

    return [
        'metrics' => [
            'total_bookings' => (int) reportValue($pdo, "SELECT COUNT(cb.id) {$base}", $params),
            'total_attendance' => (int) reportValue($pdo, "SELECT COUNT(CASE WHEN cb.booking_status = 'attended' THEN 1 END) {$base}", $params),
            'average_attendance' => (float) reportValue($pdo, "SELECT COALESCE(COUNT(CASE WHEN cb.booking_status = 'attended' THEN 1 END) / NULLIF(COUNT(DISTINCT cs.id), 0), 0) {$base}", $params),
            'completion_rate' => (float) reportValue($pdo, "SELECT COALESCE(SUM(cs.status = 'completed') / NULLIF(COUNT(DISTINCT cs.id), 0) * 100, 0) FROM class_schedules cs INNER JOIN classes c ON c.id = cs.class_id WHERE {$whereSql}", $params),
        ],
        'popular_classes' => reportRows($pdo, "SELECT c.title, b.name AS branch, COUNT(cb.id) AS bookings, COUNT(CASE WHEN cb.booking_status = 'attended' THEN 1 END) AS attendance, MAX(c.capacity) AS capacity, COALESCE(COUNT(cb.id) / NULLIF(SUM(COALESCE(c.capacity, 0)), 0) * 100, 0) AS utilization {$base} GROUP BY c.id, c.title, b.id, b.name ORDER BY bookings DESC, attendance DESC LIMIT 10", $params),
        'upcoming_classes' => reportRows($pdo, 'SELECT c.title, b.name AS branch, cs.scheduled_date, cs.start_time, c.capacity, COUNT(cb.id) AS bookings FROM class_schedules cs INNER JOIN classes c ON c.id = cs.class_id INNER JOIN branches b ON b.id = cs.branch_id LEFT JOIN class_bookings cb ON cb.class_schedule_id = cs.id AND cb.booking_status IN ("booked","attended") WHERE cs.status = "scheduled" AND TIMESTAMP(cs.scheduled_date, cs.start_time) >= NOW() GROUP BY cs.id, c.title, b.name, cs.scheduled_date, cs.start_time, c.capacity ORDER BY cs.scheduled_date ASC, cs.start_time ASC LIMIT 10'),
        'ranking' => reportRows($pdo, "SELECT c.title, b.name AS branch, COUNT(cb.id) AS bookings, COUNT(CASE WHEN cb.booking_status = 'attended' THEN 1 END) AS attendance, MAX(c.capacity) AS capacity, COALESCE(COUNT(cb.id) / NULLIF(SUM(COALESCE(c.capacity, 0)), 0) * 100, 0) AS utilization {$base} GROUP BY c.id, c.title, b.id, b.name ORDER BY utilization DESC, bookings DESC LIMIT 12", $params),
        'bookings_per_class' => reportRows($pdo, "SELECT c.title, COUNT(cb.id) AS bookings {$base} GROUP BY c.id, c.title ORDER BY bookings DESC LIMIT 10", $params),
        'attendance_per_class' => reportRows($pdo, "SELECT c.title, COUNT(CASE WHEN cb.booking_status = 'attended' THEN 1 END) AS attendance {$base} GROUP BY c.id, c.title ORDER BY attendance DESC LIMIT 10", $params),
    ];
}

function reportExportRows(PDO $pdo, string $type, array $filters): array
{
    return match ($type) {
        'memberships' => reportMembershipExport($pdo, $filters),
        'revenue' => reportRevenueExport($pdo, $filters),
        'attendance' => reportAttendanceExport($pdo, $filters),
        'classes' => reportClassesExport($pdo, $filters),
        default => [],
    };
}

function reportMembershipExport(PDO $pdo, array $filters): array
{
    [$where, $params] = reportMembershipWhere($filters, 'm');
    $where[] = 'm.created_at BETWEEN ? AND ?';
    $params[] = $filters['range']['start_dt'];
    $params[] = $filters['range']['end_dt'];
    return reportRows($pdo, 'SELECT u.first_name, u.last_name, u.email, p.label AS plan, b.name AS branch, m.starts_at, m.ends_at, m.amount_paid, m.payment_method, m.payment_status, m.status, m.created_at FROM memberships m INNER JOIN users u ON u.id = m.user_id INNER JOIN membership_plans p ON p.id = m.plan_id INNER JOIN branches b ON b.id = m.branch_id WHERE ' . implode(' AND ', $where) . ' ORDER BY m.created_at DESC', $params);
}

function reportRevenueExport(PDO $pdo, array $filters): array
{
    // Membership payments
    [$where, $params] = reportMembershipWhere($filters, 'm');
    $paymentDate = reportPaymentDateSql('m');
    array_unshift($where, "m.payment_status = 'paid'", "{$paymentDate} BETWEEN ? AND ?");
    array_unshift($params, $filters['range']['end_dt']);
    array_unshift($params, $filters['range']['start_dt']);
    $memRows = reportRows($pdo,
        "SELECT 'membership' AS source, CONCAT(u.first_name,' ',u.last_name) AS member, u.email, p.label AS type, b.name AS branch, m.amount_paid AS amount, m.payment_method, {$paymentDate} AS date
         FROM memberships m INNER JOIN users u ON u.id=m.user_id INNER JOIN membership_plans p ON p.id=m.plan_id INNER JOIN branches b ON b.id=m.branch_id
         WHERE " . implode(' AND ', $where) . ' ORDER BY date DESC', $params);

    // Shop order revenue (Option C)
    $oc = "(o.payment_status='paid' OR (o.status='completed' AND o.payment_method IN ('cash_on_pickup','cash_on_delivery'))) AND o.status!='cancelled'";
    $shopRows = reportRows($pdo,
        "SELECT 'shop_order' AS source, CONCAT(u.first_name,' ',u.last_name) AS member, u.email,
                CONCAT('Order #',o.id) AS type, COALESCE(b.name,'Delivery') AS branch,
                o.total_amount AS amount, o.payment_method, o.created_at AS date
         FROM orders o JOIN users u ON u.id=o.user_id LEFT JOIN branches b ON b.id=o.pickup_branch_id
         WHERE {$oc} AND o.created_at BETWEEN ? AND ? ORDER BY o.created_at DESC",
        [$filters['range']['start_dt'], $filters['range']['end_dt']]);

    return array_merge($memRows, $shopRows);
}

function reportAttendanceExport(PDO $pdo, array $filters): array
{
    [$where, $params] = reportAttendanceWhere($filters, 'al');
    return reportRows($pdo, 'SELECT al.check_in_at, u.first_name, u.last_name, u.email, b.name AS branch, al.notes FROM attendance_logs al INNER JOIN users u ON u.id = al.user_id INNER JOIN branches b ON b.id = al.branch_id WHERE ' . implode(' AND ', $where) . ' ORDER BY al.check_in_at DESC', $params);
}

function reportClassesExport(PDO $pdo, array $filters): array
{
    [$where, $params] = reportClassWhere($filters, 'cs', 'c');
    return reportRows($pdo, 'SELECT c.title, b.name AS branch, cs.scheduled_date, cs.start_time, cs.end_time, cs.status, c.capacity, COUNT(cb.id) AS bookings, COUNT(CASE WHEN cb.booking_status = "attended" THEN 1 END) AS attendance, COALESCE(COUNT(cb.id) / NULLIF(c.capacity, 0) * 100, 0) AS utilization_rate FROM class_schedules cs INNER JOIN classes c ON c.id = cs.class_id INNER JOIN branches b ON b.id = cs.branch_id LEFT JOIN class_bookings cb ON cb.class_schedule_id = cs.id WHERE ' . implode(' AND ', $where) . ' GROUP BY cs.id, c.title, b.name, cs.scheduled_date, cs.start_time, cs.end_time, cs.status, c.capacity ORDER BY cs.scheduled_date DESC, cs.start_time DESC', $params);
}
