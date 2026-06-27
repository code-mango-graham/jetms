$(document).ready(function () {

    let table;

    function showLevelModal() {
        const el = document.getElementById('levelModal');
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).show();
    }

    function hideLevelModal() {
        const el = document.getElementById('levelModal');
        if (!el) return;
        bootstrap.Modal.getOrCreateInstance(el).hide();
    }

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnAddLevel');
    $(document).off('click', '#btnCloseLevel');
    $(document).off('submit', '#levelForm');
    $(document).off('click', '.btnEdit');
    $(document).off('click', '.btnGradedata');

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

    if ($.fn.DataTable.isDataTable('#levelTable')) {
        return;
    }

        table = $('#levelTable').DataTable({
            processing: true,
            responsive: true,
            scrollX: true,
            order: [[1, 'asc'], [0, 'asc']],
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
                    render: function (data, type) {

                        if (type === 'sort' || type === 'type') {
                            return parseInt(data, 10) || 0;
                        }

                        switch (parseInt(data)) {
                            case 1:
                                return "Kindergarten";
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
                            <a class="btn btn-outline-secondary btnEdit" data-id="${data.level_id}" title = "Edit"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-outline-secondary btnGradedata" data-id="${data.level_id}" data-name="${data.level_name}" title = "View Level Data"><i class="bi bi-journal-bookmark"></i></a>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

/* ========================= */
if ($('#activeCurriculumId').val()) {
        initTable();
    } else {
        loadActiveCurriculum(function () {
            initTable();
        });
    }


    /* =========================
       ADD BUTTON
    ========================= */
$(document).on('click', '#btnAddLevel', function () {
        $('#levelForm')[0].reset();
        $('#level_id').val('');
    $('#levelModal .modal-title').text('Add New Level');
    showLevelModal();
    });

$(document).on('click', '#btnCloseLevel', function () {
        $('#content_subject')
            .removeClass('col-md-6')
            .addClass('col-md-12 mt-3 d-none')
            .empty();

        $('#content_level')
            .removeClass('col-md-6')
            .addClass('col-md-12 mt-3 d-none')
            .empty();

        $('html, body').animate({ scrollTop: 0 }, 100);
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
                    $('#levelModal .modal-title').text('Edit Grade/Level');
                    showLevelModal();
                }
            });
        });

    /* =========================
       SAVE FORM
    ========================= */
$(document).on('submit', '#levelForm', function (e) {
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

                hideLevelModal();
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

    $('#content_level')
        .removeClass('d-none col-md-12')
        .addClass('col-md-6 mt-3');

    $('#content_subject')
        .removeClass('d-none col-md-12')
        .addClass('col-md-6 mt-3');

    $('#content_subject').empty();
    $('#content_subject').load('pages/subjects.html', function () {
        $('#activeLevelId').val(level_id);
        $('#activeLevel').text(level_name);
    });
     $('html, body').animate({
        scrollTop: $('#content_level').offset().top
    }, 100);
});



});