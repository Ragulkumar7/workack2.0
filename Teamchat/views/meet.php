<div id="meet_view">
    <div style="margin-bottom: 30px;">
        <h2 style="font-size: 2rem; font-weight: 800; color: var(--text-dark);">Meetings</h2>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 8px;">Connect face-to-face with your team instantly.</p>
    </div>
    
    <div style="display: flex; gap: 20px; margin-bottom: 50px; flex-wrap: wrap;">
        <button class="meet-hero-btn primary" onclick="document.getElementById('createMeetingModal').style.display='flex'">
            <i class="ri-links-fill"></i> Create meeting link
        </button>
        <button class="meet-hero-btn" onclick="openNewScheduleModal()">
            <i class="ri-calendar-event-fill"></i> Schedule meeting
        </button>
        <button class="meet-hero-btn" onclick="document.getElementById('joinMeetingModal').style.display='flex'">
            <i class="ri-keyboard-fill"></i> Join with ID
        </button>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px;">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark);">Recent Links</h3>
    </div>
    <div id="instantMeetingsContainer">
        <div class="data-card" style="margin-bottom: 30px; text-align: center; padding: 40px;">
            <i class="ri-links-line" style="font-size: 3rem; color: var(--border); margin-bottom: 15px; display: block;"></i>
            <p style="color: var(--text-muted); font-weight: 500;">No instant meeting links generated yet.</p>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 20px; margin-top: 40px;">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark);">Upcoming Scheduled Meetings</h3>
        <a href="#" onclick="switchMainTab('calendar_view', document.querySelectorAll('.sidebar-secondary-teams .nav-icon')[3])" style="color: var(--primary); text-decoration: none; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 6px;"><i class="ri-calendar-line"></i> View Calendar</a>
    </div>
    
    <div id="scheduledMeetingsContainer">
        <div class="data-card" style="text-align: center; padding: 40px;">
            <i class="ri-calendar-check-line" style="font-size: 3rem; color: var(--border); margin-bottom: 15px; display: block;"></i>
            <p style="color: var(--text-muted); font-weight: 500;">No scheduled meetings coming up.</p>
        </div>
    </div>
</div>