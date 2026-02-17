<?php 
// Ensure these paths are correct for your file structure
$sidebarPath = '../sidebars.php'; 
$headerPath = '../header.php';

if (file_exists($sidebarPath)) include $sidebarPath;
if (file_exists($headerPath)) include $headerPath;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - Stephan Peralt</title>
    
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
        
        /* Layout Adjustment for Sidebar */
        .page-wrapper {
            transition: margin-left 0.3s ease-in-out;
            margin-left: 95px; /* Matches your sidebar width */
            padding: 30px;
            min-height: 100vh;
            width: calc(100% - 95px);
        }

        @media (max-width: 991.98px) {
            .page-wrapper { margin-left: 0 !important; width: 100% !important; padding: 15px; }
        }

        /* Profile Card */
        .profile-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: var(--card-shadow);
            text-align: center;
            padding: 30px 20px;
            height: 100%;
        }

        .profile-img-container { 
    text-align: center; 
    margin-top: 30px;
    display: flex;         /* Add this */
    justify-content: center; /* Add this */
    align-items: center;     /* Add this */
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
            margin-left: 80px;
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

        /* Section Cards */
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

        /* Information Rows */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item { margin-bottom: 5px; }
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

        /* Timeline Items (Education/Experience) */
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

        /* Contact List */
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
                    <img src="https://ui-avatars.com/api/?name=Stephan+Peralt&background=random&size=128" class="profile-img" alt="Stephan Peralt">
                    <h5 class="emp-name">Stephan Peralt <i class="fas fa-check-circle text-success fs-6" title="Verified"></i></h5>
                    <p class="emp-designation">Senior Software Developer</p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span class="badge-pill badge-dept">Engineering Dept</span>
                        <span class="badge-pill badge-exp">10+ Years Exp</span>
                    </div>

                    <hr class="my-4" style="opacity: 0.1;">

                    <div class="text-start px-2">
                        <div class="contact-list-item">
                            <div><i class="fas fa-id-badge contact-icon"></i> <span class="text-muted small">Employee ID</span></div>
                            <span class="fw-bold text-dark">EMP-0054</span>
                        </div>
                        <div class="contact-list-item">
                            <div><i class="fas fa-envelope contact-icon"></i> <span class="text-muted small">Email</span></div>
                            <span class="fw-bold text-dark text-break">perralt12@example.com</span>
                        </div>
                        <div class="contact-list-item">
                            <div><i class="fas fa-phone-alt contact-icon"></i> <span class="text-muted small">Phone</span></div>
                            <span class="fw-bold text-dark">(163) 2459 315</span>
                        </div>
                        <div class="contact-list-item">
                            <div><i class="fas fa-map-marker-alt contact-icon"></i> <span class="text-muted small">Location</span></div>
                            <span class="fw-bold text-dark">New York, USA</span>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="card-header-custom">
                        <h6>Emergency Contacts</h6>
                    </div>
                    <div class="card-body-custom pt-0">
                        <div class="contact-list-item">
                            <div>
                                <div class="fw-bold text-dark">Adrian Peralt</div>
                                <div class="small text-muted">Father (Primary)</div>
                            </div>
                            <div class="fw-bold text-dark">+1 127 2685 598</div>
                        </div>
                        <div class="contact-list-item">
                            <div>
                                <div class="fw-bold text-dark">Karen Wills</div>
                                <div class="small text-muted">Mother (Secondary)</div>
                            </div>
                            <div class="fw-bold text-dark">+1 989 7774 787</div>
                        </div>
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
                                <span class="data-value">Stephan Peralt</span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Date of Birth</span>
                                <span class="data-value">24th July 2000</span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Gender</span>
                                <span class="data-value">Male</span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Marital Status</span>
                                <span class="data-value">Single</span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Nationality</span>
                                <span class="data-value">American</span>
                            </div>
                            <div class="info-item">
                                <span class="data-label">Joining Date</span>
                                <span class="data-value">12th Jan 2013</span>
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
                                    <tr>
                                        <td class="fw-bold">Hendry Peralt</td>
                                        <td>Brother</td>
                                        <td>25 May 2014</td>
                                        <td class="text-end">+1 265 6956 961</td>
                                    </tr>
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
                                <div class="timeline-item">
                                    <div class="timeline-title">Google</div>
                                    <div class="timeline-subtitle">Senior UI/UX Developer</div>
                                    <div class="timeline-date">Jan 2013 - Present</div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-title">Salesforce</div>
                                    <div class="timeline-subtitle">Web Developer</div>
                                    <div class="timeline-date">Dec 2012 - Jan 2013</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="section-card h-100">
                            <div class="card-header-custom">
                                <h6>Education</h6>
                            </div>
                            <div class="card-body-custom">
                                <div class="timeline-item">
                                    <div class="timeline-title">Oxford University</div>
                                    <div class="timeline-subtitle">M.Sc. Computer Science</div>
                                    <div class="timeline-date">2020 - 2022</div>
                                </div>
                                <div class="timeline-item">
                                    <div class="timeline-title">Cambridge University</div>
                                    <div class="timeline-subtitle">B.E. Computer Networks</div>
                                    <div class="timeline-date">2016 - 2019</div>
                                </div>
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