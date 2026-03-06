<?php
// Database connection
require_once __DIR__ . '/include/db_connect.php'; 

date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// 1. Get all active employees (excluding Admins if needed)
$emp_query = "SELECT id, username FROM users WHERE role != 'Admin'";
$emp_result = $conn->query($emp_query);

if ($emp_result->num_rows > 0) {
    while ($emp = $emp_result->fetch_assoc()) {
        $emp_id = $emp['id'];

        // 2. Check if the employee has punched in today
        $att_check = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $att_check->bind_param("is", $emp_id, $today);
        $att_check->execute();
        $att_res = $att_check->get_result();

        // If no attendance record found for today
        if ($att_res->num_rows === 0) {
            
            // 3. Check if they have an APPROVED leave for today
            $leave_check = $conn->prepare("SELECT leave_type FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND ? BETWEEN start_date AND end_date");
            $leave_check->bind_param("is", $emp_id, $today);
            $leave_check->execute();
            $leave_res = $leave_check->get_result();

            $final_status = 'Absent'; // Default to Absent

            // If an approved leave is found
            if ($leave_res->num_rows > 0) {
                $leave_row = $leave_res->fetch_assoc();
                $final_status = $leave_row['leave_type']; // Example: "Sick Leave", "Casual Leave"
            }

            $leave_check->close();

            // 4. Insert the status (Absent or Leave) into the attendance table
            // Note: punch_in and punch_out will be NULL
            $insert_att = $conn->prepare("INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?)");
            $insert_att->bind_param("iss", $emp_id, $today, $final_status);
            $insert_att->execute();
            $insert_att->close();
            
            echo "Updated User ID {$emp_id} as {$final_status} for {$today}.<br>";
        }
        $att_check->close();
    }
    echo "Cron job completed successfully.";
} else {
    echo "No employees found.";
}

$conn->close();
?>