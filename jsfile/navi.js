   
$(document).ready(function () {
    
        $("#admin_dash").on("click", function()
		{
          //  alert("Dashboard");
			$("#app-main").empty();
			$("#app-main").load("pages/dashboard.html");			
        });
        $("#admin_teachers").on("click", function()
		{
          //  alert("Dashboard");
			$("#app-main").empty();
			$("#app-main").load("pages/teachers.html");			
        });
        $("#admin_students").on("click", function()
		{
          //  alert("Dashboard");
			$("#app-main").empty();
			$("#app-main").load("pages/students.html");			
        });
});
