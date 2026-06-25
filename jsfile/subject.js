$(document).ready(function () {
    let table1;
    let table2;
    console.log('subject.js loaded');
    
    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#btnclosecard');
    $(document).off('click', '#btnAddSection');
    $(document).off('click', '#btnAddSubject');
    $(document).off('submit', '#sectionForm');
    $(document).off('submit', '#subjectForm');
    $(document).off('click', '.btnEdit1');
    $(document).off('click', '.btnEdit2');
    
    function initTable() {
         if ($.fn.DataTable.isDataTable('#sectionsTable')) {
        table1 = $('#sectionsTable').DataTable();
        return;
    }
       
        table1 = $('#sectionsTable').DataTable({
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            language: {
                lengthMenu: "Show _MENU_ entries"
            },
            ajax: {
                url: 'config/section_load.php',
                type: 'POST',
                data: function () {
                    return {
                        level_id: $('#activeLevelId').val()
                    };
                },
                dataSrc: 'data'
            },

            columns: [
                { data: 'section_name'},
                {
                    data: null,
                    className: 'text-center',
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-secondary btnEdit1" data-id="${data.section_id}" title = "Edit"><i class="bi bi-pencil"></i></a>
                            </div>
                        `;
                    }
                }
            ]
        });
    }

     function initTable2() {
          if ($.fn.DataTable.isDataTable('#subjectTable')) {
        table2 = $('#subjectTable').DataTable();
        return;
    }
       
        table2 = $('#subjectTable').DataTable({
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            language: {
                lengthMenu: "Show _MENU_ entries"
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
                { data: 'subject_name'},
                { data: 'subject_code', className: 'text-center' },
                {
                    data: null,
                    className: 'text-center',
                    render: function (data) {
                        return `
                            <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-secondary btnEdit2" data-id="${data.subject_id}" title = "Edit"><i class="bi bi-pencil"></i></a>
                            </div>
                        `;
                    }
                }
            ]
        });
    }


/* ========================= */

$(document).on('click', '#btnclosecard', function () {
        $('html, body').animate({
            scrollTop: 0
        }, 100);
        $("#content_level").empty();
        });


$(document).on('click', '#btnAddSection', function () {
        $('#sectionForm')[0].reset();
        $('#section_id').val('');
        $('.modal-title').text('Add New Section');
        $('#sectionModal').modal('show');
    });

$(document).on('click', '#btnAddSubject', function () {
        $('#subjectForm')[0].reset();
        $('#subject_id').val('');
        $('#subjectModal .modal-title').text('Add New Subject');
        $('#subjectModal').modal('show');
    });

     /* =========================
       SAVE FORM
    ========================= */
$(document).on('submit', '#sectionForm', function (e) {
        e.preventDefault();
       
        $.ajax({
            url: 'config/section_add.php',
            type: 'POST',
            data: $(this).serialize() + '&level_id=' + $('#activeLevelId').val(),
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

                $('#sectionModal').modal('hide');
                document.activeElement.blur(); 
                if ($.fn.DataTable.isDataTable('#sectionsTable')) {
                    $('#sectionsTable').DataTable().ajax.reload(null, false);
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
/* =========================*/

$(document).on('submit', '#subjectForm', function (e) {
        e.preventDefault();
       
        $.ajax({
            url: 'config/subject_add.php',
            type: 'POST',
            data: $(this).serialize() + '&level_id=' + $('#activeLevelId').val(),
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

                $('#subjectModal').modal('hide');
                document.activeElement.blur(); 
                if ($.fn.DataTable.isDataTable('#subjectTable')) {
                    $('#subjectTable').DataTable().ajax.reload(null, false);
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
/* =========================*/   

$(document).on('click', '.btnEdit2', function(){
         let subject_id = $(this).data('id');
            $.ajax({
                url: 'config/subject_get.php',
                type: 'POST',
                data: {
                    subject_id: subject_id
                },
                dataType: 'json',
                success: function(data){

                    $('#subject_id').val(data.subject_id);
                    $('#subject_name').val(data.subject_name);
                    $('#subject_code').val(data.subject_code);
                    $('#subjectModal .modal-title').text('Edit Subject');
                    $('#subjectModal').modal('show');
                }
            });
        });
/* =========================*/   

$(document).on('click', '.btnEdit1', function(){
         let section_id = $(this).data('id');
            $.ajax({
                url: 'config/section_get.php',
                type: 'POST',
                data: {
                    section_id: section_id
                },
                dataType: 'json',
                success: function(data){

                    $('#section_id').val(data.section_id);
                    $('#section_name').val(data.section_name);
                    $('.modal-title').text('Edit Section');
                    $('#sectionModal').modal('show');
                }
            });
        });


initTable();
initTable2();
});