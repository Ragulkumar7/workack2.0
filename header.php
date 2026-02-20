<?php
// 1. Start Session (if not already started)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- IMPROVED DYNAMIC PATH ---
$script_path = $_SERVER['SCRIPT_NAME']; // e.g., /workack2.0/manager/dashboard.php
$path_parts = explode('/', trim($script_path, '/'));

// If your project is in a folder like 'workack2.0', we skip the first part
// Otherwise, count all parts except the filename
$folder_depth = count($path_parts) - 2; // -1 for project folder, -1 for filename

$base_path = ($folder_depth > 0) ? str_repeat('../', $folder_depth) : './';
// 3. Get Logged-in User Data
$user_email = $_SESSION['username'] ?? 'Guest';
$user_role  = $_SESSION['role'] ?? 'User';

// Generate Display Name
$display_name = ucfirst(explode('@', $user_email)[0]); 
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<style>
    /* --- HEADER STYLING (FIXED TOP) --- */
    #mainHeader {
        position: fixed;
        top: 0;
        right: 0;
        left: 95px; /* Starts after sidebar */
        width: calc(100% - 95px); /* Explicit width calculation */
        height: 64px;
        background-color: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        z-index: 50; /* Higher than content, lower than Sidebar */
        transition: left 0.3s ease, width 0.3s ease; /* Smooth animation */
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 24px;
        box-sizing: border-box;
    }

    /* --- DYNAMIC SHIFTING (When Sidebar Opens) --- */
    #mainHeader.main-shifted {
        left: 315px; /* 95px + 220px */
        width: calc(100% - 315px); /* Adjust width dynamically */
    }

    /* --- RESPONSIVE (Mobile) --- */
    @media (max-width: 768px) {
        #mainHeader { 
            left: 0 !important; 
            width: 100% !important; 
        }
        #mainHeader.main-shifted { 
            left: 0 !important; 
            width: 100% !important; 
        }
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
        <span class="absolute top-2 right-2.5 w-2 h-2 bg-red-500 border-2 border-white rounded-full"></span>
      </button>

      <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white border border-gray-100 rounded-xl shadow-xl z-50 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
          <h3 class="font-bold text-gray-800 text-sm">Notifications</h3>
          <button class="text-xs text-orange-600 hover:underline font-medium">Mark all read</button>
        </div>
        <div class="max-h-[300px] overflow-y-auto">
             <div class="p-4 flex gap-3 hover:bg-gray-50 border-b border-gray-50 cursor-pointer transition-colors">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-bold text-xs">S</div>
                <div class="flex-1">
                  <p class="text-xs text-gray-600 leading-snug"><span class="font-bold text-gray-800">System</span> update pending approval.</p>
                  <span class="text-[10px] text-gray-400 mt-1 block">Just Now</span>
                </div>
             </div>
        </div>
        <div class="p-2 bg-gray-50 text-center">
          <button class="text-xs font-medium text-gray-500 hover:text-gray-800">View All Notifications</button>
        </div>
      </div>
    </div>

    <div class="relative pl-2 border-l border-gray-200 ml-2">
      <button id="profileBtn" class="flex items-center gap-2 focus:outline-none">
        <div class="w-9 h-9 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-sm uppercase shadow-sm ring-2 ring-transparent hover:ring-slate-100 transition-all">
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