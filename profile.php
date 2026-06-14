<?php
// ============================================================
//  FitSync — Member Profile (Enhanced)
//  profile.php
// ============================================================
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/config/auth_guard.php';
requireRole('member');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/attendance_helpers.php';
require_once __DIR__ . '/includes/membership_helpers.php';
require_once __DIR__ . '/includes/member_dashboard_helpers.php';
require_once __DIR__ . '/includes/schedule_helpers.php';

$pdo    = db();
$userId = (int) $_SESSION['user_id'];
$qrFile = 'qrcodes/member' . $userId . '.png';

// Check if account is pending approval
$isPending = isset($_SESSION['pending_approval']) && $_SESSION['pending_approval'];

if (!$isPending) {
    expireOldMemberships($pdo);
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$userRow = $stmt->fetch();

// Only load membership data if not pending approval
if ($isPending) {
    $mem = null;
    $activeMembership = null;
    $hasActiveMembership = false;
    $allMems = [];
    $myFeedbacks = [];
    $branches = [];
    $attendanceDates = [];
    $attendanceTotal = 0;
    $currentStreak = 0;
    $checkedInToday = false;
    $lastAttendanceDate = null;
    $monthlyVisits = 0;
    $daysRemaining = 0;
    $progressPct = 0;
    $scheduleContext = [];
    $memberHub = [];
    $membershipPlans = [];
    $notifications = [];
} else {
    $mem = getLatestMembership($pdo, $userId);
    $activeMembership = getActiveMembership($pdo, $userId);
    $hasActiveMembership = (bool) $activeMembership;
    if ($activeMembership) {
        $mem = $activeMembership;
    }

    $stmt = $pdo->prepare(
        'SELECT m.*, p.label AS plan_label, b.name AS branch_name
     FROM memberships m
     JOIN membership_plans p ON p.id = m.plan_id
     JOIN branches b ON b.id = m.branch_id
     WHERE m.user_id = ?
     ORDER BY m.created_at DESC, m.starts_at DESC'
    );
    $stmt->execute([$userId]);
    $allMems = $stmt->fetchAll();
    $membershipPlans = getMembershipPlans($pdo);

    $stmt = $pdo->prepare(
        'SELECT f.*, b.name AS branch_name
     FROM feedback f
     LEFT JOIN branches b ON b.id = f.branch_id
     WHERE f.user_id = ?
     ORDER BY f.created_at DESC'
    );
    $stmt->execute([$userId]);
    $myFeedbacks = $stmt->fetchAll();

    $branches = $pdo->query(
        'SELECT id, name, city, address FROM branches WHERE is_active = 1 ORDER BY name'
    )->fetchAll();

    $attendanceDates = fitsyncAttendanceDates($pdo, $userId);
    $attendanceTotal = fitsyncAttendanceTotal($pdo, $userId);
    $currentStreak = fitsyncCurrentStreak($attendanceDates);
    $checkedInToday = in_array((new DateTimeImmutable('today'))->format('Y-m-d'), $attendanceDates, true);
    $lastAttendanceDate = $attendanceDates ? end($attendanceDates) : null;
    $monthlyVisitsStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM attendance_logs
     WHERE user_id = ? AND check_in_at >= DATE_FORMAT(CURDATE(), "%Y-%m-01")'
    );
    $monthlyVisitsStmt->execute([$userId]);
    $monthlyVisits = (int) $monthlyVisitsStmt->fetchColumn();

    $daysRemaining = 0;
    $progressPct   = 0;
    if ($mem) {
        $now   = new DateTime('today');
        $start = new DateTime($mem['starts_at']);
        $end   = new DateTime($mem['ends_at']);
        $daysRemaining = $end > $now ? (int) $now->diff($end)->days : 0;
        $totalDays     = max(1, (int) $start->diff($end)->days);
        $cap           = $now < $end ? $now : $end;
        $elapsed       = $start <= $cap ? (int) $start->diff($cap)->days : 0;
        $progressPct   = min(100, (int) round(($elapsed / $totalDays) * 100));
    }
    /* ── NOTIFICATIONS ── */
    $notifications = [];

    if (!$isPending) {
        // No active membership
        if (!$hasActiveMembership) {
            $latestStatus = $mem ? $mem['status'] : null;
            $notifications[] = [
                'id'           => 'no_membership',
                'type'         => 'danger',
                'icon'         => 'ti-alert-circle',
                'title'        => $latestStatus === 'expired' ? 'Membership Expired' : 'No Active Membership',
                'body'         => $latestStatus === 'expired'
                    ? 'Your ' . htmlspecialchars($mem['plan_label']) . ' plan has expired. Renew to regain full access.'
                    : 'You don\'t have an active membership yet. Request one to unlock all features.',
                'action'       => 'billing',
                'action_label' => 'View Plans',
                'time'         => 'Now',
            ];
        } else {
            // Expiring soon (≤ 7 days)
            if ($daysRemaining > 0 && $daysRemaining <= 7) {
                $notifications[] = [
                    'id'           => 'mem_expiring',
                    'type'         => 'warning',
                    'icon'         => 'ti-clock-exclamation',
                    'title'        => 'Membership Expiring Soon',
                    'body'         => 'Your ' . htmlspecialchars($mem['plan_label']) . ' plan expires in '
                        . $daysRemaining . ' ' . ($daysRemaining === 1 ? 'day' : 'days') . '. Renew to keep access.',
                    'action'       => 'billing',
                    'action_label' => 'Renew Now',
                    'time'         => 'In ' . $daysRemaining . 'd',
                ];
            }

            // Haven't checked in today
            if (!$checkedInToday) {
                $notifications[] = [
                    'id'           => 'checkin_today',
                    'type'         => 'info',
                    'icon'         => 'ti-door-enter',
                    'title'        => 'Haven\'t Checked In Today',
                    'body'         => 'Scan your QR code at the front desk to log today\'s visit and keep your streak alive.',
                    'action'       => null,
                    'action_label' => null,
                    'time'         => 'Today',
                ];
            }

            // Streak milestones
            if ($currentStreak > 0 && in_array($currentStreak, [3, 7, 14, 21, 30, 60, 90, 100, 365])) {
                $notifications[] = [
                    'id'           => 'streak_' . $currentStreak,
                    'type'         => 'success',
                    'icon'         => 'ti-flame',
                    'title'        => $currentStreak . '-Day Streak! 🔥',
                    'body'         => 'You\'ve trained ' . $currentStreak . ' days in a row. Outstanding consistency — keep the momentum!',
                    'action'       => null,
                    'action_label' => null,
                    'time'         => 'Today',
                ];
            }

            // Visit milestones (exact match only, so it shows once per milestone)
            foreach ([10, 25, 50, 100, 200, 500] as $milestone) {
                if ($attendanceTotal === $milestone) {
                    $notifications[] = [
                        'id'           => 'milestone_' . $milestone,
                        'type'         => 'success',
                        'icon'         => 'ti-trophy',
                        'title'        => number_format($milestone) . ' Visits — Milestone Reached!',
                        'body'         => 'You\'ve completed ' . number_format($milestone) . ' gym visits. That\'s serious dedication!',
                        'action'       => null,
                        'action_label' => null,
                        'time'         => 'Today',
                    ];
                    break;
                }
            }

            // No visits this calendar month yet
            if ($monthlyVisits === 0) {
                $notifications[] = [
                    'id'           => 'monthly_zero_' . date('Y-m'),
                    'type'         => 'info',
                    'icon'         => 'ti-calendar-stats',
                    'title'        => 'No Visits in ' . date('F') . ' Yet',
                    'body'         => 'Your membership is active but you haven\'t visited the gym this month. Don\'t let it go to waste!',
                    'action'       => null,
                    'action_label' => null,
                    'time'         => date('M Y'),
                ];
            }

            // Welcome message for brand-new active members
            if ($attendanceTotal === 0) {
                $notifications[] = [
                    'id'           => 'welcome_first',
                    'type'         => 'success',
                    'icon'         => 'ti-confetti',
                    'title'        => 'Welcome to FitSync!',
                    'body'         => 'Your membership is active. Use the QR code below to check in for your very first session.',
                    'action'       => null,
                    'action_label' => null,
                    'time'         => 'New',
                ];
            }
        }
    }
}

$scheduleContext = memberScheduleContext($pdo, $mem, $userId);

/* ── INJECT ANNOUNCEMENTS INTO NOTIFICATIONS ── */
if (!$isPending) {
    foreach (($scheduleContext['announcements'] ?? []) as $ann) {
        $branchLabel = $ann['branch_name'] ? htmlspecialchars((string) $ann['branch_name']) : 'All Branches';
        $notifications[] = [
            'id'           => 'ann_' . (int) $ann['id'],
            'type'         => 'info',
            'icon'         => 'ti-speakerphone',
            'title'        => htmlspecialchars((string) $ann['title']),
            'body'         => htmlspecialchars((string) $ann['body']) . ' — ' . $branchLabel,
            'action'       => null,
            'action_label' => null,
            'time'         => date('M j', strtotime((string) $ann['starts_at'])),
        ];
    }
}

$memberHub = memberDashboardData($mem, $allMems, $attendanceDates, $monthlyVisits, $daysRemaining, $hasActiveMembership, $scheduleContext);

