<?php
// ticketraise_form.php

// 1. Session & DB Connection
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ROBUST DATABASE CONNECTION & ABSOLUTE PATH RESOLUTION
$projectRoot = __DIR__; 
$dbPath = $projectRoot . '/../include/db_connect.php';

// Check if the script is running from a subfolder (like /employee/) or the root folder
if (file_exists($dbPath)) {
    require_once $dbPath;
    $upload_base_dir = $projectRoot . '/../uploads/tickets/'; // Resolves to workack2.0/uploads/tickets/
} else {
    $dbPath = $projectRoot . '/include/db_connect.php';
    if(file_exists($dbPath)) {
        require_once $dbPath;
        $upload_base_dir = $projectRoot . '/uploads/tickets/'; // Resolves to workack2.0/uploads/tickets/
    } else {
        die("Database connection file not found at: " . $dbPath);
    }
}

if (!isset($conn) || $conn === null) {
    die("Database connection variable (\$conn) is null or not found in db_connect.php.");
}

// Flags to trigger SweetAlert after page load
$show_success_alert = false;
$show_error_alert = false;

// --- HANDLE FORM SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['id']) ? $_SESSION['id'] : 1); 
    $ticket_code = $_POST['ticket_code'];
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $cc_email = mysqli_real_escape_string($conn, $_POST['cc_email']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $attachment = NULL;

    // Handle File Upload Exceptionally
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        
        // Ensure directory exists absolutely
        if (!is_dir($upload_base_dir)) {
            mkdir($upload_base_dir, 0777, true);
        }
        
        // Make extension lowercase to prevent .PDF vs .pdf issues
        $file_extension = strtolower(pathinfo($_FILES["attachment"]["name"], PATHINFO_EXTENSION));
        $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
        
        // Exact physical path on your XAMPP server
        $target_file = $upload_base_dir . $new_filename;
        
        // Move file and save DB path
        if (move_uploaded_file($_FILES["attachment"]["tmp_name"], $target_file)) {
            // Save standardized relative path for DB
            $attachment = 'uploads/tickets/' . $new_filename;
        }
    }

    // Insert into tickets table
    $query = "INSERT INTO tickets (user_id, ticket_code, subject, priority, department, cc_email, description, attachment, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Open')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssssss", $user_id, $ticket_code, $subject, $priority, $department, $cc_email, $description, $attachment);

    if ($stmt->execute()) {
        $show_success_alert = true;

        // --- AUTOMATICALLY SEND NOTIFICATION TO IT TEAM ---
        $notif_title = "New Ticket: " . $ticket_code;
        $notif_msg = "Dept: " . $department . " | Priority: " . $priority;
        
        $notif_query = "INSERT INTO notifications (user_id, title, message, type) 
                        SELECT id, ?, ?, 'alert' FROM users WHERE role IN ('IT Admin', 'System Admin')";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("ss", $notif_title, $notif_msg);
        $notif_stmt->execute();
        $notif_stmt->close();

    } else {
        $show_error_alert = true;
    }
    $stmt->close();
}

include 'sidebars.php';

