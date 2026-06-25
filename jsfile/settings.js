   
$(document).ready(function () {
    
    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#school_year');
    $(document).off('click', '#curriculum');
    $(document).off('click', '#departments');
    $(document).off('click', '#grade');
    $(document).off('click', '#subjects');

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
            $("#content_level").empty();

            if ($.fn.DataTable.isDataTable('#sectionsTable')) {
              $('#sectionsTable').DataTable().destroy();
            }

            if ($.fn.DataTable.isDataTable('#subjectTable')) {
              $('#subjectTable').DataTable().destroy();
            }
            $(activeId).addClass("active");
        }
});

