<div class="row g-3">
    <div class="col-lg-8">
        <div class="gym-calendar-wrap">
            <div class="cal-header">
                <div>
                    <div class="section-kicker text-muted mb-1">Attendance</div>
                    <div class="cal-title" id="calTitle">May 2026</div>
                </div>
                <div class="cal-nav">
                    <button class="cal-nav-btn" onclick="calNav(-1)" aria-label="Previous month"><i class="ti ti-chevron-left"></i></button>
                    <button class="cal-nav-btn" onclick="calNav(1)" aria-label="Next month"><i class="ti ti-chevron-right"></i></button>
                </div>
            </div>
            <?php if (!$attendanceDates): ?>
            <div class="empty-inline mb-3">
                <i class="ti ti-calendar-off"></i>
                <span>No attendance history yet. Your check-ins will appear here after your first visit.</span>
            </div>
            <?php endif ?>
            <div class="cal-days-header">
                <div class="cal-day-name">Sun</div>
                <div class="cal-day-name">Mon</div>
                <div class="cal-day-name">Tue</div>
                <div class="cal-day-name">Wed</div>
                <div class="cal-day-name">Thu</div>
                <div class="cal-day-name">Fri</div>
                <div class="cal-day-name">Sat</div>
            </div>
            <div class="cal-grid" id="calGrid"></div>
            <div class="cal-streak-bar">
                <div>
                    <div class="cal-streak-num" id="streakNum"><?= number_format($currentStreak) ?></div>
                    <div class="cal-streak-lbl">Day Streak</div>
                </div>
                <div style="flex:1;height:6px;background:var(--input-bg);border-radius:3px;overflow:hidden;margin-left:.5rem">
                    <div id="streakBar" style="height:100%;background:var(--fs-red);border-radius:3px;transition:width .5s;width:0%"></div>
                </div>
                <div style="font-size:.75rem;color:var(--text-muted)" id="streakGoal">/ 7 day goal</div>
            </div>
            <div class="cal-legend">
                <div class="cal-legend-item"><div class="cal-legend-dot" style="background:var(--fs-red)"></div> Gym Day</div>
                <div class="cal-legend-item"><div class="cal-legend-dot" style="border:1px solid var(--fs-red);background:transparent"></div> Today</div>
                <div class="cal-legend-item"><div class="cal-legend-dot" style="background:var(--input-bg)"></div> No Visit</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="fs-card mb-3">
            <div class="section-kicker text-muted">Visit Summary</div>
            <div class="d-flex flex-column gap-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:.82rem;color:var(--text-muted)"><i class="ti ti-calendar-stats me-1"></i>This Month</span>
                    <span style="font-size:.82rem;font-weight:700;color:var(--text-primary)"><?= number_format($monthlyVisits) ?> visits</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:.82rem;color:var(--text-muted)"><i class="ti ti-clock-check me-1"></i>Last Visit</span>
                    <span style="font-size:.82rem;font-weight:700;color:var(--text-primary)"><?= $lastAttendanceDate ? date('M j, Y', strtotime($lastAttendanceDate)) : 'Never' ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:.82rem;color:var(--text-muted)"><i class="ti ti-clipboard-check me-1"></i>Total Visits</span>
                    <span style="font-size:.82rem;font-weight:700;color:var(--text-primary)" id="attendanceTotalStat"><?= number_format($attendanceTotal) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
