$(document).ready(function () {

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddSchoolYear');
    $(document).off('submit', '#schoolYearForm');
    $(document).off('click', '.btnEditSchoolYear');
    $(document).off('click', '.btnDeleteSchoolYear');
    $(document).off('click', '.btnActivateSchoolYear');

    if ($.fn.DataTable.isDataTable('#schoolYearTable')) {
        $('#schoolYearTable').DataTable().destroy();
        $('#schoolYearTable tbody').empty();
    }

    let table = $('#schoolYearTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: {
            lengthMenu: "Show _MENU_ entries"
        },
        ajax: {
            url: 'config/schoolyear.php',
            type: 'POST',
            data: function (d) {
                d.action = 'load';
                return d;
            }
        },
        columns: [
            { data: 'schoolyear_name' },
            {
                data: 'status',
                render: function (data) {
                    return data === 'active'
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                render: function (data) {
                    const activateBtn = data.status === 'active'
                        ? ''
                        : `<button class="btn btn-outline-success btn-sm btnActivateSchoolYear" data-id="${data.schoolyear_id}" title="Set Active">
                               <i class="bi bi-check-circle"></i>
                           </button>`;
                    const deleteBtn = data.status === 'active'
                        ? ''
                        : `<button class="btn btn-outline-danger btn-sm btnDeleteSchoolYear" data-id="${data.schoolyear_id}" title="Delete">
                               <i class="bi bi-trash"></i>
                           </button>`;

                    return `
                        <div class="btn-group btn-group-sm">
                            ${activateBtn}
                            <button class="btn btn-outline-secondary btn-sm btnEditSchoolYear" data-id="${data.schoolyear_id}" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            ${deleteBtn}
                        </div>
                    `;
                }
            }
        ]
    });

    $('#btnAddSchoolYear').click(function () {
        $('#schoolYearForm')[0].reset();
        $('#schoolyear_id').val('');
        $('.modal-title').text('Add School Year');
        $('#schoolYearModal').modal('show');
    });

    $(document).on('click', '.btnEditSchoolYear', function () {
        let schoolyear_id = $(this).data('id');
        $.ajax({
            url: 'config/schoolyear.php',
            type: 'POST',
            data: {
                action: 'get',
                schoolyear_id: schoolyear_id
            },
            dataType: 'json',
            success: function (data) {
                $('#schoolyear_id').val(data.schoolyear_id);
                $('#schoolyear_name').val(data.schoolyear_name);
                $('.modal-title').text('Edit School Year');

                $('#schoolYearModal').modal('show');
            }
        });
    });

    $('#schoolYearForm').submit(function (e) {
        e.preventDefault();

        $.ajax({
            url: 'config/schoolyear.php',
            type: 'POST',
            data: $(this).serialize() + '&action=add',
            dataType: 'json',
            success: function (res) {
                if (res.status === "error") {
                    Swal.fire({
                        icon: 'error',
                        title: res.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                $('#schoolYearModal').modal('hide');
                document.activeElement.blur();
                $('#schoolYearTable').DataTable().ajax.reload(null, false);
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

    $(document).on('click', '.btnActivateSchoolYear', function () {
        let schoolyear_id = $(this).data('id');

        Swal.fire({
            title: 'Set as Active School Year?',
            text: 'This will deactivate the currently active school year.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#12a480',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Activate',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: 'config/schoolyear.php',
                type: 'POST',
                data: {
                    action: 'activate',
                    schoolyear_id: schoolyear_id
                },
                dataType: 'json',
                success: function (res) {
                    if (res.status === "error") {
                        Swal.fire({
                            icon: 'error',
                            title: res.message,
                            timer: 3000,
                            showConfirmButton: false
                        });
                        return;
                    }

                    document.activeElement.blur();
                    $('#schoolYearTable').DataTable().ajax.reload(null, false);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'School Year Activated',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                }
            });
        });
    });

    $(document).on('click', '.btnDeleteSchoolYear', function () {
        let schoolyear_id = $(this).data('id');

        Swal.fire({
            title: 'Delete School Year?',
            text: 'Are you sure you want to delete this school year?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'config/schoolyear.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        schoolyear_id: schoolyear_id
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === "error") {
                            Swal.fire({
                                icon: 'error',
                                title: res.message,
                                timer: 3000,
                                showConfirmButton: false
                            });
                            return;
                        }

                        document.activeElement.blur();
                        $('#schoolYearTable').DataTable().ajax.reload(null, false);
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Deleted Successfully',
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
