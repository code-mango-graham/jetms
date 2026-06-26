$(document).ready(function () {

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddposition');
    $(document).off('submit', '#positionForm');
    $(document).off('click', '.btnEditposition');
    $(document).off('click', '.btnDeleteposition');

let table = $('#positionTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
             language: {
                lengthMenu: "Show _MENU_ entries"
            },
            ajax: {
                url: 'config/position_load.php',
                type: 'POST'
            },
            columns: [
                { data: 'position_title' },
                { data: 'description' },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data){

                        return `
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btn-sm btnEditposition"
                                        data-id="${data.position_id}"
                                        title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm btnDeleteposition"
                                        data-id="${data.position_id}"
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
        $('#positionForm')[0].reset();
        $('#position_id').val('');
        $('.modal-title').text('Add Position');
        $('#positionModal').modal('show');
       });

$(document).on('click', '.btnEditposition', function(){
         let position_id = $(this).data('id');
            $.ajax({
                url: 'config/position_get.php',
                type: 'POST',
                data: {
                    position_id: position_id
                },
                dataType: 'json',
                success: function(data){

                    $('#position_id').val(data.position_id);
                    $('#position_title').val(data.position_title);
                    $('#description').val(data.description);
                    $('.modal-title').text('Edit Position');

                    $('#positionModal').modal('show');
                }
            });
        });

$('#positionForm').submit(function(e){
             e.preventDefault();

    $.ajax({
        url: 'config/position_add.php',
        type: 'POST',
        data: $(this).serialize(),
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

            $('#positionModal').modal('hide');
            document.activeElement.blur(); 
            $('#positionTable').DataTable().ajax.reload(null, false);
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

$(document).on('click', '.btnDeleteposition', function(){
         let position_id = $(this).data('id');

         Swal.fire({
            title: 'Delete Position?',
            text: 'Are you sure you want to delete this position?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {

                $.ajax({
                    url: 'config/position_delete.php',
                    type: 'POST',
                    data: {
                        position_id: position_id
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
                        $('#positionTable').DataTable().ajax.reload(null, false);
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
