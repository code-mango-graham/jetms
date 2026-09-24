$(document).ready(function () {

    let attendanceTable;

    attendanceTable = $('#attendanceTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No attendance logged today." },
        order: [[0, 'desc']],
        ajax: {
            url: 'config/attendance.php',
            type: 'POST',
            data: { action: 'load' }
        },
        columns: [
            { data: 'log_time' },
            { data: 'student_name' },
            { data: 'lrn' },
            {
                data: 'log_type',
                render: function (data) {
                    return data === 'in'
                        ? '<span class="badge bg-success">Time In</span>'
                        : '<span class="badge bg-secondary">Time Out</span>';
                }
            }
        ]
    });

    function focusScanInput() {
        setTimeout(function () { $('#scan_lrn').trigger('focus'); }, 50);
    }

    focusScanInput();

    $('#scanForm').submit(function (e) {
        e.preventDefault();

        const lrn = $('#scan_lrn').val().trim();
        if (!lrn) {
            focusScanInput();
            return;
        }

        $.ajax({
            url: 'config/attendance.php',
            type: 'POST',
            data: { action: 'scan', lrn: lrn, source: 'manual' },
            dataType: 'json',
            success: function (res) {
                $('#scan_lrn').val('');
                focusScanInput();

                if (res.status === 'error') {
                    $('#scanResult').html(`
                        <div class="alert alert-danger d-flex align-items-center mb-0">
                            <i class="bi bi-x-circle fs-3 me-2"></i>
                            <div>${esc(res.message)}</div>
                        </div>
                    `);
                    return;
                }

                const d = res.data;
                const isIn = d.log_type === 'in';
                const photo = d.student_photo ? 'assets/img/students/' + d.student_photo : 'logos/logo1.png';

                $('#scanResult').html(`
                    <div class="alert ${isIn ? 'alert-success' : 'alert-secondary'} d-flex align-items-center mb-0">
                        <img src="${photo}" class="rounded-circle me-3" style="width:56px;height:56px;object-fit:cover;">
                        <div class="flex-grow-1">
                            <div class="fw-bold fs-5">${esc(d.student_name)}</div>
                            <div>${esc(d.log_time)}</div>
                        </div>
                        <div class="fs-4 fw-bold">${isIn ? 'TIME IN' : 'TIME OUT'}</div>
                    </div>
                `);

                attendanceTable.ajax.reload(null, false);
            },
            error: function () {
                focusScanInput();
            }
        });
    });

    $('#btnRefreshLog').on('click', function () {
        attendanceTable.ajax.reload(null, false);
    });

});
