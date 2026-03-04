<?php
// 1. Start Session (if not already started)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 2. AJAX HANDLER FOR NOTIFICATIONS (Must run before any HTML output) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['header_action']) && $_POST['header_action'] === 'mark_notifications_read') {
    if (isset($conn) && isset($_SESSION['user_id'])) {
        ob_clean(); // Prevent whitespace corruption in JSON response
        header('Content-Type: application/json');
        
        $uid = (int)$_SESSION['user_id'];
        $u_role = $_SESSION['role'] ?? '';
        
        // A. Mark direct personal notifications as read
        $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $uid AND is_read = 0");
        
        // B. Mark role-based broadcast notifications as read for THIS specific user
        $role_notifs = $conn->query("
            SELECT n.id FROM notifications n
            LEFT JOIN notification_reads nr ON n.id = nr.notif_id AND nr.user_id = $uid
            WHERE (n.target_role = '$u_role' OR n.target_role = 'All') AND nr.notif_id IS NULL
        ");
        
        if ($role_notifs && $role_notifs->num_rows > 0) {
            while($rn = $role_notifs->fetch_assoc()) {
                $nid = (int)$rn['id'];
                $conn->query("INSERT IGNORE INTO notification_reads (user_id, notif_id) VALUES ($uid, $nid)");
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// --- 3. IMPROVED DYNAMIC PATH ---
$script_path = $_SERVER['SCRIPT_NAME']; 
$path_parts = explode('/', trim($script_path, '/'));
$folder_depth = count($path_parts) - 2; 
$base_path = ($folder_depth > 0) ? str_repeat('../', $folder_depth) : './';

// --- 4. GET LOGGED-IN USER DATA ---
$current_user_id = $_SESSION['user_id'] ?? 0;
$user_email      = $_SESSION['username'] ?? 'Guest';
$user_role       = $_SESSION['role'] ?? 'User';
$display_name    = ucfirst(explode('@', $user_email)[0]); 

// --- 5. SMART NOTIFICATION SYSTEM (Crash-Proof DB Patch & Fetch) ---
$unread_count = 0;
$notifications = [];

if (isset($conn) && $current_user_id > 0) {
    
    // A. Ensure 'notifications' table exists and is structured for Roles
    $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($check_table && $check_table->num_rows == 0) {
        $conn->query("CREATE TABLE `notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `target_role` varchar(50) DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `type` varchar(50) DEFAULT 'info',
            `link` varchar(255) DEFAULT '#',
            `is_read` tinyint(1) DEFAULT 0,
            `created_at` timestamp DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `target_role` (`target_role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } else {
        // Smart Patch existing table
        $conn->query("ALTER TABLE `notifications` MODIFY COLUMN `user_id` int(11) NULL DEFAULT NULL");
        
        $check_role = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'target_role'");
        if ($check_role && $check_role->num_rows == 0) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `target_role` varchar(50) DEFAULT NULL AFTER `user_id`");
        }
        $check_read = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'is_read'");
        if ($check_read && $check_read->num_rows == 0) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `is_read` tinyint(1) DEFAULT 0");
        }
        $check_type = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'type'");
        if ($check_type && $check_type->num_rows == 0) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `type` varchar(50) DEFAULT 'info'");
        }
        $check_link = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'link'");
        if ($check_link && $check_link->num_rows == 0) {
            $conn->query("ALTER TABLE `notifications` ADD COLUMN `link` varchar(255) DEFAULT '#'");
        }
    }

    // B. Ensure 'notification_reads' tracking table exists for broadcast messages
    $check_reads = $conn->query("SHOW TABLES LIKE 'notification_reads'");
    if ($check_reads && $check_reads->num_rows == 0) {
        $conn->query("CREATE TABLE `notification_reads` (
            `user_id` int(11) NOT NULL,
            `notif_id` int(11) NOT NULL,
            `read_at` timestamp DEFAULT current_timestamp(),
            PRIMARY KEY (`user_id`, `notif_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // C. Fetch Unread Count (Combines Personal + Unread Role Broadcasts)
    $cnt_query = "
        SELECT COUNT(*) as cnt 
        FROM notifications n
        LEFT JOIN notification_reads nr ON n.id = nr.notif_id AND nr.user_id = $current_user_id
        WHERE (
            (n.user_id = $current_user_id AND n.is_read = 0) 
            OR 
            ((n.target_role = '$user_role' OR n.target_role = 'All') AND nr.notif_id IS NULL)
        )
    ";
    $cnt_res = $conn->query($cnt_query);
    if ($cnt_res) $unread_count = (int)$cnt_res->fetch_assoc()['cnt'];

    // D. Fetch Recent 5 Notifications (Combines Personal + Role Broadcasts)
    $notif_query = "
        SELECT n.*, 
               IF(n.user_id = $current_user_id, n.is_read, IF(nr.notif_id IS NOT NULL, 1, 0)) as actual_read_status
        FROM notifications n
        LEFT JOIN notification_reads nr ON n.id = nr.notif_id AND nr.user_id = $current_user_id
        WHERE (n.user_id = $current_user_id OR n.target_role = '$user_role' OR n.target_role = 'All')
        ORDER BY n.created_at DESC 
        LIMIT 5
    ";
    $notif_res = $conn->query($notif_query);
    if ($notif_res) {
        while ($row = $notif_res->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
}

// Helper function for "Time Ago" format
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
        
        $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hr', 'i' => 'min', 's' => 'sec');
        foreach ($string as $k => &$v) {
            if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); } 
            else { unset($string[$k]); }
        }
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<style>
    /* --- HEADER STYLING (FIXED TOP) --- */
    #mainHeader {
        position: fixed;
        top: 0;
        right: 0;
        left: 95px; 
        width: calc(100% - 95px); 
        height: 64px;
        background-color: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        z-index: 50; 
        transition: left 0.3s ease, width 0.3s ease; 
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 24px;
        box-sizing: border-box;
    }

    /* --- DYNAMIC SHIFTING (When Sidebar Opens) --- */
    #mainHeader.main-shifted {
        left: 315px; 
        width: calc(100% - 315px); 
    }

    /* Notification Pulse Animation */
    @keyframes pulse-ring {
        0% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
        100% { transform: scale(0.8); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .pulse-dot { animation: pulse-ring 2s infinite; }

    /* --- RESPONSIVE (Mobile) --- */
    @media (max-width: 768px) {
        #mainHeader { left: 0 !important; width: 100% !important; }
        #mainHeader.main-shifted { left: 0 !important; width: 100% !important; }
    }
</style>

<header id="mainHeader">
  <div class="flex items-center gap-3">
    
    <a href="<?php echo $base_path; ?>settings.php" class="hidden md:inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg transition-all mr-1">
      <i data-lucide="user" class="w-4 h-4"></i>
      <span>View Profile</span>
    </a>

    <button id="fullscreenBtn" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors hidden lg:block" title="Toggle Fullscreen">
      <i data-lucide="maximize" class="w-5 h-5"></i>
    </button>
    
    <div class="relative">
      <button id="notifBtn" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors relative">
        <i data-lucide="bell" class="w-5 h-5"></i>
        <?php if($unread_count > 0): ?>
            <span id="notifBadge" class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-red-500 border-2 border-white rounded-full pulse-dot"></span>
        <?php endif; ?>
      </button>

      <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-100 rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
          <h3 class="font-bold text-gray-800 text-sm">Notifications <?php echo $unread_count > 0 ? "($unread_count)" : ""; ?></h3>
          <?php if($unread_count > 0): ?>
              <button onclick="markNotificationsRead()" class="text-xs text-teal-600 hover:underline font-bold" id="markReadBtn">Mark all read</button>
          <?php endif; ?>
        </div>
        
        <div class="max-h-[300px] overflow-y-auto" id="notifList">
            <?php if (empty($notifications)): ?>
                <div class="p-6 text-center text-gray-400">
                    <i data-lucide="bell-off" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                    <p class="text-xs font-medium">You're all caught up!</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): 
                    // Dynamic styling based on notification type
                    $type = strtolower($notif['type']);
                    $bg = 'bg-blue-100'; $text = 'text-blue-600'; $icon = 'bell';
                    
                    if (strpos($type, 'alert') !== false || strpos($type, 'danger') !== false) { $bg = 'bg-red-100'; $text = 'text-red-600'; $icon = 'alert-circle'; }
                    elseif (strpos($type, 'success') !== false || strpos($type, 'salary') !== false || strpos($type, 'approved') !== false) { $bg = 'bg-emerald-100'; $text = 'text-emerald-600'; $icon = 'check-circle'; }
                    elseif (strpos($type, 'warning') !== false || strpos($type, 'leave') !== false) { $bg = 'bg-orange-100'; $text = 'text-orange-600'; $icon = 'clock'; }
                    
                    $is_read_styling = $notif['actual_read_status'] == 1 ? 'opacity-60' : 'bg-blue-50/20';
                ?>
                <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" class="p-4 flex gap-3 hover:bg-gray-50 border-b border-gray-50 transition-colors <?php echo $is_read_styling; ?>">
                    <div class="w-8 h-8 shrink-0 <?php echo $bg; ?> rounded-full flex items-center justify-center <?php echo $text; ?>">
                        <i data-lucide="<?php echo $icon; ?>" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1">
                      <p class="text-xs text-gray-600 leading-snug">
                          <?php if(!empty($notif['target_role'])): ?>
                              <span class="text-[9px] bg-slate-200 text-slate-600 px-1 rounded mr-1"><?= htmlspecialchars($notif['target_role']) ?></span>
                          <?php endif; ?>
                          <span class="font-bold text-gray-800"><?php echo htmlspecialchars($notif['title']); ?></span> 
                          <span class="block mt-0.5"><?php echo htmlspecialchars($notif['message']); ?></span>
                      </p>
                      <span class="text-[10px] text-gray-400 mt-1 block font-medium"><?php echo time_elapsed_string($notif['created_at']); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="relative pl-2 border-l border-gray-200 ml-2">
      <button id="profileBtn" class="flex items-center gap-2 focus:outline-none">
        <div class="w-9 h-9 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-sm uppercase shadow-sm ring-2 ring-transparent hover:ring-slate-100 transition-all relative">
            <?php echo substr($display_name, 0, 1); ?>
            <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full"></span>
        </div>
      </button>

      <div id="profileDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white border border-gray-100 rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center gap-3 bg-gray-50/50">
          <div class="w-10 h-10 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-sm uppercase">
            <?php echo substr($display_name, 0, 1); ?>
          </div>
          <div class="overflow-hidden">
            <h4 class="font-bold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($display_name); ?></h4>
            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($user_role); ?></p>
          </div>
        </div>
        
        <div class="py-1">
          <a href="<?php echo $base_path; ?>settings.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
            <i data-lucide="settings" class="w-4 h-4"></i>
            Settings
          </a>
        </div>

        <div class="border-t border-gray-100 py-1">
          <a href="<?php echo $base_path; ?>logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors font-medium">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            Logout
          </a>
        </div>
      </div>
    </div>

  </div>
</header>

<div style="height: 84px; width: 100%; flex-shrink: 0;"></div>

<script>
  // Initialize Icons
  if (typeof lucide !== 'undefined') { lucide.createIcons(); }

  // --- DROPDOWN LOGIC ---
  const setupDropdown = (btnId, dropdownId) => {
    const btn = document.getElementById(btnId);
    const dropdown = document.getElementById(dropdownId);
    if (btn && dropdown) {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          dropdown.classList.toggle('hidden');
          // Close other dropdown
          const otherId = btnId === 'notifBtn' ? 'profileDropdown' : 'notifDropdown';
          document.getElementById(otherId)?.classList.add('hidden');
        });
        document.addEventListener('click', (e) => {
          if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.add('hidden');
          }
        });
    }
  };
  setupDropdown('notifBtn', 'notifDropdown');
  setupDropdown('profileBtn', 'profileDropdown');

  // --- AJAX: MARK NOTIFICATIONS READ ---
  function markNotificationsRead() {
      const btn = document.getElementById('markReadBtn');
      if(btn) btn.innerText = "Marking...";
      
      const formData = new FormData();
      formData.append('header_action', 'mark_notifications_read');

      fetch(window.location.href, {
          method: 'POST',
          body: formData
      })
      .then(response => response.json())
      .then(data => {
          if(data.success) {
              // Hide the red badge
              const badge = document.getElementById('notifBadge');
              if(badge) badge.style.display = 'none';
              
              if(btn) btn.style.display = 'none';

              // Remove unread styling from list
              const items = document.querySelectorAll('#notifList a');
              items.forEach(item => {
                  item.classList.remove('bg-blue-50/20');
                  item.classList.add('opacity-60');
              });
          }
      }).catch(err => console.log('Notification update failed.', err));
  }

  // --- FULLSCREEN LOGIC ---
  const fullscreenBtn = document.getElementById('fullscreenBtn');
  if (fullscreenBtn) {
      fullscreenBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
          document.documentElement.requestFullscreen().catch(err => { console.log(err); });
        } else {
          if (document.exitFullscreen) document.exitFullscreen();
        }
      });
  }

  // --- SYNC HEADER WITH SIDEBAR (Auto-Width Adjustment) ---
  const headerObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.attributeName === 'class') {
        const mainContent = document.getElementById('mainContent');
        const mainHeader = document.getElementById('mainHeader');
        if (mainContent && mainHeader) {
          if (mainContent.classList.contains('main-shifted')) {
            mainHeader.classList.add('main-shifted');
          } else {
            mainHeader.classList.remove('main-shifted');
          }
        }
      }
    });
  });

  const targetNode = document.getElementById('mainContent');
  if (targetNode) {
    headerObserver.observe(targetNode, { attributes: true });
  }
</script>