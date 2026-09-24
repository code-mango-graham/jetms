$(document).ready(function () {

    $(document).off('click', '#btnBackupNow');
    $(document).off('click', '.btnRestoreBackup');

    if ($.fn.DataTable.isDataTable('#backupTable')) {
        $('#backupTable').DataTable().destroy();
        $('#backupTable tbody').empty();
    }

    const typeBadge = {
        weekly: '<span class="badge bg-primary">Weekly (automatic)</span>',
        manual: '<span class="badge bg-success">Manual</span>',
        pre_activate: '<span class="badge bg-warning text-dark">Before school-year activation</span>',
        pre_restore: '<span class="badge bg-secondary">Before a restore</span>'
    };

    function fmtSize(bytes) {
        if (bytes > 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    const table = $('#backupTable').DataTable({
        processing: true,
        responsive: true,
        scrollX: true,
        language: { lengthMenu: "Show _MENU_ entries", emptyTable: "No backups yet." },
        ajax: {
            url: 'config/backup.php',
            type: 'POST',
            data: function (d) { d.action = 'list'; return d; }
        },
        order: [[2, 'desc']],
        columns: [
            { data: 'file' },
            { data: 'type', render: function (t) { return typeBadge[t] || t; } },
            { data: 'created_at' },
            { data: 'size', render: function (b) { return fmtSize(b); } },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function (d) {
                    return `<div class="btn-group btn-group-sm">
                        <a class="btn btn-outline-secondary" href="config/backup.php?action=download&file=${encodeURIComponent(d.file)}" title="Download"><i class="bi bi-download"></i></a>
                        <button class="btn btn-outline-danger btnRestoreBackup" data-file="${d.file}" title="Restore this backup"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>`;
                }
            }
        ]
    });

    $('#btnBackupNow').click(function () {
        window.jetmsConfirm({
            title: 'Create a backup now?',
            warning: 'A full copy of the database (all students, enrollments, payments and grades) will be saved on the server. ' +
                     'Backup files contain personal data - keep them safe.',
            confirmText: 'Create Backup',
            confirmColor: '#12a480'
        }).then(function (confirmed) {
            if (!confirmed) return;

            Swal.fire({ title: 'Creating backup...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
            $.ajax({
                url: 'config/backup.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'create', confirm_password: confirmed.password },
                success: function (res) {
                    if (res.status === 'error') {
                        Swal.fire({ icon: 'error', title: res.message });
                        return;
                    }
                    table.ajax.reload(null, false);
                    Swal.fire({ icon: 'success', title: 'Backup created', text: res.message });
                },
                error: function () { Swal.fire({ icon: 'error', title: 'Request failed. Please log in again and retry.' }); }
            });
        });
    });

    $(document).on('click', '.btnRestoreBackup', function () {
        const file = $(this).data('file');

        window.jetmsConfirm({
            title: 'Restore this backup?',
            warning: '<b>This change affects the whole school year.</b><br>' + file + '<br><br>' +
                     'The database will be put back <b>exactly as it was when this backup was made</b>. ' +
                     'Everything entered after that moment (enrollments, payments, grades, students, accounts) will be <b>lost</b>. ' +
                     'A safety backup of the current data is saved first, and you will be logged out. ' +
                     'Do this outside school hours.',
            typedWord: 'RESTORE',
            confirmText: 'Restore Database',
            confirmColor: '#dc3545'
        }).then(function (confirmed) {
            if (!confirmed) return;

            Swal.fire({ title: 'Restoring database...', text: 'Do not close this window.', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
            $.ajax({
                url: 'config/backup.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'restore', file: file, confirm_text: confirmed.typed, confirm_password: confirmed.password },
                success: function (res) {
                    if (res.status === 'error') {
                        Swal.fire({ icon: 'error', title: 'Restore not completed', text: res.message });
                        return;
                    }
                    Swal.fire({ icon: 'success', title: 'Database restored', text: res.message, allowOutsideClick: false })
                        .then(function () { window.location = 'index.html'; });
                },
                error: function () { Swal.fire({ icon: 'error', title: 'Request failed. Check the Security Logs before retrying.' }); }
            });
        });
    });
});
