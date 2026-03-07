<?php
// index.php

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

define('ROOT_PATH', __DIR__ . '/');

// Include the DB connection securely
require_once ROOT_PATH . 'include/db_connect.php';

// =========================================================================
// AUTO-PATCHER: Ensure 'status' column exists in the users table
// =========================================================================
if (isset($conn)) {
    $check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($check_col && $check_col->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(50) DEFAULT 'Active'");
    }
}
// =========================================================================

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['auth_action'])) {
    // Basic sanitization
    $user_input = trim($_POST['username']);
    $pass_input = $_POST['password'];

    // 1. ADVANCED SECURE QUERY: Check BOTH Users and Employee_Profiles tables simultaneously
    // Also allows logging in with either Username OR Email
    $sql = "SELECT u.id, u.username, u.password, u.role, u.status as auth_status, ep.status as hr_status 
            FROM users u 
            LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
            WHERE u.username = ? OR u.email = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ss", $user_input, $user_input);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            
            if (password_verify($pass_input, $row['password']) || $pass_input === $row['password']) { 
                
                // =========================================================
                // 2. DUAL-LAYER TERMINATION CHECK
                // =========================================================
                $auth_status = $row['auth_status'] ?? 'Active'; 
                $hr_status = $row['hr_status'] ?? 'Active'; 
                
                // If EITHER the Auth table OR the HR table says Inactive/Terminated -> BLOCK!
                if (strcasecmp($auth_status, 'Inactive') === 0 || strcasecmp($hr_status, 'Inactive') === 0 || strcasecmp($hr_status, 'Terminated') === 0) {
                    
                    // Annihilate the session completely
                    session_unset();
                    session_destroy();
                    session_start();
                    
                    $error_message = "Access Denied: Your account has been permanently deactivated. Please contact HR.";
                } else {
                    // =========================================================
                    // 3. SUCCESSFUL LOGIN: SECURE SESSION GENERATION
                    // =========================================================
                    session_regenerate_id(true); // Prevent Session Fixation attacks
                    
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];

                    // --- DYNAMIC REDIRECT BASED ON ROLE ---
                    switch ($row['role']) {
                        case 'Manager':
                            header("Location: manager/manager_dashboard.php");
                            break;
                        case 'System Admin':
                        case 'HR':
                            header("Location: HR/hr_dashboard.php");
                            break;
                        case 'Team Lead':
                            header("Location: TL/tl_dashboard.php");
                            break;
                        case 'HR Executive':
                            header("Location: HR_executive/HR_executive_dashboard.php");
                            break;
                        case 'Employee':
                            header("Location: employee/employee_dashboard.php");
                            break;
                        case 'Accounts':
                            header("Location: Accounts/Accounts_dashboard.php");
                            break;
                        case 'Sales Manager':  
                            header("Location: sales_manager/sales_dashboard.php");
                            break;           
                        case 'Sales Executive':
                            header("Location: sales_executive/sales_executive_dashboard.php");
                            break;
                        case 'IT Admin':
                            header("Location: ITadmin/ITadmin_dashboard.php");
                            break;
                        case 'IT Executive':
                            header("Location: IT_Executive/ITExecutive_dashboard.php");
                            break;
                        case 'CFO':
                            header("Location: CFO/cfo_dashboard.php");
                            break;
                        default:
                            header("Location: employee/employee_dashboard.php");
                            break;
                    }
                    exit();
                }

            } else {
                $error_message = "Invalid password. Please try again.";
            }
        } else {
            $error_message = "Account not found. Please check your username or email.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Database error: Could not prepare statement.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack HRMS | Secure Access</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-color: #16636B; 
            --brand-dark: #0a2d31;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; height: 100vh; display: flex; overflow: hidden; background: #fff; }
        .page-wrapper { display: flex; width: 100%; height: 100%; }

        .branding-side {
            flex: 1; background: var(--brand-color); color: white;
            padding: 80px; display: flex; flex-direction: column; justify-content: center; position: relative;
        }

        .branding-side h1 { font-size: 52px; font-weight: 800; line-height: 1.1; margin-bottom: 25px; min-height: 120px; white-space: pre-wrap; }
        .cursor { display: inline-block; width: 4px; background-color: #fff; margin-left: 5px; animation: blink 1s infinite; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

        .branding-side p.desc { font-size: 18px; color: #cbdada; line-height: 1.6; max-width: 500px; margin-bottom: 40px; }

        .sliding-card {
            background: rgba(255, 255, 255, 0.1); padding: 30px; border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(10px); min-height: 140px;
        }
        .sliding-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #fff; opacity: 0.7; margin-bottom: 12px; font-weight: 700; }
        #sliding-text { font-style: italic; font-size: 18px; color: #fff; transition: opacity 0.5s ease; }

        .login-side {
            flex: 1; display: flex; flex-direction: column; justify-content: center; 
            align-items: center; padding: 40px 60px; position: relative; overflow-y: auto;
        }

        .form-box { width: 100%; max-width: 400px; }
        .logo { font-weight: 800; font-size: 30px; color: var(--brand-color); margin-bottom: 40px; display: flex; align-items: center; gap: 15px; }
        .logo img { height: 80px; width: auto; object-fit: contain; }

        h2 { font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 10px; }
        .subtitle { color: var(--text-muted); margin-bottom: 30px; font-size: 15px; }

        .group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: 700; margin-bottom: 8px; color: #334155; }
        input, select { width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 15px; background: var(--bg-light); transition: 0.3s; }
        input:focus { outline: none; border-color: var(--brand-color); background: #fff; box-shadow: 0 0 0 4px rgba(22, 99, 107, 0.1); }

        .btn-primary { width: 100%; padding: 16px; background: var(--brand-color); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: all 0.3s ease; }
        .btn-primary:hover { background: var(--brand-dark); transform: translateY(-1px); }

        .footer { margin-top: 50px; width: 100%; text-align: center; font-size: 13px; color: var(--text-muted); padding-bottom: 20px; }
        .footer b { color: var(--brand-color); }
        
        .alert { padding: 14px; border-radius: 10px; font-size: 14px; margin-bottom: 25px; background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; line-height: 1.4;}
        .alert svg { width: 24px; height: 24px; fill: currentColor; flex-shrink: 0; }

        @media (max-width: 1024px) { .branding-side { display: none; } }
    </style>
</head>
<body>

<div class="page-wrapper">
    <section class="branding-side">
        <h1 id="typewriter"></h1><span class="cursor"></span>
        <p class="desc">Workack HRMS provides a modern, intuitive interface to manage payroll, attendance, and team growth in one place.</p>
        <div class="sliding-card">
            <div class="label">Workack Insights</div>
            <p id="sliding-text">"Efficiency is the foundation of a great workplace."</p>
        </div>
    </section>

    <section class="login-side">
         <div class="form-box">
            <div class="logo">
                <img src="assets/logo.png" alt="Workack Logo" onerror="this.style.display='none'">
                Workack
            </div>

            <h2>Welcome Back</h2>
            <p class="subtitle">Please enter your credentials to access your dashboard.</p>

            <?php if ($error_message): ?>
                <div class="alert">
                    <svg viewBox="0 0 20 20"><path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm1 15H9v-2h2v2zm0-4H9V5h2v6z"/></svg>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="group">
                    <label>Username / Email</label>
                    <input type="text" name="username" id="user_in" placeholder="employee@gmail.com" required autocomplete="username">
                </div>
                <div class="group">
                    <label>Password</label>
                    <input type="password" name="password" id="pass_in" placeholder="••••••••" required autocomplete="current-password">
                </div>
                <button type="submit" name="auth_action" class="btn-primary">Sign In to Workack</button>
            </form>
        </div>
        <div class="footer">&copy; <?= date('Y') ?> Workack HRMS. All rights reserved by <b>neoerainfotech.in</b></div>
    </section>
</div>

<script>
    const titleText = "Empowering Talent, \nSimplifying HR.";
    let charIndex = 0;
    const typewriter = document.getElementById('typewriter');
    function typeEffect() {
        if (charIndex < titleText.length) {
            typewriter.innerHTML += titleText.charAt(charIndex) === '\n' ? '<br>' : titleText.charAt(charIndex);
            charIndex++;
            setTimeout(typeEffect, 100);
        }
    }

    const insights = [
        '"Efficiency is the foundation of a great workplace."',
        '"Empower your employees with transparent management."',
        '"Automate payroll and focus on growing your talent."',
        '"Workack HRMS: Where data meets human connection."'
    ];
    let i = 0;
    const slidingText = document.getElementById('sliding-text');
    setInterval(() => {
        slidingText.style.opacity = 0;
        setTimeout(() => { 
            i = (i + 1) % insights.length; 
            slidingText.textContent = insights[i]; 
            slidingText.style.opacity = 1; 
        }, 500);
    }, 4000);

    window.onload = () => { 
        typeEffect(); 
    };
</script>
</body>
</html>