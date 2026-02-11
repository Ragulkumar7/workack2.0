<?php 
include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - Stephan Peralt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; color: #334155; overflow-x: hidden; }
        
        /* FIX: The .page-wrapper now acts as the 'mainContent' target.
           The transition ensures the "Push" effect from your video is smooth.
        */
        .page-wrapper {
            transition: margin-left 0.3s ease-in-out;
            margin-left: 95px; /* Matches Primary Sidebar width */
            padding: 20px;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        /* This class is toggled by your sidebars.php script. 
           It adds the secondary sidebar width (220px) to the base margin (95px).
        */
        .main-shifted { 
            margin-left: 315px !important; 
        }

        @media (max-width: 991.98px) {
            .page-wrapper { margin-left: 0 !important; }
            .main-shifted { margin-left: 0 !important; }
        }

        /* Profile & Info Cards */
        .profile-card, .info-card { border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; background: #fff; }
        .profile-img-container { text-align: center; margin-top: 20px; } 
        .profile-img { width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; background: #eee; object-fit: cover; }
        
        .badge-dev { background: #e2e8f0; color: #475569; border-radius: 5px; font-size: 0.85rem; padding: 4px 12px; font-weight: 500;}
        .badge-exp { background: #e0f2fe; color: #0369a1; border-radius: 5px; font-size: 0.85rem; padding: 4px 12px; font-weight: 500;}
        
        .card-title-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .card-title-flex h6 { margin: 0; font-weight: 700; color: #1e293b; font-size: 1rem; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 0.9rem; align-items: center; }
        .info-label { color: #64748b; display: flex; align-items: center; gap: 8px; }
        .info-value { font-weight: 500; text-align: right; color: #1e293b; }
        
        /* Emergency Contacts */
        .emergency-row { padding: 15px 0; border-top: 1px solid #f1f5f9; }
        .emergency-type { font-size: 0.85rem; color: #94a3b8; margin-bottom: 4px; }
        .emergency-name { font-weight: 700; color: #1e293b; font-size: 0.95rem; }
        .emergency-dot { color: #ef4444; margin: 0 5px; font-size: 1.2rem; line-height: 0; vertical-align: middle; }
        .emergency-relation { font-size: 0.9rem; color: #334155; font-weight: 500; }
        .emergency-phone { font-weight: 600; color: #1e293b; font-size: 0.95rem; text-align: right; }

        /* Detail Cards */
        .detail-card { border: none; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; background: #fff; }
        .card-header-custom { background: #fff; border: none; display: flex; justify-content: space-between; align-items: center; padding: 20px; cursor: pointer; text-decoration: none !important; color: inherit; }
        .card-header-custom h5 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #1e293b; }
        .card-header-custom .fa-chevron-down { transition: transform 0.3s ease; }
        .card-header-custom[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }

        .data-label { display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 2px; }
        .data-value { display: block; color: #1e293b; font-weight: 600; font-size: 0.95rem; }

        /* Items */
        .item-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; width: 100%; }
        .item-main-text { color: #1e293b; font-weight: 700; font-size: 1.1rem; margin-bottom: 2px; }
        .item-sub-text { color: #64748b; font-size: 0.9rem; margin-bottom: 4px; }
        .item-date { color: #334155; font-size: 0.95rem; font-weight: 500; text-align: right; min-width: 120px; }
        .role-badge { background: #f1f5f9; color: #475569; border-radius: 6px; font-size: 0.85rem; padding: 4px 12px; display: inline-flex; align-items: center; margin-top: 5px; font-weight: 500; border: 1px solid #e2e8f0; }
        .role-badge::before { content: "•"; margin-right: 6px; color: #006064; font-size: 1.2rem; }

        /* Teal Buttons */
        .btn-edit, .btn-message, .btn-save, .btn-bank-stat, .btn-upload { background-color: #006064; color: white; border: none; border-radius: 8px; font-weight: 500; transition: background 0.2s; }
        .btn-edit:hover, .btn-message:hover, .btn-save:hover, .btn-bank-stat:hover { background-color: #004d40; }
        .btn-edit, .btn-message { padding: 10px 18px; }
        .btn-bank-stat { padding: 10px 20px; font-size: 0.95rem; font-weight: 600; }
        .btn-save { padding: 10px 24px; font-weight: 600; }
        .btn-collapse { background: white; border: 1px solid #e2e8f0; border-radius: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: #64748b; }
        
        /* Modals */
        .upload-section { background-color: #f8fafc; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 20px; margin-bottom: 20px; }
        .upload-circle { width: 80px; height: 80px; border-radius: 50%; background: #eee; }
        .stat-section-title { font-weight: 700; color: #1e293b; margin: 25px 0 15px 0; font-size: 1.1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }
    </style>
</head>
<body>

<div class="page-wrapper" id="mainContent">
    <div class="container-fluid">
        <div class="row mb-4 align-items-center">
            <div class="col-6">
                <h5 class="fw-bold mb-0 text-dark">Employee Details</h5>
            </div>
            <div class="col-6 text-end d-flex justify-content-end gap-2">
                <button class="btn-bank-stat" data-bs-toggle="modal" data-bs-target="#bankStatutoryModal">
                    <i class="far fa-plus-square me-2"></i> Bank & Statutory
                </button>
                <button class="btn-collapse"><i class="fas fa-angles-up"></i></button>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4">
                <div class="profile-card p-0">
                    <div class="profile-img-container">
                        <img src="https://via.placeholder.com/100" class="profile-img" alt="Stephan Peralt">
                    </div>
                    <div class="card-body text-center mt-2">
                        <h4 class="fw-bold mb-1">Stephan Peralt <i class="fas fa-check-circle text-success" style="font-size: 1rem;"></i></h4>
                        <div class="d-flex justify-content-center gap-2 mb-4">
                            <span class="badge-dev">• Software Developer</span>
                            <span class="badge-exp">10+ years of Experience</span>
                        </div>
                        <div class="d-flex gap-2 justify-content-center pb-3">
                            <button class="btn-edit" data-bs-toggle="modal" data-bs-target="#editEmployeeModal"><i class="far fa-edit"></i> Edit Info</button>
                            <button class="btn-message"><i class="far fa-comment-dots"></i> Message</button>
                        </div>
                    </div>
                </div>
                
                <div class="info-card p-4">
                    <div class="card-title-flex"><h6>Basic information</h6><i class="far fa-edit text-muted" style="cursor:pointer; font-size: 0.8rem;" data-bs-toggle="modal" data-bs-target="#editEmployeeModal"></i></div>
                    <div class="info-row"><span class="info-label"><i class="fas fa-phone-alt"></i> Phone</span><span class="info-value">(163) 2459 315</span></div>
                    <div class="info-row"><span class="info-label"><i class="far fa-envelope"></i> Email</span><span class="info-value text-primary">perralt12@example.com</span></div>
                    <div class="info-row"><span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span><span class="info-value">Male</span></div>
                    <div class="info-row"><span class="info-label"><i class="far fa-calendar-alt"></i> Birthday</span><span class="info-value">24th July 2000</span></div>
                </div>

                <div class="info-card p-4">
                    <div class="card-title-flex"><h6>Emergency Contact Number</h6><i class="far fa-edit text-muted" style="cursor:pointer; font-size: 0.8rem;"></i></div>
                    <div class="emergency-row border-0 pt-0">
                        <div class="emergency-type">Primary</div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div><span class="emergency-name">Adrian Peralt</span><span class="emergency-dot">●</span><span class="emergency-relation">Father</span></div>
                            <div class="emergency-phone">+1 127 2685 598</div>
                        </div>
                    </div>
                    <div class="emergency-row">
                        <div class="emergency-type">Secondary</div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div><span class="emergency-name">Karen Wills</span><span class="emergency-dot">●</span><span class="emergency-relation">Mother</span></div>
                            <div class="emergency-phone">+1 989 7774 787</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <?php
                    $sections = [
                        "About Employee" => '<p class="m-0 text-secondary" style="line-height: 1.6;">As an award winning designer, I deliver exceptional quality work and bring value to your brand! With 10 years of experience and 350+ projects completed worldwide with satisfied customers, I developed the 360° brand approach, which helped me to create numerous brands that are relevant, meaningful and loved.</p>',
                        "Bank Information" => '<div class="row g-3">
                            <div class="col-md-3 col-6"><span class="data-label">Bank Name</span><span class="data-value">Swiz International Bank</span></div>
                            <div class="col-md-3 col-6"><span class="data-label">Bank account no</span><span class="data-value">159843014641</span></div>
                            <div class="col-md-3 col-6"><span class="data-label">IFSC Code</span><span class="data-value">ICI24504</span></div>
                            <div class="col-md-3 col-6"><span class="data-label">Branch</span><span class="data-value">Alabama USA</span></div>
                        </div>',
                        "Family Information" => '<div class="row g-3">
                            <div class="col-md-3 col-6"><span class="data-label">Name</span><span class="data-value">Hendry Peralt</span></div>
                            <div class="col-md-3 col-6"><span class="data-label">Relationship</span><span class="data-value">Brother</span></div>
                            <div class="col-md-3 col-6"><span class="data-label">Date of birth</span><span class="data-value">25 May 2014</span></div>
                            <div class="col-md-3 col-6"><span class="data-label">Phone</span><span class="data-value">+1 265 6956 961</span></div>
                        </div>',
                        "Education Details" => '<div class="item-row"><div><div class="item-sub-text">Oxford University</div><div class="item-main-text">Computer Science</div></div><div class="item-date">2020 - 2022</div></div><div class="item-row"><div><div class="item-sub-text">Cambridge University</div><div class="item-main-text">Computer Network & Systems</div></div><div class="item-date">2016 - 2019</div></div>',
                        "Experience" => '<div class="item-row"><div><div class="item-main-text">Google</div><div class="role-badge">UI/UX Developer</div></div><div class="item-date">Jan 2013 - Present</div></div><div class="item-row"><div><div class="item-main-text">Salesforce</div><div class="role-badge">Web Developer</div></div><div class="item-date">Dec 2012 - Jan 2015</div></div>'
                    ];
                    $count = 0;
                    foreach($sections as $title => $html) {
                        $collapseId = "collapseMain" . $count;
                        $targetModal = "#editEmployeeModal";
                        if ($title == "Bank Information") { $targetModal = "#bankStatutoryModal"; }
                        else if ($title == "Family Information") { $targetModal = "#familyDetailsModal"; }
                        else if ($title == "Education Details") { $targetModal = "#educationDetailsModal"; }
                        else if ($title == "Experience") { $targetModal = "#experienceDetailsModal"; }
                        
                        echo '<div class="detail-card">
                            <a class="card-header-custom" data-bs-toggle="collapse" href="#' . $collapseId . '" role="button" aria-expanded="true">
                                <h5>' . $title . '</h5>
                                <div class="text-muted d-flex align-items-center">
                                    <i class="far fa-edit me-3" style="font-size: 0.9rem;" data-bs-toggle="modal" data-bs-target="' . $targetModal . '"></i>
                                    <i class="fas fa-chevron-down" style="font-size: 0.9rem;"></i>
                                </div>
                            </a>
                            <div id="' . $collapseId . '" class="collapse show">
                                <div class="card-body px-4 pb-4 pt-0">' . $html . '</div>
                            </div>
                        </div>';
                        $count++;
                    }
                ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editEmployeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header pt-4 px-4 d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold">Edit Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pb-4">
                <div class="upload-section">
                    <img src="https://via.placeholder.com/80" class="upload-circle">
                    <div>
                        <h6 class="fw-bold mb-1">Upload Profile Image</h6>
                        <button class="btn-upload px-3 py-1">Upload</button>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="small fw-600">First Name *</label><input type="text" class="form-control bg-light border-0" value="Stephan"></div>
                    <div class="col-md-6"><label class="small fw-600">Last Name</label><input type="text" class="form-control bg-light border-0" value="Peralt"></div>
                    <div class="col-12"><label class="small fw-600">About Employee *</label><textarea class="form-control bg-light border-0" rows="3"></textarea></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light px-4 border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-save">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bankStatutoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header pt-4 px-4"><h5 class="modal-title fw-bold">Bank & Statutory</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body px-4 pb-4">
                <div class="stat-section-title">Basic Salary Information</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4"><label class="form-label small">Salary basis *</label><select class="form-select bg-light border-0"><option>Select</option></select></div>
                    <div class="col-md-4"><label class="form-label small">Amount</label><input type="text" class="form-control bg-light border-0"></div>
                    <div class="col-md-4"><label class="form-label small">Payment type</label><select class="form-select bg-light border-0"><option>Select</option></select></div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0"><button class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn-save">Save</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>