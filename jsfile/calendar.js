$(document).ready(function () {

    $(document).off('click', '#btnCalPrev');
    $(document).off('click', '#btnCalNext');
    $(document).off('click', '#btnCalToday');
    $(document).off('click', '.cal-day');
    $(document).off('click', '#btnShowEventForm');
    $(document).off('click', '#btnCancelEventForm');
    $(document).off('submit', '#eventForm');
    $(document).off('click', '.btnDeleteEvent');
    $(document).off('click', '.btnEditEvent');

    const isAdmin = window.__jetms_role === 'admin';
    const typeColors = { Holiday: '#dc3545', Exam: '#0d6efd', Meeting: '#6f42c1', Deadline: '#fd7e14', Other: '#6c757d' };
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const today = new Date();
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth(); // 0-indexed
    let eventsByDate = {};
    let selectedDateStr = null;

    if (isAdmin) {
        $('#btnAddEvent').removeClass('d-none');
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function toDateStr(y, m, d) { return `${y}-${pad(m + 1)}-${pad(d)}`; }

    function eachDateInRange(startStr, endStr, cb) {
        const start = new Date(startStr + 'T00:00:00');
        const end = new Date((endStr || startStr) + 'T00:00:00');
        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            cb(toDateStr(d.getFullYear(), d.getMonth(), d.getDate()));
        }
    }

    function loadEvents() {
        // Pad the fetch range to match the full grid rendered (including the
        // leading/trailing days from adjacent months shown as muted cells) —
        // otherwise clicking one of those muted days shows "no events" even
        // when it actually has one, since it was never fetched.
        const firstOfMonth = new Date(viewYear, viewMonth, 1);
        const startOffset = firstOfMonth.getDay();
        const gridStart = new Date(viewYear, viewMonth, 1 - startOffset);

        const lastDay = new Date(viewYear, viewMonth + 1, 0).getDate();
        const daysInMonth = lastDay;
        const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
        const gridEnd = new Date(gridStart);
        gridEnd.setDate(gridStart.getDate() + totalCells - 1);

        const rangeStart = toDateStr(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate());
        const rangeEnd = toDateStr(gridEnd.getFullYear(), gridEnd.getMonth(), gridEnd.getDate());

        $.ajax({
            url: 'config/event.php',
            type: 'POST',
            data: { action: 'load', range_start: rangeStart, range_end: rangeEnd },
            dataType: 'json',
            success: function (res) {
                eventsByDate = {};
                (res.data || []).forEach(function (ev) {
                    eachDateInRange(ev.start_date, ev.end_date, function (dateStr) {
                        if (!eventsByDate[dateStr]) eventsByDate[dateStr] = [];
                        eventsByDate[dateStr].push(ev);
                    });
                });
                renderGrid();
            }
        });
    }

    function renderGrid() {
        $('#calMonthLabel').text(monthNames[viewMonth] + ' ' + viewYear);

        const firstOfMonth = new Date(viewYear, viewMonth, 1);
        const startOffset = firstOfMonth.getDay(); // 0=Sun
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
        const daysInPrevMonth = new Date(viewYear, viewMonth, 0).getDate();

        let html = weekdays.map(function (w) { return `<div class="cal-weekday">${w}</div>`; }).join('');

        const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
        const todayStr = toDateStr(today.getFullYear(), today.getMonth(), today.getDate());

        for (let i = 0; i < totalCells; i++) {
            const dayNum = i - startOffset + 1;
            let cellDate, cellClasses = ['cal-day'], displayNum, muted = false;

            if (dayNum < 1) {
                displayNum = daysInPrevMonth + dayNum;
                const prevMonth = viewMonth === 0 ? 11 : viewMonth - 1;
                const prevYear = viewMonth === 0 ? viewYear - 1 : viewYear;
                cellDate = toDateStr(prevYear, prevMonth, displayNum);
                muted = true;
            } else if (dayNum > daysInMonth) {
                displayNum = dayNum - daysInMonth;
                const nextMonth = viewMonth === 11 ? 0 : viewMonth + 1;
                const nextYear = viewMonth === 11 ? viewYear + 1 : viewYear;
                cellDate = toDateStr(nextYear, nextMonth, displayNum);
                muted = true;
            } else {
                displayNum = dayNum;
                cellDate = toDateStr(viewYear, viewMonth, dayNum);
            }

            if (muted) cellClasses.push('cal-day-muted');
            if (cellDate === todayStr) cellClasses.push('cal-day-today');

            const dayEvents = eventsByDate[cellDate] || [];
            let chipsHtml = '';
            dayEvents.slice(0, 3).forEach(function (ev) {
                const color = typeColors[ev.event_type] || typeColors.Other;
                chipsHtml += `<div class="cal-day-event-chip" style="background:${color};" title="${esc(ev.title)}">${esc(ev.title)}</div>`;
            });
            if (dayEvents.length > 3) {
                chipsHtml += `<div class="text-muted" style="font-size:0.65rem;">+${dayEvents.length - 3} more</div>`;
            }

            html += `<div class="${cellClasses.join(' ')}" data-date="${cellDate}">
                <div class="cal-day-number">${displayNum}</div>
                <div class="cal-day-events">${chipsHtml}</div>
            </div>`;
        }

        $('#calendarGrid').html(html);
    }

    $(document).on('click', '#btnCalPrev', function () {
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        loadEvents();
    });

    $(document).on('click', '#btnCalNext', function () {
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        loadEvents();
    });

    $(document).on('click', '#btnCalToday', function () {
        viewYear = today.getFullYear();
        viewMonth = today.getMonth();
        loadEvents();
    });

    function renderDayModal(dateStr) {
        selectedDateStr = dateStr;
        const dayEvents = eventsByDate[dateStr] || [];
        const niceDate = new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        $('#dayModalTitle').text(niceDate);

        let listHtml = '';
        if (dayEvents.length === 0) {
            listHtml = '<div class="text-muted">No events on this day.</div>';
        } else {
            dayEvents.forEach(function (ev) {
                const color = typeColors[ev.event_type] || typeColors.Other;
                const rangeLabel = ev.end_date && ev.end_date !== ev.start_date
                    ? `${ev.start_date} &rarr; ${ev.end_date}`
                    : ev.start_date;
                listHtml += `
                    <div class="subpanel p-3 mb-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge" style="background:${color};">${ev.event_type}</span>
                                <strong class="ms-1">${esc(ev.title)}</strong>
                                <div class="text-muted small">${rangeLabel}</div>
                                ${ev.description ? `<div class="mt-1 small">${esc(ev.description)}</div>` : ''}
                                ${ev.admin_name ? `<div class="text-muted small mt-1">Posted by ${esc(ev.admin_name)}</div>` : ''}
                            </div>
                            ${isAdmin ? `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btnEditEvent" data-id="${ev.event_id}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-outline-danger btnDeleteEvent" data-id="${ev.event_id}"><i class="bi bi-trash"></i></button>
                            </div>` : ''}
                        </div>
                    </div>
                `;
            });
        }
        $('#dayEventsList').html(listHtml);
        $('#eventForm').addClass('d-none')[0].reset();
        $('#event_id').val('');

        if (isAdmin) {
            $('#btnShowEventForm').removeClass('d-none');
        }

        $('#dayModal').modal('show');
    }

    $(document).on('click', '.cal-day', function () {
        renderDayModal($(this).data('date'));
    });

    $(document).on('click', '#btnShowEventForm', function () {
        $('#eventForm').removeClass('d-none');
        $('#btnShowEventForm').addClass('d-none');
        $('#event_start_date').val(selectedDateStr);
        $('#event_end_date').val('');
    });

    $(document).on('click', '#btnCancelEventForm', function () {
        $('#eventForm').addClass('d-none')[0].reset();
        $('#event_id').val('');
        if (isAdmin) $('#btnShowEventForm').removeClass('d-none');
    });

    $('#btnAddEvent').click(function () {
        const todayStr = toDateStr(today.getFullYear(), today.getMonth(), today.getDate());
        renderDayModal(todayStr);
        $('#eventForm').removeClass('d-none');
        $('#btnShowEventForm').addClass('d-none');
        $('#event_start_date').val(todayStr);
    });

    $(document).on('click', '.btnEditEvent', function () {
        const event_id = $(this).data('id');
        $.ajax({
            url: 'config/event.php',
            type: 'POST',
            data: { action: 'get', event_id: event_id },
            dataType: 'json',
            success: function (data) {
                if (data.status === 'error') {
                    Swal.fire({ icon: 'error', title: data.message, timer: 3000, showConfirmButton: false });
                    return;
                }
                $('#event_id').val(data.event_id);
                $('#event_title').val(data.title);
                $('#event_start_date').val(data.start_date);
                $('#event_end_date').val(data.end_date || '');
                $('#event_type').val(data.event_type);
                $('#event_description').val(data.description || '');
                $('#eventForm').removeClass('d-none');
                $('#btnShowEventForm').addClass('d-none');
            }
        });
    });

    $('#eventForm').submit(function (e) {
        e.preventDefault();

        const payload = {
            action: 'add',
            event_id: $('#event_id').val(),
            title: $('#event_title').val(),
            start_date: $('#event_start_date').val(),
            end_date: $('#event_end_date').val(),
            event_type: $('#event_type').val(),
            description: $('#event_description').val()
        };

        $.ajax({
            url: 'config/event.php',
            type: 'POST',
            data: payload,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }
                $('#dayModal').modal('hide');
                document.activeElement.blur();
                loadEvents();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Event saved', showConfirmButton: false, timer: 2500 });
            }
        });
    });

    $(document).on('click', '.btnDeleteEvent', function () {
        const event_id = $(this).data('id');

        Swal.fire({
            title: 'Remove this event?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Remove'
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'config/event.php',
                    type: 'POST',
                    data: { action: 'delete', event_id: event_id },
                    dataType: 'json',
                    success: function () {
                        $('#dayModal').modal('hide');
                        document.activeElement.blur();
                        loadEvents();
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Event removed', showConfirmButton: false, timer: 2500 });
                    }
                });
            }
        });
    });

    loadEvents();
});