$initials = strtoupper(
    substr($userRow['first_name'] ?? 'U', 0, 1) .
        substr($userRow['last_name']  ?? 'U', 0, 1)
);
$fullName = htmlspecialchars(trim(($userRow['first_name'] ?? '') . ' ' . ($userRow['last_name'] ?? '')));
$hour     = (int) (new DateTime())->format('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ── SHOP DATA ─────────────────────────────────────────────
$shopCartCount = 0;
try {
    $cartCountStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?');
    $cartCountStmt->execute([$userId]);
    $shopCartCount = (int)$cartCountStmt->fetchColumn();
} catch (Throwable) {}

function payLabel(string $m): string
{
    return match ($m) {
        'credit_card'   => 'Credit Card',
        'debit_card'    => 'Debit Card',
        'gcash'         => 'GCash',
        'maya'          => 'Maya',
        'bank_transfer' => 'Bank Transfer',
        'cash'          => 'Cash / Walk-in',
        default         => ucfirst($m),
    };
}

// Workout programs data
$workoutPrograms = [
    [
        'id' => 'ppl',
        'name' => 'Push / Pull / Legs',
        'tag' => 'Hypertrophy',
        'days' => 6,
        'level' => 'Intermediate',
        'color' => '#cc1a1a',
        'icon' => 'ti-barbell',
        'desc' => 'Classic 6-day split targeting push muscles, pull muscles, and legs twice per week for maximum volume.',
        'schedule' => [
            ['day' => 'Mon', 'focus' => 'Push', 'exercises' => ['Bench Press 4×8', 'Overhead Press 3×10', 'Incline DB Press 3×12', 'Lateral Raises 4×15', 'Tricep Pushdown 3×12']],
            ['day' => 'Tue', 'focus' => 'Pull', 'exercises' => ['Deadlift 4×5', 'Pull-ups 4×8', 'Barbell Row 3×10', 'Face Pulls 3×15', 'Bicep Curls 3×12']],
            ['day' => 'Wed', 'focus' => 'Legs', 'exercises' => ['Squat 4×8', 'Romanian Deadlift 3×10', 'Leg Press 3×12', 'Leg Curl 3×12', 'Calf Raises 4×15']],
            ['day' => 'Thu', 'focus' => 'Push', 'exercises' => ['Overhead Press 4×6', 'Incline Bench 4×10', 'Cable Fly 3×15', 'Dips 3×12', 'Skull Crushers 3×12']],
            ['day' => 'Fri', 'focus' => 'Pull', 'exercises' => ['Weighted Pull-ups 4×6', 'Seated Row 3×10', 'Single-arm Row 3×12', 'Lat Pulldown 3×12', 'Hammer Curls 3×12']],
            ['day' => 'Sat', 'focus' => 'Legs', 'exercises' => ['Front Squat 4×6', 'Bulgarian Split Squat 3×10', 'Hack Squat 3×12', 'Leg Extension 3×15', 'Standing Calf Raise 4×20']],
            ['day' => 'Sun', 'focus' => 'Rest', 'exercises' => []],
        ]
    ],
    [
        'id' => 'ul',
        'name' => 'Upper / Lower',
        'tag' => 'Strength',
        'days' => 4,
        'level' => 'Beginner–Intermediate',
        'color' => '#1a6fcc',
        'icon' => 'ti-arrows-split-2',
        'desc' => '4-day split alternating upper and lower body. Great for strength and size with adequate recovery.',
        'schedule' => [
            ['day' => 'Mon', 'focus' => 'Upper A', 'exercises' => ['Bench Press 4×5', 'Barbell Row 4×5', 'Overhead Press 3×8', 'Pull-ups 3×8', 'Curls 2×12']],
            ['day' => 'Tue', 'focus' => 'Lower A', 'exercises' => ['Squat 4×5', 'Romanian Deadlift 3×8', 'Leg Press 3×10', 'Leg Curl 3×10', 'Calf Raises 3×15']],
            ['day' => 'Wed', 'focus' => 'Rest', 'exercises' => []],
            ['day' => 'Thu', 'focus' => 'Upper B', 'exercises' => ['Incline Press 4×8', 'Cable Row 4×8', 'DB Shoulder Press 3×10', 'Lat Pulldown 3×10', 'Tricep Dips 3×12']],
            ['day' => 'Fri', 'focus' => 'Lower B', 'exercises' => ['Deadlift 4×4', 'Front Squat 3×6', 'Walking Lunges 3×12', 'Leg Extension 3×15', 'Seated Calf 3×15']],
            ['day' => 'Sat', 'focus' => 'Rest', 'exercises' => []],
            ['day' => 'Sun', 'focus' => 'Rest', 'exercises' => []],
        ]
    ],
    [
        'id' => 'fullbody',
        'name' => 'Full Body',
        'tag' => 'General Fitness',
        'days' => 3,
        'level' => 'Beginner',
        'color' => '#1acc6f',
        'icon' => 'ti-run',
        'desc' => '3-day full-body training. Ideal for beginners or those with limited time. High frequency per muscle group.',
        'schedule' => [
            ['day' => 'Mon', 'focus' => 'Full Body A', 'exercises' => ['Squat 3×8', 'Bench Press 3×8', 'Barbell Row 3×8', 'Overhead Press 2×10', 'Plank 3×45s']],
            ['day' => 'Tue', 'focus' => 'Rest', 'exercises' => []],
            ['day' => 'Wed', 'focus' => 'Full Body B', 'exercises' => ['Deadlift 3×5', 'Incline Press 3×10', 'Pull-ups 3×8', 'Goblet Squat 3×12', 'Ab Wheel 3×10']],
            ['day' => 'Thu', 'focus' => 'Rest', 'exercises' => []],
            ['day' => 'Fri', 'focus' => 'Full Body C', 'exercises' => ['Front Squat 3×8', 'Dips 3×10', 'Cable Row 3×10', 'DB Press 3×12', 'Farmers Walk 3×40m']],
            ['day' => 'Sat', 'focus' => 'Rest', 'exercises' => []],
            ['day' => 'Sun', 'focus' => 'Rest', 'exercises' => []],
        ]
    ],
    [
        'id' => 'hiit',
        'name' => 'HIIT & Cardio',
        'tag' => 'Fat Loss',
        'days' => 5,
        'level' => 'All Levels',
        'color' => '#cc7a1a',
        'icon' => 'ti-flame',
        'desc' => 'High-intensity interval training combined with steady-state cardio for maximum calorie burn and conditioning.',
        'schedule' => [
            ['day' => 'Mon', 'focus' => 'HIIT A', 'exercises' => ['Burpees 4×15', 'Jump Squats 4×20', 'Mountain Climbers 4×30s', 'Box Jumps 3×12', 'Sprint Intervals 6×30s']],
            ['day' => 'Tue', 'focus' => 'Cardio', 'exercises' => ['Treadmill 30min steady', 'Incline Walk 15min', 'Rowing Machine 15min']],
            ['day' => 'Wed', 'focus' => 'Rest', 'exercises' => []],
            ['day' => 'Thu', 'focus' => 'HIIT B', 'exercises' => ['Kettlebell Swings 4×20', 'Battle Ropes 4×30s', 'Jump Rope 5×1min', 'Sled Push 4×20m', 'Assault Bike 6×20s']],
            ['day' => 'Fri', 'focus' => 'Cardio', 'exercises' => ['Bike 20min steady', 'Stairmaster 20min', 'Core Circuit 3×12']],
            ['day' => 'Sat', 'focus' => 'Active Recovery', 'exercises' => ['Yoga / Stretching 30min', 'Light Walk 20min']],
            ['day' => 'Sun', 'focus' => 'Rest', 'exercises' => []],
        ]
    ],
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>FitSync — My Profile</title>
    <link rel="icon" href="assets/FitSYNC Emblem Light.svg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <script>
        /* Apply saved theme & fix logos before first paint */
        (function() {
            var saved = localStorage.getItem('fs-theme');
            if (saved) document.documentElement.setAttribute('data-bs-theme', saved);
            document.addEventListener('DOMContentLoaded', function() {
                var isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
                document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(function(logo) {
                    logo.src = isLight ? logo.dataset.logoLight : logo.dataset.logoDark;
                });
            });
        })();
    </script>
    <style>
        :root,
        [data-bs-theme="dark"] {
            --fs-red: #cc1a1a;
            --fs-red-hover: #a01212;
            --fs-red-glow: rgba(204, 26, 26, .28);
            --fs-red-soft: rgba(204, 26, 26, .12);
            --sidebar-w: 270px;
            --sidebar-bg: #0d0d0d;
            --sidebar-border: rgba(255, 255, 255, .07);
            --card-bg: #111111;
            --card-border: rgba(255, 255, 255, .07);
            --page-bg: #0a0a0a;
            --input-bg: rgba(255, 255, 255, .05);
            --input-border: rgba(255, 255, 255, .08);
            --input-color: #fff;
            --input-ph: rgba(255, 255, 255, .3);
            --text-primary: #fff;
            --text-muted: rgba(255, 255, 255, .45);
            --text-dimmed: rgba(255, 255, 255, .25);
            --row-hover: rgba(255, 255, 255, .025);
            --th-bg: rgba(255, 255, 255, .03);
            --td-border: rgba(255, 255, 255, .04);
        }

        [data-bs-theme="light"] {
            --sidebar-bg: #fff;
            --sidebar-border: rgba(0, 0, 0, .08);
            --card-bg: #fff;
            --card-border: rgba(0, 0, 0, .07);
            --page-bg: #f4f2ef;
            --input-bg: rgba(0, 0, 0, .04);
            --input-border: rgba(0, 0, 0, .1);
            --input-color: #111;
            --input-ph: rgba(0, 0, 0, .3);
            --text-primary: #111;
            --text-muted: rgba(0, 0, 0, .45);
            --text-dimmed: rgba(0, 0, 0, .25);
            --row-hover: rgba(0, 0, 0, .02);
            --th-bg: rgba(0, 0, 0, .03);
            --td-border: rgba(0, 0, 0, .05);
        }

        * {
            font-family: 'Outfit', system-ui, sans-serif;
            box-sizing: border-box
        }

        body {
            background: var(--page-bg);
            overflow-x: hidden;
            transition: background .25s
        }

        /* ── SIDEBAR ── */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-w);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            z-index: 200;
            transition: transform .3s cubic-bezier(.25, .46, .45, .94), background .25s;
            overflow-y: auto;
            pointer-events: auto;
        }

        .sb-header {
            padding: 1.5rem 1.35rem 1rem;
            border-bottom: 1px solid var(--sidebar-border);
            flex-shrink: 0
        }

        .sb-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            text-decoration: none;
            margin-bottom: 1.35rem
        }

        .brand-text .fit {
            font-size: 1.1rem;
            font-weight: 900;
            letter-spacing: 1px;
            color: var(--text-primary)
        }

        .brand-text .sync {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--fs-red);
            letter-spacing: 1px
        }

        .sb-avatar-card {
            background: linear-gradient(135deg, var(--fs-red) 0%, #8a1010 100%);
            border-radius: 16px;
            padding: 1.1rem;
            position: relative;
            overflow: hidden;
        }

        .sb-avatar-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08)
        }

        .sb-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .2);
            border: 2px solid rgba(255, 255, 255, .35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: .65rem;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .sb-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%
        }

        .sb-member-name {
            font-size: .95rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            position: relative;
            z-index: 1
        }

        .sb-member-plan {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .7);
            margin-top: .2rem;
            position: relative;
            z-index: 1
        }

        .sb-member-badge {
            position: absolute;
            top: .75rem;
            right: .75rem;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            font-size: .58rem;
            font-weight: 700;
            padding: .18rem .55rem;
            border-radius: 50px;
            letter-spacing: .5px;
            text-transform: uppercase;
            z-index: 1
        }

        .sb-nav {
            padding: .75rem;
            flex: 1
        }

        .sb-nav-label {
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-dimmed);
            padding: .5rem .65rem .35rem;
            margin-top: .25rem
        }

        .sb-nav-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem .85rem;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-muted);
            font-size: .85rem;
            font-weight: 600;
            transition: background .18s, color .18s;
            margin-bottom: 2px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .sb-nav-item i {
            font-size: 1.05rem;
            flex-shrink: 0
        }

        .sb-nav-item:hover {
            background: var(--fs-red-soft);
            color: var(--text-primary)
        }

        .sb-nav-item.active {
            background: var(--fs-red-soft);
            color: var(--text-primary)
        }

        .sb-nav-item.active i {
            color: var(--fs-red)
        }

        .sb-nav-item:disabled {
            opacity: .35;
            cursor: not-allowed;
            pointer-events: none;
        }

        .sb-cart-badge {
            margin-left: auto;
            background: var(--fs-red);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .sb-footer {
            padding: .85rem .75rem 1.25rem;
            border-top: 1px solid var(--sidebar-border);
            flex-shrink: 0;
            position: relative;
            z-index: 201;
            pointer-events: auto;
        }

        .sb-theme-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .5rem .85rem;
            margin-bottom: .35rem
        }

        .sb-theme-label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: .5rem
        }

        .theme-pill {
            width: 44px;
            height: 24px;
            border-radius: 50px;
            border: 1px solid var(--sidebar-border);
            background: var(--input-bg);
            position: relative;
            cursor: pointer;
            transition: background .3s;
            padding: 0;
            flex-shrink: 0
        }

        .theme-pill:disabled {
            opacity: .35;
            cursor: not-allowed;
            pointer-events: none;
        }

        .theme-pill-knob {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--fs-red);
            transition: transform .3s
        }

        [data-bs-theme="light"] .theme-pill-knob {
            transform: translateX(20px)
        }

        .sb-logout {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem .85rem;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            color: rgba(255, 80, 80, .65);
            font-size: .85rem;
            font-weight: 600;
            transition: background .18s, color .18s;
            border: none;
            background: none;
            width: 100%
        }

        .sb-logout:hover {
            background: rgba(204, 26, 26, .12);
            color: #ff6b6b
        }

        /* ── MAIN ── */
        .main-content {
            position: relative;
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding: 2rem 2rem 3rem;
            transition: margin .3s
        }

        /* ── PENDING OVERLAY (blurry backdrop) ── */
        .pending-overlay {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            left: var(--sidebar-w);
            z-index: 150;
            background: rgba(10, 10, 10, .55);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            pointer-events: none;
            /* ← CHANGE THIS */
        }

        @media (max-width: 991.98px) {
            .pending-overlay {
                left: 0;
            }
        }

        /* ── PENDING NOTICE (fixed top-center, above overlay) ── */
        .pending-notice {
            position: fixed;
            top: 2rem;
            left: 50%;
            transform: translateX(-50%);
            /* on desktop, account for sidebar width so it centres in the content area */
            margin-left: calc(var(--sidebar-w) / 2);
            z-index: 190;
            width: calc(100% - var(--sidebar-w) - 3rem);
            max-width: 560px;
            min-width: 280px;
            pointer-events: none;
            /* let clicks pass through to sidebar */
        }

        .pending-notice-card {
            background: rgba(17, 17, 17, .98);
            border: 1px solid rgba(255, 193, 7, .3);
            box-shadow: 0 8px 40px rgba(255, 193, 7, .08), 0 4px 24px rgba(0, 0, 0, .45);
            border-radius: 20px;
            padding: 1.35rem 1.6rem;
            color: var(--text-primary);
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            pointer-events: auto;
        }

        [data-bs-theme="light"] .pending-notice-card {
            background: #fffdf0;
            border-color: rgba(214, 161, 0, .35);
            box-shadow: 0 8px 40px rgba(214, 161, 0, .1), 0 4px 24px rgba(0, 0, 0, .12);
        }

        .pending-notice-icon {
            width: 40px;
            height: 40px;
            border-radius: 11px;
            background: rgba(255, 193, 7, .12);
            border: 1px solid rgba(255, 193, 7, .22);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d6a100;
            font-size: 1.25rem;
            flex-shrink: 0;
            margin-top: .1rem;
        }

        .pending-notice-body h2 {
            margin: 0 0 .3rem;
            font-size: 1rem;
            font-weight: 900;
            color: var(--text-primary);
        }

        .pending-notice-body p {
            margin: 0;
            color: var(--text-muted);
            font-size: .82rem;
            line-height: 1.6;
        }

        .pending-notice-body p+p {
            margin-top: .4rem;
        }

        /* On mobile: full-width, no sidebar offset */
        @media (max-width: 991.98px) {
            .pending-notice {
                left: 1rem;
                right: 1rem;
                transform: none;
                margin-left: 0;
                width: auto;
                max-width: none;
                top: 5rem;
                /* clears the hamburger row height + gap */
            }
        }

        @media (max-width: 575.98px) {
            .pending-notice-card {
                padding: 1rem 1.1rem;
                gap: .75rem;
            }

            .pending-notice-icon {
                width: 34px;
                height: 34px;
                font-size: 1rem;
                border-radius: 9px;
            }

            .pending-notice-body h2 {
                font-size: .9rem;
            }

            .pending-notice-body p {
                font-size: .78rem;
            }
        }

        /* ── HERO DASHBOARD HEADER ── */
        .dash-hero {
            background: linear-gradient(135deg, #1a0505 0%, #111 60%, #0d0d0d 100%);
            border: 1px solid rgba(204, 26, 26, .2);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        [data-bs-theme="light"] .dash-hero {
            background: linear-gradient(135deg, #fff5f5 0%, #fff 60%, #fafafa 100%);
        }

        .dash-hero::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: rgba(204, 26, 26, .06);
            pointer-events: none;
        }

        .dash-hero::after {
            content: '';
            position: absolute;
            bottom: -40px;
            left: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(204, 26, 26, .04);
            pointer-events: none;
        }

        .dash-hero-greeting {
            font-size: clamp(1.4rem, 3vw, 2rem);
            font-weight: 900;
            letter-spacing: -.5px;
            color: var(--text-primary);
            margin-bottom: .3rem;
        }

        .dash-hero-sub {
            font-size: .88rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem
        }

        .dash-hero-stats {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .dash-mini-stat {
            border-left: 2px solid rgba(204, 26, 26, .3);
            padding-left: .85rem;
        }

        .dash-mini-stat-val {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1
        }

        .dash-mini-stat-lbl {
            font-size: .62rem;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text-muted);
            margin-top: .15rem
        }

        .dash-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: var(--fs-red-soft);
            border: 1px solid rgba(204, 26, 26, .25);
            color: var(--fs-red);
            font-size: .72rem;
            font-weight: 700;
            padding: .3rem .85rem;
            border-radius: 50px;
            margin-bottom: 1rem;
            letter-spacing: .3px;
        }

        .dash-hero-badge span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--fs-red);
            display: inline-block
        }

        /* ── PAGE SECTIONS ── */
        .page-section {
            display: none
        }

        .page-section.active {
            display: block
        }

        /* ── CARDS ── */
        .fs-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.5rem;
            transition: border-color .2s
        }

        /* ── STAT CARDS ── */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 1.25rem 1.35rem;
            position: relative;
            overflow: hidden;
            transition: transform .2s, border-color .2s
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: rgba(204, 26, 26, .3)
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -18px;
            right: -18px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--fs-red-soft)
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--fs-red-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--fs-red);
            font-size: 1.1rem;
            margin-bottom: .9rem
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -1px;
            line-height: 1;
            margin-bottom: .2rem;
            color: var(--text-primary)
        }

        .stat-label {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--text-muted)
        }

        .stat-sub {
            font-size: .72rem;
            color: var(--text-muted);
            margin-top: .3rem
        }

        /* ── MEMBERSHIP CARD ── */
        .membership-card {
            background: linear-gradient(135deg, #1a0505 0%, #0d0d0d 50%, #1a0808 100%);
            border: 1px solid rgba(204, 26, 26, .25);
            border-radius: 20px;
            padding: 1.75rem;
            position: relative;
            overflow: hidden
        }

        [data-bs-theme="light"] .membership-card {
            background: linear-gradient(135deg, #fff5f5 0%, #ffffff 50%, #fff0f0 100%)
        }

        .membership-card::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(204, 26, 26, .07)
        }

        .mem-tag {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
            color: var(--fs-red);
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .4rem
        }

        .mem-tag span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--fs-red);
            display: inline-block
        }

        .mem-plan-name {
            font-size: 1.6rem;
            font-weight: 900;
            letter-spacing: -.5px;
            margin-bottom: .25rem;
            color: var(--text-primary)
        }

        .mem-dates {
            font-size: .78rem;
            color: var(--text-muted);
            margin-bottom: 1.25rem
        }

        .mem-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: .72rem;
            margin-bottom: .45rem;
            color: var(--text-muted)
        }

        .mem-progress-label span:last-child {
            font-weight: 700;
            color: var(--fs-red)
        }

        .progress-track {
            height: 5px;
            border-radius: 3px;
            background: var(--fs-red-soft);
            overflow: hidden
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: var(--fs-red);
            transition: width 1s cubic-bezier(.25, .46, .45, .94)
        }

        .mem-pills {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: 1.25rem
        }

        .mem-pill {
            display: flex;
            align-items: center;
            gap: .3rem;
            background: var(--fs-red-soft);
            border: 1px solid rgba(204, 26, 26, .2);
            color: var(--text-primary);
            font-size: .72rem;
            font-weight: 600;
            padding: .28rem .75rem;
            border-radius: 50px
        }

        .mem-pill i {
            color: var(--fs-red);
            font-size: .85rem
        }

        /* ── STATUS BADGE ── */
        .status-badge {
            font-size: .65rem;
            font-weight: 700;
            padding: .2rem .65rem;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: .4px
        }

        .status-badge.active {
            background: rgba(76, 175, 135, .12);
            color: #4caf87;
            border: 1px solid rgba(76, 175, 135, .25)
        }

        .status-badge.pending {
            background: rgba(255, 193, 7, .12);
            color: #d6a100;
            border: 1px solid rgba(255, 193, 7, .25)
        }

        .status-badge.paid {
            background: rgba(76, 175, 135, .12);
            color: #4caf87;
            border: 1px solid rgba(76, 175, 135, .25)
        }

        .status-badge.failed,
        .status-badge.refunded {
            background: rgba(220, 53, 69, .12);
            color: #e05656;
            border: 1px solid rgba(220, 53, 69, .25)
        }

        .status-badge.expired {
            background: rgba(150, 150, 150, .12);
            color: #888;
            border: 1px solid rgba(150, 150, 150, .25)
        }

        .status-badge.cancelled {
            background: rgba(220, 53, 69, .12);
            color: #e05656;
            border: 1px solid rgba(220, 53, 69, .25)
        }

        /* ── FORM INPUTS ── */
        .fs-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: .35rem
        }

        .fs-input {
            background: var(--input-bg) !important;
            border: 1px solid var(--input-border) !important;
            color: var(--input-color) !important;
            border-radius: 12px !important;
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            padding: .65rem 1rem;
            transition: border-color .2s
        }

        select.fs-input {
            appearance: none;
            padding-right: 2.4rem !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%238f8f8f' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: right .85rem center !important;
            background-size: 14px 14px !important
        }

        .fs-input:focus {
            border-color: rgba(204, 26, 26, .5) !important;
            box-shadow: 0 0 0 3px rgba(204, 26, 26, .1) !important;
            outline: none
        }

        .fs-input::placeholder {
            color: var(--input-ph) !important
        }

        .fs-input:disabled,
        .fs-input[readonly] {
            opacity: .6;
            cursor: not-allowed
        }

        .fs-input option {
            background: var(--card-bg);
            color: var(--input-color)
        }

        /* ── GYM CALENDAR ── */
        .gym-calendar-wrap {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 1.5rem;
        }

        .cal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem
        }

        .cal-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary)
        }

        .cal-nav {
            display: flex;
            gap: .4rem
        }

        .cal-nav-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--card-border);
            background: var(--input-bg);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            transition: all .18s
        }

        .cal-nav-btn:hover {
            border-color: var(--fs-red);
            color: var(--fs-red)
        }

        .cal-days-header {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 4px;
            margin-bottom: 8px
        }

        .cal-month-name {
            font-size: .75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-align: center;
        }

        .cal-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
            width: 100%;
        }

        .cal-cell {
            aspect-ratio: 1 / 1;
            min-height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .82rem;
            font-weight: 700;
            cursor: pointer;
            transition: all .15s;
            color: var(--text-muted);
            background: var(--input-bg);
            border: 1px solid transparent;
            position: relative;
        }

        .cal-cell:hover:not(.cal-empty):not(.cal-future) {
            border-color: var(--fs-red);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .cal-cell.cal-empty {
            cursor: default;
            opacity: 0;
            background: transparent;
            border-color: transparent
        }

        .cal-cell.cal-future {
            cursor: default;
            opacity: .35
        }

        .cal-cell.cal-today {
            border-color: var(--fs-red);
        }

        .cal-cell.cal-attended {
            background: var(--fs-red);
            color: #fff;
        }

        .cal-cell.cal-attended.cal-today {
            box-shadow: 0 0 0 2px rgba(255, 255, 255, .35);
        }

        .gym-calendar-wrap {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 1.5rem;
            overflow-x: hidden
        }

        @media (max-width: 767.98px) {
            .cal-cell {
                min-height: 36px;
                font-size: .75rem;
            }

            .cal-grid {
                gap: 6px;
            }

            .cal-days-header {
                gap: 3px;
            }
        }

        .cal-streak-bar {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-top: 1.25rem;
            padding: 1rem 1.1rem;
            background: var(--input-bg);
            border-radius: 12px;
            border: 1px solid var(--card-border)
        }

        .cal-streak-num {
            font-size: 1.6rem;
            font-weight: 900;
            color: var(--fs-red);
            line-height: 1
        }

        .cal-streak-lbl {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text-muted)
        }

        .cal-legend {
            display: flex;
            gap: 1rem;
            margin-top: .85rem;
            flex-wrap: wrap
        }

        .cal-legend-item {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .7rem;
            color: var(--text-muted)
        }

        .cal-legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 4px
        }

        /* ── PROGRAM CARDS ── */
        .program-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 18px;
            overflow: hidden;
            transition: transform .2s, border-color .2s, box-shadow .2s;
            cursor: pointer;
        }

        .program-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, .3)
        }

        .program-card.selected {
            border-color: var(--prog-color, var(--fs-red)) !important;
        }

        .program-card-bar {
            height: 4px;
            width: 100%
        }

        .program-card-body {
            padding: 1.25rem
        }

        .program-tag {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: .2rem .6rem;
            border-radius: 50px;
            margin-bottom: .65rem;
            display: inline-block
        }

        .program-name {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: -.3px;
            color: var(--text-primary);
            margin-bottom: .35rem
        }

        .program-meta {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap
        }

        .program-meta-item {
            font-size: .7rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: .25rem
        }

        .program-desc {
            font-size: .8rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-top: .65rem
        }

        /* Program detail */
        .prog-detail {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            overflow: hidden
        }

        .prog-detail-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--card-border);
        }

        .prog-schedule-grid {
            display: flex;
            flex-direction: column;
            gap: 0
        }

        .prog-day-row {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--td-border);
            transition: background .15s;
        }

        .prog-day-row:last-child {
            border-bottom: none
        }

        .prog-day-row:hover {
            background: var(--row-hover)
        }

        .prog-day-label {
            width: 36px;
            flex-shrink: 0;
            text-align: center
        }

        .prog-day-name {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text-dimmed)
        }

        .prog-day-focus {
            font-size: .8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-top: .1rem
        }

        .prog-day-rest {
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-dimmed);
            margin-top: .1rem
        }

        .prog-exercises {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            flex: 1
        }

        .prog-ex-chip {
            font-size: .72rem;
            font-weight: 600;
            padding: .2rem .65rem;
            border-radius: 50px;
            background: var(--input-bg);
            color: var(--text-muted);
            border: 1px solid var(--card-border)
        }

        /* ── STARS ── */
        .star-picker {
            display: flex;
            gap: .3rem
        }

        .star-picker .star {
            font-size: 1.6rem;
            cursor: pointer;
            color: var(--card-border);
            transition: color .15s;
            line-height: 1
        }

        .star-picker .star.active,
        .star-picker .star:hover {
            color: var(--fs-red)
        }

        /* ── TABLE ── */
        .fs-table-wrap {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            overflow-x: auto
        }

        .fs-table {
            margin: 0
        }

        .fs-table thead th {
            background: var(--th-bg);
            border-bottom: 1px solid var(--card-border);
            color: var(--text-muted);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: .8rem 1.1rem;
            white-space: nowrap
        }

        .fs-table tbody td {
            padding: .85rem 1.1rem;
            border-bottom: 1px solid var(--td-border);
            color: var(--text-primary);
            font-size: .85rem;
            vertical-align: middle
        }

        .fs-table tbody tr:last-child td {
            border-bottom: none
        }

        .fs-table tbody tr:hover td {
            background: var(--row-hover)
        }

        /* ── FEEDBACK CARD ── */
        .feedback-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 1.15rem 1.3rem
        }

        .feedback-stars {
            color: var(--fs-red);
            font-size: .9rem;
            letter-spacing: 1px
        }

        /* ── ALERT ── */
        .fs-alert {
            border-radius: 12px;
            font-size: .85rem;
            padding: .7rem 1rem;
            display: none
        }

        /* ── SIDEBAR OVERLAY ── */
        .sb-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            z-index: 199
        }

        /* ── BTN ── */
        .btn-fs {
            background: var(--fs-red);
            border: none;
            color: #fff;
            font-weight: 700;
            letter-spacing: .3px
        }

        .btn-fs:hover {
            background: var(--fs-red-hover);
            color: #fff
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted)
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: .75rem;
            display: block;
            color: var(--text-dimmed)
        }

        .empty-state p {
            font-size: .85rem;
            margin: 0
        }

        .section-kicker {
            font-size: .72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .7px;
            color: var(--fs-red);
            margin-bottom: .4rem
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: .25rem
        }

        .section-subtitle {
            font-size: .85rem;
            color: var(--text-muted);
            line-height: 1.6
        }

        .member-hub-card {
            border-color: rgba(204, 26, 26, .18)
        }

        .quick-action-row {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap
        }

        .quick-action-grid {
            display: grid;
            gap: .5rem
        }

        .member-alert-list {
            display: grid;
            gap: .65rem
        }

        .member-alert {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .85rem 1rem;
            border-radius: 14px;
            border: 1px solid var(--card-border);
            background: var(--input-bg)
        }

        .member-alert i {
            font-size: 1.15rem;
            color: var(--fs-red);
            flex-shrink: 0
        }

        .member-alert-title {
            font-size: .86rem;
            font-weight: 800;
            color: var(--text-primary)
        }

        .member-alert-body {
            font-size: .76rem;
            color: var(--text-muted);
            line-height: 1.45
        }

        .member-alert-success {
            border-color: rgba(76, 175, 135, .22)
        }

        .member-alert-success i {
            color: #4caf87
        }

        .member-alert-warning {
            border-color: rgba(255, 193, 7, .28)
        }

        .member-alert-warning i {
            color: #d6a100
        }

        .member-alert-danger {
            border-color: rgba(220, 53, 69, .28)
        }

        .member-alert-danger i {
            color: #e05656
        }

        .hub-metric {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            background: var(--input-bg)
        }

        .hub-metric-label,
        .detail-label {
            font-size: .68rem;
            color: var(--text-dimmed);
            text-transform: uppercase;
            letter-spacing: .55px;
            font-weight: 800
        }

        .hub-metric-value,
        .detail-value {
            font-size: .9rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-top: .15rem
        }

        .hub-metric-sub,
        .detail-muted {
            font-size: .74rem;
            color: var(--text-muted);
            margin-top: .15rem
        }

        .hub-soft-panel {
            padding: 1rem;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            background: var(--input-bg);
            font-size: .82rem;
            color: var(--text-muted)
        }

        .hub-soft-panel strong {
            color: var(--text-primary);
            font-size: .8rem;
            text-align: right
        }

        .empty-row {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .65rem .75rem;
            border: 1px dashed var(--card-border);
            border-radius: 12px
        }

        .empty-row i {
            color: var(--text-dimmed);
            font-size: 1.05rem
        }

        .empty-row-label {
            color: var(--text-primary);
            font-size: .82rem;
            font-weight: 700
        }

        .empty-row-copy {
            color: var(--text-muted);
            font-size: .76rem
        }

        .empty-inline {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .8rem .95rem;
            border-radius: 12px;
            background: var(--input-bg);
            border: 1px dashed var(--card-border);
            color: var(--text-muted);
            font-size: .82rem
        }

        .empty-inline i {
            color: var(--text-dimmed);
            font-size: 1.1rem
        }

        .schedule-placeholder-list {
            display: grid;
            gap: .75rem
        }

        .schedule-placeholder-row {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .75rem;
            border-radius: 12px;
            background: var(--input-bg);
            border: 1px solid var(--card-border)
        }

        .schedule-placeholder-row i {
            color: var(--fs-red);
            font-size: 1.1rem;
            margin-top: .1rem
        }

        /* ── NO MEMBERSHIP NOTICE ── */
        .no-mem-card {
            background: var(--card-bg);
            border: 1px dashed var(--card-border);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center
        }

        .no-mem-card i {
            font-size: 2.5rem;
            color: var(--text-dimmed);
            margin-bottom: 1rem;
            display: block
        }

        /* ── AVATAR UPLOAD ── */
        .avatar-upload-wrap {
            position: relative;
            display: inline-block;
            cursor: pointer
        }

        .avatar-upload-wrap:hover .avatar-upload-overlay {
            opacity: 1
        }

        .avatar-upload-overlay {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: rgba(0, 0, 0, .55);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity .2s;
            color: #fff;
            font-size: 1.1rem;
        }

        .profile-avatar-lg {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--fs-red), #7a0f0f);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 800;
            color: #fff;
            overflow: hidden;
            border: 3px solid var(--card-border);
        }

        .profile-avatar-lg img {
            width: 100%;
            height: 100%;
            object-fit: cover
        }

        /* ── HAMBURGER ── */
        .hamburger {
            display: none;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-primary);
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(calc(-1 * var(--sidebar-w)))
            }

            .sidebar.open {
                transform: translateX(0)
            }

            .main-content {
                margin-left: 0
            }

            .sb-overlay.active {
                display: block
            }

            .hamburger {
                display: flex
            }
        }

        @media (max-width: 575.98px) {
            .main-content {
                padding: 1.25rem 1rem 2rem
            }

            .dash-hero {
                padding: 1.5rem
            }

            .page-tab {
                padding: .6rem .9rem;
                font-size: .78rem
            }
        }

        /* Plan card highlights */
        .plan-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1rem;
            transition: box-shadow .18s, border-color .18s
        }

        .plan-card.active-plan {
            border-color: var(--fs-red);
            box-shadow: 0 8px 30px var(--fs-red-glow);
        }

        .plan-badge {
            position: absolute;
            top: 10px;
            right: 12px;
            background: var(--fs-red);
            color: #fff;
            font-size: .65rem;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-weight: 700
        }

        /* ─── QR CHECK-IN SYSTEM ─── */
        .hero-qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .55rem;
            flex-shrink: 0;
            align-self: center;
        }

        .hero-qr-box {
            width: 150px;
            height: 150px;
            background: #fff;
            border-radius: 20px;
            padding: 12px;
            cursor: pointer;
            border: 2px solid rgba(204, 26, 26, .4);
            box-shadow: 0 8px 30px rgba(0, 0, 0, .4);
            transition: transform .22s, box-shadow .22s;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative
        }

        .hero-qr-box:hover {
            transform: translateY(-3px) scale(1.04);
            box-shadow: 0 14px 42px rgba(0, 0, 0, .45), 0 0 0 4px rgba(204, 26, 26, .2)
        }

        .hero-qr-box canvas,
        .hero-qr-box img {
            width: 100% !important;
            height: 100% !important;
            display: block
        }

        @keyframes qrPulse {
            0% {
                box-shadow: 0 8px 30px rgba(0, 0, 0, .4), 0 0 0 0 rgba(204, 26, 26, .55)
            }

            70% {
                box-shadow: 0 8px 30px rgba(0, 0, 0, .4), 0 0 0 18px rgba(204, 26, 26, 0)
            }

            100% {
                box-shadow: 0 8px 30px rgba(0, 0, 0, .4), 0 0 0 0 rgba(204, 26, 26, 0)
            }
        }

        .hero-qr-box.qr-pulse {
            animation: qrPulse 1.5s ease-out 1
        }

        .hero-qr-label {
            display: flex;
            align-items: center;
            gap: .3rem;
            font-size: .63rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .55px;
            color: rgba(255, 255, 255, .5)
        }

        [data-bs-theme="light"] .hero-qr-label {
            color: rgba(0, 0, 0, .4)
        }

        .hero-scan-btn {
            display: flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255, 255, 255, .09);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, .18);
            color: rgba(255, 255, 255, .85);
            font-family: 'Outfit', sans-serif;
            font-size: .72rem;
            font-weight: 700;
            padding: .4rem .95rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap
        }

        [data-bs-theme="light"] .hero-scan-btn {
            background: rgba(0, 0, 0, .06);
            border-color: rgba(0, 0, 0, .14);
            color: rgba(0, 0, 0, .65)
        }

        .hero-scan-btn:hover {
            background: var(--fs-red);
            border-color: var(--fs-red);
            color: #fff;
            box-shadow: 0 4px 18px rgba(204, 26, 26, .4)
        }

        .qr-modal-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 16px;
            padding: 12px;
            border: 2px solid rgba(204, 26, 26, .12);
            box-shadow: 0 8px 28px rgba(0, 0, 0, .12)
        }

        .qr-modal-wrap canvas,
        .qr-modal-wrap img {
            display: block
        }

        .qr-expires-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: rgba(255, 193, 7, .1);
            border: 1px solid rgba(255, 193, 7, .25);
            color: #c8900a;
            font-size: .68rem;
            font-weight: 700;
            padding: .28rem .75rem;
            border-radius: 50px;
            margin-top: .65rem
        }

        /* Scanner modal */
        .scanner-modal .modal-content {
            background: #0c0c0c;
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 24px;
            overflow: hidden
        }

        .scanner-modal .modal-header {
            background: #0c0c0c;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            padding: 1.15rem 1.5rem .9rem
        }

        .scanner-modal .modal-title {
            color: #fff;
            font-weight: 800;
            font-size: 1rem
        }

        .scanner-viewport {
            position: relative;
            background: #000;
            min-height: 300px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center
        }

        #scannerVideo {
            width: 100%;
            max-height: 360px;
            object-fit: cover;
            display: block
        }

        #scannerCanvas {
            display: none
        }

        /* Vignette: box-shadow punches a transparent "window" into the dark overlay */
        .scan-vignette {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 216px;
            height: 216px;
            border-radius: 4px;
            box-shadow: 0 0 0 600px rgba(0, 0, 0, .52);
            z-index: 1;
            pointer-events: none
        }

        .scan-box-frame {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 216px;
            height: 216px;
            z-index: 2;
            pointer-events: none
        }

        .scan-corner {
            position: absolute;
            width: 28px;
            height: 28px
        }

        .scan-corner.tl {
            top: 0;
            left: 0;
            border-top: 3px solid #cc1a1a;
            border-left: 3px solid #cc1a1a;
            border-radius: 5px 0 0 0
        }

        .scan-corner.tr {
            top: 0;
            right: 0;
            border-top: 3px solid #cc1a1a;
            border-right: 3px solid #cc1a1a;
            border-radius: 0 5px 0 0
        }

        .scan-corner.bl {
            bottom: 0;
            left: 0;
            border-bottom: 3px solid #cc1a1a;
            border-left: 3px solid #cc1a1a;
            border-radius: 0 0 0 5px
        }

        .scan-corner.br {
            bottom: 0;
            right: 0;
            border-bottom: 3px solid #cc1a1a;
            border-right: 3px solid #cc1a1a;
            border-radius: 0 0 5px 0
        }

        .scan-laser {
            position: absolute;
            left: 5px;
            right: 5px;
            height: 2px;
            border-radius: 2px;
            background: linear-gradient(90deg, transparent, rgba(204, 26, 26, .95), transparent);
            animation: laserSweep 2.2s ease-in-out infinite
        }

        @keyframes laserSweep {
            0% {
                top: 5px
            }

            100% {
                top: calc(100% - 7px)
            }
        }

        .scan-status-bar {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: .95rem 1.4rem;
            background: #141414;
            border-top: 1px solid rgba(255, 255, 255, .06);
            min-height: 64px;
            transition: all .25s
        }

        .scan-status-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: rgba(255, 255, 255, .06);
            color: rgba(255, 255, 255, .5);
            transition: all .3s
        }

        .scan-status-text {
            font-size: .82rem;
            font-weight: 600;
            color: rgba(255, 255, 255, .6);
            transition: color .3s;
            line-height: 1.4
        }

        .scan-status-bar.is-scanning .scan-status-icon {
            background: rgba(204, 26, 26, .15);
            color: #cc1a1a
        }

        .scan-status-bar.is-success .scan-status-icon {
            background: rgba(76, 175, 135, .15);
            color: #4caf87
        }

        .scan-status-bar.is-success .scan-status-text {
            color: #4caf87
        }

        .scan-status-bar.is-error .scan-status-icon {
            background: rgba(220, 53, 69, .15);
            color: #e05656
        }

        .scan-status-bar.is-error .scan-status-text {
            color: #e05656
        }

        .scan-status-bar.is-loading .scan-status-icon {
            background: rgba(214, 161, 0, .15);
            color: #d6a100
        }

        .scan-status-bar.is-loading .scan-status-text {
            color: #d6a100
        }

        @keyframes spin {
            from {
                transform: rotate(0deg)
            }

            to {
                transform: rotate(360deg)
            }
        }

        .scan-modal-foot {
            background: #0c0c0c;
            border-top: 1px solid rgba(255, 255, 255, .06);
            padding: .85rem 1.5rem;
            text-align: center
        }

        @media(max-width:575.98px) {
            .hero-qr-section {
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                width: 100%;
                gap: .6rem;
                padding-top: .35rem;
                align-self: auto;
            }

            .hero-qr-box {
                width: 76px;
                height: 76px;
                padding: 5px;
                border-radius: 12px;
            }
        }

        /* ════════════════════════════════════
   NOTIFICATION SYSTEM
════════════════════════════════════ */

        /* Bell button */
        .notif-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            background: var(--card-bg);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all .18s;
            flex-shrink: 0;
        }

        .notif-btn:hover {
            border-color: var(--fs-red);
            color: var(--fs-red);
            background: var(--fs-red-soft);
        }

        /* Unread badge on bell */
        .notif-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            border-radius: 50px;
            background: var(--fs-red);
            color: #fff;
            font-size: .6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--page-bg);
            line-height: 1;
        }

        /* Slide-out panel */
        .notif-panel {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: 380px;
            max-width: 100vw;
            background: var(--sidebar-bg);
            border-left: 1px solid var(--sidebar-border);
            z-index: 300;
            display: flex;
            flex-direction: column;
            transform: translateX(105%);
            transition: transform .3s cubic-bezier(.25, .46, .45, .94);
            box-shadow: -12px 0 50px rgba(0, 0, 0, .35);
        }

        .notif-panel.open {
            transform: translateX(0);
        }

        /* Panel header */
        .notif-panel-header {
            padding: 1.25rem 1.35rem 1rem;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .notif-panel-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .notif-panel-title>i {
            color: var(--fs-red);
            font-size: 1.1rem;
        }

        .notif-unread-chip {
            font-size: .62rem;
            font-weight: 800;
            background: var(--fs-red);
            color: #fff;
            padding: .1rem .5rem;
            border-radius: 50px;
            line-height: 1.6;
            margin-left: .15rem;
        }

        .notif-panel-actions {
            display: flex;
            align-items: center;
            gap: .45rem;
        }

        .notif-mark-all-btn {
            font-size: .7rem;
            font-weight: 700;
            color: var(--fs-red);
            background: none;
            border: none;
            cursor: pointer;
            padding: .3rem .65rem;
            border-radius: 8px;
            transition: background .15s;
            white-space: nowrap;
        }

        .notif-mark-all-btn:hover {
            background: var(--fs-red-soft);
        }

        .notif-close-btn {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            border: 1px solid var(--card-border);
            background: var(--input-bg);
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            transition: all .15s;
            flex-shrink: 0;
        }

        .notif-close-btn:hover {
            color: var(--text-primary);
            border-color: rgba(255, 255, 255, .2);
        }

        /* Filter bar */
        .notif-filter-bar {
            display: flex;
            gap: .4rem;
            padding: .65rem .75rem;
            border-bottom: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }

        .notif-filter-btn {
            font-size: .7rem;
            font-weight: 700;
            padding: .28rem .75rem;
            border-radius: 50px;
            border: 1px solid var(--card-border);
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .15s;
            font-family: 'Outfit', sans-serif;
        }

        .notif-filter-btn.active,
        .notif-filter-btn:hover {
            background: var(--fs-red-soft);
            border-color: rgba(204, 26, 26, .25);
            color: var(--fs-red);
        }

        /* Scrollable list */
        .notif-list {
            flex: 1;
            overflow-y: auto;
            padding: .65rem;
        }

        .notif-list::-webkit-scrollbar {
            width: 3px;
        }

        .notif-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .notif-list::-webkit-scrollbar-thumb {
            background: var(--card-border);
            border-radius: 3px;
        }

        /* Individual notification item */
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            padding: .9rem 1rem;
            border-radius: 14px;
            margin-bottom: .45rem;
            cursor: pointer;
            transition: background .15s, border-color .15s;
            position: relative;
            background: var(--input-bg);
            border: 1px solid var(--card-border);
        }

        .notif-item:last-child {
            margin-bottom: 0;
        }

        .notif-item:hover {
            background: var(--row-hover);
            border-color: rgba(204, 26, 26, .2);
        }

        .notif-item.unread {
            background: var(--fs-red-soft);
            border-color: rgba(204, 26, 26, .2);
        }

        .notif-item.unread::after {
            content: '';
            position: absolute;
            top: 50%;
            right: .9rem;
            transform: translateY(-50%);
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--fs-red);
            flex-shrink: 0;
        }

        /* Type-coloured icon box */
        .notif-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .notif-icon.success {
            background: rgba(76, 175, 135, .12);
            color: #4caf87;
        }

        .notif-icon.warning {
            background: rgba(255, 193, 7, .12);
            color: #d6a100;
        }

        .notif-icon.danger {
            background: rgba(220, 53, 69, .12);
            color: #e05656;
        }

        .notif-icon.info {
            background: rgba(74, 158, 218, .12);
            color: #4a9eda;
        }

        /* Notification body */
        .notif-content {
            flex: 1;
            min-width: 0;
            padding-right: 1rem;
            /* leave room for unread dot */
        }

        .notif-title {
            font-size: .86rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.25;
            margin-bottom: .2rem;
        }

        .notif-body-text {
            font-size: .76rem;
            color: var(--text-muted);
            line-height: 1.55;
            margin-bottom: .4rem;
        }

        .notif-meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .notif-time {
            font-size: .65rem;
            color: var(--text-dimmed);
            font-weight: 600;
        }

        .notif-action-link {
            font-size: .68rem;
            font-weight: 700;
            color: var(--fs-red);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            font-family: 'Outfit', sans-serif;
            transition: opacity .15s;
        }

        .notif-action-link:hover {
            opacity: .75;
            text-decoration: underline;
        }

        /* Empty state */
        .notif-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text-muted);
        }

        .notif-empty i {
            font-size: 2.5rem;
            color: var(--text-dimmed);
            margin-bottom: .75rem;
            display: block;
        }

        .notif-empty p {
            font-size: .84rem;
            margin: 0;
            line-height: 1.65;
        }

        /* Panel footer */
        .notif-panel-foot {
            padding: .85rem;
            border-top: 1px solid var(--sidebar-border);
            flex-shrink: 0;
        }

        /* Backdrop overlay */
        .notif-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 299;
        }

        .notif-overlay.active {
            display: block;
        }

        /* Mobile: full-width panel */
        @media (max-width: 575.98px) {
            .notif-panel {
                width: 100vw;
                border-left: none;
            }

            .dash-hero-stats {
                flex-wrap: nowrap;
                gap: .5rem;
            }

            .dash-mini-stat {
                flex: 1;
                min-width: 0;
                padding-left: .55rem;
            }

            .dash-mini-stat-val {
                font-size: 1.7rem;
            }

            .dash-mini-stat-lbl {
                font-size: .7rem;
                letter-spacing: .2px;
            }
        }

        /* ══ PAYMENT METHOD (Renewal Modal) ══ */
        .payment-method-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .65rem;
            margin-bottom: 1.25rem;
        }

        .payment-method-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: 1rem;
            border: 2px solid var(--card-border);
            border-radius: 14px;
            background: var(--input-bg);
            cursor: pointer;
            transition: all .25s ease;
            position: relative;
            font-family: 'Outfit', sans-serif;
            outline: none;
            font-size: 0;
        }

        .payment-method-btn:hover {
            border-color: rgba(204, 26, 26, .5);
            background: rgba(204, 26, 26, .04);
        }

        .payment-method-btn.active {
            border-color: var(--fs-red);
            background: rgba(204, 26, 26, .1);
        }

        .payment-method-icon {
            font-size: 1.8rem;
            color: var(--fs-red);
        }

        .payment-method-label {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--text-primary);
            text-align: center;
            line-height: 1.2;
        }

        .payment-method-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--fs-red);
            border: 2px solid var(--card-bg);
            display: none;
            align-items: center;
            justify-content: center;
            font-size: .55rem;
            color: #fff;
        }

        .payment-method-btn.active .payment-method-badge {
            display: flex;
        }

        /* Payment info cards */
        .payment-info-card {
            border: 1.5px solid var(--card-border);
            border-radius: 16px;
            padding: 1.5rem;
            background: var(--input-bg);
            margin-bottom: 1.25rem;
            display: none;
        }

        .payment-info-card.active {
            display: block;
            animation: pmSlideDown .25s ease;
        }

        @keyframes pmSlideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .payment-card-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--fs-red);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* QR section */
        .payment-qr-section {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .payment-qr-container {
            width: 200px;
            height: 200px;
            margin: 0 auto 1rem;
            border: 2px solid var(--card-border);
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-qr-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .payment-qr-label {
            font-size: .8rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-top: .75rem;
        }

        /* Account detail rows */
        .payment-details-section {
            margin-bottom: 1.5rem;
        }

        .payment-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .9rem 1.1rem;
            background: rgba(204, 26, 26, .05);
            border-radius: 12px;
            border: 1px solid rgba(204, 26, 26, .15);
            margin-bottom: .75rem;
            gap: .75rem;
        }

        .payment-detail-row:last-child { margin-bottom: 0; }

        .payment-detail-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .3px;
            min-width: 120px;
        }

        .payment-detail-content {
            display: flex;
            align-items: center;
            gap: .6rem;
            flex: 1;
            justify-content: flex-end;
        }

        .payment-detail-value {
            color: var(--text-primary);
            font-weight: 700;
            font-family: 'Courier New', monospace;
            font-size: .85rem;
        }

        .payment-copy-btn {
            background: rgba(204, 26, 26, .15);
            border: none;
            color: var(--fs-red);
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .2s;
            font-size: .8rem;
            flex-shrink: 0;
        }

        .payment-copy-btn:hover { background: var(--fs-red); color: #fff; }
        .payment-copy-btn.copied { background: rgba(46, 204, 113, .2); color: #2ecc71; }

        /* Cash note */
        .cash-note {
            display: flex;
            align-items: flex-start;
            gap: .65rem;
            padding: .8rem 1rem;
            border-radius: 12px;
            background: rgba(204, 26, 26, .07);
            border: 1px solid rgba(204, 26, 26, .18);
            font-size: .82rem;
            color: var(--text-primary);
            line-height: 1.5;
        }

        .cash-note i { color: var(--fs-red); font-size: 1.1rem; flex-shrink: 0; margin-top: .05rem; }

        /* Card mock display */
        .card-display-mock {
            background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        [data-bs-theme="light"] .card-display-mock {
            background: linear-gradient(135deg, #f0f0f0 0%, #e8e8e8 100%);
        }

        .card-display-content { position: relative; z-index: 2; color: #fff; }
        [data-bs-theme="light"] .card-display-content { color: #333; }

        .card-chip {
            width: 40px;
            height: 32px;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .card-number {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            letter-spacing: 2px;
            margin-bottom: .8rem;
            font-weight: 600;
        }

        .card-info-row {
            display: flex;
            justify-content: space-between;
            font-size: .75rem;
            font-weight: 500;
        }

        .card-info-item { display: flex; flex-direction: column; gap: .15rem; }

        .card-label {
            font-size: .58rem;
            text-transform: uppercase;
            letter-spacing: .6px;
            opacity: .6;
        }

        /* auth-input / auth-label / auth-select reused inside modal */
        .auth-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .6px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: .35rem;
            display: block;
        }

        .auth-input {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            padding: .65rem 1rem;
            border-radius: 12px;
            width: 100%;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .auth-input:focus {
            border-color: var(--fs-red);
            box-shadow: 0 0 0 3px var(--fs-red-glow);
        }

        .auth-input::placeholder { color: var(--text-muted); opacity: .6; }

        .auth-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            padding: .65rem 2.4rem .65rem 1rem;
            border-radius: 12px;
            width: 100%;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
        }

        .auth-select:focus {
            border-color: var(--fs-red);
            box-shadow: 0 0 0 3px var(--fs-red-glow);
        }

        [data-bs-theme="dark"] .auth-select option {
            background: #1a1a1a;
            color: #fff;
        }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap .auth-input { padding-left: 2.6rem; }
        .input-icon-wrap .ii {
            position: absolute;
            left: .85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
        }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed var(--card-border);
            border-radius: 14px;
            padding: 1.2rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            border-color: var(--fs-red);
            background: rgba(204, 26, 26, .04);
        }

        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-zone-icon {
            font-size: 1.6rem;
            color: var(--text-muted);
            margin-bottom: .4rem;
            transition: color .2s;
        }

        .upload-zone:hover .upload-zone-icon { color: var(--fs-red); }

        .upload-zone-title {
            font-size: .82rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .upload-zone-title span { color: var(--fs-red); }

        .upload-zone-hint {
            font-size: .7rem;
            color: var(--text-muted);
            margin-top: .2rem;
        }

        .upload-preview {
            display: none;
            align-items: center;
            gap: .6rem;
            padding: .55rem .85rem;
            border-radius: 10px;
            background: rgba(204, 26, 26, .07);
            border: 1px solid rgba(204, 26, 26, .2);
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-top: .6rem;
        }

        .upload-preview.show { display: flex; }
        .upload-preview i { color: var(--fs-red); font-size: .95rem; }

        .upload-remove {
            margin-left: auto;
            cursor: pointer;
            color: var(--text-muted);
            font-size: .9rem;
            line-height: 1;
            border: none;
            background: none;
            padding: 0;
            transition: color .2s;
        }

        .upload-remove:hover { color: var(--fs-red); }
    </style>
</head>

<body>

    <div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

    <!-- ════════ SIDEBAR ════════ -->
    <aside class="sidebar" id="sidebar">
        <div class="sb-header">
            <a class="sb-brand" href="index.php">
                <img class="theme-logo" src="assets/FitSYNC%20Emblem%20Light.svg" alt="FitSync" width="30" height="30" id="sidebarLogo" data-logo-dark="assets/FitSYNC%20Emblem%20Light.svg" data-logo-light="assets/FitSYNC%20Emblem.svg" />
                <span class="brand-text"><span class="fit">FIT</span><span class="sync">SYNC</span></span>
            </a>
            <div class="sb-avatar-card">
                <?php if ($hasActiveMembership): ?>
                    <span class="sb-member-badge">Active</span>
                <?php endif ?>
                <div class="sb-avatar" id="sbAvatar">
                    <span id="sbAvatarInitials"><?= $initials ?></span>
                </div>
                <div class="sb-member-name" id="sbMemberName"><?= $fullName ?></div>
                <div class="sb-member-plan">
                    <?= $hasActiveMembership ? htmlspecialchars($mem['plan_label']) . ' · Member' : 'No active plan' ?>
                </div>
            </div>
        </div>

        <nav class="sb-nav">
            <div class="sb-nav-label">Menu</div>
            <button class="sb-nav-item active" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('dashboard', this)">
                <i class="ti ti-layout-dashboard"></i> Dashboard
            </button>
            <button class="sb-nav-item" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('programs', this)">
                <i class="ti ti-barbell"></i> Programs
            </button>
            <button class="sb-nav-item" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('billing', this)">
                <i class="ti ti-receipt"></i> Plans & Billing
            </button>
            <button class="sb-nav-item" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('schedule', this)">
                <i class="ti ti-calendar-event"></i> Schedule
            </button>
            <button class="sb-nav-item" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('feedback', this)">
                <i class="ti ti-message-star"></i> Feedback
            </button>
            <button class="sb-nav-item" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('settings', this)">
                <i class="ti ti-settings"></i> Settings
            </button>
            <div class="sb-nav-label" style="margin-top:1rem">Shop</div>
            <button class="sb-nav-item" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('shop', this)">
                <i class="ti ti-shopping-bag"></i> Shop
            </button>
            <button class="sb-nav-item" <?= $isPending ? 'disabled' : '' ?> onclick="showTab('orders', this)">
                <i class="ti ti-package"></i> Orders
            </button>
        </nav>

        <div class="sb-footer">
            <div class="sb-theme-row">
                <span class="sb-theme-label"><i class="ti ti-moon"></i> Dark Mode</span>
                <button class="theme-pill" onclick="toggleTheme()" aria-label="Toggle theme">
                    <div class="theme-pill-knob"></div>
                </button>
            </div>
            <a href="logout.php" class="sb-logout">
                <i class="ti ti-logout"></i> Log Out
            </a>
        </div>
    </aside>

    <!-- ════════ MAIN ════════ -->
    <main class="main-content">

        <!-- Mobile hamburger row — z-index above pending overlay so it stays tappable -->
        <div id="mobileHamburgerRow" style="display:flex;align-items:center;gap:.65rem;margin-bottom:1.5rem;position:relative;z-index:200" class="d-lg-none">
            <button class="hamburger" onclick="openSidebar()"><i class="ti ti-menu-2"></i></button>
            <span style="font-size:.9rem;font-weight:700;color:var(--text-muted)">FitSync</span>
            <button class="notif-btn ms-auto" id="notifBellMobile"
                onclick="toggleNotifPanel()" title="Notifications"
                aria-label="Notifications">
                <i class="ti ti-bell"></i>
                <span class="notif-badge" id="notifBadgeMobile" style="display:none">0</span>
            </button>
        </div>

        <?php if ($isPending): ?>
            <!-- Blurry backdrop overlay covering main content -->
            <div class="pending-overlay"></div>
            <!-- Notice card: fixed top-center, above overlay, above sidebar on mobile -->
            <div class="pending-notice">
                <div class="pending-notice-card">
                    <div class="pending-notice-icon">
                        <i class="ti ti-clock-hour-4"></i>
                    </div>
                    <div class="pending-notice-body">
                        <h2>Account Pending Approval</h2>
                        <p>Your account is currently under review by our administrators. The full member dashboard is visible, but interactions are disabled until approval.</p>
                        <p>Use the sidebar to toggle dark mode or log out while you wait.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── HERO HEADER ── -->
        <!-- Notification action bar (desktop) -->
        <div class="d-none d-lg-flex" style="justify-content:flex-end;margin-bottom:.85rem">
            <button class="notif-btn" id="notifBellDesktop"
                onclick="toggleNotifPanel()" title="Notifications"
                aria-label="Notifications">
                <i class="ti ti-bell"></i>
                <span class="notif-badge" id="notifBadgeDesktop" style="display:none">0</span>
            </button>
        </div>
        <div class="dash-hero" id="dashHero">
            <div style="position:relative;z-index:1;display:flex;align-items:flex-start;justify-content:space-between;gap:2rem;flex-wrap:wrap">
                <!-- Left: greeting + stats -->
                <div>
                    <div class="dash-hero-badge"><span></span> Member Portal</div>
                    <div class="dash-hero-greeting"><?= $greeting ?>, <?= htmlspecialchars($userRow['first_name'] ?? 'Member') ?> 👋</div>
                    <div class="dash-hero-sub"><?= date('l, F j, Y') ?> · Welcome back to FitSync</div>
                    <div class="dash-hero-stats">
                        <?php if ($hasActiveMembership): ?>
                            <div class="dash-mini-stat">
                                <div class="dash-mini-stat-val"><?= number_format($daysRemaining) ?></div>
                                <div class="dash-mini-stat-lbl">Days Left</div>
                            </div>
                            <div class="dash-mini-stat">
                                <div class="dash-mini-stat-val"><?= $progressPct ?>%</div>
                                <div class="dash-mini-stat-lbl">Plan Used</div>
                            </div>
                            <div class="dash-mini-stat">
                                <div class="dash-mini-stat-val" id="attendanceTotalHero"><?= number_format($attendanceTotal) ?></div>
                                <div class="dash-mini-stat-lbl">Total Visits</div>
                            </div>
                        <?php endif ?>
                        <div class="dash-mini-stat">
                            <div class="dash-mini-stat-val" id="streakDisplay"><?= number_format($currentStreak) ?></div>
                            <div class="dash-mini-stat-lbl">Day Streak 🔥</div>
                        </div>
                    </div>
                </div>

                <!-- Right: QR check-in widget -->
                <?php if (!$isPending): ?>
                    <div class="hero-qr-section" id="heroQrSection">
                        <div class="hero-qr-box" id="heroQrBox" onclick="openQrModal()" title="Tap to enlarge">
                            <?php if (file_exists(__DIR__ . '/' . $qrFile)): ?>
                                <img src="<?= $qrFile ?>" alt="QR Code" />
                            <?php else: ?>
                                <div id="heroQrCode"></div>
                            <?php endif; ?>
                        </div>
                        <div class="hero-qr-label"><i class="ti ti-qrcode" aria-hidden="true"></i> Check-in QR</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>


        <!-- ══ DASHBOARD ══ -->
        <div class="page-section active" id="tab-dashboard">

            <?php include __DIR__ . '/includes/member_today_panel.php'; ?>

            <?php include __DIR__ . '/includes/member_membership_section.php'; ?>

            <?php include __DIR__ . '/includes/member_attendance_section.php'; ?>
        </div>
        <div class="page-section" id="tab-profile">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="fs-card">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1.5rem">Personal Information</div>
                        <div class="alert fs-alert" id="profileAlert" role="alert"></div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fs-label">First Name</label>
                                <input type="text" class="form-control fs-input" id="p-fname"
                                    value="<?= htmlspecialchars($userRow['first_name'] ?? '') ?>" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-label">Last Name</label>
                                <input type="text" class="form-control fs-input" id="p-lname"
                                    value="<?= htmlspecialchars($userRow['last_name'] ?? '') ?>" />
                            </div>
                            <div class="col-12">
                                <label class="form-label fs-label">Email</label>
                                <input type="email" class="form-control fs-input" id="p-email"
                                    value="<?= htmlspecialchars($userRow['email'] ?? '') ?>" readonly />
                                <div style="font-size:.7rem;color:var(--text-dimmed);margin-top:.3rem">Contact support to change your email address.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-label">Gender</label>
                                <select class="form-select fs-input" id="p-gender">
                                    <option value="">Prefer not to say</option>
                                    <option value="male" <?= ($userRow['gender'] ?? '') === 'male'      ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($userRow['gender'] ?? '') === 'female'    ? 'selected' : '' ?>>Female</option>
                                    <option value="nonbinary" <?= ($userRow['gender'] ?? '') === 'nonbinary' ? 'selected' : '' ?>>Non-binary</option>
                                    <option value="other" <?= ($userRow['gender'] ?? '') === 'other'     ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-label">Birthdate</label>
                                <input type="date" class="form-control fs-input" id="p-birthdate"
                                    value="<?= htmlspecialchars($userRow['birthdate'] ?? '') ?>"
                                    max="<?= date('Y-m-d', strtotime('-16 years')) ?>" />
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-fs rounded-pill px-4" onclick="saveProfile()">
                                <i class="ti ti-device-floppy me-1"></i>Save Changes
                            </button>
                            <button class="btn btn-outline-secondary rounded-pill px-4" onclick="resetProfile()">Reset</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="fs-card text-center">
                        <!-- Avatar upload -->
                        <div class="d-flex justify-content-center mb-3">
                            <div class="avatar-upload-wrap" onclick="document.getElementById('avatarInput').click()">
                                <div class="profile-avatar-lg" id="profileAvatarLg">
                                    <span id="profileInitials"><?= $initials ?></span>
                                </div>
                                <div class="avatar-upload-overlay"><i class="ti ti-camera"></i></div>
                            </div>
                        </div>
                        <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="handleAvatarUpload(this)" />
                        <div style="font-weight:700;font-size:1rem;color:var(--text-primary)" id="profileName"><?= $fullName ?></div>
                        <div style="font-size:.78rem;color:var(--text-muted);margin-top:.2rem"><?= htmlspecialchars($userRow['email'] ?? '') ?></div>
                        <div style="font-size:.7rem;color:var(--text-dimmed);margin-top:.5rem">Click avatar to upload photo</div>
                        <hr style="border-color:var(--card-border);margin:1rem 0">
                        <div class="d-flex flex-column gap-2 text-start">
                            <div style="font-size:.78rem;color:var(--text-muted)"><i class="ti ti-calendar me-1"></i>Member since <?= date('M Y', strtotime($userRow['created_at'])) ?></div>
                            <div style="font-size:.78rem;color:var(--text-muted)"><i class="ti ti-id-badge me-1"></i>Member #<?= str_pad($userId, 5, '0', STR_PAD_LEFT) ?></div>
                            <?php if ($mem): ?>
                                <div style="font-size:.78rem;color:var(--text-muted)"><i class="ti ti-building-store me-1"></i><?= htmlspecialchars($mem['branch_name']) ?></div>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ PLANS & BILLING ══ -->
        <div class="page-section" id="tab-billing">
            <?php if ($allMems): ?>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1rem">Membership History</div>
                        <div class="fs-table-wrap">
                            <table class="table fs-table">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th>Branch</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allMems as $m): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($m['plan_label']) ?></strong></td>
                                            <td style="color:var(--text-muted)"><?= htmlspecialchars($m['branch_name']) ?></td>
                                            <td style="color:var(--text-muted)"><?= date('M j, Y', strtotime($m['starts_at'])) ?></td>
                                            <td style="color:var(--text-muted)"><?= date('M j, Y', strtotime($m['ends_at'])) ?></td>
                                            <td><strong>₱<?= number_format((float)$m['amount_paid'], 2) ?></strong></td>
                                            <td style="color:var(--text-muted)"><?= payLabel($m['payment_method']) ?></td>
                                            <td><span class="status-badge <?= htmlspecialchars($m['payment_status']) ?>"><?= ucfirst($m['payment_status']) ?></span></td>
                                            <td><span class="status-badge <?= htmlspecialchars($m['status']) ?>"><?= ucfirst($m['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Plans preview (show plan descriptions/features) -->
                <div class="row g-3 mb-4">
                    <?php if (!empty($membershipPlans)): ?>
                        <div class="col-12">
                            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1rem">Available Plans</div>
                            <div class="row g-3">
                                <?php foreach ($membershipPlans as $plan):
                                    $slug = $plan['slug'] ?? '';
                                    $features = [];
                                    switch ($slug) {
                                        case '1mo':
                                            $features = [
                                                ['label' => 'Full gym access', 'yes' => true],
                                                ['label' => 'Locker & showers', 'yes' => true],
                                                ['label' => '2 classes/week', 'yes' => true],
                                                ['label' => 'Personal trainer', 'yes' => false],
                                                ['label' => 'Multi-branch', 'yes' => false],
                                            ];
                                            break;
                                        case '3mo':
                                            $features = [
                                                ['label' => 'Full gym access', 'yes' => true],
                                                ['label' => 'Locker & showers', 'yes' => true],
                                                ['label' => 'Unlimited classes', 'yes' => true],
                                                ['label' => '1 PT session/mo', 'yes' => true],
                                                ['label' => 'Multi-branch', 'yes' => false],
                                            ];
                                            break;
                                        case '6mo':
                                            $features = [
                                                ['label' => 'Full gym access', 'yes' => true],
                                                ['label' => 'Locker & showers', 'yes' => true],
                                                ['label' => 'Unlimited classes', 'yes' => true],
                                                ['label' => '2 PT sessions/mo', 'yes' => true],
                                                ['label' => 'Multi-branch', 'yes' => true],
                                            ];
                                            break;
                                        case '12mo':
                                            $features = [
                                                ['label' => 'Full gym access', 'yes' => true],
                                                ['label' => 'Locker & showers', 'yes' => true],
                                                ['label' => 'Unlimited classes', 'yes' => true],
                                                ['label' => '4 PT sessions/mo', 'yes' => true],
                                                ['label' => 'Multi-branch', 'yes' => true],
                                            ];
                                            break;
                                        default:
                                            $features = [];
                                    }
                                ?>
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <?php $isActive = isset($mem['plan_slug']) && $mem['plan_slug'] === ($plan['slug'] ?? ''); ?>
                                        <div class="plan-card border h-100 d-flex flex-column p-3<?= $isActive ? ' active-plan' : '' ?>" style="position:relative">
                                            <?php if ($isActive): ?><div class="plan-badge">Active</div><?php endif ?>
                                            <div style="font-weight:800"><?= htmlspecialchars($plan['label']) ?></div>
                                            <div style="font-size:1.1rem;font-weight:800;margin-top:.35rem">₱<?= number_format((float)$plan['price'], 2) ?></div>
                                            <hr style="border-color:var(--card-border);margin:.6rem 0">
                                            <ul class="list-unstyled" style="margin-bottom:auto">
                                                <?php foreach ($features as $f): ?>
                                                    <li style="margin:.35rem 0;color:<?= $f['yes'] ? 'var(--text-primary)' : 'var(--text-muted)' ?>">
                                                        <i class="ti <?= $f['yes'] ? 'ti-check' : 'ti-x' ?>" style="margin-right:.5rem"></i><?= htmlspecialchars($f['label']) ?>
                                                    </li>
                                                <?php endforeach ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endforeach ?>
                            </div>
                        </div>
                    <?php endif ?>
                </div>

                <div class="fs-card" style="border-style:dashed">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:42px;height:42px;border-radius:12px;background:var(--fs-red-soft);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="ti ti-refresh" style="color:var(--fs-red);font-size:1.2rem"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div style="font-weight:700;font-size:.9rem;color:var(--text-primary)">Ready to renew?</div>
                            <div style="font-size:.78rem;color:var(--text-muted)">Submit a renewal for admin payment approval.</div>
                        </div>
                        <button class="btn btn-fs rounded-pill px-4 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#renewModal">
                            <i class="ti ti-refresh me-1"></i>Renew
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-mem-card">
                    <i class="ti ti-receipt-off"></i>
                    <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:.5rem">No Billing History Yet</div>
                    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.5rem">Membership renewals and payment approvals will appear here after your first request.</p>
                    <button class="btn btn-fs rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#renewModal">
                        <i class="ti ti-refresh me-1"></i>Request Membership
                    </button>
                </div>
            <?php endif ?>
        </div>

        <!-- ══ PROGRAMS ══ -->
        <div class="modal fade" id="renewModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:18px">
                    <div class="modal-header" style="border-color:var(--card-border)">
                        <h5 class="modal-title" style="font-weight:800;color:var(--text-primary)">Renew Membership</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="renewAlert" class="alert d-none" style="font-size:.85rem"></div>
                        <label class="form-label fs-label">Plan</label>
                        <select class="form-select fs-input mb-3" id="renew-plan">
                            <?php foreach ($membershipPlans as $plan): ?>
                                <option value="<?= (int) $plan['id'] ?>"><?= htmlspecialchars($plan['label']) ?> - ₱<?= number_format((float) $plan['price'], 2) ?></option>
                            <?php endforeach ?>
                        </select>

                        <!-- Payment Method -->
                        <div class="mb-3">
                            <label class="form-label fs-label mb-2">Payment Method</label>
                            <div class="payment-method-grid" id="rnPaymentMethodGrid">
                                <button type="button" class="payment-method-btn active" data-method="cash" onclick="rnSelectPaymentMethod('cash', event)">
                                    <i class="ti ti-cash payment-method-icon"></i>
                                    <span class="payment-method-label">Cash</span>
                                    <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                </button>
                                <button type="button" class="payment-method-btn" data-method="gcash" onclick="rnSelectPaymentMethod('gcash', event)">
                                    <i class="ti ti-wallet payment-method-icon"></i>
                                    <span class="payment-method-label">GCash</span>
                                    <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                </button>
                                <button type="button" class="payment-method-btn" data-method="maya" onclick="rnSelectPaymentMethod('maya', event)">
                                    <i class="ti ti-wallet payment-method-icon"></i>
                                    <span class="payment-method-label">Maya</span>
                                    <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                </button>
                                <button type="button" class="payment-method-btn" data-method="bank_transfer" onclick="rnSelectPaymentMethod('bank_transfer', event)">
                                    <i class="ti ti-building-bank payment-method-icon"></i>
                                    <span class="payment-method-label">Bank Transfer</span>
                                    <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                </button>
                                <button type="button" class="payment-method-btn" data-method="credit_card" onclick="rnSelectPaymentMethod('credit_card', event)">
                                    <i class="ti ti-credit-card payment-method-icon"></i>
                                    <span class="payment-method-label">Credit Card</span>
                                    <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                </button>
                                <button type="button" class="payment-method-btn" data-method="debit_card" onclick="rnSelectPaymentMethod('debit_card', event)">
                                    <i class="ti ti-credit-card payment-method-icon"></i>
                                    <span class="payment-method-label">Debit Card</span>
                                    <div class="payment-method-badge"><i class="ti ti-check" style="font-size:.55rem"></i></div>
                                </button>
                            </div>
                            <select id="renew-payment" style="display:none">
                                <option value="cash">Cash / Walk-in</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>

                        <!-- Payment Info Cards -->
                        <div id="rnPaymentInfoContainer">

                            <!-- Cash / Walk-in -->
                            <div class="payment-info-card active" id="rn-pay-info-cash">
                                <div class="payment-card-title"><i class="ti ti-cash me-1"></i> Walk-in Payment</div>
                                <div class="cash-note">
                                    <i class="ti ti-map-pin"></i>
                                    <span>Complete your payment at the gym branch.</span>
                                </div>
                            </div>

                            <!-- GCash -->
                            <div class="payment-info-card" id="rn-pay-info-gcash">
                                <div class="payment-card-title"><i class="ti ti-wallet"></i> GCash Payment</div>
                                <div class="payment-qr-section">
                                    <div class="payment-qr-container"><img src="qrcodes/qr_sample.png" alt="GCash QR Code"></div>
                                    <div class="payment-qr-label">Scan the QR code using GCash to complete your payment.</div>
                                </div>
                                <div class="payment-details-section">
                                    <div class="payment-detail-row">
                                        <div class="payment-detail-label">Account Name</div>
                                        <div class="payment-detail-content">
                                            <span class="payment-detail-value">FitSync Gym</span>
                                            <button type="button" class="payment-copy-btn" onclick="rnCopyToClipboard('FitSync Gym', this)"><i class="ti ti-copy"></i></button>
                                        </div>
                                    </div>
                                    <div class="payment-detail-row">
                                        <div class="payment-detail-label">Mobile Number</div>
                                        <div class="payment-detail-content">
                                            <span class="payment-detail-value">0917 123 4567</span>
                                            <button type="button" class="payment-copy-btn" onclick="rnCopyToClipboard('09171234567', this)"><i class="ti ti-copy"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Maya -->
                            <div class="payment-info-card" id="rn-pay-info-maya">
                                <div class="payment-card-title"><i class="ti ti-wallet"></i> Maya Payment</div>
                                <div class="payment-qr-section">
                                    <div class="payment-qr-container"><img src="qrcodes/qr_sample.png" alt="Maya QR Code"></div>
                                    <div class="payment-qr-label">Scan the QR code using Maya to complete your payment.</div>
                                </div>
                                <div class="payment-details-section">
                                    <div class="payment-detail-row">
                                        <div class="payment-detail-label">Account Name</div>
                                        <div class="payment-detail-content">
                                            <span class="payment-detail-value">FitSync Gym</span>
                                            <button type="button" class="payment-copy-btn" onclick="rnCopyToClipboard('FitSync Gym', this)"><i class="ti ti-copy"></i></button>
                                        </div>
                                    </div>
                                    <div class="payment-detail-row">
                                        <div class="payment-detail-label">Mobile Number</div>
                                        <div class="payment-detail-content">
                                            <span class="payment-detail-value">0917 765 4321</span>
                                            <button type="button" class="payment-copy-btn" onclick="rnCopyToClipboard('09177654321', this)"><i class="ti ti-copy"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Transfer -->
                            <div class="payment-info-card" id="rn-pay-info-bank_transfer">
                                <div class="payment-card-title"><i class="ti ti-building-bank"></i> Bank Transfer</div>
                                <div class="payment-qr-section">
                                    <div class="payment-qr-container"><img src="qrcodes/qr_sample.png" alt="Bank Transfer QR Code"></div>
                                    <div class="payment-qr-label">Scan the QR code or use the account details below to transfer.</div>
                                </div>
                                <div class="payment-details-section">
                                    <div class="payment-detail-row">
                                        <div class="payment-detail-label">Bank Name</div>
                                        <div class="payment-detail-content">
                                            <span class="payment-detail-value">Metrobank</span>
                                            <button type="button" class="payment-copy-btn" onclick="rnCopyToClipboard('Metrobank', this)"><i class="ti ti-copy"></i></button>
                                        </div>
                                    </div>
                                    <div class="payment-detail-row">
                                        <div class="payment-detail-label">Account Name</div>
                                        <div class="payment-detail-content">
                                            <span class="payment-detail-value">FitSync Corp</span>
                                            <button type="button" class="payment-copy-btn" onclick="rnCopyToClipboard('FitSync Corp', this)"><i class="ti ti-copy"></i></button>
                                        </div>
                                    </div>
                                    <div class="payment-detail-row">
                                        <div class="payment-detail-label">Account Number</div>
                                        <div class="payment-detail-content">
                                            <span class="payment-detail-value">123 456 789 0</span>
                                            <button type="button" class="payment-copy-btn" onclick="rnCopyToClipboard('1234567890', this)"><i class="ti ti-copy"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Credit Card -->
                            <div class="payment-info-card" id="rn-pay-info-credit_card">
                                <div class="payment-card-title"><i class="ti ti-credit-card me-1"></i> Credit Card</div>
                                <div class="card-display-mock">
                                    <div class="card-display-content">
                                        <div class="card-chip"></div>
                                        <div class="card-number">•••• •••• •••• <span id="rnCcDisplay4">0000</span></div>
                                        <div class="card-info-row">
                                            <div class="card-info-item"><span class="card-label">Cardholder</span><span id="rnCcDisplayName" style="font-size:.78rem;text-transform:uppercase">YOUR NAME</span></div>
                                            <div class="card-info-item"><span class="card-label">Valid Thru</span><span id="rnCcDisplayExp" style="font-size:.78rem">MM/YY</span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="auth-label">Cardholder Name</div>
                                        <div class="input-icon-wrap"><i class="ti ti-user ii"></i><input class="auth-input" type="text" id="rnPdCcName" placeholder="Juan Dela Cruz" oninput="rnUpdateCardDisplay()" /></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="auth-label">Card Type</div>
                                        <select class="auth-select" id="rnPdCcType"><option value="">Select type</option><option value="visa">Visa</option><option value="mastercard">Mastercard</option><option value="amex">American Express</option><option value="jcb">JCB</option></select>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="auth-label">Last 4 Digits</div>
                                        <div class="input-icon-wrap"><i class="ti ti-credit-card ii"></i><input class="auth-input" type="text" id="rnPdCcLast4" placeholder="1234" maxlength="4" oninput="this.value=this.value.replace(/\D/g,'');rnUpdateCardDisplay()" /></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="auth-label">Expiry (MM/YY)</div>
                                        <input class="auth-input" type="text" id="rnPdCcExp" placeholder="MM/YY" maxlength="5" oninput="rnFormatCardExpiry(this);rnUpdateCardDisplay()" />
                                    </div>
                                </div>
                                <div class="mb-3"><div class="auth-label">CVV</div><input class="auth-input" type="text" id="rnPdCcCvv" placeholder="•••" maxlength="4" oninput="this.value=this.value.replace(/\D/g,'')" /></div>
                            </div>

                            <!-- Debit Card -->
                            <div class="payment-info-card" id="rn-pay-info-debit_card">
                                <div class="payment-card-title"><i class="ti ti-credit-card me-1"></i> Debit Card</div>
                                <div class="card-display-mock">
                                    <div class="card-display-content">
                                        <div class="card-chip"></div>
                                        <div class="card-number">•••• •••• •••• <span id="rnDcDisplay4">0000</span></div>
                                        <div class="card-info-row">
                                            <div class="card-info-item"><span class="card-label">Cardholder</span><span id="rnDcDisplayName" style="font-size:.78rem;text-transform:uppercase">YOUR NAME</span></div>
                                            <div class="card-info-item"><span class="card-label">Valid Thru</span><span id="rnDcDisplayExp" style="font-size:.78rem">MM/YY</span></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="auth-label">Cardholder Name</div>
                                        <div class="input-icon-wrap"><i class="ti ti-user ii"></i><input class="auth-input" type="text" id="rnPdDcName" placeholder="Juan Dela Cruz" oninput="rnUpdateCardDisplay('debit')" /></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="auth-label">Bank Name</div>
                                        <input class="auth-input" type="text" id="rnPdDcBank" placeholder="e.g. BDO, BPI" />
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="auth-label">Last 4 Digits</div>
                                        <div class="input-icon-wrap"><i class="ti ti-credit-card ii"></i><input class="auth-input" type="text" id="rnPdDcLast4" placeholder="5678" maxlength="4" oninput="this.value=this.value.replace(/\D/g,'');rnUpdateCardDisplay('debit')" /></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="auth-label">Expiry (MM/YY)</div>
                                        <input class="auth-input" type="text" id="rnPdDcExp" placeholder="MM/YY" maxlength="5" oninput="rnFormatCardExpiry(this,'debit');rnUpdateCardDisplay('debit')" />
                                    </div>
                                </div>
                                <div class="mb-3"><div class="auth-label">CVV</div><input class="auth-input" type="text" id="rnPdDcCvv" placeholder="•••" maxlength="4" oninput="this.value=this.value.replace(/\D/g,'')" /></div>
                            </div>

                        </div><!-- /#rnPaymentInfoContainer -->

                        <!-- Proof of Payment Upload (REQUIRED) -->
                        <div class="mb-3" id="rnProofUploadSection" style="display:none">
                            <div class="auth-label mb-2">Proof of Payment <span style="color:var(--fs-red);font-weight:700">*</span></div>
                            <div class="upload-zone" id="rnUploadZone"
                                ondragover="event.preventDefault();this.classList.add('dragover')"
                                ondragleave="this.classList.remove('dragover')"
                                ondrop="rnHandleFileDrop(event)">
                                <input type="file" id="rnProofFile" accept=".jpg,.jpeg,.png,.pdf" onchange="rnHandleFileSelect(this)" />
                                <div class="upload-zone-icon"><i class="ti ti-cloud-upload"></i></div>
                                <div class="upload-zone-title"><span>Upload File</span> or drag &amp; drop</div>
                                <div class="upload-zone-hint">JPG, PNG, or PDF accepted</div>
                            </div>
                            <div class="upload-preview" id="rnUploadPreview">
                                <i class="ti ti-file-check"></i>
                                <span id="rnUploadFileName">screenshot.jpg</span>
                                <button class="upload-remove" type="button" onclick="rnRemoveUpload()" title="Remove file"><i class="ti ti-x"></i></button>
                            </div>
                            <p style="font-size:.68rem;color:var(--text-muted);margin-top:.45rem;margin-bottom:0;line-height:1.5">
                                <i class="ti ti-info-circle" style="font-size:.75rem"></i>
                                Upload a screenshot or receipt so staff can verify your payment.
                            </p>
                        </div>

                    </div>
                    <div class="modal-footer" style="border-color:var(--card-border)">
                        <button class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-fs rounded-pill px-4" onclick="submitRenewal()">Submit Renewal</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SCHEDULE -->
        <div class="page-section" id="tab-schedule">
            <?php include __DIR__ . '/includes/member_schedule_section.php'; ?>
        </div>

        <div class="page-section" id="tab-programs">
            <div id="programsListView">
                <div style="margin-bottom:1.5rem">
                    <div style="font-size:1.1rem;font-weight:800;letter-spacing:-.3px;color:var(--text-primary);margin-bottom:.3rem">Workout Programs</div>
                    <div style="font-size:.85rem;color:var(--text-muted)">Choose a training program that matches your goals. Click any card to see the full schedule.</div>
                </div>
                <div class="row g-3">
                    <?php foreach ($workoutPrograms as $prog): ?>
                        <div class="col-md-6">
                            <div class="program-card" onclick="showProgram('<?= $prog['id'] ?>')" style="--prog-color:<?= $prog['color'] ?>">
                                <div class="program-card-bar" style="background:<?= $prog['color'] ?>"></div>
                                <div class="program-card-body">
                                    <div class="program-tag" style="background:<?= $prog['color'] ?>22;color:<?= $prog['color'] ?>;border:1px solid <?= $prog['color'] ?>44">
                                        <?= htmlspecialchars($prog['tag']) ?>
                                    </div>
                                    <div class="program-name"><?= htmlspecialchars($prog['name']) ?></div>
                                    <div class="program-meta">
                                        <div class="program-meta-item"><i class="ti ti-calendar"></i> <?= $prog['days'] ?> days/week</div>
                                        <div class="program-meta-item"><i class="ti ti-signal"></i> <?= htmlspecialchars($prog['level']) ?></div>
                                    </div>
                                    <div class="program-desc"><?= htmlspecialchars($prog['desc']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>

            <!-- Program Detail View -->
            <div id="programDetailView" style="display:none">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <button class="btn btn-outline-secondary btn-sm rounded-pill" onclick="backToPrograms()">
                        <i class="ti ti-arrow-left me-1"></i>Back
                    </button>
                    <span style="font-size:.85rem;color:var(--text-muted)">Programs</span>
                    <span style="font-size:.85rem;color:var(--text-dimmed)">/</span>
                    <span style="font-size:.85rem;color:var(--text-primary);font-weight:700" id="progDetailBreadcrumb">—</span>
                </div>
                <div class="prog-detail">
                    <div class="prog-detail-header" id="progDetailHeader"></div>
                    <div class="prog-schedule-grid" id="progSchedule"></div>
                </div>
            </div>
        </div>

        <!-- ══ FEEDBACK ══ -->
        <div class="page-section" id="tab-feedback">
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="fs-card">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1.25rem">Leave a Review</div>
                        <div class="alert fs-alert" id="feedbackAlert" role="alert"></div>
                        <div class="mb-3">
                            <label class="form-label fs-label">Branch</label>
                            <select class="form-select fs-input" id="fb-branch">
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?> — <?= htmlspecialchars($b['city']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fs-label">Rating</label>
                            <div class="star-picker" id="starPicker">
                                <span class="star active" data-val="1">★</span>
                                <span class="star active" data-val="2">★</span>
                                <span class="star active" data-val="3">★</span>
                                <span class="star active" data-val="4">★</span>
                                <span class="star active" data-val="5">★</span>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fs-label">Your Review</label>
                            <textarea class="form-control fs-input" id="fb-body" rows="4"
                                placeholder="Share your experience with us…"
                                style="resize:vertical;min-height:100px"></textarea>
                        </div>
                        <button class="btn btn-fs w-100 rounded-pill" onclick="submitFeedback()">
                            <i class="ti ti-send me-1"></i>Submit Review
                        </button>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1rem">Your Past Reviews</div>
                    <div id="myFeedbackList">
                        <?php if ($myFeedbacks): ?>
                            <?php foreach ($myFeedbacks as $f): ?>
                                <div class="feedback-card mb-2">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div class="feedback-stars"><?= str_repeat('★', (int)$f['rating']) . str_repeat('☆', 5 - (int)$f['rating']) ?></div>
                                        <span style="font-size:.7rem;color:var(--text-dimmed)"><?= date('M j, Y', strtotime($f['created_at'])) ?></span>
                                    </div>
                                    <div style="font-size:.85rem;color:var(--text-muted);line-height:1.7;margin:.4rem 0">"<?= htmlspecialchars($f['body']) ?>"</div>
                                    <div style="font-size:.72rem;color:var(--text-dimmed)"><i class="ti ti-map-pin" style="font-size:.8rem"></i> <?= htmlspecialchars($f['branch_name'] ?? 'Unknown Branch') ?></div>
                                </div>
                            <?php endforeach ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="ti ti-message-off"></i>
                                <p>No reviews yet. Be the first to share your experience!</p>
                            </div>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ SETTINGS ══ -->
        <div class="page-section" id="tab-settings">
            <div class="row g-3">
                <div class="col-lg-6">
                    <!-- Change Password -->
                    <div class="fs-card mb-3">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1.25rem">Change Password</div>
                        <div class="alert fs-alert" id="pwAlert" role="alert"></div>
                        <div class="mb-3">
                            <label class="form-label fs-label">Current Password</label>
                            <input type="password" class="form-control fs-input" id="pw-current" placeholder="••••••••" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label fs-label">New Password</label>
                            <input type="password" class="form-control fs-input" id="pw-new" placeholder="Min. 8 characters" oninput="checkPwStrength(this.value)" />
                            <div class="d-flex gap-1 mt-2">
                                <div style="height:3px;flex:1;border-radius:2px;background:var(--input-border)" id="ps1"></div>
                                <div style="height:3px;flex:1;border-radius:2px;background:var(--input-border)" id="ps2"></div>
                                <div style="height:3px;flex:1;border-radius:2px;background:var(--input-border)" id="ps3"></div>
                                <div style="height:3px;flex:1;border-radius:2px;background:var(--input-border)" id="ps4"></div>
                            </div>
                            <div style="font-size:.68rem;margin-top:.3rem;font-weight:600" id="pwStrengthLabel"></div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fs-label">Confirm New Password</label>
                            <input type="password" class="form-control fs-input" id="pw-confirm" placeholder="Repeat new password" />
                        </div>
                        <button class="btn btn-fs rounded-pill px-4" onclick="changePassword()">
                            <i class="ti ti-lock me-1"></i>Update Password
                        </button>
                    </div>
                </div>
                <div class="col-lg-6">
                    <!-- Edit Profile (quick) -->
                    <div class="fs-card mb-3">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1.25rem">Edit Profile</div>
                        <div class="alert fs-alert" id="settingsProfileAlert" role="alert"></div>
                        <!-- Avatar -->
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar-upload-wrap" onclick="document.getElementById('avatarInput2').click()">
                                <div class="profile-avatar-lg" id="settingsAvatarLg" style="width:60px;height:60px;font-size:1.4rem">
                                    <span id="settingsInitials"><?= $initials ?></span>
                                </div>
                                <div class="avatar-upload-overlay"><i class="ti ti-camera"></i></div>
                            </div>
                            <div>
                                <div style="font-weight:700;font-size:.9rem;color:var(--text-primary)" id="settingsName"><?= $fullName ?></div>
                                <div style="font-size:.72rem;color:var(--text-dimmed);margin-top:.2rem">Click to change photo</div>
                            </div>
                        </div>
                        <input type="file" id="avatarInput2" accept="image/*" style="display:none" onchange="handleAvatarUpload(this)" />
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fs-label">First Name</label>
                                <input type="text" class="form-control fs-input" id="s-fname" value="<?= htmlspecialchars($userRow['first_name'] ?? '') ?>" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fs-label">Last Name</label>
                                <input type="text" class="form-control fs-input" id="s-lname" value="<?= htmlspecialchars($userRow['last_name'] ?? '') ?>" />
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fs-label">Gender</label>
                            <select class="form-select fs-input" id="s-gender">
                                <option value="">Prefer not to say</option>
                                <option value="male" <?= ($userRow['gender'] ?? '') === 'male'      ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($userRow['gender'] ?? '') === 'female'    ? 'selected' : '' ?>>Female</option>
                                <option value="nonbinary" <?= ($userRow['gender'] ?? '') === 'nonbinary' ? 'selected' : '' ?>>Non-binary</option>
                                <option value="other" <?= ($userRow['gender'] ?? '') === 'other'     ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fs-label">Birthdate</label>
                            <input type="date" class="form-control fs-input" id="s-birthdate"
                                value="<?= htmlspecialchars($userRow['birthdate'] ?? '') ?>"
                                max="<?= date('Y-m-d', strtotime('-16 years')) ?>" />
                        </div>
                        <button class="btn btn-fs rounded-pill px-4" onclick="saveProfileFromSettings()">
                            <i class="ti ti-device-floppy me-1"></i>Save Profile
                        </button>
                    </div>

                    <!-- Appearance -->
                    <div class="fs-card mb-3">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1.25rem">Appearance</div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div style="font-weight:600;font-size:.9rem;color:var(--text-primary)">Dark Mode</div>
                                <div style="font-size:.75rem;color:var(--text-muted);margin-top:.15rem">Toggle between dark and light theme</div>
                            </div>
                            <button class="theme-pill" onclick="toggleTheme()" aria-label="Toggle theme">
                                <div class="theme-pill-knob"></div>
                            </button>
                        </div>
                    </div>

                    <!-- Account -->
                    <div class="fs-card" style="border-color:rgba(204,26,26,.2)">
                        <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);margin-bottom:1.25rem">Account</div>
                        <div class="d-flex flex-column gap-2">
                            <div style="font-size:.82rem;color:var(--text-muted)">Signed in as <strong style="color:var(--text-primary)"><?= htmlspecialchars($userRow['email']) ?></strong></div>
                            <a href="logout.php" class="btn btn-outline-danger rounded-pill d-flex align-items-center gap-2 justify-content-center mt-2">
                                <i class="ti ti-logout"></i> Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ QR VIEW MODAL ══ -->
        <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:22px">
                    <div class="modal-header" style="border-color:var(--card-border);padding:1.25rem 1.5rem .75rem">
                        <h5 class="modal-title" style="font-weight:800;color:var(--text-primary)">
                            <i class="ti ti-qrcode me-2" style="color:var(--fs-red)" aria-hidden="true"></i>My Check-in QR
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center" style="padding:1.5rem 1.5rem 1rem">
                        <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:1.5rem;line-height:1.6">
                            Show this to front-desk staff, or scan it at a self-service kiosk to log your visit.
                        </p>
                        <div style="display:flex;justify-content:center;margin-bottom:1.25rem">
                            <div class="qr-modal-wrap">
                                <?php
                                if (file_exists(__DIR__ . '/' . $qrFile)):
                                ?>
                                    <img src="<?= $qrFile ?>" alt="QR Code" />
                                <?php else: ?>
                                    <div id="qrModalCode"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="font-weight:800;font-size:1rem;color:var(--text-primary)"><?= $fullName ?></div>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.2rem">
                            Member #<?= str_pad($userId, 5, '0', STR_PAD_LEFT) ?>
                            <?= $hasActiveMembership ? ' · ' . htmlspecialchars($mem['plan_label']) : '' ?>
                        </div>
                        <div class="qr-expires-badge">
                            <i class="ti ti-clock" style="font-size:.75rem" aria-hidden="true"></i> QR refreshes daily
                        </div>
                    </div>
                    <div class="modal-footer" style="border-color:var(--card-border);justify-content:center;gap:.65rem;padding:.75rem 1.5rem 1.25rem">
                        <button class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════ NOTIFICATION PANEL ════ -->
        <div class="notif-overlay" id="notifOverlay" onclick="closeNotifPanel()"></div>

        <aside class="notif-panel" id="notifPanel" aria-label="Notifications">

            <div class="notif-panel-header">
                <div class="notif-panel-title">
                    <i class="ti ti-bell"></i>
                    Notifications
                    <span class="notif-unread-chip" id="notifUnreadChip" style="display:none">0</span>
                </div>
                <div class="notif-panel-actions">
                    <button class="notif-mark-all-btn" id="markAllBtn"
                        onclick="markAllRead()" style="display:none">
                        Mark all read
                    </button>
                    <button class="notif-close-btn" onclick="closeNotifPanel()"
                        aria-label="Close notifications">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
            </div>

            <div class="notif-filter-bar">
                <button class="notif-filter-btn active"
                    data-filter="all" onclick="filterNotifs('all', this)">All</button>
                <button class="notif-filter-btn"
                    data-filter="unread" onclick="filterNotifs('unread', this)">Unread</button>
            </div>

            <div class="notif-list" id="notifList">
                <!-- Populated by JS -->
            </div>

            <div class="notif-panel-foot">
                <div style="font-size:.7rem;color:var(--text-dimmed);text-align:center">
                    Notifications refresh on page load
                </div>
            </div>

        </aside>

        <!-- ══ SHOP TAB ══ -->
        <div class="page-section" id="tab-shop">
            <!-- Search + Filter -->
            <div class="row g-2 mb-4">
                <div class="col-md-7">
                    <div style="position:relative">
                        <i class="ti ti-search" style="position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:1rem"></i>
                        <input type="text" id="shopSearch" placeholder="Search products…" oninput="debounceShop()"
                            style="width:100%;padding:.55rem .85rem .55rem 2.4rem;background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;color:var(--input-color);font-size:.875rem;outline:none">
                    </div>
                </div>
                <div class="col-md-5">
                    <select id="shopCategory" onchange="loadShopProducts()"
                        style="width:100%;padding:.55rem .85rem;background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;color:var(--input-color);font-size:.875rem;outline:none;appearance:none">
                        <option value="">All Categories</option>
                    </select>
                </div>
            </div>
            <div id="shopProductGrid" class="row g-3"></div>
            <div id="shopEmpty" style="display:none;text-align:center;padding:4rem 1rem;color:var(--text-muted)">
                <i class="ti ti-mood-empty" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
                <div>No products found.</div>
            </div>
        </div>

        <!-- ══ CART MODAL ══ -->
        <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:18px">
                    <div class="modal-header" style="border-color:var(--card-border)">
                        <h5 class="modal-title" id="cartModalLabel" style="font-weight:800;color:var(--text-primary);display:flex;align-items:center;gap:0.5rem">
                            <i class="ti ti-shopping-cart" style="color:var(--fs-red)"></i> Shopping Cart
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="cartContent" style="padding:0">
                        <!-- Cart items will be loaded here dynamically -->
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$isPending): ?>
            <!-- Cart Floating Action Button -->
            <button class="cart-fab" id="cartFabBtn" data-bs-toggle="modal" data-bs-target="#cartModal" aria-label="View Cart" onclick="loadCart()">
                <i class="ti ti-shopping-cart"></i>
                <span class="cart-fab-badge" id="cartFabBadge" style="<?= $shopCartCount > 0 ? '' : 'display:none' ?>"><?= $shopCartCount ?></span>
            </button>
        <?php endif; ?>


        <!-- ══ ORDERS TAB ══ -->
        <div class="page-section" id="tab-orders">
            <div id="ordersContent"></div>
        </div>

        <!-- Custom Confirm Modal -->
        <div class="modal fade" id="fsConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
                <div class="modal-content" style="border-radius:16px;border:1px solid var(--card-border);background:var(--card-bg)">
                    <div class="modal-body" style="padding:1.75rem 1.5rem 1rem">
                        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                            <div id="fsConfirmIcon" style="width:40px;height:40px;border-radius:50%;background:rgba(46,204,113,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <i class="ti ti-circle-check" style="font-size:1.3rem;color:#2ecc71"></i>
                            </div>
                            <div>
                                <div id="fsConfirmTitle" style="font-weight:700;font-size:.95rem;color:var(--text-primary)"></div>
                                <div id="fsConfirmMsg" style="font-size:.82rem;color:var(--text-muted);margin-top:.15rem"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding:.85rem 1.5rem;border-top:1px solid var(--card-border);gap:.5rem;justify-content:flex-end">
                        <button class="btn btn-outline-secondary rounded-pill px-4" style="font-size:.82rem" data-bs-dismiss="modal">Cancel</button>
                        <button id="fsConfirmOkBtn" class="btn rounded-pill px-4" style="font-size:.82rem;font-weight:700;background:var(--fs-red);border:none;color:#fff">Confirm</button>
                    </div>
                </div>
            </div>
        </div>


        <style>


        /* ── SHOP STYLES ── */

        .shop-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:16px;overflow:hidden;transition:transform .2s,box-shadow .2s,border-color .2s;height:100%;display:flex;flex-direction:column}
        .shop-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,.3);border-color:rgba(204,26,26,.3)}
        .shop-card-img-ph{width:100%;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(204,26,26,.08),rgba(204,26,26,.02));color:var(--text-muted);font-size:3.5rem}
        .shop-card-img{width:100%;aspect-ratio:1/1;object-fit:cover}
        .shop-card-body{padding:1rem;flex:1;display:flex;flex-direction:column}
        .shop-cat-badge{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--fs-red);background:rgba(204,26,26,.1);padding:.2rem .55rem;border-radius:6px;display:inline-block;margin-bottom:.45rem}
        .shop-card-name{font-size:.92rem;font-weight:700;margin-bottom:.35rem;color:var(--text-primary)}
        .shop-card-desc{font-size:.78rem;color:var(--text-muted);line-height:1.5;flex:1;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .shop-card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:.85rem;gap:.5rem}
        .shop-price{font-size:1.1rem;font-weight:800;color:var(--fs-red)}
        .cart-row{display:flex;align-items:center;gap:1rem;padding:1rem;border-bottom:1px solid var(--card-border)}
        .cart-row:last-child{border-bottom:none}
        .cart-thumb{width:64px;height:64px;border-radius:10px;object-fit:cover;flex-shrink:0}
        .cart-thumb-ph{width:64px;height:64px;border-radius:10px;background:rgba(204,26,26,.08);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:1.5rem;flex-shrink:0}
        .qty-ctrl{display:flex;align-items:center;gap:.35rem}
        .qty-btn{width:28px;height:28px;border-radius:7px;border:1px solid var(--card-border);background:var(--input-bg);color:var(--text-primary);font-size:.9rem;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s}
        .qty-btn:hover{background:rgba(204,26,26,.15)}
        .qty-val{width:32px;text-align:center;font-weight:700;font-size:.88rem}
        .order-status-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;font-weight:700;padding:.25rem .65rem;border-radius:8px;text-transform:uppercase;letter-spacing:.4px}
        .status-pending{background:rgba(255,193,7,.15);color:#ffc107}
        .status-processing{background:rgba(13,110,253,.15);color:#4a9eff}
        .status-shipped{background:rgba(111,66,193,.15);color:#a066f5}
        .status-delivered{background:rgba(25,135,84,.15);color:#2ecc71}
        .status-cancelled{background:rgba(220,53,69,.15);color:#ff6b6b}
        .status-completed{background:rgba(46,204,113,.15);color:#2ecc71}
        .status-out_for_delivery,.status-out-for-delivery{background:rgba(111,66,193,.15);color:#a066f5}
        .status-ready_for_pickup,.status-ready-for-pickup{background:rgba(255,193,7,.15);color:#ffc107}
        .status-picked_up,.status-picked-up{background:rgba(46,204,113,.12);color:#58d68d}

        /* ── CART FAB ── */
        .cart-fab {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #cc1a1a, #ff4040);
            color: #fff;
            box-shadow: 0 4px 16px rgba(204, 26, 26, 0.4);
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .cart-fab:hover {
            transform: scale(1.08) translateY(-2px);
            box-shadow: 0 6px 20px rgba(204, 26, 26, 0.6);
        }
        .cart-fab:active {
            transform: scale(0.95);
        }
        .cart-fab i {
            font-size: 1.6rem;
        }
        .cart-fab-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #fff;
            color: #cc1a1a;
            font-size: 0.75rem;
            font-weight: 800;
            min-width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            border: 2px solid #cc1a1a;
        }
        </style>
    </main>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF = <?= json_encode($csrf) ?>;
        const USER_ID = <?= $userId ?>;
        let attendanceDates = <?= json_encode($attendanceDates, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let attendanceTotal = <?= (int) $attendanceTotal ?>;
        let currentStreak = <?= (int) $currentStreak ?>;
        const HAS_ACTIVE_MEMBERSHIP = <?= $hasActiveMembership ? 'true' : 'false' ?>;

        // Workout programs data
        const programs = <?= json_encode($workoutPrograms, JSON_HEX_TAG) ?>;

        /* ════════════════════════════════════
   NOTIFICATION SYSTEM
════════════════════════════════════ */
        const ALL_NOTIFS = <?= json_encode($notifications, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        // Persist read state per user in localStorage
        let readNotifIds = new Set(
            JSON.parse(localStorage.getItem('fs_read_notifs_' + USER_ID) || '[]')
        );
        let notifFilter = 'all';

        /* ── Helpers ── */
        function getUnreadCount() {
            return ALL_NOTIFS.filter(n => !readNotifIds.has(n.id)).length;
        }

        function saveReadIds() {
            localStorage.setItem('fs_read_notifs_' + USER_ID, JSON.stringify([...readNotifIds]));
        }

        /* ── Update all badges ── */
        function updateNotifBadges() {
            const count = getUnreadCount();

            // All bell badge elements
            ['notifBadgeMobile', 'notifBadgeDesktop'].forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                if (count > 0) {
                    el.textContent = count > 99 ? '99+' : count;
                    el.style.display = 'flex';
                } else {
                    el.style.display = 'none';
                }
            });

            // Panel header chip
            const chip = document.getElementById('notifUnreadChip');
            if (chip) {
                chip.textContent = count;
                chip.style.display = count > 0 ? 'inline-block' : 'none';
            }

            // "Mark all read" button visibility
            const markBtn = document.getElementById('markAllBtn');
            if (markBtn) markBtn.style.display = count > 0 ? 'block' : 'none';
        }

        /* ── Render the list ── */
        function renderNotifList() {
            const list = document.getElementById('notifList');
            if (!list) return;

            let notifs = ALL_NOTIFS;
            if (notifFilter === 'unread') {
                notifs = notifs.filter(n => !readNotifIds.has(n.id));
            }

            if (notifs.length === 0) {
                const msg = notifFilter === 'unread' ?
                    'All caught up — no unread notifications.' :
                    'No notifications right now. Check back later.';
                list.innerHTML = `
            <div class="notif-empty">
                <i class="ti ti-bell-off"></i>
                <p>${msg}</p>
            </div>`;
                return;
            }

            list.innerHTML = notifs.map(n => {
                const isUnread = !readNotifIds.has(n.id);
                const actionHtml = n.action ? `
            <button class="notif-action-link"
                    onclick="notifAction('${n.id}','${n.action}', event)">
                ${n.action_label}
            </button>` : '';

                return `
            <div class="notif-item ${isUnread ? 'unread' : ''}"
                 onclick="markRead('${n.id}')">
                <div class="notif-icon ${n.type}">
                    <i class="ti ${n.icon}"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title">${n.title}</div>
                    <div class="notif-body-text">${n.body}</div>
                    <div class="notif-meta-row">
                        <span class="notif-time">${n.time}</span>
                        ${actionHtml}
                    </div>
                </div>
            </div>`;
            }).join('');
        }

        /* ── Actions ── */
        function markRead(id) {
            readNotifIds.add(id);
            saveReadIds();
            renderNotifList();
            updateNotifBadges();
        }

        function markAllRead() {
            ALL_NOTIFS.forEach(n => readNotifIds.add(n.id));
            saveReadIds();
            renderNotifList();
            updateNotifBadges();
        }

        function filterNotifs(filter, btn) {
            notifFilter = filter;
            document.querySelectorAll('.notif-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderNotifList();
        }

        function notifAction(id, tab, e) {
            e.stopPropagation();
            markRead(id);
            closeNotifPanel();
            setTimeout(() => showTab(tab, null), 220);
        }

        /* ── Panel open / close ── */
        function toggleNotifPanel() {
            const panel = document.getElementById('notifPanel');
            panel.classList.contains('open') ? closeNotifPanel() : openNotifPanel();
        }

        function openNotifPanel() {
            renderNotifList();
            document.getElementById('notifPanel').classList.add('open');
            document.getElementById('notifOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeNotifPanel() {
            document.getElementById('notifPanel').classList.remove('open');
            document.getElementById('notifOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Initialise badges on load
        updateNotifBadges();

        /* ── TAB NAV ── */
        function showTab(id, btn) {
            // Hide the cart modal if it's open when moving to another tab
            const cartModalEl = document.getElementById('cartModal');
            if (cartModalEl) {
                const modalInstance = bootstrap.Modal.getInstance(cartModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }

            if (id === 'cart') {
                if (cartModalEl) {
                    loadCart();
                    bootstrap.Modal.getOrCreateInstance(cartModalEl).show();
                }
                return;
            }

            const section = document.getElementById('tab-' + id);
            if (!section) return;

            document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.page-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.sb-nav-item').forEach(n => n.classList.remove('active'));
            section.classList.add('active');
            document.querySelector(`.page-tab[data-tab="${id}"]`)?.classList.add('active');
            if (btn) btn.classList.add('active');
            else document.querySelector(`.sb-nav-item[onclick*="'${id}'"]`)?.classList.add('active');
            history.replaceState(null, '', '#' + id);
            if (window.innerWidth < 992) closeSidebar();
            // Sync settings fields when opening settings
            if (id === 'settings') syncSettingsFields();
            // Lazy-load shop tabs
            if (id === 'shop')   { window._modalQty = 1; loadShopProducts(); }
            if (id === 'orders') loadOrders();
        }
        document.querySelectorAll('.page-tab').forEach(tab => {
            tab.addEventListener('click', () => showTab(tab.dataset.tab, tab));
        });

        /* ── SIDEBAR ── */
        const initialTab = location.hash.replace('#', '');
        if (['dashboard', 'programs', 'billing', 'schedule', 'feedback', 'settings', 'shop', 'cart', 'orders'].includes(initialTab)) {
            if (initialTab === 'cart') {
                showTab('shop', null);
                setTimeout(() => {
                    loadCart();
                    const cartModalEl = document.getElementById('cartModal');
                    if (cartModalEl) bootstrap.Modal.getOrCreateInstance(cartModalEl).show();
                }, 100);
            } else {
                showTab(initialTab, null);
            }
        }

        /* ══════════════════════════════════════════════
           SHOP MODULE
        ══════════════════════════════════════════════ */
        const SHOP_CSRF = CSRF;
        let shopProducts = [];
        let shopSearchTimer = null;
        window._modalQty = 1;

        // Debounce search
        function debounceShop() {
            clearTimeout(shopSearchTimer);
            shopSearchTimer = setTimeout(loadShopProducts, 350);
        }

        // Load products
        async function loadShopProducts() {
            const search   = document.getElementById('shopSearch')?.value ?? '';
            const category = document.getElementById('shopCategory')?.value ?? '';
            try {
                const res  = await fetch('handlers/shop_handler.php?' + new URLSearchParams({ action:'get_products', search, category }));
                const data = await res.json();
                if (!data.success) return;
                shopProducts = data.products;
                renderShopProducts(data.products);
                populateCategoryFilter(data.categories, category);
            } catch(e) { console.error('Shop load', e); }
        }

        function populateCategoryFilter(cats, active) {
            const sel = document.getElementById('shopCategory');
            if (!sel) return;
            const cur = active || sel.value;
            sel.innerHTML = '<option value="">All Categories</option>';
            cats.forEach(c => {
                const o = document.createElement('option');
                o.value = c; o.textContent = c;
                if (c === cur) o.selected = true;
                sel.appendChild(o);
            });
        }

        function renderShopProducts(products) {
            const grid  = document.getElementById('shopProductGrid');
            const empty = document.getElementById('shopEmpty');
            if (!grid) return;
            if (!products.length) { grid.innerHTML = ''; empty.style.display = 'block'; return; }
            empty.style.display = 'none';
            grid.innerHTML = products.map(p => {
                const inStock = parseInt(p.stock) > 0;
                const img = p.image
                    ? `<img src="${p.image}" class="shop-card-img" alt="${shEsc(p.name)}" loading="lazy">`
                    : `<div class="shop-card-img-ph"><i class="ti ti-package"></i></div>`;
                const stockBadge = inStock
                    ? `<span style="font-size:.68rem;font-weight:600;color:#2ecc71;background:rgba(46,204,113,.12);padding:.18rem .5rem;border-radius:6px">${p.stock} left</span>`
                    : `<span style="font-size:.68rem;font-weight:600;color:#ff6b6b;background:rgba(255,107,107,.12);padding:.18rem .5rem;border-radius:6px">Sold Out</span>`;
                return `<div class="col-6 col-md-4 col-lg-3">
                    <div class="shop-card">
                        <div onclick="openProductModal(${p.id})" style="cursor:pointer">${img}</div>
                        <div class="shop-card-body">
                            <span class="shop-cat-badge">${shEsc(p.category)}</span>
                            <div class="shop-card-name" onclick="openProductModal(${p.id})" style="cursor:pointer">${shEsc(p.name)}</div>
                            <div class="shop-card-desc">${shEsc(p.description)}</div>
                            <div class="shop-card-footer">
                                <span class="shop-price">&#8369;${parseFloat(p.price).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>
                                ${stockBadge}
                            </div>
                            <button class="btn-fs w-100 mt-2" style="border-radius:9px;padding:.42rem;font-size:.82rem"
                                ${!inStock ? 'disabled style="opacity:.4;cursor:not-allowed"' : `onclick="quickAddToCart(${p.id},'${shEscAttr(p.name)}')"`}>
                                <i class="ti ti-shopping-cart-plus"></i> ${inStock ? 'Add to Cart' : 'Out of Stock'}
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function openProductModal(id) {
            const p = shopProducts.find(x => parseInt(x.id) === id);
            if (!p) return;
            window._modalQty = 1;
            const inStock = parseInt(p.stock) > 0;
            document.getElementById('productModalTitle').textContent = p.name;
            document.getElementById('productModalBody').innerHTML = `
                <div class="row g-0">
                    <div class="col-md-5">
                        ${ p.image
                            ? `<img src="${p.image}" style="width:100%;aspect-ratio:1/1;object-fit:cover" alt="${shEsc(p.name)}">`
                            : `<div style="width:100%;aspect-ratio:1/1;display:flex;align-items:center;justify-content:center;background:rgba(204,26,26,.06);font-size:5rem;color:var(--text-muted)"><i class="ti ti-photo"></i></div>`
                        }
                    </div>
                    <div class="col-md-7 p-4">
                        <span class="shop-cat-badge">${shEsc(p.category)}</span>
                        <h4 class="fw-bold my-2">${shEsc(p.name)}</h4>
                        <p style="font-size:.88rem;color:var(--text-muted);line-height:1.7">${shEsc(p.description)}</p>
                        <div style="font-size:1.6rem;font-weight:900;color:var(--fs-red);margin:.75rem 0">
                            &#8369;${parseFloat(p.price).toLocaleString('en-PH',{minimumFractionDigits:2})}
                        </div>
                        <div style="margin-bottom:1rem;font-size:.82rem;color:${ inStock?'#2ecc71':'#ff6b6b' }">
                            <i class="ti ti-packages"></i> ${ inStock ? p.stock+' units available' : 'Out of stock' }
                        </div>
                        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
                            <div class="qty-ctrl">
                                <button class="qty-btn" onclick="modalQtyChange(-1)"><i class="ti ti-minus"></i></button>
                                <span class="qty-val" id="modalQtyDisplay">1</span>
                                <button class="qty-btn" onclick="modalQtyChange(1)"><i class="ti ti-plus"></i></button>
                            </div>
                            <button class="btn-fs flex-fill" style="border-radius:10px;padding:.55rem"
                                ${ !inStock ? 'disabled' : '' }
                                onclick="addToCartFromModal(${p.id},'${shEscAttr(p.name)}')">
                                <i class="ti ti-shopping-cart-plus"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
        }
        function modalQtyChange(d) {
            window._modalQty = Math.max(1, (window._modalQty||1) + d);
            const el = document.getElementById('modalQtyDisplay');
            if (el) el.textContent = window._modalQty;
        }
        async function quickAddToCart(pid, name) { await doAddToCart(pid, 1, name); }
        async function addToCartFromModal(pid, name) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).hide();
            await doAddToCart(pid, window._modalQty || 1, name);
        }
        async function doAddToCart(pid, qty, name) {
            try {
                const form = new FormData();
                form.append('action','add_to_cart'); form.append('csrf_token',SHOP_CSRF);
                form.append('product_id',pid); form.append('quantity',qty);
                const data = await (await fetch('handlers/shop_handler.php',{method:'POST',body:form})).json();
                if (data.success) { updateCartBadge(data.cart_count); shopToast('success', `${name} added to cart!`); }
                else shopToast('error', data.message || 'Could not add to cart.');
            } catch { shopToast('error','Network error.'); }
        }

        function updateCartBadge(count) {
            ['sbCartBadge','shopHeaderCartBadge','cartFabBadge'].forEach(id => {
                const b = document.getElementById(id);
                if (!b) return;
                b.textContent = count;
                b.style.display = count > 0 ? '' : 'none';
            });
        }

        // Cart
        async function loadCart() {
            try {
                const data = await (await fetch('handlers/shop_handler.php?action=get_cart')).json();
                if (!data.success) return;
                updateCartBadge(data.count);
                renderCart(data.items, data.total);
            } catch { }
        }
        function renderCart(items, total) {
            const el = document.getElementById('cartContent');
            if (!el) return;
            if (!items.length) {
                el.innerHTML = `<div style="text-align:center;padding:4rem 1rem;color:var(--text-muted)">
                    <i class="ti ti-shopping-cart-off" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
                    <div style="font-size:1rem;font-weight:600">Your cart is empty</div>
                    <div style="font-size:.85rem;margin-top:.4rem">Browse the shop and add items to get started.</div>
                    <button class="btn-fs mt-3" style="border-radius:10px;padding:.5rem 1.5rem" onclick="showTab('shop',null)">
                        <i class="ti ti-shopping-bag"></i> Browse Shop
                    </button></div>`;
                return;
            }
            const token = 'chk_' + Date.now();
            el.innerHTML = `<div style="overflow:hidden">
                ${items.map(item => `
                <div class="cart-row">
                    ${ item.image ? `<img src="${item.image}" class="cart-thumb" alt="">` : `<div class="cart-thumb-ph"><i class="ti ti-package"></i></div>` }
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.9rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${shEsc(item.name)}</div>
                        <div style="font-size:.78rem;color:var(--text-muted)">&#8369;${parseFloat(item.price).toLocaleString('en-PH',{minimumFractionDigits:2})} each</div>
                    </div>
                    <div class="qty-ctrl">
                        <button class="qty-btn" onclick="cartQty(${item.id},${parseInt(item.quantity)-1})"><i class="ti ti-minus"></i></button>
                        <span class="qty-val">${item.quantity}</span>
                        <button class="qty-btn" onclick="cartQty(${item.id},${parseInt(item.quantity)+1})"><i class="ti ti-plus"></i></button>
                    </div>
                    <div style="min-width:80px;text-align:right">
                        <div style="font-size:.92rem;font-weight:800;color:var(--fs-red)">&#8369;${(parseFloat(item.price)*parseInt(item.quantity)).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
                        <button style="background:none;border:none;color:rgba(255,80,80,.6);font-size:.75rem;cursor:pointer" onclick="cartRemove(${item.id})">
                            <i class="ti ti-trash"></i> Remove
                        </button>
                    </div>
                </div>`).join('')}
                <div style="padding:1.25rem 1rem;border-top:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;background:var(--input-bg)">
                    <div>
                        <div style="font-size:.8rem;color:var(--text-muted)">Subtotal</div>
                        <div style="font-size:1.4rem;font-weight:900;color:var(--fs-red)">&#8369;${parseFloat(total).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
                    </div>
                    <button class="btn-fs" style="border-radius:12px;padding:.6rem 1.5rem;font-size:.9rem;background:linear-gradient(135deg,#cc1a1a,#ff4040)" onclick="window.location.href='checkout.php'">
                        <i class="ti ti-credit-card"></i> Checkout
                    </button>
                </div>
            </div>`;
        }
        async function cartQty(id, qty) {
            const f = new FormData(); f.append('action','update_cart'); f.append('csrf_token',SHOP_CSRF); f.append('cart_id',id); f.append('quantity',qty);
            await fetch('handlers/shop_handler.php',{method:'POST',body:f}); loadCart();
        }
        async function cartRemove(id) {
            const f = new FormData(); f.append('action','remove_from_cart'); f.append('csrf_token',SHOP_CSRF); f.append('cart_id',id);
            await fetch('handlers/shop_handler.php',{method:'POST',body:f}); loadCart();
        }


        // Orders
        async function loadOrders() {
            try {
                const data = await (await fetch('handlers/shop_handler.php?action=get_orders')).json();
                window._ordersData = data;
                const el = document.getElementById('ordersContent');
                if (!el) return;
                if (!data.success || !data.orders.length) {
                    el.innerHTML = `<div style="text-align:center;padding:4rem 1rem;color:var(--text-muted)">
                        <i class="ti ti-package-off" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
                        <div>No orders yet.</div>
                        <button class="btn-fs mt-3" style="border-radius:10px;padding:.5rem 1.5rem" onclick="showTab('shop',null)">
                            <i class="ti ti-shopping-bag"></i> Start Shopping
                        </button></div>`;
                    return;
                }
                const statusIcons = {
                    pending:'ti-clock',processing:'ti-loader-2',out_for_delivery:'ti-truck',
                    delivered:'ti-circle-check',ready_for_pickup:'ti-building-store',
                    picked_up:'ti-checks',cancelled:'ti-ban',completed:'ti-rosette-discount-check'
                };
                const payColors = {pending:'#ffc107',paid:'#2ecc71',rejected:'#ff6b6b'};
                el.innerHTML = `<div style="display:flex;flex-direction:column;gap:.75rem">` + data.orders.map(o => {
                    const items = (data.details[o.id] || []);
                    const d = new Date(o.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'});
                    let addr = null; try { if(o.delivery_address) addr = JSON.parse(o.delivery_address); } catch(e){}
                    const isPickup = o.fulfillment_method === 'pickup';
                    const fulfillLabel = isPickup
                        ? `Branch Pick-Up${o.branch_name ? ' · ' + shEsc(o.branch_name) : ''}`
                        : `Delivery${addr ? ' · ' + shEsc(addr.city||'') + ', ' + shEsc(addr.region||'') : ''}`;
                    const fulfillIcon = isPickup ? 'ti-building-store' : 'ti-truck';
                    const statusLabel = o.status.replace(/_/g,' ');
                    const payLabel = o.payment_method.replace(/_/g,' ') + ' · ' + o.payment_status;
                    const payColor = payColors[o.payment_status] || '#aaa';
                    const isCompleted = o.status === 'completed';
                    const canReceive = ['processing','out_for_delivery','ready_for_pickup','delivered','picked_up'].includes(o.status);

                    // Items summary line shown in collapsed header
                    const itemSummary = items.slice(0,2).map(it => `${shEsc(it.name)} ×${it.quantity}`).join(', ')
                        + (items.length > 2 ? ` +${items.length - 2} more` : '');

                    // Full items list (inside collapsible body)
                    const itemsHtml = items.map(it => `
                        <div style="display:flex;align-items:center;gap:.7rem;padding:.4rem 0;border-bottom:1px solid var(--card-border)">
                            ${ it.image
                                ? `<img src="${it.image}" style="width:38px;height:38px;border-radius:8px;object-fit:cover;flex-shrink:0" alt="">`
                                : `<div style="width:38px;height:38px;border-radius:8px;background:rgba(204,26,26,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="ti ti-package" style="font-size:1.1rem;color:var(--text-muted)"></i></div>`
                            }
                            <div style="flex:1;min-width:0">
                                <div style="font-size:.85rem;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${shEsc(it.name)}</div>
                                <div style="font-size:.75rem;color:var(--text-muted)">×${it.quantity} &nbsp;·&nbsp; &#8369;${parseFloat(it.price).toLocaleString('en-PH',{minimumFractionDigits:2})}/ea</div>
                            </div>
                            <div style="font-size:.85rem;font-weight:700;color:var(--text-primary);white-space:nowrap">&#8369;${(parseFloat(it.price)*it.quantity).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
                        </div>`).join('');

                    const btnStyle = `padding:.32rem .8rem;font-size:.75rem;border-radius:8px;font-weight:600;display:inline-flex;align-items:center;gap:.3rem;cursor:pointer;border:1px solid var(--card-border);background:var(--input-bg);color:var(--text-primary);transition:all .15s;text-decoration:none`;
                    const receiveBtn = canReceive ? `
                        <button onclick="markOrderReceived(${o.id}, this)" style="${btnStyle};border-color:rgba(46,204,113,.5);background:rgba(46,204,113,.08);color:#2ecc71"
                            onmouseover="this.style.background='rgba(46,204,113,.18)'" onmouseout="this.style.background='rgba(46,204,113,.08)'">
                            <i class="ti ti-circle-check"></i> Order Received
                        </button>` : '';
                    const cancelBtn = o.status === 'pending' ? `
                        <button onclick="cancelOrder(${o.id}, this)" style="${btnStyle};border-color:rgba(255,107,107,.4);background:rgba(255,107,107,.07);color:#ff6b6b"
                            onmouseover="this.style.background='rgba(255,107,107,.18)'" onmouseout="this.style.background='rgba(255,107,107,.07)'">
                            <i class="ti ti-x"></i> Cancel Order
                        </button>` : '';

                    return `<div style="background:var(--card-bg);border:1px solid ${isCompleted?'rgba(46,204,113,.25)':'var(--card-border)'};border-radius:14px;overflow:hidden">
                        <div onclick="toggleOrderItems(${o.id})" style="padding:.85rem 1.1rem;cursor:pointer;display:flex;align-items:flex-start;gap:.75rem;justify-content:space-between">
                            <div style="flex:1;min-width:0">
                                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.3rem">
                                    <span style="font-weight:800;font-size:.9rem;color:var(--text-primary)">Order #${o.id}</span>
                                    <span style="font-size:.72rem;color:var(--text-muted)">${d}</span>
                                    <span class="order-status-badge status-${o.status.replace(/_/g,'-')}">
                                        <i class="ti ${statusIcons[o.status]||'ti-circle'}"></i> ${statusLabel}
                                    </span>
                                </div>
                                <div style="font-size:.78rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:.2rem">${itemSummary}</div>
                                ${o.status === 'cancelled' && o.cancel_reason ? `<div style="font-size:.72rem;color:#ff6b6b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:.2rem"><i class="ti ti-message-x" style="font-size:.75rem"></i> Reason: ${shEsc(o.cancel_reason)}</div>` : ''}
                                <div style="font-size:.72rem;color:var(--text-muted);display:flex;align-items:center;gap:.3rem;flex-wrap:wrap">
                                    <i class="ti ${fulfillIcon}" style="font-size:.8rem"></i>${fulfillLabel}
                                    <span>&nbsp;·&nbsp;</span>
                                    <span style="color:${payColor}">${payLabel}</span>
                                </div>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;flex-shrink:0">
                                <span style="font-size:1rem;font-weight:800;color:var(--fs-red)">&#8369;${parseFloat(o.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>
                                <i class="ti ti-chevron-down" id="chevron-${o.id}" style="font-size:.85rem;color:var(--text-muted);transition:transform .25s"></i>
                            </div>
                        </div>
                        <div id="order-body-${o.id}" style="display:none;border-top:1px solid var(--card-border)">
                            <div style="padding:.6rem 1.1rem">${itemsHtml}</div>
                            <div style="padding:.65rem 1.1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;background:rgba(0,0,0,.04)">
                                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                                    <a href="checkout.php?order_id=${o.id}" style="${btnStyle}"
                                        onmouseover="this.style.borderColor='var(--fs-red)';this.style.color='var(--fs-red)'"
                                        onmouseout="this.style.borderColor='var(--card-border)';this.style.color='var(--text-primary)'">
                                        <i class="ti ti-eye"></i> Details
                                    </a>
                                    <button onclick="printOrderReceipt(${o.id})" style="${btnStyle}"
                                        onmouseover="this.style.borderColor='#4a9eff';this.style.color='#4a9eff'"
                                        onmouseout="this.style.borderColor='var(--card-border)';this.style.color='var(--text-primary)'">
                                        <i class="ti ti-printer"></i> Receipt
                                    </button>
                                    ${receiveBtn}
                                    ${cancelBtn}
                                </div>
                                <span style="font-size:.75rem;font-weight:800;color:var(--fs-red)">Total: &#8369;${parseFloat(o.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</span>
                            </div>
                        </div>
                    </div>`;
                }).join('') + '</div>';
            } catch { }
        }

        function toggleOrderItems(id) {
            const body    = document.getElementById('order-body-' + id);
            const chevron = document.getElementById('chevron-' + id);
            if (!body) return;
            const open = body.style.display === 'none';
            body.style.display      = open ? 'block' : 'none';
            chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
        }

        function cancelOrder(id, triggerBtn) {
            // Build a lightweight overlay modal to collect the cancellation reason
            const existing = document.getElementById('cancelReasonOverlay');
            if (existing) existing.remove();

            const overlay = document.createElement('div');
            overlay.id = 'cancelReasonOverlay';
            overlay.style.cssText = 'position:fixed;inset:0;z-index:10500;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(3px)';
            overlay.innerHTML = `
                <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:18px;width:100%;max-width:420px;padding:1.5rem;box-shadow:0 24px 60px rgba(0,0,0,.4)">
                    <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:1rem">
                        <div style="width:38px;height:38px;border-radius:50%;background:rgba(255,107,107,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="ti ti-circle-x" style="color:#ff6b6b;font-size:1.2rem"></i>
                        </div>
                        <div>
                            <div style="font-weight:800;font-size:.95rem;color:var(--text-primary)">Cancel Order #${id}</div>
                            <div style="font-size:.75rem;color:var(--text-muted);margin-top:.1rem">This action cannot be undone.</div>
                        </div>
                    </div>
                    <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);display:block;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.4px">Reason for cancellation</label>
                    <textarea id="cancelReasonText" rows="3" placeholder="e.g. Changed my mind, ordered wrong item…"
                        style="width:100%;box-sizing:border-box;padding:.6rem .85rem;background:var(--input-bg);border:1px solid var(--input-border);border-radius:10px;color:var(--input-color);font-size:.875rem;resize:vertical;min-height:80px;outline:none;font-family:inherit"></textarea>
                    <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem">
                        <button id="cancelReasonDismiss" style="padding:.42rem 1rem;font-size:.82rem;font-weight:600;border-radius:8px;border:1px solid var(--card-border);background:var(--input-bg);color:var(--text-primary);cursor:pointer">Never mind</button>
                        <button id="cancelReasonConfirm" style="padding:.42rem 1rem;font-size:.82rem;font-weight:700;border-radius:8px;border:none;background:linear-gradient(135deg,#cc1a1a,#ff4040);color:#fff;cursor:pointer">
                            <i class="ti ti-circle-x"></i> Confirm Cancel
                        </button>
                    </div>
                </div>`;

            document.body.appendChild(overlay);

            const close = () => overlay.remove();

            document.getElementById('cancelReasonDismiss').addEventListener('click', close);
            overlay.addEventListener('click', e => { if (e.target === overlay) close(); });

            document.getElementById('cancelReasonConfirm').addEventListener('click', async () => {
                const reason = document.getElementById('cancelReasonText').value.trim();
                if (!reason) {
                    document.getElementById('cancelReasonText').style.borderColor = '#ff6b6b';
                    document.getElementById('cancelReasonText').placeholder = 'Please enter a reason before cancelling.';
                    return;
                }
                const confirmBtn = document.getElementById('cancelReasonConfirm');
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="ti ti-loader-2"></i> Cancelling…';
                try {
                    const f = new FormData();
                    f.append('action',        'cancel_order');
                    f.append('csrf_token',    CSRF);
                    f.append('order_id',      id);
                    f.append('cancel_reason', reason);
                    const data = await (await fetch('handlers/shop_handler.php', { method: 'POST', body: f })).json();
                    close();
                    if (data.success) {
                        shopToast('info', 'Order #' + id + ' cancelled.');
                        setTimeout(() => loadOrders(), 600);
                    } else {
                        shopToast('error', data.message || 'Could not cancel order.');
                    }
                } catch(e) {
                    close();
                    shopToast('error', 'Network error. Please try again.');
                }
            });
        }

        async function markOrderReceived(id, btn) {
            fsConfirm(
                'Confirm Order Received',
                'Mark Order #' + id + ' as received/completed?',
                async () => {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ti ti-loader-2"></i> Updating…';
            try {
                const f = new FormData();
                f.append('action',   'mark_order_received');
                f.append('order_id', id);
                const data = await (await fetch('handlers/shop_handler.php', { method: 'POST', body: f })).json();
                if (data.success) {
                    shopToast('success', 'Order #' + id + ' marked as completed!');
                    setTimeout(() => loadOrders(), 800);
                } else {
                    shopToast('error', data.message || 'Failed to update order.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ti ti-circle-check" style="font-size:.85rem"></i> Order Received';
                }
            } catch(e) {
                shopToast('error', 'Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="ti ti-circle-check" style="font-size:.85rem"></i> Order Received';
            }
                }
            );
        }

        // Custom confirm helper
        function fsConfirm(title, msg, onOk) {
            document.getElementById('fsConfirmTitle').textContent = title;
            document.getElementById('fsConfirmMsg').textContent   = msg;
            const okBtn = document.getElementById('fsConfirmOkBtn');
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('fsConfirmModal'));
            const handler = () => { modal.hide(); onOk(); okBtn.removeEventListener('click', handler); };
            okBtn.removeEventListener('click', handler); // clear any stale listener
            okBtn.addEventListener('click', handler);
            modal.show();
        }

        function printOrderReceipt(id) {
            const allOrders = window._ordersData;
            if (!allOrders) { window.open('checkout.php?order_id=' + id + '&print=1'); return; }
            const o = allOrders.orders.find(x => parseInt(x.id) === id);
            const items = (allOrders.details[id] || []);
            if (!o) { window.open('checkout.php?order_id=' + id + '&print=1'); return; }
            const d = new Date(o.created_at).toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'});
            let addrBlock = '';
            if (o.fulfillment_method === 'pickup') {
                addrBlock = `<p><strong>Pickup Branch:</strong> ${o.branch_name || ''}<br>${o.branch_address || ''}</p>
                             <p><strong>Pickup Date/Time:</strong> ${o.pickup_date || ''} ${o.pickup_time || ''}</p>`;
            } else if (o.delivery_address) {
                let addr = {}; try { addr = JSON.parse(o.delivery_address); } catch(e) {}
                addrBlock = `<p><strong>Deliver to:</strong> ${o.recipient_name || ''} &middot; ${o.recipient_contact || ''}<br>
                              ${[addr.street,addr.barangay,addr.city,addr.region,addr.zip].filter(Boolean).join(', ')}</p>`;
            }
            const rows = items.map(it =>
                `<tr><td>${it.name}</td><td style="text-align:center">${it.quantity}</td><td style="text-align:right">&#8369;${parseFloat(it.price).toLocaleString('en-PH',{minimumFractionDigits:2})}</td><td style="text-align:right">&#8369;${(parseFloat(it.price)*parseInt(it.quantity)).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>`
            ).join('');
            const deliveryRow = parseFloat(o.delivery_fee) > 0
                ? `<tr><td colspan="3" style="text-align:right">Delivery Fee</td><td style="text-align:right">&#8369;${parseFloat(o.delivery_fee).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>` : '';
            const win = window.open('', '_blank', 'width=700,height=900');
            win.document.write(`<!DOCTYPE html><html><head><title>Receipt — Order #${id}</title>
            <style>
                body{font-family:Arial,sans-serif;font-size:13px;color:#111;margin:0;padding:2rem}
                h1{font-size:1.3rem;margin:0 0 .25rem}
                h2{font-size:1rem;margin:0 0 1.5rem;color:#555}
                table{width:100%;border-collapse:collapse;margin:1rem 0}
                th{background:#f2f2f2;padding:.45rem .6rem;text-align:left;font-size:.8rem;text-transform:uppercase;letter-spacing:.5px}
                td{padding:.4rem .6rem;border-bottom:1px solid #eee}
                .total-row td{font-weight:700;font-size:1rem;border-top:2px solid #111;border-bottom:none}
                .meta{display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1rem;font-size:.85rem}
                .meta span{color:#555}
                .footer{margin-top:2rem;font-size:.8rem;color:#888;text-align:center}
                @media print{body{padding:.5rem}button{display:none}}
            </style></head><body>
            <h1>FitSync — Order Receipt</h1>
            <h2>Order #${id} &nbsp;&middot;&nbsp; ${d}</h2>
            <div class="meta">
                <div><span>Customer:</span><br><strong>${o.customer_name}</strong></div>
                <div><span>Payment:</span><br><strong>${o.payment_method.replace(/_/g,' ')} &middot; ${o.payment_status}</strong></div>
                <div><span>Status:</span><br><strong>${o.status.replace(/_/g,' ')}</strong></div>
            </div>
            ${addrBlock}
            <table>
                <thead><tr><th>Item</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit Price</th><th style="text-align:right">Subtotal</th></tr></thead>
                <tbody>${rows}${deliveryRow}</tbody>
                <tfoot><tr class="total-row"><td colspan="3" style="text-align:right">TOTAL</td><td style="text-align:right">&#8369;${parseFloat(o.total_amount).toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr></tfoot>
            </table>
            <div class="footer">Thank you for your purchase! &mdash; FitSync</div>
            <br><button onclick="window.print()" style="padding:.5rem 1.5rem;font-size:.85rem;cursor:pointer">🖨 Print</button>
            </body></html>`);
            win.document.close();
        }

        // Toast helper — floats above the cart FAB

        (function () {
            // Track active toasts so we can stack them
            const _toasts = [];
            const FAB_BOTTOM   = 96;  // px from bottom (FAB is 60px + 2rem ≈ 92px; add 4px gap)
            const FAB_RIGHT    = 32;  // px from right  (matches FAB right: 2rem)
            const TOAST_GAP    = 10;  // px gap between stacked toasts
            const TOAST_HEIGHT = 52;  // approximate height of one toast pill

            const ICONS = {
                success: 'ti-circle-check',
                error:   'ti-circle-x',
                info:    'ti-info-circle',
            };
            const COLORS = {
                success: { bg: 'linear-gradient(135deg,#1db954,#2ecc71)', border: 'rgba(46,204,113,.35)' },
                error:   { bg: 'linear-gradient(135deg,#cc1a1a,#ff4040)', border: 'rgba(255,64,64,.35)' },
                info:    { bg: 'linear-gradient(135deg,#1565c0,#4a9eff)', border: 'rgba(74,158,255,.35)' },
            };

            function reflow() {
                // Reposition all toasts from bottom so they stack correctly
                let offset = FAB_BOTTOM;
                for (let i = _toasts.length - 1; i >= 0; i--) {
                    const el = _toasts[i];
                    el.style.bottom = offset + 'px';
                    offset += (el.offsetHeight || TOAST_HEIGHT) + TOAST_GAP;
                }
            }

            window.shopToast = function shopToast(type, msg) {
                const cfg  = COLORS[type] || COLORS.info;
                const icon = ICONS[type]  || ICONS.info;

                const t = document.createElement('div');
                t.style.cssText = [
                    'position:fixed',
                    `right:${FAB_RIGHT}px`,
                    `bottom:${FAB_BOTTOM}px`,
                    'z-index:10000',
                    `background:${cfg.bg}`,
                    `border:1px solid ${cfg.border}`,
                    'color:#fff',
                    'padding:.55rem 1rem .55rem .75rem',
                    'border-radius:50px',
                    'font-size:.84rem',
                    'font-weight:700',
                    'box-shadow:0 8px 28px rgba(0,0,0,.35)',
                    'display:flex',
                    'align-items:center',
                    'gap:.5rem',
                    'max-width:260px',
                    'opacity:0',
                    'transform:translateY(14px) scale(.94)',
                    'transition:opacity .28s ease,transform .28s cubic-bezier(.175,.885,.32,1.275)',
                    'pointer-events:none',
                    'white-space:nowrap',
                    'overflow:hidden',
                    'text-overflow:ellipsis',
                ].join(';');
                t.innerHTML = `<i class="ti ${icon}" style="font-size:1.1rem;flex-shrink:0"></i><span style="overflow:hidden;text-overflow:ellipsis">${msg}</span>`;

                document.body.appendChild(t);
                _toasts.push(t);
                reflow();

                // Animate in
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        t.style.opacity = '1';
                        t.style.transform = 'translateY(0) scale(1)';
                    });
                });

                // Auto-dismiss after 3 s
                setTimeout(() => {
                    t.style.opacity = '0';
                    t.style.transform = 'translateY(8px) scale(.94)';
                    setTimeout(() => {
                        t.remove();
                        const idx = _toasts.indexOf(t);
                        if (idx !== -1) _toasts.splice(idx, 1);
                        reflow();
                    }, 280);
                }, 3000);
            };
        })();

        // XSS helpers
        function shEsc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        function shEscAttr(s) { return String(s).replace(/'/g,"&#39;").replace(/"/g,'&quot;'); }

        // Shop tab lazy-loading is handled inside showTab above

        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sbOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
            const hb = document.getElementById('mobileHamburgerRow');
            if (hb) hb.style.visibility = 'hidden';
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sbOverlay').classList.remove('active');
            document.body.style.overflow = '';
            const hb = document.getElementById('mobileHamburgerRow');
            if (hb) hb.style.visibility = 'visible';
        }

        /* ── THEME ── */
        function updateThemeLogos() {
            const isLight = document.documentElement.getAttribute('data-bs-theme') === 'light';
            document.querySelectorAll('[data-logo-dark][data-logo-light]').forEach(logo => {
                logo.setAttribute('src', isLight ? logo.dataset.logoLight : logo.dataset.logoDark);
            });
        }

        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-bs-theme') === 'dark';
            html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('fs-theme', isDark ? 'light' : 'dark');
            updateThemeLogos();
        }

        /* ── PROGRESS BAR ANIMATION ── */
        window.addEventListener('load', () => {
            const fill = document.getElementById('progressFill');
            if (fill) setTimeout(() => {
                fill.style.width = '<?= $progressPct ?>%'
            }, 300);
        });

        /* ── ALERT HELPERS ── */
        function showAlert(id, msg, type = 'danger') {
            const el = document.getElementById(id);
            el.className = `alert fs-alert alert-${type}`;
            el.textContent = msg;
            el.style.display = 'block';
            el.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function hideAlert(id) {
            document.getElementById(id).style.display = 'none'
        }

        /* ── API HELPER ── */
        async function apiPost(payload) {
            const res = await fetch('handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ...payload,
                    csrf_token: CSRF
                }),
            });
            if (!res.ok) throw new Error('Server error ' + res.status);
            return res.json();
        }

        /* ── SAVE PROFILE ── */
        async function saveProfile() {
            hideAlert('profileAlert');
            const fname = document.getElementById('p-fname').value.trim();
            const lname = document.getElementById('p-lname').value.trim();
            const gender = document.getElementById('p-gender').value;
            const birthdate = document.getElementById('p-birthdate').value;
            if (!fname || !lname) {
                showAlert('profileAlert', 'Please enter your full name.');
                return
            }
            try {
                const data = await apiPost({
                    action: 'update_profile',
                    first_name: fname,
                    last_name: lname,
                    gender,
                    birthdate
                });
                if (data.success) {
                    showAlert('profileAlert', data.message, 'success');
                    updateNameDisplays(fname, lname);
                } else {
                    showAlert('profileAlert', data.message)
                }
            } catch {
                showAlert('profileAlert', 'Connection error. Please try again.')
            }
        }

        async function saveProfileFromSettings() {
            hideAlert('settingsProfileAlert');
            const fname = document.getElementById('s-fname').value.trim();
            const lname = document.getElementById('s-lname').value.trim();
            const gender = document.getElementById('s-gender').value;
            const birthdate = document.getElementById('s-birthdate').value;
            if (!fname || !lname) {
                showAlert('settingsProfileAlert', 'Please enter your full name.');
                return
            }
            try {
                const data = await apiPost({
                    action: 'update_profile',
                    first_name: fname,
                    last_name: lname,
                    gender,
                    birthdate
                });
                if (data.success) {
                    showAlert('settingsProfileAlert', data.message, 'success');
                    updateNameDisplays(fname, lname);
                    // Sync to profile tab
                    document.getElementById('p-fname').value = fname;
                    document.getElementById('p-lname').value = lname;
                    document.getElementById('p-gender').value = gender;
                    document.getElementById('p-birthdate').value = birthdate;
                } else {
                    showAlert('settingsProfileAlert', data.message)
                }
            } catch {
                showAlert('settingsProfileAlert', 'Connection error. Please try again.')
            }
        }

        function updateNameDisplays(fname, lname) {
            const full = fname + ' ' + lname;
            const initials = (fname[0] + lname[0]).toUpperCase();
            document.getElementById('profileName').textContent = full;
            document.getElementById('profileInitials').textContent = initials;
            document.getElementById('sbMemberName').textContent = full;
            document.getElementById('sbAvatarInitials').textContent = initials;
            document.getElementById('settingsName').textContent = full;
            document.getElementById('settingsInitials').textContent = initials;
        }

        function syncSettingsFields() {
            document.getElementById('s-fname').value = document.getElementById('p-fname').value;
            document.getElementById('s-lname').value = document.getElementById('p-lname').value;
            document.getElementById('s-gender').value = document.getElementById('p-gender').value;
            document.getElementById('s-birthdate').value = document.getElementById('p-birthdate').value;
        }

        function resetProfile() {
            document.getElementById('p-fname').value = <?= json_encode($userRow['first_name'] ?? '') ?>;
            document.getElementById('p-lname').value = <?= json_encode($userRow['last_name'] ?? '') ?>;
            document.getElementById('p-gender').value = <?= json_encode($userRow['gender'] ?? '') ?>;
            document.getElementById('p-birthdate').value = <?= json_encode($userRow['birthdate'] ?? '') ?>;
            hideAlert('profileAlert');
        }

        /* ── AVATAR UPLOAD (client-side preview, stored in localStorage) ── */
        function handleAvatarUpload(input) {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const dataUrl = e.target.result;
                localStorage.setItem('fs_avatar_' + USER_ID, dataUrl);
                applyAvatar(dataUrl);
            };
            reader.readAsDataURL(file);
        }

        function applyAvatar(dataUrl) {
            // Profile tab big avatar
            const pAvatar = document.getElementById('profileAvatarLg');
            pAvatar.innerHTML = `<img src="${dataUrl}" alt="Avatar" />`;
            // Settings avatar
            const sAvatar = document.getElementById('settingsAvatarLg');
            sAvatar.innerHTML = `<img src="${dataUrl}" alt="Avatar" />`;
            // Sidebar avatar
            const sbAvatar = document.getElementById('sbAvatar');
            sbAvatar.innerHTML = `<img src="${dataUrl}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%" />`;
        }

        // Load saved avatar on page load
        (function() {
            const saved = localStorage.getItem('fs_avatar_' + USER_ID);
            if (saved) applyAvatar(saved);
        })();

        /* ── STAR RATING ── */
        let selectedRating = 5;
        const stars = document.querySelectorAll('.star-picker .star');
        stars.forEach(star => {
            star.addEventListener('mouseover', () => highlightStars(+star.dataset.val));
            star.addEventListener('mouseout', () => highlightStars(selectedRating));
            star.addEventListener('click', () => {
                selectedRating = +star.dataset.val;
                highlightStars(selectedRating)
            });
        });

        function highlightStars(n) {
            stars.forEach(s => s.classList.toggle('active', +s.dataset.val <= n));
        }

        /* ── SUBMIT FEEDBACK ── */
        async function submitFeedback() {
            hideAlert('feedbackAlert');
            const branch_id = document.getElementById('fb-branch').value;
            const body = document.getElementById('fb-body').value.trim();
            if (!body) {
                showAlert('feedbackAlert', 'Please write your review before submitting.');
                return
            }
            try {
                const data = await apiPost({
                    action: 'submit_feedback',
                    branch_id,
                    rating: selectedRating,
                    body
                });
                if (data.success) {
                    showAlert('feedbackAlert', data.message, 'success');
                    document.getElementById('fb-body').value = '';
                    selectedRating = 5;
                    highlightStars(5);
                    if (data.card) {
                        const list = document.getElementById('myFeedbackList');
                        const empty = list.querySelector('.empty-state');
                        if (empty) empty.remove();
                        list.insertAdjacentHTML('afterbegin', data.card);
                    }
                } else {
                    showAlert('feedbackAlert', data.message)
                }
            } catch {
                showAlert('feedbackAlert', 'Connection error. Please try again.')
            }
        }

        /* ── PASSWORD STRENGTH ── */
        function checkPwStrength(val) {
            const segs = ['ps1', 'ps2', 'ps3', 'ps4'].map(id => document.getElementById(id));
            const lbl = document.getElementById('pwStrengthLabel');
            segs.forEach(s => s.style.background = 'var(--input-border)');
            if (!val) {
                lbl.textContent = '';
                return
            }
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const colors = ['', '#e74c3c', '#e67e22', '#2ecc71', '#2ecc71'];
            const labels = ['', 'Weak', 'Fair', 'Good', 'Strong 💪'];
            for (let i = 0; i < score; i++) segs[i].style.background = colors[score];
            lbl.textContent = labels[score] || '';
            lbl.style.color = colors[score] || '';
        }

        /* ── CHANGE PASSWORD ── */
        async function changePassword() {
            hideAlert('pwAlert');
            const current = document.getElementById('pw-current').value;
            const pw = document.getElementById('pw-new').value;
            const confirm = document.getElementById('pw-confirm').value;
            if (!current) {
                showAlert('pwAlert', 'Please enter your current password.');
                return
            }
            if (pw.length < 8) {
                showAlert('pwAlert', 'New password must be at least 8 characters.');
                return
            }
            if (pw !== confirm) {
                showAlert('pwAlert', 'New passwords do not match.');
                return
            }
            try {
                const data = await apiPost({
                    action: 'change_password',
                    current_password: current,
                    new_password: pw,
                    confirm_password: confirm
                });
                if (data.success) {
                    showAlert('pwAlert', data.message, 'success');
                    ['pw-current', 'pw-new', 'pw-confirm'].forEach(id => document.getElementById(id).value = '');
                    checkPwStrength('');
                } else {
                    showAlert('pwAlert', data.message)
                }
            } catch {
                showAlert('pwAlert', 'Connection error. Please try again.')
            }
        }

        async function submitRenewal() {
            hideAlert('renewAlert');
            const plan_id = document.getElementById('renew-plan').value;
            const payment_method = document.getElementById('renew-payment').value;

            if (payment_method !== 'cash') {
                const proofFile = document.getElementById('rnProofFile').files[0];
                if (!proofFile) {
                    const el = document.getElementById('renewAlert');
                    el.className = 'alert alert-danger';
                    el.textContent = 'Proof of payment is required. Please upload a screenshot or receipt.';
                    el.classList.remove('d-none');
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    return;
                }
            }

            const payload = new FormData();
            payload.append('action', 'renew_membership');
            payload.append('csrf_token', CSRF);
            payload.append('plan_id', plan_id);
            payload.append('payment_method', payment_method);
            const proofFile = document.getElementById('rnProofFile').files[0];
            if (proofFile) payload.append('proof_file', proofFile);

            try {
                const res = await fetch('handlers/profile_handler.php', { method: 'POST', body: payload });
                if (!res.ok) throw new Error('Server error ' + res.status);
                const data = await res.json();
                if (data.success) {
                    showAlert('renewAlert', data.message, 'success');
                    setTimeout(() => location.reload(), 900);
                } else {
                    showAlert('renewAlert', data.message);
                }
            } catch {
                showAlert('renewAlert', 'Connection error. Please try again.');
            }
        }

        /* ── RENEWAL PAYMENT METHOD HELPERS ── */
        function rnSelectPaymentMethod(method, event) {
            event.preventDefault();
            document.querySelectorAll('#rnPaymentMethodGrid .payment-method-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById('renew-payment').value = method;
            document.querySelectorAll('#rnPaymentInfoContainer .payment-info-card').forEach(card => card.classList.remove('active'));
            const card = document.getElementById('rn-pay-info-' + method);
            if (card) card.classList.add('active');
            document.getElementById('rnProofUploadSection').style.display = (method !== 'cash') ? 'block' : 'none';
            rnRemoveUpload();
        }

        function rnUpdateCardDisplay(cardType = 'credit') {
            if (cardType === 'credit') {
                document.getElementById('rnCcDisplayName').textContent = (document.getElementById('rnPdCcName').value || 'YOUR NAME').toUpperCase();
                document.getElementById('rnCcDisplay4').textContent = (document.getElementById('rnPdCcLast4').value || '0000').padStart(4, '0');
                document.getElementById('rnCcDisplayExp').textContent = document.getElementById('rnPdCcExp').value || 'MM/YY';
            } else {
                document.getElementById('rnDcDisplayName').textContent = (document.getElementById('rnPdDcName').value || 'YOUR NAME').toUpperCase();
                document.getElementById('rnDcDisplay4').textContent = (document.getElementById('rnPdDcLast4').value || '0000').padStart(4, '0');
                document.getElementById('rnDcDisplayExp').textContent = document.getElementById('rnPdDcExp').value || 'MM/YY';
            }
        }

        function rnFormatCardExpiry(input) {
            let v = input.value.replace(/\D/g, '');
            if (v.length >= 2) v = v.slice(0, 2) + '/' + v.slice(2, 4);
            input.value = v;
        }

        function rnCopyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(() => {
                const orig = button.className;
                button.classList.add('copied');
                button.innerHTML = '<i class="ti ti-check"></i>';
                setTimeout(() => { button.className = orig; button.innerHTML = '<i class="ti ti-copy"></i>'; }, 2000);
            }).catch(() => console.error('Failed to copy'));
        }

        function rnHandleFileSelect(input) {
            if (input.files && input.files[0]) rnShowUploadPreview(input.files[0]);
        }

        function rnHandleFileDrop(e) {
            e.preventDefault();
            document.getElementById('rnUploadZone').classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type)) {
                const el = document.getElementById('renewAlert');
                el.className = 'alert alert-danger';
                el.textContent = 'Proof of payment must be JPG, PNG, or PDF.';
                el.classList.remove('d-none');
                return;
            }
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('rnProofFile').files = dt.files;
            rnShowUploadPreview(file);
        }

        function rnShowUploadPreview(file) {
            document.getElementById('rnUploadFileName').textContent = file.name;
            document.getElementById('rnUploadPreview').classList.add('show');
            document.getElementById('rnUploadZone').style.display = 'none';
        }

        function rnRemoveUpload() {
            document.getElementById('rnProofFile').value = '';
            document.getElementById('rnUploadPreview').classList.remove('show');
            document.getElementById('rnUploadZone').style.display = '';
        }

        async function bookClass(scheduleId) {
            try {
                const data = await apiPost({
                    action: 'book_class',
                    schedule_id: scheduleId
                });
                alert(data.message || (data.success ? 'Class reserved.' : 'Unable to reserve class.'));
                if (data.success && data.reload) location.reload();
            } catch {
                alert('Connection error. Please try again.');
            }
        }

        async function cancelClassBooking(bookingId) {
            if (!confirm('Cancel this class reservation?')) return;
            try {
                const data = await apiPost({
                    action: 'cancel_booking',
                    booking_id: bookingId
                });
                alert(data.message || (data.success ? 'Booking cancelled.' : 'Unable to cancel booking.'));
                if (data.success && data.reload) location.reload();
            } catch {
                alert('Connection error. Please try again.');
            }
        }

        /* ════════════════════════════════════
           GYM ATTENDANCE CALENDAR
        ════════════════════════════════════ */
        let calYear, calMonth;

        function loadAttendance() {
            return [...new Set(attendanceDates)];
        }

        function todayStr() {
            const n = new Date();
            return `${n.getFullYear()}-${String(n.getMonth()+1).padStart(2,'0')}-${String(n.getDate()).padStart(2,'0')}`;
        }

        function calcStreak(attended) {
            const set = new Set(attended);
            let streak = 0;
            const today = new Date();
            for (let i = 0; i < 365; i++) {
                const d = new Date(today);
                d.setDate(d.getDate() - i);
                const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
                if (set.has(key)) streak++;
                else if (i > 0) break; // allow today to be missed (hasn't gone yet)
            }
            return streak;
        }

        function updateStreakDisplays() {
            const streak = currentStreak;
            document.getElementById('streakNum').textContent = streak;
            document.getElementById('streakDisplay').textContent = streak;
            const streakStat = document.getElementById('streakStat');
            if (streakStat) streakStat.textContent = streak;
            const totalHero = document.getElementById('attendanceTotalHero');
            if (totalHero) totalHero.textContent = attendanceTotal.toLocaleString('en-PH');
            const totalStat = document.getElementById('attendanceTotalStat');
            if (totalStat) totalStat.textContent = attendanceTotal.toLocaleString('en-PH');
            const pct = Math.min(100, Math.round((streak / 7) * 100));
            document.getElementById('streakBar').style.width = pct + '%';
        }

        function renderCalendar() {
            const today = new Date();
            if (calYear === undefined) {
                calYear = today.getFullYear();
                calMonth = today.getMonth();
            }
            const attended = new Set(loadAttendance());
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const tStr = todayStr();

            document.getElementById('calTitle').textContent = `${monthNames[calMonth]} ${calYear}`;

            const firstDay = new Date(calYear, calMonth, 1).getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

            let html = '';
            for (let i = 0; i < firstDay; i++) {
                html += '<div class="cal-cell cal-empty"></div>';
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${calYear}-${String(calMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const isToday = dateStr === tStr;
                const isFuture = dateStr > tStr;
                const isAttended = attended.has(dateStr);
                let cls = 'cal-cell';
                if (isFuture) cls += ' cal-future';
                if (isToday) cls += ' cal-today';
                if (isAttended) cls += ' cal-attended';
                html += `<div class="${cls}" title="${dateStr}">${d}</div>`;
            }

            const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
            for (let i = firstDay + daysInMonth; i < totalCells; i++) {
                html += '<div class="cal-cell cal-empty"></div>';
            }

            document.getElementById('calGrid').innerHTML = html;
            updateStreakDisplays();
        }

        function calNav(dir) {
            calMonth += dir;
            if (calMonth > 11) {
                calMonth = 0;
                calYear++;
            }
            if (calMonth < 0) {
                calMonth = 11;
                calYear--;
            }
            renderCalendar();
        }

        // Manual attendance logging functions removed

        /* ════════════════════════════════════
           WORKOUT PROGRAMS
        ════════════════════════════════════ */
        function showProgram(id) {
            const prog = programs.find(p => p.id === id);
            if (!prog) return;
            document.getElementById('programsListView').style.display = 'none';
            document.getElementById('programDetailView').style.display = 'block';
            document.getElementById('progDetailBreadcrumb').textContent = prog.name;

            document.getElementById('progDetailHeader').innerHTML = `
            <div style="display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:${prog.color};margin-bottom:.4rem">${prog.tag}</div>
                    <div style="font-size:1.4rem;font-weight:900;letter-spacing:-.4px;color:var(--text-primary);margin-bottom:.4rem">${prog.name}</div>
                    <div style="font-size:.85rem;color:var(--text-muted);line-height:1.6;max-width:500px">${prog.desc}</div>
                    <div style="display:flex;gap:1rem;margin-top:.85rem;flex-wrap:wrap">
                        <span style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:center;gap:.3rem"><i class="ti ti-calendar"></i>${prog.days} training days/week</span>
                        <span style="font-size:.75rem;color:var(--text-muted);display:flex;align-items:center;gap:.3rem"><i class="ti ti-signal"></i>${prog.level}</span>
                    </div>
                </div>
                <div style="width:4px;min-height:80px;border-radius:4px;background:${prog.color};flex-shrink:0;align-self:stretch"></div>
            </div>
        `;

            const schedHtml = prog.schedule.map(day => {
                const isRest = day.focus === 'Rest' || day.exercises.length === 0;
                const exHtml = day.exercises.map(ex => `<span class="prog-ex-chip">${ex}</span>`).join('');
                return `
            <div class="prog-day-row">
                <div class="prog-day-label">
                    <div class="prog-day-name">${day.day}</div>
                    ${isRest
                        ? `<div class="prog-day-rest">Rest</div>`
                        : `<div class="prog-day-focus" style="color:${prog.color}">${day.focus}</div>`
                    }
                </div>
                <div class="prog-exercises">
                    ${isRest
                        ? `<span style="font-size:.8rem;color:var(--text-dimmed);font-style:italic">Recovery day — stretch, walk, or rest completely.</span>`
                        : exHtml
                    }
                </div>
            </div>`;
            }).join('');
            document.getElementById('progSchedule').innerHTML = schedHtml;
        }

        function backToPrograms() {
            document.getElementById('programsListView').style.display = 'block';
            document.getElementById('programDetailView').style.display = 'none';
        }
        /* ══════════════════════════════════════════════
   QR CHECK-IN SYSTEM
══════════════════════════════════════════════ */

        function getQrPayload() {
            return 'MBR-' + String(USER_ID).padStart(5, '0');
        }

        /* ── Hero QR ── */
        function initHeroQr() {
            const el = document.getElementById('heroQrCode');
            if (!el || typeof QRCode === 'undefined') return;
            el.innerHTML = '';
            new QRCode(el, {
                text: getQrPayload(),
                width: 94,
                height: 94,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        /* ── QR view modal ── */
        function openQrModal() {
            const el = document.getElementById('qrModalCode');
            if (el && typeof QRCode !== 'undefined') {
                el.innerHTML = '';
                new QRCode(el, {
                    text: getQrPayload(),
                    width: 176,
                    height: 176,
                    colorDark: '#111111',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('qrModal')).show();
        }

        function openScannerFromQrModal() {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('qrModal')).hide();
            setTimeout(openScannerModal, 340);
        }

        /* ── BOOT ── */
        renderCalendar();
        initHeroQr();
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
</body>

</html>