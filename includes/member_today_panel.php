<div class="fs-card member-hub-card mb-4">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <div class="section-kicker">Today / Gym Hub</div>
            <div class="section-title"><?= date('l, F j') ?></div>
            <div class="section-subtitle">Fast status for your next gym visit.</div>
        </div>
        <div class="quick-action-row">
            <button class="btn btn-outline-secondary rounded-pill px-3" onclick="showTab('schedule', null)">
                <i class="ti ti-calendar-event me-1"></i>Schedule
            </button>
        </div>
    </div>

    <div class="member-alert-list mt-3">
        <?php foreach ($memberHub['alerts'] as $alert): ?>
        <div class="member-alert member-alert-<?= memberH($alert['level']) ?>">
            <i class="ti <?= memberH($alert['icon']) ?>"></i>
            <div class="flex-grow-1">
                <div class="member-alert-title"><?= memberH($alert['title']) ?></div>
                <div class="member-alert-body"><?= memberH($alert['body']) ?></div>
            </div>
            <?php if (!empty($alert['action'])): ?>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="showTab('<?= memberH($alert['action']) ?>', null)">
                <?= memberH($alert['action_label'] ?? 'Open') ?>
            </button>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    </div>

    <?php if (!empty($memberHub['operations']['today_class']) || !empty($memberHub['operations']['announcement']) || !empty($memberHub['operations']['weekly_class_count'])): ?>
    <div class="hub-soft-panel mt-3">
        <div class="section-kicker text-muted">Today at your branch</div>
        <div class="d-flex flex-column gap-2">
            <?php if (!empty($memberHub['operations']['today_class'])): ?>
            <div class="d-flex justify-content-between gap-3">
                <span><?= memberH($memberHub['operations']['today_class']['title']) ?></span>
                <strong><?= memberH(scheduleTime((string) $memberHub['operations']['today_class']['start_time'])) ?></strong>
            </div>
            <?php endif ?>
            <?php if (!empty($memberHub['operations']['upcoming_booking'])): ?>
            <div class="d-flex justify-content-between gap-3">
                <span>Your next booking</span>
                <strong><?= memberH($memberHub['operations']['upcoming_booking']['title']) ?>, <?= memberH(date('M j', strtotime((string) $memberHub['operations']['upcoming_booking']['scheduled_date']))) ?></strong>
            </div>
            <?php endif ?>
            <?php if (!empty($memberHub['operations']['announcement'])): ?>
            <!-- Announcement moved to notification panel -->
            <?php endif ?>
            <?php if (!empty($memberHub['operations']['upcoming_bookings_this_week'])): ?>
            <div class="d-flex justify-content-between gap-3">
                <span>Your bookings this week</span>
                <strong><?= number_format((int) $memberHub['operations']['upcoming_bookings_this_week']) ?></strong>
            </div>
            <?php endif ?>
            <div class="d-flex justify-content-between gap-3">
                <span>Classes this week</span>
                <strong><?= number_format((int) $memberHub['operations']['weekly_class_count']) ?></strong>
            </div>
        </div>
    </div>
    <?php endif ?>

    <div class="row g-3 mt-1">
        <div class="col-md-3 col-6">
            <div class="hub-metric">
                <div class="hub-metric-label">Attendance</div>
                <div class="hub-metric-value"><?= $memberHub['attendance']['checked_in_today'] ? 'Checked in' : 'Not yet' ?></div>
                <div class="hub-metric-sub"><?= $memberHub['attendance']['checked_in_today'] ? 'Today logged' : 'Tap check in when you arrive' ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="hub-metric">
                <div class="hub-metric-label">Last Visit</div>
                <div class="hub-metric-value"><?= $memberHub['attendance']['last_visit'] ? date('M j', strtotime($memberHub['attendance']['last_visit'])) : 'No visits' ?></div>
                <div class="hub-metric-sub"><?= number_format($memberHub['attendance']['monthly_visits']) ?> this month</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="hub-metric">
                <div class="hub-metric-label">Branch</div>
                <div class="hub-metric-value"><?= memberH($memberHub['branch']['name']) ?></div>
                <div class="hub-metric-sub"><?= memberH($memberHub['branch']['city'] ?: 'Assigned branch') ?></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="hub-metric">
                <div class="hub-metric-label">Streak</div>
                <div class="hub-metric-value"><span id="streakStat"><?= number_format($memberHub['attendance']['current_streak']) ?></span> day<?= $memberHub['attendance']['current_streak'] === 1 ? '' : 's' ?></div>
                <div class="hub-metric-sub">Current run</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="hub-soft-panel h-100">
                <div class="section-kicker text-muted">Branch Info</div>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between gap-3">
                        <span>Schedule</span>
                        <strong><?= memberH($memberHub['branch']['schedule_status']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between gap-3">
                        <span>Notices</span>
                        <strong><?= memberH($memberHub['branch']['notice']) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="hub-soft-panel h-100">
                <div class="section-kicker text-muted">Upcoming Activity</div>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($memberHub['activity'] as $activity): ?>
                    <div class="empty-row">
                        <i class="ti <?= memberH($activity['icon']) ?>"></i>
                        <div>
                            <div class="empty-row-label"><?= memberH($activity['label']) ?></div>
                            <div class="empty-row-copy"><?= memberH($activity['value']) ?></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    </div>
</div>
