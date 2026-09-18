$(document).ready(function () {
    let usersTable;

    function showUserModal() {
        const el = document.getElementById('userModal');
        if (!el) return;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
    }

    function hideUserModal() {
        const el = document.getElementById('userModal');
        if (!el) return;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.hide();
    }

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddUser');
    $(document).off('submit', '#userForm');
    $(document).off('click', '.btnDeleteUser');
    $(document).off('click', '.btnResetPassword');
    $(document).off('change', '#account_role');
    $(document).off('click', '.toggle-password');

    $(document).on('click', '.toggle-password', function () {
        const input = $(this).closest('.input-group').find('input');
        const icon = $(this).find('i');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    function toggleRoleFields(role) {
        $('.field-admin').addClass('d-none');
        $('.field-admin input').prop('required', false);
        $('.field-linked').addClass('d-none');
        $('#linked_id').prop('required', false);

        if (role === 'admin') {
            $('.field-admin').removeClass('d-none');
            $('#first_name, #last_name').prop('required', true);
        } else if (role === 'student' || role === 'teacher') {
            $('.field-linked').removeClass('d-none');
            $('#linked_id').prop('required', true);
            loadLinkedOptions(role);
        }
    }

    function loadLinkedOptions(role) {
        const url = role === 'teacher' ? 'config/teacher.php' : 'config/student.php';
        const idField = role === 'teacher' ? 'teacher_id' : 'student_id';

        $.ajax({
            url: url,
            type: 'POST',
            data: { action: 'load' },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select --</option>';

                (res.data || []).forEach(function (row) {
                    const label = `${row.last_name}, ${row.first_name}`;
                    options += `<option value="${row[idField]}">${label}</option>`;
                });

                $('#linked_id').html(options).trigger('change');
            }
        });
    }

    function initTable() {
        if ($.fn.DataTable.isDataTable('#usersTable')) {
            $('#usersTable').DataTable().destroy();
            $('#usersTable tbody').empty();
        }

        usersTable = $('#usersTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: {
                lengthMenu: 'Show _MENU_ entries'
            },
            ajax: {
                url: 'config/user.php',
                type: 'POST',
                data: function (d) {
                    d.action = 'load';
                    return d;
                },
                dataSrc: 'data'
            },
            columns: [
                {
                    data: 'role',
                    render: function (data) {
                        return data ? data.charAt(0).toUpperCase() + data.slice(1) : '';
                    }
                },
                { data: 'full_name', defaultContent: '' },
                { data: 'username' },
                { data: 'created_at', defaultContent: '' },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btnResetPassword" data-role="${data.role}" data-id="${data.account_id}" title="Reset Password">
                                    <i class="bi bi-key"></i>
                                </button>
                                <button class="btn btn-outline-danger btnDeleteUser" data-role="${data.role}" data-id="${data.account_id}" title="Delete">
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

    function resetUserForm() {
        $('#userForm')[0].reset();
        $('#account_role').val('').trigger('change');
        $('#linked_id').html('<option value="">-- Select --</option>').trigger('change');
        toggleRoleFields('');
    }

    $(document).on('change', '#account_role', function () {
        toggleRoleFields($(this).val());
    });

    $(document).on('click', '#btnAddUser', function () {
        resetUserForm();
        $('.modal-title').text('Add Account');
        showUserModal();
    });

    $(document).on('submit', '#userForm', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/user.php',
            type: 'POST',
            data: $(this).serialize() + '&action=add',
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

                hideUserModal();
                document.activeElement.blur();

                if (usersTable) {
                    usersTable.ajax.reload(null, false);
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Account Created',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
    });

    $(document).on('click', '.btnDeleteUser', function () {
        const role = $(this).data('role');
        const account_id = $(this).data('id');

        Swal.fire({
            title: 'Remove Account?',
            text: 'Are you sure you want to remove this login account?',
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
                url: 'config/user.php',
                type: 'POST',
                data: { action: 'delete', role: role, account_id: account_id },
                dataType: 'json',
                success: function (res) {
                    if (!res.success) {
                        Swal.fire({
                            icon: 'error',
                            title: res.message,
                            timer: 3000,
                            showConfirmButton: false
                        });
                        return;
                    }

                    document.activeElement.blur();

                    if (usersTable) {
                        usersTable.ajax.reload(null, false);
                    }

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Account Removed',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
        });
    });

    $(document).on('click', '.btnResetPassword', function () {
        const role = $(this).data('role');
        const account_id = $(this).data('id');

        Swal.fire({
            title: 'Reset Password',
            input: 'password',
            inputLabel: 'New password',
            inputPlaceholder: 'Enter a new password',
            showCancelButton: true,
            confirmButtonText: 'Reset',
            cancelButtonText: 'Cancel',
            inputValidator: function (value) {
                if (!value || value.length < 4) {
                    return 'Password must be at least 4 characters';
                }
            }
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: 'config/user.php',
                type: 'POST',
                data: { action: 'reset_password', role: role, account_id: account_id, password: result.value },
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

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Password Reset',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
        });
    });

    initTable();
});
