<?php 
// 1. START SESSION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- FIXED DATABASE CONNECTION (Absolute Path) ---
$dbPath = './include/db_connect.php';
$sidebarPath = './sidebars.php';
$headerPath = './header.php';

// Include the Database Connection
if (file_exists($dbPath)) {
    include_once $dbPath;
} else {
    die("<div style='color:red; font-weight:bold; padding:20px; border:2px solid red; background:#fff;'>
        Critical Error: Cannot find database file!<br>
        Expected: " . htmlspecialchars($dbPath) . "
    </div>");
}

// 2. CHECK LOGIN
if (!isset($_SESSION['user_id']) && !isset($_SESSION['id'])) { 
    header("Location: index.php"); 
    exit(); 
}

// Determine User ID (Session or GET)
$view_user_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['id']);

// --- 3. HANDLE UPDATES (POST REQUESTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Update General & Personal Info
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
    }

    // B. Update Password (Security Settings)
    if (isset($_POST['update_password'])) {
        $current_pass = $_POST['current_password'];
        $new_pass     = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        // 1. Fetch current password hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $view_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_data = $res->fetch_assoc();

        if ($user_data && password_verify($current_pass, $user_data['password'])) {
            if ($new_pass === $confirm_pass) {
                // 2. Hash new password and update
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, last_password_change = NOW() WHERE id = ?");
                $stmt->bind_param("si", $new_hash, $view_user_id);
                if($stmt->execute()) {
                    echo "<script>alert('Password updated successfully!');</script>";
                }
            } else {
                echo "<script>alert('New passwords do not match.');</script>";
            }
        } else {
            echo "<script>alert('Current password is incorrect.');</script>";
        }
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
    }

    // D. Update Family Info
    if (isset($_POST['update_family'])) {
        $family = [];
        if(!empty($_POST['fam_name_1'])) { $family[] = ['name' => $_POST['fam_name_1'], 'relation' => $_POST['fam_rel_1'], 'dob' => $_POST['fam_dob_1'], 'phone' => $_POST['fam_phone_1']]; }
        $json_family = json_encode($family);
        $stmt = $conn->prepare("UPDATE employee_profiles SET family_info=? WHERE user_id=?");
        $stmt->bind_param("si", $json_family, $view_user_id);
        $stmt->execute();
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
    }

    // G. Update Bank Info
    if (isset($_POST['update_bank'])) {
        $bank_data = ['bank_name' => $_POST['bank_name'], 'acc_no' => $_POST['acc_no'], 'ifsc' => $_POST['ifsc'], 'pan' => $_POST['pan']];
        $json_bank = json_encode($bank_data);
        $stmt = $conn->prepare("UPDATE employee_profiles SET bank_info=? WHERE user_id=?");
        $stmt->bind_param("si", $json_bank, $view_user_id);
        $stmt->execute();
    }

    // Redirect to prevent form resubmission
    echo "<script>window.location.href = window.location.href;</script>";
    exit;
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

