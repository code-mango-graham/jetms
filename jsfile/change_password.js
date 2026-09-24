$(document).ready(function () {
    $(document).off('submit', '#changePasswordForm');
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

    $(document).on('submit', '#changePasswordForm', function (e) {
        e.preventDefault();

        const newPassword = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();

        if (newPassword !== confirmPassword) {
            Swal.fire({
                icon: 'error',
                title: 'Passwords do not match',
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }

        $.ajax({
            url: 'config/change_password.php',
            type: 'POST',
            data: {
                current_password: $('#current_password').val(),
                new_password: newPassword
            },
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

                const modalEl = document.getElementById('changePasswordModal');
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                document.activeElement.blur();
                $('#changePasswordForm')[0].reset();

                if (window.__jetms_must_change) {
                    Swal.fire({ icon: 'success', title: 'Password changed', text: 'Loading your account...', timer: 1500, showConfirmButton: false })
                        .then(function () { window.location.reload(); });
                    return;
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Password Changed Successfully',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            },
            error: function (xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to change password: ' + error,
                    timer: 4000,
                    showConfirmButton: true
                });
            }
        });
    });

    // Still on the default password: the dialog cannot be dismissed until it is changed.
    if (window.__jetms_must_change) {
        const el = document.getElementById('changePasswordModal');
        $(el).find('.btn-close, [data-bs-dismiss="modal"]').hide();
        if (!$(el).find('.jetms-must-change').length) {
            $(el).find('.modal-body').prepend('<div class="alert alert-warning jetms-must-change">You are still using the default password. Choose a new one (8 or more characters, with letters and numbers) to continue.</div>');
        }
        bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: false }).show();
    }
});
