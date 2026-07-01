$(document).ready(function () {
    console.log('Student management loaded');
    
    let studentsTable;
    let activeSchoolYearId;
    let activeSchoolYearName;
    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddStudent');
    $(document).off('submit', '#studentForm');
    $(document).off('click', '#btnSaveStudent');
    $(document).off('click', '.btnEditStudent');
    $(document).off('change', '#statusFilter');

    // Load active school year
    function loadActiveSchoolYear() {
        $.ajax({
            url: 'config/school_year_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                if (res.data && res.data.length > 0) {
                    // Find the active school year (status = 1)
                    const activeYear = res.data.find(year => year.status == 1);
                    if (activeYear) {
                        activeSchoolYearId = activeYear.schoolyear_id;
                        activeSchoolYearName = activeYear.schoolyear_name;
                        $('#activeSchoolYear').text(activeSchoolYearName);
                    } else {
                        // If no active year, use the first one
                        activeSchoolYearId = res.data[0].schoolyear_id;
                        activeSchoolYearName = res.data[0].schoolyear_name;
                        $('#activeSchoolYear').text(activeSchoolYearName + ' (No active status)');
                    }
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

    // Show student modal
    function showStudentModal() {
        const el = document.getElementById('studentModal');
        if (!el) return;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.show();
        
        // Initialize Select2 on modal elements
        $('#studentModal .select2').select2({
            dropdownParent: $('#studentModal')
        });
        
        // Load provinces when modal is shown
        loadProvinces();
    }

    // Hide student modal
    function hideStudentModal() {
        const el = document.getElementById('studentModal');
        if (!el) return;
        const modal = bootstrap.Modal.getOrCreateInstance(el);
        modal.hide();
    }

    // Load provinces
    function loadProvinces(selectedProvCode = '') {
        $.ajax({
            url: 'config/ref_province_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Province --</option>';
                
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function (row) {
                        options += `<option value="${row.provCode}">${row.provDesc}</option>`;
                    });
                }
                
                $('#province').html(options);
                $('#province').trigger('change');
                
                if (selectedProvCode) {
                    $('#province').val(selectedProvCode).trigger('change');
                    loadMunicipalities(selectedProvCode);
                }
            },
            error: function (err) {
                console.error('Error loading provinces:', err);
            }
        });
    }

    // Load municipalities by province code
    function loadMunicipalities(provCode, selectedCitymunCode = '') {
        if (!provCode) {
            $('#municipality').html('<option value="">-- Select Municipality --</option>');
            $('#barangay').html('<option value="">-- Select Barangay --</option>');
            return;
        }

        $.ajax({
            url: 'config/ref_citymun_load.php',
            type: 'POST',
            data: { prov_code: provCode },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Municipality --</option>';
                
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function (row) {
                        options += `<option value="${row.citymunCode}">${row.citymunDesc}</option>`;
                    });
                }
                
                $('#municipality').html(options);
                $('#municipality').trigger('change');
                $('#barangay').html('<option value="">-- Select Barangay --</option>');
                $('#barangay').trigger('change');
                
                if (selectedCitymunCode) {
                    $('#municipality').val(selectedCitymunCode).trigger('change');
                    loadBarangays(selectedCitymunCode);
                }
            },
            error: function (err) {
                console.error('Error loading municipalities:', err);
            }
        });
    }

    // Load barangays by municipality/city code
    function loadBarangays(citymunCode, selectedBrgyCode = '') {
        if (!citymunCode) {
            $('#barangay').html('<option value="">-- Select Barangay --</option>');
            return;
        }

        $.ajax({
            url: 'config/ref_brgy_load.php',
            type: 'POST',
            data: { citymun_code: citymunCode },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Barangay --</option>';
                
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function (row) {
                        options += `<option value="${row.brgyDesc}">${row.brgyDesc}</option>`;
                    });
                }
                
                $('#barangay').html(options);
                $('#barangay').trigger('change');
                
                if (selectedBrgyCode) {
                    $('#barangay').val(selectedBrgyCode).trigger('change');
                }
            },
            error: function (err) {
                console.error('Error loading barangays:', err);
            }
        });
    }

    // Handle province change
    $(document).on('change', '#province', function () {
        const provCode = $(this).val();
        loadMunicipalities(provCode);
    });

    // Handle municipality change
    $(document).on('change', '#municipality', function () {
        const citymunCode = $(this).val();
        loadBarangays(citymunCode);
    });

    // Initialize DataTable
    function initTable() {
        if ($.fn.DataTable.isDataTable('#studentsTable')) {
            $('#studentsTable').DataTable().destroy();
            $('#studentsTable tbody').empty();
        }

        studentsTable = $('#studentsTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: {
                lengthMenu: 'Show _MENU_ entries'
            },
            ajax: {
                url: 'config/student_load.php',
                type: 'POST',
                dataSrc: 'data'
            },
            columns: [
                { data: 'lrn', defaultContent: '' },
                { data: 'last_name' },
                { data: 'first_name' },
                {
                    data: 'birthday',
                    render: function (data) {
                        return data && data !== '0000-00-00' ? data : '';
                    }
                },
                { data: 'sex', defaultContent: '' },
                {
                    data: 'student_status',
                    render: function (data) {
                        let badgeClass = 'bg-secondary';
                        switch (data) {
                            case 'active':
                            case 'enrolled':
                                badgeClass = 'bg-success';
                                break;
                            case 'inactive':
                                badgeClass = 'bg-warning';
                                break;
                            case 'transferred':
                                badgeClass = 'bg-info';
                                break;
                            case 'graduated':
                                badgeClass = 'bg-primary';
                                break;
                            case 'archived':
                                badgeClass = 'bg-dark';
                                break;
                            case 'deceased':
                                badgeClass = 'bg-danger';
                                break;
                        }
                        return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btnEditStudent" data-id="${data.student_id}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ],
            layout: {
                topStart: [
                    {
                        buttons: [
                            { extend: 'copy', text: 'Copy', className: 'btn btn-secondary btn-sm' },
                            { extend: 'excel', text: 'Excel', className: 'btn btn-success btn-sm' },
                            { extend: 'pdf', text: 'PDF', className: 'btn btn-danger btn-sm' },
                            { extend: 'print', text: 'Print', className: 'btn btn-primary btn-sm' }
                        ]
                    },
                    'pageLength'
                ],
                topEnd: 'search'
            }
        });
    }

    // Add new student button click
    $(document).on('click', '#btnAddStudent', function () {
        $('#studentForm')[0].reset();
        $('#student_id').val('');
        $('#studentModal .modal-title').text('Add New Student');
        showStudentModal();
    });

    // Edit student
    $(document).on('click', '.btnEditStudent', function () {
        const studentId = $(this).data('id');
        $.ajax({
            url: 'config/student_get.php',
            type: 'POST',
            data: { student_id: studentId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const data = res.data;
                    $('#studentId').val(data.student_id);
                    $('#lrn').val(data.lrn);
                    $('#last_name').val(data.last_name);
                    $('#first_name').val(data.first_name);
                    $('#middle_name').val(data.middle_name);
                    $('#extension_name').val(data.extension_name);
                    $('#middle_initial').val(data.middle_initial);
                    $('#birthday').val(data.birthday);
                    $('#sex').val(data.sex);
                    $('#cp_no').val(data.cp_no);
                    $('#spoken_language').val(data.spoken_language);
                    $('#fb_name').val(data.fb_name);
                    $('#esc_id_no').val(data.esc_id_no);
                    $('#former_school').val(data.former_school);
                    $('#father_name').val(data.father_name);
                    $('#mother_name').val(data.mother_name);
                    $('#contact_person').val(data.contact_person);
                    $('#contact_fb_name').val(data.contact_fb_name);
                    $('#street_name').val(data.street_name);
                    $('#barangay').val(data.barangay);
                    $('#municipality').val(data.municipality);
                    $('#province').val(data.province);
                    $('#contact_cp_no').val(data.contact_cp_no);
                    $('#student_status').val(data.student_status);

                    // Load province if not empty, then load municipalities and barangays
                    if (data.province) {
                        loadProvinces(data.province);
                        
                        // Wait for province to load, then load municipality
                        setTimeout(() => {
                            if (data.municipality) {
                                loadMunicipalities(data.province, data.municipality);
                            }
                        }, 300);

                        // After municipality loads, load barangay
                        setTimeout(() => {
                            if (data.barangay && data.municipality) {
                                loadBarangays(data.municipality, data.barangay);
                            }
                        }, 600);
                    }

                    $('#studentModal .modal-title').text('Edit Student');
                    showStudentModal();
                }
            },
            error: function (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load student data',
                    timer: 3000,
                    showConfirmButton: false
                });
                console.error(err);
            }
        });
    });
   
    $(document).on('submit', '#studentForm', function(e){
        e.preventDefault();
        
        // Determine if adding new or updating existing student
        const studentId = $('#student_id').val();
        const endpoint = studentId ? 'config/student_update.php' : 'config/student_add.php';

        $.ajax({
            url: endpoint,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res){
                console.log('Response:', res);

                if(res.status !== 'success'){
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'Failed to save student',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                hideStudentModal();
                document.activeElement.blur(); 
                $('#studentsTable').DataTable().ajax.reload(null, false);
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: res.message || 'Saved Successfully',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            },
            error: function(xhr, status, error){
                console.error('AJAX Error:', error, xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save student: ' + error,
                    timer: 5000,
                    showConfirmButton: true
                });
            }
        });
    });

    // Filter by status
    $(document).on('change', '#statusFilter', function () {
        const filterValue = $(this).val();
        if (filterValue === '') {
            studentsTable.column(5).search('').draw();
        } else {
            studentsTable.column(5).search(filterValue).draw();
        }
    });

    // Initialize
    loadActiveSchoolYear();
    initTable();
});
