$(document).ready(function () {

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddCurriculum');
    $(document).off('submit', '#curriculumForm');
    $(document).off('click', '.btnEditcurriculum');

let table = $('#curriculumTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
             language: {
                lengthMenu: "Show _MENU_ entries"
            },
            ajax: {
                url: 'config/curriculum_load.php',
                type: 'POST'
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'curriculum_name' },
                { data: 'description' },
                {
                    data: 'status',
                    className: 'text-center',
                    render: function(data) {

                        if(data === '1'){
                            return '<span class="badge text-bg-success">Active</span>';
                        }

                        return '<span class="badge text-bg-light">Inactive</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data){

                        return `
                            <button class="btn btn-outline-secondary btn-sm btnEditcur"
                                    data-id="${data.curriculum_id}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        `;
                    }
                }
            ]
        });

$('#btnAdd').click(function(){
        $('#curriculumForm')[0].reset();
        $('#curriculum_id').val('');
        $('.modal-title').text('Add Curriculum');
        $('#curriculumModal').modal('show');
       });

$(document).on('click', '.btnEditcur', function(){
         let curriculum_id = $(this).data('id');
            $.ajax({
                url: 'config/curriculum_get.php',
                type: 'POST',
                data: {
                    curriculum_id: curriculum_id
                },
                dataType: 'json',
                success: function(data){

                    $('#curriculum_id').val(data.curriculum_id);
                    $('#curriculum_name').val(data.curriculum_name);
                    $('#description').val(data.description);
                    $('#status').val(data.status);

                    $('.modal-title').text('Edit Curriculum');

                    $('#curriculumModal').modal('show');
                }
            });
        });

$('#curriculumForm').submit(function(e){
             e.preventDefault();

    $.ajax({
        url: 'config/curriculum_add.php',
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

            $('#curriculumModal').modal('hide');
            document.activeElement.blur(); 
            $('#curriculumTable').DataTable().ajax.reload(null, false);
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