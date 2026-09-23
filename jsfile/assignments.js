$(document).ready(function () {

    $(document).off('click', '#btnAddAssignment');
    $(document).off('submit', '#assignmentForm');
    $(document).off('click', '.btnEditAssignment');
    $(document).off('click', '.btnDeleteAssignment');
    $(document).off('change', '#assign_level_id');

    if ($.fn.DataTable.isDataTable('#assignmentTable')) {
        $('#assignmentTable').DataTable().destroy();
        $('#assignmentTable tbody').empty();
    }

    let table = $('#assignmentTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries" },
        ajax: {
            url: 'config/assignment.php',
            type: 'POST',
            data: function (d) { d.action = 'load'; return d; }
        },
        order: [[1, 'asc']],
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
            { data: 'teacher_name' },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (d) {
                    return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-secondary btn-sm btnEditAssignment" data-id="${d.class_id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm btnDeleteAssignment" data-id="${d.class_id}" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    // ===========================================================
    // Level dropdown + cascading Section/Subject
    // ===========================================================
    function loadLevels(selectedId) {
        $.ajax({
            url: 'config/level.php',
            type: 'POST',
            data: { action: 'load' },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Level --</option>';
                (res.data || []).forEach(function (lvl) {
                    options += `<option value="${lvl.level_id}">${lvl.level_name}</option>`;
                });
                $('#assign_level_id').html(options);
                if (selectedId) {
                    $('#assign_level_id').val(selectedId);
                }
            }
        });
    }

    function loadTeachers(selectedId) {
        $.ajax({
            url: 'config/teacher.php',
            type: 'POST',
            data: { action: 'load' },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Teacher --</option>';
                (res.data || []).forEach(function (t) {
                    options += `<option value="${t.teacher_id}">${t.last_name}, ${t.first_name}</option>`;
                });
                $('#assign_teacher_id').html(options);
                if (selectedId) {
                    $('#assign_teacher_id').val(selectedId);
                }
            }
        });
    }

    function loadSectionsForLevel(level_id, selectedId) {
        if (!level_id) {
            $('#assign_section_id').html('<option value="">-- Select Level First --</option>').prop('disabled', true);
            return;
        }
        $.ajax({
            url: 'config/section.php',
            type: 'POST',
            data: { action: 'load', level_id: level_id },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Section --</option>';
                (res.data || []).forEach(function (s) {
                    options += `<option value="${s.section_id}">${s.section_name}</option>`;
                });
                $('#assign_section_id').html(options).prop('disabled', false);
                if (selectedId) {
                    $('#assign_section_id').val(selectedId);
                }
            }
        });
    }

    function loadSubjectsForLevel(level_id, selectedId) {
        if (!level_id) {
            $('#assign_subject_id').html('<option value="">-- Select Level First --</option>').prop('disabled', true);
            return;
        }
        $.ajax({
            url: 'config/subject.php',
            type: 'POST',
            data: { action: 'load', level_id: level_id },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Subject --</option>';
                (res.data || []).forEach(function (s) {
                    options += `<option value="${s.subject_id}">${s.subject_name}${s.subject_code ? ' (' + s.subject_code + ')' : ''}</option>`;
                });
                $('#assign_subject_id').html(options).prop('disabled', false);
                if (selectedId) {
                    $('#assign_subject_id').val(selectedId);
                }
            }
        });
    }

    $(document).on('change', '#assign_level_id', function () {
        const level_id = $(this).val();
        loadSectionsForLevel(level_id);
        loadSubjectsForLevel(level_id);
    });

    function resetAssignmentForm() {
        $('#assignmentForm')[0].reset();
        $('#class_id').val('');
        $('#assign_section_id').html('<option value="">-- Select Level First --</option>').prop('disabled', true);
        $('#assign_subject_id').html('<option value="">-- Select Level First --</option>').prop('disabled', true);
    }

    $('#btnAddAssignment').click(function () {
        resetAssignmentForm();
        loadLevels();
        loadTeachers();
        $('#assignmentModal .modal-title').text('Add Assignment');
        $('#assignmentModal').modal('show');
    });

    $(document).on('click', '.btnEditAssignment', function () {
        const class_id = $(this).data('id');
        $.ajax({
            url: 'config/assignment.php',
            type: 'POST',
            data: { action: 'get', class_id: class_id },
            dataType: 'json',
            success: function (data) {
                if (data.status === 'error') {
                    Swal.fire({ icon: 'error', title: data.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                $('#class_id').val(data.class_id);

                loadLevels(data.level_id);
                loadTeachers(data.teacher_id);
                loadSectionsForLevel(data.level_id, data.section_id);
                loadSubjectsForLevel(data.level_id, data.subject_id);

                $('#assignmentModal .modal-title').text('Edit Assignment');
                $('#assignmentModal').modal('show');
            }
        });
    });

    $('#assignmentForm').submit(function (e) {
        e.preventDefault();

        const payload = {
            action: 'add',
            class_id: $('#class_id').val(),
            level_id: $('#assign_level_id').val(),
            section_id: $('#assign_section_id').val(),
            subject_id: $('#assign_subject_id').val(),
            teacher_id: $('#assign_teacher_id').val()
        };

        $.ajax({
            url: 'config/assignment.php',
            type: 'POST',
            data: payload,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                $('#assignmentModal').modal('hide');
                document.activeElement.blur();
                $('#assignmentTable').DataTable().ajax.reload(null, false);
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

    $(document).on('click', '.btnDeleteAssignment', function () {
        const class_id = $(this).data('id');

        Swal.fire({
            title: 'Remove Assignment?',
            text: 'The teacher will no longer be assigned to this class.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Remove',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'config/assignment.php',
                    type: 'POST',
                    data: { action: 'delete', class_id: class_id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'error') {
                            Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                            return;
                        }

                        document.activeElement.blur();
                        $('#assignmentTable').DataTable().ajax.reload(null, false);
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Removed Successfully',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true
                        });
                    }
                });
            }
        });
    });

});
