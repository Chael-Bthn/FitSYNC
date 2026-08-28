<?php
header('Cache-Control: no-store');
require_once __DIR__ . '/config/auth_guard.php';
require_once __DIR__ . '/config/db.php';
requireRole('member');
$pdo    = db();
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Athlete';
$firstName = explode(' ', trim($userName))[0];

// Auto-migrate gymbuddy tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS gymbuddy_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        height_cm DECIMAL(5,1) DEFAULT 170,
        weight_kg DECIMAL(5,1) DEFAULT 70,
        goal_weight_kg DECIMAL(5,1) DEFAULT 65,
        age TINYINT DEFAULT 25,
        body_goal ENUM('lose_fat','build_muscle','maintain','athletic') DEFAULT 'build_muscle',
        fitness_level ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
        diet_preference VARCHAR(30) DEFAULT 'no_restriction',
        daily_calories INT DEFAULT 2000,
        daily_protein INT DEFAULT 150,
        daily_carbs INT DEFAULT 200,
        daily_fats INT DEFAULT 65,
        water_goal INT DEFAULT 8,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS nutrition_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        log_date DATE NOT NULL,
        meal_type ENUM('breakfast','lunch','dinner','snack') DEFAULT 'snack',
        food_name VARCHAR(150) NOT NULL,
        calories INT DEFAULT 0,
        protein_g DECIMAL(6,1) DEFAULT 0,
        carbs_g DECIMAL(6,1) DEFAULT 0,
        fats_g DECIMAL(6,1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS workout_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        log_date DATE NOT NULL,
        workout_name VARCHAR(100),
        duration_minutes INT DEFAULT 0,
        exercises_done INT DEFAULT 0,
        total_sets INT DEFAULT 0,
        calories_burned INT DEFAULT 0,
        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS weight_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        log_date DATE NOT NULL,
        weight_kg DECIMAL(5,1) NOT NULL,
        note VARCHAR(200),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Throwable $e) { /* non-fatal */ }

// Load profile
$profileStmt = $pdo->prepare('SELECT * FROM gymbuddy_profiles WHERE user_id=? LIMIT 1');
$profileStmt->execute([$userId]);
$gbProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);
$hasProfile = (bool)$gbProfile;

// Today's nutrition
$today = date('Y-m-d');
$nutrStmt = $pdo->prepare('SELECT * FROM nutrition_logs WHERE user_id=? AND log_date=? ORDER BY created_at ASC');
$nutrStmt->execute([$userId, $today]);
$todayNutrition = $nutrStmt->fetchAll(PDO::FETCH_ASSOC);

// Workout history (last 30 days)
$wkStmt = $pdo->prepare('SELECT log_date, workout_name FROM workout_logs WHERE user_id=? AND log_date >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) ORDER BY log_date DESC');
$wkStmt->execute([$userId]);
$workoutHistory = $wkStmt->fetchAll(PDO::FETCH_ASSOC);
$workoutDates = array_column($workoutHistory, 'log_date');

// Streak calculation
$streak = 0;
$checkDate = new DateTime();
while (in_array($checkDate->format('Y-m-d'), $workoutDates)) {
    $streak++;
    $checkDate->modify('-1 day');
    if ($streak > 365) break;
}

// Weight history (last 30 entries)
$wtStmt = $pdo->prepare('SELECT log_date, weight_kg FROM weight_logs WHERE user_id=? ORDER BY log_date DESC LIMIT 30');
$wtStmt->execute([$userId]);
$weightHistory = array_reverse($wtStmt->fetchAll(PDO::FETCH_ASSOC));