// Storage Data Calculation
$storage_used = $profile['storage_used_gb'] ?? 0;
$storage_limit = $profile['storage_limit_gb'] ?? 100;
$storage_pct = ($storage_limit > 0) ? ($storage_used / $storage_limit) * 100 : 0;
$st_docs = $profile['storage_docs_gb'] ?? 0;
$st_media = $profile['storage_media_gb'] ?? 0;
$st_sys = $profile['storage_system_gb'] ?? 0;
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
            --bg-light: #f7f7f7;
            --white: #ffffff;
            --primary-orange: #1b5a5a; 
            --text-dark: #333333;
            --text-muted: #666666;
            --border-light: #e3e3e3;
        }

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; display: block; overflow-x: hidden; }
        
        /* --- SIDEBAR INTEGRATION CSS --- */
        #mainContent { 
            margin-left: 95px; 
            padding: 30px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
            box-sizing: border-box;
        }
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        .top-settings-nav { display: flex; background: var(--white); padding: 0 15px; border-radius: 8px; border: 1px solid var(--border-light); margin-bottom: 30px; overflow-x: auto; }
        .top-nav-item { padding: 15px 20px; text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 500; white-space: nowrap; border-bottom: 3px solid transparent; cursor: pointer; }
        .top-nav-item.active { color: var(--primary-orange); border-bottom: 3px solid var(--primary-orange); }

        .settings-container { display: grid; grid-template-columns: 280px 1fr; gap: 30px; }
        .side-nav-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; padding: 15px 0; height: fit-content; }
        .nav-link-custom { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; color: var(--text-dark); text-decoration: none; font-size: 14px; cursor: pointer; }
        .submenu { display: none; background: #fafafa; padding-left: 10px; }
        .submenu.show { display: block; }
        .submenu-item { display: block; padding: 10px 20px; color: var(--text-muted); text-decoration: none; font-size: 13px; cursor: pointer; }
        .submenu-item:hover, .submenu-item.active { color: var(--primary-orange); }

        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; padding: 0; display: none; }
        .content-card.active { display: block; }
        
        /* --- PROFILE SECTION STYLES (Merged) --- */
        .profile-container { padding: 30px; }
        .profile-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; padding: 30px 20px; position: relative; height: 55%; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .profile-img { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin: 0 auto 15px auto; }
        .emp-name { font-weight: 700; font-size: 1.2rem; color: #0f172a; margin-bottom: 5px; }
        .emp-designation { color: #64748b; font-size: 0.9rem; font-weight: 500; margin-bottom: 15px; }
        .badge-pill { padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-dept { background: #f1f5f9; color: #475569; }
        .badge-exp { background: #e0f2fe; color: #0284c7; }
        
        .section-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden; }
        .card-header-custom { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; background: #fff; display: flex; justify-content: space-between; align-items: center; }
        .card-header-custom h6 { margin: 0; font-weight: 700; color: #0f172a; text-transform: uppercase; font-size: 0.8rem; }
        .card-body-custom { padding: 20px; }
        
        .btn-edit-card { background: transparent; border: 1px solid #e2e8f0; color: #64748b; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; }
        .btn-edit-card:hover { background: #f1f5f9; color: var(--primary-orange); }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .data-label { display: block; font-size: 0.7rem; text-transform: uppercase; color: #94a3b8; font-weight: 600; margin-bottom: 4px; }
        .data-value { font-size: 0.9rem; color: #0f172a; font-weight: 500; }
        
        .timeline-item { position: relative; padding-left: 20px; margin-bottom: 15px; border-left: 2px solid #e2e8f0; }
        .timeline-title { font-weight: 600; color: #0f172a; font-size: 0.95rem; }
        .timeline-subtitle { font-size: 0.85rem; color: #64748b; }
        .contact-list-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; font-size: 0.9rem; }
        
        /* Override Bootstrap Modal for cleaner look */
        .modal-content { border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
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

        <div class="top-settings-nav">
            <div class="top-nav-item active" onclick="showSection('profile', this, 'General Settings')">
                <i class="fas fa-cog"></i> General Settings
            </div>
            <div class="top-nav-item" onclick="showSection('storage', this, 'Other Settings')">
                <i class="fas fa-th"></i> Other Settings
            </div>
        </div>

        <div class="settings-container">
            <aside class="side-nav-card">
                <div class="nav-link-custom" onclick="toggleSub('general-submenu', 'arrow-gen')">
                    <span>General Settings</span><i class="fas fa-chevron-down" id="arrow-gen"></i>
                </div>
                <div class="submenu show" id="general-submenu">
                    <div class="submenu-item active" onclick="showSection('profile', this, 'General Settings')">» Profile Settings</div>
                    <div class="submenu-item" onclick="showSection('security', this, 'General Settings')">Security Settings</div>
                </div>

                <div class="nav-link-custom" onclick="toggleSub('other-submenu', 'arrow-oth')">
                    <span>Other Settings</span><i class="fas fa-chevron-down" id="arrow-oth"></i>
                </div>
                <div class="submenu" id="other-submenu">
                    <div class="submenu-item" onclick="showSection('storage', this, 'Other Settings')">Storage</div>
                </div>
            </aside>

            <div id="profile-card" class="content-card active">
                <div class="profile-container">
                    <?php if (!$profile): ?>
                        <div class="alert alert-warning">Profile not found for this user.</div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-lg-4 mb-4">
                                <div class="profile-card mb-4">
                                    <button class="btn-edit-card position-absolute top-0 end-0 mt-3 me-3" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <img src="<?php echo !empty($profile['profile_img']) ? $profile['profile_img'] : "https://ui-avatars.com/api/?name=".urlencode($profile['full_name']); ?>" class="profile-img">
                                    <h5 class="emp-name"><?php echo htmlspecialchars($profile['full_name']); ?></h5>
                                    <p class="emp-designation"><?php echo htmlspecialchars($profile['designation']); ?></p>
                                    <div class="d-flex justify-content-center gap-2 mb-3">
                                        <span class="badge-pill badge-dept"><?php echo htmlspecialchars($profile['department']); ?></span>
                                        <span class="badge-pill badge-exp"><?php echo htmlspecialchars($profile['experience_label']); ?></span>
                                    </div>
                                    <hr class="opacity-25 my-3">
                                    <div class="text-start px-2">
                                        <div class="contact-list-item"><span>ID</span><span class="fw-bold"><?php echo htmlspecialchars($profile['emp_id_code']); ?></span></div>
                                        <div class="contact-list-item"><span>Email</span><span class="fw-bold"><?php echo htmlspecialchars($profile['email']); ?></span></div>
                                        <div class="contact-list-item"><span>Phone</span><span class="fw-bold"><?php echo htmlspecialchars($profile['phone']); ?></span></div>
                                    </div>
                                </div>

                                <div class="section-card">
                                    <div class="card-header-custom">
                                        <h6>Emergency Contacts</h6>
                                        <button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editEmergencyModal"><i class="fas fa-pen"></i></button>
                                    </div>
                                    <div class="card-body-custom pt-0">
                                        <?php if (!empty($emergency_contacts)): foreach($emergency_contacts as $contact): ?>
                                            <div class="contact-list-item">
                                                <div><div class="fw-bold"><?php echo htmlspecialchars($contact['name']); ?></div><small class="text-muted"><?php echo htmlspecialchars($contact['relation']); ?></small></div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($contact['phone']); ?></div>
                                            </div>
                                        <?php endforeach; else: ?><p class="text-muted small mt-2">No contacts added.</p><?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-8">
                                <div class="section-card">
                                    <div class="card-header-custom"><h6>Personal Information</h6><button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fas fa-pen"></i></button></div>
                                    <div class="card-body-custom">
                                        <div class="info-grid">
                                            <div class="info-item"><span class="data-label">Full Name</span><span class="data-value"><?php echo htmlspecialchars($profile['full_name']); ?></span></div>
                                            <div class="info-item"><span class="data-label">DOB</span><span class="data-value"><?php echo htmlspecialchars($profile['dob']); ?></span></div>
                                            <div class="info-item"><span class="data-label">Gender</span><span class="data-value"><?php echo htmlspecialchars($profile['gender']); ?></span></div>
                                            <div class="info-item"><span class="data-label">Marital Status</span><span class="data-value"><?php echo htmlspecialchars($profile['marital_status']); ?></span></div>
                                            <div class="info-item"><span class="data-label">Nationality</span><span class="data-value"><?php echo htmlspecialchars($profile['nationality']); ?></span></div>
                                            <div class="info-item"><span class="data-label">Joined</span><span class="data-value"><?php echo htmlspecialchars($profile['joining_date']); ?></span></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-card">
                                    <div class="card-header-custom"><h6>Family Information</h6><button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editFamilyModal"><i class="fas fa-pen"></i></button></div>
                                    <div class="card-body-custom">
                                        <?php if (!empty($family_info)): foreach($family_info as $fam): ?>
                                            <div class="contact-list-item">
                                                <div><div class="fw-bold"><?php echo htmlspecialchars($fam['name']); ?></div><small><?php echo htmlspecialchars($fam['relation']); ?></small></div>
                                                <div class="text-end"><small><?php echo htmlspecialchars($fam['dob']); ?></small><br><strong><?php echo htmlspecialchars($fam['phone']); ?></strong></div>
                                            </div>
                                        <?php endforeach; else: ?><p class="text-muted small">No family info.</p><?php endif; ?>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="section-card h-100">
                                            <div class="card-header-custom"><h6>Experience</h6><button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editExperienceModal"><i class="fas fa-pen"></i></button></div>
                                            <div class="card-body-custom">
                                                <?php if (!empty($experience_list)): foreach($experience_list as $job): ?>
                                                    <div class="timeline-item">
                                                        <div class="timeline-title"><?php echo htmlspecialchars($job['company']); ?></div>
                                                        <div class="timeline-subtitle"><?php echo htmlspecialchars($job['role']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($job['duration']); ?></small>
                                                    </div>
                                                <?php endforeach; else: ?><p class="text-muted small">No experience.</p><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="section-card h-100">
                                            <div class="card-header-custom"><h6>Education</h6><button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editEducationModal"><i class="fas fa-pen"></i></button></div>
                                            <div class="card-body-custom">
                                                <?php if (!empty($education_list)): foreach($education_list as $edu): ?>
                                                    <div class="timeline-item">
                                                        <div class="timeline-title"><?php echo htmlspecialchars($edu['school']); ?></div>
                                                        <div class="timeline-subtitle"><?php echo htmlspecialchars($edu['degree']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($edu['year']); ?></small>
                                                    </div>
                                                <?php endforeach; else: ?><p class="text-muted small">No education.</p><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="section-card mt-4">
                                    <div class="card-header-custom"><h6>Bank Information</h6><button class="btn-edit-card" data-bs-toggle="modal" data-bs-target="#editBankModal"><i class="fas fa-pen"></i></button></div>
                                    <div class="card-body-custom">
                                        <div class="info-grid">
                                            <div class="info-item"><span class="data-label">Bank</span><span class="data-value"><?php echo htmlspecialchars($bank_info['bank_name'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">Account</span><span class="data-value"><?php echo htmlspecialchars($bank_info['acc_no'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">IFSC</span><span class="data-value"><?php echo htmlspecialchars($bank_info['ifsc'] ?? '-'); ?></span></div>
                                            <div class="info-item"><span class="data-label">PAN</span><span class="data-value"><?php echo htmlspecialchars($bank_info['pan'] ?? '-'); ?></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="security-card" class="content-card" style="padding:30px;">
                <h4 style="margin-bottom:20px;">Security Settings</h4>
                
                <div class="security-section mb-4">
                    <h5 style="font-size:16px; margin-bottom:15px; font-weight:600;">Change Password</h5>
                    <form method="POST">
                        <input type="hidden" name="update_password" value="1">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Current Password</label>
                                <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                            </div>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="submit" class="btn btn-dark btn-sm px-4">Update Password</button>
                        </div>
                    </form>
                </div>

                <hr class="my-4 opacity-25">

                <div class="security-section d-flex justify-content-between align-items-center">
                    <div>
                        <h5 style="font-size:16px; margin-bottom:5px; font-weight:600;">Two-Factor Authentication</h5>
                        <p class="text-muted small m-0">Add an extra layer of security to your account.</p>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckDefault">
                    </div>
                </div>
            </div>

            <div id="storage-card" class="content-card" style="padding:30px;">
                <h4 style="margin-bottom:20px;">Storage Settings</h4>
                
                <div class="storage-overview mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold small text-uppercase text-muted">Storage Usage</span>
                        <span class="text-dark small fw-bold"><?php echo $storage_used; ?> GB used of <?php echo $storage_limit; ?> GB</span>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 5px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $storage_pct; ?>%; background-color: var(--primary-orange);" aria-valuenow="<?php echo $storage_pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="storage-details">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Details breakdown</h6>
                    <ul class="list-group list-group-flush border-0">
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 border-light">
                            <div><i class="fas fa-file-alt text-secondary me-2"></i> Documents</div>
                            <span class="fw-bold text-dark"><?php echo $st_docs; ?> GB</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 border-light">
                            <div><i class="fas fa-image text-info me-2"></i> Images & Media</div>
                            <span class="fw-bold text-dark"><?php echo $st_media; ?> GB</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 border-light">
                            <div><i class="fas fa-database text-warning me-2"></i> System Backups</div>
                            <span class="fw-bold text-dark"><?php echo $st_sys; ?> GB</span>
                        </li>
                    </ul>
                    <div class="mt-4 text-end">
                        <button class="btn btn-outline-danger btn-sm me-2"><i class="fas fa-trash-alt me-1"></i> Clear Cache</button>
                        <button class="btn btn-dark btn-sm"><i class="fas fa-cloud-upload-alt me-1"></i> Upgrade Storage</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Edit Personal Info</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label small">Full Name</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($profile['full_name']); ?>"></div>
                            <div class="col-md-6"><label class="form-label small">Designation</label><input type="text" name="designation" class="form-control" value="<?php echo htmlspecialchars($profile['designation']); ?>"></div>
                            <div class="col-md-6"><label class="form-label small">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone']); ?>"></div>
                            <div class="col-md-6"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>"></div>
                            <div class="col-md-12"><label class="form-label small">Location</label><input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($profile['location']); ?>"></div>
                            <div class="col-md-6"><label class="form-label small">DOB</label><input type="date" name="dob" class="form-control" value="<?php echo $profile['dob']; ?>"></div>
                            <div class="col-md-6"><label class="form-label small">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="Male" <?php echo ($profile['gender']=='Male')?'selected':''; ?>>Male</option>
                                    <option value="Female" <?php echo ($profile['gender']=='Female')?'selected':''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label small">Marital Status</label>
                                <select name="marital_status" class="form-select">
                                    <option value="Single" <?php echo ($profile['marital_status']=='Single')?'selected':''; ?>>Single</option>
                                    <option value="Married" <?php echo ($profile['marital_status']=='Married')?'selected':''; ?>>Married</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label small">Nationality</label><input type="text" name="nationality" class="form-control" value="<?php echo htmlspecialchars($profile['nationality']); ?>"></div>
                        </div>
                        <div class="mt-3 text-end"><button type="submit" class="btn btn-dark">Save Changes</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEmergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Edit Emergency Contacts</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_emergency" value="1">
                        <h6 class="small fw-bold">Primary</h6>
                        <div class="row g-2 mb-3">
                            <div class="col-12"><input type="text" name="em_name_1" class="form-control" placeholder="Name" value="<?php echo $emergency_contacts[0]['name'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="em_rel_1" class="form-control" placeholder="Relation" value="<?php echo $emergency_contacts[0]['relation'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="em_phone_1" class="form-control" placeholder="Phone" value="<?php echo $emergency_contacts[0]['phone'] ?? ''; ?>"></div>
                        </div>
                        <h6 class="small fw-bold">Secondary</h6>
                        <div class="row g-2">
                            <div class="col-12"><input type="text" name="em_name_2" class="form-control" placeholder="Name" value="<?php echo $emergency_contacts[1]['name'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="em_rel_2" class="form-control" placeholder="Relation" value="<?php echo $emergency_contacts[1]['relation'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="em_phone_2" class="form-control" placeholder="Phone" value="<?php echo $emergency_contacts[1]['phone'] ?? ''; ?>"></div>
                        </div>
                        <div class="mt-3 text-end"><button type="submit" class="btn btn-dark">Update</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editFamilyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Edit Family Info</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_family" value="1">
                        <div class="row g-2">
                            <div class="col-6"><input type="text" name="fam_name_1" class="form-control" placeholder="Name" value="<?php echo $family_info[0]['name'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="fam_rel_1" class="form-control" placeholder="Relation" value="<?php echo $family_info[0]['relation'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="date" name="fam_dob_1" class="form-control" value="<?php echo $family_info[0]['dob'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="fam_phone_1" class="form-control" placeholder="Phone" value="<?php echo $family_info[0]['phone'] ?? ''; ?>"></div>
                        </div>
                        <div class="mt-3 text-end"><button type="submit" class="btn btn-dark">Update</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editExperienceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Edit Experience</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_experience" value="1">
                        <h6 class="small fw-bold">Job 1</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><input type="text" name="exp_comp_1" class="form-control" placeholder="Company" value="<?php echo $experience_list[0]['company'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="exp_role_1" class="form-control" placeholder="Role" value="<?php echo $experience_list[0]['role'] ?? ''; ?>"></div>
                            <div class="col-12"><input type="text" name="exp_dur_1" class="form-control" placeholder="Duration" value="<?php echo $experience_list[0]['duration'] ?? ''; ?>"></div>
                        </div>
                        <h6 class="small fw-bold">Job 2</h6>
                        <div class="row g-2">
                            <div class="col-6"><input type="text" name="exp_comp_2" class="form-control" placeholder="Company" value="<?php echo $experience_list[1]['company'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="exp_role_2" class="form-control" placeholder="Role" value="<?php echo $experience_list[1]['role'] ?? ''; ?>"></div>
                            <div class="col-12"><input type="text" name="exp_dur_2" class="form-control" placeholder="Duration" value="<?php echo $experience_list[1]['duration'] ?? ''; ?>"></div>
                        </div>
                        <div class="mt-3 text-end"><button type="submit" class="btn btn-dark">Update</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEducationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Edit Education</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_education" value="1">
                        <h6 class="small fw-bold">School 1</h6>
                        <div class="row g-2 mb-2">
                            <div class="col-12"><input type="text" name="edu_school_1" class="form-control" placeholder="School" value="<?php echo $education_list[0]['school'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="edu_deg_1" class="form-control" placeholder="Degree" value="<?php echo $education_list[0]['degree'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="edu_year_1" class="form-control" placeholder="Year" value="<?php echo $education_list[0]['year'] ?? ''; ?>"></div>
                        </div>
                        <h6 class="small fw-bold">School 2</h6>
                        <div class="row g-2">
                            <div class="col-12"><input type="text" name="edu_school_2" class="form-control" placeholder="School" value="<?php echo $education_list[1]['school'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="edu_deg_2" class="form-control" placeholder="Degree" value="<?php echo $education_list[1]['degree'] ?? ''; ?>"></div>
                            <div class="col-6"><input type="text" name="edu_year_2" class="form-control" placeholder="Year" value="<?php echo $education_list[1]['year'] ?? ''; ?>"></div>
                        </div>
                        <div class="mt-3 text-end"><button type="submit" class="btn btn-dark">Update</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editBankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title fw-bold">Edit Bank Info</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="update_bank" value="1">
                        <div class="mb-2"><label class="form-label small">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?php echo htmlspecialchars($bank_info['bank_name'] ?? ''); ?>"></div>
                        <div class="mb-2"><label class="form-label small">Account No</label><input type="text" name="acc_no" class="form-control" value="<?php echo htmlspecialchars($bank_info['acc_no'] ?? ''); ?>"></div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label small">IFSC</label><input type="text" name="ifsc" class="form-control" value="<?php echo htmlspecialchars($bank_info['ifsc'] ?? ''); ?>"></div>
                            <div class="col-6"><label class="form-label small">PAN</label><input type="text" name="pan" class="form-control" value="<?php echo htmlspecialchars($bank_info['pan'] ?? ''); ?>"></div>
                        </div>
                        <div class="mt-3 text-end"><button type="submit" class="btn btn-dark">Update</button></div>
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

            // Handle Top Nav Active State
            document.querySelectorAll('.top-nav-item').forEach(item => item.classList.remove('active'));
            const topNavs = document.querySelectorAll('.top-nav-item');
            if (categoryName === 'General Settings') topNavs[0].classList.add('active');
            else if (categoryName === 'Other Settings') topNavs[1].classList.add('active');

            // Update Breadcrumb
            let sectionName = element.innerText.replace('» ', '').trim();
            document.getElementById('breadcrumb-text').innerText = 'Settings / ' + categoryName + ' / ' + sectionName;
        }

        function toggleSub(id, arrowId) { 
            document.getElementById(id).classList.toggle("show"); 
            document.getElementById(arrowId).style.transform = document.getElementById(id).classList.contains("show") ? "rotate(180deg)" : "rotate(0deg)";
        }

        // Initialize Arrow Rotation
        window.onload = function() { if(document.getElementById("arrow-gen")) document.getElementById("arrow-gen").style.transform = "rotate(180deg)"; };
    </script>
</body>
</html>