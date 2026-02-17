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

    <title>New Recruitment</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>

        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }

        .card { transition: transform 0.2s; }

        .card:hover { transform: translateY(-5px); }

    </style>

</head>

<body>



    <div class="lg:ml-20 p-6 md:p-10 transition-all duration-300 min-h-screen">

       

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">

            <div>

                <h1 class="text-2xl font-bold text-gray-800">Recruitment</h1>

                <nav class="text-sm text-gray-500 flex items-center gap-2 mt-1">

                    <i class="fa-solid fa-house text-xs"></i>

                    <span>&rsaquo;</span> Recruitment <span>&rsaquo;</span> <span class="text-gray-400">Jobs</span>

                </nav>

            </div>

           

            <div class="flex items-center gap-3 mt-4 md:mt-0">

                <div class="flex bg-white border rounded-md overflow-hidden">

                    <button class="px-3 py-2 text-gray-400 border-r"><i class="fa-solid fa-list-ul"></i></button>

                    <button class="px-3 py-2 bg-teal-700 text-white"><i class="fa-solid fa-grid-2"></i><i class="fa-solid fa-table-cells-large"></i></button>

                </div>

                <button class="bg-white border px-4 py-2 rounded-md text-gray-700 flex items-center gap-2">

                    <i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-chevron-down text-xs"></i>

                </button>

                <button class="bg-white border p-2 rounded-md text-gray-500"><i class="fa-solid fa-angles-up"></i></button>

            </div>

        </div>



        <div class="bg-white p-4 rounded-lg shadow-sm border mb-8 flex flex-col lg:flex-row justify-between items-center gap-4">

            <h2 class="font-bold text-gray-700 px-2">Job Grid</h2>

           

            <div class="flex flex-wrap items-center gap-3">

                <div class="relative">

                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">

                        <i class="fa-regular fa-calendar"></i>

                    </span>

                    <input type="text" value="02/10/2026 - 02/16/2026" class="pl-10 pr-4 py-2 border rounded-md text-sm text-gray-600 focus:outline-none w-56">

                </div>



                <select class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none appearance-none bg-white min-w-[100px]">

                    <option>Role</option>

                </select>

                <select class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none appearance-none bg-white min-w-[100px]">

                    <option>Status</option>

                </select>

                <select class="border rounded-md px-4 py-2 text-sm text-gray-600 focus:outline-none appearance-none bg-white min-w-[180px]">

                    <option>Sort By : Last 7 Days</option>

                </select>

            </div>

        </div>



        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            <?php

            $jobs = [

                ['title' => 'Senior IOS Developer', 'loc' => 'New York, USA', 'sal' => '30, 000 - 35, 000 / month', 'exp' => '2 years', 'icon' => 'fa-apple', 'icon_bg' => 'bg-gray-50'],

                ['title' => 'Junior PHP Developer', 'loc' => 'Los Angeles, USA', 'sal' => '20, 000 - 25, 000 / month', 'exp' => '4 years', 'icon' => 'fa-php', 'icon_bg' => 'bg-blue-50'],

                ['title' => 'Network Engineer', 'loc' => 'Bristol, UK', 'sal' => '30, 000 - 35, 000 / month', 'exp' => '1 year', 'icon' => 'fa-globe', 'icon_bg' => 'bg-gray-50'],

                ['title' => 'React Developer', 'loc' => 'Birmingham, UK', 'sal' => '28, 000 - 32, 000 / month', 'exp' => '3 years', 'icon' => 'fa-react', 'icon_bg' => 'bg-blue-50'],

                ['title' => 'Laravel Developer', 'loc' => 'Washington, USA', 'sal' => '30, 000 - 35, 000 / month', 'exp' => '2 years', 'icon' => 'fa-laravel', 'icon_bg' => 'bg-red-50'],

                ['title' => 'DevOps Engineer', 'loc' => 'Coventry, UK', 'sal' => '30, 000 - 35, 000 / month', 'exp' => '2 years', 'icon' => 'fa-gears', 'icon_bg' => 'bg-gray-50'],

                ['title' => 'Android Developer', 'loc' => 'Chicago, USA', 'sal' => '30, 000 - 35, 000 / month', 'exp' => '2 years', 'icon' => 'fa-android', 'icon_bg' => 'bg-green-50'],

                ['title' => 'HTML Developer', 'loc' => 'Carlisle, UK', 'sal' => '30, 000 - 35, 000 / month', 'exp' => '2 years', 'icon' => 'fa-html5', 'icon_bg' => 'bg-orange-50'],

            ];



            foreach ($jobs as $job): ?>

                <div class="card bg-white rounded-xl shadow-sm border p-5">

                    <div class="flex items-center gap-4 bg-gray-50 rounded-lg p-4 mb-4">

                        <div class="w-12 h-12 <?= $job['icon_bg'] ?> rounded-lg flex items-center justify-center text-2xl text-gray-700">

                            <i class="fa-brands <?= $job['icon'] ?>"></i>

                        </div>

                        <div>

                            <h3 class="font-bold text-gray-800 leading-tight"><?= $job['title'] ?></h3>

                            <p class="text-xs text-gray-500">25 Applicants</p>

                        </div>

                    </div>



                    <div class="space-y-3 mb-5">

                        <div class="flex items-center gap-2 text-sm text-gray-600">

                            <i class="fa-solid fa-location-dot text-gray-400 w-4"></i>

                            <?= $job['loc'] ?>

                        </div>

                        <div class="flex items-center gap-2 text-sm text-gray-600">

                            <i class="fa-solid fa-dollar-sign text-gray-400 w-4"></i>

                            <?= $job['sal'] ?>

                        </div>

                        <div class="flex items-center gap-2 text-sm text-gray-600">

                            <i class="fa-solid fa-briefcase text-gray-400 w-4"></i>

                            <?= $job['exp'] ?> of experience

                        </div>

                    </div>



                    <div class="flex gap-2 mb-6">

                        <span class="px-3 py-1 bg-pink-100 text-pink-500 text-[10px] font-bold uppercase rounded">Full Time</span>

                        <span class="px-3 py-1 bg-cyan-50 text-cyan-500 text-[10px] font-bold uppercase rounded border border-cyan-100">Expert</span>

                    </div>



                    <div>

                        <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2">

                            <div class="bg-teal-700 h-1.5 rounded-full" style="width: 40%"></div>

                        </div>

                        <p class="text-[11px] text-gray-400">10 of 25 filled</p>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>



    <div class="fixed right-0 top-1/2 bg-teal-700 text-white p-2 rounded-l-md cursor-pointer shadow-lg z-50">

        <i class="fa-solid fa-gear animate-spin-slow"></i>

    </div>



</body>

</html>