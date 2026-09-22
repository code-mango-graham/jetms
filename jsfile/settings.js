$(document).ready(function () {

    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('click', '#users');
    $(document).off('click', '#school_year');
    $(document).off('click', '#teachers');
    $(document).off('click', '#offices');
    $(document).off('click', '#positions');
    $(document).off('click', '#levels');
    $(document).off('click', '#assignments');

$("#users").on("click", function()
		{
            setActiveMenu("#users");
			$("#content_settings").removeClass("d-none");
			$("#content_settings").empty();
			$("#content_settings").load("pages/users.html?v=" + Date.now());
        });
$("#school_year").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#school_year");
			$("#content_settings").removeClass("d-none");
			$("#content_settings").empty();
			$("#content_settings").load("pages/school_year.html?v=" + Date.now());
        });
$("#teachers").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#teachers");
			$("#content_settings").removeClass("d-none");
			$("#content_settings").empty();
			$("#content_settings").load("pages/teachers.html?v=" + Date.now());
        });
$("#offices").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#offices");
			$("#content_settings").removeClass("d-none");
			$("#content_settings").empty();
			$("#content_settings").load("pages/office.html?v=" + Date.now());
        });
$("#positions").on("click", function()
		{
          //  alert("Dashboard");
            setActiveMenu("#positions");
			$("#content_settings").removeClass("d-none");
			$("#content_settings").empty();
			$("#content_settings").load("pages/positions.html?v=" + Date.now());
        });
$("#levels").on("click", function()
		{
          //  alert("Levels");
            setActiveMenu("#levels");
			$("#content_settings").removeClass("d-none");
			$("#content_settings").empty();
			$("#content_level").empty();
			$("#content_settings").load("pages/levels.html?v=" + Date.now());
        });
$("#assignments").on("click", function()
		{
            setActiveMenu("#assignments");
			$("#content_settings").removeClass("d-none");
			$("#content_settings").empty();
			$("#content_settings").load("pages/assignments.html?v=" + Date.now());
        });
        function setActiveMenu(activeId) {
            $("#users").removeClass("active");
            $("#school_year").removeClass("active");
            $("#teachers").removeClass("active");
            $("#offices").removeClass("active");
            $("#positions").removeClass("active");
            $("#levels").removeClass("active");
            $("#assignments").removeClass("active");

            $("#content_settings").addClass("d-none");
            $("#content_level").addClass("d-none");
            $("#content_subject").addClass("d-none");
            $(activeId).addClass("active");
        }
});

