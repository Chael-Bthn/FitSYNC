<?php
declare(strict_types=1);

function scheduleBranches(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, name, city, address
         FROM branches
         WHERE is_active = 1
         ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);
}

function scheduleClasses(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT c.*, b.name AS branch_name, b.city AS branch_city
            FROM classes c
            INNER JOIN branches b ON b.id = c.branch_id';
    if ($activeOnly) {
        $sql .= ' WHERE c.is_active = 1 AND b.is_active = 1';
    }
    $sql .= ' ORDER BY c.is_active DESC, c.title ASC';

    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function scheduleClassSchedules(PDO $pdo, int $limit = 80): array
{
    $stmt = $pdo->prepare(
        'SELECT cs.*, c.title, c.description, c.trainer_name, c.duration_minutes, c.capacity,
                b.name AS branch_name, b.city AS branch_city,
                (
                    SELECT COUNT(*)
                    FROM class_bookings cb
                    WHERE cb.class_schedule_id = cs.id
                      AND cb.booking_status IN ("booked","attended")
                ) AS booked_count
         FROM class_schedules cs
         INNER JOIN classes c ON c.id = cs.class_id
         INNER JOIN branches b ON b.id = cs.branch_id
         ORDER BY cs.scheduled_date DESC, cs.start_time DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function scheduleAnnouncements(PDO $pdo, ?int $branchId = null, bool $activeOnly = false): array
{
    $where = [];
    $params = [];
    if ($branchId !== null && $branchId > 0) {
        $where[] = 'ba.branch_id = ?';
        $params[] = $branchId;
    }
    if ($activeOnly) {
        $where[] = 'ba.is_active = 1';
        $where[] = 'ba.starts_at <= NOW()';
        $where[] = '(ba.ends_at IS NULL OR ba.ends_at >= NOW())';
    }

    $sql = 'SELECT ba.*, b.name AS branch_name, b.city AS branch_city
            FROM branch_announcements ba
            INNER JOIN branches b ON b.id = ba.branch_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ba.is_active DESC, ba.starts_at DESC, ba.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function scheduleOperatingHours(PDO $pdo, ?int $branchId = null): array
{
    $params = [];
    $where = '';
    if ($branchId !== null && $branchId > 0) {
        $where = ' WHERE boh.branch_id = ?';
        $params[] = $branchId;
    }

    $stmt = $pdo->prepare(
        'SELECT boh.*, b.name AS branch_name
         FROM branch_operating_hours boh
         INNER JOIN branches b ON b.id = boh.branch_id' . $where . '
         ORDER BY b.name ASC, boh.day_of_week ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function scheduleUpcomingClasses(PDO $pdo, ?int $branchId = null, int $daysAhead = 14, int $limit = 20, ?int $userId = null): array
{
    $params = [];
    $branchSql = '';
    $userSelect = 'NULL AS member_booking_id, NULL AS member_booking_status';
    $userJoin = '';
    if ($userId !== null && $userId > 0) {
        $userSelect = 'ub.id AS member_booking_id, ub.booking_status AS member_booking_status';
        $userJoin = ' LEFT JOIN class_bookings ub ON ub.class_schedule_id = cs.id AND ub.user_id = ? AND ub.booking_status = "booked"';
        $params[] = $userId;
    }
    $params[] = $daysAhead;
    if ($branchId !== null && $branchId > 0) {
        $branchSql = ' AND cs.branch_id = ?';
        $params[] = $branchId;
    }
    $params[] = $limit;

    $stmt = $pdo->prepare(
        'SELECT cs.*, c.title, c.description, c.trainer_name, c.duration_minutes, c.capacity,
                b.name AS branch_name, b.city AS branch_city,
                (
                    SELECT COUNT(*)
                    FROM class_bookings cb
                    WHERE cb.class_schedule_id = cs.id
                      AND cb.booking_status IN ("booked","attended")
                ) AS booked_count,
                ' . $userSelect . '
         FROM class_schedules cs
         INNER JOIN classes c ON c.id = cs.class_id
         INNER JOIN branches b ON b.id = cs.branch_id
         ' . $userJoin . '
         WHERE c.is_active = 1
           AND cs.status = "scheduled"
           AND TIMESTAMP(cs.scheduled_date, cs.start_time) >= NOW()
           AND cs.scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)' . $branchSql . '
         ORDER BY cs.scheduled_date ASC, cs.start_time ASC
         LIMIT ?'
    );
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function scheduleTodayClasses(PDO $pdo, ?int $branchId = null): array
{
    $params = [];
    $branchSql = '';
    if ($branchId !== null && $branchId > 0) {
        $branchSql = ' AND cs.branch_id = ?';
        $params[] = $branchId;
    }

    $stmt = $pdo->prepare(
        'SELECT cs.*, c.title, c.trainer_name, b.name AS branch_name
         FROM class_schedules cs
         INNER JOIN classes c ON c.id = cs.class_id
         INNER JOIN branches b ON b.id = cs.branch_id
         WHERE c.is_active = 1
           AND cs.status = "scheduled"
           AND cs.scheduled_date = CURDATE()' . $branchSql . '
         ORDER BY cs.start_time ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function memberScheduleContext(PDO $pdo, ?array $membership, ?int $userId = null): array
{
    $branchId = (int) ($membership['branch_id'] ?? 0);

    return [
        'upcoming_classes' => scheduleUpcomingClasses($pdo, $branchId ?: null, 14, 8, $userId),
        'today_classes' => scheduleTodayClasses($pdo, $branchId ?: null),
        'announcements' => scheduleAnnouncements($pdo, $branchId ?: null, true),
        'hours' => scheduleOperatingHours($pdo, $branchId ?: null),
        'weekly_class_count' => count(scheduleUpcomingClasses($pdo, $branchId ?: null, 7, 50)),
        'bookings' => $userId ? scheduleMemberUpcomingBookings($pdo, $userId, 10) : [],
    ];
}

function scheduleMemberUpcomingBookings(PDO $pdo, int $userId, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        'SELECT cb.*, cs.scheduled_date, cs.start_time, cs.end_time, cs.status AS schedule_status,
                c.title, c.trainer_name, c.capacity, b.name AS branch_name,
                (
                    SELECT COUNT(*)
                    FROM class_bookings allb
                    WHERE allb.class_schedule_id = cs.id
                      AND allb.booking_status IN ("booked","attended")
                ) AS booked_count
         FROM class_bookings cb
         INNER JOIN class_schedules cs ON cs.id = cb.class_schedule_id
         INNER JOIN classes c ON c.id = cs.class_id
         INNER JOIN branches b ON b.id = cs.branch_id
         WHERE cb.user_id = ?
           AND cb.booking_status = "booked"
           AND TIMESTAMP(cs.scheduled_date, cs.start_time) >= NOW()
         ORDER BY cs.scheduled_date ASC, cs.start_time ASC
         LIMIT ?'
    );
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function scheduleRemainingCapacity(?int $capacity, int $bookedCount): ?int
{
    if ($capacity === null || $capacity <= 0) {
        return null;
    }

    return max(0, $capacity - $bookedCount);
}

function scheduleReserveClass(PDO $pdo, int $userId, int $scheduleId): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT cs.*, c.capacity, c.is_active
             FROM class_schedules cs
             INNER JOIN classes c ON c.id = cs.class_id
             WHERE cs.id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();
        if (!$schedule || (int) $schedule['is_active'] !== 1) {
            throw new RuntimeException('Class schedule was not found.');
        }
        if ($schedule['status'] !== 'scheduled') {
            throw new RuntimeException('This class is not available for booking.');
        }
        if (strtotime($schedule['scheduled_date'] . ' ' . $schedule['start_time']) < time()) {
            throw new RuntimeException('Past classes cannot be booked.');
        }

        $existing = $pdo->prepare(
            'SELECT id FROM class_bookings
             WHERE user_id = ? AND class_schedule_id = ? AND booking_status = "booked"
             LIMIT 1'
        );
        $existing->execute([$userId, $scheduleId]);
        if ($existing->fetch()) {
            throw new RuntimeException('You already reserved this class.');
        }

        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM class_bookings
             WHERE class_schedule_id = ? AND booking_status IN ("booked","attended")'
        );
        $count->execute([$scheduleId]);
        $booked = (int) $count->fetchColumn();
        $capacity = $schedule['capacity'] !== null ? (int) $schedule['capacity'] : null;
        if ($capacity !== null && $capacity > 0 && $booked >= $capacity) {
            throw new RuntimeException('This class is already full.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO class_bookings (user_id, class_schedule_id, booking_status, booked_at)
             VALUES (?, ?, "booked", NOW())'
        );
        $insert->execute([$userId, $scheduleId]);
        $pdo->commit();

        return ['success' => true, 'message' => 'Class reserved.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function scheduleCancelBooking(PDO $pdo, int $userId, int $bookingId): array
{
    $stmt = $pdo->prepare(
        'UPDATE class_bookings cb
         INNER JOIN class_schedules cs ON cs.id = cb.class_schedule_id
         SET cb.booking_status = "cancelled", cb.cancelled_at = NOW()
         WHERE cb.id = ?
           AND cb.user_id = ?
           AND cb.booking_status = "booked"
           AND TIMESTAMP(cs.scheduled_date, cs.start_time) >= NOW()'
    );
    $stmt->execute([$bookingId, $userId]);

    return [
        'success' => $stmt->rowCount() > 0,
        'message' => $stmt->rowCount() > 0 ? 'Booking cancelled.' : 'Booking cannot be cancelled.',
    ];
}

function scheduleDayName(int $dayOfWeek): string
{
    return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$dayOfWeek - 1] ?? 'Unknown';
}

function scheduleTime(?string $time): string
{
    return $time ? date('g:i A', strtotime($time)) : 'Closed';
}
