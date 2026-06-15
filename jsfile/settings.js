   
$(document).ready(function () {
    
        $("#school_year").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#school_year");
			$("#content_settings").empty();
			$("#content_settings").load("pages/school_year.html");			
        });
        $("#curriculum").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#curriculum");
			$("#content_settings").empty();
			$("#content_settings").load("pages/curriculum.html");			
        });
        $("#departments").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#departments");
			$("#content_settings").empty();
			$("#content_settings").load("pages/department.html");			
        });
        $("#grade").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#grade");
			$("#content_settings").empty();
			$("#content_settings").load("pages/level.html");			
        });

        function setActiveMenu(activeId) {

        $("#school_year").removeClass("active");
        $("#curriculum").removeClass("active");
        $("#departments").removeClass("active");
        $("#grade").removeClass("active");
        $(activeId).addClass("active");
        }
});

