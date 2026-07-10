let sectionsTable;
let subjectsTable;
let currentLevelId;

function getSectionModal() {
    const el = document.getElementById('sectionModal');
    if (!el) return null;
    return bootstrap.Modal.getOrCreateInstance(el);
}

function openSectionModal() {
    const modal = getSectionModal();
    if (modal) modal.show();
}

function closeSectionModal() {
    const modal = getSectionModal();
    if (modal) modal.hide();
}

function getSubjectModal() {
    const el = document.getElementById('subjectModal');
    if (!el) return null;
    return bootstrap.Modal.getOrCreateInstance(el);
}

function openSubjectModal() {
    const modal = getSubjectModal();
    if (modal) modal.show();
}

function closeSubjectModal() {
    const modal = getSubjectModal();
    if (modal) modal.hide();
}

function initLevelView(levelId) {
    currentLevelId = levelId;

    // Initialize sections table
    if ($.fn.DataTable.isDataTable('#sectionsTable')) {
        sectionsTable = $('#sectionsTable').DataTable();
        sectionsTable.ajax.reload();
    } else {
        sectionsTable = $('#sectionsTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: {
                lengthMenu: 'Show _MENU_ entries'
            },
            ajax: {
                url: 'config/section_load.php',
                type: 'POST',
                data: function () {
                    return { level_id: currentLevelId };
                }
            },
            columns: [
                { data: 'section_name', className: 'text-center' },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btnEditSection" data-id="${data.section_id}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btnDeleteSection" data-id="${data.section_id}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

    // Initialize subjects table
    if ($.fn.DataTable.isDataTable('#subjectsTable')) {
        subjectsTable = $('#subjectsTable').DataTable();
        subjectsTable.ajax.reload();
    } else {
        subjectsTable = $('#subjectsTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: {
                lengthMenu: 'Show _MENU_ entries'
            },
            ajax: {
                url: 'config/subject_load.php',
                type: 'POST',
                data: function () {
                    return { level_id: currentLevelId };
                }
            },
            columns: [
                { data: 'subject_name', className: 'text-left' },
                { data: 'subject_code', className: 'text-center' },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btnEditSubject" data-id="${data.subject_id}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btnDeleteSubject" data-id="${data.subject_id}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

    // Set level name
    $('#view_level_name_subjects').text($('#view_level_name').text());

    // Event handlers for sections
    $(document).off('click', '#btnAddSection');
    $(document).off('submit', '#sectionForm');
    $(document).off('click', '.btnEditSection');
    $(document).off('click', '.btnDeleteSection');
    $(document).off('click', '#btnBackLevel');
    
    // Event handlers for subjects
    $(document).off('click', '#btnAddSubject');
    $(document).off('submit', '#subjectForm');
    $(document).off('click', '.btnEditSubject');
    $(document).off('click', '.btnDeleteSubject');

    // SECTION HANDLERS
    $('#btnAddSection').on('click', function () {
        $('#sectionForm')[0].reset();
        $('#section_id').val('');
        $('#level_id_hidden').val(currentLevelId);
        $('#sectionModal .modal-title').text('Add Section');
        openSectionModal();
    });

    $(document).on('submit', '#sectionForm', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/section_add.php',
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

                closeSectionModal();
                document.activeElement.blur();
                sectionsTable.ajax.reload(null, false);

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

    $(document).on('click', '.btnEditSection', function () {
        const section_id = $(this).data('id');

        $.ajax({
            url: 'config/section_get.php',
            type: 'POST',
            data: { section_id: section_id },
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

                $('#section_id').val(data.section_id || '');
                $('#section_name').val(data.section_name || '');
                $('#level_id_hidden').val(data.level_id || '');
                $('#sectionModal .modal-title').text('Edit Section');
                openSectionModal();
            }
        });
    });

    $(document).on('click', '.btnDeleteSection', function () {
        const section_id = $(this).data('id');

        Swal.fire({
            title: 'Delete Section?',
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
                url: 'config/section_delete.php',
                type: 'POST',
                data: { section_id: section_id },
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

                    sectionsTable.ajax.reload(null, false);

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

    // SUBJECT HANDLERS
    $('#btnAddSubject').on('click', function () {
        $('#subjectForm')[0].reset();
        $('#subject_id').val('');
        $('#level_id_subject').val(currentLevelId);
        $('#subjectModal .modal-title').text('Add Subject');
        openSubjectModal();
    });

    $(document).on('submit', '#subjectForm', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/subject_add.php',
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

                closeSubjectModal();
                document.activeElement.blur();
                subjectsTable.ajax.reload(null, false);

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

    $(document).on('click', '.btnEditSubject', function () {
        const subject_id = $(this).data('id');

        $.ajax({
            url: 'config/subject_get.php',
            type: 'POST',
            data: { subject_id: subject_id },
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

                $('#subject_id').val(data.subject_id || '');
                $('#subject_name').val(data.subject_name || '');
                $('#subject_code').val(data.subject_code || '');
                $('#level_id_subject').val(data.level_id || '');
                $('#subjectModal .modal-title').text('Edit Subject');
                openSubjectModal();
            }
        });
    });

    $(document).on('click', '.btnDeleteSubject', function () {
        const subject_id = $(this).data('id');

        Swal.fire({
            title: 'Delete Subject?',
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
                url: 'config/subject_delete.php',
                type: 'POST',
                data: { subject_id: subject_id },
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

                    subjectsTable.ajax.reload(null, false);

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

    $('#btnBackLevel').on('click', function () {
        $('#content_level').empty();
        $('html, body').animate({ scrollTop: 0 }, 100);
    });
}

$(document).ready(function () {
    // This will be called by level.js
});
