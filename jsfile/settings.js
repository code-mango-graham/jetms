   
$(document).ready(function () {

  function resetContentLayout() {
    $('#content_settings')
      .removeClass('col-md-6 col-lg-6')
      .addClass('col-md-12');

    $('#content_level')
      .removeClass('col-md-6 col-lg-6')
      .addClass('col-md-12 mt-3 d-none')
      .empty();

    $('#content_subject')
      .removeClass('col-md-6 col-lg-6')
      .addClass('col-md-12 mt-3 d-none')
      .empty();
  }
    
    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#school_year');
    $(document).off('click', '#curriculum');
    $(document).off('click', '#departments');
    $(document).off('click', '#positions');

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
         $("#positions").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#positions");
			$("#content_settings").empty();
			$("#content_settings").load("pages/positions.html");			
        });

        function setActiveMenu(activeId) {
            $("#school_year").removeClass("active");
            $("#curriculum").removeClass("active");
            $("#departments").removeClass("active");
            $("#positions").removeClass("active");
            resetContentLayout();

            if ($.fn.DataTable.isDataTable('#sectionsTable')) {
              $('#sectionsTable').DataTable().destroy();
            }

            if ($.fn.DataTable.isDataTable('#subjectTable')) {
              $('#subjectTable').DataTable().destroy();
            }
            $(activeId).addClass("active");
        }
});

