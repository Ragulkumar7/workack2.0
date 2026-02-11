<?php
// ats_bulk.php

// 1. SESSION CHECK
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$results = [];
$show_results = false;

// 2. BACKEND LOGIC (MOCK SIMULATION)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['resumes'])) {
    
    $job_role = $_POST['job_role'];
    // Get skills and clean them up
    $required_skills = array_map('trim', explode(',', strtolower($_POST['skills'])));
    
    // Uploaded Files Processing
    $files = $_FILES['resumes'];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        $filename = $files['name'][$i];
        
        // Simulation Logic: 
        // In a real app, you would use a PDF parser here.
        // For this demo, we generate a realistic "Mock Score".
        
        $found_skills = [];
        $match_count = 0;

        foreach ($required_skills as $req) {
            // Randomly simulate if a candidate has the skill (For Demo Purpose)
            // Giving a 50% chance for each skill to match
            if (rand(0, 100) > 40) { 
                $found_skills[] = $req; 
                $match_count++;
            }
        }

        // Calculate Percentage
        $total_req = count($required_skills);
        $score = ($total_req > 0) ? round(($match_count / $total_req) * 100) : 0;

        // Determine Status & Color Logic
        if ($score >= 75) {
            $status = 'Shortlisted';
            $css_text = 'text-emerald-700';
            $css_bg = 'bg-emerald-50';
            $css_bar = 'bg-emerald-500';
        } elseif ($score >= 40) {
            $status = 'Review';
            $css_text = 'text-amber-700';
            $css_bg = 'bg-amber-50';
            $css_bar = 'bg-amber-500';
        } else {
            $status = 'Rejected';
            $css_text = 'text-rose-700';
            $css_bg = 'bg-rose-50';
            $css_bar = 'bg-rose-500';
        }

        $results[] = [
            'name' => pathinfo($filename, PATHINFO_FILENAME), // Show filename as Candidate Name
            'score' => $score,
            'status' => $status,
            'css_text' => $css_text,
            'css_bg' => $css_bg,
            'css_bar' => $css_bar,
            'skills' => $found_skills
        ];
    }

    // Sort by Score (High to Low) - Best candidates first
    usort($results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $show_results = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | Bulk Resume Screener</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        
        /* Layout Wrapper */
        #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); transition: 0.3s; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* Upload Area Styling */
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            transition: 0.3s;
            background: #fff;
            cursor: pointer;
            position: relative;
        }
        .upload-zone:hover { border-color: #ff5b37; background: #fff7ed; }
        
        /* Score Bar Animation */
        .score-track { width: 100px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .score-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>
    <?php include('header.php'); ?>

    <div id="mainContent">
        
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Smart Resume Screener</h1>
                <p class="text-sm text-slate-500">Bulk upload and AI-rank candidates instantly</p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
            
            <div class="xl:col-span-4 space-y-6">
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <h3 class="font-bold text-slate-800 mb-5 flex items-center gap-2">
                        <span class="bg-slate-100 text-slate-600 w-6 h-6 rounded flex items-center justify-center text-xs">1</span> Job Details
                    </h3>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Target Role</label>
                            <input type="text" name="job_role" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:border-orange-500 outline-none" placeholder="e.g. Senior Java Developer" value="<?php echo $_POST['job_role'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Must-Have Skills</label>
                            <textarea name="skills" rows="3" class="w-full border border-slate-300 rounded-lg p-2.5 text-sm focus:border-orange-500 outline-none" placeholder="e.g. Java, Spring Boot, Microservices, SQL" required><?php echo $_POST['skills'] ?? ''; ?></textarea>
                            <p class="text-xs text-slate-400 mt-1">Separate keywords with commas.</p>
                        </div>

                        <h3 class="font-bold text-slate-800 mb-4 mt-6 flex items-center gap-2">
                            <span class="bg-slate-100 text-slate-600 w-6 h-6 rounded flex items-center justify-center text-xs">2</span> Upload Resumes
                        </h3>
                        
                        <div class="upload-zone">
                            <input type="file" name="resumes[]" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required>
                            <div class="text-slate-400">
                                <i class="fa-solid fa-cloud-arrow-up text-4xl mb-3 text-orange-500"></i>
                                <p class="text-sm font-medium text-slate-600">Drag & Drop files here</p>
                                <p class="text-xs mt-1">Supports PDF, DOCX (Bulk Upload)</p>
                            </div>
                        </div>

                        <button type="submit" class="w-full mt-6 bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-lg shadow-md transition-all flex justify-center items-center gap-2">
                            <i class="fa-solid fa-microchip"></i> Start Screening
                        </button>
                    </form>
                </div>
            </div>

            <div class="xl:col-span-8">
                <?php if ($show_results): ?>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                            <div>
                                <h3 class="font-bold text-slate-700">Screening Results</h3>
                                <p class="text-xs text-slate-500"><?php echo count($results); ?> Candidates Processed</p>
                            </div>
                            <div class="flex gap-2">
                                <button class="px-3 py-1.5 bg-white border border-slate-300 rounded text-xs font-medium hover:bg-slate-50 shadow-sm">Export Excel</button>
                                <button class="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs font-medium hover:bg-emerald-700 shadow-sm">Shortlist Top 5</button>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="bg-white text-slate-500 border-b border-slate-200 uppercase text-xs">
                                    <tr>
                                        <th class="p-4 font-semibold">Candidate</th>
                                        <th class="p-4 font-semibold">Match Score</th>
                                        <th class="p-4 font-semibold">Matched Skills</th>
                                        <th class="p-4 font-semibold">Status</th>
                                        <th class="p-4 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php foreach ($results as $row): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs border border-slate-200">
                                                    <?php echo strtoupper(substr($row['name'], 0, 2)); ?>
                                                </div>
                                                <span class="font-medium text-slate-700"><?php echo ucwords(str_replace(['_', '-'], ' ', $row['name'])); ?></span>
                                            </div>
                                        </td>
                                        
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <span class="font-bold text-base <?php echo $row['css_text']; ?>"><?php echo $row['score']; ?>%</span>
                                                <div class="score-track">
                                                    <div class="score-fill <?php echo $row['css_bar']; ?>" style="width: <?php echo $row['score']; ?>%"></div>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="p-4">
                                            <div class="flex flex-wrap gap-1">
                                                <?php 
                                                $display_skills = array_slice($row['skills'], 0, 3);
                                                foreach ($display_skills as $skill): ?>
                                                    <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-[10px] uppercase font-bold border border-slate-200"><?php echo $skill; ?></span>
                                                <?php endforeach; ?>
                                                
                                                <?php if(count($row['skills']) > 3): ?>
                                                    <span class="px-2 py-0.5 text-slate-400 text-[10px] border border-transparent">+<?php echo count($row['skills']) - 3; ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if(empty($row['skills'])): ?>
                                                    <span class="text-xs text-slate-400 italic">No matches</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td class="p-4">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo $row['css_bg'] . ' ' . $row['css_text']; ?>">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </td>

                                        <td class="p-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-orange-500 hover:bg-orange-50 transition" title="View Resume">
                                                    <i class="fa-regular fa-eye"></i>
                                                </button>
                                                <button class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-emerald-500 hover:bg-emerald-50 transition" title="Shortlist">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-xl bg-slate-50 text-slate-400 min-h-[450px]">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                            <i class="fa-solid fa-layer-group text-3xl text-orange-200"></i>
                        </div>
                        <h4 class="font-bold text-lg text-slate-600">No resumes processed yet</h4>
                        <p class="text-sm text-slate-400 mt-1">Upload candidate files to see the AI ranking list here.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>