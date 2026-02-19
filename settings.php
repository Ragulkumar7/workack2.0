<?php 
// 1. START SESSION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- FIXED DATABASE CONNECTION (Absolute Path) ---
$dbPath = '../include/db_connect.php'; 
$sidebarPath = '../sidebars.php';
$headerPath = '../header.php';

// Include the Database Connection
if (file_exists($dbPath)) {
    include_once $dbPath;
} else {
    // Fallback if the path is slightly different in this directory
    if(file_exists('./include/db_connect.php')) {
        include_once './include/db_connect.php';
        $sidebarPath = './sidebars.php';
        $headerPath = './header.php';
    } else {
        die("<div style='color:red; font-weight:bold; padding:20px; border:2px solid red; background:#fff;'>
            Critical Error: Cannot find database file!
        </div>");
    }
}

// 2. CHECK LOGIN
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) { 
    header("Location: ../index.php"); 
    exit(); 
}

// Determine User ID (Session or GET)
$view_user_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['id']);

// --- 3. HANDLE UPDATES (POST REQUESTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Update General & Personal Info + Profile Picture
    if (isset($_POST['update_profile'])) {
        $u_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $u_desg = mysqli_real_escape_string($conn, $_POST['designation']);
        $u_phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $u_email = mysqli_real_escape_string($conn, $_POST['email']);
        $u_loc  = mysqli_real_escape_string($conn, $_POST['location']);
        $u_dob  = mysqli_real_escape_string($conn, $_POST['dob']);
        $u_gen  = mysqli_real_escape_string($conn, $_POST['gender']);
        $u_mar  = mysqli_real_escape_string($conn, $_POST['marital_status']);
        $u_nat  = mysqli_real_escape_string($conn, $_POST['nationality']);
        
        $sql = "UPDATE employee_profiles SET full_name=?, designation=?, phone=?, email=?, location=?, dob=?, gender=?, marital_status=?, nationality=? WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssi", $u_name, $u_desg, $u_phone, $u_email, $u_loc, $u_dob, $u_gen, $u_mar, $u_nat, $view_user_id);
        $stmt->execute();
        $stmt->close();

        // Handle Profile Image Upload
        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/profiles/';
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) { 
                mkdir($upload_dir, 0777, true); 
            }
            
            $file_extension = strtolower(pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_extension, $allowed_types)) {
                $new_filename = "user_" . $view_user_id . "_" . time() . "." . $file_extension;
                $target_file = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $target_file)) {
                    $img_sql = "UPDATE employee_profiles SET profile_img=? WHERE user_id=?";
                    $img_stmt = $conn->prepare($img_sql);
                    $img_stmt->bind_param("si", $new_filename, $view_user_id);
                    $img_stmt->execute();
                    $img_stmt->close();
                }
            }
        }
        
        $_SESSION['toast'] = "Profile updated successfully!";
    }

    // B. Update Password (Security Settings)
    if (isset($_POST['update_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass     = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $view_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_data = $res->fetch_assoc();

        if ($user_data && password_verify($current_pass, $user_data['password'])) {
            if ($new_pass === $confirm_pass) {
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt_upd = $conn->prepare("UPDATE users SET password = ?, last_password_change = NOW() WHERE id = ?");
                $stmt_upd->bind_param("si", $new_hash, $view_user_id);
                if($stmt_upd->execute()) {
                    $_SESSION['toast'] = "Password updated successfully!";
                }
                $stmt_upd->close();
            } else {
                $_SESSION['toast_err'] = "New passwords do not match.";
            }
        } else {
            $_SESSION['toast_err'] = "Current password is incorrect.";
        }
        $stmt->close();
    }

    // C. Update Emergency Contacts
    if (isset($_POST['update_emergency'])) {
        $contacts = [];
        if(!empty($_POST['em_name_1'])) { $contacts[] = ['name' => $_POST['em_name_1'], 'relation' => $_POST['em_rel_1'], 'phone' => $_POST['em_phone_1']]; }
        if(!empty($_POST['em_name_2'])) { $contacts[] = ['name' => $_POST['em_name_2'], 'relation' => $_POST['em_rel_2'], 'phone' => $_POST['em_phone_2']]; }
        $json_contacts = json_encode($contacts);
        $stmt = $conn->prepare("UPDATE employee_profiles SET emergency_contacts=? WHERE user_id=?");
        $stmt->bind_param("si", $json_contacts, $view_user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['toast'] = "Emergency contacts updated!";
    }

    // D. Update Family Info
    if (isset($_POST['update_family'])) {
        $family = [];
        if(!empty($_POST['fam_name_1'])) { $family[] = ['name' => $_POST['fam_name_1'], 'relation' => $_POST['fam_rel_1'], 'dob' => $_POST['fam_dob_1'], 'phone' => $_POST['fam_phone_1']]; }
        $json_family = json_encode($family);
        $stmt = $conn->prepare("UPDATE employee_profiles SET family_info=? WHERE user_id=?");
        $stmt->bind_param("si", $json_family, $view_user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['toast'] = "Family information updated!";
    }

    // E. Update Experience
    if (isset($_POST['update_experience'])) {
        $experience = [];
        if(!empty($_POST['exp_comp_1'])) { $experience[] = ['company' => $_POST['exp_comp_1'], 'role' => $_POST['exp_role_1'], 'duration' => $_POST['exp_dur_1']]; }
        if(!empty($_POST['exp_comp_2'])) { $experience[] = ['company' => $_POST['exp_comp_2'], 'role' => $_POST['exp_role_2'], 'duration' => $_POST['exp_dur_2']]; }
        $json_exp = json_encode($experience);
        $stmt = $conn->prepare("UPDATE employee_profiles SET experience_history=? WHERE user_id=?");
        $stmt->bind_param("si", $json_exp, $view_user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['toast'] = "Experience updated!";
    }

    // F. Update Education
    if (isset($_POST['update_education'])) {
        $education = [];
        if(!empty($_POST['edu_school_1'])) { $education[] = ['school' => $_POST['edu_school_1'], 'degree' => $_POST['edu_deg_1'], 'year' => $_POST['edu_year_1']]; }
        if(!empty($_POST['edu_school_2'])) { $education[] = ['school' => $_POST['edu_school_2'], 'degree' => $_POST['edu_deg_2'], 'year' => $_POST['edu_year_2']]; }
        $json_edu = json_encode($education);
        $stmt = $conn->prepare("UPDATE employee_profiles SET education_history=? WHERE user_id=?");
        $stmt->bind_param("si", $json_edu, $view_user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['toast'] = "Education updated!";
    }

    // G. Update Bank Info
    if (isset($_POST['update_bank'])) {
        $bank_data = ['bank_name' => $_POST['bank_name'], 'acc_no' => $_POST['acc_no'], 'ifsc' => $_POST['ifsc'], 'pan' => $_POST['pan']];
        $json_bank = json_encode($bank_data);
        $stmt = $conn->prepare("UPDATE employee_profiles SET bank_info=? WHERE user_id=?");
        $stmt->bind_param("si", $json_bank, $view_user_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['toast'] = "Bank details updated!";
    }

    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['id']) ? "?id=".$_GET['id'] : ""));
    exit();
}

// --- 4. FETCH DATA FOR DISPLAY ---
if (isset($conn)) {
    $stmt = $conn->prepare("SELECT * FROM employee_profiles WHERE user_id = ?");
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
}

// Default Data Wrappers
$emergency_contacts = !empty($profile['emergency_contacts']) ? json_decode($profile['emergency_contacts'], true) : [];
$family_info        = !empty($profile['family_info'])        ? json_decode($profile['family_info'], true)        : [];
$experience_list    = !empty($profile['experience_history']) ? json_decode($profile['experience_history'], true) : [];
$education_list     = !empty($profile['education_history'])  ? json_decode($profile['education_history'], true)  : [];
$bank_info          = !empty($profile['bank_info'])          ? json_decode($profile['bank_info'], true)          : [];

// Format Profile Image for Display
$display_img = "https://ui-avatars.com/api/?name=".urlencode($profile['full_name'] ?? 'User')."&background=1b5a5a&color=fff";
if (!empty($profile['profile_img']) && $profile['profile_img'] !== 'default_user.png') {
    if (str_starts_with($profile['profile_img'], 'http')) {
        $display_img = $profile['profile_img'];
    } else {
        $display_img = '../assets/profiles/' . $profile['profile_img'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | Settings</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-light: #f8fafc;
            --white: #ffffff;
            --primary-orange: #1b5a5a; 
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
        }

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; overflow-x: hidden; }
        
        /* --- SIDEBAR INTEGRATION CSS --- */
        #mainContent { 
            margin-left: 95px; 
            padding: 30px; 
            transition: margin-left 0.3s ease, width 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
            box-sizing: border-box;
        }
        #mainContent.main-shifted { 
            margin-left: 315px; width: calc(100% - 315px); 
        }
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; margin: 0; font-weight: 700; letter-spacing: -0.5px; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        .top-settings-nav { display: flex; background: var(--white); padding: 0 15px; border-radius: 12px; border: 1px solid var(--border-light); margin-bottom: 30px; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .top-nav-item { padding: 16px 24px; text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 600; white-space: nowrap; border-bottom: 3px solid transparent; cursor: pointer; transition: 0.2s;}
        .top-nav-item:hover { color: var(--primary-orange); }
        .top-nav-item.active { color: var(--primary-orange); border-bottom: 3px solid var(--primary-orange); }

        .settings-container { display: grid; grid-template-columns: 280px 1fr; gap: 30px; }
        .side-nav-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; padding: 15px 0; height: fit-content; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .nav-link-custom { display: flex; justify-content: space-between; align-items: center; padding: 14px 24px; color: var(--text-dark); text-decoration: none; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .nav-link-custom:hover { background-color: #f8fafc; }
        .submenu { display: none; background: #fafafa; padding-left: 10px; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; }
        .submenu.show { display: block; }
        .submenu-item { display: block; padding: 12px 24px; color: var(--text-muted); text-decoration: none; font-size: 13px; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .submenu-item:hover, .submenu-item.active { color: var(--primary-orange); background-color: #f0fdfa; border-radius: 0 20px 20px 0; margin-right: 10px;}

        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; padding: 0; display: none; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .content-card.active { display: block; animation: fadeIn 0.3s ease; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* --- PROFILE SECTION STYLES --- */
        .profile-container { padding: 30px; }
        .profile-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; padding: 30px 20px; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .profile-img-container { position: relative; width: 130px; margin: 0 auto 15px auto; }
        .profile-img { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        
        .emp-name { font-weight: 700; font-size: 1.2rem; color: #0f172a; margin-bottom: 5px; }
        .emp-designation { color: #64748b; font-size: 0.9rem; font-weight: 500; margin-bottom: 15px; }
        .badge-pill { padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-dept { background: #f1f5f9; color: #475569; }
        .badge-exp { background: #e0f2fe; color: #0284c7; }
        
        .section-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden; }
        .card-header-custom { padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background: #fcfcfd; display: flex; justify-content: space-between; align-items: center; }
        .card-header-custom h6 { margin: 0; font-weight: 700; color: #0f172a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        .card-body-custom { padding: 24px; }
        
        .btn-edit-card { background: transparent; border: 1px solid #e2e8f0; color: #64748b; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 12px; }
        .btn-edit-card:hover { background: #f0fdfa; color: var(--primary-orange); border-color: #ccfbf1; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .data-label { display: block; font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 4px; letter-spacing: 0.5px; }
        .data-value { font-size: 0.95rem; color: #0f172a; font-weight: 500; }
        
        .timeline-item { position: relative; padding-left: 20px; margin-bottom: 15px; border-left: 2px solid #e2e8f0; }
        .timeline-title { font-weight: 600; color: #0f172a; font-size: 0.95rem; }
        .timeline-subtitle { font-size: 0.85rem; color: #64748b; }
        .contact-list-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; font-size: 0.9rem; }
        .contact-list-item:last-child { border-bottom: none; padding-bottom: 0; }
        
        .modal-content { border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); border-radius: 16px; }
        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 20px 24px; }
        .modal-body { padding: 24px; }
        .form-control, .form-select { padding: 10px 15px; font-size: 14px; border-radius: 8px; border-color: #e2e8f0; }
        .form-control:focus, .form-select:focus { border-color: var(--primary-orange); box-shadow: 0 0 0 0.25rem rgba(27, 90, 90, 0.1); }
        .btn-dark { background-color: var(--text-dark); border: none; padding: 10px 20px; border-radius: 8px; font-weight: 500; }
        .btn-dark:hover { background-color: var(--primary-orange); }

        /* Responsiveness */
        @media (max-width: 992px) {
            .settings-container { grid-template-columns: 1fr; }
            .side-nav-card { margin-bottom: 0; }
        }
        
        @media (max-width: 768px) {
            #mainContent { margin-left: 0 !important; width: 100% !important; padding: 20px; }
            .top-settings-nav { flex-wrap: nowrap; overflow-x: auto; padding: 0 10px; }
            .top-nav-item { padding: 12px 15px; font-size: 13px; }
            .profile-card { margin-bottom: 24px; height: auto; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <?php include($sidebarPath); ?>

    <div id="mainContent">
        <?php include($headerPath); ?>

        <div class="page-header">
            <h1>Settings</h1>
            <div class="breadcrumb" id="breadcrumb-text">Settings / General Settings / Profile Settings</div>
        </div>

        <?php if(isset($_SESSION['toast'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['toast']; unset($_SESSION['toast']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['toast_err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['toast_err']; unset($_SESSION['toast_err']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="top-settings-nav">
            <div class="top-nav-item active" onclick="showSection('profile', this, 'General Settings')">
                <i class="fas fa-user-circle me-2"></i> Profile & General
            </div>
        </div>

        <div class="settings-container">
            <aside class="side-nav-card">
                <div class="nav-link-custom" onclick="toggleSub('general-submenu', 'arrow-gen')">
                    <span>General Settings</span><i class="fas fa-chevron-down text-muted" id="arrow-gen"></i>
                </div>
                <div class="submenu show" id="general-submenu">
                    <div class="submenu-item active" onclick="showSection('profile', this, 'General Settings')">» Profile Settings</div>
                    <div class="submenu-item" onclick="showSection('security', this, 'General Settings')">Security Settings</div>
                </div>
            </aside>

            <div id="profile-card" class="content-card active">
                <div class="profile-container">
                    <?php if (!$profile): ?>
                        <div class="alert alert-warning border-0 rounded-3 shadow-sm">
                            <i class="fas fa-info-circle me-2"></i> Profile data not found for this user.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-lg-4 mb-4">
                                <div class="profile-card">
                                    <button class="btn-edit-card position-absolute top-0 end-0 mt-3 me-3" data-bs-toggle="modal" data-bs-target="#editProfileModal" title="Edit Profile">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <div class="profile-img-container">
                                        <img src="<?php echo $display_img; ?>" class="profile-img" alt="Profile Image">
                                    </div>
                                    <h5 class="emp-name"><?php echo htmlspecialchars($profile['full_name']); ?></h5>
                                    <p class="emp-designation"><?php echo htmlspecialchars($profile['designation']); ?></p>
                                    <div class="d-flex justify-content-center gap-2 mb-4">
                                        <span class="badge-pill badge-dept"><?php echo htmlspecialchars($profile['department'] ?? 'No Dept'); ?></span>
                                        <span class="badge-pill badge-exp"><?php echo htmlspecialchars($profile['experience_label'] ?? 'New'); ?></span>
                                    </div>
                                    
                                    <div class="text-start bg-light p-3 rounded-3 border border-light">
                                        <div class="contact-list-item pt-0">
                                            <span class="text-muted small">Employee ID</span>
                                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($profile['emp_id_code'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="contact-list-item">
                                            <span class="text-muted small">Email</span>
                                            <span class="fw-bold text-dark" style="font-size:12px;"><?php echo htmlspecialchars($profile['email']); ?></span>
                                        </div>
                                        <div class="contact-list-item pb-0">
                                            <span class="text-muted small">Phone</span>
                                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($profile['phone']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-card mt-4">
                                    <div class="card-header-custom">
                                        <h6><i class="fas fa-heartbeat me-2 text-danger"></i> Emergency Contacts</h6>
                                        <button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editEmergencyModal"><i class="fas fa-pen"></i></button>
                                    </div>
                                    <div class="card-body-custom pt-0">
                                        <?php if (!empty($emergency_contacts)): foreach($emergency_contacts as $contact): ?>
                                            <div class="contact-list-item">
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($contact['name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($contact['relation']); ?></small>
                                                </div>
                                                <div class="fw-bold text-primary-orange"><?php echo htmlspecialchars($contact['phone']); ?></div>
                                            </div>
                                        <?php endforeach; else: ?>
                                            <div class="text-center text-muted small mt-4 pb-2"><i class="fas fa-user-shield mb-2" style="font-size:24px; opacity:0.2;"></i><br>No emergency contacts added.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-8">
                                <div class="section-card">
                                    <div class="card-header-custom">
                                        <h6><i class="fas fa-address-card me-2 text-primary"></i> Personal Information</h6>
                                        <button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fas fa-pen"></i></button>
                                    </div>
                                    <div class="card-body-custom">
                                        <div class="info-grid">
                                            <div class="info-item"><span class="data-label">Full Name</span><span class="data-value"><?php echo htmlspecialchars($profile['full_name']); ?></span></div>
                                            <div class="info-item"><span class="data-label">DOB</span><span class="data-value"><?php echo !empty($profile['dob']) ? date('d M, Y', strtotime($profile['dob'])) : '-'; ?></span></div>
                                            <div class="info-item"><span class="data-label">Gender</span><span class="data-value"><?php echo htmlspecialchars($profile['gender'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">Marital Status</span><span class="data-value"><?php echo htmlspecialchars($profile['marital_status'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">Nationality</span><span class="data-value"><?php echo htmlspecialchars($profile['nationality'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">Joined Date</span><span class="data-value"><?php echo !empty($profile['joining_date']) ? date('d M, Y', strtotime($profile['joining_date'])) : '-'; ?></span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-card">
                                    <div class="card-header-custom">
                                        <h6><i class="fas fa-users me-2 text-success"></i> Family Information</h6>
                                        <button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editFamilyModal"><i class="fas fa-pen"></i></button>
                                    </div>
                                    <div class="card-body-custom">
                                        <?php if (!empty($family_info)): ?>
                                            <div class="row">
                                            <?php foreach($family_info as $fam): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="border border-light rounded-3 p-3 bg-light h-100">
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($fam['name']); ?></div>
                                                            <span class="badge bg-white text-secondary border"><?php echo htmlspecialchars($fam['relation']); ?></span>
                                                        </div>
                                                        <div class="small text-muted mb-1"><i class="fas fa-birthday-cake me-1"></i> <?php echo htmlspecialchars($fam['dob']); ?></div>
                                                        <div class="small text-muted"><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($fam['phone']); ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted small"><i class="fas fa-info-circle me-1"></i> No family information added.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="section-card h-100">
                                            <div class="card-header-custom">
                                                <h6><i class="fas fa-briefcase me-2 text-warning"></i> Experience</h6>
                                                <button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editExperienceModal"><i class="fas fa-pen"></i></button>
                                            </div>
                                            <div class="card-body-custom">
                                                <?php if (!empty($experience_list)): foreach($experience_list as $job): ?>
                                                    <div class="timeline-item">
                                                        <div class="timeline-title"><?php echo htmlspecialchars($job['company']); ?></div>
                                                        <div class="timeline-subtitle"><?php echo htmlspecialchars($job['role']); ?></div>
                                                        <span class="badge bg-light text-dark border mt-1"><i class="far fa-clock me-1"></i> <?php echo htmlspecialchars($job['duration']); ?></span>
                                                    </div>
                                                <?php endforeach; else: ?>
                                                    <div class="text-muted small"><i class="fas fa-info-circle me-1"></i> No experience added.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="section-card h-100">
                                            <div class="card-header-custom">
                                                <h6><i class="fas fa-graduation-cap me-2 text-info"></i> Education</h6>
                                                <button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editEducationModal"><i class="fas fa-pen"></i></button>
                                            </div>
                                            <div class="card-body-custom">
                                                <?php if (!empty($education_list)): foreach($education_list as $edu): ?>
                                                    <div class="timeline-item">
                                                        <div class="timeline-title"><?php echo htmlspecialchars($edu['school']); ?></div>
                                                        <div class="timeline-subtitle"><?php echo htmlspecialchars($edu['degree']); ?></div>
                                                        <span class="badge bg-light text-dark border mt-1"><i class="far fa-calendar-alt me-1"></i> Class of <?php echo htmlspecialchars($edu['year']); ?></span>
                                                    </div>
                                                <?php endforeach; else: ?>
                                                    <div class="text-muted small"><i class="fas fa-info-circle me-1"></i> No education added.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-card mt-4">
                                    <div class="card-header-custom">
                                        <h6><i class="fas fa-university me-2 text-secondary"></i> Bank Information</h6>
                                        <button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editBankModal"><i class="fas fa-pen"></i></button>
                                    </div>
                                    <div class="card-body-custom">
                                        <div class="info-grid">
                                            <div class="info-item"><span class="data-label">Bank Name</span><span class="data-value"><?php echo htmlspecialchars($bank_info['bank_name'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">Account No</span><span class="data-value"><?php echo htmlspecialchars($bank_info['acc_no'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">IFSC Code</span><span class="data-value"><?php echo htmlspecialchars($bank_info['ifsc'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">PAN Number</span><span class="data-value"><?php echo htmlspecialchars($bank_info['pan'] ?? '-'); ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="security-card" class="content-card" style="padding:30px;">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-light">
                    <i class="fas fa-shield-alt fs-4 text-primary-orange me-3"></i>
                    <h4 class="mb-0 fw-bold">Security Settings</h4>
                </div>
                
                <div class="security-section mb-5">
                    <h5 style="font-size:16px; margin-bottom:15px; font-weight:700; color:#0f172a;">Change Password</h5>
                    <p class="small text-muted mb-4">Ensure your account is using a long, random password to stay secure.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        <div class="row g-4">
                            <div class="col-md-12 col-lg-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Current Password</label>
                                <input type="password" name="current_password" class="form-control bg-light" placeholder="Enter current password" required>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">New Password</label>
                                <input type="password" name="new_password" class="form-control bg-light" placeholder="Enter new password" required>
                            </div>
                            <div class="col-md-6 col-lg-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control bg-light" placeholder="Confirm new password" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-dark px-4 py-2"><i class="fas fa-key me-2"></i> Update Password</button>
                        </div>
                    </form>
                </div>

                <div class="security-section d-flex justify-content-between align-items-center bg-light p-4 rounded-3 border border-light">
                    <div>
                        <h5 style="font-size:16px; margin-bottom:5px; font-weight:700; color:#0f172a;">Two-Factor Authentication</h5>
                        <p class="text-muted small m-0">Add an extra layer of security to your account requiring an OTP code.</p>
                    </div>
                    <div class="form-check form-switch fs-4 m-0">
                        <input class="form-check-input cursor-pointer" type="checkbox" role="switch" id="flexSwitchCheckDefault">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2 text-primary-orange"></i> Edit Profile Info</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="d-flex flex-column align-items-center justify-content-center mb-4 pb-4 border-bottom text-center">
                            <img src="<?php echo $display_img; ?>" id="imgPreview" class="rounded-circle border border-3 border-light shadow-sm mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                            
                            <label for="profile_img" class="btn btn-sm btn-outline-dark px-4 rounded-pill cursor-pointer mb-2 shadow-sm">
                                <i class="fas fa-camera me-2"></i> Change Photo
                            </label>
                            <input type="file" name="profile_img" id="profile_img" class="d-none" accept="image/*" onchange="previewImage(event)">
                            
                            <span class="small text-muted">JPG, PNG or WEBP. Max size 2MB.</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label small fw-bold text-muted">Full Name</label><input type="text" name="full_name" class="form-control bg-light" value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold text-muted">Designation</label><input type="text" name="designation" class="form-control bg-light" value="<?php echo htmlspecialchars($profile['designation'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold text-muted">Phone Number</label><input type="text" name="phone" class="form-control bg-light" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label small fw-bold text-muted">Email Address</label><input type="email" name="email" class="form-control bg-light" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>"></div>
                            <div class="col-md-12"><label class="form-label small fw-bold text-muted">Current Location</label><input type="text" name="location" class="form-control bg-light" value="<?php echo htmlspecialchars($profile['location'] ?? ''); ?>"></div>
                            <div class="col-md-4"><label class="form-label small fw-bold text-muted">Date of Birth</label><input type="date" name="dob" class="form-control bg-light" value="<?php echo $profile['dob'] ?? ''; ?>"></div>
                            <div class="col-md-4"><label class="form-label small fw-bold text-muted">Gender</label>
                                <select name="gender" class="form-select bg-light">
                                    <option value="Male" <?php echo (($profile['gender']??'')=='Male')?'selected':''; ?>>Male</option>
                                    <option value="Female" <?php echo (($profile['gender']??'')=='Female')?'selected':''; ?>>Female</option>
                                    <option value="Other" <?php echo (($profile['gender']??'')=='Other')?'selected':''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label small fw-bold text-muted">Marital Status</label>
                                <select name="marital_status" class="form-select bg-light">
                                    <option value="Single" <?php echo (($profile['marital_status']??'')=='Single')?'selected':''; ?>>Single</option>
                                    <option value="Married" <?php echo (($profile['marital_status']??'')=='Married')?'selected':''; ?>>Married</option>
                                </select>
                            </div>
                            <div class="col-md-12"><label class="form-label small fw-bold text-muted">Nationality</label><input type="text" name="nationality" class="form-control bg-light" value="<?php echo htmlspecialchars($profile['nationality'] ?? ''); ?>"></div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark px-4"><i class="fas fa-save me-2"></i> Save Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEmergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Emergency Contacts</h5><button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_emergency" value="1">
                        <h6 class="small fw-bold text-primary-orange mb-3 border-bottom pb-2">Primary Contact</h6>
                        <div class="row g-2 mb-4">
                            <div class="col-12"><input type="text" name="em_name_1" class="form-control bg-light" placeholder="Full Name" value="<?php echo htmlspecialchars($emergency_contacts[0]['name'] ?? ''); ?>"></div>
                            <div class="col-6"><input type="text" name="em_rel_1" class="form-control bg-light" placeholder="Relationship" value="<?php echo htmlspecialchars($emergency_contacts[0]['relation'] ?? ''); ?>"></div>
                            <div class="col-6"><input type="text" name="em_phone_1" class="form-control bg-light" placeholder="Phone Number" value="<?php echo htmlspecialchars($emergency_contacts[0]['phone'] ?? ''); ?>"></div>
                        </div>
                        <h6 class="small fw-bold text-primary-orange mb-3 border-bottom pb-2">Secondary Contact</h6>
                        <div class="row g-2">
                            <div class="col-12"><input type="text" name="em_name_2" class="form-control bg-light" placeholder="Full Name" value="<?php echo htmlspecialchars($emergency_contacts[1]['name'] ?? ''); ?>"></div>
                            <div class="col-6"><input type="text" name="em_rel_2" class="form-control bg-light" placeholder="Relationship" value="<?php echo htmlspecialchars($emergency_contacts[1]['relation'] ?? ''); ?>"></div>
                            <div class="col-6"><input type="text" name="em_phone_2" class="form-control bg-light" placeholder="Phone Number" value="<?php echo htmlspecialchars($emergency_contacts[1]['phone'] ?? ''); ?>"></div>
                        </div>
                        <div class="mt-4 text-end"><button type="submit" class="btn btn-dark px-4">Save Contacts</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editFamilyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Family Information</h5><button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_family" value="1">
                        <div class="p-3 bg-light rounded-3 border border-light">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="small text-muted fw-bold">Name</label><input type="text" name="fam_name_1" class="form-control" placeholder="Relative Name" value="<?php echo htmlspecialchars($family_info[0]['name'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label class="small text-muted fw-bold">Relationship</label><input type="text" name="fam_rel_1" class="form-control" placeholder="e.g. Spouse" value="<?php echo htmlspecialchars($family_info[0]['relation'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label class="small text-muted fw-bold">Date of Birth</label><input type="date" name="fam_dob_1" class="form-control" value="<?php echo htmlspecialchars($family_info[0]['dob'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label class="small text-muted fw-bold">Phone Number</label><input type="text" name="fam_phone_1" class="form-control" placeholder="Contact" value="<?php echo htmlspecialchars($family_info[0]['phone'] ?? ''); ?>"></div>
                            </div>
                        </div>
                        <div class="mt-4 text-end"><button type="submit" class="btn btn-dark px-4">Save Information</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editExperienceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Work Experience</h5><button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_experience" value="1">
                        <h6 class="small fw-bold text-muted text-uppercase mb-2">Most Recent Role</h6>
                        <div class="row g-2 mb-4 p-3 bg-light rounded-3">
                            <div class="col-md-6"><input type="text" name="exp_comp_1" class="form-control" placeholder="Company Name" value="<?php echo htmlspecialchars($experience_list[0]['company'] ?? ''); ?>"></div>
                            <div class="col-md-6"><input type="text" name="exp_role_1" class="form-control" placeholder="Job Title" value="<?php echo htmlspecialchars($experience_list[0]['role'] ?? ''); ?>"></div>
                            <div class="col-12"><input type="text" name="exp_dur_1" class="form-control" placeholder="Duration (e.g. 2020 - 2023)" value="<?php echo htmlspecialchars($experience_list[0]['duration'] ?? ''); ?>"></div>
                        </div>
                        
                        <h6 class="small fw-bold text-muted text-uppercase mb-2">Previous Role</h6>
                        <div class="row g-2 p-3 bg-light rounded-3">
                            <div class="col-md-6"><input type="text" name="exp_comp_2" class="form-control" placeholder="Company Name" value="<?php echo htmlspecialchars($experience_list[1]['company'] ?? ''); ?>"></div>
                            <div class="col-md-6"><input type="text" name="exp_role_2" class="form-control" placeholder="Job Title" value="<?php echo htmlspecialchars($experience_list[1]['role'] ?? ''); ?>"></div>
                            <div class="col-12"><input type="text" name="exp_dur_2" class="form-control" placeholder="Duration" value="<?php echo htmlspecialchars($experience_list[1]['duration'] ?? ''); ?>"></div>
                        </div>
                        <div class="mt-4 text-end"><button type="submit" class="btn btn-dark px-4">Save Experience</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEducationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Education History</h5><button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_education" value="1">
                        <h6 class="small fw-bold text-muted text-uppercase mb-2">Highest Degree</h6>
                        <div class="row g-2 mb-4 p-3 bg-light rounded-3">
                            <div class="col-12"><input type="text" name="edu_school_1" class="form-control" placeholder="University / School" value="<?php echo htmlspecialchars($education_list[0]['school'] ?? ''); ?>"></div>
                            <div class="col-md-8"><input type="text" name="edu_deg_1" class="form-control" placeholder="Degree (e.g. B.Tech Computer Science)" value="<?php echo htmlspecialchars($education_list[0]['degree'] ?? ''); ?>"></div>
                            <div class="col-md-4"><input type="text" name="edu_year_1" class="form-control" placeholder="Passing Year" value="<?php echo htmlspecialchars($education_list[0]['year'] ?? ''); ?>"></div>
                        </div>
                        
                        <h6 class="small fw-bold text-muted text-uppercase mb-2">Previous Degree</h6>
                        <div class="row g-2 p-3 bg-light rounded-3">
                            <div class="col-12"><input type="text" name="edu_school_2" class="form-control" placeholder="University / School" value="<?php echo htmlspecialchars($education_list[1]['school'] ?? ''); ?>"></div>
                            <div class="col-md-8"><input type="text" name="edu_deg_2" class="form-control" placeholder="Degree" value="<?php echo htmlspecialchars($education_list[1]['degree'] ?? ''); ?>"></div>
                            <div class="col-md-4"><input type="text" name="edu_year_2" class="form-control" placeholder="Passing Year" value="<?php echo htmlspecialchars($education_list[1]['year'] ?? ''); ?>"></div>
                        </div>
                        <div class="mt-4 text-end"><button type="submit" class="btn btn-dark px-4">Save Education</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editBankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Bank Information</h5><button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_bank" value="1">
                        <div class="p-4 bg-light rounded-3 border border-light mb-2">
                            <div class="mb-3"><label class="form-label small fw-bold text-muted">Bank Name</label><input type="text" name="bank_name" class="form-control" placeholder="e.g. State Bank of India" value="<?php echo htmlspecialchars($bank_info['bank_name'] ?? ''); ?>"></div>
                            <div class="mb-3"><label class="form-label small fw-bold text-muted">Account Number</label><input type="text" name="acc_no" class="form-control" placeholder="Enter account number" value="<?php echo htmlspecialchars($bank_info['acc_no'] ?? ''); ?>"></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label small fw-bold text-muted">IFSC Code</label><input type="text" name="ifsc" class="form-control text-uppercase" placeholder="e.g. SBIN0001234" value="<?php echo htmlspecialchars($bank_info['ifsc'] ?? ''); ?>"></div>
                                <div class="col-md-6"><label class="form-label small fw-bold text-muted">PAN Number</label><input type="text" name="pan" class="form-control text-uppercase" placeholder="e.g. ABCDE1234F" value="<?php echo htmlspecialchars($bank_info['pan'] ?? ''); ?>"></div>
                            </div>
                        </div>
                        <div class="small text-muted fst-italic"><i class="fas fa-lock me-1"></i> Your bank details are securely encrypted.</div>
                        <div class="mt-4 text-end"><button type="submit" class="btn btn-dark px-4">Save Details</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Custom Tab Switching Logic
        function showSection(sectionId, element, categoryName) {
            // Hide all cards
            document.querySelectorAll('.content-card').forEach(card => card.classList.remove('active'));
            // Show target card
            document.getElementById(sectionId + '-card').classList.add('active');

            // Reset Submenu Active States
            document.querySelectorAll('.submenu-item').forEach(item => { 
                item.classList.remove('active'); 
                item.innerText = item.innerText.replace('» ', '');
            });

            // Set clicked item active
            if (element.classList.contains('submenu-item')) {
                element.classList.add('active'); 
                element.innerText = '» ' + element.innerText;
            }

            // Update Breadcrumb
            let sectionName = element.innerText.replace('» ', '').trim();
            document.getElementById('breadcrumb-text').innerText = 'Settings / ' + categoryName + ' / ' + sectionName;
        }

        function toggleSub(id, arrowId) { 
            document.getElementById(id).classList.toggle("show"); 
            document.getElementById(arrowId).style.transform = document.getElementById(id).classList.contains("show") ? "rotate(180deg)" : "rotate(0deg)";
        }

        // Live Image Preview for Profile Upload
        function previewImage(event) {
            var reader = new FileReader();
            reader.onload = function() {
                var output = document.getElementById('imgPreview');
                output.src = reader.result;
            }
            if(event.target.files[0]){
                reader.readAsDataURL(event.target.files[0]);
            }
        }

        // Initialize Arrow Rotation
        window.onload = function() { 
            if(document.getElementById("arrow-gen")) {
                document.getElementById("arrow-gen").style.transform = "rotate(180deg)"; 
            }
        };
    </script>
</body>
</html>