// Water log (from session/localStorage - handled client side)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Gym Buddy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.0.0/dist/tabler-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* VARIABLES + RESET */
        :root {
            --bg: #0a0a0a;
            --bg-2: #111111;
            --card: #161616;
            --card-2: #1e1e1e;
            --border: rgba(255,255,255,0.07);
            --accent: #cc1a1a;
            --accent-dim: rgba(204,26,26,0.12);
            --accent-glow: 0 0 24px rgba(204,26,26,0.4);
            --accent-ring: rgba(204,26,26,0.25);
            --gym-red: #f43f5e;
            --gym-red-dim: rgba(244,63,94,0.12);
            --blue: #60a5fa;
            --purple: #a78bfa;
            --yellow: #fbbf24;
            --text: #f0f0f0;
            --text-2: #888888;
            --text-3: #444444;
            --font: 'Inter', system-ui, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; user-select: none; -webkit-tap-highlight-color: transparent; }
        body { font-family: var(--font); background-color: #0a0a12; color: var(--text); overflow: hidden; }
        ::-webkit-scrollbar { display: none; }
        
        /* APP SHELL */
        #app-root {
            max-width: 430px;
            margin: 0 auto;
            background: var(--bg);
            height: 100vh;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* SCREEN SYSTEM */
        .screen {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 90px;
            display: none;
            animation: fadeIn 0.2s ease-out;
            padding-top: 20px;
            padding-inline: 16px;
        }
        .screen.active { display: block; }

        /* TOP BAR */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .greeting h1 { font-size: 1.25rem; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .greeting p { font-size: 0.875rem; color: var(--text-2); }
        .avatar { width: 44px; height: 44px; background: var(--card-2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: var(--accent); border: 2px solid var(--accent-dim); }

        /* CARDS */
        .card { background: var(--card); border-radius: 20px; padding: 20px; margin-bottom: 16px; border: 1px solid var(--border); }
        .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .card-title i { color: var(--accent); }

        /* HOME TAB */
        .ai-insight { background: linear-gradient(145deg, var(--card), var(--bg-2)); border: 1px solid rgba(167, 139, 250, 0.2); border-radius: 16px; padding: 16px; display: flex; gap: 12px; margin-bottom: 16px; }
        .ai-insight i { color: var(--purple); font-size: 1.5rem; margin-top: 2px; }
        .ai-insight p { font-size: 0.875rem; color: var(--text-2); line-height: 1.5; }
        .ai-insight strong { color: var(--text); }
        
        .macro-ring-container { display: flex; align-items: center; justify-content: center; position: relative; margin: 10px 0 20px 0; }
        .macro-ring { width: 160px; height: 160px; transform: rotate(-90deg); }
        .macro-circle-bg { fill: none; stroke: var(--card-2); stroke-width: 12; }
        .macro-circle { fill: none; stroke-width: 12; stroke-linecap: round; transition: stroke-dashoffset 1s ease; }
        .macro-protein { stroke: var(--accent); }
        .macro-carbs { stroke: var(--blue); }
        .macro-fats { stroke: var(--yellow); }
        .macro-center { position: absolute; text-align: center; }
        .macro-cals { font-size: 1.75rem; font-weight: 800; color: var(--text); line-height: 1; }
        .macro-label { font-size: 0.75rem; color: var(--text-2); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
        
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 16px 12px; text-align: center; }
        .stat-value { font-size: 1.25rem; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .stat-label { font-size: 0.75rem; color: var(--text-2); }
        
        .today-workout-card { position: relative; overflow: hidden; }
        .today-workout-card .play-btn { width: 48px; height: 48px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--bg); font-size: 1.5rem; position: absolute; right: 20px; top: 50%; transform: translateY(-50%); box-shadow: var(--accent-glow); animation: pulse 2s infinite; cursor: pointer; }
        
        .water-tracker { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; }
        .water-glass { font-size: 1.5rem; opacity: 0.3; transition: all 0.2s; cursor: pointer; }
        .water-glass.filled { opacity: 1; filter: drop-shadow(0 0 5px rgba(96,165,250,0.5)); }

        /* NUTRITION TAB */
        .date-nav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding: 0 16px; }
        .date-nav button { background: none; border: none; color: var(--text-2); font-size: 1.25rem; cursor: pointer; padding: 8px; }
        .date-nav span { font-weight: 600; font-size: 1rem; }
        
        .macro-bars { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
        .macro-bar-row { display: flex; flex-direction: column; gap: 6px; }
        .macro-bar-header { display: flex; justify-content: space-between; font-size: 0.875rem; }
        .macro-bar-track { height: 8px; background: var(--card-2); border-radius: 4px; overflow: hidden; }
        .macro-bar-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
        
        .meal-section { margin-bottom: 20px; }
        .meal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .meal-title { font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .meal-cals { font-size: 0.875rem; color: var(--text-2); }
        .food-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--card); border-radius: 12px; margin-bottom: 8px; border: 1px solid var(--border); }
        .food-info h4 { font-size: 0.9rem; font-weight: 500; margin-bottom: 4px; }
        .food-macros { font-size: 0.75rem; color: var(--text-2); }
        .food-calories { font-weight: 600; font-size: 0.9rem; }
        .add-food-btn { width: 100%; padding: 12px; background: none; border: 1px dashed var(--border); color: var(--accent); border-radius: 12px; font-weight: 600; display: flex; justify-content: center; align-items: center; gap: 8px; cursor: pointer; }

        /* WORKOUTS TAB */
        .week-strip { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .day-bubble { display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .day-name { font-size: 0.75rem; color: var(--text-2); }
        .day-circle { width: 36px; height: 36px; border-radius: 50%; background: var(--card); display: flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 600; color: var(--text-2); border: 1px solid var(--border); }
        .day-bubble.active .day-name { color: var(--accent); font-weight: 600; }
        .day-bubble.active .day-circle { background: var(--accent); color: var(--bg); border-color: var(--accent); box-shadow: var(--accent-glow); }
        
        .exercise-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
        .exercise-item { display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--card); border-radius: 16px; border: 1px solid var(--border); }
        .ex-emoji { font-size: 1.5rem; width: 40px; height: 40px; background: var(--card-2); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .ex-details { flex: 1; }
        .ex-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 4px; }
        .ex-meta { font-size: 0.75rem; color: var(--text-2); display: flex; gap: 12px; }
        
        .btn-primary { width: 100%; padding: 16px; background: var(--accent); color: var(--bg); border: none; border-radius: 16px; font-size: 1rem; font-weight: 700; cursor: pointer; box-shadow: var(--accent-glow); display: flex; justify-content: center; align-items: center; gap: 8px; }

        /* GLOBAL APP HEADER */
        .app-global-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px 8px 20px;
            z-index: 50;
            background: var(--bg);
        }
        .header-pill-btn {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: #141416;
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            cursor: pointer;
            position: relative;
            transition: all 0.2s;
        }
        .header-pill-btn:hover { background: #1c1c20; border-color: rgba(255,255,255,0.18); }
        .header-brand {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: #ffffff;
        }
        .notif-badge-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #e51e1e;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 17px;
            height: 17px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg);
            box-shadow: 0 0 8px rgba(229,30,30,0.6);
        }

        /* AI COACH SCREEN - REFERENCE DESIGN */
        .ai-screen-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 4px 6px 16px 6px;
        }
        .ai-coach-card {
            flex: 1;
            background: radial-gradient(circle at center 42%, #190909 0%, #0d0d10 65%, #08080a 100%);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 28px;
            padding: 20px 16px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.6);
        }
        .ai-badge-pill {
            align-self: flex-start;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 14px;
            border-radius: 20px;
            background: rgba(204,26,26,0.15);
            border: 1px solid rgba(204,26,26,0.35);
            color: #ff4d4d;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            z-index: 10;
        }
        .ai-badge-pill .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #e51e1e;
            box-shadow: 0 0 10px #ff3333;
            animation: pulseRedDot 1.5s infinite;
        }

        /* CENTER VISUALIZER STAGE */
        .ai-center-stage {
            position: relative;
            width: 100%;
            height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto 0;
        }
        .hud-ring-outer {
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            border: 1px dashed rgba(204,26,26,0.22);
            animation: rotateHUD 40s linear infinite;
        }
        .hud-ring-mid {
            position: absolute;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            border: 1px solid rgba(204,26,26,0.4);
            box-shadow: 0 0 25px rgba(204,26,26,0.15), inset 0 0 25px rgba(204,26,26,0.15);
        }
        .hud-ring-inner {
            position: absolute;
            width: 210px;
            height: 210px;
            border-radius: 50%;
            border: 1px dashed rgba(204,26,26,0.3);
        }
        .ecg-wave-svg {
            position: absolute;
            width: 100%;
            height: 70px;
            z-index: 2;
            pointer-events: none;
            filter: drop-shadow(0 0 7px rgba(229,30,30,0.85));
        }
        .mascot-avatar-circle {
            position: relative;
            width: 175px;
            height: 175px;
            border-radius: 50%;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            animation: floatMascot 4s ease-in-out infinite;
        }
        .mascot-avatar-circle img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            filter: drop-shadow(0 0 25px rgba(204,26,26,0.5));
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .mascot-avatar-circle:active img {
            transform: scale(0.94);
        }

        /* BOTTOM TALK CONTROLS */
        .ai-talk-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            z-index: 10;
            cursor: pointer;
            margin-bottom: 4px;
            width: 100%;
        }
        .tap-talk-text {
            color: #9e9ea8;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: color 0.2s;
        }
        .ai-talk-section:hover .tap-talk-text {
            color: #ffffff;
        }
        .audio-bars-wave {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4.5px;
            height: 38px;
        }
        .audio-bars-wave .bar {
            width: 3.5px;
            background: #e51e1e;
            border-radius: 4px;
            box-shadow: 0 0 8px rgba(229,30,30,0.7);
            height: 8px;
            transition: height 0.15s ease;
        }
        .audio-bars-wave.active .bar {
            animation: waveBounce 0.8s ease-in-out infinite alternate;
        }
        .audio-bars-wave .bar:nth-child(1)  { height: 6px;  animation-delay: 0.1s; }
        .audio-bars-wave .bar:nth-child(2)  { height: 10px; animation-delay: 0.3s; }
        .audio-bars-wave .bar:nth-child(3)  { height: 16px; animation-delay: 0.15s; }
        .audio-bars-wave .bar:nth-child(4)  { height: 24px; animation-delay: 0.4s; }
        .audio-bars-wave .bar:nth-child(5)  { height: 32px; animation-delay: 0.2s; }
        .audio-bars-wave .bar:nth-child(6)  { height: 38px; animation-delay: 0.5s; }
        .audio-bars-wave .bar:nth-child(7)  { height: 26px; animation-delay: 0.25s; }
        .audio-bars-wave .bar:nth-child(8)  { height: 36px; animation-delay: 0.45s; }
        .audio-bars-wave .bar:nth-child(9)  { height: 30px; animation-delay: 0.1s; }
        .audio-bars-wave .bar:nth-child(10) { height: 20px; animation-delay: 0.35s; }
        .audio-bars-wave .bar:nth-child(11) { height: 14px; animation-delay: 0.55s; }
        .audio-bars-wave .bar:nth-child(12) { height: 8px;  animation-delay: 0.2s; }
        .audio-bars-wave .bar:nth-child(13) { height: 5px;  animation-delay: 0.4s; }

        @keyframes waveBounce {
            0% { transform: scaleY(0.4); opacity: 0.6; }
            100% { transform: scaleY(1.3); opacity: 1; filter: drop-shadow(0 0 10px #ff3333); }
        }
        @keyframes floatMascot {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-7px); }
        }
        @keyframes rotateHUD {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes pulseRedDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.85); }
        }

        /* AI CHAT DRAWER / POPUP */
        .ai-chat-drawer {
            position: absolute;
            inset: 0;
            background: rgba(10,10,12,0.96);
            backdrop-filter: blur(12px);
            z-index: 30;
            display: none;
            flex-direction: column;
            padding: 16px;
            border-radius: 28px;
            animation: fadeIn 0.25s ease-out;
        }
        .ai-chat-drawer.active {
            display: flex;
        }
        .ai-drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 12px;
        }
        .chat-area { flex: 1; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; padding-bottom: 16px; }
        .chat-bubble { max-width: 85%; padding: 12px 16px; border-radius: 18px; font-size: 0.9rem; line-height: 1.4; }
        .chat-ai { background: var(--card); border-bottom-left-radius: 4px; align-self: flex-start; border: 1px solid var(--border); }
        .chat-user { background: #cc1a1a; color: #ffffff; border-bottom-right-radius: 4px; align-self: flex-end; }
        .quick-prompts { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 8px; }
        .quick-prompt { padding: 8px 14px; background: var(--card-2); border: 1px solid var(--border); border-radius: 18px; font-size: 0.78rem; white-space: nowrap; cursor: pointer; color: var(--text); transition: all 0.15s; }
        .quick-prompt:hover { background: rgba(204,26,26,0.15); border-color: rgba(204,26,26,0.4); }
        .chat-input-bar { display: flex; gap: 10px; margin-top: auto; }
        .chat-input-bar input { flex: 1; background: var(--card); border: 1px solid var(--border); padding: 12px 16px; border-radius: 24px; color: var(--text); font-size: 0.9rem; outline: none; }
        .chat-input-bar button { width: 44px; height: 44px; border-radius: 50%; background: #cc1a1a; border: none; color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem; }

        /* PROGRESS TAB */
        .chart-container { height: 200px; width: 100%; margin-top: 16px; }
        .measurements-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .measure-item { background: var(--card-2); padding: 12px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; }
        .measure-label { font-size: 0.875rem; color: var(--text-2); }
        .measure-val { font-weight: 600; }
        
        .milestone { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border); }
        .milestone:last-child { border-bottom: none; }
        .milestone-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .milestone.done .milestone-icon { background: var(--accent-dim); color: var(--accent); }
        .milestone.locked .milestone-icon { background: var(--card-2); color: var(--text-3); border: 1px dashed var(--text-3); }
        .milestone-text { font-size: 0.9rem; flex: 1; }
        .milestone.locked .milestone-text { color: var(--text-3); }

        /* BOTTOM NAV - EXACT REFERENCE DESIGN */
        .bottom-nav {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #09090b;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 8px 10px 18px 10px;
            z-index: 100;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: #666675;
            text-decoration: none;
            font-size: 0.68rem;
            font-weight: 500;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 12px;
            transition: all 0.2s;
            min-width: 54px;
        }
        .nav-item i {
            font-size: 1.45rem;
            transition: transform 0.2s;
        }
        .nav-item.active {
            color: #ffffff;
        }
        .nav-item.active i {
            transform: translateY(-2px);
        }
        .nav-item.center-ai-btn {
            background: rgba(204,26,26,0.12);
            border: 1.5px solid rgba(204,26,26,0.45);
            border-radius: 16px;
            padding: 6px 14px;
            box-shadow: 0 0 16px rgba(204,26,26,0.25);
            min-width: 68px;
        }
        .nav-item.center-ai-btn i {
            font-size: 1.5rem;
            color: #e51e1e;
        }
        .nav-item.center-ai-btn span {
            color: #ff5555;
            font-weight: 700;
            font-size: 0.66rem;
        }


        /* MODALS & OVERLAYS */
        .overlay { position: absolute; inset: 0; background: var(--bg); z-index: 200; display: none; flex-direction: column; }
        .overlay.active { display: flex; animation: slideIn 0.3s ease-out; }
        
        .modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); z-index: 300; display: none; opacity: 0; transition: opacity 0.3s; }
        .modal-backdrop.active { display: block; opacity: 1; }
        .bottom-sheet { position: absolute; bottom: 0; left: 0; width: 100%; background: var(--card); border-radius: 24px 24px 0 0; padding: 24px; z-index: 301; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.1, 0.9, 0.2, 1); border-top: 1px solid var(--border); }
        .bottom-sheet.active { transform: translateY(0); }
        
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1.25rem; font-weight: 600; }
        .close-btn { background: none; border: none; color: var(--text-2); font-size: 1.5rem; cursor: pointer; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.875rem; color: var(--text-2); margin-bottom: 8px; }
        .form-control { width: 100%; background: var(--bg); border: 1px solid var(--border); padding: 12px 16px; border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 1rem; outline: none; }
        .form-control:focus { border-color: var(--accent); }
        .row-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        .tabs { display: flex; background: var(--bg); border-radius: 12px; padding: 4px; margin-bottom: 16px; }
        .tab-btn { flex: 1; padding: 8px; text-align: center; border-radius: 8px; font-size: 0.875rem; cursor: pointer; transition: background 0.2s; }
        .tab-btn.active { background: var(--card-2); color: var(--text); font-weight: 500; }

        /* GYM MODE OVERLAY */
        #gym-mode-overlay { background: var(--bg); }
        .gym-progress-bar { height: 4px; background: var(--card-2); width: 100%; }
        .gym-progress-fill { height: 100%; background: var(--accent); width: 0%; transition: width 0.3s; }
        .gym-header { display: flex; justify-content: space-between; align-items: center; padding: 16px; border-bottom: 1px solid var(--border); }
        .gym-main { flex: 1; display: flex; flex-direction: column; padding: 24px 16px; overflow-y: auto; align-items: center; }
        
        .exercise-display { text-align: center; margin-bottom: 32px; width: 100%; }
        .exercise-display .emoji { font-size: 4rem; margin-bottom: 16px; }
        .exercise-display h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 8px; }
        .exercise-display p { color: var(--text-2); font-size: 1rem; }
        
        .set-dots { display: flex; gap: 8px; justify-content: center; margin-bottom: 24px; }
        .set-dot { width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--border); }
        .set-dot.filled { background: var(--accent); border-color: var(--accent); }
        
        .controls-row { display: flex; gap: 24px; width: 100%; margin-bottom: 40px; }
        .control-group { flex: 1; background: var(--card); border-radius: 20px; padding: 16px; border: 1px solid var(--border); text-align: center; }
        .control-label { font-size: 0.75rem; color: var(--text-2); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .control-stepper { display: flex; justify-content: space-between; align-items: center; }
        .stepper-btn { width: 40px; height: 40px; border-radius: 50%; background: var(--card-2); border: none; color: var(--text); font-size: 1.5rem; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .stepper-val { font-size: 1.75rem; font-weight: 700; }
        
        .mark-done-btn { width: 100%; height: 64px; background: var(--accent); color: var(--bg); border: none; border-radius: 32px; font-size: 1.25rem; font-weight: 800; box-shadow: var(--accent-glow); display: flex; align-items: center; justify-content: center; cursor: pointer; margin-top: auto; }
        
        .next-preview { background: var(--card-2); padding: 16px; text-align: center; font-size: 0.875rem; color: var(--text-2); margin-top: 16px; border-radius: 12px; width: 100%; }

        /* REST PANEL */
        .rest-panel { position: absolute; inset: 0; background: rgba(8,8,15,0.95); z-index: 10; display: none; flex-direction: column; align-items: center; justify-content: center; }
        .rest-panel.active { display: flex; animation: fadeIn 0.3s; }
        .rest-title { font-size: 1.5rem; font-weight: 700; color: var(--accent); margin-bottom: 32px; letter-spacing: 2px; }
        .rest-timer { position: relative; width: 200px; height: 200px; display: flex; align-items: center; justify-content: center; margin-bottom: 40px; }
        .rest-timer svg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: rotate(-90deg); }
        .rest-timer-bg { fill: none; stroke: var(--card-2); stroke-width: 8; }
        .rest-timer-path { fill: none; stroke: var(--accent); stroke-width: 8; stroke-linecap: round; transition: stroke-dashoffset 1s linear; }
        .rest-time-text { font-size: 3rem; font-weight: 800; font-variant-numeric: tabular-nums; }
        .skip-btn { padding: 12px 32px; border-radius: 24px; border: 1px solid var(--text-2); background: none; color: var(--text); font-size: 1rem; cursor: pointer; }

        /* ONBOARDING OVERLAY */
        #onboarding { background: var(--bg); padding: 24px; justify-content: center; }
        .onb-step { display: none; text-align: center; animation: fadeIn 0.3s; }
        .onb-step.active { display: block; }
        .onb-dots { display: flex; gap: 8px; justify-content: center; margin-bottom: 40px; }
        .onb-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--card-2); transition: background 0.3s; }
        .onb-dot.active { background: var(--accent); }
        .onb-title { font-size: 1.75rem; font-weight: 700; margin-bottom: 12px; }
        .onb-desc { color: var(--text-2); margin-bottom: 32px; }
        .onb-card-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 32px; }
        .onb-card { background: var(--card); border: 2px solid var(--border); border-radius: 16px; padding: 20px 12px; cursor: pointer; transition: all 0.2s; }
        .onb-card.selected { border-color: var(--accent); background: var(--accent-dim); }
        .onb-card i, .onb-card .emoji { font-size: 2rem; margin-bottom: 8px; display: block; }
        .onb-card span { font-weight: 600; font-size: 0.9rem; }
        .onb-chips { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-bottom: 32px; }
        .onb-chip { padding: 10px 20px; background: var(--card); border: 1px solid var(--border); border-radius: 24px; font-size: 0.9rem; cursor: pointer; }
        .onb-chip.selected { background: var(--accent); color: var(--bg); border-color: var(--accent); }
        .onb-btn-row { display: flex; gap: 16px; }
        .onb-btn-row .btn-secondary { flex: 1; padding: 16px; background: var(--card); border: none; border-radius: 16px; color: var(--text); font-weight: 600; }
        .onb-btn-row .btn-primary { flex: 2; }

        /* ANIMATIONS */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(34,212,126,0.4); } 70% { box-shadow: 0 0 0 15px rgba(34,212,126,0); } 100% { box-shadow: 0 0 0 0 rgba(34,212,126,0); } }
        @keyframes celebrateIn { 0% { transform: scale(0.8); opacity: 0; } 50% { transform: scale(1.1); } 100% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>
<div id="app-root">

    <!-- GLOBAL APP HEADER (MATCHING REFERENCE) -->
    <header class="app-global-header">
        <button class="header-pill-btn" onclick="window.location.href='profile.php'" title="Dashboard Menu">
            <i class="ti ti-menu-2"></i>
        </button>
        <div class="header-brand">FitSync</div>
        <button class="header-pill-btn" onclick="showCoachNotif()" title="Notifications">
            <i class="ti ti-bell"></i>
            <span class="notif-badge-dot">1</span>
        </button>
    </header>

    <!-- SCREEN: HOME -->
    <div id="screen-home" class="screen active">
        <div class="top-bar">
            <div class="greeting">
                <h1 id="greeting-text">Good morning, <?php echo htmlspecialchars($firstName); ?>!</h1>
                <p>Ready to crush your goals?</p>
            </div>
            <div class="avatar"><?php echo strtoupper(substr($firstName, 0, 1)); ?></div>
        </div>

        <div class="ai-insight">
            <i class="ti ti-sparkles"></i>
            <p><strong>AI Insight:</strong> You're on a 3-day workout streak. Keep it up and try to hit 150g protein today for optimal recovery.</p>
        </div>

        <div class="card" onclick="switchTab('nutrition')" style="cursor:pointer;position:relative" title="Open Nutrition Tracker">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h3 class="card-title" style="margin-bottom:0"><i class="ti ti-apple"></i> Nutrition Target</h3>
                <span style="font-size:0.75rem;color:#ff4d4d;font-weight:600;display:flex;align-items:center;gap:3px;background:rgba(204,26,26,0.12);padding:4px 10px;border-radius:12px;border:1px solid rgba(204,26,26,0.25)">
                    Food Log <i class="ti ti-chevron-right"></i>
                </span>
            </div>
            <div class="macro-ring-container">
                <svg class="macro-ring" viewBox="0 0 100 100">
                    <circle class="macro-circle-bg" cx="50" cy="50" r="40"></circle>
                    <circle class="macro-circle macro-fats" cx="50" cy="50" r="40" stroke-dasharray="251.2" stroke-dashoffset="251.2" id="ring-fats"></circle>
                    <circle class="macro-circle macro-carbs" cx="50" cy="50" r="40" stroke-dasharray="251.2" stroke-dashoffset="251.2" id="ring-carbs"></circle>
                    <circle class="macro-circle macro-protein" cx="50" cy="50" r="40" stroke-dasharray="251.2" stroke-dashoffset="251.2" id="ring-protein"></circle>
                </svg>
                <div class="macro-center">
                    <div class="macro-cals" id="home-cals">0</div>
                    <div class="macro-label">kcal left</div>
                </div>
            </div>
            <div class="stats-row">
                <div><div class="stat-value" style="color:var(--accent)" id="home-p">0g</div><div class="stat-label">Protein</div></div>
                <div><div class="stat-value" style="color:var(--blue)" id="home-c">0g</div><div class="stat-label">Carbs</div></div>
                <div><div class="stat-value" style="color:var(--yellow)" id="home-f">0g</div><div class="stat-label">Fats</div></div>
            </div>
        </div>

        <div class="card today-workout-card">
            <h3 class="card-title"><i class="ti ti-barbell"></i> Today's Plan</h3>
            <h2 id="home-workout-name" style="margin-bottom:8px">Push Day A</h2>
            <p style="color:var(--text-2);font-size:0.875rem;margin-bottom:16px"><span id="home-workout-focus">Chest & Shoulders</span> • <span id="home-workout-excount">6 exercises</span></p>
            <div class="play-btn" onclick="switchTab('workouts')"><i class="ti ti-player-play-filled"></i></div>
        </div>

        <div class="card">
            <h3 class="card-title"><i class="ti ti-droplet"></i> Hydration</h3>
            <div class="water-tracker" id="water-tracker">
                <span class="water-glass" onclick="setWaterGlass(1)">💧</span>
                <span class="water-glass" onclick="setWaterGlass(2)">💧</span>
                <span class="water-glass" onclick="setWaterGlass(3)">💧</span>
                <span class="water-glass" onclick="setWaterGlass(4)">💧</span>
                <span class="water-glass" onclick="setWaterGlass(5)">💧</span>
                <span class="water-glass" onclick="setWaterGlass(6)">💧</span>
                <span class="water-glass" onclick="setWaterGlass(7)">💧</span>
                <span class="water-glass" onclick="setWaterGlass(8)">💧</span>
            </div>
            <p style="text-align:center;font-size:0.75rem;color:var(--text-2);margin-top:12px" id="water-text">0/8 glasses</p>
        </div>

        <div class="stats-row" style="margin-bottom:0">
            <div class="stat-card">
                <div class="stat-value"><?php echo $streak; ?> <i class="ti ti-flame" style="color:var(--gym-red);font-size:1rem"></i></div>
                <div class="stat-label">Day Streak</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($workoutHistory); ?></div>
                <div class="stat-label">Workouts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo !empty($weightHistory) && count($weightHistory)>1 ? sprintf('%+.1f', $weightHistory[0]['weight_kg'] - $weightHistory[count($weightHistory)-1]['weight_kg']) : '0.0'; ?>kg</div>
                <div class="stat-label">This Month</div>
            </div>
        </div>
    </div>

    <!-- SCREEN: NUTRITION -->
    <div id="screen-nutrition" class="screen">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <div style="display:flex;align-items:center;gap:10px">
                <button onclick="switchTab('home')" style="width:36px;height:36px;border-radius:10px;background:var(--card-2);border:1px solid var(--border);color:var(--text);display:flex;align-items:center;justify-content:center;cursor:pointer">
                    <i class="ti ti-arrow-left"></i>
                </button>
                <h1 style="font-size:1.35rem;font-weight:700;margin:0">Nutrition & Food Log</h1>
            </div>
            <button onclick="openLogMealModal('snack')" style="padding:6px 12px;background:rgba(204,26,26,0.15);border:1px solid rgba(204,26,26,0.3);border-radius:12px;color:#ff4d4d;font-size:0.75rem;font-weight:600;cursor:pointer">
                + Quick Log
            </button>
        </div>
        <div class="date-nav">
            <button><i class="ti ti-chevron-left"></i></button>
            <span>Today, <?php echo date('M j'); ?></span>
            <button style="opacity:0.3"><i class="ti ti-chevron-right"></i></button>
        </div>


        <div class="macro-bars">
            <div class="macro-bar-row">
                <div class="macro-bar-header"><span>Protein</span><span id="nutr-p-text">0 / 150g</span></div>
                <div class="macro-bar-track"><div class="macro-bar-fill" style="background:var(--accent);width:0%" id="nutr-p-bar"></div></div>
            </div>
            <div class="macro-bar-row">
                <div class="macro-bar-header"><span>Carbs</span><span id="nutr-c-text">0 / 200g</span></div>
                <div class="macro-bar-track"><div class="macro-bar-fill" style="background:var(--blue);width:0%" id="nutr-c-bar"></div></div>
            </div>
            <div class="macro-bar-row">
                <div class="macro-bar-header"><span>Fats</span><span id="nutr-f-text">0 / 65g</span></div>
                <div class="macro-bar-track"><div class="macro-bar-fill" style="background:var(--yellow);width:0%" id="nutr-f-bar"></div></div>
            </div>
        </div>

        <div id="meals-container">
            <!-- Populated by JS -->
        </div>

        <div class="ai-insight" style="margin-top:24px">
            <i class="ti ti-robot"></i>
            <p>💡 <strong>AI suggests:</strong> Based on your remaining macros, a tuna salad with light mayo would be perfect for dinner.</p>
        </div>
    </div>

    <!-- SCREEN: WORKOUTS -->
    <div id="screen-workouts" class="screen">
        <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:16px">Plan</h1>
        
        <div class="week-strip" id="week-strip">
            <!-- Populated by JS -->
        </div>

        <div class="card" style="border:1px solid var(--accent-dim)">
            <h3 class="card-title" id="wk-day-name">Push Day A</h3>
            <p style="color:var(--text-2);font-size:0.875rem;margin-bottom:16px"><span id="wk-focus">Chest & Shoulders</span> • <span id="wk-excount">6 exercises</span></p>
            <button class="btn-primary" onclick="openGymMode()"><i class="ti ti-player-play-filled"></i> Start Workout</button>
        </div>

        <div class="exercise-list" id="wk-exercises">
            <!-- Populated by JS -->
        </div>
        
        <div style="text-align:center;margin-top:24px">
            <a href="#" style="color:var(--text-2);font-size:0.875rem">View All Programs</a>
        </div>
    </div>

    <!-- SCREEN: AI COACH (MATCHING REFERENCE UI) -->
    <div id="screen-ai" class="screen" style="padding-top:4px;padding-bottom:78px">
        <div class="ai-screen-container">
            <div class="ai-coach-card">
                <!-- Red Badge Pill Top Left -->
                <div class="ai-badge-pill">
                    <span class="dot"></span> AI Coach
                </div>

                <!-- Center Visualizer Stage with Concentric HUD Rings + ECG + Mascot Avatar -->
                <div class="ai-center-stage">
                    <div class="hud-ring-outer"></div>
                    <div class="hud-ring-mid"></div>
                    <div class="hud-ring-inner"></div>

                    <!-- Glowing ECG / Heartbeat Waveform SVG -->
                    <svg class="ecg-wave-svg" viewBox="0 0 400 100" preserveAspectRatio="none">
                        <path d="M0,50 L120,50 L135,42 L145,58 L155,50 L170,50 L180,20 L195,85 L205,35 L215,60 L225,50 L245,50 L255,42 L265,55 L275,50 L400,50" 
                              fill="none" stroke="#e51e1e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>

                    <!-- Exact Black Cat Mascot Circle Avatar -->
                    <div class="mascot-avatar-circle" onclick="toggleVoiceMode()" title="Tap to talk with Coach AI">
                        <img src="assets/gym_buddy_mascot.jpg" alt="FitSync AI Mascot" id="ai-mascot-img">
                    </div>
                </div>

                <!-- Bottom Section: Tap to Talk + Audio Waveform -->
                <div class="ai-talk-section" onclick="toggleVoiceMode()">
                    <div class="tap-talk-text" id="tap-talk-status">Tap to Talk</div>
                    <div class="audio-bars-wave" id="audio-visualizer-bars">
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                    </div>
                </div>

                <!-- Interactive Chat Drawer / Popover -->
                <div class="ai-chat-drawer" id="ai-chat-drawer">
                    <div class="ai-drawer-head">
                        <div style="display:flex;align-items:center;gap:10px">
                            <img src="assets/gym_buddy_mascot.jpg" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #cc1a1a">
                            <div>
                                <div style="font-weight:700;font-size:0.95rem;color:#fff">FitSync AI Coach</div>
                                <div style="font-size:0.72rem;color:#ff4d4d">● Voice & Chat Assistant</div>
                            </div>
                        </div>
                        <button class="close-btn" onclick="closeChatDrawer()"><i class="ti ti-x"></i></button>
                    </div>
                    <div class="quick-prompts">
                        <div class="quick-prompt" onclick="sendQuickPrompt('plan')">📋 Today's plan</div>
                        <div class="quick-prompt" onclick="sendQuickPrompt('diet')">🥗 Meal ideas</div>
                        <div class="quick-prompt" onclick="sendQuickPrompt('tired')">😴 I'm tired</div>
                        <div class="quick-prompt" onclick="sendQuickPrompt('progress')">📈 My progress</div>
                        <div class="quick-prompt" onclick="sendQuickPrompt('modify')">💪 Modify workout</div>
                        <div class="quick-prompt" onclick="sendQuickPrompt('sore')">🤕 I'm sore</div>
                    </div>
                    <div class="chat-area" id="ai-chat">
                        <div class="chat-bubble chat-ai">Hey <?php echo htmlspecialchars($firstName); ?>! I'm your AI fitness coach. How can I help you crush your goals today?</div>
                    </div>
                    <div class="chat-input-bar">
                        <input type="text" id="ai-input" placeholder="Ask your coach anything..." onkeypress="if(event.key==='Enter') sendMessage()">
                        <button onclick="sendMessage()"><i class="ti ti-send"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SCREEN: PROGRESS -->
    <div id="screen-progress" class="screen">
        <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:16px">Progress</h1>
        
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:16px">
                <div>
                    <h3 class="card-title">Weight Trend</h3>
                    <div style="font-size:2rem;font-weight:800"><?php echo !empty($weightHistory) ? $weightHistory[0]['weight_kg'] : ($gbProfile['weight_kg'] ?? 70); ?><span style="font-size:1rem;color:var(--text-2);font-weight:500">kg</span></div>
                </div>
                <button style="padding:8px 12px;background:var(--card-2);border:1px solid var(--border);border-radius:12px;color:var(--text);font-size:0.75rem;cursor:pointer" onclick="openLogWeightModal()">+ Log</button>
            </div>
            <div class="chart-container">
                <canvas id="weightChart"></canvas>
            </div>
        </div>

        <h3 style="font-size:1rem;font-weight:600;margin:24px 0 12px 0">Body Measurements</h3>
        <div class="measurements-grid" style="margin-bottom:24px">
            <div class="measure-item"><span class="measure-label">Chest</span><span class="measure-val">98 cm</span></div>
            <div class="measure-item"><span class="measure-label">Waist</span><span class="measure-val">82 cm</span></div>
            <div class="measure-item"><span class="measure-label">Arms</span><span class="measure-val">36 cm</span></div>
            <div class="measure-item"><span class="measure-label">Thighs</span><span class="measure-val">58 cm</span></div>
        </div>

        <h3 style="font-size:1rem;font-weight:600;margin:24px 0 12px 0">Milestones</h3>
        <div class="card" style="padding:0 20px">
            <div class="milestone done">
                <div class="milestone-icon"><i class="ti ti-check"></i></div>
                <div class="milestone-text">First Workout Completed</div>
            </div>
            <div class="milestone done">
                <div class="milestone-icon"><i class="ti ti-check"></i></div>
                <div class="milestone-text">7-Day Streak</div>
            </div>
            <div class="milestone locked">
                <div class="milestone-icon"><i class="ti ti-lock"></i></div>
                <div class="milestone-text">30 Workouts Total</div>
            </div>
            <div class="milestone locked">
                <div class="milestone-icon"><i class="ti ti-lock"></i></div>
                <div class="milestone-text">Reach Goal Weight</div>
            </div>
        </div>
    </div>

    <!-- BOTTOM NAV (MATCHING EXACT REFERENCE) -->
    <nav class="bottom-nav">
        <div class="nav-item active" onclick="switchTab('home')" id="nav-home">
            <i class="ti ti-home"></i>
            <span>Home</span>
        </div>
        <div class="nav-item" onclick="switchTab('workouts')" id="nav-workouts">
            <i class="ti ti-barbell"></i>
            <span>Programs</span>
        </div>
        <div class="nav-item center-ai-btn" onclick="switchTab('ai')" id="nav-ai">
            <i class="ti ti-cat"></i>
            <span>AI Coach</span>
        </div>
        <div class="nav-item" onclick="switchTab('progress')" id="nav-progress">
            <i class="ti ti-chart-line"></i>
            <span>Progress</span>
        </div>
        <div class="nav-item" onclick="window.location.href='profile.php'" id="nav-profile">
            <i class="ti ti-user"></i>
            <span>Profile</span>
        </div>
    </nav>


    <!-- ONBOARDING OVERLAY -->
    <div id="onboarding" class="overlay">
        <!-- Step 0 -->
        <div class="onb-step active" id="onb-0">
            <i class="ti ti-barbell" style="font-size:4rem;color:var(--accent);margin-bottom:24px"></i>
            <h1 class="onb-title">Welcome to Gym Buddy</h1>
            <p class="onb-desc">Let's set up your profile to personalize your AI coach and plans.</p>
            <button class="btn-primary" style="margin-top:40px" onclick="onboardingNextStep()">Get Started</button>
        </div>
        <!-- Step 1 -->
        <div class="onb-step" id="onb-1">
            <div class="onb-dots"><div class="onb-dot active"></div><div class="onb-dot"></div><div class="onb-dot"></div><div class="onb-dot"></div></div>
            <h1 class="onb-title">Your Stats</h1>
            <p class="onb-desc">Basic info helps us calculate your macros.</p>
            <div style="text-align:left;margin-bottom:20px">
                <label style="display:block;margin-bottom:8px;color:var(--text-2);font-size:0.875rem">Height (cm)</label>
                <input type="number" id="onb-height" class="form-control" value="175">
            </div>
            <div style="text-align:left;margin-bottom:20px">
                <label style="display:block;margin-bottom:8px;color:var(--text-2);font-size:0.875rem">Current Weight (kg)</label>
                <input type="number" id="onb-weight" class="form-control" value="75">
            </div>
            <div style="text-align:left;margin-bottom:40px">
                <label style="display:block;margin-bottom:8px;color:var(--text-2);font-size:0.875rem">Age</label>
                <input type="number" id="onb-age" class="form-control" value="25">
            </div>
            <div class="onb-btn-row">
                <button class="btn-secondary" onclick="onboardingPrevStep()">Back</button>
                <button class="btn-primary" onclick="onboardingNextStep()">Next</button>
            </div>
        </div>
        <!-- Step 2 -->
        <div class="onb-step" id="onb-2">
            <div class="onb-dots"><div class="onb-dot"></div><div class="onb-dot active"></div><div class="onb-dot"></div><div class="onb-dot"></div></div>
            <h1 class="onb-title">Main Goal</h1>
            <p class="onb-desc">What do you want to achieve?</p>
            <div class="onb-card-grid">
                <div class="onb-card selected" onclick="selCard(this,'body_goal','lose_fat')"><span class="emoji">🔥</span><span>Lose Fat</span></div>
                <div class="onb-card" onclick="selCard(this,'body_goal','build_muscle')"><span class="emoji">💪</span><span>Build Muscle</span></div>
                <div class="onb-card" onclick="selCard(this,'body_goal','maintain')"><span class="emoji">⚖️</span><span>Maintain</span></div>
                <div class="onb-card" onclick="selCard(this,'body_goal','athletic')"><span class="emoji">🏃</span><span>Athletic</span></div>
            </div>
            <input type="hidden" id="onb-body_goal" value="lose_fat">
            <div class="onb-btn-row">
                <button class="btn-secondary" onclick="onboardingPrevStep()">Back</button>
                <button class="btn-primary" onclick="onboardingNextStep()">Next</button>
            </div>
        </div>
        <!-- Step 3 -->
        <div class="onb-step" id="onb-3">
            <div class="onb-dots"><div class="onb-dot"></div><div class="onb-dot"></div><div class="onb-dot active"></div><div class="onb-dot"></div></div>
            <h1 class="onb-title">Fitness Level</h1>
            <p class="onb-desc">How experienced are you?</p>
            <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:40px">
                <div class="onb-card selected" style="padding:16px;display:flex;align-items:center;gap:16px" onclick="selCard(this,'fitness_level','beginner')">
                    <span class="emoji" style="margin:0">🌱</span>
                    <div style="text-align:left"><span style="display:block;font-size:1rem;margin-bottom:4px">Beginner</span><span style="font-size:0.75rem;color:var(--text-2);font-weight:400">Just starting out</span></div>
                </div>
                <div class="onb-card" style="padding:16px;display:flex;align-items:center;gap:16px" onclick="selCard(this,'fitness_level','intermediate')">
                    <span class="emoji" style="margin:0">⚡</span>
                    <div style="text-align:left"><span style="display:block;font-size:1rem;margin-bottom:4px">Intermediate</span><span style="font-size:0.75rem;color:var(--text-2);font-weight:400">Consistent for 6+ months</span></div>
                </div>
                <div class="onb-card" style="padding:16px;display:flex;align-items:center;gap:16px" onclick="selCard(this,'fitness_level','advanced')">
                    <span class="emoji" style="margin:0">🔥</span>
                    <div style="text-align:left"><span style="display:block;font-size:1rem;margin-bottom:4px">Advanced</span><span style="font-size:0.75rem;color:var(--text-2);font-weight:400">Training for years</span></div>
                </div>
            </div>
            <input type="hidden" id="onb-fitness_level" value="beginner">
            <div class="onb-btn-row">
                <button class="btn-secondary" onclick="onboardingPrevStep()">Back</button>
                <button class="btn-primary" onclick="onboardingNextStep()">Next</button>
            </div>
        </div>
        <!-- Step 4 -->
        <div class="onb-step" id="onb-4">
            <div class="onb-dots"><div class="onb-dot"></div><div class="onb-dot"></div><div class="onb-dot"></div><div class="onb-dot active"></div></div>
            <h1 class="onb-title">Diet Preferences</h1>
            <p class="onb-desc">Any specific eating styles?</p>
            <div class="onb-chips">
                <div class="onb-chip selected" onclick="selChip(this,'diet_preference','no_restriction')">No Restriction</div>
                <div class="onb-chip" onclick="selChip(this,'diet_preference','vegetarian')">Vegetarian</div>
                <div class="onb-chip" onclick="selChip(this,'diet_preference','vegan')">Vegan</div>
                <div class="onb-chip" onclick="selChip(this,'diet_preference','keto')">Keto</div>
                <div class="onb-chip" onclick="selChip(this,'diet_preference','high_protein')">High Protein</div>
            </div>
            <input type="hidden" id="onb-diet_preference" value="no_restriction">
            <div class="onb-btn-row">
                <button class="btn-secondary" onclick="onboardingPrevStep()">Back</button>
                <button class="btn-primary" onclick="onboardingNextStep()">Complete</button>
            </div>
        </div>
        <!-- Step 5: Loading -->
        <div class="onb-step" id="onb-5">
            <div style="height:100px;display:flex;align-items:center;justify-content:center;margin:40px 0">
                <i class="ti ti-loader" style="font-size:4rem;color:var(--accent);animation:pulse 1.5s infinite"></i>
            </div>
            <h1 class="onb-title">AI is generating your plan...</h1>
            <p class="onb-desc">Crunching the numbers and building your routine.</p>
        </div>
    </div>

    <!-- GYM MODE OVERLAY -->
    <div id="gym-mode-overlay" class="overlay">
        <div class="gym-progress-bar"><div class="gym-progress-fill" id="gym-progress"></div></div>
        <div class="gym-header">
            <button class="close-btn" onclick="closeGymMode()"><i class="ti ti-x"></i></button>
            <div style="text-align:center">
                <div style="font-size:0.75rem;color:var(--text-2);text-transform:uppercase;letter-spacing:1px" id="gym-wk-name">Push Day A</div>
                <div style="font-weight:600;font-size:0.875rem" id="gym-ex-counter">Exercise 1 of 6</div>
            </div>
            <div style="width:24px"></div>
        </div>
        
        <div class="gym-main">
            <div class="exercise-display">
                <div class="emoji" id="gym-emoji">🏋️</div>
                <h2 id="gym-ex-name">Bench Press</h2>
                <p id="gym-ex-muscle">Chest</p>
            </div>
            
            <div style="font-size:0.875rem;font-weight:600;color:var(--accent);margin-bottom:12px;letter-spacing:1px" id="gym-set-label">SET 1 OF 4</div>
            <div class="set-dots" id="gym-set-dots">
                <div class="set-dot filled"></div><div class="set-dot"></div><div class="set-dot"></div><div class="set-dot"></div>
            </div>
            
            <div class="controls-row">
                <div class="control-group">
                    <div class="control-label">Reps</div>
                    <div class="control-stepper">
                        <button class="stepper-btn" onclick="adjustReps(-1)">-</button>
                        <span class="stepper-val" id="gym-reps">10</span>
                        <button class="stepper-btn" onclick="adjustReps(1)">+</button>
                    </div>
                </div>
                <div class="control-group">
                    <div class="control-label">Weight (kg)</div>
                    <div class="control-stepper">
                        <button class="stepper-btn" onclick="adjustWeight(-2.5)">-</button>
                        <span class="stepper-val" id="gym-weight">60</span>
                        <button class="stepper-btn" onclick="adjustWeight(2.5)">+</button>
                    </div>
                </div>
            </div>
            
            <button class="mark-done-btn" onclick="markSetDone()"><i class="ti ti-check"></i> Mark Set Done</button>
            <div class="next-preview" id="gym-next-preview">NEXT UP · Overhead Press · 3 sets</div>
        </div>
        
        <!-- REST PANEL inside gym overlay -->
        <div class="rest-panel" id="rest-panel">
            <div class="rest-title">REST</div>
            <div class="rest-timer">
                <svg viewBox="0 0 100 100">
                    <circle class="rest-timer-bg" cx="50" cy="50" r="45"></circle>
                    <circle class="rest-timer-path" cx="50" cy="50" r="45" stroke-dasharray="282.7" stroke-dashoffset="0" id="rest-svg-circle"></circle>
                </svg>
                <div class="rest-time-text" id="rest-text">1:30</div>
            </div>
            <button class="skip-btn" onclick="skipRest()">Skip Rest</button>
        </div>
        
        <!-- WORKOUT COMPLETE PANEL -->
        <div class="rest-panel" id="workout-complete-panel" style="background:var(--bg)">
            <i class="ti ti-trophy" style="font-size:6rem;color:var(--yellow);margin-bottom:24px;animation:celebrateIn 0.5s ease-out"></i>
            <h1 style="font-size:2rem;font-weight:800;margin-bottom:8px">Workout Complete!</h1>
            <p style="color:var(--text-2);margin-bottom:32px">Awesome job crushing your goals.</p>
            <div class="card" style="width:80%;max-width:300px;text-align:left;margin-bottom:40px">
                <div style="display:flex;justify-content:space-between;margin-bottom:12px"><span>Duration:</span><strong id="wc-time">45 min</strong></div>
                <div style="display:flex;justify-content:space-between;margin-bottom:12px"><span>Total Sets:</span><strong id="wc-sets">20</strong></div>
                <div style="display:flex;justify-content:space-between"><span>Est. Burn:</span><strong id="wc-cals">320 kcal</strong></div>
            </div>
            <button class="btn-primary" style="width:80%;max-width:300px" onclick="finishWorkout()">Finish</button>
        </div>
    </div>

    <!-- MODAL BACKDROP -->
    <div class="modal-backdrop" id="modal-backdrop" onclick="closeAllModals()"></div>

    <!-- LOG MEAL MODAL -->
    <div class="bottom-sheet" id="modal-meal">
        <div class="modal-header">
            <h2 class="modal-title">Add Food</h2>
            <button class="close-btn" onclick="closeLogMealModal()"><i class="ti ti-x"></i></button>
        </div>
        <div class="tabs">
            <div class="tab-btn active" onclick="setMealType(this, 'breakfast')">Breakfast</div>
            <div class="tab-btn" onclick="setMealType(this, 'lunch')">Lunch</div>
            <div class="tab-btn" onclick="setMealType(this, 'dinner')">Dinner</div>
            <div class="tab-btn" onclick="setMealType(this, 'snack')">Snack</div>
        </div>
        <input type="hidden" id="meal-type-input" value="breakfast">
        
        <div class="form-group">
            <label>Food Name</label>
            <input type="text" class="form-control" id="meal-name" placeholder="e.g., Chicken Breast 100g">
        </div>
        <div class="row-inputs">
            <div class="form-group">
                <label>Calories</label>
                <input type="number" class="form-control" id="meal-cals" placeholder="0">
            </div>
            <div class="form-group">
                <label>Protein (g)</label>
                <input type="number" class="form-control" id="meal-p" placeholder="0">
            </div>
            <div class="form-group">
                <label>Carbs (g)</label>
                <input type="number" class="form-control" id="meal-c" placeholder="0">
            </div>
            <div class="form-group">
                <label>Fats (g)</label>
                <input type="number" class="form-control" id="meal-f" placeholder="0">
            </div>
        </div>
        <div style="margin-top:16px">
            <label style="font-size:0.75rem;font-weight:600;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;display:block">Quick Add</label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
                <button onclick="quickAddFood('Chicken Breast 100g',165,31,0,3.6)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🍗 Chicken 100g</button>
                <button onclick="quickAddFood('White Rice 100g',130,2.7,28,0.3)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🍚 Rice 100g</button>
                <button onclick="quickAddFood('2 Whole Eggs',143,12.6,0.7,9.5)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🥚 2 Eggs</button>
                <button onclick="quickAddFood('Whey Protein Shake',120,25,3,1.5)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🥛 Protein Shake</button>
                <button onclick="quickAddFood('Banana (medium)',89,1.1,23,0.3)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🍌 Banana</button>
                <button onclick="quickAddFood('Oatmeal 50g (dry)',190,6.5,33,3.5)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🌾 Oats 50g</button>
                <button onclick="quickAddFood('Canned Tuna 100g',116,25.5,0,1)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🐟 Tuna 100g</button>
                <button onclick="quickAddFood('Greek Yogurt 150g',88,10,4,0.7)" style="padding:6px 10px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:0.72rem;cursor:pointer">🥛 Greek Yogurt</button>
            </div>
        </div>
        <button class="btn-primary" style="margin-top:4px" onclick="submitLogMeal()">Add Food</button>

    </div>

    <!-- LOG WEIGHT MODAL -->
    <div class="bottom-sheet" id="modal-weight">
        <div class="modal-header">
            <h2 class="modal-title">Log Weight</h2>
            <button class="close-btn" onclick="closeLogWeightModal()"><i class="ti ti-x"></i></button>
        </div>
        <div class="form-group">
            <label>Weight (kg)</label>
            <input type="number" class="form-control" id="weight-input" step="0.1" value="<?php echo !empty($weightHistory) ? $weightHistory[0]['weight_kg'] : 70; ?>">
        </div>
        <button class="btn-primary" style="margin-top:16px" onclick="submitLogWeight()">Save</button>
    </div>

</div>

<script>
// === CONSTANTS ===
const GB_PROFILE = <?php echo json_encode($gbProfile ?: []); ?>;
let GB_NUTRITION = <?php echo json_encode($todayNutrition); ?>;
const GB_WEIGHT_HISTORY = <?php echo json_encode($weightHistory); ?>;
const GB_WORKOUT_DATES = <?php echo json_encode($workoutDates); ?>;
const GB_STREAK = <?php echo $streak; ?>;
const GB_FIRST_NAME = <?php echo json_encode($firstName); ?>;
const HAS_PROFILE = <?php echo $hasProfile ? 'true' : 'false'; ?>;

// === WORKOUT DATA (AI-generated mock) ===
const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const WORKOUT_PLAN = [
    { name: 'Rest Day', focus: 'Recovery', exercises: [], isRest: true },
    { name: 'Push Day A', focus: 'Chest & Shoulders', color: '#f43f5e', exercises: [
        { name: 'Bench Press', sets: 4, reps: 10, rest: 90, muscle: 'Chest', emoji: '🏋️' },
        { name: 'Overhead Press', sets: 3, reps: 10, rest: 90, muscle: 'Shoulders', emoji: '💪' },
        { name: 'Incline DB Press', sets: 3, reps: 12, rest: 75, muscle: 'Upper Chest', emoji: '🏋️' },
        { name: 'Cable Flyes', sets: 3, reps: 15, rest: 60, muscle: 'Chest', emoji: '🔗' },
        { name: 'Tricep Pushdown', sets: 3, reps: 15, rest: 60, muscle: 'Triceps', emoji: '💪' },
        { name: 'Lateral Raises', sets: 4, reps: 15, rest: 60, muscle: 'Side Delts', emoji: '🦅' },
    ]},
    { name: 'Pull Day A', focus: 'Back & Biceps', color: '#60a5fa', exercises: [
        { name: 'Deadlift', sets: 4, reps: 6, rest: 120, muscle: 'Lower Back', emoji: '🏋️' },
        { name: 'Pull-Ups', sets: 4, reps: 8, rest: 90, muscle: 'Lats', emoji: '🔗' },
        { name: 'Barbell Row', sets: 3, reps: 10, rest: 90, muscle: 'Upper Back', emoji: '🏋️' },
        { name: 'Seated Cable Row', sets: 3, reps: 12, rest: 75, muscle: 'Mid Back', emoji: '🔗' },
        { name: 'Barbell Curl', sets: 3, reps: 12, rest: 60, muscle: 'Biceps', emoji: '💪' },
        { name: 'Hammer Curl', sets: 3, reps: 12, rest: 60, muscle: 'Brachialis', emoji: '💪' },
    ]},
    { name: 'Leg Day A', focus: 'Quads & Glutes', color: '#a78bfa', exercises: [
        { name: 'Squat', sets: 4, reps: 8, rest: 120, muscle: 'Quads', emoji: '🦵' },
        { name: 'Romanian Deadlift', sets: 3, reps: 10, rest: 90, muscle: 'Hamstrings', emoji: '🏋️' },
        { name: 'Leg Press', sets: 3, reps: 12, rest: 90, muscle: 'Quads', emoji: '🦵' },
        { name: 'Walking Lunges', sets: 3, reps: 20, rest: 75, muscle: 'Glutes', emoji: '🚶' },
        { name: 'Leg Curl', sets: 3, reps: 15, rest: 60, muscle: 'Hamstrings', emoji: '🦵' },
        { name: 'Calf Raises', sets: 4, reps: 20, rest: 45, muscle: 'Calves', emoji: '👟' },
    ]},
    { name: 'Rest Day', focus: 'Recovery', exercises: [], isRest: true },
    { name: 'Push Day B', focus: 'Shoulders & Triceps', color: '#f43f5e', exercises: [
        { name: 'DB Shoulder Press', sets: 4, reps: 10, rest: 90, muscle: 'Shoulders', emoji: '🏋️' },
        { name: 'Cable Lateral Raise', sets: 4, reps: 15, rest: 60, muscle: 'Side Delts', emoji: '🔗' },
        { name: 'Dips', sets: 3, reps: 12, rest: 75, muscle: 'Chest/Triceps', emoji: '💪' },
        { name: 'Skull Crushers', sets: 3, reps: 12, rest: 75, muscle: 'Triceps', emoji: '🏋️' },
        { name: 'Tricep Overhead Ext.', sets: 3, reps: 15, rest: 60, muscle: 'Triceps', emoji: '💪' },
        { name: 'Front Raises', sets: 3, reps: 15, rest: 60, muscle: 'Front Delts', emoji: '🦅' },
    ]},
    { name: 'Pull Day B + Legs', focus: 'Full Body', color: '#22d47e', exercises: [
        { name: 'Weighted Pull-Ups', sets: 4, reps: 8, rest: 90, muscle: 'Lats', emoji: '🔗' },
        { name: 'T-Bar Row', sets: 4, reps: 10, rest: 90, muscle: 'Back', emoji: '🏋️' },
        { name: 'Face Pulls', sets: 3, reps: 15, rest: 60, muscle: 'Rear Delts', emoji: '🔗' },
        { name: 'Preacher Curl', sets: 3, reps: 12, rest: 60, muscle: 'Biceps', emoji: '💪' },
        { name: 'Hip Thrust', sets: 4, reps: 12, rest: 90, muscle: 'Glutes', emoji: '🦵' },
        { name: 'Bulgarian Split Squat', sets: 3, reps: 10, rest: 90, muscle: 'Quads/Glutes', emoji: '🦵' },
    ]},
];

// === AI MOCK RESPONSES ===
const AI_RESPONSES = {
    plan: '📋 Here\'s your plan for today: **Push Day A** — Bench Press, Overhead Press, Incline DB Press, Cable Flyes, Tricep Pushdown, Lateral Raises. Estimated time: 45-55 mins. Make sure to warm up for 5-10 minutes before starting! 💪',
    diet: '🥗 Based on your macros today, you still need **40g of protein**. I suggest: Grilled chicken breast (100g = 31g protein), a scoop of whey protein shake (25g), or 3 boiled eggs (18g). For your carbs, sweet potato or brown rice would be great with dinner!',
    tired: '😴 Rest is part of the process! On low-energy days, I recommend: (1) A lighter workout or just stretching, (2) Make sure you got 7-9 hours of sleep, (3) Check your protein and calorie intake — you might be under-eating. Want me to adjust today\'s workout to something less intense?',
    progress: '📈 Great progress this month! You\'ve completed **12 workouts** with a **7-day streak**. Your weight trend is on track. Keep hitting your protein targets and you should see more muscle definition in the next 2-3 weeks. You\'re crushing it! 🔥',
    modify: '💪 I can adjust your workout! What do you need? (1) Replace an exercise with an easier variation, (2) Reduce volume (fewer sets), (3) Switch to a cardio session instead. Just let me know and I\'ll update your plan!',
    sore: '🤕 Muscle soreness (DOMS) is normal! Make sure to: (1) Stay hydrated, (2) Get enough protein for muscle repair, (3) Consider a light active recovery session — walking, light stretching, or yoga. If the pain is sharp or joint-related, rest and consult a professional.',
    default: '🤖 I\'m your AI fitness coach! I can help with workout planning, nutrition advice, recovery tips, and progress tracking. AI integration is coming soon — for now, try the quick prompts below! 💪'
};

// === APP STATE ===
let currentDayIndex = new Date().getDay();
let weightChart = null;

// Gym Mode State
let gymActive = false;
let gExIndex = 0;
let gSetIndex = 0;
let gWorkout = null;
let restTimerId = null;
let workoutStartTime = 0;
let totalSetsDone = 0;

// === INITIALIZATION ===
document.addEventListener('DOMContentLoaded', () => {
    if (!HAS_PROFILE) {
        initOnboarding();
    } else {
        initApp();
    }
});

function initApp() {
    document.getElementById('greeting-text').innerText = getGreeting() + ', ' + GB_FIRST_NAME + '!';
    updateHomeTab();
    loadNutritionTab();
    loadWorkoutsTab();
    initWeightChart();
    
    // Load water
    const savedWater = localStorage.getItem('gb_water_' + new Date().toDateString()) || 0;
    setWaterGlass(parseInt(savedWater) || 0, false);
}

// === TABS ===
function switchTab(tabId) {
    document.querySelectorAll('.screen').forEach(el => { el.classList.remove('active'); });
    const scr = document.getElementById('screen-' + tabId);
    if (scr) scr.classList.add('active');
    
    document.querySelectorAll('.nav-item').forEach(el => { el.classList.remove('active'); });
    const navItem = document.getElementById('nav-' + tabId);
    if (navItem) navItem.classList.add('active');
}


function getGreeting() {
    const hr = new Date().getHours();
    if (hr < 12) return 'Good morning';
    if (hr < 18) return 'Good afternoon';
    return 'Good evening';
}

// === HOME TAB ===
function updateHomeTab() {
    initMacroRing();
    const tw = getTodayWorkout();
    if (tw.isRest) {
        document.getElementById('home-workout-name').innerText = 'Rest Day';
        document.getElementById('home-workout-focus').innerText = 'Recovery';
        document.getElementById('home-workout-excount').innerText = '0 exercises';
    } else {
        document.getElementById('home-workout-name').innerText = tw.name;
        document.getElementById('home-workout-focus').innerText = tw.focus;
        document.getElementById('home-workout-excount').innerText = tw.exercises.length + ' exercises';
    }
}

function initMacroRing() {
    let tp=0, tc=0, tf=0, tcal=0;
    GB_NUTRITION.forEach(n => {
        tp += parseFloat(n.protein_g); tc += parseFloat(n.carbs_g); tf += parseFloat(n.fats_g); tcal += parseInt(n.calories);
    });
    
    const maxP = GB_PROFILE.daily_protein || 150;
    const maxC = GB_PROFILE.daily_carbs || 200;
    const maxF = GB_PROFILE.daily_fats || 65;
    const maxCal = GB_PROFILE.daily_calories || 2000;
    
    document.getElementById('home-p').innerText = Math.round(tp) + 'g';
    document.getElementById('home-c').innerText = Math.round(tc) + 'g';
    document.getElementById('home-f').innerText = Math.round(tf) + 'g';
    
    let leftCals = maxCal - tcal;
    document.getElementById('home-cals').innerText = leftCals > 0 ? leftCals : 0;
    
    // Update SVG rings (circumference 251.2)
    const C = 251.2;
    const pPct = Math.min(tp / maxP, 1);
    const cPct = Math.min(tc / maxC, 1);
    const fPct = Math.min(tf / maxF, 1);
    
    // Stack them: Fats -> Carbs -> Protein (z-index simulated by stroke order and offset)
    // We'll just draw them overlapping, easiest is separate rings or dashed arrays.
    // For simplicity, we make them concentric or just simple overlapping.
    // Let's do simple overlapping with varying sizes or just one simple ring showing total cal? 
    // Wait, the prompt says "protein=green, carbs=blue, fats=yellow". We can use stroke-dashoffset.
    setTimeout(() => {
        document.getElementById('ring-protein').style.strokeDashoffset = C - (C * pPct);
        document.getElementById('ring-carbs').style.strokeDashoffset = C - (C * cPct);
        document.getElementById('ring-fats').style.strokeDashoffset = C - (C * fPct);
    }, 100);
}

function setWaterGlass(n, save=true) {
    const glasses = document.querySelectorAll('.water-glass');
    glasses.forEach((g, i) => {
        if (i < n) g.classList.add('filled');
        else g.classList.remove('filled');
    });
    document.getElementById('water-text').innerText = n + '/8 glasses';
    if (save) localStorage.setItem('gb_water_' + new Date().toDateString(), n);
}

// === NUTRITION TAB ===
function loadNutritionTab() {
    let tp=0, tc=0, tf=0, tcal=0;
    const meals = { breakfast:[], lunch:[], dinner:[], snack:[] };
    
    GB_NUTRITION.forEach(n => {
        tp += parseFloat(n.protein_g); tc += parseFloat(n.carbs_g); tf += parseFloat(n.fats_g); tcal += parseInt(n.calories);
        if(meals[n.meal_type]) meals[n.meal_type].push(n);
    });
    
    const maxP = GB_PROFILE.daily_protein || 150;
    const maxC = GB_PROFILE.daily_carbs || 200;
    const maxF = GB_PROFILE.daily_fats || 65;
    
    document.getElementById('nutr-p-text').innerText = Math.round(tp) + ' / ' + maxP + 'g';
    document.getElementById('nutr-c-text').innerText = Math.round(tc) + ' / ' + maxC + 'g';
    document.getElementById('nutr-f-text').innerText = Math.round(tf) + ' / ' + maxF + 'g';
    
    setTimeout(() => {
        document.getElementById('nutr-p-bar').style.width = Math.min((tp/maxP)*100, 100) + '%';
        document.getElementById('nutr-c-bar').style.width = Math.min((tc/maxC)*100, 100) + '%';
        document.getElementById('nutr-f-bar').style.width = Math.min((tf/maxF)*100, 100) + '%';
    }, 100);
    
    const container = document.getElementById('meals-container');
    container.innerHTML = '';
    
    const mealTitles = { breakfast:'🌅 Breakfast', lunch:'☀️ Lunch', dinner:'🌙 Dinner', snack:'🍎 Snack' };
    
    Object.keys(meals).forEach(type => {
        let secHtml = `<div class="meal-section">
            <div class="meal-header">
                <div class="meal-title">${mealTitles[type]}</div>
                <div class="meal-cals">${meals[type].reduce((sum,i)=>sum+parseInt(i.calories),0)} kcal</div>
            </div>`;
            
        meals[type].forEach(item => {
            secHtml += `<div class="food-item">
                <div class="food-info">
                    <h4>${item.food_name}</h4>
                    <div class="food-macros">P: ${item.protein_g}g • C: ${item.carbs_g}g • F: ${item.fats_g}g</div>
                </div>
                <div class="food-calories">${item.calories} kcal</div>
            </div>`;
        });
        
        secHtml += `<button class="add-food-btn" onclick="openLogMealModal('${type}')">+ Add Food</button></div>`;
        container.innerHTML += secHtml;
    });
}

function openLogMealModal(mealType) {
    document.getElementById('modal-backdrop').classList.add('active');
    document.getElementById('modal-meal').classList.add('active');
    
    // Reset tabs
    document.querySelectorAll('#modal-meal .tab-btn').forEach(b => b.classList.remove('active'));
    // Find tab and activate
    const tabs = document.querySelectorAll('#modal-meal .tab-btn');
    for (let t of tabs) {
        if (t.innerText.toLowerCase() === mealType) {
            t.classList.add('active');
            break;
        }
    }
    document.getElementById('meal-type-input').value = mealType;
}
function setMealType(el, type) {
    document.querySelectorAll('#modal-meal .tab-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('meal-type-input').value = type;
}
function closeLogMealModal() {
    document.getElementById('modal-backdrop').classList.remove('active');
    document.getElementById('modal-meal').classList.remove('active');
}

function quickAddFood(name, cals, protein, carbs, fats) {
    document.getElementById('meal-name').value  = name;
    document.getElementById('meal-cals').value  = cals;
    document.getElementById('meal-p').value     = protein;
    document.getElementById('meal-c').value     = carbs;
    document.getElementById('meal-f').value     = fats;
}

function submitLogMeal() {
    const data = new FormData();
    data.append('action', 'log_meal');
    data.append('meal_type', document.getElementById('meal-type-input').value);
    data.append('food_name', document.getElementById('meal-name').value);
    data.append('calories', document.getElementById('meal-cals').value || 0);
    data.append('protein_g', document.getElementById('meal-p').value || 0);
    data.append('carbs_g', document.getElementById('meal-c').value || 0);
    data.append('fats_g', document.getElementById('meal-f').value || 0);
    
    fetch('handlers/gymbuddy_handler.php', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            // Reload nutrition
            fetch('handlers/gymbuddy_handler.php?action=get_nutrition&date=' + new Date().toISOString().split('T')[0])
            .then(r => r.json())
            .then(res2 => {
                GB_NUTRITION = res2.logs;
                loadNutritionTab();
                updateHomeTab();
                closeLogMealModal();
                // clear inputs
                document.getElementById('meal-name').value='';
                document.getElementById('meal-cals').value='';
                document.getElementById('meal-p').value='';
                document.getElementById('meal-c').value='';
                document.getElementById('meal-f').value='';
            });
        } else { alert(res.message); }
    });
}

