$(document).ready(function () {
    $(document).off('click', '#btnAddTeacher');
    $(document).off('click', '#refreshTeachers');
    $(document).off('submit', '#teacherForm');
    $(document).off('click', '.btnEditTeacher');
    $(document).off('click', '.btnDeleteTeacher');

    const teacherTable = $('#teachersTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: {
            lengthMenu: "Show _MENU_ entries"
        },
        ajax: {
            url: 'config/teacher_load.php',
            type: 'POST',
            dataSrc: 'data'
        },
        columns: [
            { data: 'last_name' },
            { data: 'first_name' },
            { data: 'middle_name', render: data => data || '' },
            { data: 'extension_name', render: data => data || '' },
            { data: 'nick_name', render: data => data || '' },
            { data: 'gender' },
            { data: 'birthday' },
            { data: 'position_title', render: data => data || '' },
            { data: 'department_name', render: data => data || '' },
            { data: 'date_hired', render: data => data || '' },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary btnEditTeacher" data-id="${data.teacher_id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btnDeleteTeacher" data-id="${data.teacher_id}" title="Archive">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    function loadSelectOptions(selectedPositionId = '', selectedDepartmentId = '') {
        $.ajax({
            url: 'config/position_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                const positionSelect = $('#position_id');
                positionSelect.empty().append('<option value="">-- Select Position --</option>');

                (res.data || []).forEach(function (item) {
                    positionSelect.append(`<option value="${item.position_id}">${item.position_title}</option>`);
                });

                if (selectedPositionId !== '') {
                    positionSelect.val(String(selectedPositionId));
                }
                positionSelect.trigger('change');
            }
        });

        $.ajax({
            url: 'config/department_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                const departmentSelect = $('#department_id');
                departmentSelect.empty().append('<option value="">-- Select Department --</option>');

                (res.data || []).forEach(function (item) {
                    departmentSelect.append(`<option value="${item.department_id}">${item.department_name}</option>`);
                });

                if (selectedDepartmentId !== '') {
                    departmentSelect.val(String(selectedDepartmentId));
                }
                departmentSelect.trigger('change');
            }
        });
    }

    $(document).on('click', '#btnAddTeacher', function () {
        $('#teacherForm')[0].reset();
        $('#teacher_id').val('');
        $('#teacherModal .modal-title').text('Add Teacher / Staff');
        loadSelectOptions();
        $('#teacherModal').modal('show');
    });

    $(document).on('click', '.btnEditTeacher', function () {
        const teacher_id = $(this).data('id');

        $.ajax({
            url: 'config/teacher_get.php',
            type: 'POST',
            dataType: 'json',
            data: { teacher_id: teacher_id },
            success: function (data) {
                $('#teacher_id').val(data.teacher_id);
                $('#first_name').val(data.first_name);
                $('#middle_name').val(data.middle_name);
                $('#last_name').val(data.last_name);
                $('#extension_name').val(data.extension_name);
                $('#nick_name').val(data.nick_name);
                $('#gender').val(data.gender).trigger('change');
                $('#birthday').val(data.birthday);
                $('#phone_number').val(data.phone_number);
                $('#email').val(data.email);
                $('#date_hired').val(data.date_hired);

                $('#teacherModal .modal-title').text('Edit Teacher / Staff');
                loadSelectOptions(data.position_id, data.department_id);
                $('#teacherModal').modal('show');
            }
        });
    });

    $(document).on('submit', '#teacherForm', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/teacher_add.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({
                        icon: 'error',
                        title: res.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                $('#teacherModal').modal('hide');
                teacherTable.ajax.reload(null, false);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Saved Successfully',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
    });

    $(document).on('click', '.btnDeleteTeacher', function () {
        const teacher_id = $(this).data('id');

        Swal.fire({
            title: 'Archive this teacher?',
            text: 'This will set teacher_remarks to 0.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, archive it'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: 'config/teacher_delete.php',
                type: 'POST',
                dataType: 'json',
                data: { teacher_id: teacher_id },
                success: function (res) {
                    if (res.status === 'error') {
                        Swal.fire({
                            icon: 'error',
                            title: res.message,
                            timer: 3000,
                            showConfirmButton: false
                        });
                        return;
                    }

                    teacherTable.ajax.reload(null, false);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: res.message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
        });
    });

    $(document).on('click', '#refreshTeachers', function () {
        teacherTable.ajax.reload(null, false);
    });
});
