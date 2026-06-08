<?php
// ============================================================
//  FitSync — Member Profile (Enhanced)
//  profile.php
// ============================================================
require_once __DIR__ . '/config/auth_guard.php';
requireRole('member');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/attendance_helpers.php';
require_once __DIR__ . '/includes/membership_helpers.php';
require_once __DIR__ . '/includes/member_dashboard_helpers.php';
require_once __DIR__ . '/includes/schedule_helpers.php';

$pdo    = db();
$userId = (int) $_SESSION['user_id'];

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
}

$scheduleContext = memberScheduleContext($pdo, $mem, $userId);
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

        .sb-footer {
            padding: .85rem .75rem 1.25rem;
            border-top: 1px solid var(--sidebar-border);
            flex-shrink: 0
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

        .pending-overlay {
            position: absolute;
            inset: 0;
            z-index: 150;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(10, 10, 10, .55);
            backdrop-filter: blur(10px);
            pointer-events: auto;
        }

        .pending-overlay-card {
            max-width: 520px;
            width: 100%;
            background: rgba(17, 17, 17, .98);
            border: 1px solid rgba(255, 193, 7, .2);
            box-shadow: 0 25px 80px rgba(0, 0, 0, .35);
            border-radius: 24px;
            padding: 2rem;
            color: var(--text-primary);
        }

        .pending-overlay-card h2 {
            margin: 0 0 1rem;
            font-size: 1.35rem;
            font-weight: 900;
        }

        .pending-overlay-card p {
            margin: .75rem 0 0;
            color: var(--text-muted);
            line-height: 1.7;
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
            flex-shrink: 0
        }

        .hero-qr-box {
            width: 110px;
            height: 110px;
            background: #fff;
            border-radius: 16px;
            padding: 8px;
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
                flex-wrap: wrap;
                justify-content: flex-start;
                width: 100%;
                gap: .75rem;
                padding-top: .5rem
            }

            .hero-qr-box {
                width: 80px;
                height: 80px;
                padding: 5px
            }
        }
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
            <button class="sb-nav-item active" onclick="showTab('dashboard', this)">
                <i class="ti ti-layout-dashboard"></i> Dashboard
            </button>
            <button class="sb-nav-item" onclick="showTab('programs', this)">
                <i class="ti ti-barbell"></i> Programs
            </button>
            <button class="sb-nav-item" onclick="showTab('billing', this)">
                <i class="ti ti-receipt"></i> Plans & Billing
            </button>
            <button class="sb-nav-item" onclick="showTab('schedule', this)">
                <i class="ti ti-calendar-event"></i> Schedule
            </button>
            <button class="sb-nav-item" onclick="showTab('feedback', this)">
                <i class="ti ti-message-star"></i> Feedback
            </button>
            <button class="sb-nav-item" onclick="showTab('settings', this)">
                <i class="ti ti-settings"></i> Settings
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

        <!-- Mobile hamburger row -->
        <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:1.5rem" class="d-lg-none">
            <button class="hamburger" onclick="openSidebar()"><i class="ti ti-menu-2"></i></button>
            <span style="font-size:.9rem;font-weight:700;color:var(--text-muted)">FitSync</span>
        </div>

        <?php if ($isPending): ?>
            <div class="pending-overlay">
                <div class="pending-overlay-card">
                    <h2>Account Pending Approval</h2>
                    <p>Your account is currently under review by our administrators. The full member dashboard is visible, but interactions are disabled until approval.</p>
                    <p>Use the sidebar to toggle dark mode or log out while you wait.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── HERO HEADER ── -->
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
                            <?php 
                            $qrFile = 'qrcodes/member' . $userId . '.png';
                            if (file_exists(__DIR__ . '/' . $qrFile)):
                            ?>
                                <img src="<?= $qrFile ?>" alt="QR Code" />
                            <?php else: ?>
                                <div id="heroQrCode"></div>
                            <?php endif; ?>
                        </div>
                        <div class="hero-qr-label"><i class="ti ti-qrcode" aria-hidden="true"></i> Check-in QR</div>
                        <button class="hero-scan-btn" onclick="openScannerModal()">
                            <i class="ti ti-scan" aria-hidden="true"></i> Scan to Check In
                        </button>
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
            <div class="modal-dialog modal-dialog-centered">
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
                        <label class="form-label fs-label">Payment Method</label>
                        <select class="form-select fs-input" id="renew-payment">
                            <option value="gcash">GCash</option>
                            <option value="maya">Maya</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash / Walk-in</option>
                        </select>
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

        /* ── TAB NAV ── */
        function showTab(id, btn) {
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
        }
        document.querySelectorAll('.page-tab').forEach(tab => {
            tab.addEventListener('click', () => showTab(tab.dataset.tab, tab));
        });

        /* ── SIDEBAR ── */
        const initialTab = location.hash.replace('#', '');
        if (['dashboard', 'programs', 'billing', 'schedule', 'feedback', 'settings'].includes(initialTab)) {
            showTab(initialTab, null);
        }

        function openSidebar() {
            document.getElementById('sidebar').classList.add('open');
            document.getElementById('sbOverlay').classList.add('active');
            document.body.style.overflow = 'hidden'
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sbOverlay').classList.remove('active');
            document.body.style.overflow = ''
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
            try {
                const data = await apiPost({
                    action: 'renew_membership',
                    plan_id,
                    payment_method
                });
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