function closeAllModals() {
    closeLogMealModal();
    closeLogWeightModal();
}

// === WORKOUTS TAB ===
function loadWorkoutsTab() {
    const strip = document.getElementById('week-strip');
    strip.innerHTML = '';
    const today = new Date().getDay(); // 0-6
    
    DAYS.forEach((d, i) => {
        const short = d.substring(0,3);
        const isToday = i === today;
        strip.innerHTML += `<div class="day-bubble ${isToday?'active':''}">
            <div class="day-circle">${short[0]}</div>
            <div class="day-name">${short}</div>
        </div>`;
    });
    
    const tw = WORKOUT_PLAN[today];
    document.getElementById('wk-day-name').innerText = tw.name;
    document.getElementById('wk-focus').innerText = tw.focus;
    document.getElementById('wk-excount').innerText = tw.exercises.length + ' exercises';
    
    const exCont = document.getElementById('wk-exercises');
    exCont.innerHTML = '';
    if (tw.isRest) {
        exCont.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-2)">Enjoy your rest day!</div>';
        document.querySelector('#screen-workouts .btn-primary').style.display = 'none';
    } else {
        document.querySelector('#screen-workouts .btn-primary').style.display = 'flex';
        tw.exercises.forEach(ex => {
            exCont.innerHTML += `<div class="exercise-item">
                <div class="ex-emoji">${ex.emoji}</div>
                <div class="ex-details">
                    <div class="ex-name">${ex.name}</div>
                    <div class="ex-meta"><span>${ex.sets} sets x ${ex.reps} reps</span><span>•</span><span>Rest ${ex.rest}s</span></div>
                </div>
            </div>`;
        });
    }
}
function getTodayWorkout() { return WORKOUT_PLAN[new Date().getDay()]; }

