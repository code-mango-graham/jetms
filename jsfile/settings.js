   
$(document).ready(function () {

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#school_year');
    $(document).off('click', '#teacher');
    $(document).off('click', '#offices');
    $(document).off('click', '#positions');

$("#school_year").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#school_year");
			$("#content_settings").empty();
			$("#content_settings").load("pages/school_year.html");			
        });
$("#teachers").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#teachers");
			$("#content_settings").empty();
			$("#content_settings").load("pages/teachers.html");			
        });
$("#offices").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#offices");
			$("#content_settings").empty();
			$("#content_settings").load("pages/office.html");			
        });
$("#positions").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#positions");
			$("#content_settings").empty();
			$("#content_settings").load("pages/positions.html");			
        });

        function setActiveMenu(activeId) {
            $("#school_year").removeClass("active");
            $("#teachers").removeClass("active");
            $("#offices").removeClass("active");
            $("#positions").removeClass("active");

            if ($.fn.DataTable.isDataTable('#sectionsTable')) {
              $('#sectionsTable').DataTable().destroy();
            }

            if ($.fn.DataTable.isDataTable('#subjectTable')) {
              $('#subjectTable').DataTable().destroy();
            }
            $(activeId).addClass("active");
        }
});

