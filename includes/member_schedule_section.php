<div class="fs-card mb-3">
    <div class="section-kicker text-muted">Schedule</div>
    <div class="section-title">Branch schedule</div>
    <div class="section-subtitle">Real operating hours, active announcements, and upcoming classes for your assigned branch.</div>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="fs-card mb-3">
            <div class="section-kicker text-muted">Your Branch</div>
            <div style="font-size:1.2rem;font-weight:900;color:var(--text-primary)"><?= htmlspecialchars($mem['branch_name'] ?? 'Unassigned') ?></div>
            <div style="font-size:.85rem;color:var(--text-muted);margin-top:.25rem"><?= htmlspecialchars($mem['branch_city'] ?? '') ?></div>
            <?php if (!empty($mem['branch_address'])): ?>
            <div style="font-size:.78rem;color:var(--text-dimmed);margin-top:.35rem"><?= htmlspecialchars($mem['branch_address']) ?></div>
            <?php endif ?>
            <hr style="border-color:var(--card-border)">
            <div class="schedule-placeholder-list">
                <?php if (!empty($scheduleContext['hours'])): ?>
                    <?php foreach ($scheduleContext['hours'] as $hour): ?>
                    <div class="schedule-placeholder-row">
                        <i class="ti ti-clock-hour-4"></i>
                        <div>
                            <div class="detail-value"><?= htmlspecialchars(scheduleDayName((int) $hour['day_of_week'])) ?></div>
                            <div class="detail-muted">
                                <?= (int) $hour['is_closed'] === 1 ? 'Closed' : htmlspecialchars(scheduleTime($hour['open_time']) . ' - ' . scheduleTime($hour['close_time'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <div class="empty-inline">
                        <i class="ti ti-clock-off"></i>
                        <span>Operating hours are not configured for this branch yet.</span>
                    </div>
                <?php endif ?>
            </div>
        </div>
        <div class="fs-card">
            <div class="section-kicker text-muted">Announcements</div>
            <div class="d-flex flex-column gap-2">
                <?php if (!empty($scheduleContext['announcements'])): ?>
                    <?php foreach ($scheduleContext['announcements'] as $notice): ?>
                    <div class="empty-row">
                        <i class="ti ti-speakerphone"></i>
                        <div>
                            <div class="empty-row-label"><?= htmlspecialchars($notice['title']) ?></div>
                            <div class="empty-row-copy"><?= htmlspecialchars($notice['body']) ?></div>
                            <div class="empty-row-copy">Until <?= $notice['ends_at'] ? date('M j, Y', strtotime($notice['ends_at'])) : 'further notice' ?></div>
                        </div>
                    </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <div class="empty-inline">
                        <i class="ti ti-speakerphone"></i>
                        <span>No active branch announcements right now.</span>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="fs-card mb-3">
            <div class="section-kicker text-muted">My Bookings</div>
            <div class="section-title">Reserved classes</div>
            <div class="d-flex flex-column gap-2 mt-2">
                <?php if (!empty($scheduleContext['bookings'])): ?>
                    <?php foreach ($scheduleContext['bookings'] as $booking): ?>
                    <?php
                        $bookingCapacity = $booking['capacity'] !== null ? (int) $booking['capacity'] : null;
                        $bookingRemaining = scheduleRemainingCapacity($bookingCapacity, (int) $booking['booked_count']);
                    ?>
                    <div class="schedule-placeholder-row">
                        <i class="ti ti-calendar-check"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div class="detail-value"><?= htmlspecialchars($booking['title']) ?></div>
                                <span class="status-badge active"><?= htmlspecialchars($booking['booking_status']) ?></span>
                            </div>
                            <div class="detail-muted">
                                <?= htmlspecialchars(date('M j, Y', strtotime($booking['scheduled_date'])) . ' · ' . scheduleTime($booking['start_time']) . ' - ' . scheduleTime($booking['end_time'])) ?>
                                <?php if (!empty($booking['trainer_name'])): ?>
                                    &middot; Coach <?= htmlspecialchars($booking['trainer_name']) ?>
                                <?php endif ?>
                            </div>
                            <div class="detail-muted">
                                <?= htmlspecialchars($booking['branch_name']) ?>
                                <?php if ($bookingRemaining !== null): ?>
                                    &middot; <?= number_format($bookingRemaining) ?> slot<?= $bookingRemaining === 1 ? '' : 's' ?> left
                                <?php endif ?>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="cancelClassBooking(<?= (int) $booking['id'] ?>)">
                            Cancel
                        </button>
                    </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <div class="empty-inline">
                        <i class="ti ti-calendar-plus"></i>
                        <span>No upcoming reservations yet.</span>
                    </div>
                <?php endif ?>
            </div>
        </div>
        <div class="fs-card">
            <div class="section-kicker text-muted">Upcoming Classes</div>
            <div class="d-flex flex-column gap-2">
                <?php if (!empty($scheduleContext['upcoming_classes'])): ?>
                    <?php foreach ($scheduleContext['upcoming_classes'] as $class): ?>
                    <?php
                        $capacity = $class['capacity'] !== null ? (int) $class['capacity'] : null;
                        $bookedCount = (int) $class['booked_count'];
                        $remaining = scheduleRemainingCapacity($capacity, $bookedCount);
                        $isBooked = !empty($class['member_booking_id']);
                        $isFull = $remaining !== null && $remaining <= 0 && !$isBooked;
                    ?>
                    <div class="schedule-placeholder-row">
                        <i class="ti ti-calendar-event"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div class="detail-value"><?= htmlspecialchars($class['title']) ?></div>
                                <span class="status-badge active"><?= htmlspecialchars(date('M j', strtotime($class['scheduled_date']))) ?></span>
                            </div>
                            <div class="detail-muted">
                                <?= htmlspecialchars(scheduleTime($class['start_time']) . ' - ' . scheduleTime($class['end_time'])) ?>
                                <?php if (!empty($class['trainer_name'])): ?>
                                    &middot; Coach <?= htmlspecialchars($class['trainer_name']) ?>
                                <?php endif ?>
                                &middot; <?= number_format($bookedCount) ?> booked<?= $capacity ? ' / ' . number_format($capacity) : '' ?>
                            </div>
                            <?php if (!empty($class['description'])): ?>
                            <div class="detail-muted"><?= htmlspecialchars($class['description']) ?></div>
                            <?php endif ?>
                        </div>
                        <?php if ($isBooked): ?>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="cancelClassBooking(<?= (int) $class['member_booking_id'] ?>)">Cancel</button>
                        <?php elseif ($isFull): ?>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" disabled>Full</button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-fs rounded-pill" onclick="bookClass(<?= (int) $class['id'] ?>)">Reserve</button>
                        <?php endif ?>
                    </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="ti ti-calendar-off"></i>
                        <p>No upcoming classes are scheduled for your branch yet.</p>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
