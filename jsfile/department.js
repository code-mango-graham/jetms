$(document).ready(function () {
let table = $('#departmentTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
             language: {
                lengthMenu: "Show _MENU_ entries"
            },
            ajax: {
                url: 'config/department_load.php',
                type: 'POST'
            },
            columns: [
                { data: 'department_name' },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data){

                        return `
                            <button class="btn btn-outline-info btn-sm btnEditdep"
                                    data-id="${data.department_id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        `;
                    }
                }
            ]
        });

$('#btnAdd').click(function(){
        $('#departmentForm')[0].reset();
        $('#department_id').val('');
        $('.modal-title').text('Add Department');
        $('#departmentModal').modal('show');
       });

$(document).on('click', '.btnEditdep', function(){
         let department_id = $(this).data('id');
            $.ajax({
                url: 'config/department_get.php',
                type: 'POST',
                data: {
                    department_id: department_id
                },
                dataType: 'json',
                success: function(data){

                    $('#department_id').val(data.department_id);
                    $('#department_name').val(data.department_name);
                    $('.modal-title').text('Edit Department/Office');

                    $('#departmentModal').modal('show');
                }
            });
        });

$('#departmentForm').submit(function(e){
             e.preventDefault();

    $.ajax({
        url: 'config/department_add.php',
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

            $('#departmentModal').modal('hide');
            document.activeElement.blur(); 
            $('#departmentTable').DataTable().ajax.reload(null, false);
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

});