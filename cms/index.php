<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Workack - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-brand-teal { background-color: #206060; }
        .text-brand-teal { color: #206060; }
        .btn-brand { background-color: #1a5151; }
        .btn-brand:hover { background-color: #133f3f; }
        .glass-panel {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
    </style>
</head>
<body class="h-screen w-full flex items-center justify-center bg-gray-50 m-0">
    
    <div class="flex flex-col md:flex-row w-full h-full md:h-screen bg-white overflow-hidden">

        <div class="bg-brand-teal text-white w-full md:w-1/2 p-10 md:p-16 flex flex-col justify-center relative">
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight tracking-tight">
                Empowering Talent,<br>Simplifying HR.
            </h1>
            <p class="text-lg md:text-xl text-gray-200 mb-12 max-w-lg font-light">
                CMS Workack provides a modern, intuitive interface to manage payroll, attendance, and team growth in one place.
            </p>

            <div class="glass-panel rounded-2xl p-6 mt-10 md:mt-20 max-w-md">
                <h3 class="text-xs font-bold tracking-widest text-gray-300 mb-4 uppercase">CMS Workack Insights</h3>
                <div class="space-y-3 text-sm text-gray-200">
                    <p class="flex justify-between"><span>System Status:</span> <span class="text-white font-semibold">Online</span></p>
                    <p class="flex justify-between"><span>Active Users:</span> <span class="text-white font-semibold">125</span></p>
                    <p class="flex justify-between"><span>Attendance Rate:</span> <span class="text-white font-semibold">98.5%</span></p>
                </div>
            </div>
        </div>

        <div class="w-full md:w-1/2 p-10 md:p-16 flex flex-col justify-center bg-white">
            <div class="max-w-md w-full mx-auto">
                
                <div class="flex items-center gap-4 mb-12">
                    <img src="../assets/logo.png" alt="CMS Workack Logo" class="h-14 w-auto object-contain">
                    <span class="text-3xl font-bold text-gray-800 tracking-tight">CMS Workack</span>
                </div>

                <h2 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h2>
                <p class="text-gray-500 mb-8 text-sm">Please enter your credentials to access your dashboard.</p>

                <form class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Username / Email</label>
                        <input type="email" placeholder="employee@gmail.com" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent transition bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input type="password" placeholder="••••••••" class="w-full px-4 py-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-teal focus:border-transparent transition bg-gray-50">
                    </div>
                    <button type="submit" class="w-full btn-brand text-white font-semibold py-3 mt-4 rounded-lg transition duration-200 shadow-lg hover:shadow-xl">
                        Sign In to CMS Workack
                    </button>
                </form>

                <div class="mt-12 text-center text-xs text-gray-400">
                    &copy; 2026 CMS Workack. All rights reserved by neoerainfotech.in
                </div>
            </div>
        </div>

    </div>
</body>
</html>