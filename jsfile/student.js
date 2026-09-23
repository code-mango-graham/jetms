$(document).ready(function () {
    console.log('Student management loaded');
    const defaultStudentPhoto = 'logos/logo1.png';
    const outputPhotoSize = 600;
    let cameraStream = null;
    
    let studentsTable;
    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddStudent');
    $(document).off('submit', '#studentForm');
    $(document).off('click', '#btnSaveStudent');
    $(document).off('click', '.btnEditStudent');
    $(document).off('click', '.btnViewStudent');
    $(document).off('change', '#student_photo');
    $(document).off('input', '#cp_no');
    $(document).off('input', '#contact_cp_no');

    $(document).on('input', '#cp_no, #contact_cp_no', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });
    $(document).off('click', '#btnOpenCamera');
    $(document).off('click', '#btnCapturePhoto');
    $(document).off('click', '#btnCancelCamera');
    $(document).off('click', '#btnCloseCameraModal');
    $(document).off('change', '#statusFilter');

    function resolvePhotoSrc(path) {
        if (!path) return defaultStudentPhoto;
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        if (path.startsWith('/')) return path.slice(1);
        if (path.includes('/')) return path;
        return 'assets/img/students/' + path;
    }

    function setPhotoPreview(path) {
        $('#studentPhotoPreview').attr('src', resolvePhotoSrc(path));
    }

    function dataUrlToFile(dataUrl, filename) {
        const parts = dataUrl.split(',');
        const mime = parts[0].match(/:(.*?);/)[1];
        const bstr = atob(parts[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new File([u8arr], filename, { type: mime });
    }

    function assignFileToInput(file) {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        const input = document.getElementById('student_photo');
        input.files = transfer.files;
    }

    function normalizeTo2x2FileFromImage(imageSrc) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            img.onload = function () {
                const minSide = Math.min(img.width, img.height);
                const sx = (img.width - minSide) / 2;
                const sy = (img.height - minSide) / 2;

                const canvas = document.createElement('canvas');
                canvas.width = outputPhotoSize;
                canvas.height = outputPhotoSize;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, sx, sy, minSide, minSide, 0, 0, outputPhotoSize, outputPhotoSize);

                const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                resolve(dataUrlToFile(dataUrl, `student_${Date.now()}.jpg`));
            };
            img.onerror = function () {
                reject(new Error('Unable to process selected photo'));
            };
            img.src = imageSrc;
        });
    }

    async function handleSelectedPhotoFile(file) {
        if (!file) return;

        try {
            const fileReader = new FileReader();
            const dataUrl = await new Promise(function (resolve, reject) {
                fileReader.onload = function (event) {
                    resolve(event.target.result);
                };
                fileReader.onerror = function () {
                    reject(new Error('Unable to read selected photo'));
                };
                fileReader.readAsDataURL(file);
            });

            const normalizedFile = await normalizeTo2x2FileFromImage(dataUrl);
            assignFileToInput(normalizedFile);

            const previewReader = new FileReader();
            previewReader.onload = function (event) {
                $('#studentPhotoPreview').attr('src', event.target.result);
            };
            previewReader.readAsDataURL(normalizedFile);
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Photo Error',
                text: err.message || 'Failed to process photo',
                timer: 4000,
                showConfirmButton: true
            });
        }
    }

    async function startCamera() {
        const modalEl = document.getElementById('cameraModal');
        const video = document.getElementById('cameraVideo');

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            });

            video.srcObject = cameraStream;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Camera Error',
                text: 'Cannot access camera. Please allow permission or use file upload.',
                timer: 5000,
                showConfirmButton: true
            });
        }
    }

    function stopCamera() {
        const modalEl = document.getElementById('cameraModal');
        const video = document.getElementById('cameraVideo');

        if (cameraStream) {
            cameraStream.getTracks().forEach(function (track) {
                track.stop();
            });
            cameraStream = null;
        }

        video.srcObject = null;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    }

    async function captureFromCamera() {
        const video = document.getElementById('cameraVideo');
        if (!video || !video.videoWidth || !video.videoHeight) {
            return;
        }

        // Match the on-screen guide (62% square) to extract centered framing from camera feed.
        const cropSize = Math.floor(Math.min(video.videoWidth, video.videoHeight) * 0.62);
        const sx = Math.floor((video.videoWidth - cropSize) / 2);
        const sy = Math.floor((video.videoHeight - cropSize) / 2);

        const canvas = document.createElement('canvas');
        canvas.width = outputPhotoSize;
        canvas.height = outputPhotoSize;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, sx, sy, cropSize, cropSize, 0, 0, outputPhotoSize, outputPhotoSize);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        const cameraFile = dataUrlToFile(dataUrl, `student_camera_${Date.now()}.jpg`);
        assignFileToInput(cameraFile);
        $('#studentPhotoPreview').attr('src', dataUrl);
        stopCamera();
    }

    // Load active school year
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

    // Load provinces. `selectedProvName` is the stored NAME (province/municipality are
    // saved as readable names, not PSGC codes — see the submit handler), so it's resolved
    // to its code here before the dropdown can be pre-selected or cascaded.
    function loadProvinces(selectedProvName = '', onDone) {
        $.ajax({
            url: 'config/ref_province_load.php',
            type: 'POST',
            dataType: 'json',
            success: function (res) {
                const list = res.data || [];
                let options = '<option value="">-- Select Province --</option>';

                list.forEach(function (row) {
                    options += `<option value="${row.provCode}">${row.provDesc}</option>`;
                });

                $('#province').html(options);

                let matchedCode = '';
                if (selectedProvName) {
                    const match = list.find(function (row) { return row.provDesc === selectedProvName; });
                    if (match) {
                        matchedCode = match.provCode;
                        $('#province').val(matchedCode);
                    }
                }

                $('#province').trigger('change');

                if (typeof onDone === 'function') {
                    onDone(matchedCode);
                }
            },
            error: function (err) {
                console.error('Error loading provinces:', err);
            }
        });
    }

    // Load municipalities by province code. `selectedCitymunName` is the stored NAME.
    function loadMunicipalities(provCode, selectedCitymunName = '', onDone) {
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
                const list = res.data || [];
                let options = '<option value="">-- Select Municipality --</option>';

                list.forEach(function (row) {
                    options += `<option value="${row.citymunCode}">${row.citymunDesc}</option>`;
                });

                $('#municipality').html(options);
                $('#barangay').html('<option value="">-- Select Barangay --</option>');

                let matchedCode = '';
                if (selectedCitymunName) {
                    const match = list.find(function (row) { return row.citymunDesc === selectedCitymunName; });
                    if (match) {
                        matchedCode = match.citymunCode;
                        $('#municipality').val(matchedCode);
                    }
                }

                $('#municipality').trigger('change');
                $('#barangay').trigger('change');

                if (typeof onDone === 'function') {
                    onDone(matchedCode);
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
                url: 'config/student.php',
                type: 'POST',
                data: function (d) {
                    d.action = 'load';
                    return d;
                },
                dataSrc: 'data'
            },
            columns: [
                { 
                    data: 'lrn', 
                    className: 'text-center', 
                    defaultContent: '-',
                    render: function(data) {
                        return data && data.trim() ? data : '-';
                    }
                },
                { 
                    data: 'last_name',
                    render: function(data) {
                        return data && data.trim() ? data : '-';
                    }
                },
                { 
                    data: 'first_name',
                    render: function(data) {
                        return data && data.trim() ? data : '-';
                    }
                },
                { 
                    data: 'middle_name', 
                    defaultContent: '-',
                    render: function(data) {
                        return data && data.trim() ? data : '-';
                    }
                },
                { 
                    data: 'extension_name', 
                    defaultContent: '-',
                    render: function(data) {
                        return data && data.trim() ? data : '-';
                    }
                },
                {
                    data: 'birthday',
                    render: function (data) {
                        return data && data !== '0000-00-00' && data.trim() ? data : '-';
                    }
                },
                { 
                    data: 'sex', 
                    defaultContent: '-',
                    render: function(data) {
                        return data && data.trim() ? data : '-';
                    }
                },
                {
                    data: 'student_status',
                    render: function (data) {
                        if (!data || !data.trim()) {
                            return '<span class="badge bg-secondary">-</span>';
                        }

                        let badgeClass = 'bg-secondary';
                        switch (data) {
                            case 'New':
                                badgeClass = 'bg-success';
                                break;
                            case 'Old':
                                badgeClass = 'bg-info';
                                break;
                            case 'Transferee':
                                badgeClass = 'bg-warning';
                                break;
                            case 'Transferred':
                                badgeClass = 'bg-primary';
                                break;
                            case 'Graduated':
                                badgeClass = 'bg-purple';
                                break;
                            case 'Deceased':
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
                                <button class="btn btn-outline-primary btnViewStudent" data-id="${data.student_id}" title="View Profile">
                                    <i class="bi bi-person-vcard"></i>
                                </button>
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
        $('#existing_photo').val('');
        $('#student_photo').val('');
        setPhotoPreview('');
        $('#studentModal .modal-title').text('Add New Student');
        showStudentModal();
    });

    $(document).on('change', '#student_photo', function () {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            setPhotoPreview($('#existing_photo').val());
            return;
        }

        handleSelectedPhotoFile(file);
    });

    $(document).on('click', '#btnOpenCamera', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            Swal.fire({
                icon: 'warning',
                title: 'Camera Not Supported',
                text: 'Your browser does not support camera capture. Use file upload instead.',
                timer: 4000,
                showConfirmButton: true
            });
            return;
        }

        startCamera();
    });

    $(document).on('click', '#btnCapturePhoto', function () {
        captureFromCamera();
    });

    $(document).on('click', '#btnCancelCamera, #btnCloseCameraModal', function () {
        stopCamera();
    });

    // Edit student
    $(document).on('click', '.btnEditStudent', function () {
        const studentId = $(this).data('id');
        $.ajax({
            url: 'config/student.php',
            type: 'POST',
            data: { action: 'get', student_id: studentId },
            dataType: 'json',
            success: function (res) {
                if (res.success && res.data) {
                    const data = res.data;
                    $('#student_id').val(data.student_id);
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
                    $('#contact_cp_no').val(data.contact_cp_no);
                    $('#student_status').val(data.student_status);
                    $('#existing_photo').val(data.student_photo || '');
                    $('#student_photo').val('');
                    setPhotoPreview(data.student_photo || '');

                    // Province/Municipality/Barangay are stored as readable names, not PSGC
                    // codes, so each level must resolve its code before the next can cascade.
                    if (data.province) {
                        loadProvinces(data.province, function (provCode) {
                            if (provCode && data.municipality) {
                                loadMunicipalities(provCode, data.municipality, function (citymunCode) {
                                    if (citymunCode && data.barangay) {
                                        loadBarangays(citymunCode, data.barangay);
                                    }
                                });
                            }
                        });
                    } else {
                        loadProvinces();
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

    $(document).on('click', '.btnViewStudent', function () {
        const studentId = $(this).data('id');
        if (!studentId) {
            return;
        }
        window.open(`student_profile.html?student_id=${encodeURIComponent(studentId)}`, '_blank');
    });
   
    $(document).on('submit', '#studentForm', function(e){
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'add');

        // Province/Municipality dropdown values are PSGC codes (needed to filter the
        // next dropdown's options), not the readable name — swap in the selected
        // option's label so what gets saved matches Barangay, which already stores its name.
        const provinceText = $('#province option:selected').text();
        const municipalityText = $('#municipality option:selected').text();
        if ($('#province').val()) {
            formData.set('province', provinceText);
        }
        if ($('#municipality').val()) {
            formData.set('municipality', municipalityText);
        }

        $.ajax({
            url: 'config/student.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
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
            studentsTable.column(7).search('').draw();
        } else {
            studentsTable.column(7).search(filterValue).draw();
        }
    });

    // Initialize
    initTable();
});
