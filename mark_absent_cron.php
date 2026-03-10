



<?php
// mark_absent_cron.php

// Database connection (Check your exact path)
require_once __DIR__ . '/include/db_connect.php'; 

date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// 1. CRITICAL FIX: Update the ENUM safely to accept Leave Types in Attendance status
// This ensures inserting 'Medical', 'Casual' etc., won't fail in the database.
$conn->query("ALTER TABLE attendance MODIFY COLUMN status ENUM('On Time','Late','WFH','Absent','Annual','Medical','Casual','Other') DEFAULT 'On Time'");

// 2. Get all active employees (excluding Admins if needed)
$emp_query = "SELECT id, username FROM users WHERE role != 'Admin' AND role != 'System Admin'";
$emp_result = $conn->query($emp_query);

if ($emp_result->num_rows > 0) {
    while ($emp = $emp_result->fetch_assoc()) {
        $emp_id = $emp['id'];

        // 3. Check if the employee has punched in today
        $att_check = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $att_check->bind_param("is", $emp_id, $today);
        $att_check->execute();
        $att_res = $att_check->get_result();

        // If no attendance record found for today (meaning they haven't logged in)
        if ($att_res->num_rows === 0) {
            
            // 4. Check if they have an APPROVED leave for today
            $leave_check = $conn->prepare("SELECT leave_type FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND ? BETWEEN start_date AND end_date");
            $leave_check->bind_param("is", $emp_id, $today);
            $leave_check->execute();
            $leave_res = $leave_check->get_result();

            $final_status = 'Absent'; // Default to Absent if no info is given

            // If an approved leave is found, set status to the exact Leave Type
            if ($leave_res->num_rows > 0) {
                $leave_row = $leave_res->fetch_assoc();
                $final_status = $leave_row['leave_type']; // Will be 'Medical', 'Casual', etc.
            }

            $leave_check->close();

            // 5. Insert the status (Absent or Leave Type) into the attendance table
            // Note: punch_in and punch_out will remain NULL
            $insert_att = $conn->prepare("INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?)");
            $insert_att->bind_param("iss", $emp_id, $today, $final_status);
            
            if($insert_att->execute()) {
                echo "<div style='color: green;'>Updated User ID {$emp_id} ({$emp['username']}) as <b>{$final_status}</b> for {$today}.</div>";
            } else {
                echo "<div style='color: red;'>Failed to update User ID {$emp_id}. Error: " . $conn->error . "</div>";
            }
            
            $insert_att->close();
        }
        $att_check->close();
    }
    echo "<br><h3 style='color: blue;'>Cron job completed successfully! All unmarked attendances have been evaluated and processed.</h3>";
} else {
    echo "No employees found.";
}

$conn->close();
?>