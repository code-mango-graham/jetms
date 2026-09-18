$(document).ready(function () {
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

    $('.role-tabs .nav-link').on('click', function () {
        $('.role-tabs .nav-link').removeClass('active');
        $(this).addClass('active');

        const role = $(this).data('role');
        $('#role').val(role);
        $('#roleLabel').text(role.charAt(0).toUpperCase() + role.slice(1));
    });

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        const $btn = $('#btnLogin');
        $btn.prop('disabled', true);

        $.ajax({
            url: 'config/auth_login.php',
            type: 'POST',
            data: {
                role: $('#role').val(),
                username: $('#username').val(),
                password: $('#password').val()
            },
            dataType: 'json',
            success: function (res) {
                if (res.status !== 'success') {
                    $btn.prop('disabled', false);
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: res.message || 'Invalid username or password',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                window.location.href = res.redirect || 'mainpage.php';
            },
            error: function (xhr, status, error) {
                $btn.prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to log in: ' + error,
                    timer: 4000,
                    showConfirmButton: true
                });
            }
        });
    });
});
