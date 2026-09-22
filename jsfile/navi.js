
$(document).ready(function () {

        function refreshAnnouncementBadge() {
            $.ajax({
                url: 'config/announcement.php',
                type: 'POST',
                data: { action: 'unread_count' },
                dataType: 'json',
                success: function (res) {
                    const count = res.count || 0;
                    const $badges = $('.announcement-badge');
                    if (count > 0) {
                        $badges.text(count > 99 ? '99+' : count).removeClass('d-none');
                    } else {
                        $badges.addClass('d-none').text('');
                    }
                }
            });
        }

        function markAnnouncementsViewed() {
            $.ajax({
                url: 'config/announcement.php',
                type: 'POST',
                data: { action: 'mark_viewed' },
                dataType: 'json',
                complete: function () {
                    $('.announcement-badge').addClass('d-none').text('');
                }
            });
        }

        refreshAnnouncementBadge();


        $("#admin_dash").on("click", function()
		{
          //  alert("Dashboard");
			$("#app-main").empty();
			$("#app-main").load("pages/dashboard.html?v=" + Date.now());
        });
        $("#admin_students").on("click", function()
		{
          //  alert("Dashboard");
			$("#app-main").empty();
			$("#app-main").load("pages/students.html?v=" + Date.now());
        });
        $('#admin_enroll').click(function () {
            $("#app-main").empty();
			$("#app-main").load("pages/enrollment.html?v=" + Date.now());
        });
        $('#admin_payments').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/payments.html?v=" + Date.now());
        });
        $('#admin_attendance').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/attendance.html?v=" + Date.now());
        });
        $('#admin_announcements').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/announcements.html?v=" + Date.now());
            markAnnouncementsViewed();
        });
        $('#admin_settings').click(function () {
            $("#app-main").empty();
			$("#app-main").load("pages/settings.html?v=" + Date.now());
        });

        $('#teacher_classes').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/teacher_classes.html?v=" + Date.now());
        });
        $('#teacher_announcements').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/teacher_announcements.html?v=" + Date.now());
            markAnnouncementsViewed();
        });

        $('#student_subjects').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/student_subjects.html?v=" + Date.now());
        });
        $('#student_grades').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/student_grades.html?v=" + Date.now());
        });
        $('#student_payments').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/student_payments.html?v=" + Date.now());
        });
        $('#student_announcements').click(function () {
            $("#app-main").empty();
            $("#app-main").load("pages/student_announcements.html?v=" + Date.now());
            markAnnouncementsViewed();
        });
});

