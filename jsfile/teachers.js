   
   $(document).ready(function () {
       $('#teachersTable').DataTable({
           layout: {
        topStart: {

    buttons: [
        {
            extend: 'copy',
            text: 'Copy',
            className: 'btn btn-secondary btn-sm'
        },
        {
            extend: 'excel',
            text: 'Excel',
            className: 'btn btn-success btn-sm'
        },
        {
            extend: 'pdf',
            text: 'PDF',
            className: 'btn btn-danger btn-sm'
        },
        {
            extend: 'print',
            text: 'Print',
            className: 'btn btn-primary btn-sm'
        }
    ]
        }
    }
        });
    });