// === GYM MODE ===
function openGymMode() {
    gWorkout = getTodayWorkout();
    if(gWorkout.isRest) return;
    gymActive = true;
    gExIndex = 0;
    gSetIndex = 0;
    totalSetsDone = 0;
    workoutStartTime = Date.now();
    document.getElementById('gym-mode-overlay').classList.add('active');
    document.getElementById('workout-complete-panel').style.display = 'none';
    renderGymExercise();
}

function closeGymMode() {
    if(confirm('End workout early? Progress will be lost.')) {
        gymActive = false;
        document.getElementById('gym-mode-overlay').classList.remove('active');
        if(restTimerId) clearInterval(restTimerId);
        document.getElementById('rest-panel').classList.remove('active');
    }
}

function renderGymExercise() {
    const ex = gWorkout.exercises[gExIndex];
    document.getElementById('gym-wk-name').innerText = gWorkout.name;
    document.getElementById('gym-ex-counter').innerText = `Exercise ${gExIndex+1} of ${gWorkout.exercises.length}`;
    document.getElementById('gym-emoji').innerText = ex.emoji;
    document.getElementById('gym-ex-name').innerText = ex.name;
    document.getElementById('gym-ex-muscle').innerText = ex.muscle;
    document.getElementById('gym-set-label').innerText = `SET ${gSetIndex+1} OF ${ex.sets}`;
    document.getElementById('gym-reps').innerText = ex.reps;
    document.getElementById('gym-weight').innerText = '0'; // default empty bar or prev weight
    
    // Dots
    const dotsCont = document.getElementById('gym-set-dots');
    dotsCont.innerHTML = '';
    for(let i=0; i<ex.sets; i++) {
        dotsCont.innerHTML += `<div class="set-dot ${i<gSetIndex ? 'filled' : ''}"></div>`;
    }
    
    // Progress
    const totalEx = gWorkout.exercises.length;
    document.getElementById('gym-progress').style.width = ((gExIndex / totalEx) * 100) + '%';
    
    // Next
    const nextPreview = document.getElementById('gym-next-preview');
    if (gExIndex + 1 < gWorkout.exercises.length) {
        const nx = gWorkout.exercises[gExIndex+1];
        nextPreview.innerText = `NEXT UP · ${nx.name} · ${nx.sets} sets`;
        nextPreview.style.display = 'block';
    } else {
        nextPreview.style.display = 'none';
    }
}

