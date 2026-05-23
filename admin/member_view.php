<?php
require_once __DIR__ . '/../config/auth_guard.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/member_helpers.php';

$pdo = db();
expireOldMemberships($pdo);

$memberId = max(0, (int) ($_GET['id'] ?? 0));
$member = $memberId ? memberProfile($pdo, $memberId) : null;
if (!$member) {
    http_response_code(404);
    exit('Member not found.');
}

$history = memberMembershipHistory($pdo, $memberId);
$insights = memberAttendanceInsights($pdo, $memberId);
$notes = memberNotes($pdo, $memberId);
$timeline = memberTimeline($pdo, $memberId);
$flags = memberRetentionIndicators($member, $insights);
$branches = $pdo->query('SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Member Profile';
$topbarCrumb = $member['first_name'] . ' ' . $member['last_name'];
$activeAdminPage = 'members';
require_once __DIR__ . '/../includes/admin_header.php';
require_once __DIR__ . '/../includes/admin_sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a class="btn btn-outline-secondary btn-sm rounded-pill" href="../admin.php#members"><i class="ti ti-chevron-left"></i> Back to Members</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-4">
        <div class="fs-card p-3 h-100">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:64px;height:64px;border-radius:18px;background:var(--fs-red);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:900">
                    <?= htmlspecialchars(strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1))) ?>
                </div>
                <div>
                    <div style="font-size:1.25rem;font-weight:900"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?></div>
                    <div style="color:var(--text-muted);font-size:.86rem"><?= htmlspecialchars($member['email']) ?></div>
                </div>
            </div>
            <div class="d-flex flex-column gap-2" style="font-size:.86rem">
                <div><strong>Gender:</strong> <span style="color:var(--text-muted)"><?= htmlspecialchars($member['gender'] ?? 'Not set') ?></span></div>
                <div><strong>Birthdate:</strong> <span style="color:var(--text-muted)"><?= $member['birthdate'] ? date('M j, Y', strtotime($member['birthdate'])) : 'Not set' ?></span></div>
                <div><strong>Account:</strong> <span class="status-badge <?= $member['is_active'] ? 'active' : 'cancelled' ?>"><?= $member['is_active'] ? 'Active' : 'Inactive' ?></span></div>
                <div><strong>Branch:</strong> <span style="color:var(--text-muted)"><?= htmlspecialchars($member['branch_name'] ?? 'Unassigned') ?></span></div>
                <div><strong>Registered:</strong> <span style="color:var(--text-muted)"><?= date('M j, Y', strtotime($member['created_at'])) ?></span></div>
                <div><strong>Last Login:</strong> <span style="color:var(--text-muted)"><?= $member['last_login_at'] ? date('M j, Y g:i A', strtotime($member['last_login_at'])) : 'Never' ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="row g-3">
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon"><i class="ti ti-clipboard-check"></i></div><div class="stat-value"><?= number_format($insights['total_visits']) ?></div><div class="stat-label">Total Visits</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon"><i class="ti ti-flame"></i></div><div class="stat-value"><?= number_format($insights['current_streak']) ?></div><div class="stat-label">Day Streak</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon"><i class="ti ti-calendar"></i></div><div class="stat-value"><?= number_format($insights['monthly_attendance']) ?></div><div class="stat-label">This Month</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-icon"><i class="ti ti-repeat"></i></div><div class="stat-value"><?= number_format($insights['attendance_frequency'], 1) ?></div><div class="stat-label">Frequency</div></div></div>
        </div>
        <div class="fs-card p-3 mt-3">
            <div style="font-size:.75rem;font-weight:800;text-transform:uppercase;color:var(--text-muted);margin-bottom:.75rem">Retention Indicators</div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($flags): ?>
                    <?php foreach ($flags as $flag): ?>
                        <span class="status-badge <?= $flag['level'] === 'danger' ? 'failed' : 'pending' ?>"><?= htmlspecialchars($flag['label']) ?></span>
                    <?php endforeach ?>
                <?php else: ?>
                    <span class="status-badge active">No active risk flags</span>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <div class="admin-table-wrap">
            <div class="p-3 border-bottom" style="border-color:var(--card-border)!important;font-weight:900">Membership Lifecycle</div>
            <table class="table admin-table">
                <thead><tr><th>Plan</th><th>Dates</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['plan_label']) ?><div style="font-size:.72rem;color:var(--text-dimmed)"><?= htmlspecialchars($m['branch_name']) ?></div></td>
                            <td><?= date('M j, Y', strtotime($m['starts_at'])) ?> - <?= date('M j, Y', strtotime($m['ends_at'])) ?></td>
                            <td><span class="status-badge <?= htmlspecialchars($m['payment_status']) ?>"><?= htmlspecialchars($m['payment_status']) ?></span></td>
                            <td><span class="status-badge <?= htmlspecialchars($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($m['payment_status'] === 'pending'): ?>
                                        <button class="tbl-btn" onclick="memberAction({action:'approve_payment',membership_id:<?= (int) $m['id'] ?>})"><i class="ti ti-check"></i></button>
                                        <button class="tbl-btn" onclick="memberAction({action:'reject_payment',membership_id:<?= (int) $m['id'] ?>})"><i class="ti ti-x"></i></button>
                                    <?php endif ?>
                                    <button class="tbl-btn" onclick="memberAction({action:'set_membership_status',membership_id:<?= (int) $m['id'] ?>,status:'frozen'})"><i class="ti ti-player-pause"></i></button>
                                    <button class="tbl-btn" onclick="memberAction({action:'set_membership_status',membership_id:<?= (int) $m['id'] ?>,status:'active'})"><i class="ti ti-player-play"></i></button>
                                    <button class="tbl-btn" onclick="memberAction({action:'set_membership_status',membership_id:<?= (int) $m['id'] ?>,status:'cancelled'})"><i class="ti ti-ban"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="fs-card p-3 mb-3">
            <div style="font-weight:900;margin-bottom:.75rem">Admin Actions</div>
            <div class="row g-2">
                <div class="col-7"><input class="form-control fs-input" type="number" id="extendDays" min="1" max="730" placeholder="Extend days"></div>
                <div class="col-5"><button class="btn btn-fs w-100 rounded-pill" onclick="extendMembership()">Extend</button></div>
                <div class="col-7">
                    <select class="form-select fs-select" id="branchId">
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>" <?= (int) ($member['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-5"><button class="btn btn-outline-secondary w-100 rounded-pill" onclick="changeBranch()">Change Branch</button></div>
            </div>
        </div>
        <div class="fs-card p-3">
            <div style="font-weight:900;margin-bottom:.75rem">Internal Notes</div>
            <textarea class="form-control fs-input mb-2" id="noteBody" rows="3" placeholder="Add private admin note"></textarea>
            <button class="btn btn-fs rounded-pill mb-3" onclick="addNote()">Add Note</button>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($notes as $note): ?>
                    <div style="border-top:1px solid var(--card-border);padding-top:.75rem">
                        <div style="font-size:.82rem;color:var(--text-muted)"><?= date('M j, Y g:i A', strtotime($note['created_at'])) ?> by <?= htmlspecialchars($note['admin_first'] . ' ' . $note['admin_last']) ?></div>
                        <div><?= nl2br(htmlspecialchars($note['note_body'])) ?></div>
                    </div>
                <?php endforeach ?>
                <?php if (!$notes): ?><div style="color:var(--text-muted);font-size:.86rem">No internal notes yet.</div><?php endif ?>
            </div>
        </div>
    </div>
