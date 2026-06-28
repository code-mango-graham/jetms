window.initSchoolYearView = function (schoolyearId) {
    console.log('initSchoolYearView called with ID:', schoolyearId);
    
    // Check if required elements exist
    if (!$('#syLevelsTable').length) {
        console.error('Required HTML elements not found!');
        return;
    }
    
    // Check if required libraries are available
    if (typeof $ === 'undefined') {
        console.error('jQuery is not available');
        return;
    }
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables is not available');
        return;
    }
    
    let levelsTable;
    let sectionsTable;
    let assignTable;
    let teacherOptions = [];

    function levelTypeLabel(typeValue) {
        const type = parseInt(typeValue, 10);
        if (type === 1) return 'Kindergarten';
        if (type === 2) return 'Primary Education';
        if (type === 3) return 'Junior High School';
        if (type === 4) return 'Senior High School';
        return 'Unknown';
    }

    function getSectionModal() {
        const el = document.getElementById('sySectionModal');
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

    function teacherSelectHtml(selectedId, subjectId) {
        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        let html = `<select class="form-select form-select-sm assign-teacher" data-subject-id="${subjectId}">`;
        html += '<option value="">-- Unassigned --</option>';

        teacherOptions.forEach(function (teacher) {
            const fullName = `${teacher.last_name}, ${teacher.first_name}${teacher.middle_name ? ' ' + teacher.middle_name : ''}`;
            const selected = String(selectedId || '') === String(teacher.teacher_id) ? 'selected' : '';
            html += `<option value="${teacher.teacher_id}" ${selected}>${escapeHtml(fullName)}</option>`;
        });

        html += '</select>';
        return html;
    }

    function reloadAssignments() {
        if (assignTable) {
            assignTable.ajax.reload(null, false);
        }
    }

    function loadTeachers(callback) {
        $.ajax({
            url: 'config/teacher_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                teacherOptions = res.data || [];
                console.log('Teachers loaded:', teacherOptions.length);
                callback();
            },
            error: function (xhr, status, error) {
                console.error('Error loading teachers:', error);
                teacherOptions = [];
                callback();
            }
        });
    }

    function initLevelsTable() {
        if ($.fn.DataTable.isDataTable('#syLevelsTable')) {
            $('#syLevelsTable').DataTable().destroy();
        }

        try {
            levelsTable = $('#syLevelsTable').DataTable({
                processing: true,
                responsive: true,
                scrollX: true,
                paging: false,
                searching: false,
                info: false,
                ajax: {
                    url: 'config/school_year_levels.php',
                    type: 'POST',
                    data: function () {
                        return { schoolyear_id: $('#view_schoolyear_id').val() };
                    },
                    dataSrc: function (json) {
                        console.log('Levels loaded:', json);
                        $('#view_schoolyear_name').text(json.schoolyear_name || '-');
                        $('#view_curriculum_name').text(json.curriculum_name || 'Not linked');
                        $('#view_curriculum_id').val(json.curriculum_id || '');
                        console.log('Returning data:', json.data || []);
                        return json.data || [];
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                    }
                },
            columns: [
                { data: 'level_name', render: function(data) { console.log('Rendering level_name:', data); return data; } },
                {
                    data: 'level_type',
                    className: 'text-center',
                    render: function (data) {
                        console.log('Rendering level_type:', data);
                        return levelTypeLabel(data);
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (row) {
                        console.log('Rendering button for row:', row);
                        return `
                            <button class="btn btn-outline-primary btn-sm btnSelectSyLevel"
                                data-id="${row.level_id}"
                                data-name="${row.level_name}">
                                Select
                            </button>
                        `;
                    }
                }
            ]
        });
        } catch (e) {
            console.error('Error initializing levels table:', e);
        }
    }

    function initSectionsTable() {
        if ($.fn.DataTable.isDataTable('#sySectionsTable')) {
            $('#sySectionsTable').DataTable().destroy();
        }

        sectionsTable = $('#sySectionsTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            paging: false,
            searching: false,
            info: false,
            ajax: {
                url: 'config/sy_section_load.php',
                type: 'POST',
                data: function () {
                    return {
                        schoolyear_id: $('#view_schoolyear_id').val(),
                        level_id: $('#view_level_id').val()
                    };
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'section_name' },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (row) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btnSelectSySection" data-id="${row.schoolyear_section_id}" data-name="${row.section_name}">Select</button>
                                <button class="btn btn-outline-secondary btnEditSySection" data-id="${row.schoolyear_section_id}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btnDeleteSySection" data-id="${row.schoolyear_section_id}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

    function initAssignTable() {
        if ($.fn.DataTable.isDataTable('#sySubjectAssignTable')) {
            $('#sySubjectAssignTable').DataTable().destroy();
        }

        assignTable = $('#sySubjectAssignTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            paging: false,
            searching: false,
            info: false,
            ajax: {
                url: 'config/sy_subject_assignment_load.php',
                type: 'POST',
                data: function () {
                    return {
                        schoolyear_section_id: $('#view_schoolyear_section_id').val(),
                        level_id: $('#view_level_id').val()
                    };
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'subject_code', className: 'text-center' },
                { data: 'subject_name' },
                {
                    data: null,
                    orderable: false,
                    render: function (row) {
                        return teacherSelectHtml(row.teacher_id, row.subject_id);
                    }
                },
                {
                    data: 'schedule_info',
                    orderable: false,
                    render: function (value, type, row) {
                        const safeValue = String(value || '')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;');
                        return `<input type="text" class="form-control form-control-sm assign-schedule" data-subject-id="${row.subject_id}" value="${safeValue}" placeholder="e.g. Mon-Fri 8:00-9:00">`;
                    }
                },
                {
                    data: 'room_no',
                    orderable: false,
                    render: function (value, type, row) {
                        const safeValue = String(value || '')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;');
                        return `<input type="text" class="form-control form-control-sm assign-room" data-subject-id="${row.subject_id}" value="${safeValue}" placeholder="Room #">`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (row) {
                        return `<button class="btn btn-success btn-sm btnSaveSubjectAssign" data-subject-id="${row.subject_id}">Save</button>`;
                    }
                }
            ]
        });
    }

    $('#view_schoolyear_id').val(schoolyearId);
    $('#view_level_id').val('');
    $('#view_schoolyear_section_id').val('');
    $('#view_active_path').text('Select level first');
    $('#view_selected_level').text('Select a level');
    $('#view_selected_section').text('Select a section');
    $('#btnAddSySection').prop('disabled', true);

    $(document).off('click', '#btnCloseSchoolYearView');
    $(document).off('click', '.btnSelectSyLevel');
    $(document).off('click', '#btnAddSySection');
    $(document).off('submit', '#sySectionForm');
    $(document).off('click', '.btnEditSySection');
    $(document).off('click', '.btnDeleteSySection');
    $(document).off('click', '.btnSelectSySection');
    $(document).off('click', '.btnSaveSubjectAssign');

    $(document).on('click', '#btnCloseSchoolYearView', function () {
        $('#content_level').empty();
    });

    $(document).on('click', '.btnSelectSyLevel', function () {
        const levelId = $(this).data('id');
        const levelName = $(this).data('name');

        $('#view_level_id').val(levelId);
        $('#view_schoolyear_section_id').val('');
        $('#btnAddSySection').prop('disabled', false);
        $('#view_active_path').text(`${levelName} / Select section`);
        $('#view_selected_level').text(levelName);
        $('#view_selected_section').text('Select a section');

        if (sectionsTable) {
            sectionsTable.ajax.reload(null, false);
        }
        if (assignTable) {
            assignTable.clear().draw();
        }
    });

    $(document).on('click', '#btnAddSySection', function () {
        $('#sySectionForm')[0].reset();
        $('#schoolyear_section_id').val('');
        $('#sySectionModal .modal-title').text('Add Section');
        openSectionModal();
    });

    $(document).on('submit', '#sySectionForm', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/sy_section_add.php',
            type: 'POST',
            data: $(this).serialize() +
                '&schoolyear_id=' + encodeURIComponent($('#view_schoolyear_id').val()) +
                '&level_id=' + encodeURIComponent($('#view_level_id').val()),
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
                if (sectionsTable) {
                    sectionsTable.ajax.reload(null, false);
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Saved Successfully',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            }
        });
    });

    $(document).on('click', '.btnEditSySection', function () {
        const sectionId = $(this).data('id');

        $.ajax({
            url: 'config/sy_section_get.php',
            type: 'POST',
            data: { schoolyear_section_id: sectionId },
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

                $('#schoolyear_section_id').val(data.schoolyear_section_id || '');
                $('#sy_section_name').val(data.section_name || '');
                $('#sySectionModal .modal-title').text('Edit Section');
                openSectionModal();
            }
        });
    });

    $(document).on('click', '.btnDeleteSySection', function () {
        const sectionId = $(this).data('id');

        Swal.fire({
            title: 'Delete Section?',
            text: 'This section will be removed for the selected school year.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'config/sy_section_delete.php',
                type: 'POST',
                data: { schoolyear_section_id: sectionId },
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

                    if (String($('#view_schoolyear_section_id').val()) === String(sectionId)) {
                        $('#view_schoolyear_section_id').val('');
                        if (assignTable) {
                            assignTable.clear().draw();
                        }
                    }

                    if (sectionsTable) {
                        sectionsTable.ajax.reload(null, false);
                    }

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Deleted Successfully',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                }
            });
        });
    });

    $(document).on('click', '.btnSelectSySection', function () {
        const sectionId = $(this).data('id');
        const sectionName = $(this).data('name');

        $('#view_schoolyear_section_id').val(sectionId);
        $('#view_selected_section').text(sectionName);

        const currentText = $('#view_active_path').text();
        const levelLabel = currentText.includes('/') ? currentText.split('/')[0].trim() : currentText;
        $('#view_active_path').text(`${levelLabel} / ${sectionName}`);

        reloadAssignments();
    });

    $(document).on('click', '.btnSaveSubjectAssign', function () {
        const subjectId = $(this).data('subject-id');
        const sectionId = $('#view_schoolyear_section_id').val();
        const row = $(this).closest('tr');

        if (!sectionId) {
            Swal.fire({
                icon: 'warning',
                title: 'Select a section first',
                timer: 2000,
                showConfirmButton: false
            });
            return;
        }

        const teacherId = row.find('.assign-teacher').val() || '';
        const scheduleInfo = row.find('.assign-schedule').val() || '';
        const roomNo = row.find('.assign-room').val() || '';

        $.ajax({
            url: 'config/sy_subject_assignment_save.php',
            type: 'POST',
            data: {
                schoolyear_section_id: sectionId,
                subject_id: subjectId,
                teacher_id: teacherId,
                schedule_info: scheduleInfo,
                room_no: roomNo
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

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Assignment Saved',
                    showConfirmButton: false,
                    timer: 1500,
                    timerProgressBar: true
                });
            }
        });
    });

    loadTeachers(function () {
        console.log('Starting table initialization...');
        initLevelsTable();
        initSectionsTable();
        initAssignTable();
        console.log('Table initialization complete');
    });
};
