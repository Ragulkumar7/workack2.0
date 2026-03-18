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

    <aside class="w-64 h-screen sticky top-0 bg-workack-darker text-white shadow-lg flex flex-col">
        <div class="h-16 flex items-center justify-center border-b border-workack-dark">
            <h1 class="text-2xl font-bold tracking-wider text-workack-light"><i class="fas fa-leaf mr-2"></i> Workack</h1>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="cms_dashboard.php" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-chart-line w-6"></i> Dashboard</a>
            <a href="client_management.php" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-users w-6"></i> Customers</a>
            <a href="tickets.php" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-ticket-alt w-6"></i> Tickets</a>
            <a href="licenses.php" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-key w-6"></i> Licenses</a>
            <a href="payments_details.php" class="block px-4 py-2 hover:bg-workack-dark rounded-md transition-colors"><i class="fas fa-wallet w-6"></i> Payments</a>
        </nav>
    </aside>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Get the current file name from the URL (e.g., 'cms_dashboard.php')
            const currentPage = window.location.pathname.split('/').pop();
            
            // Get all links inside the navigation
            const navLinks = document.querySelectorAll('nav a');
            
            navLinks.forEach(link => {
                const linkHref = link.getAttribute('href');
                
                // Check if the link matches the current page
                if (linkHref === currentPage || (currentPage === '' && linkHref === 'cms_dashboard.php')) {
                    // Remove hover effect and add the permanent mild highlight
                    link.classList.remove('hover:bg-workack-dark');
                    link.classList.add('bg-workack-dark', 'bg-opacity-70', 'border-l-4', 'border-workack-light');
                }
            });
        });
    </script>
</body>
</html>