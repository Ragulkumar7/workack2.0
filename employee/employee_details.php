<?php 
// 1. ROBUST DATABASE CONNECTION
// We calculate the path to workack2.0 (one level up from /employee/)
$projectRoot = dirname(__DIR__); 

// According to your path: C:\xampp\htdocs\workack2.0\include\db_connect.php
$dbPath = $projectRoot . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'db_connect.php';

// According to your path: C:\xampp\htdocs\workack2.0\sidebars.php
$sidebarPath = $projectRoot . DIRECTORY_SEPARATOR . 'sidebars.php'; 

// According to your path: C:\xampp\htdocs\workack2.0\header.php
$headerPath  = $projectRoot . DIRECTORY_SEPARATOR . 'header.php';

// Include the Database Connection
if (file_exists($dbPath)) {
    include_once $dbPath;
} else {
    die("<div style='color:red; font-weight:bold; padding:20px; border:2px solid red; background:#fff;'>
        Critical Error: Cannot find database file!<br>
        I looked for it here: " . htmlspecialchars($dbPath) . "<br>
        Please ensure the 'include' folder exists and contains 'db_connect.php'.
    </div>");
}

// 2. INCLUDE SIDEBAR & HEADER
if (file_exists($sidebarPath)) include_once $sidebarPath;
if (file_exists($headerPath))  include_once $headerPath;

// Determine which user ID to fetch
if (isset($_GET['id'])) {
    $view_user_id = intval($_GET['id']);
} elseif (isset($_SESSION['id'])) {
    $view_user_id = $_SESSION['id'];
} else {
    $view_user_id = 1; // Default fallback for testing
}

// --- FETCH SINGLE ROW DATA ---
if (isset($conn)) {
    $sql = "SELECT * FROM employee_profiles WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $view_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
} else {
    die("<div style='color:red; padding:20px;'>Database connection variable '\$conn' is not initialized. Check your db_connect.php file.</div>");
}

// Handle empty profile
if (!$profile) {
    echo "<div class='alert alert-warning m-4' style='margin-left: 110px;'>
            <strong>Profile Not Found!</strong> No data exists in the 'employee_profiles' table for User ID: $view_user_id.
          </div>";
    exit;
}

// --- DECODE JSON DATA INTO ARRAYS ---
$emergency_contacts = !empty($profile['emergency_contacts']) ? json_decode($profile['emergency_contacts'], true) : [];
$family_info        = !empty($profile['family_info'])        ? json_decode($profile['family_info'], true)        : [];
$experience_list    = !empty($profile['experience_history']) ? json_decode($profile['experience_history'], true) : [];
$education_list     = !empty($profile['education_history'])  ? json_decode($profile['education_history'], true)  : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - <?php echo htmlspecialchars($profile['full_name']); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0f172a;
            --text-color: #334155;
            --text-muted: #64748b;
            --bg-color: #f8f9fa;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body { 
            background-color: var(--bg-color); 
            font-family: 'Inter', sans-serif; 
            color: var(--text-color); 
            overflow-x: hidden; 
        }
        
        .page-wrapper {
            transition: margin-left 0.3s ease-in-out;
            margin-left: 95px; 
            padding: 30px;
            min-height: 100vh;
            width: calc(100% - 95px);
        }

        @media (max-width: 991.98px) {
            .page-wrapper { margin-left: 0 !important; width: 100% !important; padding: 15px; }
        }

        .profile-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
            text-align: center;
            padding: 30px 20px;
            height: 100%;
        }

        .profile-img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            display: block;
            border: 4px solid #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            margin-left: auto; 
            margin-right: auto;
        }

        .emp-name { font-weight: 700; font-size: 1.25rem; color: var(--primary-color); margin-bottom: 5px; }
        .emp-designation { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin-bottom: 15px; }
        
        .badge-pill {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-exp { background: #e0f2fe; color: #0284c7; }
        .badge-dept { background: #f1f5f9; color: #475569; }

        .section-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .card-header-custom {
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }
        
        .card-header-custom h6 {
            margin: 0;
            font-weight: 700;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.85rem;
        }

        .card-body-custom { padding: 24px; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .data-label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .data-value {
            font-size: 0.95rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        .timeline-item {
            position: relative;
            padding-left: 20px;
            margin-bottom: 20px;
            border-left: 2px solid #e2e8f0;
        }
        .timeline-item:last-child { margin-bottom: 0; }
        
        .timeline-title { font-weight: 600; color: var(--primary-color); font-size: 1rem; }
        .timeline-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 4px; }
        .timeline-date { 
            font-size: 0.8rem; 
            color: #fff; 
            background: #64748b; 
            padding: 2px 8px; 
            border-radius: 4px; 
            display: inline-block; 
        }

        .contact-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .contact-list-item:last-child { border-bottom: none; }
        .contact-icon { width: 30px; color: #94a3b8; text-align: center; }

    </style>
</head>
<body>

<div class="page-wrapper" id="mainContent">
    <div class="container-fluid">
        
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="fw-bold text-dark m-0">Employee Profile</h4>
                <p class="text-muted small m-0">View employee details and history</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="profile-card mb-4">
                    <?php 
                        $img_src = !empty($profile['profile_img']) ? $profile['profile_img'] : "https://ui-avatars.com/api/?name=".urlencode($profile['full_name'])."&background=random&size=128";
                    ?>
                    <img src="<?php echo $img_src; ?>" class="profile-img" alt="<?php echo htmlspecialchars($profile['full_name']); ?>">
                    
                    <h5 class="emp-name"><?php echo htmlspecialchars($profile['full_name']); ?> <i class="fas fa-check-circle text-success fs-6" title="Verified"></i></h5>
                    <p class="emp-designation"><?php echo htmlspecialchars($profile['designation']); ?></p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span class="badge-pill badge-dept"><?php echo htmlspecialchars($profile['department']); ?></span>
                        <span class="badge-pill badge-exp"><?php echo htmlspecialchars($profile['experience_label']); ?></span>
                    </div>

                    <hr class="my-4" style="opacity: 0.1;">

                    <div class="text-start px-2">
                        <div class="contact-list-item">
                            <div><i class="fas fa-id-badge contact-icon"></i> <span class="text-muted small">Employee ID</span></div>
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($profile['emp_id_code']); ?></span>
                        </div>
                        <div class="contact-list-item">
                            <div><i class="fas fa-envelope contact-icon"></i> <span class="text-muted small">Email</span></div>
                            <span class="fw-bold text-dark text-break"><?php echo htmlspecialchars($profile['email']); ?></span>
                        </div>
                        <div class="contact-list-item">
                            <div><i class="fas fa-phone-alt contact-icon"></i> <span class="text-muted small">Phone</span></div>
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($profile['phone']); ?></span>
                        </div>
                        <div class="contact-list-item">
                            <div><i class="fas fa-map-marker-alt contact-icon"></i> <span class="text-muted small">Location</span></div>
                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($profile['location']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="card-header-custom">
                        <h6>Emergency Contacts</h6>
                    </div>
                    <div class="card-body-custom pt-0">
                        <?php if (!empty($emergency_contacts)): ?>
                            <?php foreach($emergency_contacts as $contact): ?>
                                <div class="contact-list-item">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($contact['name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($contact['relation']); ?></div>
                                    </div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($contact['phone']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted small mt-3">No emergency contacts added.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                
                <div class="section-card">
                    <div class="card-header-custom">
                        <h6>Personal Information</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="data-label">Full Name</span>
                                <span class="data-value"><?php echo htmlspecialchars($profile['full_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Date of Birth</span>
                                <span class="data-value">
                                    <?php echo ($profile['dob']) ? date('jS F Y', strtotime($profile['dob'])) : 'N/A'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Gender</span>
                                <span class="data-value"><?php echo htmlspecialchars($profile['gender']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Marital Status</span>
                                <span class="data-value"><?php echo htmlspecialchars($profile['marital_status']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Nationality</span>
                                <span class="data-value"><?php echo htmlspecialchars($profile['nationality']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Joining Date</span>
                                <span class="data-value">
                                    <?php echo ($profile['joining_date']) ? date('jS M Y', strtotime($profile['joining_date'])) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="card-header-custom">
                        <h6>Family Information</h6>
                    </div>
                    <div class="card-body-custom">
                        <div class="table-responsive">
                            <table class="table table-borderless mb-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="text-muted small text-uppercase">Name</th>
                                        <th class="text-muted small text-uppercase">Relationship</th>
                                        <th class="text-muted small text-uppercase">Date of Birth</th>
                                        <th class="text-muted small text-uppercase text-end">Phone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($family_info)): ?>
                                        <?php foreach($family_info as $fam): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($fam['name']); ?></td>
                                                <td><?php echo htmlspecialchars($fam['relation']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($fam['dob'])); ?></td>
                                                <td class="text-end"><?php echo htmlspecialchars($fam['phone']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted small">No family details available.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="section-card h-100">
                            <div class="card-header-custom">
                                <h6>Experience</h6>
                            </div>
                            <div class="card-body-custom">
                                <?php if (!empty($experience_list)): ?>
                                    <?php foreach($experience_list as $job): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-title"><?php echo htmlspecialchars($job['company']); ?></div>
                                            <div class="timeline-subtitle"><?php echo htmlspecialchars($job['role']); ?></div>
                                            <div class="timeline-date"><?php echo htmlspecialchars($job['duration']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small">No experience records found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="section-card h-100">
                            <div class="card-header-custom">
                                <h6>Education</h6>
                            </div>
                            <div class="card-body-custom">
                                <?php if (!empty($education_list)): ?>
                                    <?php foreach($education_list as $edu): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-title"><?php echo htmlspecialchars($edu['school']); ?></div>
                                            <div class="timeline-subtitle"><?php echo htmlspecialchars($edu['degree']); ?></div>
                                            <div class="timeline-date"><?php echo htmlspecialchars($edu['year']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small">No education records found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>