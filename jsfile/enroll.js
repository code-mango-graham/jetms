$(document).ready(function () {
    console.log('enroll.js loaded');
    
    // Unbind previous handlers to prevent duplicates when page is reloaded
    $(document).off('submit', '#enrollmentForm');
    $(document).off('click', '#btnSubmitEnrollment');
    
    // Enrollment form functionality to be implemented
});