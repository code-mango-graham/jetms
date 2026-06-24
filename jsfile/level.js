$(document).ready(function () {

    let table;

    /* =========================
       LOAD ACTIVE CURRICULUM
    ========================= */
    function loadActiveCurriculum(callback) {
        $.ajax({
            url: "config/get_active_curriculum.php",
            type: "GET",
            dataType: "json",
            success: function (data) {

                if (data) {
                    $("#activeCurriculum").text(data.curriculum_name);
                    $("#activeCurriculumId").val(data.curriculum_id);
                } else {
                    $("#activeCurriculum").text("No active curriculum");
                    $("#activeCurriculumId").val("");
                }

                if (callback) callback(); // FIXED
            }
        });
    }


    /* =========================
       INIT DATATABLE
    ========================= */
    function initTable() {

        table = $('#levelTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            language: {
                lengthMenu: "Show _MENU_ entries"
            },
            ajax: {
                url: 'config/level_load.php',
                type: 'POST',
                data: function () {
                    return {
                        curriculum_id: $('#activeCurriculumId').val()
                    };
                },
                dataSrc: 'data'
            },

            columns: [
                { data: 'level_name', className: 'text-center' },
                {
                    data: 'level_type',
                    className: 'text-center',
                    render: function (data) {

                        switch (parseInt(data)) {
                            case 1:
                                return "Kindergarden";
                            case 2:
                                return "Primary Education";
                            case 3:
                                return "Junior High School";
                            case 4:
                                return "Senior High School";
                            default:
                                return "Unknown";
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-info btnEdit" data-id="${data.level_id}" title = "Edit"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-outline-secondary btnGradedata" data-id="${data.level_id}" data-name="${data.level_name}" title = "View Level Data"><i class="bi bi-journal-bookmark"></i></a>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

/* ========================= */
loadActiveCurriculum(function () {
        initTable();
    });


    /* =========================
       ADD BUTTON
    ========================= */
$('#btnAdd').click(function () {
        $('#levelForm')[0].reset();
        $('#level_id').val('');
        $('.modal-title').text('Add New Level');
        $('#levelModal').modal('show');
    });


    /* =========================
       EDIT BUTTON )
    ========================= */
$(document).on('click', '.btnEdit', function(){
         let level_id = $(this).data('id');
            $.ajax({
                url: 'config/level_get.php',
                type: 'POST',
                data: {
                    level_id: level_id
                },
                dataType: 'json',
                success: function(data){

                    $('#level_id').val(data.level_id);
                    $('#level_name').val(data.level_name);
                    $('#level_type').val(data.level_type);
                    $('.modal-title').text('Edit Grade/level');
                    $('#levelModal').modal('show');
                }
            });
        });

    /* =========================
       SAVE FORM
    ========================= */
$('#levelForm').submit(function (e) {
        e.preventDefault();
       
        $.ajax({
            url: 'config/level_add.php',
            type: 'POST',
            data: $(this).serialize() + '&curriculum_id=' + $('#activeCurriculumId').val(),
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

                $('#levelModal').modal('hide');
                document.activeElement.blur(); 
                $('#levelTable').DataTable().ajax.reload(null, false);// FIXED TABLE

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
/* =========================*/
       
$(document).on('click', '.btnGradedata', function () {
    let level_id = $(this).data('id');
    let level_name = $(this).data('name');
    $('#content_level').empty();
    $('#content_level').load('pages/subjects.html', function () {
        $('#activeLevelId').val(level_id);
        $('#activeLevel').text(level_name);
    });
     $('html, body').animate({
        scrollTop: $('#content_level').offset().top
    }, 100);
});


});