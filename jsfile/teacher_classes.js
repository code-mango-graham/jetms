$(document).ready(function () {

    $(document).off('click', '.btnManageClass');
    $(document).off('click', '#btnBackToClasses');
    $(document).off('click', '#quarterTabs a');
    $(document).off('click', '#btnAddComponent');
    $(document).off('submit', '#componentForm');
    $(document).off('click', '.btnDeleteComponent');
    $(document).off('change', '.gradebook-score-input');
    $(document).off('change', '.gradebook-final-input');

    let currentClassId = null;
    let currentQuarter = 'Q1';

    if ($.fn.DataTable.isDataTable('#myClassesTable')) {
        $('#myClassesTable').DataTable().destroy();
        $('#myClassesTable tbody').empty();
    }

    let classesTable = $('#myClassesTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No classes assigned yet." },
        ajax: {
            url: 'config/assignment.php',
            type: 'POST',
            data: function (d) { d.action = 'teacher_classes'; return d; }
        },
        columns: [
            { data: 'schoolyear_name' },
            { data: 'level_name' },
            { data: 'section_name' },
            {
                data: null,
                render: function (d) {
                    return d.subject_name + (d.subject_code ? ` <span class="text-muted">(${d.subject_code})</span>` : '');
                }
            },
            { data: 'student_count', className: 'text-center' },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (d) {
                    return `<button class="btn btn-outline-primary btn-sm btnManageClass" data-id="${d.class_id}" data-label="${d.level_name} - ${d.section_name} - ${d.subject_name}">
                        <i class="bi bi-journal-check me-1"></i>Manage
                    </button>`;
                }
            }
        ]
    });

    // ===========================================================
    // Show / hide class detail
    // ===========================================================
    $(document).on('click', '.btnManageClass', function () {
        currentClassId = $(this).data('id');
        currentQuarter = 'Q1';

        $('#classDetailTitle').text($(this).data('label'));
        $('#quarterTabs a').removeClass('active');
        $('#quarterTabs a[data-quarter="Q1"]').addClass('active');

        $('#classListCard').addClass('d-none');
        $('#classDetailCard').removeClass('d-none');

        loadComponentsAndGradebook();
    });

    $('#btnBackToClasses').click(function () {
        $('#classDetailCard').addClass('d-none');
        $('#classListCard').removeClass('d-none');
        classesTable.ajax.reload(null, false);
    });

    $(document).on('click', '#quarterTabs a', function (e) {
        e.preventDefault();
        currentQuarter = $(this).data('quarter');
        $('#quarterTabs a').removeClass('active');
        $(this).addClass('active');
        loadComponentsAndGradebook();
    });

    // ===========================================================
    // Components
    // ===========================================================
    function renderComponentChips(components) {
        let html = '';
        if (components.length === 0) {
            html = '<span class="text-muted">No components yet for this quarter.</span>';
        }
        components.forEach(function (c) {
            const icon = c.component_type === 'quiz' ? 'bi-question-circle' : (c.component_type === 'exam' ? 'bi-file-earmark-text' : 'bi-pencil-square');
            html += `<span class="badge bg-secondary me-1 mb-1" style="font-size:0.85rem;">
                <i class="bi ${icon} me-1"></i>${c.title} (/${parseFloat(c.max_score)})
                <a href="#" class="text-white btnDeleteComponent ms-1" data-id="${c.component_id}" title="Remove"><i class="bi bi-x-circle"></i></a>
            </span>`;
        });
        $('#componentChips').html(html);
    }

    function renderGradebook(components, roster) {
        let headerRow = '<th>Student</th>';
        components.forEach(function (c) {
            headerRow += `<th class="text-center">${c.title}<br><small class="text-muted">/${parseFloat(c.max_score)}</small></th>`;
        });
        headerRow += '<th width="120">Final Grade</th>';
        $('#gradebookHeaderRow').html(headerRow);

        if (roster.length === 0) {
            $('#gradebookBody').html(`<tr><td colspan="${components.length + 2}" class="text-center text-muted">No students enrolled in this class yet.</td></tr>`);
            return;
        }

        const statusBadge = {
            dropped: '<span class="badge bg-danger ms-1" style="font-size:0.65rem;">Dropped</span>',
            transferred: '<span class="badge bg-warning ms-1" style="font-size:0.65rem;">Transferred</span>',
            completed: '<span class="badge bg-secondary ms-1" style="font-size:0.65rem;">Completed</span>'
        };

        let body = '';
        roster.forEach(function (student) {
            const badge = statusBadge[student.enrollment_status] || '';
            body += `<tr><td>${student.last_name}, ${student.first_name}${badge}</td>`;
            components.forEach(function (c) {
                const val = student.scores && student.scores[c.component_id] !== null && student.scores[c.component_id] !== undefined ? student.scores[c.component_id] : '';
                body += `<td><input type="number" class="form-control form-control-sm gradebook-score-input" style="width:80px;" min="0" max="${c.max_score}" step="0.01"
                    data-component-id="${c.component_id}" data-student-id="${student.student_id}" data-original="${val}" value="${val}"></td>`;
            });
            const finalVal = student.final_grade !== null && student.final_grade !== undefined ? student.final_grade : '';
            body += `<td><input type="number" class="form-control form-control-sm gradebook-final-input" style="width:90px;" min="0" max="100" step="0.01"
                data-student-id="${student.student_id}" data-original="${finalVal}" value="${finalVal}"></td></tr>`;
        });
        $('#gradebookBody').html(body);
    }

    function loadComponentsAndGradebook() {
        $('#gradebookBody').html('<tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: 'config/grading.php',
            type: 'POST',
            data: { action: 'components', class_id: currentClassId, quarter: currentQuarter },
            dataType: 'json',
            success: function (res) {
                renderComponentChips(res.data || []);
            }
        });

        $.ajax({
            url: 'config/grading.php',
            type: 'POST',
            data: { action: 'scores', class_id: currentClassId, quarter: currentQuarter },
            dataType: 'json',
            success: function (res) {
                renderGradebook(res.components || [], res.roster || []);
            }
        });
    }

    $('#btnAddComponent').click(function () {
        $('#componentForm')[0].reset();
        $('#componentModal').modal('show');
    });

    $('#componentForm').submit(function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/grading.php',
            type: 'POST',
            data: {
                action: 'component_add',
                class_id: currentClassId,
                quarter: currentQuarter,
                component_type: $('#component_type').val(),
                title: $('#component_title').val(),
                max_score: $('#component_max_score').val(),
                date_given: $('#component_date').val()
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }
                $('#componentModal').modal('hide');
                document.activeElement.blur();
                loadComponentsAndGradebook();
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Component added', showConfirmButton: false, timer: 2000 });
            }
        });
    });

    $(document).on('click', '.btnDeleteComponent', function (e) {
        e.preventDefault();
        const component_id = $(this).data('id');

        Swal.fire({
            title: 'Remove this component?',
            text: 'All recorded scores for it will also be hidden.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Remove'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'config/grading.php',
                    type: 'POST',
                    data: { action: 'component_delete', component_id: component_id },
                    dataType: 'json',
                    success: function () {
                        loadComponentsAndGradebook();
                    }
                });
            }
        });
    });

    // ===========================================================
    // Inline score / final grade saving — changing an already-recorded
    // value requires a reason (prompted here, enforced again server-side)
    // ===========================================================
    function isRealEdit($input) {
        const original = $input.data('original');
        const originalStr = (original === undefined || original === null) ? '' : String(original);
        const newStr = $input.val();
        return originalStr !== '' && originalStr !== newStr;
    }

    function askEditReason() {
        return Swal.fire({
            title: 'Reason for this change',
            input: 'textarea',
            inputPlaceholder: 'Required — why is this score/grade being changed?',
            inputValidator: function (value) {
                if (!value || !value.trim()) {
                    return 'A reason is required to change an already-recorded value';
                }
            },
            showCancelButton: true,
            confirmButtonText: 'Save Change',
            cancelButtonText: 'Cancel'
        });
    }

    function saveScore($input, reason) {
        $.ajax({
            url: 'config/grading.php',
            type: 'POST',
            data: {
                action: 'score_save',
                component_id: $input.data('component-id'),
                student_id: $input.data('student-id'),
                score: $input.val(),
                reason: reason || ''
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    $input.val($input.data('original'));
                    return;
                }
                $input.data('original', $input.val());
                $input.addClass('is-valid');
                setTimeout(function () { $input.removeClass('is-valid'); }, 1000);
            }
        });
    }

    function saveFinalGrade($input, reason) {
        $.ajax({
            url: 'config/grading.php',
            type: 'POST',
            data: {
                action: 'final_grade_save',
                class_id: currentClassId,
                quarter: currentQuarter,
                student_id: $input.data('student-id'),
                final_grade: $input.val(),
                reason: reason || ''
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    $input.val($input.data('original'));
                    return;
                }
                $input.data('original', $input.val());
                $input.addClass('is-valid');
                setTimeout(function () { $input.removeClass('is-valid'); }, 1000);
            }
        });
    }

    $(document).on('change', '.gradebook-score-input', function () {
        const $input = $(this);
        if (isRealEdit($input)) {
            askEditReason().then(function (result) {
                if (result.isConfirmed) {
                    saveScore($input, result.value);
                } else {
                    $input.val($input.data('original'));
                }
            });
        } else {
            saveScore($input, '');
        }
    });

    $(document).on('change', '.gradebook-final-input', function () {
        const $input = $(this);
        if (isRealEdit($input)) {
            askEditReason().then(function (result) {
                if (result.isConfirmed) {
                    saveFinalGrade($input, result.value);
                } else {
                    $input.val($input.data('original'));
                }
            });
        } else {
            saveFinalGrade($input, '');
        }
    });

});
