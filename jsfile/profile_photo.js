$(document).ready(function () {

    $(document).off('change', '#profile_photo_input');
    $(document).off('submit', '#profilePhotoForm');

    $(document).on('change', '#profile_photo_input', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            $('#profilePhotoPreview').attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
    });

    $('#profilePhotoForm').submit(function (e) {
        e.preventDefault();

        const fileInput = document.getElementById('profile_photo_input');
        if (!fileInput.files[0]) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_my_photo');
        formData.append('photo', fileInput.files[0]);

        $.ajax({
            url: 'config/user.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3500, showConfirmButton: false });
                    return;
                }

                const newSrc = 'assets/img/' + (res.role === 'admin' ? 'admins' : 'students') + '/' + res.photo;
                $('#topbarUserPhoto, #dropdownUserPhoto').attr('src', newSrc);

                $('#profilePhotoModal').modal('hide');
                document.activeElement.blur();

                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Profile Photo Updated', showConfirmButton: false, timer: 3000, timerProgressBar: true
                });
            }
        });
    });

});
