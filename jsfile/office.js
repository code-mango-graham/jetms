$(document).ready(function () {

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddOffice');
    $(document).off('submit', '#officeForm');
    $(document).off('click', '.btnEditOffice');
    $(document).off('click', '.btnDeleteOffice');

if ($.fn.DataTable.isDataTable('#officeTable')) {
    $('#officeTable').DataTable().destroy();
    $('#officeTable tbody').empty();
}

let table = $('#officeTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
             language: {
                lengthMenu: "Show _MENU_ entries"
            },
            ajax: {
                url: 'config/office.php',
                type: 'POST',
                data: function (d) {
                    d.action = 'load';
                    return d;
                }
            },
            columns: [
                { data: 'office_name' },
                { data: 'office_description' },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data){

                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btn-sm btnEditOffice"
                                        data-id="${data.office_id}"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm btnDeleteOffice"
                                        data-id="${data.office_id}"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        });

$('#btnAdd').click(function(){
        $('#officeForm')[0].reset();
        $('#office_id').val('');
        $('.modal-title').text('Add Office');
        $('#officeModal').modal('show');
       });

$(document).on('click', '.btnEditOffice', function(){
         let office_id = $(this).data('id');
            $.ajax({
                url: 'config/office.php',
                type: 'POST',
                data: {
                    action: 'get',
                    office_id: office_id
                },
                dataType: 'json',
                success: function(data){

                    $('#office_id').val(data.office_id);
                    $('#office_name').val(data.office_name);
                    $('#office_description').val(data.office_description);
                    $('.modal-title').text('Edit Office');

                    $('#officeModal').modal('show');
                }
            });
        });

$('#officeForm').submit(function(e){
             e.preventDefault();

    $.ajax({
        url: 'config/office.php',
        type: 'POST',
        data: $(this).serialize() + '&action=add',
        dataType: 'json',
        success: function(res){

            if(res.status === "error"){

                Swal.fire({
                    icon: 'error',
                    title: res.message,
                    timer: 3000,
                    showConfirmButton: false
                });

                return;
            }

            $('#officeModal').modal('hide');
            document.activeElement.blur(); 
            $('#officeTable').DataTable().ajax.reload(null, false);
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

$(document).on('click', '.btnDeleteOffice', function(){
         let office_id = $(this).data('id');

         Swal.fire({
            title: 'Delete Office?',
            text: 'Are you sure you want to delete this office?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {

                $.ajax({
                    url: 'config/office.php',
                    type: 'POST',
                    data: {
                        action: 'delete',
                        office_id: office_id
                    },
                    dataType: 'json',
                    success: function(res){

                        if(res.status === "error"){

                            Swal.fire({
                                icon: 'error',
                                title: res.message,
                                timer: 3000,
                                showConfirmButton: false
                            });

                            return;
                        }

                        document.activeElement.blur();
                        $('#officeTable').DataTable().ajax.reload(null, false);
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