function adjustReps(d) {
    let r = parseInt(document.getElementById('gym-reps').innerText) + d;
    if(r<1) r=1;
    document.getElementById('gym-reps').innerText = r;
}
function adjustWeight(d) {
    let w = parseFloat(document.getElementById('gym-weight').innerText) + d;
    if(w<0) w=0;
    document.getElementById('gym-weight').innerText = w;
}

function markSetDone() {
    totalSetsDone++;
    const ex = gWorkout.exercises[gExIndex];
    if (gSetIndex + 1 < ex.sets) {
        startRestTimer(ex.rest);
    } else {
        // Next Exercise
        if (gExIndex + 1 < gWorkout.exercises.length) {
            nextExercise();
        } else {
            showWorkoutComplete();
        }
    }
}

function startRestTimer(secs) {
    const rp = document.getElementById('rest-panel');
    rp.classList.add('active');
    let left = secs;
    const C = 282.7; // 2 * PI * 45
    const circle = document.getElementById('rest-svg-circle');
    
    document.getElementById('rest-text').innerText = formatTime(left);
    circle.style.strokeDashoffset = 0;
    
    if(restTimerId) clearInterval(restTimerId);
    restTimerId = setInterval(() => {
        left--;
        document.getElementById('rest-text').innerText = formatTime(left);
        const pct = left / secs;
        circle.style.strokeDashoffset = C - (C * pct);
        
        if (left <= 0) {
            skipRest();
        }
    }, 1000);
}

