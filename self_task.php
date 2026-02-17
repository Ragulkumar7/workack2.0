<?php 
// 1. SESSION & DATABASE CONNECTION
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// FIXED PATH LOGIC
// We look for db_connect.php inside: C:\xampp\htdocs\workack2.0\include\
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/workack2.0/include/db_connect.php';

if (file_exists($dbPath)) {
    include_once($dbPath);
} else {
    // Fallback if the above fails: try relative path
    $dbPath = __DIR__ . '/include/db_connect.php';
    if(file_exists($dbPath)) {
        include_once($dbPath);
    } else {
        die("<div style='color:red; padding:20px; border:1px solid red;'>
            <b>Error:</b> db_connect.php not found.<br>
            Please ensure your file is at: <b>C:\xampp\htdocs\workack2.0\include\db_connect.php</b>
        </div>");
    }
}

// Check Login (Using your existing session variable)
if (!isset($_SESSION['user_id'])) { 
    // If you use 'id' in other pages, ensure they match.
    $current_user_id = isset($_SESSION['id']) ? $_SESSION['id'] : null;
    if(!$current_user_id) {
        header("Location: index.php"); 
        exit(); 
    }
} else {
    $current_user_id = $_SESSION['user_id'];
}

// 2. HANDLE NEW TASK SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_task'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);

    $stmt = $conn->prepare("INSERT INTO personal_taskboard (user_id, title, priority, due_date, description, status) VALUES (?, ?, ?, ?, ?, 'todo')");
    $stmt->bind_param("issss", $current_user_id, $title, $priority, $due_date, $desc);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 3. HANDLE STATUS UPDATE
if (isset($_GET['update_id']) && isset($_GET['new_status'])) {
    $tid = intval($_GET['update_id']);
    $stat = mysqli_real_escape_string($conn, $_GET['new_status']);
    
    $stmt = $conn->prepare("UPDATE personal_taskboard SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $stat, $tid, $current_user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 4. FETCH TASKS
$tasks_result = $conn->query("SELECT * FROM personal_taskboard WHERE user_id = $current_user_id ORDER BY created_at DESC");
$tasks = ['todo' => [], 'inprogress' => [], 'completed' => []];
if($tasks_result) {
    while($row = $tasks_result->fetch_assoc()) {
        $tasks[$row['status']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | My Tasks</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: '#1b5a5a', primaryDark: '#144343', bgLight: '#f8fafc' },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        #mainContent { margin-left: 95px; width: calc(100% - 95px); transition: all 0.3s ease; }
        #mainContent.main-shifted { margin-left: 315px; width: calc(100% - 315px); }
        .task-col-scroll::-webkit-scrollbar { width: 4px; }
        .task-col-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <?php 
    // Including files from the root workack2.0 folder
    include_once('sidebars.php'); 
    include_once('header.php'); 
    ?>

    <div id="mainContent" class="p-8 min-h-screen">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">My Tasks</h1>
                <nav class="flex text-sm text-gray-500 mt-1 gap-2 items-center">
                    <span>Dashboard</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-primary font-medium">Personal Task Board</span>
                </nav>
            </div>
            <button onclick="openModal('addTaskModal')" class="bg-primary hover:bg-primaryDark text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow-lg flex items-center gap-2 transform active:scale-95">
                <i class="fas fa-plus"></i> New Task
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start h-[calc(100vh-180px)]">
            
            <div class="bg-slate-100/80 rounded-2xl p-4 h-full flex flex-col border border-slate-200/60" id="todo-col">
                <div class="flex justify-between items-center mb-4 px-1">
                    <h3 class="font-bold text-slate-700 uppercase text-xs tracking-wider flex items-center gap-2">To Do</h3>
                    <span class="bg-white text-slate-600 px-2.5 py-0.5 rounded-md text-xs font-bold border border-slate-200"><?php echo count($tasks['todo']); ?></span>
                </div>
                <div class="overflow-y-auto flex-1 task-col-scroll pr-1 space-y-3">
                    <?php foreach($tasks['todo'] as $t): ?>
                    <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:border-primary/50 transition-all">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 uppercase mb-2 inline-block"><?php echo $t['priority']; ?></span>
                        <h4 class="font-bold text-slate-800 text-sm mb-1"><?php echo htmlspecialchars($t['title']); ?></h4>
                        <p class="text-xs text-gray-500 line-clamp-2 mb-3"><?php echo htmlspecialchars($t['description']); ?></p>
                        <div class="pt-3 border-t border-gray-50 flex justify-between items-center">
                            <span class="text-[10px] text-gray-400"><?php echo date('d M', strtotime($t['due_date'])); ?></span>
                            <a href="?update_id=<?php echo $t['id']; ?>&new_status=inprogress" class="text-xs font-bold text-primary hover:underline">Start</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-slate-100/80 rounded-2xl p-4 h-full flex flex-col border border-slate-200/60" id="inprogress-col">
                <div class="flex justify-between items-center mb-4 px-1">
                    <h3 class="font-bold text-blue-700 uppercase text-xs tracking-wider">In Progress</h3>
                    <span class="bg-white text-blue-600 px-2.5 py-0.5 rounded-md text-xs font-bold border border-blue-100"><?php echo count($tasks['inprogress']); ?></span>
                </div>
                <div class="overflow-y-auto flex-1 task-col-scroll pr-1 space-y-3">
                    <?php foreach($tasks['inprogress'] as $t): ?>
                    <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm">
                        <h4 class="font-bold text-slate-800 text-sm mb-1"><?php echo htmlspecialchars($t['title']); ?></h4>
                        <a href="?update_id=<?php echo $t['id']; ?>&new_status=completed" class="block w-full text-center mt-3 py-1.5 rounded-lg bg-green-50 text-green-700 text-xs font-bold border border-green-100 hover:bg-green-600 hover:text-white transition-all">Finish</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-slate-100/80 rounded-2xl p-4 h-full flex flex-col border border-slate-200/60" id="completed-col">
                <div class="flex justify-between items-center mb-4 px-1">
                    <h3 class="font-bold text-green-700 uppercase text-xs tracking-wider">Completed</h3>
                    <span class="bg-white text-green-600 px-2.5 py-0.5 rounded-md text-xs font-bold border border-green-100"><?php echo count($tasks['completed']); ?></span>
                </div>
                <div class="overflow-y-auto flex-1 task-col-scroll pr-1 space-y-3">
                    <?php foreach($tasks['completed'] as $t): ?>
                    <div class="bg-white p-4 rounded-xl border border-gray-100 opacity-70">
                        <h4 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                            <i class="fas fa-check-circle text-green-500"></i> <?php echo htmlspecialchars($t['title']); ?>
                        </h4>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <div id="addTaskModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" id="modalPanel">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-lg text-slate-800">Create New Task</h3>
                <button onclick="closeModal('addTaskModal')" class="text-gray-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form class="p-6 space-y-4" method="POST">
                <input type="hidden" name="add_task" value="1">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Task Title *</label>
                    <input type="text" name="title" required class="w-full px-4 py-2.5 bg-slate-50 border border-gray-200 rounded-lg text-sm focus:border-primary focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Priority</label>
                        <select name="priority" class="w-full px-4 py-2 bg-slate-50 border border-gray-200 rounded-lg text-sm">
                            <option>Low</option><option selected>Medium</option><option>High</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Due Date</label>
                        <input type="date" name="due_date" required class="w-full px-4 py-2 bg-slate-50 border border-gray-200 rounded-lg text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2 bg-slate-50 border border-gray-200 rounded-lg text-sm resize-none"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('addTaskModal')" class="text-sm font-semibold text-gray-500">Cancel</button>
                    <button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-lg">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
    </script>
</body>
</html>