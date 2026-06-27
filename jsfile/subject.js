$(document).ready(function () {
    let subjectTable;

    function showSubjectModal() {
        const el = document.getElementById('subjectModal');
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).show();
    }

    function hideSubjectModal() {
        const el = document.getElementById('subjectModal');
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).hide();
    }

    $(document).off('click', '#btnclosecard');
    $(document).off('click', '#btnAddSubject');
    $(document).off('submit', '#subjectForm');
    $(document).off('click', '.btnEditSubject');

    function initSubjectTable() {
        if ($.fn.DataTable.isDataTable('#subjectTable')) {
            subjectTable = $('#subjectTable').DataTable();
            return;
        }

        subjectTable = $('#subjectTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            autoWidth: false,
            paging: false,
            info: false,
            lengthChange: false,
            ordering: false,
            language: {
                lengthMenu: 'Show _MENU_ entries'
            },
            ajax: {
                url: 'config/subject_load.php',
                type: 'POST',
                data: function () {
                    return {
                        level_id: $('#activeLevelId').val()
                    };
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'subject_name' },
                { data: 'subject_code', className: 'text-center' },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btnEditSubject" data-id="${data.subject_id}" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

    $(document).on('click', '#btnclosecard', function () {
        $('#content_subject')
            .removeClass('col-md-6')
            .addClass('col-md-12 mt-3 d-none');

        $('html, body').animate({ scrollTop: 0 }, 100);
        $('#content_subject').empty();
    });

    $(document).on('click', '#btnAddSubject', function () {
        $('#subjectForm')[0].reset();
        $('#subject_id').val('');
        $('#subjectModal .modal-title').text('Add New Subject');
        showSubjectModal();
    });

    $(document).on('submit', '#subjectForm', function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/subject_add.php',
            type: 'POST',
            data: $(this).serialize() + '&level_id=' + $('#activeLevelId').val(),
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

                hideSubjectModal();
                document.activeElement.blur();

                if (subjectTable) {
                    subjectTable.ajax.reload(null, false);
                }

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

    $(document).on('click', '.btnEditSubject', function () {
        const subject_id = $(this).data('id');

        $.ajax({
            url: 'config/subject_get.php',
            type: 'POST',
            data: { subject_id: subject_id },
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

                $('#subject_id').val(data.subject_id || '');
                $('#subject_name').val(data.subject_name || '');
                $('#subject_code').val(data.subject_code || '');
                $('#subjectModal .modal-title').text('Edit Subject');
                showSubjectModal();
            }
        });
    });

    initSubjectTable();
});