function skipRest() {
    clearInterval(restTimerId);
    document.getElementById('rest-panel').classList.remove('active');
    nextSet();
}

function nextSet() {
    gSetIndex++;
    renderGymExercise();
}

function nextExercise() {
    gExIndex++;
    gSetIndex=0;
    renderGymExercise();
}

function showWorkoutComplete() {
    document.getElementById('gym-progress').style.width = '100%';
    const duration = Math.round((Date.now() - workoutStartTime) / 60000);
    const cals = duration * 6; // rough est
    
    document.getElementById('wc-time').innerText = duration + ' min';
    document.getElementById('wc-sets').innerText = totalSetsDone;
    document.getElementById('wc-cals').innerText = cals + ' kcal';
    
    const wcp = document.getElementById('workout-complete-panel');
    wcp.style.display = 'flex';
}

function finishWorkout() {
    const duration = Math.round((Date.now() - workoutStartTime) / 60000);
    const cals = duration * 6;
    
    const data = new FormData();
    data.append('action', 'log_workout');
    data.append('workout_name', gWorkout.name);
    data.append('duration_minutes', duration);
    data.append('exercises_done', gWorkout.exercises.length);
    data.append('total_sets', totalSetsDone);
    data.append('calories_burned', cals);
    
    fetch('handlers/gymbuddy_handler.php', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
        gymActive = false;
        document.getElementById('gym-mode-overlay').classList.remove('active');
        alert('Workout saved! Great job.');
        location.reload(); // reload to update streak
    });
}

