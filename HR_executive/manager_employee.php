<?php 
include '../sidebars.php'; 
include '../header.php';
// Commented out includes for testing purposes, uncomment in your real file
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Executive Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .team-card:hover { transform: translateY(-5px); transition: all 0.3s ease; }
        .member-chip:hover { background-color: #f3f4f6; }
        
        /* Dropdown visibility logic */
        .group:hover .dropdown-menu { display: block; }
        .dropdown-menu { display: none; z-index: 50; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen font-sans">

  

    <div class="container mx-auto py-10 px-4">
        <header class="mb-10">
            <h2 class="text-3xl font-extrabold text-gray-800">Manager Overview</h2>
            <p class="text-gray-500 mt-2">Manage and view team distributions across departments.</p>
        </header>

        <?php
        // Sample Data Structure
        $departments = [
            "Development Team" => [
                "Lead" => "Alexander Wright",
                "Members" => ["Sarah Chen", "Marcus Vane", "Elena Rodriguez", "David Kim"]
            ],
            "Design & Creative" => [
                "Lead" => "Sophia Bennett",
                "Members" => ["Liam O'Shea", "Emma Wilson", "Noah Garcia"]
            ],
            "Marketing & Growth" => [
                "Lead" => "Julian Thorne",
                "Members" => ["Olivia Pope", "Riley Scott", "Lucas Meyer", "Isabella Ross"]
            ]
        ];
        ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($departments as $teamName => $data): ?>
                <div class="team-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 p-5 border-b border-gray-100">
                        <h3 class="text-xl font-bold text-teal-900 uppercase tracking-wider text-sm">
                            <?php echo $teamName; ?>
                        </h3>
                    </div>

                    <div class="p-6">
                        <div class="mb-6">
                            <label class="text-xs font-semibold text-gray-400 uppercase">Team Lead</label>
                            <div class="flex items-center mt-2 p-3 bg-teal-50 rounded-lg border border-teal-100">
                                <div class="h-10 w-10 rounded-full bg-teal-600 flex items-center justify-center text-white font-bold">
                                    <?php echo substr($data['Lead'], 0, 1); ?>
                                </div>
                                <div class="ml-3">
                                    <p class="font-bold text-gray-800"><?php echo $data['Lead']; ?></p>
                                    <p class="text-xs text-teal-700 font-medium">Senior Manager</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-gray-400 uppercase">Team Members</label>
                            <div class="mt-3 space-y-2">
                                <?php foreach ($data['Members'] as $member): ?>
                                    <div class="member-chip flex items-center justify-between p-2 rounded-md border border-transparent hover:border-gray-200 transition-colors group relative">
                                        <div class="flex items-center">
                                            <i class="far fa-user text-gray-400 mr-3 text-sm"></i>
                                            <span class="text-gray-700 text-sm"><?php echo $member; ?></span>
                                        </div>
                                        
                                        <div class="relative inline-block text-left">
                                            <button class="text-gray-400 hover:text-teal-700 px-2">
                                                <i class="fas fa-ellipsis-v text-xs"></i>
                                            </button>
                                            
                                            <div class="dropdown-menu absolute right-0 mt-0 w-32 bg-white border border-gray-200 rounded-md shadow-xl">
                                                <div class="py-1 text-xs">
                                                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-teal-50 hover:text-teal-700">
                                                        <i class="fas fa-ban mr-2"></i> Deactivate
                                                    </a>
                                                    <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-teal-50 hover:text-teal-700">
                                                        <i class="fas fa-exchange-alt mr-2"></i> Swipe
                                                    </a>
                                                    <hr class="my-1 border-gray-100">
                                                    <a href="#" class="block px-4 py-2 text-red-600 hover:bg-red-50">
                                                        <i class="fas fa-trash-alt mr-2"></i> Remove
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 text-right border-t border-gray-100">
                        <span class="text-xs text-gray-500 italic">
                            Total: <?php echo count($data['Members']) + 1; ?> Personnel
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <footer class="text-center py-10 text-gray-400 text-sm">
        &copy; <?php echo date("Y"); ?> HR Executive Management System
    </footer>

</body>
</html>