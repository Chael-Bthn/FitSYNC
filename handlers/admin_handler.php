<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/qr_helpers.php';
require_once __DIR__ . '/../includes/membership_helpers.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    respond(false, 'Not authorized.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;
$submittedToken = $data['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
if (!$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    respond(false, 'Invalid request. Please refresh the page.');
}

$action = trim((string) ($data['action'] ?? ''));
$adminId = (int) $_SESSION['user_id'];

match ($action) {
    'create_member' => createMember($data),
    'change_member_plan' => changeMemberPlan($data),
    'delete_feedback' => deleteFeedback($data),
    'approve_payment' => approvePayment($data, $adminId),
    'reject_payment' => rejectPayment($data, $adminId),
    'approve_account' => approveAccount($data),
    'reject_account' => rejectAccount($data),
    'delete_account' => deleteAccount($data),
    'set_membership_status' => setMembershipStatus($data),
    'extend_membership' => extendMembership($data),
    'change_member_branch' => changeMemberBranch($data),
    'add_member_note' => addMemberNote($data, $adminId),
    'save_class' => saveClass($data),
    'set_class_active' => setClassActive($data),
    'delete_class' => deleteClass($data),
    'save_class_schedule' => saveClassSchedule($data),
    'set_class_schedule_status' => setClassScheduleStatus($data),
    'delete_class_schedule' => deleteClassSchedule($data),
    'save_announcement' => saveAnnouncement($data),
    'set_announcement_active' => setAnnouncementActive($data),
    'delete_announcement' => deleteAnnouncement($data),
    'save_operating_hour' => saveOperatingHour($data),
    default => respond(false, 'Unknown action.'),
};

function createMember(array $data): void
{
    $firstName = trim((string) ($data['first_name'] ?? ''));
    $lastName = trim((string) ($data['last_name'] ?? ''));
    $gender = trim((string) ($data['gender'] ?? ''));
    $birthdate = trim((string) ($data['birthdate'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $password = (string) ($data['password'] ?? '');
    $confirmPassword = (string) ($data['confirm_password'] ?? '');
    $planId = (int) ($data['plan_id'] ?? 0);
    $paymentMethod = trim((string) ($data['payment_method'] ?? 'cash'));

    if ($firstName === '' || $lastName === '' || strlen($firstName) > 64 || strlen($lastName) > 64) {
        respond(false, 'Enter a valid first and last name.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
        respond(false, 'Enter a valid email address.');
    }
    if (strlen($password) < 8 || $password !== $confirmPassword) {
        respond(false, 'Password must be at least 8 characters and match confirmation.');
    }
    if (!in_array($gender, ['male', 'female', 'nonbinary', 'other'], true)) {
        respond(false, 'Please select a valid gender.');
    }

    $birthdateValue = normalizeBirthdate($birthdate);
    $allowedPayments = ['credit_card', 'debit_card', 'gcash', 'maya', 'bank_transfer', 'cash'];
    if (!in_array($paymentMethod, $allowedPayments, true)) {
        respond(false, 'Invalid payment method selected.');
    }

    $pdo = db();
    $plan = findActivePlan($pdo, $planId);
    if (!$plan) {
        respond(false, 'Invalid plan selected.');
    }

    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    if ($existing->fetch()) {
        respond(false, 'A user with this email already exists.');
    }

    try {
        $pdo->beginTransaction();

        $userStmt = $pdo->prepare(
            'INSERT INTO users
                (role, first_name, last_name, email, password_hash, birthdate, gender, email_verified_at, is_active, created_at, updated_at)
             VALUES ("member", ?, ?, ?, ?, ?, ?, NOW(), 1, NOW(), NOW())'
        );
        $userStmt->execute([
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            $birthdateValue,
            $gender,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $start = new DateTimeImmutable('today');
        $end = $start->modify('+' . (int) $plan['duration_days'] . ' days');
        $membershipStmt = $pdo->prepare(
            'INSERT INTO memberships
                (user_id, plan_id, branch_id, starts_at, ends_at, amount_paid, payment_method, payment_status, status, payment_ref)
             VALUES (?, ?, 1, ?, ?, ?, ?, "paid", "active", ?)'
        );
        $membershipStmt->execute([
            $userId,
            (int) $plan['id'],
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            (float) $plan['price'],
            $paymentMethod,
            'ADM-' . strtoupper(bin2hex(random_bytes(4))),
        ]);

        $pdo->commit();

        // Generate QR Code
        generateUserQrCode($userId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[FitSync Admin] Create member failed: ' . $e->getMessage());
        http_response_code(500);
        respond(false, 'Unable to create member right now.');
    }

    respond(true, 'Member added successfully.', ['reload' => true]);
}

function changeMemberPlan(array $data): void
{
    $memberId = (int) ($data['member_id'] ?? 0);
    $membershipId = (int) ($data['membership_id'] ?? 0);
    $planId = (int) ($data['plan_id'] ?? 0);
    if ($memberId <= 0 || $planId <= 0) {
        respond(false, 'Invalid member or plan.');
    }

    $pdo = db();
    $plan = findActivePlan($pdo, $planId);
    if (!$plan) {
        respond(false, 'Invalid plan selected.');
    }

    $member = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = "member" LIMIT 1');
    $member->execute([$memberId]);
    if (!$member->fetch()) {
        respond(false, 'Member not found.');
    }

    if ($membershipId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE memberships
             SET plan_id = ?,
                 ends_at = DATE_ADD(starts_at, INTERVAL ? DAY),
                 amount_paid = ?,
                 updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            (int) $plan['id'],
            (int) $plan['duration_days'],
            (float) $plan['price'],
            $membershipId,
            $memberId,
        ]);

        respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Member plan updated.' : 'No membership was updated.', ['reload' => true]);
    }

    $start = new DateTimeImmutable('today');
    $end = $start->modify('+' . (int) $plan['duration_days'] . ' days');
    $stmt = $pdo->prepare(
        'INSERT INTO memberships
            (user_id, plan_id, branch_id, starts_at, ends_at, amount_paid, payment_method, payment_status, status, payment_ref)
         VALUES (?, ?, 1, ?, ?, ?, "cash", "paid", "active", ?)'
    );
    $stmt->execute([
        $memberId,
        (int) $plan['id'],
        $start->format('Y-m-d'),
        $end->format('Y-m-d'),
        (float) $plan['price'],
        'ADM-' . strtoupper(bin2hex(random_bytes(4))),
    ]);

    respond(true, 'Member plan added.', ['reload' => true]);
}

function deleteFeedback(array $data): void
{
    $feedbackId = (int) ($data['feedback_id'] ?? 0);
    if ($feedbackId <= 0) {
        respond(false, 'Invalid feedback.');
    }

    $stmt = db()->prepare('UPDATE feedback SET is_visible = 0 WHERE id = ? AND is_visible = 1');
    $stmt->execute([$feedbackId]);

    respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Feedback deleted.' : 'Feedback was already deleted.', ['reload' => true]);
}

function approvePayment(array $data, int $adminId): void
{
    $membershipId = (int) ($data['membership_id'] ?? 0);
    if ($membershipId <= 0) {
        respond(false, 'Invalid membership.');
    }

    $pdo = db();
    expireOldMemberships($pdo);

    $stmt = $pdo->prepare(
        'UPDATE memberships
         SET payment_status = "paid",
             status = "active",
             payment_ref = COALESCE(payment_ref, ?),
             updated_at = NOW()
         WHERE id = ? AND payment_status = "pending"'
    );
    $stmt->execute(['APR-' . $adminId . '-' . date('YmdHis'), $membershipId]);

    // If membership was approved, also activate the user account (for new registrations)
    if ($stmt->rowCount() > 0) {
        try {
            $activate = $pdo->prepare(
                'UPDATE users u
                 INNER JOIN memberships m ON m.user_id = u.id
                 SET u.is_active = 1, u.updated_at = NOW()
                 WHERE m.id = ?'
            );
            $activate->execute([$membershipId]);
        } catch (Throwable) {
            // non-fatal
        }
    }

    respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Payment approved.' : 'No pending payment found.', [
        'reload' => true,
    ]);
}

function rejectPayment(array $data, int $adminId): void
{
    $membershipId = (int) ($data['membership_id'] ?? 0);
    if ($membershipId <= 0) {
        respond(false, 'Invalid membership.');
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE memberships
         SET payment_status = "failed",
             status = "cancelled",
             payment_ref = COALESCE(payment_ref, ?),
             updated_at = NOW()
         WHERE id = ? AND payment_status = "pending"'
    );
    $stmt->execute(['REJ-' . $adminId . '-' . date('YmdHis'), $membershipId]);

    respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Payment rejected.' : 'No pending payment found.', [
        'reload' => true,
    ]);
}

function approveAccount(array $data): void
{
    $memberId = (int) ($data['member_id'] ?? 0);
    if ($memberId <= 0) {
        respond(false, 'Invalid member.');
    }

    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ? AND role = "member"');
    $stmt->execute([$memberId]);

    respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Account approved.' : 'Member account was already active or not found.', [
        'reload' => true,
    ]);
}