function formatTime(s) {
    const m = Math.floor(s/60);
    const sc = s%60;
    return m + ':' + (sc<10?'0':'') + sc;
}

// === AI COACH ===
let isVoiceListening = false;
let voiceInterval = null;

function toggleVoiceMode() {
    const bars = document.getElementById('audio-visualizer-bars');
    const statusText = document.getElementById('tap-talk-status');
    const mascot = document.getElementById('ai-mascot-img');
    
    isVoiceListening = !isVoiceListening;
    
    if (isVoiceListening) {
        if (bars) bars.classList.add('active');
        if (statusText) statusText.innerHTML = '<span style="color:#ff4d4d;font-weight:700">Listening… Speak now</span>';
        if (mascot) mascot.style.filter = 'drop-shadow(0 0 35px rgba(229,30,30,0.9))';
        
        // Simulate speech recognition & auto-response after 2.5 seconds
        clearTimeout(voiceInterval);
        voiceInterval = setTimeout(() => {
            if (bars) bars.classList.remove('active');
            if (statusText) statusText.innerHTML = '<span style="color:#22d47e;font-weight:700">Coach: "Ready for today\'s push workout!"</span>';
            if (mascot) mascot.style.filter = 'drop-shadow(0 0 25px rgba(204,26,26,0.5))';
            isVoiceListening = false;
            
            // Open interactive drawer after speaking
            setTimeout(() => {
                openChatDrawer();
            }, 600);
        }, 2500);
    } else {
        clearTimeout(voiceInterval);
        if (bars) bars.classList.remove('active');
        if (statusText) statusText.innerText = 'Tap to Talk';
        if (mascot) mascot.style.filter = 'drop-shadow(0 0 25px rgba(204,26,26,0.5))';
    }
}

