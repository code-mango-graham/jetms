$(document).ready(function () {

    const isAdmin = $('#btnAddAnnouncement').length > 0;

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddAnnouncement');
    $(document).off('submit', '#announcementForm');
    $(document).off('change', '#photos');
    $(document).off('click', '.btnEditAnnouncement');
    $(document).off('click', '.btnDeleteAnnouncement');
    $(document).off('click', '.btnRemoveExistingPhoto');
    $(document).off('click', '.announcement-photo');

    const audienceLabel = { all: 'Everyone', teachers: 'Teachers Only', students: 'Students Only' };

    // Build the photo-preview lightbox once (shared across every post/page)
    if (!document.getElementById('photoLightboxModal')) {
        $('body').append(`
            <div class="modal fade" id="photoLightboxModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content bg-dark border-0">
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" style="z-index:1;" data-bs-dismiss="modal"></button>
                        <div class="modal-body text-center p-0">
                            <img id="photoLightboxImage" src="" class="img-fluid" style="max-height:85vh;">
                        </div>
                    </div>
                </div>
            </div>
        `);
    }

    $(document).on('click', '.announcement-photo', function () {
        $('#photoLightboxImage').attr('src', $(this).attr('src'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('photoLightboxModal')).show();
    });

    function timeAgo(dateStr) {
        const then = new Date(dateStr.replace(' ', 'T'));
        const diffSec = Math.floor((Date.now() - then.getTime()) / 1000);
        if (diffSec < 60) return 'just now';
        if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm ago';
        if (diffSec < 86400) return Math.floor(diffSec / 3600) + 'h ago';
        if (diffSec < 604800) return Math.floor(diffSec / 86400) + 'd ago';
        return then.toLocaleDateString();
    }

    function fullDateTime(dateStr) {
        const then = new Date(dateStr.replace(' ', 'T'));
        return then.toLocaleString(undefined, {
            year: 'numeric', month: 'long', day: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
    }

    function photoSrc(path) {
        if (!path) return '';
        return 'assets/img/announcements/' + path;
    }

    function renderFeed() {
        $.ajax({
            url: 'config/announcement.php',
            type: 'POST',
            data: { action: 'load' },
            dataType: 'json',
            success: function (res) {
                const posts = res.data || [];

                if (!posts.length) {
                    $('#announcementFeed').html('<div class="text-center text-muted py-5"><i class="bi bi-megaphone fs-1 d-block mb-2"></i>No announcements yet.</div>');
                    return;
                }

                let html = '';
                posts.forEach(function (p) {
                    const audienceBadge = isAdmin
                        ? `<span class="badge bg-secondary ms-2">${audienceLabel[p.audience] || 'Everyone'}</span>`
                        : '';

                    const adminControls = isAdmin ? `
                        <div class="dropdown ms-auto">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item btnEditAnnouncement" href="#" data-id="${p.announcement_id}"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item text-danger btnDeleteAnnouncement" href="#" data-id="${p.announcement_id}"><i class="bi bi-trash me-2"></i>Delete</a></li>
                            </ul>
                        </div>
                    ` : '';

                    const photos = p.photos || [];
                    let photoHtml = '';
                    if (photos.length === 1) {
                        photoHtml = `<div class="ps-4 mt-2 announcement-photo-wrap"><img src="${photoSrc(photos[0].photo)}" class="announcement-photo w-100 rounded" style="max-height:420px;object-fit:cover;cursor:pointer;"></div>`;
                    } else if (photos.length > 1) {
                        const tiles = photos.map(function (ph) {
                            return `<img src="${photoSrc(ph.photo)}" class="announcement-photo" style="width:100%;height:140px;object-fit:cover;border-radius:0.4rem;cursor:pointer;">`;
                        }).join('');
                        photoHtml = `<div class="ps-4 mt-2 announcement-photo-wrap" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:6px;">${tiles}</div>`;
                    }

                    const avatar = p.poster_photo
                        ? `<img src="assets/img/admins/${p.poster_photo}" class="rounded-circle" style="width:40px;height:40px;flex:none;object-fit:cover;">`
                        : `<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:40px;height:40px;flex:none;"><i class="bi bi-mortarboard-fill"></i></div>`;

                    html += `
                        <div class="announcement-post py-3">
                            <div class="d-flex align-items-center mb-2">
                                ${avatar}
                                <div class="ms-2">
                                    <div class="fw-bold">${esc(p.admin_name)}</div>
                                    <div class="text-muted small" title="${fullDateTime(p.created_at)}">${timeAgo(p.created_at)}</div>
                                </div>
                                ${audienceBadge}
                                ${adminControls}
                            </div>
                            <h6 class="fw-bold mb-1 ps-4">${esc(p.title)}</h6>
                            <div class="ps-4" style="white-space: pre-wrap;">${esc(p.content)}</div>
                            ${photoHtml}
                        </div>
                    `;
                });

                $('#announcementFeed').html(html);
            }
        });
    }

    renderFeed();

    if (!isAdmin) {
        return;
    }

    // ===========================================================
    // Admin: Add / Edit / Delete
    // ===========================================================
    function resetForm() {
        $('#announcementForm')[0].reset();
        $('#announcement_id').val('');
        $('#existingPhotosPreview, #newPhotosPreview').empty();
    }

    $('#btnAddAnnouncement').click(function () {
        resetForm();
        $('#announcementModal .modal-title').text('New Post');
        $('#announcementModal').modal('show');
    });

    $(document).on('change', '#photos', function () {
        $('#newPhotosPreview').empty();

        Array.from(this.files).forEach(function (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                $('#newPhotosPreview').append(`<img src="${e.target.result}" class="rounded" style="width:90px;height:90px;object-fit:cover;">`);
            };
            reader.readAsDataURL(file);
        });
    });

    function renderExistingPhotos(photos) {
        $('#existingPhotosPreview').empty();

        (photos || []).forEach(function (ph) {
            $('#existingPhotosPreview').append(`
                <div class="position-relative" data-photo-id="${ph.photo_id}">
                    <img src="${photoSrc(ph.photo)}" class="rounded" style="width:90px;height:90px;object-fit:cover;">
                    <button type="button" class="btn btn-danger btn-sm btnRemoveExistingPhoto"
                            data-id="${ph.photo_id}"
                            style="position:absolute;top:-6px;right:-6px;padding:0 6px;border-radius:50%;line-height:1.6;">&times;</button>
                </div>
            `);
        });
    }

    $(document).on('click', '.btnRemoveExistingPhoto', function () {
        const photo_id = $(this).data('id');
        const $wrap = $(this).closest('[data-photo-id]');

        $.ajax({
            url: 'config/announcement.php',
            type: 'POST',
            data: { action: 'delete_photo', photo_id: photo_id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }
                $wrap.remove();
            }
        });
    });

    $(document).on('click', '.btnEditAnnouncement', function (e) {
        e.preventDefault();
        const id = $(this).data('id');

        $.ajax({
            url: 'config/announcement.php',
            type: 'POST',
            data: { action: 'get', announcement_id: id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                const d = res.data;
                resetForm();
                $('#announcement_id').val(d.announcement_id);
                $('#title').val(d.title);
                $('#content').val(d.content);
                $('#audience').val(d.audience);
                renderExistingPhotos(d.photos);

                $('#announcementModal .modal-title').text('Edit Post');
                $('#announcementModal').modal('show');
            }
        });
    });

    $('#announcementForm').submit(function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'add');

        $.ajax({
            url: 'config/announcement.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                $('#announcementModal').modal('hide');
                document.activeElement.blur();
                renderFeed();
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: res.message, showConfirmButton: false, timer: 3000, timerProgressBar: true
                });
            }
        });
    });

    $(document).on('click', '.btnDeleteAnnouncement', function (e) {
        e.preventDefault();
        const id = $(this).data('id');

        Swal.fire({
            title: 'Delete this post?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'config/announcement.php',
                type: 'POST',
                data: { action: 'delete', announcement_id: id },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'error') {
                        Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                        return;
                    }

                    renderFeed();
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: 'Post Removed', showConfirmButton: false, timer: 3000, timerProgressBar: true
                    });
                }
            });
        });
    });

});
