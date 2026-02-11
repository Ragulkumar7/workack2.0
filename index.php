<?php
// index.php

// 1. Include your DB connection
require_once './include/db_connect.php';

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['auth_action'])) {
    // Basic sanitization
    $user_input = trim($_POST['username']);
    $pass_input = $_POST['password'];
    $role_input = $_POST['role'];

    // 2. MySQLi Prepared Statement
    $sql = "SELECT id, username, password, role FROM users WHERE username = ? AND role = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // Bind parameters: "ss" means two strings (username, role)
        mysqli_stmt_bind_param($stmt, "ss", $user_input, $role_input);
        
        // Execute the query
        mysqli_stmt_execute($stmt);
        
        // Get the result
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // 3. Verify Password
            if (password_verify($pass_input, $row['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $row['role'];

                // Regenerate session ID for security
                session_regenerate_id(true);

                // --- FIX: DYNAMIC REDIRECT BASED ON ROLE ---
                switch ($row['role']) {
                    case 'Manager':
                        header("Location: dashboard.php");
                        break;
                    case 'System Admin':
                    case 'HR':
                        // Managers go to the main root dashboard
                        header("Location: dashboard.php");
                        break;
                    
                    case 'Team Lead':
                        // Team Leads go to the TL folder
                        header("Location: TL/tl_dashboard.php");
                        break;

                    case 'Employee':
                        header("Location: employee/employee_dashboard.php");
                        break;
                    case 'Sales':             // Group other roles to employee dashboard if needed
                    case 'Accounts':
                        // Accounts go to the Accounts folder
                        header("Location: Accounts/Accounts_dashboard.php");
                        break;
                    case 'IT Admin':
                        // IT Admin go to the IT Admin folder
                        header("Location: ITadmin/ITadmin_dashboard.php");
                        break;
                    case 'IT Executive':
                        // IT Executive go to the IT Executive folder
                        header("Location: IT_Executive/ITExecutive_dashboard.php");
                        break;


                    case 'Digital Marketing':
                        // Employees go to the employee folder
                        header("Location: employee/employee_dashboard.php");
                        break;

                    default:
                        // Fallback for any undefined roles
                        header("Location: employee/employee_dashboard.php");
                        break;
                }
                // -------------------------------------------
                exit();

            } else {
                $error_message = "Invalid password. Please try again.";
            }
        } else {
            $error_message = "No account found with that username and role.";
        }
        
        // Close statement
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
        .alert { padding: 14px; border-radius: 10px; font-size: 14px; margin-bottom: 25px; background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; text-align: center; }

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
                <img src="assets/logo.png" alt="Workack Logo">
                Workack
            </div>

            <h2>Welcome Back</h2>
            <p class="subtitle">Please enter your credentials to access your dashboard.</p>

            <?php if ($error_message): ?>
                <div class="alert"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="group">
                    <label>Username / Email</label>
                    <input type="text" name="username" id="user_in" placeholder="employee@gmail.com" required>
                </div>
                <div class="group">
                    <label>Password</label>
                    <input type="password" name="password" id="pass_in" placeholder="•••••" required>
                </div>
                <div class="group">
                    <label>Operational Role</label>
                    <select name="role" id="role_sel" onchange="updateEx()">
                        <option value="System Admin">System Admin</option>
                        <option value="HR">HR</option>
                        <option value="Manager">Manager</option>
                        <option value="Team Lead">Team Lead</option>
                        <option value="Employee" selected>Employee</option>
                        <option value="Accounts">Accounts</option>
                        <option value="Sales">Sales</option>
                        <option value="Digital Marketing">Digital Marketing</option>
                        <option value="IT Admin">IT Admin</option>
                        <option value="IT Executive">IT Executive</option>
                    </select>
                </div>
                <button type="submit" name="auth_action" class="btn-primary">Sign In to Workack</button>
            </form>
        </div>
        <div class="footer">&copy; 2026 Workack HRMS. All rights reserved by <b>neoerainfotech.in</b></div>
    </section>
</div>

<script>
    function updateEx() {
        const r = document.getElementById('role_sel').value.toLowerCase().replace(' ', '');
        document.getElementById('user_in').placeholder = `e.g. ${r}@gmail.com`;
    }

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
        updateEx(); 
    };
</script>
</body>
</html>