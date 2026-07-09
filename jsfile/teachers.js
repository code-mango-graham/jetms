$(document).ready(function () {
    let teachersTable;

    function showTeacherModal() {
        const el = document.getElementById('teacherModal');
        if (!el) return;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
    }

    function hideTeacherModal() {
        const el = document.getElementById('teacherModal');
        if (!el) return;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.hide();
    }
    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddTeacher');
    $(document).off('click', '#refreshTeachers');
    $(document).off('submit', '#teacherForm');
    $(document).off('click', '.btnEditTeacher');
    $(document).off('click', '.btnDeleteTeacher');

    function formatDate(value) {
        if (!value || value === '0000-00-00') {
            return '';
        }

        return value;
    }

    function loadPositions(selectedId) {
        return $.ajax({
            url: 'config/position_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Position --</option>';

                (res.data || []).forEach(function (row) {
                    options += `<option value="${row.position_id}">${row.position_title}</option>`;
                });

                $('#position_id').html(options);

                if (selectedId) {
                    $('#position_id').val(String(selectedId));
                }

                $('#position_id').trigger('change');
            }
        });
    }

    function loadOffices(selectedId) {
        return $.ajax({
            url: 'config/office_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Office --</option>';

                (res.data || []).forEach(function (row) {
                    options += `<option value="${row.office_id}">${row.office_name}</option>`;
                });

                $('#department_id').html(options);

                if (selectedId) {
                    $('#department_id').val(String(selectedId));
                }

                $('#department_id').trigger('change');
            }
        });
    }

    function initTable() {
        if ($.fn.DataTable.isDataTable('#teachersTable')) {
            $('#teachersTable').DataTable().destroy();
            $('#teachersTable tbody').empty();
        }

        teachersTable = $('#teachersTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: {
                lengthMenu: 'Show _MENU_ entries'
            },
            ajax: {
                url: 'config/teacher_load.php',
                type: 'POST',
                dataSrc: 'data'
            },
            columns: [
                { data: 'last_name' },
                { data: 'first_name' },
                { data: 'middle_name', defaultContent: '' },
                { data: 'extension_name', defaultContent: '' },
                { data: 'nick_name', defaultContent: '' },
                { data: 'gender', defaultContent: '' },
                {
                    data: 'birthday',
                    render: function (data) {
                        return formatDate(data);
                    }
                },
                { data: 'position_title', defaultContent: '' },
                { data: 'office_name', defaultContent: '' },
                {
                    data: 'date_hired',
                    render: function (data) {
                        return formatDate(data);
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btnEditTeacher" data-id="${data.teacher_id}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btnDeleteTeacher" data-id="${data.teacher_id}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            layout: {
                topStart: [
                    {
                        buttons: [
                            { extend: 'copy', text: 'Copy', className: 'btn btn-secondary btn-sm' },
                            { extend: 'excel', text: 'Excel', className: 'btn btn-success btn-sm' },
                            { extend: 'pdf', text: 'PDF', className: 'btn btn-danger btn-sm' },
                            { extend: 'print', text: 'Print', className: 'btn btn-primary btn-sm' }
                        ]
                    },
                    'pageLength'
                ],
                topEnd: 'search'
            }
        });
    }

    function resetTeacherForm() {
        $('#teacherForm')[0].reset();
        $('#teacher_id').val('');
        $('#gender').val('').trigger('change');
        $('#position_id').val('').trigger('change');
        $('#department_id').val('').trigger('change');
    }

    $(document).on('click', '#btnAddTeacher', function () {
        resetTeacherForm();
        $('.modal-title').text('Add Teacher / Staff');

        $.when(loadPositions(), loadOffices()).always(function () {
            showTeacherModal();
        });
    });

    $(document).on('click', '#refreshTeachers', function () {
        if (teachersTable) {
            teachersTable.ajax.reload(null, false);
        }
    });

    $(document).on('click', '.btnEditTeacher', function () {
        const teacher_id = $(this).data('id');

        $.ajax({
            url: 'config/teacher_get.php',
            type: 'POST',
            data: { teacher_id: teacher_id },
            dataType: 'json',
            success: function (data) {
                if (data.status === 'error') {
                    Swal.fire({
                        icon: 'error',
                        title: data.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                resetTeacherForm();
                $('#teacher_id').val(data.teacher_id || '');
                $('#last_name').val(data.last_name || '');
                $('#first_name').val(data.first_name || '');
                $('#middle_name').val(data.middle_name || '');
                $('#extension_name').val(data.extension_name || '');
                $('#nick_name').val(data.nick_name || '');
                $('#gender').val(data.gender || '').trigger('change');
                $('#birthday').val(data.birthday || '');
                $('#date_hired').val(data.date_hired || '');
                $('#phone_number').val(data.phone_number || '');
                $('#email').val(data.email || '');

                $.when(
                    loadPositions(data.position_id),
                    loadOffices(data.department_id)
                ).always(function () {
                    $('.modal-title').text('Edit Teacher / Staff');
                    showTeacherModal();
                });
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

                hideTeacherModal();
                document.activeElement.blur();

                if (teachersTable) {
                    teachersTable.ajax.reload(null, false);
                }

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
            title: 'Delete Teacher?',
            text: 'Are you sure you want to archive this teacher record?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: 'config/teacher_delete.php',
                type: 'POST',
                data: { teacher_id: teacher_id },
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

                    document.activeElement.blur();

                    if (teachersTable) {
                        teachersTable.ajax.reload(null, false);
                    }

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Deleted Successfully',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
        });
    });

    initTable();
    loadPositions();
    loadOffices();
});