function rejectAccount(array $data): void
{
    $memberId = (int) ($data['member_id'] ?? 0);
    if ($memberId <= 0) {
        respond(false, 'Invalid member.');
    }

    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ? AND role = "member"');
    $stmt->execute([$memberId]);
    $pending = $pdo->prepare(
        'UPDATE memberships
         SET status = "cancelled",
             payment_status = "failed",
             updated_at = NOW()
         WHERE user_id = ? AND payment_status = "pending" AND status = "pending"'
    );
    $pending->execute([$memberId]);

    respond($stmt->rowCount() > 0 || $pending->rowCount() > 0, 'Account rejected and pending memberships cancelled.', [
        'reload' => true,
    ]);
}

function deleteAccount(array $data): void
{
    $memberId = (int) ($data['member_id'] ?? 0);
    if ($memberId <= 0) {
        respond(false, 'Invalid member.');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM memberships WHERE user_id = ?');
        $stmt->execute([$memberId]);

        $noteStmt = $pdo->prepare('DELETE FROM member_notes WHERE member_id = ?');
        $noteStmt->execute([$memberId]);

        $userStmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "member"');
        $userStmt->execute([$memberId]);

        $deleted = $userStmt->rowCount() > 0;
        $pdo->commit();

        respond($deleted, $deleted ? 'Account deleted successfully.' : 'Member account not found.', [
            'reload' => true,
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        respond(false, 'Unable to delete the account. Please try again.');
    }
}

function setMembershipStatus(array $data): void
{
    $membershipId = (int) ($data['membership_id'] ?? 0);
    $status = trim((string) ($data['status'] ?? ''));
    $allowed = ['active', 'expired', 'cancelled', 'frozen'];
    if ($membershipId <= 0 || !in_array($status, $allowed, true)) {
        respond(false, 'Invalid membership status.');
    }

    $pdo = db();
    if ($status === 'active') {
        $stmt = $pdo->prepare(
            'UPDATE memberships m
             INNER JOIN membership_plans p ON p.id = m.plan_id
             SET m.status = "active",
                 m.starts_at = IF(m.ends_at < CURDATE(), CURDATE(), m.starts_at),
                 m.ends_at = IF(m.ends_at < CURDATE(), DATE_ADD(CURDATE(), INTERVAL p.duration_days DAY), m.ends_at),
                 m.updated_at = NOW()
             WHERE m.id = ? AND m.payment_status = "paid"'
        );
        $stmt->execute([$membershipId]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE memberships
             SET status = ?, updated_at = NOW()
             WHERE id = ? AND payment_status = "paid"'
        );
        $stmt->execute([$status, $membershipId]);
    }

    respond($stmt->rowCount() > 0, 'Membership updated.', ['reload' => true]);
}

function extendMembership(array $data): void
{
    $membershipId = (int) ($data['membership_id'] ?? 0);
    $days = (int) ($data['days'] ?? 0);
    if ($membershipId <= 0 || $days < 1 || $days > 730) {
        respond(false, 'Enter a valid extension between 1 and 730 days.');
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'UPDATE memberships
         SET ends_at = DATE_ADD(GREATEST(ends_at, CURDATE()), INTERVAL ? DAY),
             status = IF(payment_status = "paid", "active", status),
             updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$days, $membershipId]);

    respond($stmt->rowCount() > 0, 'Membership extended.', ['reload' => true]);
}

function changeMemberBranch(array $data): void
{
    $memberId = (int) ($data['member_id'] ?? 0);
    $branchId = (int) ($data['branch_id'] ?? 0);
    if ($memberId <= 0 || $branchId <= 0) {
        respond(false, 'Invalid member or branch.');
    }

    $pdo = db();
    $branch = $pdo->prepare('SELECT id FROM branches WHERE id = ? AND is_active = 1 LIMIT 1');
    $branch->execute([$branchId]);
    if (!$branch->fetch()) {
        respond(false, 'Branch is not available.');
    }

    $stmt = $pdo->prepare(
        'UPDATE memberships
         SET branch_id = ?, updated_at = NOW()
         WHERE user_id = ?
         ORDER BY ends_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([$branchId, $memberId]);

    respond($stmt->rowCount() > 0, 'Branch assignment updated.', ['reload' => true]);
}

function addMemberNote(array $data, int $adminId): void
{
    $memberId = (int) ($data['member_id'] ?? 0);
    $body = trim((string) ($data['note_body'] ?? ''));
    if ($memberId <= 0 || $body === '') {
        respond(false, 'Note body is required.');
    }
    if (strlen($body) > 3000) {
        respond(false, 'Note is too long.');
    }

    $pdo = db();
    $member = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = "member" LIMIT 1');
    $member->execute([$memberId]);
    if (!$member->fetch()) {
        respond(false, 'Member not found.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO member_notes (member_id, admin_id, note_body, created_at)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$memberId, $adminId, $body]);

    respond(true, 'Note added.', ['reload' => true]);
}

function saveClass(array $data): void
{
    $id = (int) ($data['class_id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $trainer = trim((string) ($data['trainer_name'] ?? ''));
    $branchId = (int) ($data['branch_id'] ?? 0);
    $duration = (int) ($data['duration_minutes'] ?? 60);
    $capacity = (int) ($data['capacity'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($title === '' || strlen($title) > 120 || $branchId <= 0 || $duration < 15 || $duration > 360) {
        respond(false, 'Enter a valid class title, branch, and duration.');
    }
    if ($description !== '' && strlen($description) > 500) {
        respond(false, 'Class description is too long.');
    }
    if ($trainer !== '' && strlen($trainer) > 120) {
        respond(false, 'Trainer name is too long.');
    }
    if ($capacity < 0 || $capacity > 500) {
        respond(false, 'Capacity must be between 0 and 500.');
    }

    $pdo = db();
    ensureBranch($pdo, $branchId);

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE classes
             SET title = ?, description = ?, trainer_name = ?, branch_id = ?, duration_minutes = ?, capacity = ?, is_active = ?
             WHERE id = ?'
        );
        $stmt->execute([$title, $description ?: null, $trainer ?: null, $branchId, $duration, $capacity ?: null, $isActive, $id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO classes (title, description, trainer_name, branch_id, duration_minutes, capacity, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$title, $description ?: null, $trainer ?: null, $branchId, $duration, $capacity ?: null, $isActive]);
    }

    respond(true, 'Class saved.', ['reload' => true]);
}

function setClassActive(array $data): void
{
    $id = (int) ($data['class_id'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;
    if ($id <= 0) {
        respond(false, 'Invalid class.');
    }

    $stmt = db()->prepare('UPDATE classes SET is_active = ? WHERE id = ?');
    $stmt->execute([$isActive, $id]);

    respond($stmt->rowCount() > 0, 'Class updated.', ['reload' => true]);
}

function deleteClass(array $data): void
{
    $id = (int) ($data['class_id'] ?? 0);
    if ($id <= 0) {
        respond(false, 'Invalid class.');
    }

    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM classes WHERE id = ?');
    $stmt->execute([$id]);

    respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Class deleted.' : 'Class not found.', ['reload' => true]);
}

function saveClassSchedule(array $data): void
{
    $id = (int) ($data['schedule_id'] ?? 0);
    $classId = (int) ($data['class_id'] ?? 0);
    $branchId = (int) ($data['branch_id'] ?? 0);
    $date = trim((string) ($data['scheduled_date'] ?? ''));
    $start = trim((string) ($data['start_time'] ?? ''));
    $end = trim((string) ($data['end_time'] ?? ''));
    $status = trim((string) ($data['status'] ?? 'scheduled'));
    $allowed = ['scheduled', 'cancelled', 'completed'];

    if ($classId <= 0 || $branchId <= 0 || !validDate($date) || !validTime($start) || !validTime($end) || !in_array($status, $allowed, true)) {
        respond(false, 'Enter a valid schedule.');
    }
    if ($end <= $start) {
        respond(false, 'End time must be after start time.');
    }

    $pdo = db();
    ensureBranch($pdo, $branchId);
    ensureClass($pdo, $classId);

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE class_schedules
             SET class_id = ?, branch_id = ?, scheduled_date = ?, start_time = ?, end_time = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute([$classId, $branchId, $date, $start, $end, $status, $id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO class_schedules (class_id, branch_id, scheduled_date, start_time, end_time, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$classId, $branchId, $date, $start, $end, $status]);
    }

    respond(true, 'Class schedule saved.', ['reload' => true]);
}

function setClassScheduleStatus(array $data): void
{
    $id = (int) ($data['schedule_id'] ?? 0);
    $status = trim((string) ($data['status'] ?? ''));
    if ($id <= 0 || !in_array($status, ['scheduled', 'cancelled', 'completed'], true)) {
        respond(false, 'Invalid schedule status.');
    }

    $stmt = db()->prepare('UPDATE class_schedules SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    respond($stmt->rowCount() > 0, 'Schedule updated.', ['reload' => true]);
}

function deleteClassSchedule(array $data): void
{
    $id = (int) ($data['schedule_id'] ?? 0);
    if ($id <= 0) {
        respond(false, 'Invalid class schedule.');
    }

    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM class_schedules WHERE id = ?');
    $stmt->execute([$id]);

    respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Class schedule deleted.' : 'Class schedule not found.', ['reload' => true]);
}

function saveAnnouncement(array $data): void
{
    $id = (int) ($data['announcement_id'] ?? 0);
    $branchId = (int) ($data['branch_id'] ?? 0);
    $title = trim((string) ($data['title'] ?? ''));
    $body = trim((string) ($data['body'] ?? ''));
    $startsAt = trim((string) ($data['starts_at'] ?? ''));
    $endsAt = trim((string) ($data['ends_at'] ?? ''));
    $isActive = !empty($data['is_active']) ? 1 : 0;

    $normalizedStartsAt = normalizeDateTime($startsAt);
    $normalizedEndsAt = $endsAt !== '' ? normalizeDateTime($endsAt) : null;

    if ($branchId <= 0 || $title === '' || strlen($title) > 140 || $body === '' || strlen($body) > 4000 || $normalizedStartsAt === null) {
        respond(false, 'Enter a valid announcement.');
    }
    if ($endsAt !== '' && ($normalizedEndsAt === null || $normalizedEndsAt < $normalizedStartsAt)) {
        respond(false, 'End date must be after the start date.');
    }

    $pdo = db();
    ensureBranch($pdo, $branchId);

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE branch_announcements
             SET branch_id = ?, title = ?, body = ?, starts_at = ?, ends_at = ?, is_active = ?
             WHERE id = ?'
        );
        $stmt->execute([$branchId, $title, $body, $normalizedStartsAt, $normalizedEndsAt, $isActive, $id]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO branch_announcements (branch_id, title, body, starts_at, ends_at, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$branchId, $title, $body, $normalizedStartsAt, $normalizedEndsAt, $isActive]);
    }

    respond(true, 'Announcement saved.', ['reload' => true]);
}

function setAnnouncementActive(array $data): void
{
    $id = (int) ($data['announcement_id'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;
    if ($id <= 0) {
        respond(false, 'Invalid announcement.');
    }

    $stmt = db()->prepare('UPDATE branch_announcements SET is_active = ? WHERE id = ?');
    $stmt->execute([$isActive, $id]);

    respond($stmt->rowCount() > 0, 'Announcement updated.', ['reload' => true]);
}

function deleteAnnouncement(array $data): void
{
    $id = (int) ($data['announcement_id'] ?? 0);
    if ($id <= 0) {
        respond(false, 'Invalid announcement.');
    }

    $stmt = db()->prepare('DELETE FROM branch_announcements WHERE id = ?');
    $stmt->execute([$id]);

    respond($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? 'Announcement deleted.' : 'Announcement not found.', ['reload' => true]);
}

function saveOperatingHour(array $data): void
{
    $branchId = (int) ($data['branch_id'] ?? 0);
    $day = (int) ($data['day_of_week'] ?? 0);
    $open = trim((string) ($data['open_time'] ?? ''));
    $close = trim((string) ($data['close_time'] ?? ''));
    $isClosed = !empty($data['is_closed']) ? 1 : 0;

    if ($branchId <= 0 || $day < 1 || $day > 7) {
        respond(false, 'Invalid branch hours.');
    }
    if (!$isClosed && (!validTime($open) || !validTime($close) || $close <= $open)) {
        respond(false, 'Enter valid opening and closing times.');
    }

    $pdo = db();
    ensureBranch($pdo, $branchId);

    $stmt = $pdo->prepare(
        'INSERT INTO branch_operating_hours (branch_id, day_of_week, open_time, close_time, is_closed)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE open_time = VALUES(open_time), close_time = VALUES(close_time), is_closed = VALUES(is_closed)'
    );
    $stmt->execute([$branchId, $day, $isClosed ? null : $open, $isClosed ? null : $close, $isClosed]);

    respond(true, 'Operating hours saved.', ['reload' => true]);
}

function ensureBranch(PDO $pdo, int $branchId): void
{
    $stmt = $pdo->prepare('SELECT id FROM branches WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$branchId]);
    if (!$stmt->fetch()) {
        respond(false, 'Branch is not available.');
    }
}

function ensureClass(PDO $pdo, int $classId): void
{
    $stmt = $pdo->prepare('SELECT id FROM classes WHERE id = ? LIMIT 1');
    $stmt->execute([$classId]);
    if (!$stmt->fetch()) {
        respond(false, 'Class not found.');
    }
}

function findActivePlan(PDO $pdo, int $planId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, duration_days, price
         FROM membership_plans
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    return $plan ?: null;
}

function normalizeBirthdate(string $birthdate): string
{
    $bd = DateTime::createFromFormat('Y-m-d', $birthdate);
    if (!$bd || $bd->format('Y-m-d') !== $birthdate || $bd >= new DateTime()) {
        respond(false, 'Please enter a valid birthdate.');
    }
    if ((new DateTime())->diff($bd)->y < 16) {
        respond(false, 'Member must be at least 16 years old.');
    }

    return $bd->format('Y-m-d');
}

function validDate(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value;
}

function validTime(string $value): bool
{
    $dt = DateTime::createFromFormat('H:i', $value);
    return $dt && $dt->format('H:i') === $value;
}

function validDateTime(string $value): bool
{
    return normalizeDateTime($value) !== null;
}

function normalizeDateTime(string $value): ?string
{
    $value = str_replace('T', ' ', trim($value));
    $dt = DateTime::createFromFormat('Y-m-d H:i', $value);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

function respond(bool $success, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
