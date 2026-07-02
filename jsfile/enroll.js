$(document).ready(function () {
    console.log('Enrollment management loaded');
    
    let activeSchoolYearId;
    let activeSchoolYearName;
    let enrollmentTable;
    let selectedLevelName = '';
    let studentList = [];
    let updatingStudent = false;

    // Unbind previous handlers
    $(document).off('change', '#level_select');
    $(document).off('change', '#section_select');
    $(document).off('change', '#student_lrn');
    $(document).off('change', '#student_name');
    $(document).off('submit', '#enrollmentForm');
    $(document).off('reset', '#enrollmentForm');

    // Load active school year
    function loadActiveSchoolYear() {
        $.ajax({
            url: 'config/school_year_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                if (res.data && res.data.length > 0) {
                    const activeYear = res.data.find(year => year.status == 1);
                    if (activeYear) {
                        activeSchoolYearId = activeYear.schoolyear_id;
                        activeSchoolYearName = activeYear.schoolyear_name;
                    } else {
                        activeSchoolYearId = res.data[0].schoolyear_id;
                        activeSchoolYearName = res.data[0].schoolyear_name;
                    }
                    $('#activeSchoolYear').text(activeSchoolYearName);
                    loadLevels();
                    initEnrollmentTable();
                } else {
                    $('#activeSchoolYear').text('No school year found');
                }
            },
            error: function (err) {
                console.error('Error loading school year:', err);
                $('#activeSchoolYear').text('Error loading school year');
            }
        });
    }

    // Load students
    function loadStudents() {
        $.ajax({
            url: 'config/student_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                studentList = res.data || [];
                
                let lrnOptions = '<option value="">-- Select LRN --</option>';
                let nameOptions = '<option value="">-- Select Name --</option>';
                
                if (studentList && studentList.length > 0) {
                    studentList.forEach(function (student) {
                        const lrn = student.lrn || '-';
                        const name = (student.first_name || '') + ' ' + (student.last_name || '');
                        
                        lrnOptions += `<option value="${student.student_id}">${lrn}</option>`;
                        nameOptions += `<option value="${student.student_id}">${name}</option>`;
                    });
                }
                
                $('#student_lrn').html(lrnOptions);
                $('#student_name').html(nameOptions);
                
                $('#student_lrn').select2({
                    placeholder: '-- Select LRN --',
                    allowClear: true
                });
                $('#student_name').select2({
                    placeholder: '-- Select Name --',
                    allowClear: true
                });
            },
            error: function (err) {
                console.error('Error loading students:', err);
            }
        });
    }

    // Load levels
    function loadLevels() {
        $.ajax({
            url: 'config/level_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Level --</option>';
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function (level) {
                        options += `<option value="${level.level_id}" data-name="${level.level_name}">${level.level_name}</option>`;
                    });
                }
                $('#level_select').html(options);
            },
            error: function (err) {
                console.error('Error loading levels:', err);
            }
        });
    }

    // Load sections by level (from school year setup)
    function loadSections(levelId) {
        console.log('loadSections called with levelId:', levelId, 'activeSchoolYearId:', activeSchoolYearId);
        
        if (!levelId || !activeSchoolYearId) {
            console.warn('Missing data for section load:', { levelId, activeSchoolYearId });
            $('#section_select').html('<option value="">-- Select Section --</option>');
            return;
        }

        $.ajax({
            url: 'config/enrollment_section_load.php',
            type: 'POST',
            data: { schoolyear_id: activeSchoolYearId, level_id: levelId },
            dataType: 'json',
            success: function (res) {
                console.log('Sections loaded:', res);
                let options = '<option value="">-- Select Section --</option>';
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function (section) {
                        options += `<option value="${section.schoolyear_section_id}">${section.section_name}</option>`;
                    });
                }
                $('#section_select').html(options);
            },
            error: function (err) {
                console.error('Error loading sections:', err, 'URL:', 'config/enrollment_section_load.php', 'Data:', { schoolyear_id: activeSchoolYearId, level_id: levelId });
            }
        });
    }

    // Load subjects based on section for active school year
    function loadSubjects(sectionId) {
        if (!sectionId || !activeSchoolYearId) {
            $('#subjectsContainer').html('');
            return;
        }

        const levelOption = $('#level_select').find('option:selected');
        selectedLevelName = levelOption.data('name') || '';

        $.ajax({
            url: 'config/enrollment_subject_load.php',
            type: 'POST',
            data: { schoolyear_id: activeSchoolYearId, section_id: sectionId },
            dataType: 'json',
            success: function (res) {
                let html = '';
                
                if (res.data && res.data.length > 0) {
                    if (selectedLevelName && selectedLevelName.toLowerCase().includes('senior')) {
                        html = '<div class="row">';
                        res.data.forEach(function (subject) {
                            html += `
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input subject-checkbox" type="checkbox" 
                                            name="subject_ids[]" value="${subject.subject_id}" 
                                            id="subject_${subject.subject_id}">
                                        <label class="form-check-label" for="subject_${subject.subject_id}">
                                            ${subject.subject_code} - ${subject.subject_name}
                                        </label>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                    } else {
                        html = '<div class="alert alert-info">Subjects for this section:</div><div class="row">';
                        res.data.forEach(function (subject) {
                            html += `
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input subject-checkbox" type="checkbox" 
                                            name="subject_ids[]" value="${subject.subject_id}" 
                                            id="subject_${subject.subject_id}" checked>
                                        <label class="form-check-label" for="subject_${subject.subject_id}">
                                            ${subject.subject_code} - ${subject.subject_name}
                                        </label>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                    }
                } else {
                    html = '<div class="alert alert-warning">No subjects assigned to this section for the current school year</div>';
                }
                
                $('#subjectsContainer').html(html);
            },
            error: function (err) {
                console.error('Error loading subjects:', err);
                $('#subjectsContainer').html('<div class="alert alert-danger">Error loading subjects</div>');
            }
        });
    }

    // Student LRN selection change
    $(document).on('change', '#student_lrn', function () {
        if (updatingStudent) return;
        
        const studentId = $(this).val();
        if (studentId) {
            const student = studentList.find(s => s.student_id == studentId);
            if (student) {
                updatingStudent = true;
                $('#student_id').val(studentId);
                $('#student_birthday').val(student.birthday || '');
                $('#student_gender').val(student.sex || '');
                $('#student_name').val(studentId).trigger('change.select2');
                updatingStudent = false;
            }
        }
    });

    // Student Name selection change
    $(document).on('change', '#student_name', function () {
        if (updatingStudent) return;
        
        const studentId = $(this).val();
        if (studentId) {
            const student = studentList.find(s => s.student_id == studentId);
            if (student) {
                updatingStudent = true;
                $('#student_id').val(studentId);
                $('#student_birthday').val(student.birthday || '');
                $('#student_gender').val(student.sex || '');
                $('#student_lrn').val(studentId).trigger('change.select2');
                updatingStudent = false;
            }
        }
    });

    // Level change event
    $(document).on('change', '#level_select', function () {
        const levelId = $(this).val();
        loadSections(levelId);
        $('#subjectsContainer').html('');
    });

    // Section change event
    $(document).on('change', '#section_select', function () {
        const sectionId = $(this).val();
        loadSubjects(sectionId);
    });

    // Form reset
    $(document).on('reset', '#enrollmentForm', function () {
        setTimeout(function () {
            $('#student_id').val('');
            $('#student_birthday').val('');
            $('#student_gender').val('');
            $('#subjectsContainer').html('');
            $('#section_select').html('<option value="">-- Select Section --</option>');
            $('#student_lrn').val(null).trigger('change');
            $('#student_name').val(null).trigger('change');
        }, 0);
    });

    // Form submit
    $(document).on('submit', '#enrollmentForm', function (e) {
        e.preventDefault();

        const studentId = $('#student_id').val();
        const levelId = $('#level_select').val();
        const sectionId = $('#section_select').val();
        const selectedSubjects = [];

        $('.subject-checkbox:checked').each(function () {
            selectedSubjects.push($(this).val());
        });

        if (!studentId || !levelId || !sectionId) {
            Swal.fire({
                icon: 'error',
                title: 'Missing Information',
                text: 'Please select student, level, and section',
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }

        if (selectedSubjects.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'No Subjects Selected',
                text: 'Please select at least one subject',
                timer: 3000,
                showConfirmButton: false
            });
            return;
        }

        $.ajax({
            url: 'config/enrollment_save.php',
            type: 'POST',
            data: {
                student_id: studentId,
                schoolyear_id: activeSchoolYearId,
                section_id: sectionId,
                subject_ids: selectedSubjects
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    $('#enrollmentForm')[0].reset();
                    enrollmentTable.ajax.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', error, xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to enroll student: ' + error,
                    timer: 5000,
                    showConfirmButton: true
                });
            }
        });
    });

    // Initialize DataTable
    function initEnrollmentTable() {
        enrollmentTable = $('#enrollmentTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: {
                lengthMenu: 'Show _MENU_ entries'
            },
            ajax: {
                url: 'config/enrollment_load.php',
                type: 'POST',
                data: { schoolyear_id: activeSchoolYearId },
                dataSrc: 'data'
            },
            columns: [
                { data: 'lrn', defaultContent: '-' },
                { data: 'student_name' },
                { data: 'level_name' },
                { data: 'section_name' },
                { data: 'schoolyear_name' },
                { 
                    data: 'subject_count',
                    render: function (data) {
                        return data + ' subject(s)';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-danger btnDeleteEnrollment" data-id="${data.enrollment_id}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

    // Delete enrollment
    $(document).on('click', '.btnDeleteEnrollment', function () {
        const enrollmentId = $(this).data('id');

        Swal.fire({
            title: 'Delete Enrollment?',
            text: 'This action cannot be undone',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'config/enrollment_delete.php',
                    type: 'POST',
                    data: { enrollment_id: enrollmentId },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted',
                                text: res.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            enrollmentTable.ajax.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message,
                                timer: 3000,
                                showConfirmButton: false
                            });
                        }
                    },
                    error: function (err) {
                        console.error('Error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to delete enrollment'
                        });
                    }
                });
            }
        });
    });

    // Initialize on page load
    loadActiveSchoolYear();
    loadStudents();
});
