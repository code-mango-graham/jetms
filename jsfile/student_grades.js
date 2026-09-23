$(document).ready(function () {

    $(document).off('change', '#grades_subject_select');

    function money(v) {
        return parseFloat(v).toFixed(2);
    }

    function renderGrades(components, finalGrades) {
        const quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        const finalMap = {};
        (finalGrades || []).forEach(function (f) { finalMap[f.quarter] = f.final_grade; });

        let html = '';
        let hasAnyComponent = components.length > 0;

        quarters.forEach(function (q) {
            const qComponents = components.filter(function (c) { return c.quarter === q; });
            if (qComponents.length === 0 && finalMap[q] === undefined) {
                return;
            }

            html += `<div class="subpanel p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>${q}</strong>
                    ${finalMap[q] !== undefined ? `<span class="badge bg-success">Final Grade: ${money(finalMap[q])}</span>` : '<span class="badge bg-secondary">Final Grade: Not yet released</span>'}
                </div>`;

            if (qComponents.length === 0) {
                html += '<div class="text-muted small">No quizzes/activities recorded yet.</div>';
            } else {
                html += '<table class="table table-sm table-bordered mb-0"><thead><tr><th>Type</th><th>Title</th><th class="text-center">Score</th></tr></thead><tbody>';
                qComponents.forEach(function (c) {
                    const scoreDisplay = c.score !== null && c.score !== undefined ? `${money(c.score)} / ${money(c.max_score)}` : '<span class="text-muted">Not yet recorded</span>';
                    html += `<tr><td class="text-capitalize">${c.component_type}</td><td>${c.title}</td><td class="text-center">${scoreDisplay}</td></tr>`;
                });
                html += '</tbody></table>';
            }

            html += '</div>';
        });

        if (!hasAnyComponent && Object.keys(finalMap).length === 0) {
            html = '<div class="text-center text-muted py-4">No grades recorded yet for this subject.</div>';
        }

        $('#gradesDetailBody').html(html);
    }

    function loadGrades(class_id) {
        $('#gradesDetailBody').html('<div class="text-center text-muted py-4">Loading...</div>');
        $.ajax({
            url: 'config/grading.php',
            type: 'POST',
            data: { action: 'student_grades', class_id: class_id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    $('#gradesDetailBody').html(`<div class="text-center text-danger py-4">${res.message}</div>`);
                    return;
                }
                renderGrades(res.components || [], res.final_grades || []);
            }
        });
    }

    $.ajax({
        url: 'config/grading.php',
        type: 'POST',
        data: { action: 'student_subjects' },
        dataType: 'json',
        success: function (res) {
            const subjects = (res.data || []).filter(function (s) { return s.class_id; });
            let options = '<option value="">-- Select a Subject --</option>';
            subjects.forEach(function (s) {
                const label = `${s.subject_name}${s.subject_code ? ' (' + s.subject_code + ')' : ''} — S.Y. ${s.schoolyear_name}${s.enrollment_status !== 'enrolled' ? ' [' + s.enrollment_status + ']' : ''}`;
                options += `<option value="${s.class_id}">${label}</option>`;
            });
            $('#grades_subject_select').html(options);

            const preselect = sessionStorage.getItem('jetms_grades_class_id');
            if (preselect) {
                sessionStorage.removeItem('jetms_grades_class_id');
                sessionStorage.removeItem('jetms_grades_subject');
                $('#grades_subject_select').val(preselect);
                if ($('#grades_subject_select').val() === preselect) {
                    loadGrades(preselect);
                }
            }
        }
    });

    $(document).on('change', '#grades_subject_select', function () {
        const class_id = $(this).val();
        if (!class_id) {
            $('#gradesDetailBody').html('<div class="text-center text-muted py-4">Select a subject above to view your grades.</div>');
            return;
        }
        loadGrades(class_id);
    });

});
