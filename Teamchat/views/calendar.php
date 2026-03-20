<div id="calendar_view" style="display: none; flex-direction: column; height: 100%; width: 100%; background: var(--surface); overflow: hidden;">
    <div class="calendar-header">
        <div class="calendar-title">
            <i class="ri-calendar-event-fill"></i> Calendar
        </div>
        
        <div style="display: flex; align-items: center; gap: 16px;">
            <button class="cal-nav-btn" onclick="resetCalendarToToday()">Today</button>
            <div style="display: flex; gap: 8px; align-items: center;">
                <button class="btn-icon-small" onclick="shiftCalendarWeek(-1)"><i class="ri-arrow-left-s-line"></i></button>
                <button class="btn-icon-small" onclick="shiftCalendarWeek(1)"><i class="ri-arrow-right-s-line"></i></button>
            </div>
            
            <div style="position:relative; display:inline-flex; align-items:center; cursor: pointer;" onclick="document.getElementById('calendarMonthPicker').showPicker()">
                <span id="calendarMonthYear" style="font-weight: 700; font-size: 1.2rem; margin: 0 20px; pointer-events: none; color: var(--text-dark);">... <i class="ri-arrow-down-s-line" style="font-size: 1rem; color: var(--text-muted);"></i></span>
                <input type="month" id="calendarMonthPicker" onchange="changeCalendarMonth(this.value)" style="position:absolute; top:0; left:0; width:1px; height:1px; opacity:0; pointer-events:none; border:none; padding:0; margin:0;">
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 12px;">
            <select id="calendarViewSelect" onchange="toggleWeekendView(this.value)" class="cal-nav-btn" style="border:1px solid var(--border); background:var(--surface); cursor:pointer; font-weight:600; padding-right: 30px;">
                <option value="work_week">Work week</option>
                <option value="week">Full week</option>
            </select>
            <button class="cal-primary-btn" onclick="openNewScheduleModal()"><i class="ri-add-line"></i> New Meeting</button>
        </div>
    </div>

    <div class="calendar-grid-container">
        <div class="calendar-grid">
            <div class="time-col">
                <div class="day-header" style="height: 85px; border-bottom: none; position: sticky; top:0; background: #fafafa; z-index:10;"></div>
                <?php 
                $times = ['12 AM','1 AM','2 AM','3 AM','4 AM','5 AM','6 AM','7 AM','8 AM','9 AM','10 AM','11 AM','12 PM','1 PM','2 PM','3 PM','4 PM','5 PM','6 PM','7 PM','8 PM','9 PM','10 PM','11 PM'];
                foreach($times as $t): ?>
                    <div class="time-slot"><?php echo $t; ?></div>
                <?php endforeach; ?>
            </div>

            <div class="day-cols" id="calendarDayCols"></div>
        </div>
    </div>
</div>