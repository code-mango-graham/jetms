$(document).ready(function () {

    let enrollmentTable;
    let levelsCache = [];
    let activeSchoolYearName = '';

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddEnrollment');
    $(document).off('submit', '#enrollmentForm');
    $(document).off('change', '#enroll_level_id');
    $(document).off('change', '#addDownpayment');
    $(document).off('click', '.btnViewEnrollment');
    $(document).off('click', '#btnAddPayment');
    $(document).off('submit', '#paymentForm');
    $(document).off('click', '.btnCancelEnrollment');
    $(document).off('click', '.btnDropTransferEnrollment');
    $(document).off('click', '.btnReactivateEnrollment');
    $(document).off('click', '.btnConfirmDropTransfer');
    $(document).off('click', '#btnViewFullProfile');

    if ($.fn.DataTable.isDataTable('#enrollmentTable')) {
        $('#enrollmentTable').DataTable().destroy();
        $('#enrollmentTable tbody').empty();
    }
    if ($.fn.DataTable.isDataTable('#paymentTable')) {
        $('#paymentTable').DataTable().destroy();
        $('#paymentTable tbody').empty();
    }
    if ($.fn.DataTable.isDataTable('#historyTable')) {
        $('#historyTable').DataTable().destroy();
        $('#historyTable tbody').empty();
    }

    function money(v) {
        return '₱' + parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ===========================================================
    // Init: active school year badge + level list
    // ===========================================================
    $.ajax({
        url: 'config/schoolyear.php',
        type: 'POST',
        data: { action: 'load' },
        dataType: 'json',
        success: function (res) {
            const active = (res.data || []).find(function (y) { return y.status === 'active'; });
            if (active) {
                activeSchoolYearName = active.schoolyear_name;
                $('#activeYearBadge').removeClass('bg-secondary').addClass('bg-success').text('S.Y. ' + active.schoolyear_name);
                $('#enroll_schoolyear_display').val(active.schoolyear_name);
            } else {
                $('#activeYearBadge').text('No active school year');
            }
        }
    });

    $.ajax({
        url: 'config/level.php',
        type: 'POST',
        data: { action: 'load' },
        dataType: 'json',
        success: function (res) {
            levelsCache = res.data || [];
            let options = '<option value="">-- Select Level --</option>';
            levelsCache.forEach(function (lvl) {
                options += `<option value="${lvl.level_id}">${esc(lvl.level_name)}</option>`;
            });
            $('#enroll_level_id').html(options);
        }
    });

    $.ajax({
        url: 'config/student.php',
        type: 'POST',
        data: { action: 'load' },
        dataType: 'json',
        success: function (res) {
            let options = '<option value="">-- Select Student --</option>';
            (res.data || []).forEach(function (st) {
                if (st.student_status === 'archived') return;
                const label = `${st.last_name}, ${st.first_name}` + (st.lrn ? ` (${st.lrn})` : '');
                options += `<option value="${st.student_id}">${label}</option>`;
            });
            $('#enroll_student_id').html(options);
        }
    });

    // ===========================================================
    // Main DataTable
    // ===========================================================
    enrollmentTable = $('#enrollmentTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries" },
        ajax: {
            url: 'config/enrollment.php',
            type: 'POST',
            data: function (d) { d.action = 'load'; return d; }
        },
        columns: [
            { data: 'lrn', defaultContent: '-' },
            { data: null, render: function (d) { return `${d.last_name}, ${d.first_name}`; } },
            { data: 'level_name' },
            { data: 'section_name' },
            { data: 'tuition_fee', render: function (d) { return money(d); } },
            { data: 'balance', render: function (d) { return money(d); } },
            {
                data: 'status',
                render: function (data) {
                    const map = { enrolled: 'bg-success', dropped: 'bg-danger', transferred: 'bg-warning', completed: 'bg-secondary' };
                    return `<span class="badge ${map[data] || 'bg-secondary'}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (d) {
                    return `<button class="btn btn-outline-primary btn-sm btnViewEnrollment" data-id="${d.enrollment_id}" data-student="${d.student_id}" title="View">
                        <i class="bi bi-eye"></i>
                    </button>`;
                }
            }
        ]
    });

    // ===========================================================
    // Add Enrollment modal
    // ===========================================================
    function resetEnrollmentForm() {
        $('#enrollmentForm')[0].reset();
        $('#enroll_schoolyear_display').val(activeSchoolYearName);
        $('#enroll_section_id').html('<option value="">-- Select Level First --</option>').prop('disabled', true);
        $('#subjectsPreviewWrap, #subjectsPickWrap, #downpaymentFields').addClass('d-none');
        $('#subjectsPreview').text('Select a level to see its subjects.');
        $('#subjectsPick').empty();
    }

    $('#btnAddEnrollment').click(function () {
        resetEnrollmentForm();
        $('#enrollmentModal').modal('show');
    });

    $(document).on('change', '#enroll_level_id', function () {
        const level_id = $(this).val();
        $('#subjectsPreviewWrap, #subjectsPickWrap').addClass('d-none');

        if (!level_id) {
            $('#enroll_section_id').html('<option value="">-- Select Level First --</option>').prop('disabled', true);
            return;
        }

        const level = levelsCache.find(function (l) { return String(l.level_id) === String(level_id); });

        $.ajax({
            url: 'config/section.php',
            type: 'POST',
            data: { action: 'load', level_id: level_id },
            dataType: 'json',
            success: function (res) {
                let options = '<option value="">-- Select Section --</option>';
                (res.data || []).forEach(function (sec) {
                    options += `<option value="${sec.section_id}">${esc(sec.section_name)}</option>`;
                });
                $('#enroll_section_id').html(options).prop('disabled', false);
            }
        });

        $.ajax({
            url: 'config/subject.php',
            type: 'POST',
            data: { action: 'load', level_id: level_id },
            dataType: 'json',
            success: function (res) {
                const subjects = res.data || [];

                if (level && level.allows_subject_selection == 1) {
                    let boxes = '';
                    subjects.forEach(function (sub) {
                        boxes += `
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="subject_ids[]" value="${sub.subject_id}" id="sub_${sub.subject_id}">
                                    <label class="form-check-label" for="sub_${sub.subject_id}">${esc(sub.subject_name)}${sub.subject_code ? ' (' + esc(sub.subject_code) + ')' : ''}</label>
                                </div>
                            </div>
                        `;
                    });
                    $('#subjectsPick').html(boxes || '<div class="text-muted small">No subjects configured for this level yet.</div>');
                    $('#subjectsPickWrap').removeClass('d-none');
                } else {
                    const names = subjects.map(function (sub) { return sub.subject_name; });
                    $('#subjectsPreview').text(names.length ? names.join(', ') : 'No subjects configured for this level yet.');
                    $('#subjectsPreviewWrap').removeClass('d-none');
                }
            }
        });
    });

    $(document).on('change', '#addDownpayment', function () {
        $('#downpaymentFields').toggleClass('d-none', !this.checked);
    });

    $('#enrollmentForm').submit(function (e) {
        e.preventDefault();

        const level = levelsCache.find(function (l) { return String(l.level_id) === String($('#enroll_level_id').val()); });

        const payload = {
            action: 'add',
            student_id: $('#enroll_student_id').val(),
            level_id: $('#enroll_level_id').val(),
            section_id: $('#enroll_section_id').val(),
            tuition_fee: $('#tuition_fee').val()
        };

        if (level && level.allows_subject_selection == 1) {
            payload.subject_ids = $('#subjectsPick input[type=checkbox]:checked').map(function () { return $(this).val(); }).get();
        }

        if ($('#addDownpayment').is(':checked')) {
            payload.downpayment_amount = $('#downpayment_amount').val();
            payload.payment_mode = $('#payment_mode').val();
            payload.reference_no = $('#reference_no').val();
        }

        $.ajax({
            url: 'config/enrollment.php',
            type: 'POST',
            data: $.param(payload, true),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3500, showConfirmButton: false });
                    return;
                }

                $('#enrollmentModal').modal('hide');
                document.activeElement.blur();
                enrollmentTable.ajax.reload(null, false);
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: res.message || 'Enrolled Successfully',
                    showConfirmButton: false, timer: 3000, timerProgressBar: true
                });
            }
        });
    });

    // ===========================================================
    // Student Detail modal (Current Enrollment / Payment History / History)
    // ===========================================================
    function renderCurrentEnrollment(e) {
        let actions = '';
        if (e.status === 'enrolled') {
            actions = `
                <button class="btn btn-outline-primary btn-sm btnEditEnrollment" data-id="${e.enrollment_id}">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                </button>
                <button class="btn btn-outline-warning btn-sm btnDropTransferEnrollment" data-id="${e.enrollment_id}">
                    <i class="bi bi-box-arrow-right me-1"></i>Drop / Transfer
                </button>
                <button class="btn btn-outline-danger btn-sm btnCancelEnrollment" data-id="${e.enrollment_id}">
                    <i class="bi bi-x-circle me-1"></i>Cancel Enrollment
                </button>
            `;
        } else if (e.status === 'dropped' || e.status === 'transferred') {
            actions = `
                <button class="btn btn-outline-success btn-sm btnReactivateEnrollment" data-id="${e.enrollment_id}">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reactivate
                </button>
            `;
        }

        const map = { enrolled: 'bg-success', dropped: 'bg-danger', transferred: 'bg-warning', completed: 'bg-secondary' };
        const badge = `<span class="badge ${map[e.status] || 'bg-secondary'}">${e.status.charAt(0).toUpperCase() + e.status.slice(1)}</span>`;

        $('#currentEnrollmentBody').html(`
            <div class="row g-2 mb-2">
                <div class="col-md-4"><strong>School Year:</strong> ${esc(e.schoolyear_name)}</div>
                <div class="col-md-4"><strong>Level:</strong> ${esc(e.level_name)}</div>
                <div class="col-md-4"><strong>Section:</strong> ${esc(e.section_name)}</div>
                <div class="col-md-4"><strong>Status:</strong> ${badge}</div>
                <div class="col-md-8">${e.remarks ? '<strong>Remarks:</strong> ' + e.remarks : ''}</div>
                <div class="col-12"><strong>Subjects:</strong> ${(e.subjects || []).map(function (s) { return s.subject_name; }).join(', ') || '-'}</div>
            </div>
            <div class="d-flex gap-2 mt-2">${actions}</div>
        `);

        $('#paymentTuition').text(money(e.tuition_fee));
        $('#paymentPaid').text(money(e.total_paid));
        $('#paymentBalance').text(money(e.balance));
    }

    let paymentTable = null;
    let historyTable = null;
    const statusBadgeMap = { enrolled: 'bg-success', dropped: 'bg-danger', transferred: 'bg-warning', completed: 'bg-secondary' };

    function initPaymentTable() {
        paymentTable = $('#paymentTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No payments recorded yet." },
            ajax: {
                url: 'config/payment.php',
                type: 'POST',
                data: function (d) {
                    d.action = 'load';
                    d.enrollment_id = $('#detail_enrollment_id').val();
                    return d;
                }
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'payment_date' },
                { data: 'amount', render: function (d) { return money(d); } },
                { data: 'payment_mode' },
                { data: 'reference_no', defaultContent: '-' },
                { data: 'admin_name' }
            ]
        });
    }

    function initHistoryTable() {
        historyTable = $('#historyTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No enrollment history yet." },
            ajax: {
                url: 'config/enrollment.php',
                type: 'POST',
                data: function (d) {
                    d.action = 'history';
                    d.student_id = $('#detail_student_id').val();
                    return d;
                }
            },
            columns: [
                { data: 'schoolyear_name' },
                { data: 'level_name' },
                { data: 'section_name' },
                {
                    data: 'status',
                    render: function (data) {
                        return `<span class="badge ${statusBadgeMap[data] || 'bg-secondary'}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
                    }
                },
                { data: 'remarks', defaultContent: '-' }
            ]
        });
    }

    function refreshDetailTables() {
        if (paymentTable) {
            paymentTable.ajax.reload(null, false);
        } else {
            initPaymentTable();
        }
        if (historyTable) {
            historyTable.ajax.reload(null, false);
        } else {
            initHistoryTable();
        }
    }

    // Data loading itself happens from openDetail() directly (see below), not gated on
    // this event — only used to fix DataTables' column-width calc, which comes out wrong
    // if a table is ever measured while its modal is still hidden.
    $(document).on('shown.bs.modal', '#detailModal', function () {
        if (paymentTable) paymentTable.columns.adjust();
        if (historyTable) historyTable.columns.adjust();
    });

    function refreshCurrentEnrollment(enrollment_id) {
        $.ajax({
            url: 'config/enrollment.php',
            type: 'POST',
            data: { action: 'get', enrollment_id: enrollment_id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') return;
                renderCurrentEnrollment(res.data);
            }
        });
    }

    function openDetail(enrollment_id, student_id) {
        $('#detail_enrollment_id').val(enrollment_id);
        $('#detail_student_id').val(student_id);
        $('#paymentForm').addClass('d-none')[0].reset();

        $.ajax({
            url: 'config/enrollment.php',
            type: 'POST',
            data: { action: 'get', enrollment_id: enrollment_id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                const e = res.data;
                $('#detailStudentName').text(`${e.last_name}, ${e.first_name}`);
                renderCurrentEnrollment(e);
                refreshDetailTables();

                $('#detailModal').modal('show');
            }
        });
    }

    $(document).on('click', '.btnViewEnrollment', function () {
        openDetail($(this).data('id'), $(this).data('student'));
    });

    $(document).on('click', '#btnViewFullProfile', function () {
        const student_id = $('#detail_student_id').val();
        if (!student_id) return;
        window.open('student_profile.html?student_id=' + encodeURIComponent(student_id), '_blank');
    });

    // ===========================================================
    // Edit Enrollment (tuition / section / subjects)
    // ===========================================================
    $(document).on('click', '.btnEditEnrollment', function () {
        const enrollment_id = $(this).data('id');

        $.ajax({
            url: 'config/enrollment.php',
            type: 'POST',
            data: { action: 'get', enrollment_id: enrollment_id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }
                const e = res.data;
                const level = levelsCache.find(function (l) { return String(l.level_id) === String(e.level_id); });
                const selectable = level && level.allows_subject_selection == 1;

                $('#edit_enrollment_id').val(e.enrollment_id);
                $('#edit_level_display').val(e.level_name);
                $('#edit_tuition_fee').val(parseFloat(e.tuition_fee));
                $('#edit_paid_hint').text('Already paid: ' + money(e.total_paid));
                $('#edit_reason').val('');
                $('#edit_section_id').html('<option value="">Loading...</option>');
                $('#editSubjectsPick').empty();
                $('#editSubjectsFixed').addClass('d-none').text('');
                $('#editEnrollmentForm').data('selectable', selectable ? 1 : 0);

                $.ajax({
                    url: 'config/section.php', type: 'POST', dataType: 'json',
                    data: { action: 'load', level_id: e.level_id },
                    success: function (sr) {
                        let options = '';
                        (sr.data || []).forEach(function (sec) {
                            options += `<option value="${sec.section_id}">${esc(sec.section_name)}</option>`;
                        });
                        if (!(sr.data || []).some(function (sec) { return String(sec.section_id) === String(e.section_id); })) {
                            options = `<option value="${e.section_id}">${esc(e.section_name)} (archived)</option>` + options;
                        }
                        $('#edit_section_id').html(options).val(String(e.section_id));
                    }
                });

                $.ajax({
                    url: 'config/subject.php', type: 'POST', dataType: 'json',
                    data: { action: 'load', level_id: e.level_id },
                    success: function (sr) {
                        const subjects = sr.data || [];
                        const have = (e.subjects || []).map(function (s) { return String(s.subject_id); });
                        if (selectable) {
                            let boxes = '';
                            subjects.forEach(function (sub) {
                                boxes += `
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input edit-subject-box" type="checkbox" value="${sub.subject_id}" id="esub_${sub.subject_id}" ${have.indexOf(String(sub.subject_id)) !== -1 ? 'checked' : ''}>
                                            <label class="form-check-label" for="esub_${sub.subject_id}">${esc(sub.subject_name)}${sub.subject_code ? ' (' + esc(sub.subject_code) + ')' : ''}</label>
                                        </div>
                                    </div>`;
                            });
                            $('#editSubjectsPick').html(boxes || '<div class="text-muted small">No subjects configured for this level.</div>');
                        } else {
                            $('#editSubjectsFixed').removeClass('d-none').text(
                                (subjects.map(function (s) { return s.subject_name; }).join(', ') || 'None') +
                                '  - all of this level\'s subjects, kept up to date automatically.');
                        }
                    }
                });

                $('#editEnrollmentModal').modal('show');
            }
        });
    });

    $('#editEnrollmentForm').submit(function (ev) {
        ev.preventDefault();
        const enrollment_id = $('#edit_enrollment_id').val();
        const payload = {
            action: 'update',
            enrollment_id: enrollment_id,
            section_id: $('#edit_section_id').val(),
            tuition_fee: $('#edit_tuition_fee').val(),
            reason: $('#edit_reason').val()
        };
        if ($(this).data('selectable') == 1) {
            payload.subjects_sent = '1';
            payload.subject_ids = $('.edit-subject-box:checked').map(function () { return this.value; }).get();
        }

        $.ajax({
            url: 'config/enrollment.php',
            type: 'POST',
            data: $.param(payload, true),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 5000, showConfirmButton: false });
                    return;
                }
                $('#editEnrollmentModal').modal('hide');
                enrollmentTable.ajax.reload(null, false);
                refreshCurrentEnrollment(enrollment_id);
                refreshDetailTables();
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: res.message, showConfirmButton: false, timer: 3000, timerProgressBar: true
                });
            }
        });
    });

    // ===========================================================
    // Add Payment
    // ===========================================================
    $(document).on('click', '#btnAddPayment', function () {
        $('#payment_date').val(new Date().toISOString().slice(0, 10));
        $('#paymentForm').removeClass('d-none');
    });

    $('#paymentForm').submit(function (e) {
        e.preventDefault();

        const enrollment_id = $('#detail_enrollment_id').val();

        $.ajax({
            url: 'config/payment.php',
            type: 'POST',
            data: {
                action: 'add',
                enrollment_id: enrollment_id,
                payment_date: $('#payment_date').val(),
                amount: $('#payment_amount').val(),
                payment_mode: $('#payment_mode_add').val(),
                reference_no: $('#payment_reference_no').val()
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                $('#paymentForm')[0].reset();
                $('#paymentForm').addClass('d-none');
                refreshCurrentEnrollment(enrollment_id);
                refreshDetailTables();
                enrollmentTable.ajax.reload(null, false);
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Payment Recorded', showConfirmButton: false, timer: 3000, timerProgressBar: true
                });
            }
        });
    });

    // ===========================================================
    // Cancel / Drop-Transfer / Reactivate
    // ===========================================================
    $(document).on('click', '.btnCancelEnrollment', function () {
        const enrollment_id = $(this).data('id');

        Swal.fire({
            title: 'Cancel Enrollment?',
            text: 'This permanently removes this enrollment and its subject list. Only possible when no payments have been recorded.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Cancel Enrollment',
            cancelButtonText: 'Back'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'config/enrollment.php',
                type: 'POST',
                data: { action: 'cancel', enrollment_id: enrollment_id },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'error') {
                        Swal.fire({ icon: 'error', title: res.message, timer: 4000, showConfirmButton: false });
                        return;
                    }

                    $('#detailModal').modal('hide');
                    enrollmentTable.ajax.reload(null, false);
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: 'Enrollment Cancelled', showConfirmButton: false, timer: 3000, timerProgressBar: true
                    });
                }
            });
        });
    });

    $(document).on('click', '.btnDropTransferEnrollment', function () {
        const enrollment_id = $(this).data('id');

        Swal.fire({
            title: 'Drop or Transfer Student',
            html: `
                <select id="swalStatus" class="form-select mb-2">
                    <option value="dropped">Dropped</option>
                    <option value="transferred">Transferred</option>
                </select>
                <textarea id="swalRemarks" class="form-control" placeholder="Remarks (e.g. transferred to ABC School)"></textarea>
            `,
            showCancelButton: true,
            confirmButtonText: 'Save',
            confirmButtonColor: '#12a480',
            preConfirm: function () {
                return {
                    status: document.getElementById('swalStatus').value,
                    remarks: document.getElementById('swalRemarks').value
                };
            }
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: 'config/enrollment.php',
                type: 'POST',
                data: { action: 'drop_transfer', enrollment_id: enrollment_id, status: result.value.status, remarks: result.value.remarks },
                dataType: 'json',
                success: function (res) {
                    if (res.status === 'error') {
                        Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                        return;
                    }

                    enrollmentTable.ajax.reload(null, false);
                    refreshCurrentEnrollment(enrollment_id);
                    refreshDetailTables();
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'success',
                        title: res.message, showConfirmButton: false, timer: 3000, timerProgressBar: true
                    });
                }
            });
        });
    });

    $(document).on('click', '.btnReactivateEnrollment', function () {
        const enrollment_id = $(this).data('id');

        $.ajax({
            url: 'config/enrollment.php',
            type: 'POST',
            data: { action: 'reactivate', enrollment_id: enrollment_id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                enrollmentTable.ajax.reload(null, false);
                refreshCurrentEnrollment(enrollment_id);
                refreshDetailTables();
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'success',
                    title: 'Enrollment Reactivated', showConfirmButton: false, timer: 3000, timerProgressBar: true
                });
            }
        });
    });

});
