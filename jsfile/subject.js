$(document).ready(function () {
    let table1;
    let table2;
    function initTable() {
       
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

        initTable();
        initTable2();
/* ========================= */

$("#btnclosecard").click(function() {
                $('html, body').animate({
                    scrollTop: 0
                }, 100);
                $("#content_level").empty();
                });


$('#btnAddSection').click(function () {
        $('#sectionForm')[0].reset();
        $('#section_id').val('');
        $('.modal-title').text('Add New Section');
        $('#sectionModal').modal('show');
    });

});