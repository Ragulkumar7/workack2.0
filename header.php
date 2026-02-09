<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<header class="relative flex items-center justify-end px-4 bg-white border-b border-gray-200 w-full h-14">
  
  <div class="flex items-center gap-2">
    
    <div class="relative w-full max-w-[280px] hidden md:block mr-2">
      <input 
        type="text" 
        placeholder="Search in HRMS" 
        class="w-full pl-3 pr-16 py-1.5 bg-gray-50 border border-gray-100 rounded text-[13px] focus:outline-none focus:border-blue-300"
      />
      <div class="absolute right-2 top-1.5 flex items-center gap-1 opacity-60 pointer-events-none">
        <kbd class="px-1 py-0.5 border border-gray-300 rounded bg-white text-[9px] text-gray-400">CTRL</kbd>
        <span class="text-gray-400 text-[10px]">/</span>
      </div>
    </div>

    <a href="settings.php" class="p-1.5 text-gray-500 hover:bg-gray-50 rounded transition-colors inline-flex items-center justify-center">
      <i data-lucide="settings" class="w-[18px] h-[18px]"></i>
    </a>

    <button id="fullscreenBtn" class="p-2 text-gray-400 hover:text-gray-600 hidden lg:block">
      <i data-lucide="maximize" class="w-4 h-4"></i>
    </button>
    
    <div class="relative">
      <div id="notifBtn" class="p-2 text-gray-400 hover:text-gray-600 cursor-pointer rounded-lg hover:bg-gray-50 transition-colors">
        <i data-lucide="bell" class="w-4 h-4"></i>
        <span class="absolute top-2 right-2.5 w-1.5 h-1.5 bg-red-500 border border-white rounded-full"></span>
      </div>

      <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-80 md:w-96 bg-white border border-gray-200 rounded-lg shadow-2xl z-50 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-bold text-gray-800 text-[15px]">Notifications (2)</h3>
          <div class="flex items-center gap-3">
            <button class="text-[13px] text-orange-500 font-medium hover:underline">Mark all as read</button>
            <span class="text-gray-400 flex items-center gap-1 text-[12px]">
              <i data-lucide="calendar" class="w-3 h-3"></i> Today
            </span>
          </div>
        </div>

        <div class="max-h-[400px] overflow-y-auto">
          <div class="p-4 flex gap-3 hover:bg-gray-50 border-b border-gray-50 cursor-pointer transition-colors">
            <img src="https://i.pravatar.cc/150?u=shawn" class="w-10 h-10 rounded-lg" alt="Shawn">
            <div class="flex-1">
              <p class="text-[13px] text-gray-600 leading-tight">
                <span class="font-bold text-gray-800">Shawn</span> performance in Math is below the threshold.
              </p>
              <span class="text-[11px] text-gray-400">Just Now</span>
            </div>
          </div>

          <div class="p-4 flex gap-3 hover:bg-gray-50 border-b border-gray-50 cursor-pointer transition-colors">
            <img src="https://i.pravatar.cc/150?u=sylvia" class="w-10 h-10 rounded-lg" alt="Sylvia">
            <div class="flex-1">
              <p class="text-[13px] text-gray-600 leading-tight">
                <span class="font-bold text-gray-800">Sylvia</span> added appointment on 02:00 PM
              </p>
              <span class="text-[11px] text-gray-400">10 mins ago</span>
              <div class="flex gap-2 mt-2">
                <button class="px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-[12px] font-semibold rounded transition-colors">Deny</button>
                <button class="px-4 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-[12px] font-semibold rounded transition-colors shadow-sm">Approve</button>
              </div>
            </div>
          </div>

          <div class="p-4 flex gap-3 hover:bg-gray-50 border-b border-gray-50 cursor-pointer transition-colors">
            <img src="https://i.pravatar.cc/150?u=teressa" class="w-10 h-10 rounded-lg" alt="Teressa">
            <div class="flex-1">
              <p class="text-[13px] text-gray-600 leading-tight">
                New student record <span class="font-bold text-gray-800">George</span> is created by <span class="font-bold text-gray-800">Teressa</span>
              </p>
              <span class="text-[11px] text-gray-400">2 hrs ago</span>
            </div>
          </div>
        </div>

        <div class="p-3 grid grid-cols-2 gap-3 bg-gray-50/50">
          <button class="py-2 text-[13px] font-bold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors text-center">Cancel</button>
          <button class="py-2 text-[13px] font-bold text-white bg-orange-500 hover:bg-orange-600 rounded-lg shadow-sm transition-colors text-center">View All</button>
        </div>
      </div>
    </div>

    <div class="relative flex items-center ml-2 pl-2 border-l border-gray-100">
      <div id="profileBtn" class="relative cursor-pointer">
        <img src="https://i.pravatar.cc/100?u=stephan" class="w-8 h-8 rounded-full object-cover" />
        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full"></span>
      </div>

      <div id="profileDropdown" class="hidden absolute right-0 top-10 mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-2xl z-50 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center gap-3">
          <img src="https://i.pravatar.cc/100?u=stephan" class="w-12 h-12 rounded-full object-cover" />
          <div class="overflow-hidden">
            <h4 class="font-bold text-gray-800 text-[14px] truncate">Kevin Larry</h4>
            <p class="text-gray-500 text-[12px] font-medium italic">Manager</p>
            <p class="text-gray-400 text-[11px] truncate">warren@example.com</p>
          </div>
        </div>
        
        <div class="py-1">
          <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-gray-700 hover:bg-gray-50 transition-colors">
            <i data-lucide="settings" class="w-4 h-4 text-gray-400"></i>
            Settings
          </a>
        </div>

        <div class="border-t border-gray-100 py-1 bg-gray-50/30">
          <a href="#" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-red-500 hover:bg-red-50 transition-colors font-semibold">
            <i data-lucide="log-out" class="w-4 h-4"></i>
            Logout
          </a>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
  lucide.createIcons();

  const setupDropdown = (btnId, dropdownId) => {
    const btn = document.getElementById(btnId);
    const dropdown = document.getElementById(dropdownId);
    
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('hidden');
      if(btnId === 'notifBtn') document.getElementById('profileDropdown').classList.add('hidden');
      if(btnId === 'profileBtn') document.getElementById('notifDropdown').classList.add('hidden');
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
        dropdown.classList.add('hidden');
      }
    });
  };

  setupDropdown('notifBtn', 'notifDropdown');
  setupDropdown('profileBtn', 'profileDropdown');

  const fullscreenBtn = document.getElementById('fullscreenBtn');
  fullscreenBtn.addEventListener('click', () => {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(err => {
        alert(`Error attempting to enable full-screen mode: ${err.message}`);
      });
    } else {
      if (document.exitFullscreen) document.exitFullscreen();
    }
  });
</script>