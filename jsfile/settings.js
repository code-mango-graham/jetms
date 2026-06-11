   
$(document).ready(function () {
    
        $("#school_year").on("click", function()
		{
          //  alert("Dashboard");
            $("#school_year").addClass("active");
            $("#curriculum").removeClass("active");
			$("#content_settings").empty();
			$("#content_settings").load("pages/school_year.html");			
        });
        $("#curriculum").on("click", function()
		{
          //  alert("Dashboard");
            $("#curriculum").addClass("active");
            $("#school_year").removeClass("active");
			$("#content_settings").empty();
			$("#content_settings").load("pages/curriculum.html");			
        });
});

