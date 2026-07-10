$(document).ready(function () {
    let levelTable;

    function getLevelModal() {
        const el = document.getElementById('levelModal');
        if (!el) return null;
        return bootstrap.Modal.getOrCreateInstance(el);
    }

    function openLevelModal() {
        const modal = getLevelModal();
        if (modal) modal.show();
    }

    function closeLevelModal() {
        const modal = getLevelModal();
        if (modal) modal.hide();
    }

    levelTable = $('#levelTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: {
            lengthMenu: 'Show _MENU_ entries'
        },
        ajax: {
            url: 'config/level_load.php',
            type: 'POST'
        },
        order: [[0, 'asc']],
        columns: [
            { data: 'level_name', className: 'text-center' },
            { data: 'level_type', className: 'text-center' },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info btnViewLevel" data-id="${data.level_id}" title="View/Manage Sections & Subjects">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary btnEdit" data-id="${data.level_id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btnDelete" data-id="${data.level_id}" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    $('#btnAdd').on('click', function () {
        $('#levelForm')[0].reset();
        $('#level_id').val('');
        $('.modal-title').text('Add Level');
        openLevelModal();
    });

    $(document).on('click', '.btnEdit', function () {
        const level_id = $(this).data('id');

        $.ajax({
            url: 'config/level_get.php',
            type: 'POST',
            data: { level_id: level_id },
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

                $('#level_id').val(data.level_id || '');
                $('#level_name').val(data.level_name || '');
                $('#level_type').val(data.level_type || '');

                $('.modal-title').text('Edit Level');
                openLevelModal();
            }
        });
    });

    $('#levelForm').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/level_add.php',
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

                closeLevelModal();
                document.activeElement.blur();
                levelTable.ajax.reload(null, false);

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

    $(document).on('click', '.btnDelete', function () {
        const level_id = $(this).data('id');

        Swal.fire({
            title: 'Delete Level?',
            text: 'This will remove the level record.',
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
                url: 'config/level_delete.php',
                type: 'POST',
                data: { level_id: level_id },
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

                    levelTable.ajax.reload(null, false);

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

    $(document).on('click', '.btnViewLevel', function () {
        const level_id = $(this).data('id');

        $.ajax({
            url: 'config/level_get.php',
            type: 'POST',
            data: { level_id: level_id },
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

                $('#content_level').empty();
                $('#content_level').load('pages/level_view.html', function () {
                    $('#view_level_name').text(data.level_name);
                    $('#level_id_hidden').val(level_id);
                    $('#level_id_subject').val(level_id);
                    
                    $.getScript('jsfile/level_view.js', function() {
                        if (typeof window.initLevelView === 'function') {
                            window.initLevelView(level_id);
                        }
                    });
                });

                $('html, body').animate({
                    scrollTop: $('#content_level').offset().top
                }, 100);
            }
        });
    });

});
