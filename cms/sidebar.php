<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workack - Sidebar Component</title>
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
<body class="bg-gray-100 font-sans leading-normal tracking-normal m-0 flex">

    <aside class="w-64 min-h-screen bg-workack-darker text-white shadow-lg flex flex-col">
        <div class="h-16 flex items-center justify-center border-b border-workack-dark">
            <h1 class="text-2xl font-bold tracking-wider text-workack-light"><i class="fas fa-leaf mr-2"></i> Workack</h1>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="#" class="block px-4 py-2 bg-workack-dark rounded-md transition-colors"><i class="fas fa-chart-line w-6"></i> Dashboard</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-users w-6"></i> Customers & Signups</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-ticket-alt w-6"></i> Tickets</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-key w-6"></i> Licenses</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-clock w-6"></i> Demo Users</a>
            <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-wallet w-6"></i> Payments</a>
            <div class="pt-4 mt-4 border-t border-workack-dark">
                <p class="text-xs uppercase text-gray-400 font-semibold px-4 mb-2">HRMS</p>
                <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-user-tie w-6"></i> Employees</a>
                <a href="#" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-calendar-alt w-6"></i> Attendance</a>
            </div>
        </nav>
    </aside>

</body>
</html>