<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit('Not found.');
}

/** @var bool $hasActiveMembership */
/** @var array|null $mem */
/** @var int $progressPct */
/** @var int $daysRemaining */
?>
<?php if ($hasActiveMembership): ?>
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="membership-card">
            <div class="mem-tag"><span></span> Active Membership</div>
            <div class="mem-plan-name"><?= htmlspecialchars($mem['plan_label']) ?></div>
            <div class="mem-dates">
                <i class="ti ti-calendar" style="font-size:.85rem"></i>
                &nbsp;<?= date('M j, Y', strtotime($mem['starts_at'])) ?>
                &nbsp;-&nbsp;<?= date('M j, Y', strtotime($mem['ends_at'])) ?>
            </div>
            <div class="mem-progress-label">
                <span>Plan progress</span>
                <span><?= $progressPct ?>% complete</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progressFill" style="width:0%"></div>
            </div>
            <div class="mem-pills mt-3">
                <span class="mem-pill"><i class="ti ti-check"></i> Full Gym Access</span>
                <span class="mem-pill"><i class="ti ti-building-store"></i> <?= htmlspecialchars($mem['branch_name']) ?></span>
                <span class="mem-pill"><i class="ti ti-credit-card"></i> <?= htmlspecialchars(payLabel($mem['payment_method'])) ?></span>
                <span class="mem-pill"><i class="ti ti-hourglass"></i> <?= $daysRemaining ?> days left</span>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="fs-card h-100 d-flex flex-column">
            <div class="section-kicker text-muted">Membership Details</div>
            <div class="d-flex flex-column gap-3 flex-grow-1">
                <div>
                    <div class="detail-label">Plan</div>
                    <div class="detail-value"><?= htmlspecialchars($mem['plan_label']) ?></div>
                </div>
                <div>
                    <div class="detail-label">Status</div>
                    <span class="status-badge <?= htmlspecialchars($mem['status']) ?>"><?= ucfirst($mem['status']) ?></span>
                </div>
                <div>
                    <div class="detail-label">Expires</div>
                    <div class="detail-value"><?= date('M j, Y', strtotime($mem['ends_at'])) ?></div>
                </div>
                <div>
                    <div class="detail-label">Branch</div>
                    <div class="detail-value"><?= htmlspecialchars($mem['branch_name']) ?></div>
                </div>
            </div>
            <button class="btn btn-fs w-100 rounded-pill mt-3" onclick="showTab('billing', null)">
                <i class="ti ti-refresh me-1"></i>Renew / Upgrade
            </button>
        </div>
    </div>
</div>
<?php else: ?>
<div class="no-mem-card mb-4">
    <i class="ti ti-id-badge-off"></i>
    <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:.5rem">No Active Membership</div>
    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.5rem">Choose a plan or submit a renewal before checking in.</p>
    <button class="btn btn-fs rounded-pill px-4" onclick="showTab('billing', null)"><i class="ti ti-bolt me-1"></i>View Plans</button>
</div>
<?php endif ?>
