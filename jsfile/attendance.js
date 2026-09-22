$(document).ready(function () {

    let studentAttendanceTable = null;

    $(document).off('change', '#attendance_student_id');

    $.ajax({
        url: 'config/student.php',
        type: 'POST',
        data: { action: 'load' },
        dataType: 'json',
        success: function (res) {
            let options = '<option value="">-- Select Student --</option>';
            (res.data || []).forEach(function (st) {
                if (st.student_status === 'archived') return;
                const label = `${st.last_name}, ${st.first_name}` + (st.lrn ? ` (${st.lrn})` : '');
                options += `<option value="${st.student_id}">${label}</option>`;
            });
            $('#attendance_student_id').html(options).trigger('change');
        }
    });

    $('.select2').select2({
        width: '100%',
        placeholder: 'Search and select',
        allowClear: true
    });

    function initStudentAttendanceTable() {
        studentAttendanceTable = $('#studentAttendanceTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No attendance recorded for this student yet." },
            order: [[0, 'desc'], [1, 'desc']],
            ajax: {
                url: 'config/attendance.php',
                type: 'POST',
                data: function (d) {
                    d.action = 'student_history';
                    d.student_id = $('#attendance_student_id').val();
                    return d;
                }
            },
            columns: [
                { data: 'log_time', render: function (d) { return d.split(' ')[0]; } },
                { data: 'log_time', render: function (d) { return d.split(' ')[1] || ''; } },
                {
                    data: 'log_type',
                    render: function (data) {
                        return data === 'in'
                            ? '<span class="badge bg-success">Time In</span>'
                            : '<span class="badge bg-secondary">Time Out</span>';
                    }
                },
                { data: 'source', render: function (d) { return d.charAt(0).toUpperCase() + d.slice(1); } }
            ]
        });
    }

    $(document).on('change', '#attendance_student_id', function () {
        const student_id = $(this).val();

        if (!student_id) {
            $('#attendanceEmptyState').removeClass('d-none');
            $('#attendanceResult').addClass('d-none');
            return;
        }

        $('#attendanceEmptyState').addClass('d-none');
        $('#attendanceResult').removeClass('d-none');

        if (studentAttendanceTable) {
            studentAttendanceTable.ajax.reload(null, false);
        } else {
            initStudentAttendanceTable();
        }
    });

});
