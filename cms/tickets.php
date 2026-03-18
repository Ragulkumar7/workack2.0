<?php
// Sample ticket data - Easy to replace with your database fetch logic later
$tickets = [
    [
        'id' => 'T-5678',
        'customer' => 'TechCorp Inc.',
        'issue' => 'Login Session Expired Bug on mobile app',
        'agent' => 'Karthik Raja',
        'date' => '12/03/2026',
        'status' => 'Open',
        'status_color' => 'bg-red-100 text-red-600 border-red-200'
    ],
    [
        'id' => 'T-5677',
        'customer' => 'Jane Doe',
        'issue' => 'License Key Not Received for renewal',
        'agent' => 'Priya Sharma',
        'date' => '10/03/2026',
        'status' => 'Pending',
        'status_color' => 'bg-yellow-100 text-yellow-600 border-yellow-200'
    ],
    [
        'id' => 'T-5676',
        'customer' => 'Global Systems',
        'issue' => 'Payment Gateway Error on checkout',
        'agent' => 'Admin',
        'date' => '08/03/2026',
        'status' => 'Resolved',
        'status_color' => 'bg-green-100 text-green-600 border-green-200'
    ],
    [
        'id' => 'T-5675',
        'customer' => 'Acme Corp.',
        'issue' => 'Dashboard not loading for team members',
        'agent' => 'Karthik Raja',
        'date' => '05/03/2026',
        'status' => 'Resolved',
        'status_color' => 'bg-green-100 text-green-600 border-green-200'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack CMS | Ticket Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        workack: {
                            light: '#d1fae5', 
                            DEFAULT: '#10b981', 
                            dark: '#047857', 
                            darker: '#064e3b', 
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal flex">

    <?php include 'sidebar.php'; ?>

    <main class="flex-1 flex flex-col h-screen overflow-y-auto">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-6 z-10 sticky top-0">
            <div class="flex items-center bg-gray-100 rounded-md px-3 py-2 w-96">
                <i class="fas fa-search text-gray-400"></i>
                <input type="text" placeholder="Search tickets, users, or licenses..." class="bg-transparent border-none outline-none ml-2 w-full text-sm">
            </div>
            <div class="flex items-center space-x-4">
                <button class="text-gray-500 hover:text-workack"><i class="fas fa-bell"></i></button>
                <div class="flex items-center space-x-2 border-l pl-4 cursor-pointer">
                    <div class="w-8 h-8 bg-workack rounded-full flex items-center justify-center text-white font-bold">A</div>
                    <span class="text-sm font-medium text-gray-700">Admin</span>
                </div>
            </div>
        </header>

        <div class="p-6 flex-grow">
            <div class="mb-6 flex justify-between items-center">
                <h2 class="text-2xl font-semibold text-gray-800">Ticket Management</h2>
                <button class="bg-workack hover:bg-workack-dark text-white px-4 py-2 rounded-md shadow transition-colors text-sm">
                    <i class="fas fa-plus mr-1"></i> Create New Ticket
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-workack">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Total Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">2,345</p>
                        </div>
                        <div class="w-10 h-10 bg-workack-light rounded-full flex items-center justify-center text-workack-dark text-xl">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Open Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">89</p>
                        </div>
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600 text-xl">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Pending Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">112</p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600 text-xl">
                            <i class="fas fa-hourglass-start"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500 font-medium">Resolved Tickets</p>
                            <p class="text-2xl font-bold text-gray-800">2,144</p>
                        </div>
                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center text-green-600 text-xl">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-semibold text-gray-800">Detailed Ticket Tracking</h3>
                    <div class="flex items-center space-x-2">
                        <div class="flex items-center bg-gray-50 rounded-md px-2 py-1 border border-gray-200">
                            <i class="fas fa-search text-gray-400 text-xs"></i>
                            <input type="text" placeholder="Filter tickets..." class="bg-transparent border-none outline-none ml-2 text-xs w-48">
                        </div>
                        <button class="text-xs text-gray-500 hover:text-workack"><i class="fas fa-filter mr-1"></i> Filter</button>
                    </div>
                </div>
                
                <div class="flex flex-col w-full">
                    <div class="flex bg-gray-50 border-b border-gray-200 text-sm font-semibold text-gray-600">
                        <div class="w-1/2 px-6 py-3 border-r border-gray-200 flex items-center">
                            <i class="fas fa-bullhorn text-gray-400 mr-2"></i> Raised Details
                        </div>
                        <div class="w-1/2 px-6 py-3 flex items-center">
                            <i class="fas fa-user-shield text-gray-400 mr-2"></i> Assignment & Resolution
                        </div>
                    </div>

                    <?php foreach ($tickets as $ticket): ?>
                    <div class="flex w-full border-b last:border-0 hover:bg-gray-50 transition-colors duration-150">
                        
                        <div class="w-1/2 px-6 py-5 border-r border-gray-200">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-sm font-bold text-gray-800"><?php echo $ticket['customer']; ?></span>
                                <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded font-mono border border-gray-200">
                                    <?php echo $ticket['id']; ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2 leading-relaxed">
                                <span class="font-medium text-gray-700">Issue:</span> <?php echo $ticket['issue']; ?>
                            </p>
                            <p class="text-xs text-gray-400">
                                <i class="far fa-clock mr-1"></i> Raised on <?php echo $ticket['date']; ?>
                            </p>
                        </div>

                        <div class="w-1/2 px-6 py-5 flex flex-col justify-center">
                            <div class="flex justify-between items-center w-full">
                                
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-workack-light rounded-full flex items-center justify-center text-workack-dark mr-3">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 mb-0.5">Assigned To</p>
                                        <p class="text-sm font-semibold text-gray-800"><?php echo $ticket['agent']; ?></p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-4">
                                    <span class="<?php echo $ticket['status_color']; ?> px-3 py-1.5 rounded-full text-xs font-bold border">
                                        <?php echo $ticket['status']; ?>
                                    </span>
                                    
                                    <div class="border-l border-gray-200 pl-4 flex space-x-3 text-gray-400">
                                        <button class="hover:text-workack transition-colors" title="View Details"><i class="fas fa-eye"></i></button>
                                        <button class="hover:text-blue-500 transition-colors" title="Update Ticket"><i class="fas fa-edit"></i></button>
                                    </div>
                                </div>
                                
                            </div>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center text-sm text-gray-500 bg-white rounded-b-lg">
                    <div>Showing 1 to 4 of 2,345 entries</div>
                    <div class="flex space-x-1">
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50 disabled:opacity-50" disabled><i class="fas fa-chevron-left"></i></button>
                        <button class="px-3 py-1 bg-workack text-white border border-workack rounded text-xs font-semibold">1</button>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">2</button>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">3</button>
                        <span class="px-3 py-1 text-xs">...</span>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50">587</button>
                        <button class="px-3 py-1 bg-white border rounded text-xs hover:bg-gray-50"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

        </div>
    </main>
</body>
</html>