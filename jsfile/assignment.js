$(document).ready(function () {
    var allSubjects = [];
    var allTeachers = [];
    var currentAssignments = {};
    var selectedSubject = null;

    // Load levels on page load
    loadLevels();

    // Load teachers
    loadTeachers();

    // Level change event
    $('#assignment_level').on('change', function () {
        var levelId = $(this).val();
        $('#assignment_section').empty().append('<option value="">Select Section</option>');
        $('#subjects_list').empty();
        $('#assignment_details').html('<div class="alert alert-info text-center"><p>Select a section to load subjects</p></div>');
        $('#assignment_container').addClass('d-none');

        if (levelId) {
            loadSections(levelId);
        }
    });

    // Section change event
    $('#assignment_section').on('change', function () {
        selectedSubject = null;
        $('#subjects_list').empty();
        $('#assignment_details').html('<div class="alert alert-info text-center"><p>Select a subject from the left panel</p></div>');
        
        var sectionId = $(this).val();
        if (sectionId) {
            loadSubjects(sectionId);
            $('#assignment_container').removeClass('d-none');
        } else {
            $('#assignment_container').addClass('d-none');
        }
    });

    // Load button click
    $('#assignment_load_btn').on('click', function () {
        var levelId = $('#assignment_level').val();
        var sectionId = $('#assignment_section').val();

        if (!levelId) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please select a level'
            });
            return;
        }

        if (!sectionId) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please select a section'
            });
            return;
        }

        loadSubjects(sectionId);
    });

    // Save all assignments for section
    $('#assignment_save_all').on('click', function () {
        var sectionId = $('#assignment_section').val();
        if (!sectionId) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please select a section'
            });
            return;
        }

        var assignmentsToSave = [];
        
        allSubjects.forEach(function (subject) {
            var cardId = 'subject_card_' + subject.subject_id;
            var teacherId = $('#teacher_' + subject.subject_id).val() || null;
            var schedule = $('#schedule_' + subject.subject_id).val() || null;
            var room = $('#room_' + subject.subject_id).val() || null;

            var assignmentId = $('#assignment_id_' + subject.subject_id).val() || 0;

            assignmentsToSave.push({
                assignment_id: parseInt(assignmentId) || 0,
                subject_id: subject.subject_id,
                teacher_id: teacherId ? parseInt(teacherId) : null,
                schedule_info: schedule,
                room_no: room
            });
        });

        saveMultipleAssignments(sectionId, assignmentsToSave);
    });

    // Load Levels
    function loadLevels() {
        $.ajax({
            type: 'POST',
            url: 'config/level_load.php',
            dataType: 'json',
            success: function (response) {
                var levelSelect = $('#assignment_level');
                levelSelect.empty().append('<option value="">Select Level</option>');

                if (response.data && response.data.length > 0) {
                    response.data.forEach(function (level) {
                        levelSelect.append('<option value="' + level.level_id + '">' + level.level_name + '</option>');
                    });
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load levels'
                });
            }
        });
    }

    // Load Sections
    function loadSections(levelId) {
        $.ajax({
            type: 'POST',
            url: 'config/assignment_get_sections.php',
            data: { level_id: levelId },
            dataType: 'json',
            success: function (response) {
                var sectionSelect = $('#assignment_section');
                sectionSelect.empty().append('<option value="">Select Section</option>');

                if (response.data && response.data.length > 0) {
                    response.data.forEach(function (section) {
                        sectionSelect.append('<option value="' + section.section_id + '">' + section.section_name + '</option>');
                    });
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load sections'
                });
            }
        });
    }

    // Load Subjects
    function loadSubjects(sectionId) {
        $.ajax({
            type: 'POST',
            url: 'config/assignment_get_subjects.php',
            data: { section_id: sectionId },
            dataType: 'json',
            success: function (response) {
                allSubjects = response.data || [];
                currentAssignments = {};
                
                var subjectsList = $('#subjects_list');
                subjectsList.empty();

                if (allSubjects.length > 0) {
                    allSubjects.forEach(function (subject, index) {
                        var subjectCard = $('<div class="card card-sm mb-2 subject-card cursor-pointer" data-subject-id="' + subject.subject_id + '">')
                            .html('<div class="card-body p-2">' +
                                '<strong>' + subject.subject_name + '</strong><br>' +
                                '<small class="text-muted">' + subject.subject_code + '</small>' +
                                '</div>');
                        
                        subjectsList.append(subjectCard);

                        // Load current assignment for this subject
                        loadCurrentAssignment(sectionId, subject.subject_id);
                    });

                    // Add click handlers to subject cards
                    $('.subject-card').on('click', function () {
                        $('.subject-card').removeClass('border-primary border-3');
                        $(this).addClass('border-primary border-3');
                        
                        var subjectId = $(this).data('subject-id');
                        selectedSubject = subjectId;
                        showAssignmentForm(subjectId);
                    });
                } else {
                    subjectsList.html('<div class="alert alert-info">No subjects available for this section</div>');
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load subjects'
                });
            }
        });
    }

    // Load current assignment for a subject
    function loadCurrentAssignment(sectionId, subjectId) {
        $.ajax({
            type: 'POST',
            url: 'config/assignment_get_current.php',
            data: {
                section_id: sectionId,
                subject_id: subjectId
            },
            dataType: 'json',
            success: function (response) {
                if (response.data) {
                    currentAssignments[subjectId] = response.data;
                }
            },
            error: function () {
                // Silent error for individual subject loading
            }
        });
    }

    // Show assignment form for selected subject
    function showAssignmentForm(subjectId) {
        var subject = allSubjects.find(function (s) { return s.subject_id == subjectId; });
        
        if (!subject) return;

        var assignment = currentAssignments[subjectId] || {};
        var assignmentId = assignment.assignment_id || 0;

        var formHtml = '<form id="assignmentForm_' + subjectId + '">' +
            '<input type="hidden" id="assignment_id_' + subjectId + '" value="' + assignmentId + '">' +
            '<div class="mb-3">' +
            '<label class="form-label">Subject Name</label>' +
            '<input type="text" class="form-control" value="' + subject.subject_name + ' (' + subject.subject_code + ')" readonly>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="teacher_' + subjectId + '" class="form-label">Assign Teacher <span class="text-danger">*</span></label>' +
            '<select class="form-control" id="teacher_' + subjectId + '" name="teacher_' + subjectId + '">' +
            '<option value="">Select Teacher</option>';

        if (allTeachers.length > 0) {
            allTeachers.forEach(function (teacher) {
                var selected = assignment.teacher_id && assignment.teacher_id == teacher.teacher_id ? 'selected' : '';
                formHtml += '<option value="' + teacher.teacher_id + '" ' + selected + '>' + 
                    teacher.first_name + ' ' + teacher.last_name + '</option>';
            });
        }

        formHtml += '</select>' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="schedule_' + subjectId + '" class="form-label">Time Duration</label>' +
            '<input type="text" class="form-control" id="schedule_' + subjectId + '" placeholder="e.g., 8:00 AM - 9:00 AM" value="' + (assignment.schedule_info || '') + '">' +
            '</div>' +
            '<div class="mb-3">' +
            '<label for="room_' + subjectId + '" class="form-label">Room Number</label>' +
            '<input type="text" class="form-control" id="room_' + subjectId + '" placeholder="e.g., Room 101" value="' + (assignment.room_no || '') + '">' +
            '</div>' +
            '<button type="button" class="btn btn-primary" onclick="saveIndividualAssignment(' + subjectId + ')">Save This Assignment</button>' +
            '</form>';

        var assignmentDetails = $('#assignment_details');
        assignmentDetails.empty().append(formHtml);

        // Update header
        $('#assignment_header').text(subject.subject_name);
    }

    // Load teachers
    function loadTeachers() {
        $.ajax({
            type: 'POST',
            url: 'config/teacher_load.php',
            dataType: 'json',
            success: function (response) {
                allTeachers = response.data || [];
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load teachers'
                });
            }
        });
    }

    // Save individual assignment (make it global so it can be called from form button)
    window.saveIndividualAssignment = function (subjectId) {
        var sectionId = $('#assignment_section').val();
        var teacherId = $('#teacher_' + subjectId).val() || null;
        var schedule = $('#schedule_' + subjectId).val() || null;
        var room = $('#room_' + subjectId).val() || null;
        var assignmentId = $('#assignment_id_' + subjectId).val() || 0;

        if (!teacherId) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please select a teacher'
            });
            return;
        }

        var data = {
            assignment_id: parseInt(assignmentId) || 0,
            section_id: parseInt(sectionId),
            subject_id: subjectId,
            teacher_id: parseInt(teacherId),
            schedule_info: schedule,
            room_no: room
        };

        $.ajax({
            type: 'POST',
            url: 'config/assignment_save.php',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Update the assignment_id if it's a new record
                    if (response.assignment_id) {
                        $('#assignment_id_' + subjectId).val(response.assignment_id);
                    }

                    // Update currentAssignments
                    currentAssignments[subjectId] = {
                        assignment_id: response.assignment_id || assignmentId,
                        teacher_id: parseInt(teacherId),
                        schedule_info: schedule,
                        room_no: room
                    };

                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while saving'
                });
            }
        });
    };

    // Save multiple assignments
    function saveMultipleAssignments(sectionId, assignments) {
        $.ajax({
            type: 'POST',
            url: 'config/assignment_save_multiple.php',
            data: {
                section_id: sectionId,
                assignments: assignments
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message
                    });

                    // Reload assignments
                    loadSubjects(sectionId);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while saving multiple assignments'
                });
            }
        });
    }
});
