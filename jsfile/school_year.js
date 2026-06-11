$(document).ready(function () {
let table = $('#schoolYearTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
             language: {
                lengthMenu: "Show _MENU_ entries"
            },
            ajax: {
                url: 'config/school_year_load.php',
                type: 'POST'
            },
            order: [[2, 'desc']],
            columns: [
                { data: 'schoolyear_name', className: 'text-center' },
                { data: 'year_start', className: 'text-center' },
                { data: 'year_end', className: 'text-center' },
                {
                    data: 'status',
                    className: 'text-center',
                    render: function(data) {

                        if(data === '1'){
                            return '<span class="badge text-bg-success">Active</span>';
                        }

                        return '<span class="badge text-bg-secondary">Inactive</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data){

                        return `
                            <button class="btn btn-info btn-sm btnEdit"
                                    data-id="${data.schoolyear_id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        `;
                    }
                }
            ]
        });

$('#btnAdd').click(function(){
        $('#schoolYearForm')[0].reset();
        $('#schoolyear_id').val('');
        $('.modal-title').text('Add School Year');
        $('#schoolYearModal').modal('show');
       });

$(document).on('click', '.btnEdit', function(){
         let schoolyear_id = $(this).data('id');
            $.ajax({
                url: 'config/school_year_get.php',
                type: 'POST',
                data: {
                    schoolyear_id: schoolyear_id
                },
                dataType: 'json',
                success: function(data){

                    $('#schoolyear_id').val(data.schoolyear_id);
                    $('#schoolyear_name').val(data.schoolyear_name);
                    $('#year_start').val(data.year_start);
                    $('#year_end').val(data.year_end);
                    $('#status').val(data.status);

                    $('.modal-title').text('Edit School Year');

                    $('#schoolYearModal').modal('show');
                }
            });
        });

$('#schoolYearForm').submit(function(e){
             e.preventDefault();

    $.ajax({
        url: 'config/school_year_add.php',
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

});