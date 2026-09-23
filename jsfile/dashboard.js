$(document).ready(function () {

    $(document).off('click', '#dashViewCalendar');

    function money(v) {
        return '₱' + parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function timeAgo(dateStr) {
        const diffMs = Date.now() - new Date(dateStr.replace(' ', 'T')).getTime();
        const mins = Math.floor(diffMs / 60000);
        if (mins < 1) return 'just now';
        if (mins < 60) return mins + 'm ago';
        const hrs = Math.floor(mins / 60);
        if (hrs < 24) return hrs + 'h ago';
        const days = Math.floor(hrs / 24);
        return days + 'd ago';
    }

    const eventTypeColors = { Holiday: '#dc3545', Exam: '#0d6efd', Meeting: '#6f42c1', Deadline: '#fd7e14', Other: '#6c757d' };

    $.ajax({
        url: 'config/dashboard.php',
        type: 'POST',
        data: { action: 'stats' },
        dataType: 'json',
        success: function (res) {
            if (res.status === 'error') {
                Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                return;
            }

            const totals = res.totals || {};
            $('#dashActiveYear').text(totals.active_schoolyear_name ? '(S.Y. ' + totals.active_schoolyear_name + ')' : '(No active year)');
            $('#dashTotalEnrolled').text(totals.total_enrolled || 0);
            $('#dashTotalCollected').text(money(totals.total_collected));
            $('#dashTotalBalance').text(money(totals.total_balance));
            $('#dashAttendanceToday').text(totals.attendance_today || 0);

            // Recent payments
            const payments = res.recent_payments || [];
            $('#dashRecentPayments').html(payments.length ? payments.map(function (p) {
                return `<li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <div>${p.last_name}, ${p.first_name}</div>
                        <div class="text-muted small">${p.payment_date} &middot; ${p.payment_mode}</div>
                    </div>
                    <strong class="text-success">${money(p.amount)}</strong>
                </li>`;
            }).join('') : '<li class="list-group-item text-muted">No payments recorded yet.</li>');

            // Recent enrollments
            const enrollments = res.recent_enrollments || [];
            $('#dashRecentEnrollments').html(enrollments.length ? enrollments.map(function (e) {
                return `<li class="list-group-item">
                    <div>${e.last_name}, ${e.first_name}</div>
                    <div class="text-muted small">${e.level_name} - ${e.section_name} &middot; ${e.enrollment_date}</div>
                </li>`;
            }).join('') : '<li class="list-group-item text-muted">No enrollments yet.</li>');

            // Recent announcements
            const announcements = res.recent_announcements || [];
            $('#dashRecentAnnouncements').html(announcements.length ? announcements.map(function (a) {
                return `<li class="list-group-item">
                    <div>${a.title}</div>
                    <div class="text-muted small">${a.admin_name} &middot; ${timeAgo(a.created_at)}</div>
                </li>`;
            }).join('') : '<li class="list-group-item text-muted">No announcements yet.</li>');

            // Upcoming events
            const events = res.upcoming_events || [];
            if (events.length === 0) {
                $('#dashUpcomingEvents').html('<div class="col-12 text-muted">No upcoming events.</div>');
            } else {
                $('#dashUpcomingEvents').html(events.map(function (ev) {
                    const color = eventTypeColors[ev.event_type] || eventTypeColors.Other;
                    const rangeLabel = ev.end_date && ev.end_date !== ev.start_date ? `${ev.start_date} - ${ev.end_date}` : ev.start_date;
                    return `<div class="col-md-6 col-lg-4">
                        <div class="subpanel p-2" style="border-left:4px solid ${color};">
                            <span class="badge" style="background:${color};">${ev.event_type}</span>
                            <strong class="ms-1">${ev.title}</strong>
                            <div class="text-muted small">${rangeLabel}</div>
                        </div>
                    </div>`;
                }).join(''));
            }
        }
    });

    $(document).on('click', '#dashViewCalendar', function (e) {
        e.preventDefault();
        $('#nav_school_calendar').trigger('click');
    });

});
