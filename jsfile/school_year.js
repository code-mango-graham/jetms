$(document).ready(function () {
    let schoolYearTable;
    let activeCurriculum = null;

    function getSchoolYearModal() {
        const el = document.getElementById('schoolYearModal');
        if (!el) return null;
        return bootstrap.Modal.getOrCreateInstance(el);
    }

    function openSchoolYearModal() {
        const modal = getSchoolYearModal();
        if (modal) modal.show();
    }

    function closeSchoolYearModal() {
        const modal = getSchoolYearModal();
        if (modal) modal.hide();
    }

    function loadActiveCurriculum(done) {
        $.ajax({
            url: 'config/get_active_curriculum.php',
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                activeCurriculum = data || null;
                if (done) done();
            },
            error: function () {
                activeCurriculum = null;
                if (done) done();
            }
        });
    }

    function setCurriculumFields(curriculumId, curriculumName) {
        const id = curriculumId || (activeCurriculum ? activeCurriculum.curriculum_id : '');
        const name = curriculumName || (activeCurriculum ? activeCurriculum.curriculum_name : 'No active curriculum');

        $('#curriculum_id').val(id || '');
        $('#active_curriculum_text').text(name || 'No active curriculum');
    }

    $(document).off('click', '#btnAdd');
    $(document).off('submit', '#schoolYearForm');
    $(document).off('click', '.btnEdit');
    $(document).off('click', '.btnDeleteSchoolYear');
    $(document).off('click', '.btnViewSchoolYear');

    schoolYearTable = $('#schoolYearTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: {
            lengthMenu: 'Show _MENU_ entries'
        },
        ajax: {
            url: 'config/school_year_load.php',
            type: 'POST'
        },
        order: [[2, 'desc']],
        columns: [
            { data: 'schoolyear_name', className: 'text-center' },
            { data: 'curriculum_name', className: 'text-center' },
            { data: 'year_start', className: 'text-center' },
            { data: 'year_end', className: 'text-center' },
            {
                data: 'status',
                className: 'text-center',
                render: function (data) {
                    if (String(data) === '1') {
                        return '<span class="badge text-bg-success">Active</span>';
                    }
                    return '<span class="badge text-bg-secondary">Inactive</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary btnViewSchoolYear" data-id="${data.schoolyear_id}" title="View School Year">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-secondary btnEdit" data-id="${data.schoolyear_id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btnDeleteSchoolYear" data-id="${data.schoolyear_id}" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    $('#btnAdd').on('click', function () {
        $('#schoolYearForm')[0].reset();
        $('#schoolyear_id').val('');
        $('.modal-title').text('Add School Year');

        loadActiveCurriculum(function () {
            setCurriculumFields('', '');
            openSchoolYearModal();
        });
    });

    $(document).on('click', '.btnEdit', function () {
        const schoolyear_id = $(this).data('id');

        $.ajax({
            url: 'config/school_year_get.php',
            type: 'POST',
            data: { schoolyear_id: schoolyear_id },
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

                $('#schoolyear_id').val(data.schoolyear_id || '');
                $('#schoolyear_name').val(data.schoolyear_name || '');
                $('#year_start').val(data.year_start || '');
                $('#year_end').val(data.year_end || '');
                $('#status').val(data.status || '0');
                setCurriculumFields(data.curriculum_id, data.curriculum_name);

                $('.modal-title').text('Edit School Year');
                openSchoolYearModal();
            }
        });
    });

    $('#schoolYearForm').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/school_year_add.php',
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

                closeSchoolYearModal();
                document.activeElement.blur();
                schoolYearTable.ajax.reload(null, false);

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

    $(document).on('click', '.btnDeleteSchoolYear', function () {
        const schoolyear_id = $(this).data('id');

        Swal.fire({
            title: 'Delete School Year?',
            text: 'This will remove the school year record.',
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
                url: 'config/school_year_delete.php',
                type: 'POST',
                data: { schoolyear_id: schoolyear_id },
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

                    schoolYearTable.ajax.reload(null, false);

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

    $(document).on('click', '.btnViewSchoolYear', function () {
        const schoolyear_id = $(this).data('id');

        $('#content_level').empty();
        $('#content_level').load('pages/school_year_view.html', function () {
            $('#view_schoolyear_id').val(schoolyear_id);
            if (typeof window.initSchoolYearView === 'function') {
                window.initSchoolYearView(schoolyear_id);
            }
        });

        $('html, body').animate({
            scrollTop: $('#content_level').offset().top
        }, 100);
    });

    loadActiveCurriculum(function () {
        setCurriculumFields('', '');
    });
});
