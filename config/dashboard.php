<?php
require_once __DIR__ . '/session_boot.php';
include '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['auth']) || $_SESSION['auth']['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Not authorized"]);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {

    // =======================
    // STATS (everything the Admin Dashboard needs, in one call)
    // =======================
    case 'stats': {
        $activeYear = mysqli_fetch_assoc(mysqli_query($conn, "SELECT schoolyear_id, schoolyear_name FROM tbl_schoolyear WHERE status = 'active' LIMIT 1"));

        $totals = [
            'active_schoolyear_name' => $activeYear ? $activeYear['schoolyear_name'] : null,
            'total_enrolled' => 0,
            'total_collected' => 0,
            'total_balance' => 0,
            'attendance_today' => 0
        ];

        if ($activeYear) {
            $schoolyearId = (int) $activeYear['schoolyear_id'];

            $enrolledRow = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT COUNT(*) AS cnt, COALESCE(SUM(tuition_fee), 0) AS tuition_sum
                FROM tbl_enrollment
                WHERE schoolyear_id = $schoolyearId AND status = 'enrolled'
            "));
            $totals['total_enrolled'] = (int) $enrolledRow['cnt'];

            $paidRow = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT COALESCE(SUM(p.amount), 0) AS paid_sum
                FROM tbl_payment p
                INNER JOIN tbl_enrollment e ON e.enrollment_id = p.enrollment_id
                WHERE e.schoolyear_id = $schoolyearId
            "));

            // Outstanding = what the CURRENTLY ENROLLED students still owe: their tuition minus
            // THEIR OWN payments (a dropped student's payment must not reduce anyone else's balance).
            $owedRow = mysqli_fetch_assoc(mysqli_query($conn, "
                SELECT COALESCE(SUM(GREATEST(e.tuition_fee - COALESCE(p.paid, 0), 0)), 0) AS owed
                FROM tbl_enrollment e
                LEFT JOIN (SELECT enrollment_id, SUM(amount) AS paid FROM tbl_payment GROUP BY enrollment_id) p
                       ON p.enrollment_id = e.enrollment_id
                WHERE e.schoolyear_id = $schoolyearId AND e.status = 'enrolled'
            "));

            $totals['total_collected'] = (float) $paidRow['paid_sum'];
            $totals['total_balance'] = (float) $owedRow['owed'];
        }

        $attendanceRow = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(DISTINCT student_id) AS cnt FROM tbl_attendance_log WHERE DATE(log_time) = CURDATE()
        "));
        $totals['attendance_today'] = (int) $attendanceRow['cnt'];

        // Recent payments
        $recentPayments = [];
        $result = mysqli_query($conn, "
            SELECT p.payment_id, p.payment_date, p.amount, p.payment_mode, s.first_name, s.last_name
            FROM tbl_payment p
            INNER JOIN tbl_enrollment e ON e.enrollment_id = p.enrollment_id
            INNER JOIN tbl_student s ON s.student_id = e.student_id
            ORDER BY p.payment_id DESC LIMIT 5
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $recentPayments[] = $row;
        }

        // Recent enrollments
        $recentEnrollments = [];
        $result = mysqli_query($conn, "
            SELECT e.enrollment_id, e.level_name, e.section_name, e.enrollment_date, s.first_name, s.last_name
            FROM tbl_enrollment e
            INNER JOIN tbl_student s ON s.student_id = e.student_id
            ORDER BY e.enrollment_id DESC LIMIT 5
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $recentEnrollments[] = $row;
        }

        // Recent announcements
        $recentAnnouncements = [];
        $result = mysqli_query($conn, "
            SELECT announcement_id, title, admin_name, created_at
            FROM tbl_announcement
            WHERE announcement_remarks = 1
            ORDER BY announcement_id DESC LIMIT 3
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $recentAnnouncements[] = $row;
        }

        // Upcoming events (next 5)
        $upcomingEvents = [];
        $result = mysqli_query($conn, "
            SELECT event_id, title, event_type, start_date, end_date
            FROM tbl_event
            WHERE event_remarks = 1 AND COALESCE(end_date, start_date) >= CURDATE()
            ORDER BY start_date ASC LIMIT 5
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $upcomingEvents[] = $row;
        }

        echo json_encode([
            "totals" => $totals,
            "recent_payments" => $recentPayments,
            "recent_enrollments" => $recentEnrollments,
            "recent_announcements" => $recentAnnouncements,
            "upcoming_events" => $upcomingEvents
        ]);
        break;
    }

    default:
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        break;
}
