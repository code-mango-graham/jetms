$(document).ready(function () {

    $(document).off('click', '.btnViewGrades');

    $.ajax({
        url: 'config/grading.php',
        type: 'POST',
        data: { action: 'student_subjects' },
        dataType: 'json',
        success: function (res) {
            const subjects = res.data || [];

            if (subjects.length === 0) {
                $('#subjectsList').html('<div class="col-12 text-center text-muted py-4">No subjects on record yet. Check back once you\'re enrolled for the current school year.</div>');
                return;
            }

            let html = '';
            subjects.forEach(function (s) {
                const teacherLine = s.teacher_name
                    ? `<div class="text-muted small"><i class="bi bi-person-badge me-1"></i>${s.teacher_name}</div>`
                    : `<div class="text-muted small"><i class="bi bi-person-dash me-1"></i>Teacher not yet assigned</div>`;

                const gradesBtn = s.class_id
                    ? `<button class="btn btn-outline-primary btn-sm mt-2 btnViewGrades" data-class-id="${s.class_id}" data-subject="${s.subject_name}">
                           <i class="bi bi-journal-text me-1"></i>View Grades
                       </button>`
                    : '';

                html += `
                    <div class="col-md-4">
                        <div class="subpanel p-3 h-100">
                            <div class="fw-bold">${s.subject_name}${s.subject_code ? ` <span class="text-muted small">(${s.subject_code})</span>` : ''}</div>
                            <div class="text-muted small">${s.section_name} &middot; S.Y. ${s.schoolyear_name}</div>
                            ${teacherLine}
                            ${gradesBtn}
                        </div>
                    </div>
                `;
            });

            $('#subjectsList').html(html);
        }
    });

    $(document).on('click', '.btnViewGrades', function () {
        sessionStorage.setItem('jetms_grades_class_id', $(this).data('class-id'));
        sessionStorage.setItem('jetms_grades_subject', $(this).data('subject'));
        $('#student_grades').trigger('click');
    });

});
