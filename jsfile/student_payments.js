$(document).ready(function () {

    function money(v) {
        return '₱' + parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    if ($.fn.DataTable.isDataTable('#myPaymentsTable')) {
        $('#myPaymentsTable').DataTable().destroy();
        $('#myPaymentsTable tbody').empty();
    }

    $('#myPaymentsTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No payments recorded yet." },
        ajax: {
            url: 'config/payment.php',
            type: 'POST',
            data: function (d) { d.action = 'my_history'; return d; }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'payment_date' },
            { data: 'schoolyear_name' },
            { data: 'amount', render: function (d) { return money(d); } },
            { data: 'payment_mode' },
            { data: 'reference_no', defaultContent: '-' }
        ]
    });

});
