<?php 
include '../sidebars.php'; 
include '../header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Overview</title>
    
    <!-- Scripts & Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { 
            background-color: #f1f5f9; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b;
        }

        /* --- CRITICAL LAYOUT FIX FOR SIDEBAR --- */
        #mainContent {
            margin-left: 95px; /* Primary Sidebar Width */
            width: calc(100% - 95px);
            transition: margin-left 0.3s ease, width 0.3s ease;
            padding: 24px;
        }
        /* When Secondary Sidebar Opens */
        #mainContent.main-shifted {
            margin-left: 315px; /* 95px + 220px */
            width: calc(100% - 315px);
        }

        /* Theme Colors */
        .bg-teal-custom { background-color: #144d4d; }
        .text-teal-custom { color: #144d4d; }
        .border-teal-custom { border-color: #144d4d; }

        /* Card Hover Effects */
        .team-card {
            transition: all 0.3s ease;
        }
        .team-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* Dropdown Logic */
        .dropdown-trigger:hover .dropdown-menu { display: block; }
        .dropdown-menu { display: none; z-index: 50; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

    <main id="mainContent">
        
        <!-- Header Section -->
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Manager Overview</h1>
                <p class="text-sm text-gray-500 mt-1">Manage and view team distributions across departments.</p>
            </div>
            <div class="flex gap-3">
                <button class="bg-white border border-gray-200 px-5 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 shadow-sm hover:shadow-md transition">
                    <i class="fa-solid fa-filter text-gray-400"></i> Filter
                </button>
                <button class="bg-teal-custom text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 shadow-lg hover:opacity-90 transition">
                    <i class="fa-solid fa-plus"></i> Add Team
                </button>
            </div>
        </div>

        <?php
        // Sample Data Structure
        $departments = [
            "Development Team" => [
                "Lead" => "Alexander Wright",
                "Role" => "Senior Manager",
                "Members" => ["Sarah Chen", "Marcus Vane", "Elena Rodriguez", "David Kim"]
            ],
            "Design & Creative" => [
                "Lead" => "Sophia Bennett",
                "Role" => "Creative Director",
                "Members" => ["Liam O'Shea", "Emma Wilson", "Noah Garcia"]
            ],
            "Marketing & Growth" => [
                "Lead" => "Julian Thorne",
                "Role" => "Marketing Lead",
                "Members" => ["Olivia Pope", "Riley Scott", "Lucas Meyer", "Isabella Ross"]
            ]
        ];
        ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($departments as $teamName => $data): ?>
                <div class="team-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover-card">
                    
                    <!-- Card Header -->
                    <div class="bg-teal-custom p-6 flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center text-white text-xl">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg leading-tight">
                                <?php echo $teamName; ?>
                            </h3>
                            <p class="text-teal-100 text-xs mt-1">
                                <?php echo count($data['Members']); ?> Members
                            </p>
                        </div>
                    </div>

                    <div class="p-6">
                        <!-- Team Lead Section -->
                        <div class="mb-6">
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">Team Lead</label>
                            <div class="flex items-center mt-3 p-4 bg-slate-50 rounded-xl border border-gray-100">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($data['Lead']); ?>&background=144d4d&color=fff" class="w-11 h-11 rounded-full border-2 border-white shadow">
                                <div class="ml-3">
                                    <p class="font-bold text-slate-800 text-sm"><?php echo $data['Lead']; ?></p>
                                    <p class="text-xs text-teal-custom font-medium"><?php echo $data['Role']; ?></p>
                                </div>
                                <button class="ml-auto text-gray-400 hover:text-teal-custom transition">
                                    <i class="fa-solid fa-message"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Team Members Section -->
                        <div>
                            <label class="text-xs font-bold text-gray-400 uppercase tracking-wider">Team Members</label>
                            <div class="mt-3 space-y-2 max-h-64 overflow-y-auto custom-scrollbar pr-1">
                                <?php foreach ($data['Members'] as $member): ?>
                                    <div class="member-chip flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 transition-colors relative dropdown-trigger">
                                        <div class="flex items-center">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($member); ?>&background=random" class="w-8 h-8 rounded-full">
                                            <span class="text-slate-700 text-sm font-medium ml-3"><?php echo $member; ?></span>
                                        </div>
                                        
                                        <div class="relative">
                                            <button class="text-gray-300 hover:text-teal-custom px-2 py-1 rounded hover:bg-slate-100 transition">
                                                <i class="fas fa-ellipsis-vertical text-xs"></i>
                                            </button>
                                            
                                            <!-- Dropdown Menu -->
                                            <div class="dropdown-menu absolute right-0 top-full mt-1 w-40 bg-white border border-gray-100 rounded-xl shadow-xl overflow-hidden">
                                                <div class="py-1 text-xs">
                                                    <a href="#" class="flex items-center gap-2 px-4 py-2.5 text-slate-600 hover:bg-slate-50 hover:text-teal-custom">
                                                        <i class="fa-solid fa-eye"></i> View Profile
                                                    </a>
                                                    <a href="#" class="flex items-center gap-2 px-4 py-2.5 text-slate-600 hover:bg-slate-50 hover:text-teal-custom">
                                                        <i class="fa-solid fa-paper-plane"></i> Message
                                                    </a>
                                                    <hr class="my-1 border-gray-50">
                                                    <a href="#" class="flex items-center gap-2 px-4 py-2.5 text-red-500 hover:bg-red-50">
                                                        <i class="fa-solid fa-user-minus"></i> Remove
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-6 py-4 bg-slate-50 border-t border-gray-100 flex justify-between items-center">
                        <span class="text-xs font-medium text-gray-500">
                            Total: <span class="text-teal-custom font-bold"><?php echo count($data['Members']) + 1; ?></span> Personnel
                        </span>
                        <button class="text-xs font-bold text-teal-custom hover:text-teal-700 transition">
                            View Details <i class="fa-solid fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        // Optional: Logic to toggle main-shifted class if your sidebar uses it
        // This assumes your sidebars.php has logic to push a 'sidebar-toggled' event or class to body
        // For now, standard 95px margin is set in CSS.
    </script>
</body>
</html>