$(document).ready(function () {
    const params = new URLSearchParams(window.location.search);
    const studentId = params.get('student_id');

    function valueOrDash(value) {
        return value && String(value).trim() ? value : '-';
    }

    function safePhotoSrc(path) {
        if (!path) return 'logos/logo1.png';
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        if (path.startsWith('/')) return path.slice(1);
        if (path.includes('/')) return path;
        return 'assets/img/students/' + path;
    }

    function money(v) {
        return '₱' + parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const statusBadgeMap = { enrolled: 'bg-success', dropped: 'bg-danger', transferred: 'bg-warning', completed: 'bg-secondary' };

    function loadCurrentEnrollment() {
        $.ajax({
            url: 'config/enrollment.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'student_current', student_id: studentId },
            success: function (res) {
                const e = res.data;

                if (!e) {
                    $('#currentEnrollmentBody').html('<div class="text-muted">Not enrolled for the current school year.</div>');
                    return;
                }

                const badge = `<span class="badge ${statusBadgeMap[e.status] || 'bg-secondary'}">${e.status.charAt(0).toUpperCase() + e.status.slice(1)}</span>`;

                $('#currentEnrollmentBody').html(`
                    <div class="row g-2">
                        <div class="col-md-3"><div class="label-muted">School Year</div><div class="value-strong">${e.schoolyear_name}</div></div>
                        <div class="col-md-3"><div class="label-muted">Level</div><div class="value-strong">${e.level_name}</div></div>
                        <div class="col-md-3"><div class="label-muted">Section</div><div class="value-strong">${e.section_name}</div></div>
                        <div class="col-md-3"><div class="label-muted">Status</div><div class="value-strong">${badge}</div></div>
                        <div class="col-md-4"><div class="label-muted">Tuition</div><div class="value-strong">${money(e.tuition_fee)}</div></div>
                        <div class="col-md-4"><div class="label-muted">Paid</div><div class="value-strong text-success">${money(e.total_paid)}</div></div>
                        <div class="col-md-4"><div class="label-muted">Balance</div><div class="value-strong text-danger">${money(e.balance)}</div></div>
                        <div class="col-12"><div class="label-muted">Subjects</div><div class="value-strong">${(e.subjects || []).map(function (s) { return s.subject_name; }).join(', ') || '-'}</div></div>
                        ${e.remarks ? `<div class="col-12"><div class="label-muted">Remarks</div><div class="value-strong">${e.remarks}</div></div>` : ''}
                    </div>
                `);
            }
        });
    }

    function initPaymentTable() {
        $('#paymentTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: { lengthMenu: 'Show _MENU_ entries', emptyTable: 'No payment records yet.' },
            ajax: {
                url: 'config/payment.php',
                type: 'POST',
                data: { action: 'student_history', student_id: studentId }
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'payment_date' },
                { data: 'schoolyear_name' },
                { data: 'amount', render: function (d) { return money(d); } },
                { data: 'payment_mode' },
                { data: 'reference_no', defaultContent: '-' },
                { data: 'admin_name' }
            ]
        });
    }

    function initHistoryTable() {
        $('#historyTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: { lengthMenu: 'Show _MENU_ entries', emptyTable: 'No enrollment history yet.' },
            ajax: {
                url: 'config/enrollment.php',
                type: 'POST',
                data: { action: 'history', student_id: studentId }
            },
            columns: [
                { data: 'schoolyear_name' },
                { data: 'level_name' },
                { data: 'section_name' },
                {
                    data: 'status',
                    render: function (data) {
                        return `<span class="badge ${statusBadgeMap[data] || 'bg-secondary'}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
                    }
                },
                { data: 'remarks', defaultContent: '-' }
            ]
        });
    }

    function loadActiveYearBadge() {
        $.ajax({
            url: 'config/schoolyear.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'load' },
            success: function (res) {
                const active = (res.data || []).find(function (y) { return y.status === 'active'; });
                $('#currentYearBadge').text(active ? 'S.Y. ' + active.schoolyear_name : 'No active school year');
            }
        });
    }

    if (!studentId) {
        alert('Missing student ID.');
        return;
    }

    loadActiveYearBadge();
    loadCurrentEnrollment();
    initPaymentTable();
    initHistoryTable();

    $.ajax({
        url: 'config/student.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'profile', student_id: studentId },
        success: function (res) {
            if (!res.success || !res.data) {
                alert(res.message || 'Student not found.');
                return;
            }

            const data = res.data;
            const fullName = [data.first_name, data.middle_name, data.last_name, data.extension_name]
                .filter(Boolean)
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim();

            $('#profilePhoto').attr('src', safePhotoSrc(data.student_photo));
            $('#studentFullName').text(fullName || 'Student Profile');
            $('#studentLrn').text('LRN: ' + valueOrDash(data.lrn));
            $('#studentStatus').text('Status: ' + valueOrDash(data.student_status));

            $('#first_name').text(valueOrDash(data.first_name));
            $('#middle_name').text(valueOrDash(data.middle_name));
            $('#last_name').text(valueOrDash(data.last_name));
            $('#extension_name').text(valueOrDash(data.extension_name));
            $('#sex').text(valueOrDash(data.sex));
            $('#birthday').text(valueOrDash(data.birthday));
            $('#cp_no').text(valueOrDash(data.cp_no));
            $('#spoken_language').text(valueOrDash(data.spoken_language));

            const parentInfo = [valueOrDash(data.father_name), valueOrDash(data.mother_name)].join(' / ');
            $('#parent_info').text(parentInfo);
            $('#contact_person').text(valueOrDash(data.contact_person));
            $('#contact_cp_no').text(valueOrDash(data.contact_cp_no));

            const address = [data.street_name, data.barangay, data.municipality, data.province]
                .filter(function (part) { return part && String(part).trim(); })
                .join(', ');
            $('#address').text(address || '-');
        },
        error: function () {
            alert('Failed to load student profile.');
        }
    });
});
