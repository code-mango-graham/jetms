// Shared "are you sure? enter your password" dialog for high-impact admin actions.
// Resolves to { password, typed } when confirmed, or null when cancelled.
window.jetmsConfirm = function (opts) {
    const typedWord = opts.typedWord || null;

    return Swal.fire({
        title: opts.title || 'Please confirm',
        icon: 'warning',
        html: `
            <div class="text-start">
                <div class="alert alert-warning small mb-3">${opts.warning || ''}</div>
                ${typedWord ? `
                    <label class="small fw-bold" for="jc_typed">Type <span class="text-danger">${typedWord}</span> to continue</label>
                    <input type="text" id="jc_typed" class="swal2-input mt-1 mb-3" autocomplete="off" style="width:100%;margin:0.25rem 0 0.75rem;">
                ` : ''}
                <label class="small fw-bold" for="jc_pw">Enter your admin password</label>
                <input type="password" id="jc_pw" class="swal2-input mt-1" autocomplete="current-password" style="width:100%;margin:0.25rem 0 0;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: opts.confirmText || 'Confirm',
        confirmButtonColor: opts.confirmColor || '#dc3545',
        cancelButtonColor: '#6c757d',
        focusConfirm: false,
        didOpen: function () {
            const first = document.getElementById(typedWord ? 'jc_typed' : 'jc_pw');
            if (first) first.focus();
        },
        preConfirm: function () {
            const pw = document.getElementById('jc_pw').value;
            const typed = typedWord ? document.getElementById('jc_typed').value.trim() : null;
            if (typedWord && typed !== typedWord) {
                Swal.showValidationMessage('Type ' + typedWord + ' exactly to continue');
                return false;
            }
            if (!pw) {
                Swal.showValidationMessage('Enter your password to continue');
                return false;
            }
            return { password: pw, typed: typed };
        }
    }).then(function (result) {
        return result.isConfirmed ? result.value : null;
    });
};
