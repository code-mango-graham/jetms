$(document).ready(function () {

    $(document).off('change', '#ledger_schoolyear_filter');
    $(document).off('click', '#btnApplyPaymentFilters');
    $(document).off('click', '#btnClearPaymentFilters');
    $(document).off('click', '#paymentsViewTabs a');
    $(document).off('click', '.btnEditPayment');
    $(document).off('submit', '#editPaymentForm');

    function money(v) {
        return '₱' + parseFloat(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function currentFilters(d) {
        d.schoolyear_id = $('#ledger_schoolyear_filter').val();
        d.date_from = $('#ledger_date_from').val();
        d.date_to = $('#ledger_date_to').val();
        return d;
    }

    if ($.fn.DataTable.isDataTable('#paymentsLedgerTable')) {
        $('#paymentsLedgerTable').DataTable().destroy();
        $('#paymentsLedgerTable tbody').empty();
    }

    let ledgerTable = $('#paymentsLedgerTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No payments recorded yet." },
        ajax: {
            url: 'config/payment.php',
            type: 'POST',
            data: function (d) {
                d.action = 'ledger';
                return currentFilters(d);
            },
            dataSrc: function (json) {
                $('#paymentsTotal').text(money(json.total));
                return json.data || [];
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'payment_date' },
            { data: null, render: function (d) { return `${d.last_name}, ${d.first_name}`; } },
            { data: 'lrn', defaultContent: '-' },
            { data: null, render: function (d) { return `${d.level_name} / ${d.section_name}`; } },
            { data: 'schoolyear_name' },
            { data: 'amount', render: function (d) { return money(d); } },
            { data: 'payment_mode' },
            { data: 'reference_no', defaultContent: '-' },
            { data: 'admin_name' },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (d) {
                    return `<button class="btn btn-outline-secondary btn-sm btnEditPayment" data-id="${d.payment_id}" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>`;
                }
            }
        ]
    });

    if ($.fn.DataTable.isDataTable('#paymentsDailyTable')) {
        $('#paymentsDailyTable').DataTable().destroy();
        $('#paymentsDailyTable tbody').empty();
    }

    let dailyTable = $('#paymentsDailyTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No payments recorded yet." },
        ajax: {
            url: 'config/payment.php',
            type: 'POST',
            data: function (d) {
                d.action = 'daily_totals';
                return currentFilters(d);
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'payment_date' },
            { data: 'payment_count', className: 'text-center' },
            { data: 'total_amount', render: function (d) { return money(d); } }
        ]
    });

    $.ajax({
        url: 'config/schoolyear.php',
        type: 'POST',
        data: { action: 'load' },
        dataType: 'json',
        success: function (res) {
            let options = '<option value="">All School Years</option>';
            (res.data || []).forEach(function (y) {
                options += `<option value="${y.schoolyear_id}">${y.schoolyear_name}${y.status === 'active' ? ' (Active)' : ''}</option>`;
            });
            $('#ledger_schoolyear_filter').html(options);
        }
    });

    function reloadBoth() {
        ledgerTable.ajax.reload();
        dailyTable.ajax.reload();
    }

    $(document).on('change', '#ledger_schoolyear_filter', reloadBoth);
    $(document).on('click', '#btnApplyPaymentFilters', reloadBoth);

    $(document).on('click', '#btnClearPaymentFilters', function () {
        $('#ledger_schoolyear_filter').val('');
        $('#ledger_date_from').val('');
        $('#ledger_date_to').val('');
        reloadBoth();
    });

    $(document).on('click', '#paymentsViewTabs a', function (e) {
        e.preventDefault();
        $('#paymentsViewTabs a').removeClass('active');
        $(this).addClass('active');

        const view = $(this).data('view');
        if (view === 'daily') {
            $('#paymentsLedgerView').addClass('d-none');
            $('#paymentsDailyView').removeClass('d-none');
            dailyTable.columns.adjust();
        } else {
            $('#paymentsDailyView').addClass('d-none');
            $('#paymentsLedgerView').removeClass('d-none');
            ledgerTable.columns.adjust();
        }
    });

    // ===========================================================
    // Edit Payment (requires a reason — logged server-side)
    // ===========================================================
    $(document).on('click', '.btnEditPayment', function () {
        const payment_id = $(this).data('id');

        $.ajax({
            url: 'config/payment.php',
            type: 'POST',
            data: { action: 'get', payment_id: payment_id },
            dataType: 'json',
            success: function (data) {
                if (data.status === 'error') {
                    Swal.fire({ icon: 'error', title: data.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                $('#edit_payment_id').val(data.payment_id);
                $('#edit_payment_date').val(data.payment_date);
                $('#edit_amount').val(data.amount);
                $('#edit_payment_mode').val(data.payment_mode);
                $('#edit_reference_no').val(data.reference_no || '');
                $('#edit_reason').val('');

                $('#editPaymentModal').modal('show');
            }
        });
    });

    $('#editPaymentForm').submit(function (e) {
        e.preventDefault();

        const payload = {
            action: 'edit',
            payment_id: $('#edit_payment_id').val(),
            payment_date: $('#edit_payment_date').val(),
            amount: $('#edit_amount').val(),
            payment_mode: $('#edit_payment_mode').val(),
            reference_no: $('#edit_reference_no').val(),
            reason: $('#edit_reason').val()
        };

        $.ajax({
            url: 'config/payment.php',
            type: 'POST',
            data: payload,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'error') {
                    Swal.fire({ icon: 'error', title: res.message, timer: 3000, showConfirmButton: false });
                    return;
                }

                $('#editPaymentModal').modal('hide');
                document.activeElement.blur();
                reloadBoth();
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Payment updated',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            }
        });
    });

});
