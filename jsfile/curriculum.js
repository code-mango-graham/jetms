$(document).ready(function () {

    function openCurriculumModal() {
        const el = document.getElementById('curriculumModal');
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).show();
    }

    function closeCurriculumModal() {
        const el = document.getElementById('curriculumModal');
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).hide();
    }

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddCurriculum');
    $(document).off('submit', '#curriculumForm');
    $(document).off('click', '.btnEditcurriculum');
    $(document).off('click', '.btnManageLevels');
    $(document).off('click', '.btnViewCurriculumTree');

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
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary btn-sm btnEditcur"
                                        data-id="${data.curriculum_id}"
                                        title="Edit Curriculum">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm btnManageLevels"
                                        data-id="${data.curriculum_id}"
                                        data-name="${data.curriculum_name}"
                                        title="Manage Levels">
                                    <i class="bi bi-list-nested"></i>
                                </button>
                                <button class="btn btn-outline-success btn-sm btnViewCurriculumTree"
                                        data-id="${data.curriculum_id}"
                                        title="View Whole Curriculum">
                                    <i class="bi bi-diagram-3"></i>
                                </button>
                            </div>
                        `;
                    }
                }
            ]
        });

$('#btnAdd').click(function(){
        $('#curriculumForm')[0].reset();
        $('#curriculum_id').val('');
        $('.modal-title').text('Add Curriculum');
    openCurriculumModal();
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

                    openCurriculumModal();
                }
            });
        });

$(document).on('click', '.btnManageLevels', function () {
        const curriculum_id = $(this).data('id');
        const curriculum_name = $(this).data('name');

        $('#content_level')
            .removeClass('col-md-12 d-none')
            .addClass('col-md-6 mt-3');

        $('#content_subject')
            .removeClass('col-md-6')
            .addClass('col-md-6 mt-3 d-none')
            .empty();

        $('#content_level').empty();
        $('#content_level').load('pages/level.html', function () {
            $('#activeCurriculumId').val(curriculum_id);
            $('#activeCurriculum').text(curriculum_name);
        });

        $('html, body').animate({
            scrollTop: $('#content_level').offset().top
        }, 100);
    });

$(document).on('click', '.btnViewCurriculumTree', function () {
        const curriculum_id = $(this).data('id');
        const url = `pages/curriculum_tree.html?curriculum_id=${encodeURIComponent(curriculum_id)}`;
        window.open(url, '_blank');
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

            closeCurriculumModal();
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