$(document).ready(function () {

    $(document).off('click', '#logTabs a');
    $(document).off('click', '#btnApplyLogFilters');
    $(document).off('click', '#btnClearLogFilters');
    $(document).off('click', '.btnLogDetails');

    function esc(v) {
        return $('<div>').text(v === null || v === undefined ? '' : v).html();
    }

    function filters(d) {
        d.date_from = $('#log_date_from').val();
        d.date_to = $('#log_date_to').val();
        return d;
    }

    ['#auditTable', '#loginTable'].forEach(function (id) {
        if ($.fn.DataTable.isDataTable(id)) {
            $(id).DataTable().destroy();
            $(id + ' tbody').empty();
        }
    });

    const actionBadge = {
        create: 'bg-success', update: 'bg-primary', archive: 'bg-secondary', delete: 'bg-danger',
        activate: 'bg-warning text-dark', activate_failed: 'bg-danger', backup: 'bg-info text-dark',
        backup_download: 'bg-info text-dark', restore: 'bg-danger', restore_start: 'bg-danger', restore_failed: 'bg-danger',
        password_reset: 'bg-warning text-dark', password_change: 'bg-warning text-dark',
        auth_failed: 'bg-danger', denied: 'bg-danger', scan: 'bg-light text-dark border'
    };

    let auditRows = {};

    const auditTable = $('#auditTable').DataTable({
        processing: true,
        scrollX: true,
        pageLength: 25,
        language: { emptyTable: "No changes recorded yet." },
        ajax: {
            url: 'config/logs.php',
            type: 'POST',
            data: function (d) { d.action = 'audit_load'; return filters(d); },
            dataSrc: function (json) {
                auditRows = {};
                (json.data || []).forEach(function (r) { auditRows[r.log_id] = r; });
                return json.data || [];
            }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'created_at' },
            { data: null, render: function (r) { return esc(r.actor_name) + ' <span class="text-muted small">(' + esc(r.actor_role) + ')</span>'; } },
            { data: 'action', render: function (a) { return '<span class="badge ' + (actionBadge[a] || 'bg-secondary') + '">' + esc(a) + '</span>'; } },
            { data: 'summary', render: function (t) { return '<div style="white-space:normal;word-break:break-word;min-width:240px;max-width:520px;">' + esc(t) + '</div>'; } },
            { data: 'ip_address', defaultContent: '-' },
            {
                data: null, orderable: false, className: 'text-center',
                render: function (r) {
                    return r.details ? '<button class="btn btn-outline-secondary btn-sm btnLogDetails" data-id="' + r.log_id + '"><i class="bi bi-eye"></i></button>' : '';
                }
            }
        ]
    });

    const eventBadge = { login_success: 'bg-success', login_failed: 'bg-danger', logout: 'bg-secondary' };
    const eventLabel = { login_success: 'Login', login_failed: 'Failed login', logout: 'Logout' };

    const loginTable = $('#loginTable').DataTable({
        processing: true,
        scrollX: true,
        pageLength: 25,
        language: { emptyTable: "No login records yet." },
        ajax: {
            url: 'config/logs.php',
            type: 'POST',
            data: function (d) { d.action = 'login_load'; return filters(d); },
            dataSrc: function (json) { return json.data || []; }
        },
        order: [[0, 'desc']],
        columns: [
            { data: 'created_at' },
            { data: 'event', render: function (e) { return '<span class="badge ' + (eventBadge[e] || 'bg-secondary') + '">' + (eventLabel[e] || esc(e)) + '</span>'; } },
            { data: 'role_attempted', defaultContent: '-' },
            { data: 'username', render: function (v) { return esc(v); }, defaultContent: '-' },
            { data: 'failure_reason', render: function (v) { return esc(v); }, defaultContent: '' },
            { data: 'ip_address', defaultContent: '-' },
            { data: 'user_agent', render: function (v) { return '<span title="' + esc(v) + '">' + esc((v || '').substring(0, 40)) + '</span>'; }, defaultContent: '' }
        ]
    });

    $(document).on('click', '#logTabs a', function (e) {
        e.preventDefault();
        $('#logTabs a').removeClass('active');
        $(this).addClass('active');
        if ($(this).data('view') === 'login') {
            $('#auditView').addClass('d-none');
            $('#loginView').removeClass('d-none');
            loginTable.columns.adjust();
        } else {
            $('#loginView').addClass('d-none');
            $('#auditView').removeClass('d-none');
            auditTable.columns.adjust();
        }
    });

    function reloadBoth() { auditTable.ajax.reload(); loginTable.ajax.reload(); }
    $(document).on('click', '#btnApplyLogFilters', reloadBoth);
    $(document).on('click', '#btnClearLogFilters', function () {
        $('#log_date_from, #log_date_to').val('');
        reloadBoth();
    });

    $(document).on('click', '.btnLogDetails', function () {
        const r = auditRows[$(this).data('id')];
        if (!r) return;
        let d = {};
        try { d = JSON.parse(r.details); } catch (e) { d = { raw: r.details }; }

        let html = '<div class="text-start small">';
        if (d.changes) {
            html += '<table class="table table-sm table-bordered"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>';
            Object.keys(d.changes).forEach(function (k) {
                html += '<tr><td>' + esc(k) + '</td><td>' + esc(d.changes[k].from) + '</td><td>' + esc(d.changes[k].to) + '</td></tr>';
            });
            html += '</tbody></table>';
        }
        ['new', 'old'].forEach(function (key) {
            if (d[key]) {
                html += '<div class="fw-bold mt-2">' + (key === 'new' ? 'Recorded values' : 'Values before removal') + '</div><table class="table table-sm table-bordered"><tbody>';
                Object.keys(d[key]).forEach(function (k) { html += '<tr><td>' + esc(k) + '</td><td>' + esc(d[key][k]) + '</td></tr>'; });
                html += '</tbody></table>';
            }
        });
        Object.keys(d).forEach(function (k) {
            if (['changes', 'new', 'old'].indexOf(k) === -1) {
                html += '<div><b>' + esc(k) + ':</b> ' + esc(typeof d[k] === 'object' ? JSON.stringify(d[k]) : d[k]) + '</div>';
            }
        });
        html += '</div>';

        Swal.fire({ title: esc(r.summary), html: html, width: 700, confirmButtonText: 'Close' });
    });
});
