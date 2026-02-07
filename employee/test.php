<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f8fafc; padding: 40px; }</style>
</head>
<body>

<?php
    // Data
    $joining_date = "15 Jan 2024";
    $status = "Resigned"; // Options: "Active" or "Resigned"
    $notice_period_days = 18; 
    $last_working_day = "25 Feb 2026";
?>

    <div class="max-w-sm mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="space-y-4">
            
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                        <i class="fa-solid fa-calendar-check text-green-500 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Joined On</p>
                        <p class="font-bold text-sm text-slate-800"><?php echo $joining_date; ?></p>
                    </div>
                </div>
            </div>

            <div class="border-t border-dashed border-gray-100"></div>

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg <?php echo ($status == 'Resigned') ? 'bg-orange-50' : 'bg-blue-50'; ?> flex items-center justify-center">
                        <i class="fa-solid <?php echo ($status == 'Resigned') ? 'fa-file-signature text-orange-500' : 'fa-user-check text-blue-500'; ?> text-xs"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-gray-400 uppercase font-bold tracking-wider">Employment Status</p>
                        <p class="font-bold text-sm text-slate-800"><?php echo $status; ?></p>
                    </div>
                </div>
                
                <?php if($status == "Resigned"): ?>
                <div class="text-right">
                    <span class="block text-[10px] text-orange-600 font-bold bg-orange-100 px-2 py-0.5 rounded-full mb-1">
                        <?php echo $notice_period_days; ?> Days Notice
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php if($status == "Resigned"): ?>
            <div class="mt-2 p-3 bg-red-50 rounded-xl border border-red-100">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] text-red-600 font-bold uppercase tracking-tight">Last Working Day</span>
                    <span class="text-xs font-black text-red-700"><?php echo $last_working_day; ?></span>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>