// 2. Mock Data
$ticket_id = "TKT-" . rand(10000, 99999);
$user_name = $_SESSION['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raise New Ticket - SmartHR</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #1b5a5a;
            --primary-hover: #144d4d;
            --light: #f8fafc;
            --border: #e2e8f0;
            --text: #1e293b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            color: var(--text);
        }

        /* --- Layout Fix for Sidebar --- */
        #mainContent {
            margin-left: 95px; /* Matches Sidebar Width */
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Logic handled by sidebars.php JS */
        #mainContent.main-shifted { margin-left: 315px; }

        /* Form Styling */
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            width: 100%;      /* Full Width */
            max-width: 100%;  /* Removed 800px limit */
            margin: 0 auto;
        }

        .form-header {
            background: var(--primary);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-body { padding: 2.5rem; }

        .form-group { margin-bottom: 1.5rem; }
        
        .form-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #475569;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #fff;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(27, 90, 90, 0.1);
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            padding: 0.875rem 2.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-submit:hover { background: var(--primary-hover); }

        .btn-cancel {
            background: white;
            border: 1px solid var(--border);
            color: #64748b;
            padding: 0.875rem 2.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 1rem;
            font-size: 1rem;
        }
        .btn-cancel:hover { background: #f1f5f9; }

        /* File Upload */
        .file-upload-wrapper {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 2.5rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
        }
        .file-upload-wrapper:hover { border-color: var(--primary); background: #f0fdfa; }
        
        /* Select Styling */
        select option[value="High"] { color: #ef4444; font-weight: bold; }
        select option[value="Medium"] { color: #f59e0b; font-weight: bold; }
        select option[value="Low"] { color: #10b981; font-weight: bold; }

    </style>
</head>
<body>

<div id="mainContent">
    <?php include 'header.php'; ?>
    
    <div class="flex justify-between items-center px-8 py-6 bg-white border-b border-gray-200">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Raise a Ticket</h1>
            <nav class="flex text-sm text-gray-500 mt-1">
                <ol class="inline-flex items-center space-x-1">
                    <li><a href="dashboard.php" class="hover:text-teal-700">Dashboard</a></li>
                    <li><span class="mx-2">/</span></li>
                    <li><a href="ticketraise.php" class="hover:text-teal-700">Tickets</a></li>
                    <li><span class="mx-2">/</span></li>
                    <li class="font-medium text-teal-900">New Ticket</li>
                </ol>
            </nav>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500">Ticket ID (Auto)</p>
            <p class="font-mono font-bold text-lg text-slate-700">#<?php echo $ticket_id; ?></p>
        </div>
    </div>

    <div class="p-6 md:p-10 w-full">
        <form id="ticketForm" action="" method="POST" enctype="multipart/form-data">
            
            <input type="hidden" name="ticket_code" value="<?php echo $ticket_id; ?>">

            <div class="form-card">
                
                <div class="form-header">
                    <div>
                        <h2 class="text-xl font-bold">New Support Request</h2>
                        <p class="text-teal-100 text-sm opacity-90 mt-1">Please provide detailed information to help us resolve your issue faster.</p>
                    </div>
                    <div class="h-12 w-12 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-pen-to-square text-white text-xl"></i>
                    </div>
                </div>

                <div class="form-body">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mb-4">
                        <div class="form-group lg:col-span-3">
                            <label class="form-label">Ticket Subject <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" id="subject" class="form-control" placeholder="e.g., Laptop Screen Flickering or Salary Discrepancy" required minlength="5">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority <span class="text-red-500">*</span></label>
                            <select name="priority" id="priority" class="form-control" required>
                                <option value="" disabled selected>Select Priority</option>
                                <option value="High">🚨 High - Urgent</option>
                                <option value="Medium">⚡ Medium</option>
                                <option value="Low">🟢 Low - Routine</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-4">
                        <div class="form-group">
                            <label class="form-label">Category / Department <span class="text-red-500">*</span></label>
                            <select name="department" id="department" class="form-control" required>
                                <option value="" disabled selected>Select Department</option>
                                <option value="IT Support">IT Support (Hardware/Software)</option>
                                <option value="HR">Human Resources (Leave/Policy)</option>
                                <option value="Accounts">Accounts & Payroll</option>
                                <option value="Facilities">Admin & Facilities</option>
                                <option value="Project">Project Related</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">CC To (Optional)</label>
                            <input type="email" name="cc_email" class="form-control" placeholder="manager@example.com">
                            <p class="text-xs text-gray-400 mt-1">Notify your reporting manager or team lead.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Detailed Description <span class="text-red-500">*</span></label>
                        <textarea name="description" id="description" class="form-control" rows="8" placeholder="Please describe the issue in detail. Include error messages if applicable..." required minlength="10"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Attachments (Screenshots/Docs)</label>
                        <div class="file-upload-wrapper" onclick="document.getElementById('fileInput').click()">
                            <input type="file" id="fileInput" name="attachment" class="hidden" onchange="showFileName(this)">
                            <i class="fa-solid fa-cloud-arrow-up text-4xl text-gray-300 mb-3"></i>
                            <p class="text-base font-medium text-gray-600">Click to upload or drag and drop</p>
                            <p class="text-xs text-gray-400 mt-1">Supported: JPG, PNG, PDF, SVG (Max size: 5MB)</p>
                            <p id="fileNameDisplay" class="text-sm text-teal-700 font-bold mt-3 hidden"></p>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-8 mt-6 flex justify-end gap-3">
                        <button type="button" class="btn-cancel" onclick="window.history.back()">Cancel</button>
                        <button type="submit" name="submit_ticket" class="btn-submit shadow-lg shadow-teal-900/20">
                            <i class="fa-regular fa-paper-plane mr-2"></i> Submit Ticket
                        </button>
                    </div>

                </div>
            </div>
        </form>
    </div>

</div>

<?php if ($show_success_alert): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Ticket Raised Successfully!',
            text: 'Your ticket has been sent to the IT team.',
            icon: 'success',
            confirmButtonColor: '#1b5a5a'
        }).then(() => {
            // RELOAD THE PAGE to generate a brand new Ticket ID
            window.location.href = window.location.href; 
        });
    });
</script>
<?php endif; ?>

<?php if ($show_error_alert): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            title: 'Error!',
            text: 'Failed to raise the ticket. Please try again.',
            icon: 'error',
            confirmButtonColor: '#1b5a5a'
        });
    });
</script>
<?php endif; ?>

<script>
    // 1. File Upload Logic
    function showFileName(input) {
        const display = document.getElementById('fileNameDisplay');
        if (input.files && input.files[0]) {
            display.textContent = "Selected File: " + input.files[0].name;
            display.classList.remove('hidden');
            
            // File Validation Trigger
            validateFile(input.files[0]);
        } else {
            display.classList.add('hidden');
        }
    }

    // 2. Validation Logic
    function validateFile(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'application/pdf'];

        if (file.size > maxSize) {
            Swal.fire('File Too Large', 'Maximum allowed size is 5MB.', 'error');
            document.getElementById('fileInput').value = ""; // Clear input
            document.getElementById('fileNameDisplay').classList.add('hidden');
            return false;
        }

        if (!allowedTypes.includes(file.type)) {
            Swal.fire('Invalid File', 'Only JPG, PNG, SVG, and PDF are allowed.', 'error');
            document.getElementById('fileInput').value = ""; // Clear input
            document.getElementById('fileNameDisplay').classList.add('hidden');
            return false;
        }
        return true;
    }

    // 3. Form Submission Validation
    document.getElementById('ticketForm').addEventListener('submit', function(e) {
        const subject = document.getElementById('subject').value.trim();
        const description = document.getElementById('description').value.trim();
        const department = document.getElementById('department').value;
        const priority = document.getElementById('priority').value;

        if (subject.length < 5) {
            e.preventDefault();
            Swal.fire('Validation Error', 'Subject must be at least 5 characters long.', 'warning');
            return;
        }

        if (description.length < 10) {
            e.preventDefault();
            Swal.fire('Validation Error', 'Description must be at least 10 characters long.', 'warning');
            return;
        }

        if (department === "" || priority === "") {
            e.preventDefault();
            Swal.fire('Validation Error', 'Please select both Department and Priority.', 'warning');
            return;
        }
    });
</script>

</body>
</html>