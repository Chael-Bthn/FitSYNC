<?php
declare(strict_types=1);

function memberDashboardData(?array $membership, array $membershipHistory, array $attendanceDates, int $monthlyVisits, int $daysRemaining, bool $hasActiveMembership, array $scheduleContext = []): array
{
    $today = new DateTimeImmutable('today');
    $checkedInToday = in_array($today->format('Y-m-d'), $attendanceDates, true);
    $lastAttendanceDate = $attendanceDates ? end($attendanceDates) : null;
    $latestMembership = $membershipHistory[0] ?? $membership;
    $todayClasses = $scheduleContext['today_classes'] ?? [];
    $upcomingClasses = $scheduleContext['upcoming_classes'] ?? [];
    $announcements = $scheduleContext['announcements'] ?? [];
    $hours = $scheduleContext['hours'] ?? [];
    $bookings = $scheduleContext['bookings'] ?? [];
    $firstTodayClass = $todayClasses[0] ?? null;
    $firstAnnouncement = $announcements[0] ?? null;
    $nextBooking = $bookings[0] ?? null;
    $weekEnd = $today->modify('+7 days');
    $upcomingBookingsThisWeek = array_filter($bookings, static function (array $booking) use ($today, $weekEnd): bool {
        $bookingDate = DateTimeImmutable::createFromFormat('Y-m-d', (string) ($booking['scheduled_date'] ?? ''));
        return $bookingDate && $bookingDate >= $today && $bookingDate <= $weekEnd;
    });

    return [
        'alerts' => memberMembershipAlerts($membership, $latestMembership, $daysRemaining, $hasActiveMembership),
        'attendance' => [
            'checked_in_today' => $checkedInToday,
            'last_visit' => $lastAttendanceDate ?: null,
            'monthly_visits' => $monthlyVisits,
            'current_streak' => fitsyncCurrentStreak($attendanceDates),
            'total_visits' => count($attendanceDates),
        ],
        'branch' => [
            'name' => (string) ($membership['branch_name'] ?? 'Unassigned'),
            'city' => (string) ($membership['branch_city'] ?? ''),
            'address' => (string) ($membership['branch_address'] ?? ''),
            'schedule_status' => $hours ? 'Operating hours available' : 'Hours not configured yet',
            'notice' => $firstAnnouncement['title'] ?? 'No operational notices right now.',
        ],
        'activity' => [
            [
                'label' => 'Upcoming booking',
                'value' => $nextBooking
                    ? $nextBooking['title'] . ' at ' . scheduleTime((string) $nextBooking['start_time']) . ' on ' . date('M j', strtotime((string) $nextBooking['scheduled_date']))
                    : 'No bookings yet',
                'empty' => !$nextBooking,
                'icon' => 'ti-calendar-plus',
            ],
            [
                'label' => $firstTodayClass ? 'Class today' : 'Upcoming class',
                'value' => $firstTodayClass
                    ? $firstTodayClass['title'] . ' at ' . scheduleTime((string) $firstTodayClass['start_time'])
                    : (($upcomingClasses[0]['title'] ?? null)
                        ? $upcomingClasses[0]['title'] . ' on ' . date('M j', strtotime((string) $upcomingClasses[0]['scheduled_date']))
                        : 'No classes scheduled'),
                'empty' => !$firstTodayClass && !$upcomingClasses,
                'icon' => 'ti-users-group',
            ],
        ],
        'operations' => [
            'today_class' => $firstTodayClass,
            'upcoming_booking' => $nextBooking,
            'upcoming_bookings_this_week' => count($upcomingBookingsThisWeek),
            'announcement' => $firstAnnouncement,
            'weekly_class_count' => (int) ($scheduleContext['weekly_class_count'] ?? 0),
        ],
    ];
}

function memberMembershipAlerts(?array $membership, ?array $latestMembership, int $daysRemaining, bool $hasActiveMembership): array
{
    $alerts = [];
    $source = $latestMembership ?: $membership;

    if (!$source) {
        return [[
            'level' => 'warning',
            'icon' => 'ti-id-badge-off',
            'title' => 'No membership on file',
            'body' => 'Choose a plan before checking in at the gym.',
            'action' => 'billing',
            'action_label' => 'View plans',
        ]];
    }

    $paymentStatus = (string) ($source['payment_status'] ?? '');
    $status = (string) ($source['status'] ?? '');
    $paymentRef = (string) ($source['payment_ref'] ?? '');

    if ($paymentStatus === 'failed') {
        $alerts[] = [
            'level' => 'danger',
            'icon' => 'ti-alert-triangle',
            'title' => 'Payment failed',
            'body' => 'Your last renewal was not approved. Submit a new renewal or contact the front desk.',
            'action' => 'billing',
            'action_label' => 'Renew membership',
        ];
    }

    if ($status === 'pending') {
        $alerts[] = [
            'level' => 'warning',
            'icon' => 'ti-clock-pause',
            'title' => 'Renewal pending',
            'body' => 'Your renewal request is queued for staff review.',
            'action' => 'billing',
            'action_label' => 'Payment history',
        ];
    }

    if ($paymentStatus === 'pending') {
        $alerts[] = [
            'level' => 'warning',
            'icon' => 'ti-receipt-2',
            'title' => 'Payment pending',
            'body' => 'Payment is waiting for admin confirmation.',
            'action' => 'billing',
            'action_label' => 'View payment',
        ];
    }

    if ($hasActiveMembership && $daysRemaining <= 7) {
        $alerts[] = [
            'level' => 'warning',
            'icon' => 'ti-calendar-exclamation',
            'title' => 'Membership expiring soon',
            'body' => $daysRemaining === 0 ? 'Your membership expires today.' : $daysRemaining . ' day' . ($daysRemaining === 1 ? '' : 's') . ' remaining.',
            'action' => 'billing',
            'action_label' => 'Renew now',
        ];
    }

    if ($hasActiveMembership && str_starts_with($paymentRef, 'APR-')) {
        $alerts[] = [
            'level' => 'success',
            'icon' => 'ti-circle-check',
            'title' => 'Renewal approved',
            'body' => 'Your latest approved membership is active.',
            'action' => 'billing',
            'action_label' => 'View receipt',
        ];
    }

    if (!$alerts && $hasActiveMembership) {
        $alerts[] = [
            'level' => 'success',
            'icon' => 'ti-shield-check',
            'title' => 'Membership active',
            'body' => 'You are cleared for check-in at your assigned branch.',
            'action' => 'billing',
            'action_label' => 'Details',
        ];
    }

    return array_slice($alerts, 0, 3);
}

function memberH(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
