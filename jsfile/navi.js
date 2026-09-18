   
$(document).ready(function () {
    
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
			$("#app-main").load("#");
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
        });
});

