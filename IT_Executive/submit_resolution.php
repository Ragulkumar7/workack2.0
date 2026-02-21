<?php
// submit_resolution.php

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Include your database connection
include('../include/db_connect.php'); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Check if the 'time_taken' column exists
    $check_col = $conn->query("SHOW COLUMNS FROM tickets LIKE 'time_taken'");
    if ($check_col && $check_col->num_rows === 0) {
        // If columns don't exist, automatically add them to the tickets table
        $conn->query("ALTER TABLE tickets 
            ADD COLUMN diagnosis TEXT DEFAULT NULL,
            ADD COLUMN solution TEXT DEFAULT NULL,
            ADD COLUMN time_taken VARCHAR(50) DEFAULT NULL,
            ADD COLUMN part_name VARCHAR(255) DEFAULT NULL,
            ADD COLUMN part_serial VARCHAR(100) DEFAULT NULL,
            ADD COLUMN completion_date DATE DEFAULT NULL
        ");
    }
    
    // 2. Automatically fix the Status Dropdown Enum in Database
    $conn->query("ALTER TABLE tickets MODIFY COLUMN status ENUM('Open', 'In Progress', 'Waiting for Parts', 'Resolved', 'Closed') DEFAULT 'Open'");
    // =========================================================================


    // 3. Get the data safely to prevent "Undefined array key" warnings
    $ticket_id       = intval($_POST['ticket_id'] ?? 0);
    $raw_status      = $_POST['status'] ?? 'Open';
    $time_taken      = trim($_POST['time_taken'] ?? '');
    $diagnosis       = trim($_POST['diagnosis'] ?? '');
    $solution        = trim($_POST['solution'] ?? '');
    $part_name       = trim($_POST['part_name'] ?? '');
    $part_serial     = trim($_POST['part_serial'] ?? '');
    
    // Handle Date Safely (Send NULL if date is empty)
    $completion_date = (isset($_POST['completion_date']) && $_POST['completion_date'] !== '') ? $_POST['completion_date'] : null;

    if ($ticket_id === 0) {
        die("Invalid Ticket ID. Cannot update database.");
    }

    // 4. Status Mapper (Matches HTML dropdown with Database values)
    $status_map = [
        'in_progress'   => 'In Progress',
        'waiting_parts' => 'Waiting for Parts',
        'completed'     => 'Resolved',
        'rejected'      => 'Closed',
        'Open'          => 'Open',
        'In Progress'   => 'In Progress',
        'Waiting for Parts' => 'Waiting for Parts',
        'Resolved'      => 'Resolved',
        'Closed'        => 'Closed'
    ];
    $status = $status_map[$raw_status] ?? 'Open';

    // 5. Update Query
    $sql = "UPDATE tickets 
            SET status = ?, 
                time_taken = ?, 
                diagnosis = ?, 
                solution = ?, 
                part_name = ?, 
                part_serial = ?, 
                completion_date = ? 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("sssssssi", $status, $time_taken, $diagnosis, $solution, $part_name, $part_serial, $completion_date, $ticket_id);
        
        if ($stmt->execute()) {
            echo "<script>
                    alert('Ticket Resolution Saved Successfully!');
                    window.location.href = 'it_exec_main_ticket.php'; 
                  </script>";
        }
        } else {
            echo "<h3>Error updating record: " . $stmt->error . "</h3>";
        }
        $stmt->close();
    } else {
        echo "<h3>Database Error: " . $conn->error . "</h3>";
    }
    
    $conn->close();
} else {
    echo "Invalid Request.";
}
?>