function openChatDrawer() {
    const drawer = document.getElementById('ai-chat-drawer');
    if (drawer) drawer.classList.add('active');
}

function closeChatDrawer() {
    const drawer = document.getElementById('ai-chat-drawer');
    if (drawer) drawer.classList.remove('active');
}

function showCoachNotif() {
    alert("🔔 Coach AI Notification:\nYou're currently on a 3-day workout streak! Don't forget your Push Day workout scheduled for today.");
}

function sendQuickPrompt(type) {
    const msgs = { plan:"Today's plan", diet:"Meal ideas", tired:"I'm tired", progress:"My progress", modify:"Modify workout", sore:"I'm sore" };
    addChatMessage(msgs[type] || "Hello Coach", true);
    showTyping();
    setTimeout(() => { hideTyping(); addChatMessage(AI_RESPONSES[type] || AI_RESPONSES.default, false); }, 1000);
}

function sendMessage() {
    const input = document.getElementById('ai-input');
    const text = input.value.trim();
    if(!text) return;
    
    addChatMessage(text, true);
    input.value = '';
    showTyping();
    setTimeout(() => { hideTyping(); addChatMessage(AI_RESPONSES.default, false); }, 1000);
}

function addChatMessage(text, isUser) {
    const chat = document.getElementById('ai-chat');
    if (!chat) return;
    let formatted = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    chat.innerHTML += `<div class="chat-bubble ${isUser ? 'chat-user' : 'chat-ai'}">${formatted}</div>`;
    chat.scrollTop = chat.scrollHeight;
}

function showTyping() {
    const chat = document.getElementById('ai-chat');
    if (!chat) return;
    chat.innerHTML += `<div class="chat-bubble chat-ai typing-ind" style="opacity:0.6;font-size:1.5rem;line-height:0.5">...</div>`;
    chat.scrollTop = chat.scrollHeight;
}

function hideTyping() {
    const ind = document.querySelector('.typing-ind');
    if(ind) ind.remove();
}

// === PROGRESS TAB ===
function initWeightChart() {
    if(!document.getElementById('weightChart')) return;
    const ctx = document.getElementById('weightChart').getContext('2d');
    
    // Format data
    let labels = [];
    let data = [];
    if (GB_WEIGHT_HISTORY.length === 0) {
        labels = [new Date().toLocaleDateString('en-US', {month:'short', day:'numeric'})];
        data = [GB_PROFILE.weight_kg || 70];
    } else {
        GB_WEIGHT_HISTORY.forEach(w => {
            labels.push(new Date(w.log_date).toLocaleDateString('en-US', {month:'short', day:'numeric'}));
            data.push(w.weight_kg);
        });
    }
    
    if (weightChart) weightChart.destroy();
    
    weightChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Weight (kg)',
                data: data,
                borderColor: '#cc1a1a',
                backgroundColor: 'rgba(204,26,26,0.12)',
                borderWidth: 3,
                pointBackgroundColor: '#0a0a0c',
                pointBorderColor: '#cc1a1a',
                pointBorderWidth: 2,
                pointRadius: 4,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#888888', font: { family: 'Inter' } }, border: { display: false } },
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888888', font: { family: 'Inter' } }, border: { display: false } }
            }
        }
    });
}

function openLogWeightModal() {
    document.getElementById('modal-backdrop').classList.add('active');
    document.getElementById('modal-weight').classList.add('active');
}
function closeLogWeightModal() {
    document.getElementById('modal-backdrop').classList.remove('active');
    document.getElementById('modal-weight').classList.remove('active');
}

function submitLogWeight() {
    const w = document.getElementById('weight-input').value;
    const data = new FormData();
    data.append('action', 'log_weight');
    data.append('weight_kg', w);
    
    fetch('handlers/gymbuddy_handler.php', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            location.reload(); // Reload to refresh chart and profile
        } else { alert(res.message); }
    });
}

// === ONBOARDING ===
let onbStep = 0;
function initOnboarding() {
    document.getElementById('onboarding').classList.add('active');
}

function onboardingNextStep() {
    if (onbStep === 4) {
        saveProfile();
        return;
    }
    document.getElementById('onb-' + onbStep).classList.remove('active');
    onbStep++;
    document.getElementById('onb-' + onbStep).classList.add('active');
}

function onboardingPrevStep() {
    if(onbStep===0) return;
    document.getElementById('onb-' + onbStep).classList.remove('active');
    onbStep--;
    document.getElementById('onb-' + onbStep).classList.add('active');
}

function selCard(el, hiddenId, val) {
    const parent = el.parentNode;
    parent.querySelectorAll('.onb-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('onb-' + hiddenId).value = val;
}

function selChip(el, hiddenId, val) {
    const parent = el.parentNode;
    parent.querySelectorAll('.onb-chip').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('onb-' + hiddenId).value = val;
}

function saveProfile() {
    // Show loading step
    document.getElementById('onb-' + onbStep).classList.remove('active');
    onbStep = 5;
    document.getElementById('onb-' + onbStep).classList.add('active');
    
    const data = new FormData();
    data.append('action', 'save_profile');
    data.append('height_cm', document.getElementById('onb-height').value);
    data.append('weight_kg', document.getElementById('onb-weight').value);
    data.append('age', document.getElementById('onb-age').value);
    data.append('body_goal', document.getElementById('onb-body_goal').value);
    data.append('fitness_level', document.getElementById('onb-fitness_level').value);
    data.append('diet_preference', document.getElementById('onb-diet_preference').value);
    
    fetch('handlers/gymbuddy_handler.php', { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
        if(res.success) {
            setTimeout(() => {
                location.reload(); // Reload to populate everything
            }, 1500);
        } else {
            alert(res.message);
            onboardingPrevStep();
        }
    });
}
</script>
</body>
</html>
