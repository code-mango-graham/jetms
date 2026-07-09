   
$(document).ready(function () {
    
        $("#admin_dash").on("click", function()
		{
          //  alert("Dashboard");
			$("#app-main").empty();
			$("#app-main").load("pages/dashboard.html");			
        });
        $("#admin_students").on("click", function()
		{
          //  alert("Dashboard");
			$("#app-main").empty();
			$("#app-main").load("pages/students.html");			
        });
        $('#admin_enroll').click(function () {
            $("#app-main").empty();
			$("#app-main").load("pages/enrollmentform.html");
        });
        $('#admin_settings').click(function () {
            $("#app-main").empty();
			$("#app-main").load("pages/settings.html");
        });
});

