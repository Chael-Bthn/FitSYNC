<?php
declare(strict_types=1);

function expireOldMemberships(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        'UPDATE memberships
         SET status = "expired", updated_at = NOW()
         WHERE status = "active" AND ends_at < CURDATE()'
    );
    $stmt->execute();

    return $stmt->rowCount();
}

function getActiveMembership(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, p.label AS plan_label, p.slug AS plan_slug, p.duration_days,
                b.id AS branch_id, b.name AS branch_name, b.city AS branch_city, b.address AS branch_address
         FROM memberships m
         INNER JOIN membership_plans p ON p.id = m.plan_id
         INNER JOIN branches b ON b.id = m.branch_id
         WHERE m.user_id = ?
           AND m.status = "active"
           AND m.payment_status = "paid"
           AND m.starts_at <= CURDATE()
           AND m.ends_at >= CURDATE()
           AND b.is_active = 1
         ORDER BY m.ends_at DESC
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $membership = $stmt->fetch();

    return $membership ?: null;
}

function hasActiveMembership(PDO $pdo, int $userId): bool
{
    return getActiveMembership($pdo, $userId) !== null;
}

function getMembershipStatus(array $membership): string
{
    if (($membership['payment_status'] ?? '') === 'pending') {
        return 'pending';
    }
    if (($membership['payment_status'] ?? '') === 'failed') {
        return 'cancelled';
    }
    if (($membership['status'] ?? '') === 'active' && !empty($membership['ends_at']) && $membership['ends_at'] < date('Y-m-d')) {
        return 'expired';
    }

    return (string) ($membership['status'] ?? 'expired');
}

function getLatestMembership(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, p.label AS plan_label, p.slug AS plan_slug, p.duration_days,
                b.id AS branch_id, b.name AS branch_name, b.city AS branch_city, b.address AS branch_address
         FROM memberships m
         INNER JOIN membership_plans p ON p.id = m.plan_id
         INNER JOIN branches b ON b.id = m.branch_id
         WHERE m.user_id = ?
         ORDER BY m.ends_at DESC, m.id DESC
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $membership = $stmt->fetch();

    return $membership ?: null;
}

function getMembershipPlans(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, slug, label, duration_days, price
         FROM membership_plans
         WHERE is_active = 1
         ORDER BY sort_order'
    )->fetchAll(PDO::FETCH_ASSOC);
}