</div>

<div class="admin-table-wrap p-3">
    <div style="font-weight:900;margin-bottom:.75rem">Activity Timeline</div>
    <div class="d-flex flex-column gap-2">
        <?php foreach ($timeline as $event): ?>
            <div style="display:flex;gap:1rem;border-top:1px solid var(--card-border);padding-top:.75rem">
                <div style="width:110px;color:var(--text-muted);font-size:.82rem"><?= date('M j', strtotime($event['date'])) ?></div>
                <div><strong><?= htmlspecialchars($event['type']) ?></strong><div style="color:var(--text-muted)"><?= htmlspecialchars($event['body']) ?></div></div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<script>
function latestMembershipId() {
    return <?= (int) ($member['membership_id'] ?? 0) ?>;
}
function extendMembership() {
    const days = parseInt(document.getElementById('extendDays').value || '0', 10);
    if (!days || days < 1) return alert('Enter extension days.');
    memberAction({ action: 'extend_membership', membership_id: latestMembershipId(), days });
}
function changeBranch() {
    memberAction({ action: 'change_member_branch', member_id: <?= (int) $memberId ?>, branch_id: document.getElementById('branchId').value });
}
function addNote() {
    const note_body = document.getElementById('noteBody').value.trim();
    if (!note_body) return alert('Write a note first.');
    memberAction({ action: 'add_member_note', member_id: <?= (int) $memberId ?>, note_body });
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
