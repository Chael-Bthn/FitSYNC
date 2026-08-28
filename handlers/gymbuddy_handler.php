<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth_guard.php';
require_once __DIR__ . '/../config/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json');

function jOk(mixed $d=[]): never { echo json_encode(['success'=>true,...(is_array($d)?$d:['data'=>$d])]); exit; }
function jErr(string $m, int $c=400): never { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m]); exit; }
function uid(): int { return (int)($_SESSION['user_id']??0); }
function auth(): void { if (!uid()) jErr('Unauthorized',401); }

$pdo    = db();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

match($action) {
    'save_profile'  => saveProfile($pdo),
    'log_meal'      => logMeal($pdo),
    'delete_meal'   => deleteMeal($pdo),
    'log_workout'   => logWorkout($pdo),
    'log_weight'    => logWeight($pdo),
    'get_nutrition' => getNutrition($pdo),
    default         => jErr('Unknown action')
};

function saveProfile(PDO $pdo): never {
    auth();
    $d = $_POST;
    $pdo->prepare('INSERT INTO gymbuddy_profiles (user_id,height_cm,weight_kg,goal_weight_kg,age,body_goal,fitness_level,diet_preference,daily_calories,daily_protein,daily_carbs,daily_fats) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE height_cm=VALUES(height_cm),weight_kg=VALUES(weight_kg),goal_weight_kg=VALUES(goal_weight_kg),age=VALUES(age),body_goal=VALUES(body_goal),fitness_level=VALUES(fitness_level),diet_preference=VALUES(diet_preference),daily_calories=VALUES(daily_calories),daily_protein=VALUES(daily_protein),daily_carbs=VALUES(daily_carbs),daily_fats=VALUES(daily_fats),updated_at=NOW()')
        ->execute([uid(),$d['height_cm']??170,$d['weight_kg']??70,$d['goal_weight_kg']??65,$d['age']??25,$d['body_goal']??'build_muscle',$d['fitness_level']??'beginner',$d['diet_preference']??'no_restriction',$d['daily_calories']??2000,$d['daily_protein']??150,$d['daily_carbs']??200,$d['daily_fats']??65]);
    // Also log initial weight
    if (!empty($d['weight_kg'])) {
        $pdo->prepare('INSERT IGNORE INTO weight_logs (user_id,log_date,weight_kg) VALUES (?,CURDATE(),?)')->execute([uid(),$d['weight_kg']]);
    }
    jOk(['message'=>'Profile saved']);
}

function logMeal(PDO $pdo): never {
    auth();
    $d = $_POST;
    if (empty($d['food_name'])) jErr('Food name required');
    $pdo->prepare('INSERT INTO nutrition_logs (user_id,log_date,meal_type,food_name,calories,protein_g,carbs_g,fats_g) VALUES (?,?,?,?,?,?,?,?)') 
        ->execute([uid(),date('Y-m-d'),$d['meal_type']??'snack',$d['food_name'],(int)($d['calories']??0),(float)($d['protein_g']??0),(float)($d['carbs_g']??0),(float)($d['fats_g']??0)]);
    jOk(['id'=>(int)$pdo->lastInsertId()]);
}

function deleteMeal(PDO $pdo): never {
    auth();
    $id = (int)($_POST['id']??0);
    $pdo->prepare('DELETE FROM nutrition_logs WHERE id=? AND user_id=?')->execute([$id,uid()]);
    jOk();
}

function logWorkout(PDO $pdo): never {
    auth();
    $d = $_POST;
    $pdo->prepare('INSERT INTO workout_logs (user_id,log_date,workout_name,duration_minutes,exercises_done,total_sets,calories_burned) VALUES (?,CURDATE(),?,?,?,?,?)')
        ->execute([uid(),$d['workout_name']??'Workout',(int)($d['duration_minutes']??0),(int)($d['exercises_done']??0),(int)($d['total_sets']??0),(int)($d['calories_burned']??0)]);
    jOk();
}

function logWeight(PDO $pdo): never {
    auth();
    $w = (float)($_POST['weight_kg']??0);
    if ($w <= 0) jErr('Invalid weight');
    $pdo->prepare('INSERT INTO weight_logs (user_id,log_date,weight_kg,note) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE weight_kg=VALUES(weight_kg)')
        ->execute([uid(),date('Y-m-d'),$w,$_POST['note']??'']);
    // Update profile weight
    $pdo->prepare('UPDATE gymbuddy_profiles SET weight_kg=? WHERE user_id=?')->execute([$w,uid()]);
    jOk();
}

function getNutrition(PDO $pdo): never {
    auth();
    $date = $_GET['date'] ?? date('Y-m-d');
    $rows = $pdo->prepare('SELECT * FROM nutrition_logs WHERE user_id=? AND log_date=? ORDER BY created_at ASC');
    $rows->execute([uid(),$date]);
    jOk(['logs'=>$rows->fetchAll(PDO::FETCH_ASSOC)]);
}
