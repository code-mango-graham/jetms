$(document).ready(function () {
    const params = new URLSearchParams(window.location.search);
    const studentId = params.get('student_id');

    function valueOrDash(value) {
        return value && String(value).trim() ? value : '-';
    }

    function safePhotoSrc(path) {
        if (!path) return 'logos/logo1.png';
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        if (path.startsWith('/')) return path.slice(1);
        if (path.includes('/')) return path;
        return 'assets/img/students/' + path;
    }

    if (!studentId) {
        alert('Missing student ID.');
        return;
    }

    $.ajax({
        url: 'config/student_profile_get.php',
        type: 'GET',
        dataType: 'json',
        data: { student_id: studentId },
        success: function (res) {
            if (!res.success || !res.data) {
                alert(res.message || 'Student not found.');
                return;
            }

            const data = res.data;
            const fullName = [data.first_name, data.middle_name, data.last_name, data.extension_name]
                .filter(Boolean)
                .join(' ')
                .replace(/\s+/g, ' ')
                .trim();

            $('#profilePhoto').attr('src', safePhotoSrc(data.student_photo));
            $('#studentFullName').text(fullName || 'Student Profile');
            $('#studentLrn').text('LRN: ' + valueOrDash(data.lrn));
            $('#studentStatus').text('Status: ' + valueOrDash(data.student_status));

            $('#first_name').text(valueOrDash(data.first_name));
            $('#middle_name').text(valueOrDash(data.middle_name));
            $('#last_name').text(valueOrDash(data.last_name));
            $('#extension_name').text(valueOrDash(data.extension_name));
            $('#sex').text(valueOrDash(data.sex));
            $('#birthday').text(valueOrDash(data.birthday));
            $('#cp_no').text(valueOrDash(data.cp_no));
            $('#spoken_language').text(valueOrDash(data.spoken_language));

            const parentInfo = [valueOrDash(data.father_name), valueOrDash(data.mother_name)].join(' / ');
            $('#parent_info').text(parentInfo);
            $('#contact_person').text(valueOrDash(data.contact_person));
            $('#contact_cp_no').text(valueOrDash(data.contact_cp_no));

            const address = [data.street_name, data.barangay, data.municipality, data.province]
                .filter(function (part) { return part && String(part).trim(); })
                .join(', ');
            $('#address').text(address || '-');
        },
        error: function () {
            alert('Failed to load student profile.');
        }
    });
});
