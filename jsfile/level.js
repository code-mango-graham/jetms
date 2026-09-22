$(document).ready(function () {

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddLevel');
    $(document).off('submit', '#levelForm');
    $(document).off('click', '.btnEditLevel');
    $(document).off('click', '.btnDeleteLevel');
    $(document).off('click', '.btnManageLevel');
    $(document).off('submit', '#sectionForm');
    $(document).off('submit', '#subjectForm');
    $(document).off('click', '.btnDeleteSection');
    $(document).off('click', '.btnDeleteSubject');

    if ($.fn.DataTable.isDataTable('#levelTable')) {
        $('#levelTable').DataTable().destroy();
        $('#levelTable tbody').empty();
    }

    let table = $('#levelTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: {
            lengthMenu: "Show _MENU_ entries"
        },
        ajax: {
            url: 'config/level.php',
            type: 'POST',
            data: function (d) {
                d.action = 'load';
                return d;
            }
        },
        order: [[1, 'asc']],
        columns: [
            { data: 'level_name' },
            { data: 'level_order' },
            {
                data: 'allows_subject_selection',
                className: 'text-center',
                render: function (data) {
                    return data == 1
                        ? '<span class="badge bg-info">Selectable</span>'
                        : '<span class="badge bg-secondary">Fixed</span>';
                }
            },
            { data: 'section_count', className: 'text-center' },
            { data: 'subject_count', className: 'text-center' },
            {
                data: null,
                className: 'text-center',
                render: function (data) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary btn-sm btnManageLevel" data-id="${data.level_id}" data-name="${data.level_name}" title="Manage Sections & Subjects">
                                <i class="bi bi-diagram-3"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm btnEditLevel" data-id="${data.level_id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm btnDeleteLevel" data-id="${data.level_id}" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    function resetLevelForm() {
        $('#levelForm')[0].reset();
        $('#level_id').val('');
    }

    $('#btnAddLevel').click(function () {
        resetLevelForm();
        $('#levelModal .modal-title').text('Add Level');
        $('#levelModal').modal('show');
    });

    $(document).on('click', '.btnEditLevel', function () {
        let level_id = $(this).data('id');
        $.ajax({
            url: 'config/level.php',
            type: 'POST',
            data: {
                action: 'get',
                level_id: level_id
            },
            dataType: 'json',
            success: function (data) {
                $('#level_id').val(data.level_id);
                $('#level_name').val(data.level_name);
                $('#level_order').val(data.level_order);
                $('#allows_subject_selection').prop('checked', data.allows_subject_selection == 1);
                $('#levelModal .modal-title').text('Edit Level');

                $('#levelModal').modal('show');
            }
        });
    });

    $('#levelForm').submit(function (e) {
        e.preventDefault();

        const payload = {
            action: 'add',
            level_id: $('#level_id').val(),
            level_name: $('#level_name').val(),
            level_order: $('#level_order').val(),
            allows_subject_selection: $('#allows_subject_selection').is(':checked') ? '1' : '0'
        };

        $.ajax({
            url: 'config/level.php',
            type: 'POST',
            data: payload,
            dataType: 'json',
            success: function (res) {
                if (res.status === "error") {
                    Swal.fire({
                        icon: 'error',
                        title: res.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                $('#levelModal').modal('hide');
                document.activeElement.blur();
                $('#levelTable').DataTable().ajax.reload(null, false);
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

    $(document).on('click', '.btnDeleteLevel', function () {
        let level_id = $(this).data('id');

        Swal.fire({
            title: 'Delete Level?',
            text: 'Are you sure you want to delete this level?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'config/level.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        level_id: level_id
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === "error") {
                            Swal.fire({
                                icon: 'error',
                                title: res.message,
                                timer: 3000,
                                showConfirmButton: false
                            });
                            return;
                        }

                        document.activeElement.blur();
                        $('#levelTable').DataTable().ajax.reload(null, false);
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
            }
        });
    });

    // ===========================================================
    // Manage Sections & Subjects (nested inside the level modal)
    // ===========================================================

    function loadSections(level_id) {
        $.ajax({
            url: 'config/section.php',
            type: 'POST',
            data: { action: 'load', level_id: level_id },
            dataType: 'json',
            success: function (res) {
                let rows = '';
                (res.data || []).forEach(function (row) {
                    rows += `
                        <tr>
                            <td>${row.section_name}</td>
                            <td class="text-center">
                                <button class="btn btn-outline-danger btn-sm btnDeleteSection" data-id="${row.section_id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                if (!rows) {
                    rows = '<tr><td colspan="2" class="text-center text-muted">No sections yet.</td></tr>';
                }
                $('#sectionTable tbody').html(rows);
            }
        });
    }

    function loadSubjects(level_id) {
        $.ajax({
            url: 'config/subject.php',
            type: 'POST',
            data: { action: 'load', level_id: level_id },
            dataType: 'json',
            success: function (res) {
                let rows = '';
                (res.data || []).forEach(function (row) {
                    rows += `
                        <tr>
                            <td>${row.subject_name}</td>
                            <td>${row.subject_code || '-'}</td>
                            <td class="text-center">
                                <button class="btn btn-outline-danger btn-sm btnDeleteSubject" data-id="${row.subject_id}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                if (!rows) {
                    rows = '<tr><td colspan="3" class="text-center text-muted">No subjects yet.</td></tr>';
                }
                $('#subjectTable tbody').html(rows);
            }
        });
    }

    $(document).on('click', '.btnManageLevel', function () {
        const level_id = $(this).data('id');
        const level_name = $(this).data('name');

        $('#manage_level_id').val(level_id);
        $('#manageLevelName').text(level_name);
        $('#sectionForm')[0].reset();
        $('#subjectForm')[0].reset();

        loadSections(level_id);
        loadSubjects(level_id);

        $('#manageLevelModal').modal('show');
    });

    $('#sectionForm').submit(function (e) {
        e.preventDefault();

        const level_id = $('#manage_level_id').val();
        const section_name = $('#section_name').val();

        $.ajax({
            url: 'config/section.php',
            type: 'POST',
            data: { action: 'add', level_id: level_id, section_name: section_name },
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

                $('#sectionForm')[0].reset();
                loadSections(level_id);
                $('#levelTable').DataTable().ajax.reload(null, false);
            }
        });
    });

    $(document).on('click', '.btnDeleteSection', function () {
        const section_id = $(this).data('id');
        const level_id = $('#manage_level_id').val();

        $.ajax({
            url: 'config/section.php',
            type: 'POST',
            data: { action: 'delete', section_id: section_id },
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

                loadSections(level_id);
                $('#levelTable').DataTable().ajax.reload(null, false);
            }
        });
    });

    $('#subjectForm').submit(function (e) {
        e.preventDefault();

        const level_id = $('#manage_level_id').val();
        const subject_name = $('#subject_name').val();
        const subject_code = $('#subject_code').val();

        $.ajax({
            url: 'config/subject.php',
            type: 'POST',
            data: { action: 'add', level_id: level_id, subject_name: subject_name, subject_code: subject_code },
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

                $('#subjectForm')[0].reset();
                loadSubjects(level_id);
                $('#levelTable').DataTable().ajax.reload(null, false);
            }
        });
    });

    $(document).on('click', '.btnDeleteSubject', function () {
        const subject_id = $(this).data('id');
        const level_id = $('#manage_level_id').val();

        $.ajax({
            url: 'config/subject.php',
            type: 'POST',
            data: { action: 'delete', subject_id: subject_id },
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

                loadSubjects(level_id);
                $('#levelTable').DataTable().ajax.reload(null, false);
            }
        });
    });

});
