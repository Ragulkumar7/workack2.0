<?php 
include '../sidebars.php';
/**
 * TL PROJECT PAGE - UI ONLY
 * Added "View" Modal functionality for Currently Working Projects.
 */

// 1. Dummy Data for "New Assigned Projects" (Grid View)
$new_projects = [
    [
        "project_name" => "Office Management",
        "description" => "An office management app project streamlines administrative tasks...",
        "leader" => "Michael Walker",
        "deadline" => "12 Sep 2024",
        "tasks" => "6/10",
        "team" => ["A", "B"],
        "extra_team" => "+1"
    ],
    [
        "project_name" => "Clinic Management",
        "description" => "A clinic management project streamlines patient records...",
        "leader" => "Brian Villalobos",
        "deadline" => "24 Oct 2024",
        "tasks" => "7/10",
        "team" => ["C"],
        "extra_team" => "+3"
    ],
    [
        "project_name" => "Educational Platform",
        "description" => "An educational platform project provides a centralized space...",
        "leader" => "Harvey Smith",
        "deadline" => "18 Feb 2024",
        "tasks" => "5/10",
        "team" => ["D"],
        "extra_team" => "+1"
    ],
    [
        "project_name" => "Chat & Call Mobile App",
        "description" => "A chat and call mobile app enables users to send messages...",
        "leader" => "Stephan Peralt",
        "deadline" => "17 Oct 2024",
        "tasks" => "6/10",
        "team" => ["E", "F"],
        "extra_team" => "+1"
    ]
];

// 2. Dummy Data for "Working Projects" (List View)
// Added 'id' and 'team_members' for the View details functionality
$working_projects = [
    [
        "id" => "1",
        "name" => "Travel Planning Website", 
        "deadline" => "20 Jul 2024", 
        "progress" => "80%",
        "client" => "Global Travels Co.",
        "description" => "Developing a comprehensive booking and itinerary management system for international tourists.",
        "team_members" => [
            ["name" => "Alice Johnson", "role" => "Frontend Developer"],
            ["name" => "Bob Smith", "role" => "Backend Developer"],
            ["name" => "Charlie Day", "role" => "UI Designer"]
        ]
    ],
    [
        "id" => "2",
        "name" => "Service Booking Software", 
        "deadline" => "10 Apr 2024", 
        "progress" => "90%",
        "client" => "FixIt Solutions",
        "description" => "A SaaS platform for local service providers to manage appointments and billing.",
        "team_members" => [
            ["name" => "David Miller", "role" => "Lead Dev"],
            ["name" => "Eve Ross", "role" => "QA Engineer"]
        ]
    ]
];

