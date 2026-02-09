<?php
// ats_check.php

// 1. SESSION START
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$result = null;

// 2. LOGIC: Compare Keywords vs Resume
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $job_role = $_POST['job_role'];
    
    // Get inputs and convert to lowercase for easy comparison
    $keywords_input = strtolower($_POST['keywords']); 
    $resume_text = strtolower($_POST['resume_text']);

    // Convert comma-separated keywords into an array
    // Example: "php, css, html" -> ["php", "css", "html"]
    $keywords_array = array_map('trim', explode(',', $keywords_input));
    
    $matched_keywords = [];
    $missing_keywords = [];

    foreach ($keywords_array as $word) {
        if (!empty($word)) {
            // Check if the keyword exists in the resume text
            if (strpos($resume_text, $word) !== false) {
                $matched_keywords[] = $word;
            } else {
                $missing_keywords[] = $word;
            }
        }
    }

    // Calculate Score
    $total_keywords = count($keywords_array);
    $matched_count = count($matched_keywords);
    
    $score = ($total_keywords > 0) ? round(($matched_count / $total_keywords) * 100) : 0;

    $result = [
        'score' => $score,
        'matched' => $matched_keywords,
        'missing' => $missing_keywords
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS | ATS Resume Checker</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        #mainContent { margin-left: 95px; padding: 30px; width: calc(100% - 95px); transition: 0.3s; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }

        /* Progress Circle Animation */
        .progress-circle {
            width: 120px; height: 120px;
            border-radius: 50%;
            background: conic-gradient(var(--color) var(--percent), #e2e8f0 0);
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }
        .progress-circle::before {
            content: ""; position: absolute;
            width: 100px; height: 100px;
            background: white; border-radius: 50%;
        }
        .progress-value { position: relative; font-size: 24px; font-weight: bold; color: #1e293b; }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>

    <div id="mainContent">
        
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">ATS Resume Checker</h1>
                <p class="text-sm text-slate-500">Scan resumes against job descriptions instantly</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2">
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Job Role / Title</label>
                            <input type="text" name="job_role" class="w-full border rounded-lg p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none" placeholder="e.g. Senior PHP Developer" value="<?php echo $_POST['job_role'] ?? ''; ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Required Keywords (Comma Separated)</label>
                            <textarea name="keywords" rows="3" class="w-full border rounded-lg p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none" placeholder="e.g. PHP, Laravel, MySQL, JavaScript, API" required><?php echo $_POST['keywords'] ?? ''; ?></textarea>
                            <p class="text-xs text-slate-400 mt-1">Add skillsets, tools, or qualifications separated by commas.</p>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Paste Resume Text</label>
                            <textarea name="resume_text" rows="8" class="w-full border rounded-lg p-3 text-sm focus:ring-2 focus:ring-orange-500 outline-none" placeholder="Copy and paste the candidate's resume content here..." required><?php echo $_POST['resume_text'] ?? ''; ?></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-orange-500 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-orange-600 transition shadow-md">
                                <i class="fa-solid fa-magnifying-glass mr-2"></i> Check Score
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="lg:col-span-1">
                <?php if ($result): ?>
                    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm h-full">
                        <h3 class="font-bold text-slate-800 mb-6 border-b pb-2">Analysis Result</h3>
                        
                        <div class="flex justify-center mb-6">
                            <?php 
                                $color = ($result['score'] > 70) ? '#10b981' : (($result['score'] > 40) ? '#f59e0b' : '#ef4444'); 
                            ?>
                            <div class="progress-circle" style="--percent: <?php echo $result['score']; ?>%; --color: <?php echo $color; ?>;">
                                <div class="progress-value"><?php echo $result['score']; ?>%</div>
                            </div>
                        </div>
                        <p class="text-center text-sm font-medium text-slate-600 mb-6">Match Probability</p>

                        <div class="mb-4">
                            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Matched Skills</p>
                            <div class="flex flex-wrap gap-2">
                                <?php if(empty($result['matched'])): ?>
                                    <span class="text-sm text-slate-400 italic">No matches found.</span>
                                <?php else: ?>
                                    <?php foreach($result['matched'] as $word): ?>
                                        <span class="bg-emerald-100 text-emerald-700 text-xs px-2 py-1 rounded font-bold uppercase"><i class="fa-solid fa-check mr-1"></i><?php echo $word; ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Missing Skills</p>
                            <div class="flex flex-wrap gap-2">
                                <?php if(empty($result['missing'])): ?>
                                    <span class="text-sm text-slate-400 italic">No missing skills!</span>
                                <?php else: ?>
                                    <?php foreach($result['missing'] as $word): ?>
                                        <span class="bg-rose-100 text-rose-700 text-xs px-2 py-1 rounded font-bold uppercase"><i class="fa-solid fa-xmark mr-1"></i><?php echo $word; ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 p-6 rounded-xl border border-dashed border-slate-300 h-full flex flex-col items-center justify-center text-center">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mb-4">
                            <i class="fa-solid fa-chart-pie text-2xl text-slate-300"></i>
                        </div>
                        <h4 class="text-slate-500 font-semibold">Ready to Analyze</h4>
                        <p class="text-xs text-slate-400 mt-2">Enter the job details and resume text to see the compatibility score.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>