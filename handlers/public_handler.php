<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    $data = $_POST;
}

$sessionToken = $_SESSION['public_csrf_token'] ?? '';
$submittedToken = (string) ($data['csrf_token'] ?? '');
if (!$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(419);
    respond(false, 'Your session expired. Please refresh and try again.');
}

match ((string) ($data['action'] ?? '')) {
    'submit_feedback' => submitFeedback($data),
    'submit_contact' => submitContact($data),
    default => respond(false, 'Invalid action.'),
};

function submitFeedback(array $data): void
{
    $branchId = (int) ($data['branch_id'] ?? 0);
    $rating = (int) ($data['rating'] ?? 0);
    $body = trim((string) ($data['body'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        respond(false, 'Please select a rating between 1 and 5 stars.');
    }
    if ($body === '') {
        respond(false, 'Please write your feedback before submitting.');
    }
    if (strlen($body) > 2000) {
        respond(false, 'Feedback is too long. Please keep it under 2000 characters.');
    }

    $pdo = db();
    $branch = $pdo->prepare('SELECT id FROM branches WHERE id = ? AND is_active = 1 LIMIT 1');
    $branch->execute([$branchId]);
    if (!$branch->fetch()) {
        respond(false, 'Please select a valid branch.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO feedback (user_id, branch_id, rating, body, is_visible, created_at)
         VALUES (NULL, ?, ?, ?, 1, NOW())'
    );
    $stmt->execute([$branchId, $rating, $body]);

    respond(true, 'Thank you. Your anonymous feedback was submitted.');
}

function submitContact(array $data): void
{
    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $subject = trim((string) ($data['subject'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));

    if ($name === '' || $email === '' || $subject === '' || $message === '') {
        respond(false, 'Please complete the required contact fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'Please enter a valid email address.');
    }
    if (strlen($name) > 120 || strlen($email) > 191 || strlen($phone) > 40 || strlen($subject) > 160) {
        respond(false, 'One of the fields is too long.');
    }
    if (strlen($message) > 3000) {
        respond(false, 'Message is too long. Please keep it under 3000 characters.');
    }

    $stmt = db()->prepare(
        'INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at)
         VALUES (?, ?, ?, ?, ?, "new", NOW())'
    );
    $stmt->execute([$name, $email, $phone !== '' ? $phone : null, $subject, $message]);

    respond(true, 'Thanks. Your message was sent to the FitSync team.');
}

function respond(bool $success, string $message, array $extra = []): never
{
    echo json_encode(['success' => $success, 'message' => $message] + $extra);
    exit;
}