// 3. Dummy Data for "History"
$history_projects = [
    ["name" => "Hotel Booking App", "date" => "Jan 2024"],
    ["name" => "Car & Bike Rental Software", "date" => "Dec 2023"]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TL Project Page | HRMS UI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; color: #333; }
        
        /* --- Sidebar Integration Fix --- */
        #mainContent {
            margin-left: 95px; /* Matches Primary Sidebar Width */
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Logic handled by sidebars.php JS to push content when sub-menu opens */
        #mainContent.main-shifted { margin-left: 315px; }

        /* Top Header Navigation */
        .breadcrumb-section { 
            background: #fff; 
            padding: 20px 30px; 
            margin-bottom: 30px; 
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        /* Section Headings */
        .section-title { 
            margin: 40px 0 20px; 
            font-weight: 700; 
            color: #444; 
            border-left: 5px solid #ff9b44; 
            padding-left: 15px;
            font-size: 20px;
        }

        /* Project Card (Matches your screenshot) */
        .project-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #ededed;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        
        .project-title { font-size: 17px; font-weight: 600; color: #222; margin-bottom: 8px; }
        .project-desc { font-size: 13px; color: #888; line-height: 1.6; margin-bottom: 20px; height: 40px; overflow: hidden; }

        /* Avatars & Details */
        .avatar-circle {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: bold; font-size: 11px; margin-right: 10px;
        }
        .deadline-label { font-size: 11px; color: #bbb; text-transform: uppercase; letter-spacing: 0.5px; }
        .deadline-date { font-size: 13px; font-weight: 600; color: #333; }

        /* Task & Team Icons */
        .task-info { font-size: 13px; font-weight: 500; color: #666; }
        .team-overlap { display: flex; align-items: center; flex-direction: row-reverse; }
        .member-dot {
            width: 26px; height: 26px; border-radius: 50%;
            border: 2px solid #fff; margin-left: -10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; color: #fff; font-weight: bold;
        }
        .orange-badge { background: #ff6b3d; }
    </style>
</head>
<body>

<main id="mainContent">

    <div class="breadcrumb-section d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-0 fw-bold">Projects</h4>
            <small class="text-muted">Projects > <span class="text-dark">TL Project Page</span></small>
        </div>
        
    </div>

    <div class="container-fluid px-5">

        <h3 class="section-title">New Assigned Projects</h3>
        <div class="row g-4">
            <?php foreach($new_projects as $project) { ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="project-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="project-title"><?php echo $project['project_name']; ?></h5>
                        <i class="fa fa-ellipsis-v text-muted" style="cursor:pointer"></i>
                    </div>
                    <p class="project-desc"><?php echo $project['description']; ?></p>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar-circle bg-success"><?php echo substr($project['leader'], 0, 1); ?></div>
                            <div>
                                <div class="small fw-bold"><?php echo $project['leader']; ?></div>
                                <div class="text-muted" style="font-size: 10px;">Project Leader</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="deadline-label">Deadline</div>
                            <div class="deadline-date"><?php echo $project['deadline']; ?></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <div class="task-info">
                            <i class="fa fa-check-square text-success"></i> Tasks: <?php echo $project['tasks']; ?>
                        </div>
                        <div class="team-overlap">
                            <div class="member-dot orange-badge"><?php echo $project['extra_team']; ?></div>
                            <?php foreach($project['team'] as $initial) { ?>
                                <div class="member-dot bg-primary"><?php echo $initial; ?></div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <h3 class="section-title">Projects Currently Working On</h3>
        <div class="card border-0 shadow-sm mb-5">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Project Name</th>
                            <th>Deadline</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($working_projects as $wp) { ?>
                        <tr>
                            <td class="ps-4"><strong><?php echo $wp['name']; ?></strong></td>
                            <td><?php echo $wp['deadline']; ?></td>
                            <td>
                                <div class="progress" style="height: 6px; width: 150px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $wp['progress']; ?>"></div>
                                </div>
                            </td>
                            <td><span class="badge bg-soft-primary text-primary border border-primary">In Progress</span></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#projectModal<?php echo $wp['id']; ?>">View</button>
                            </td>
                        </tr>

                        <div class="modal fade" id="projectModal<?php echo $wp['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content border-0 shadow">
                                    <div class="modal-header bg-light">
                                        <h5 class="modal-title fw-bold text-dark"><?php echo $wp['name']; ?> - Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <p class="mb-1 text-muted small text-uppercase fw-bold">Client Name</p>
                                                <p class="fw-bold"><?php echo $wp['client']; ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1 text-muted small text-uppercase fw-bold">Final Deadline</p>
                                                <p class="text-danger fw-bold"><i class="far fa-calendar-alt me-1"></i> <?php echo $wp['deadline']; ?></p>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <p class="mb-1 text-muted small text-uppercase fw-bold">Project Description</p>
                                            <p><?php echo $wp['description']; ?></p>
                                        </div>
                                        
                                        <hr>
                                        
                                        <h6 class="fw-bold mb-3"><i class="fa fa-users text-warning me-2"></i> Assigned Team Members</h6>
                                        <div class="list-group list-group-flush">
                                            <?php foreach($wp['team_members'] as $member) { ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-primary me-2"><?php echo substr($member['name'], 0, 1); ?></div>
                                                    <div>
                                                        <p class="mb-0 fw-bold"><?php echo $member['name']; ?></p>
                                                        <small class="text-muted"><?php echo $member['role']; ?></small>
                                                    </div>
                                                </div>
                                                <span class="badge bg-light text-dark border">Active</span>
                                            </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <h3 class="section-title">Project History</h3>
        <div class="row g-3 pb-5">
            <?php foreach($history_projects as $hp) { ?>
            <div class="col-md-4">
                <div class="card p-3 border-0 shadow-sm d-flex flex-row align-items-center">
                    <div class="rounded-circle bg-light p-3 me-3">
                        <i class="fa fa-archive text-secondary"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo $hp['name']; ?></div>
                        <small class="text-muted">Completed: <?php echo $hp['date']; ?></small>
                    </div>
                    <div class="ms-auto">
                        <span class="badge bg-success">Done</span>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

    </div>

</main> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>