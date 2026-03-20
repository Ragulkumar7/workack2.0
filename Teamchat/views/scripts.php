<script>
    let activeConvId = null;
    let editingMsgId = null;
    let masterPollInterval = null; 
    let isFetchingMessages = false;
    let isSidebarFetching = false;
    let lastFetchedMsgId = 0;
    let isUserScrolling = false;
    let selectedMembers = new Set();
    let selectedAddMembers = new Set();
    let jitsiApi = null;
    const myUserName = "<?php echo htmlspecialchars($my_username, ENT_QUOTES, 'UTF-8'); ?>";
    let currentIncomingCall = null;
    let searchDebounce = null;
    let typingTimer = null;
    let isGroupChat = false;

    // --- Tab Switching Logic (Secondary Sidebar) ---
    function switchMainTab(tabId, el) {
        document.querySelectorAll('.sidebar-secondary-teams .nav-icon').forEach(n => n.classList.remove('active'));
        el.classList.add('active');

        ['chat_view', 'people_view', 'calendar_view', 'meet_view'].forEach(id => {
            const domEl = document.getElementById(id);
            if(domEl) domEl.style.display = 'none';
        });
        
        document.getElementById(tabId).style.display = 'flex';
        
        const chatSidebar = document.getElementById('chatSidebar');
        if(tabId !== 'chat_view') {
            chatSidebar.style.display = 'none';
        } else {
            chatSidebar.style.display = 'flex';
            if(activeConvId) fetchMessages(false);
        }

        if (tabId === 'calendar_view') { renderCalendar(); }
        if (tabId === 'meet_view') { loadMeetingHistory(); }
    }

    // --- DELETE MEETING HISTORY ---
    function deleteMeetingHistory(id, type) {
        Swal.fire({
            title: 'Delete this meeting?', icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#ef4444', confirmButtonText: 'Delete'
        }).then((res) => {
            if(res.isConfirmed) {
                let fd = new FormData();
                fd.append('action', 'delete_meeting'); fd.append('meet_id', id); fd.append('meet_type', type);
                fetch('backend.php', { method: 'POST', body: fd }).then(() => {
                    loadMeetingHistory();
                    if (document.getElementById('calendar_view').style.display !== 'none') { renderCalendar(); }
                });
            }
        });
    }

    // --- LOAD MEET HISTORY (Links & Scheduled) ---
    function loadMeetingHistory() {
        let fd = new FormData();
        fd.append('action', 'fetch_meeting_history');
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let instHtml = '';
            if (data.instant && data.instant.length > 0) {
                data.instant.forEach(m => {
                    instHtml += `<div class="data-card" style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; align-items: center; gap: 20px;">
                                        <div style="width: 50px; height: 50px; background: var(--primary-light); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <i class="ri-link" style="font-size: 1.5rem; color: var(--primary);"></i>
                                        </div>
                                        <div>
                                            <p style="font-weight: 700; font-size: 1.1rem; margin-bottom: 4px;">${escapeHTML(m.title)}</p>
                                            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Created: ${m.created_at}</span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 12px;">
                                        <button onclick="openEmbeddedMeeting('${m.meet_link}', 'video')" style="background: var(--primary-light); color: var(--primary); border: none; padding: 8px 24px; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='var(--primary)'; this.style.color='white'" onmouseout="this.style.background='var(--primary-light)'; this.style.color='var(--primary)'">Join</button>
                                        <button onclick="navigator.clipboard.writeText('${m.meet_link}'); Swal.fire('Copied!', 'Link copied to clipboard', 'success');" class="btn-icon-small" title="Copy Link"><i class="ri-file-copy-line"></i></button>
                                        <button onclick="deleteMeetingHistory(${m.id}, 'instant')" class="btn-icon-small" style="color:#ef4444;" title="Delete"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                </div>`;
                });
                document.getElementById('instantMeetingsContainer').innerHTML = instHtml;
            } else {
                document.getElementById('instantMeetingsContainer').innerHTML = `<div class="data-card" style="margin-bottom: 30px; text-align: center; padding: 40px;"><i class="ri-links-line" style="font-size: 3rem; color: var(--border); margin-bottom: 15px; display: block;"></i><p style="color: var(--text-muted); font-weight: 500;">No instant meeting links generated yet.</p></div>`;
            }

            let schHtml = '';
            if (data.scheduled && data.scheduled.length > 0) {
                data.scheduled.forEach(m => {
                    let delBtn = m.is_owner ? `<button onclick="deleteMeetingHistory(${m.id}, 'scheduled')" class="btn-icon-small" style="color:#ef4444;" title="Delete"><i class="ri-delete-bin-line"></i></button>` : '';
                    schHtml += `<div class="data-card" style="margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                                    <div style="display: flex; gap: 24px; align-items: center;">
                                        <div style="text-align: center; border-right: 1px solid var(--border); padding-right: 24px; min-width: 90px;">
                                            <div style="font-size: 0.85rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em;">${m.month}</div>
                                            <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark); line-height: 1.1; margin-top: 4px;">${m.day}</div>
                                        </div>
                                        <div>
                                            <h4 style="font-weight: 700; font-size: 1.2rem; margin-bottom: 6px; color: var(--text-dark);">${escapeHTML(m.title)}</h4>
                                            <p style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500; margin-bottom: 12px;"><i class="ri-time-line" style="vertical-align: middle;"></i> ${m.formatted_date} &bull; ${m.meet_time}</p>
                                            <div style="display: flex; gap: 12px;">
                                                <button onclick="openEmbeddedMeeting('${m.meet_link}', 'video')" style="background: var(--primary); color: white; border: none; padding: 8px 24px; border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; transition: 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='none'">Join Meeting</button>
                                                <button onclick="navigator.clipboard.writeText('${m.meet_link}'); Swal.fire('Copied!', 'Link copied to clipboard', 'success');" class="btn-icon-small" title="Copy Link"><i class="ri-file-copy-line"></i></button>
                                                ${delBtn}
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 100px; height: 100px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="ri-calendar-event-fill" style="font-size: 2.5rem; color: var(--primary); opacity: 0.8;"></i>
                                    </div>
                                </div>`;
                });
                document.getElementById('scheduledMeetingsContainer').innerHTML = schHtml;
            } else {
                document.getElementById('scheduledMeetingsContainer').innerHTML = `<div class="data-card" style="text-align: center; padding: 40px;"><i class="ri-calendar-check-line" style="font-size: 3rem; color: var(--border); margin-bottom: 15px; display: block;"></i><p style="color: var(--text-muted); font-weight: 500;">No scheduled meetings coming up.</p></div>`;
            }
        }).catch(err => console.error(err));
    }


    // --- CALENDAR LOGIC ---
    let currentCalDate = new Date();
    let calViewMode = 'work_week'; 

    function toggleWeekendView(mode) { calViewMode = mode; renderCalendar(); }
    function shiftCalendarWeek(direction) { currentCalDate.setDate(currentCalDate.getDate() + (direction * 7)); renderCalendar(); }
    function resetCalendarToToday() { currentCalDate = new Date(); renderCalendar(); }
    
    function changeCalendarMonth(val) {
        if(!val) return;
        let parts = val.split('-');
        currentCalDate = new Date(parts[0], parts[1] - 1, 1);
        renderCalendar();
    }

    function renderCalendar() {
        let colsContainer = document.getElementById('calendarDayCols');
        colsContainer.innerHTML = ''; 

        let dayOfWeek = currentCalDate.getDay();
        let diff = currentCalDate.getDate() - dayOfWeek + (dayOfWeek == 0 ? -6 : 1);
        let startOfWeek = new Date(currentCalDate);
        startOfWeek.setDate(diff);

        let daysToRender = calViewMode === 'work_week' ? 5 : 7;
        let dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        let today = new Date();
        
        for (let i = 0; i < daysToRender; i++) {
            let renderDate = new Date(startOfWeek);
            renderDate.setDate(startOfWeek.getDate() + i);
            
            let isToday = (renderDate.getDate() === today.getDate() && renderDate.getMonth() === today.getMonth() && renderDate.getFullYear() === today.getFullYear());
            let activeClass = isToday ? 'active' : '';

            let colHtml = `
                <div class="day-col" id="cal-col-${i}">
                    <div class="day-header ${activeClass}">
                        <div class="day-num">${renderDate.getDate()}</div>
                        <div class="day-name">${dayNames[i]}</div>
                    </div>
            `;
            
            for(let j=0; j<24; j++) {
                let hourFormat = j === 0 ? 12 : (j > 12 ? j - 12 : j);
                let amPm = j < 12 ? 'AM' : 'PM';
                let timeString = `${String(hourFormat).padStart(2,'0')}:00 ${amPm}`;
                let dateString = `${renderDate.getFullYear()}-${String(renderDate.getMonth()+1).padStart(2,'0')}-${String(renderDate.getDate()).padStart(2,'0')}`;
                
                colHtml += `<div class="grid-cell" id="cell-${dateString}-${j}" onclick="openScheduleModal('${dateString}', '${timeString}')"></div>`;
            }
            colHtml += `</div>`;
            colsContainer.insertAdjacentHTML('beforeend', colHtml);
        }

        let monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        let midWeek = new Date(startOfWeek);
        midWeek.setDate(startOfWeek.getDate() + 3); 
        
        let monthInput = document.getElementById('calendarMonthPicker');
        monthInput.value = `${midWeek.getFullYear()}-${String(midWeek.getMonth()+1).padStart(2,'0')}`;
        document.getElementById('calendarMonthYear').innerHTML = `${monthNames[midWeek.getMonth()]} ${midWeek.getFullYear()} <i class="ri-arrow-down-s-line" style="font-size: 1rem; color: var(--text-muted);"></i>`;

        let endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + daysToRender - 1);

        let startStr = `${startOfWeek.getFullYear()}-${String(startOfWeek.getMonth()+1).padStart(2,'0')}-${String(startOfWeek.getDate()).padStart(2,'0')}`;
        let endStr = `${endOfWeek.getFullYear()}-${String(endOfWeek.getMonth()+1).padStart(2,'0')}-${String(endOfWeek.getDate()).padStart(2,'0')}`;

        let fd = new FormData();
        fd.append('action', 'get_calendar_events');
        fd.append('start_date', startStr);
        fd.append('end_date', endStr);

        fetch('backend.php', { method: 'POST', body: fd }).then(r=>r.json()).then(events => {
            if (!events || events.length === 0) return;
            
            events.forEach(ev => {
                let timeMatch = ev.meet_time.match(/(\d+):00 (AM|PM)/);
                if (timeMatch) {
                    let h = parseInt(timeMatch[1]);
                    let ampm = timeMatch[2];
                    let j = (h === 12 ? (ampm === 'AM' ? 0 : 12) : (ampm === 'PM' ? h + 12 : h));
                    
                    let targetCell = document.getElementById(`cell-${ev.meet_date}-${j}`);
                    if (targetCell) {
                        targetCell.innerHTML += `<div class="cal-event" onclick="event.stopPropagation(); openEmbeddedMeeting('${ev.meet_link}','video')" title="${ev.title}">${ev.title}</div>`;
                    }
                }
            });
        }).catch(err => console.log('Calendar fetch issue:', err));
    }

    function openScheduleModal(date, time) {
        document.getElementById('schDate').value = date;
        let selectOptions = document.getElementById('schTime').options;
        for(let i=0; i < selectOptions.length; i++) {
            if(selectOptions[i].value === time) {
                document.getElementById('schTime').selectedIndex = i;
                break;
            }
        }
        document.getElementById('scheduleMeetingModal').style.display='flex';
    }


    // --- EMOJI PICKER LOGIC ---
    const emojis = ['😀','😃','😄','😁','😆','😅','😂','🤣','🥲','☺️','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥸','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾'];

    function toggleEmojiPicker(e) {
        e.stopPropagation();
        let picker = document.getElementById('emojiPicker');
        if (!picker) return;
        if (picker.style.display === 'grid') {
            picker.style.display = 'none';
        } else {
            picker.style.display = 'grid';
            if (picker.innerHTML.trim() === '') {
                picker.innerHTML = emojis.map(emo => `<span onclick="insertEmoji('${emo}')">${emo}</span>`).join('');
            }
        }
    }

    function insertEmoji(emo) {
        let input = document.getElementById('msgInput');
        if(input) { input.value += emo; input.focus(); }
    }

    document.addEventListener('click', (e) => {
        let picker = document.getElementById('emojiPicker');
        if (picker && picker.style.display === 'grid' && !e.target.closest('#emojiPicker')) {
            picker.style.display = 'none';
        }
        document.querySelectorAll('.msg-dropdown, #chatOptionsDropdown').forEach(d => {
            if(!e.target.closest('.msg-menu-btn') && !e.target.closest('.header-actions')) {
                d.style.display = 'none';
            }
        });
    });

    // --- Meet Handlers ---
    document.getElementById('joinMeetId').addEventListener('input', function(e) {
        const btn = document.getElementById('joinMeetBtn');
        const err = document.getElementById('joinMeetError');
        if(e.target.value.trim().length > 0) {
            btn.style.background = 'var(--primary)'; btn.style.color = 'white'; err.style.display = 'none'; e.target.style.borderColor = 'var(--border)';
        } else {
            btn.style.background = 'var(--border)'; btn.style.color = 'var(--text-muted)';
        }
    });

    function createAndCopyMeetLink() {
        let title = document.getElementById('newMeetTitle').value || 'Meeting';
        let id = 'Workack-Meet-' + Math.random().toString(36).substring(2, 10);

        // Copy ONLY the Meeting ID to clipboard (Removed the 'video:' prefix)
        navigator.clipboard.writeText(id).then(() => {
            Swal.fire({
                title: 'Copied!', 
                text: 'Meeting ID copied to clipboard.', 
                icon: 'success', 
                confirmButtonColor: 'var(--primary)'
            }).then(() => {
                document.getElementById('createMeetingModal').style.display = 'none';
                let fd = new FormData();
                fd.append('action', 'save_instant_meeting'); 
                fd.append('title', title); 
                fd.append('link', id);
                fetch('backend.php', { method: 'POST', body: fd }).then(() => {
                    // Update the recent links list without starting the call
                    if (document.getElementById('meet_view').style.display !== 'none') { 
                        loadMeetingHistory(); 
                    }
                });
            });
        });
    }

    function joinManualMeet() {
        let input = document.getElementById('joinMeetId');
        let id = input.value.trim();
        if(!id) { input.style.borderColor = '#ef4444'; document.getElementById('joinMeetError').style.display = 'block'; return; }
        document.getElementById('joinMeetingModal').style.display = 'none';
        openEmbeddedMeeting(id, 'video');
    }

    function saveScheduledMeeting() {
        let title = document.getElementById('schTitle').value || 'Scheduled Meeting';
        let checkboxes = document.querySelectorAll('.sch-user-checkbox:checked');
        let selectedUsers = Array.from(checkboxes).map(cb => cb.value);
        let date = document.getElementById('schDate').value;
        let time = document.getElementById('schTime').value;
        
        if(selectedUsers.length === 0) { return Swal.fire('Error', 'Select at least one person to invite.', 'error'); }

        let fd = new FormData();
        fd.append('action', 'create_scheduled_meeting'); fd.append('title', title); fd.append('date', date); fd.append('time', time); fd.append('members', JSON.stringify(selectedUsers));
        
        fetch('backend.php', { method: 'POST', body: fd }).then(async r => {
            let text = await r.text();
            try {
                let data = JSON.parse(text);
                if(data.status === 'success') {
                    document.getElementById('scheduleMeetingModal').style.display = 'none';
                    Swal.fire({title: 'Success!', text: 'Meeting scheduled and invite sent via chat.', icon: 'success', confirmButtonColor: 'var(--primary)'});
                    
                    document.getElementById('schTitle').value = ''; checkboxes.forEach(cb => cb.checked = false); 
                    
                    if (document.getElementById('calendar_view').style.display !== 'none') { renderCalendar(); }
                    if (document.getElementById('meet_view').style.display !== 'none') { loadMeetingHistory(); }
                    
                    switchMainTab('chat_view', document.querySelectorAll('.sidebar-secondary-teams .nav-icon')[0]);
                    loadConversation(data.conversation_id);
                } else { Swal.fire('Error', 'Could not schedule meeting. ' + (data.message || ''), 'error'); }
            } catch(e) { Swal.fire('Error', 'Failed to save to database.', 'error'); }
        });
    }

    // --- Responsive Layout Logic ---
    function setupLayoutObserver() {
        const primarySidebar = document.querySelector('.sidebar-primary');
        const secondarySidebar = document.querySelector('.sidebar-secondary');
        const mainContent = document.getElementById('mainContent');
        if (!primarySidebar || !mainContent) return;

        const updateMargin = () => {
            if (window.innerWidth <= 992) {
                mainContent.style.marginLeft = '0'; mainContent.style.width = '100%'; return;
            }
            let totalWidth = primarySidebar.offsetWidth;
            if (secondarySidebar && secondarySidebar.classList.contains('open')) { totalWidth += secondarySidebar.offsetWidth; }
            mainContent.style.marginLeft = totalWidth + 'px';
            mainContent.style.width = `calc(100% - ${totalWidth}px)`;
        };

        new ResizeObserver(() => updateMargin()).observe(primarySidebar);
        if (secondarySidebar) { new MutationObserver(() => updateMargin()).observe(secondarySidebar, { attributes: true, attributeFilter: ['class'] }); }
        window.addEventListener('resize', updateMargin);
        updateMargin();
    }
    document.addEventListener('DOMContentLoaded', setupLayoutObserver);

    function toggleMobileSidebar() {
        if(window.innerWidth <= 992) {
            const sb = document.getElementById('chatSidebar');
            if(activeConvId) sb.classList.add('hide-mobile'); else sb.classList.remove('hide-mobile');
        }
    }
    window.addEventListener('resize', toggleMobileSidebar);

    // --- Core Chat Logic ---
    function loadSidebar() {
        if(isSidebarFetching) return;
        isSidebarFetching = true;

        let fd = new FormData();
        fd.append('action', 'get_recent_chats');
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            isSidebarFetching = false;
            let html = '';
            data.forEach(c => {
                let active = (c.conversation_id == activeConvId) ? 'active' : '';
                let unread = c.unread > 0 ? `<span style="background:var(--primary); color:white; font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:12px;">${c.unread}</span>` : '';
                let msgText = c.last_msg || '';
                
                html += `<div class="chat-item ${active}" onclick="loadConversation(${c.conversation_id})">
                            <div style="position:relative;">
                                <img src="${c.avatar}" class="avatar" loading="lazy" style="margin:0 12px 0 0;">
                                <span style="position:absolute; bottom:2px; right:10px; width:14px; height:14px; border:3px solid ${active ? 'var(--primary-light)' : 'var(--surface)'}; border-radius:50%; background-color:#22c55e;"></span>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                    <div class="chat-item-name" style="font-weight:600; color:var(--text-dark); font-size:0.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.name}</div>
                                    <div style="font-size:0.75rem; color:var(--text-muted); font-weight: 500;">${c.time}</div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:80%; font-size:0.85rem; color:var(--text-muted); ${msgText.includes('🚫') ? 'font-style:italic;' : ''}">${msgText}</span>
                                    ${unread}
                                </div>
                            </div>
                        </div>`;
            });
            if(data.length === 0) html = '<div style="text-align:center; padding: 40px; color:var(--text-muted);"><i class="ri-chat-1-line" style="font-size: 2rem; color: var(--border); display: block; margin-bottom: 10px;"></i>No active chats</div>';
            document.getElementById('chatList').innerHTML = html;
        }).catch(() => { isSidebarFetching = false; });
    }

    function loadConversation(convId) {
        if(activeConvId === convId) return; 
        
        activeConvId = convId; editingMsgId = null; lastFetchedMsgId = 0; 
        isUserScrolling = false; isGroupChat = false; 
        document.getElementById('groupInfoPanel').style.display = 'none';

        toggleMobileSidebar();
        document.getElementById('chatAreaEmpty').style.display = 'none';
        
        const activeArea = document.getElementById('chatAreaActive');
        activeArea.style.display = 'flex';

        // Initial Loading state
        document.getElementById('headerName').innerText = 'Loading...';
        document.getElementById('headerAvatar').src = '';
        document.getElementById('msgBox').innerHTML = '<div style="text-align:center; padding: 40px;"><i class="ri-loader-4-line ri-spin" style="font-size: 2rem; color: var(--primary);"></i></div>';
        
        switchInnerTab('chat');
        fetchMessages(true); 
    }
    
    // Set up global event listeners for the chat input ONCE when page loads
    document.addEventListener('DOMContentLoaded', () => {
        let msgInput = document.getElementById('msgInput');
        if(msgInput) {
            msgInput.addEventListener('input', function() {
                if(activeConvId) {
                    startTyping(); clearTimeout(typingTimer); typingTimer = setTimeout(stopTyping, 2000);
                }
            });
            msgInput.addEventListener('blur', stopTyping);
        }
    });

    // GROUP INFO PANEL LOGIC
    function toggleGroupInfo() {
        let panel = document.getElementById('groupInfoPanel');
        if (panel.style.display === 'flex') { panel.style.display = 'none'; } 
        else { panel.style.display = 'flex'; loadGroupMembers(); }
    }

    function closeGroupInfo() { document.getElementById('groupInfoPanel').style.display = 'none'; }

    function loadGroupMembers() {
        if(!activeConvId) return;
        let fd = new FormData(); fd.append('action', 'get_group_info'); fd.append('conversation_id', activeConvId);
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(members => {
            let html = '';
            members.forEach(m => {
                html += `<div style="display:flex; align-items:center; gap:12px; padding: 12px; border-bottom: 1px solid var(--border-light); border-radius: var(--radius-sm); transition: background 0.2s;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='transparent'">
                            <img src="${m.profile_img}" loading="lazy" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                            <div>
                                <div style="font-weight:700; font-size:0.95rem; color: var(--text-dark);">${escapeHTML(m.display_name)} ${m.id == <?php echo $my_id; ?> ? '<span style="color:var(--primary); font-weight: 500;">(You)</span>' : ''}</div>
                                <div style="font-size:0.8rem; color:var(--text-muted); font-weight: 500;">${escapeHTML(m.role)}</div>
                            </div>
                        </div>`;
            });
            document.getElementById('groupMembersList').innerHTML = html;
        });
    }

    function openAddMemberModal() {
        document.getElementById('addMemberModal').style.display = 'flex';
        selectedAddMembers.clear(); document.getElementById('addMemberSearch').value = ''; searchForAddMember('');
    }
    function closeAddMemberModal() { document.getElementById('addMemberModal').style.display = 'none'; }

    function searchForAddMember(val) {
        let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val); 
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedAddMembers.has(u.id) ? 'background:var(--primary-light); border-color:var(--primary);' : 'background:var(--surface); border-color:var(--border-light);';
                let icon = selectedAddMembers.has(u.id) ? '<i class="ri-checkbox-circle-fill" style="color:var(--primary); font-size:1.2rem;"></i>' : '<i class="ri-checkbox-blank-circle-line" style="color:var(--text-muted); font-size:1.2rem;"></i>';
                html += `<div onclick="toggleAddMember(${u.id}, this)" style="padding:12px 16px; display:flex; align-items:center; gap:16px; cursor:pointer; border:1px solid; border-radius:var(--radius-sm); margin-bottom:8px; transition:0.2s; ${isSel}">
                            ${icon}
                            <img src="${u.profile_img}" loading="lazy" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <div style="font-weight:600; font-size:0.95rem;">${escapeHTML(u.display_name)}</div>
                        </div>`;
            });
            document.getElementById('addMemberUserList').innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--text-muted); font-weight: 500;">No users found</div>';
        });
    }

    function toggleAddMember(uid, el) {
        if(selectedAddMembers.has(uid)) {
            selectedAddMembers.delete(uid);
            el.style.background = 'var(--surface)'; el.style.borderColor = 'var(--border-light)';
            el.querySelector('i').className = 'ri-checkbox-blank-circle-line'; el.querySelector('i').style.color = 'var(--text-muted)';
        } else {
            selectedAddMembers.add(uid);
            el.style.background = 'var(--primary-light)'; el.style.borderColor = 'var(--primary)';
            el.querySelector('i').className = 'ri-checkbox-circle-fill'; el.querySelector('i').style.color = 'var(--primary)';
        }
    }

    function submitAddMembers() {
        if(selectedAddMembers.size === 0) return Swal.fire('Wait', 'Select at least 1 member to add.', 'warning');
        if(!activeConvId) return;

        let fd = new FormData();
        fd.append('action', 'add_members_to_group'); fd.append('conversation_id', activeConvId); fd.append('members', JSON.stringify(Array.from(selectedAddMembers)));
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if (data.status === 'success') { closeAddMemberModal(); loadGroupMembers(); fetchMessages(false); }
        });
    }

    // --- File Upload Staging Logic ---
    function queueFile(input) {
        if(input.files.length > 0) {
            document.getElementById('filePreview').style.display = 'flex';
            document.getElementById('filePreviewName').innerText = input.files[0].name;
            document.getElementById('msgInput').focus();
        }
    }
    function clearFile() {
        let input = document.getElementById('fileUpload'); input.value = ''; document.getElementById('filePreview').style.display = 'none';
    }

    function switchInnerTab(tabName) {
        const navItems = document.querySelectorAll('.header-nav-item');
        navItems.forEach(item => item.classList.remove('active'));
        
        document.getElementById('chatMessagesContainer').style.display = 'none';
        document.getElementById('chatFilesContainer').style.display = 'none';
        document.getElementById('chatPhotosContainer').style.display = 'none';
        
        if (tabName === 'chat') {
            navItems[0].classList.add('active');
            document.getElementById('chatMessagesContainer').style.display = 'flex';
            let box = document.getElementById('msgBox'); box.scrollTo({ top: box.scrollHeight });
        } else if (tabName === 'files') {
            navItems[1].classList.add('active'); document.getElementById('chatFilesContainer').style.display = 'flex';
        } else if (tabName === 'photos') {
            navItems[2].classList.add('active'); document.getElementById('chatPhotosContainer').style.display = 'flex';
        }
    }

    function addPhotoToGallery(path, id) {
        let emptyState = document.getElementById('photosEmptyState');
        let grid = document.getElementById('photosGrid');
        
        if(emptyState) emptyState.style.display = 'none';
        if(grid) {
            grid.style.display = 'grid';
            if(!document.getElementById('gallery-img-'+id)) {
                grid.insertAdjacentHTML('beforeend', `<div id="gallery-img-${id}" style="aspect-ratio: 1; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border); box-shadow: var(--shadow-sm);"><img src="${path}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'" onclick="window.open('${path}', '_blank')"></div>`);
            }
        }
    }

    function addFileToGallery(path, id, name) {
        let emptyState = document.getElementById('filesEmptyState');
        let content = document.getElementById('filesContent');
        let list = document.getElementById('filesList');
        
        if(emptyState) emptyState.style.display = 'none';
        if(content) content.style.display = 'flex';
        
        if(list && !document.getElementById('file-item-'+id)) {
            let safeName = name ? escapeHTML(name) : 'Document';
            if(safeName.includes('🚫')) return; 
            list.insertAdjacentHTML('beforeend', `<a href="${path}" target="_blank" id="file-item-${id}" style="display:flex; align-items:center; gap:20px; padding:20px; border:1px solid var(--border); border-radius:var(--radius-md); background:var(--surface); text-decoration:none; color:var(--text-dark); transition:all 0.2s; box-shadow: var(--shadow-sm);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='var(--shadow-md)'; this.style.borderColor='var(--primary)'" onmouseout="this.style.transform='none'; this.style.boxShadow='var(--shadow-sm)'; this.style.borderColor='var(--border)'">
                <div style="width:48px; height:48px; border-radius:12px; background:var(--primary-light); display:flex; align-items:center; justify-content:center;">
                    <i class="ri-file-text-fill" style="font-size:1.8rem; color:var(--primary);"></i>
                </div>
                <div style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; font-size:1.05rem;">${safeName}</div>
                <div style="width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; background: var(--hover-bg);">
                    <i class="ri-download-2-fill" style="color:var(--text-muted); font-size:1.2rem;"></i>
                </div>
            </a>`);
        }
    }

    function backToList() {
        activeConvId = null; toggleMobileSidebar();
        document.getElementById('chatAreaEmpty').style.display = 'flex';
        document.getElementById('chatAreaActive').style.display = 'none';
        document.getElementById('groupInfoPanel').style.display = 'none';
    }

    function handleScroll() { let box = document.getElementById('msgBox'); isUserScrolling = (box.scrollHeight - box.scrollTop - box.clientHeight > 50); }

    function buildMessageHTML(m) {
        let cls = m.is_me ? 'outgoing' : 'incoming';
        let content = m.message;
        let isDeleted = m.is_deleted;
        let isEdited = m.is_edited;
        
        let metaHtml = `<div class="msg-meta">`;
        if (isEdited && !isDeleted) metaHtml += `<span style="font-size:0.7rem; font-style:italic; margin-right:6px;">Edited</span>`;
        metaHtml += `<span>${m.time}</span>`;
        if (m.is_me && !isDeleted) {
            let tickClass = (m.read_status === 2) ? 'tick-read' : 'tick-sent';
            let tickMark = (m.read_status === 2) ? '<i class="ri-check-double-line"></i>' : '<i class="ri-check-line"></i>';
            metaHtml += `<span class="ticks ${tickClass}">${tickMark}</span>`;
        }
        metaHtml += `</div>`;

        let menuHtml = '';
        if (m.is_me && !isDeleted && m.message_type === 'text') {
            menuHtml = `
                <button class="msg-menu-btn" onclick="toggleMsgMenu(event, ${m.id})"><i class="ri-arrow-down-s-line"></i></button>
                <div class="msg-dropdown" id="msg-drop-${m.id}">
                    <button onclick="initEdit(${m.id}, '${escapeHTML(m.message)}')"><i class="ri-pencil-fill mr-2" style="color: var(--text-muted); margin-right: 8px;"></i> Edit Message</button>
                    <button onclick="deleteMessage(${m.id})" class="delete-btn"><i class="ri-delete-bin-fill mr-2" style="margin-right: 8px;"></i> Delete</button>
                </div>
            `;
        } else if (m.is_me && !isDeleted) {
            menuHtml = `
                <button class="msg-menu-btn" onclick="toggleMsgMenu(event, ${m.id})"><i class="ri-arrow-down-s-line"></i></button>
                <div class="msg-dropdown" id="msg-drop-${m.id}">
                    <button onclick="deleteMessage(${m.id})" class="delete-btn"><i class="ri-delete-bin-fill mr-2" style="margin-right: 8px;"></i> Delete</button>
                </div>
            `;
        }

        let innerMsg = '';
        if (isDeleted) {
            innerMsg = `<div class="msg deleted" id="msg-content-${m.id}"><i class="ri-forbid-line" style="vertical-align: middle; margin-right: 4px;"></i> ${content}</div>`;
        } else if (m.message_type === 'call') {
            let raw = m.message;
            let callType = raw.startsWith('audio:') ? 'audio' : 'video';
            let meetId = raw.replace(/^(audio|video):/i, '');
            let label = callType === 'audio' ? 'Voice Call' : 'Video Meeting';
            innerMsg = `<div class="msg ${cls}" id="msg-content-${m.id}" style="text-align:center; min-width: 220px; padding: 20px;">
                            <div style="background:var(--surface); border: 1px solid var(--border); padding:16px; border-radius:var(--radius-md); margin-bottom:12px; box-shadow: var(--shadow-sm);">
                                <i class="${callType==='audio'?'ri-phone-fill':'ri-vidicon-fill'}" style="font-size:2rem; color:var(--primary); margin-bottom: 8px; display: inline-block;"></i>
                                <br><strong style="font-size:1rem; color: var(--text-dark);">${label}</strong>
                            </div>
                            <button onclick="openEmbeddedMeeting('${meetId}','${callType}')" class="btn-primary" style="font-size: 0.9rem; padding: 10px;">Join Now</button>
                            ${metaHtml}
                        </div>`;
        } else {
            if(m.message_type === 'image') content = `<img src="${m.attachment_path}" loading="lazy" style="max-width:100%; border-radius:8px; margin-bottom:8px; border: 1px solid var(--border-light);">`;
            else if(m.message_type === 'file') content = `<a href="${m.attachment_path}" target="_blank" style="display:flex; align-items:center; gap:12px; color:inherit; text-decoration:none; background:rgba(0,0,0,0.04); padding:12px; border-radius:8px; border: 1px solid rgba(0,0,0,0.05); transition: 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.08)'" onmouseout="this.style.background='rgba(0,0,0,0.04)'"><div style="width: 32px; height: 32px; background: white; border-radius: 6px; display: flex; align-items: center; justify-content: center;"><i class="ri-file-text-fill" style="font-size: 1.2rem; color: var(--primary);"></i></div> <span style="word-break: break-all; font-weight: 600;">${escapeHTML(m.message)}</span></a>`;
            else if(m.message.includes('video:')) {
                let meetParts = m.message.split('video:'); let plainText = meetParts[0]; let meetId = meetParts[1];
                content = `${plainText}<button onclick="openEmbeddedMeeting('${meetId}','video')" class="btn-primary" style="margin-top:12px; font-size: 0.9rem;">Join Meeting</button>`;
            }
            let senderName = (!m.is_me && m.display_name) ? `<div style="font-size:0.8rem;color:var(--primary);margin-bottom:6px;font-weight:700;">${m.display_name}</div>` : '';
            innerMsg = `<div class="msg ${cls}" id="msg-content-${m.id}">${senderName}<span id="msg-text-${m.id}" style="display: block;">${content}</span>${metaHtml}${menuHtml}</div>`;
        }
        return `<div class="msg-wrapper ${cls}" id="msg-${m.id}" data-id="${m.id}">${innerMsg}</div>`;
    }

    function toggleMsgMenu(e, msgId) {
        e.stopPropagation(); let drop = document.getElementById('msg-drop-' + msgId);
        if (drop) {
            let isVisible = drop.style.display === 'block';
            document.querySelectorAll('.msg-dropdown').forEach(d => d.style.display = 'none');
            drop.style.display = isVisible ? 'none' : 'block';
        }
    }

    function fetchMessages(isInitialLoad = false) {
        if(!activeConvId || isFetchingMessages) return;
        isFetchingMessages = true;
        let fd = new FormData(); fd.append('action', 'get_messages'); fd.append('conversation_id', activeConvId); fd.append('last_msg_id', lastFetchedMsgId);

        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            isFetchingMessages = false;
            let msgs = data.messages; let info = data.info; let box = document.getElementById('msgBox');
            if(!box) return;

            if(info && isInitialLoad) {
                if(box.innerHTML.includes('ri-loader-4-line')) box.innerHTML = '';
                document.getElementById('headerAvatar').src = info.profile_img;
                document.getElementById('headerName').innerText = info.display_name;
                
                let infoBtn = document.getElementById('headerInfoBtn');
                if (info.is_group) { infoBtn.style.display = 'flex'; isGroupChat = true; } 
                else { infoBtn.style.display = 'none'; isGroupChat = false; document.getElementById('groupInfoPanel').style.display = 'none'; }
            }

            let typingDiv = document.getElementById('typingIndicator');
            if(data.typing && data.typing.length > 0) { typingDiv.textContent = data.typing.join(', ') + ' is typing...'; } else { typingDiv.textContent = ''; }

            // 1. ADD NEW MESSAGES
            if (msgs.length > 0) {
                msgs.forEach(m => {
                    lastFetchedMsgId = Math.max(lastFetchedMsgId, m.id);
                    let existingMsg = document.getElementById(`msg-${m.id}`);
                    if (existingMsg) { existingMsg.outerHTML = buildMessageHTML(m); } else { box.insertAdjacentHTML('beforeend', buildMessageHTML(m)); }
                    
                    if (m.message_type === 'image' && !m.is_deleted) { addPhotoToGallery(m.attachment_path, m.id); } 
                    else if (m.message_type === 'file' && !m.is_deleted) { addFileToGallery(m.attachment_path, m.id, m.message); }
                });
                if(!isUserScrolling) box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
            } else if (isInitialLoad) {
                box.innerHTML = '<div style="text-align:center; padding:60px 20px; color:var(--text-muted);"><div style="width: 80px; height: 80px; background: var(--border-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;"><i class="ri-chat-smile-2-fill" style="font-size: 2.5rem; color: var(--text-muted);"></i></div><h4 style="font-weight: 700; color: var(--text-dark); margin-bottom: 8px;">Start of conversation</h4><p style="font-size: 0.95rem;">Send a message to break the ice!</p></div>';
            }

            // 2. LIVE DOUBLE TICK (READ RECEIPTS) UPDATE
            if (data.read_ids && data.read_ids.length > 0) {
                data.read_ids.forEach(id => {
                    let msgEl = document.getElementById(`msg-${id}`);
                    if (msgEl) {
                        let tickSpan = msgEl.querySelector('.ticks.tick-sent'); 
                        if (tickSpan) {
                            tickSpan.className = 'ticks tick-read'; 
                            tickSpan.innerHTML = '<i class="ri-check-double-line"></i>'; 
                        }
                    }
                });
            }
            
        }).catch(() => { isFetchingMessages = false; });
    }

    function submitMessage() {
        let input = document.getElementById('msgInput'); let txt = input.value.trim(); let fileInput = document.getElementById('fileUpload');
        if(!txt && fileInput.files.length === 0) return;

        let fd = new FormData();
        if (editingMsgId) {
            fd.append('action', 'edit_message'); fd.append('message_id', editingMsgId); fd.append('new_text', txt);
            cancelEdit(); lastFetchedMsgId = 0; document.getElementById('msgBox').innerHTML = ''; 
        } else {
            fd.append('action', 'send_message'); fd.append('conversation_id', activeConvId); fd.append('message', txt);
            if (fileInput.files.length > 0) { fd.append('file', fileInput.files[0]); } else { fd.append('type', 'text'); }
            
            let box = document.getElementById('msgBox');
            let displayTxt = fileInput.files.length > 0 ? "<i class='ri-loader-4-line ri-spin'></i> Uploading file..." : escapeHTML(txt);
            box.insertAdjacentHTML('beforeend', `<div class="msg-wrapper outgoing temp-pending-msg"><div class="msg outgoing" style="opacity:0.7;">${displayTxt} <div class="msg-meta"><i class="ri-time-line"></i></div></div></div>`);
            box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
        }

        fetch('backend.php', { method: 'POST', body: fd }).then(async (r) => {
            let res = await r.json();
            if(res.status === 'error') { Swal.fire('Error', res.message, 'error'); }
            document.querySelectorAll('.temp-pending-msg').forEach(el => el.remove());
            input.value = ''; clearFile(); fetchMessages(false); loadSidebar();
        });
        input.value = ''; stopTyping();
    }

    function initEdit(id, text) {
        editingMsgId = id; document.getElementById('msgInput').value = text;
        document.getElementById('msgInput').focus(); document.getElementById('editModeBar').style.display = 'flex';
    }
    function cancelEdit() {
        editingMsgId = null; document.getElementById('msgInput').value = ''; document.getElementById('editModeBar').style.display = 'none';
    }

    function deleteMessage(id) {
        Swal.fire({
            title: 'Delete Message?', text: "This will delete the message for everyone.", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Delete'
        }).then((res) => {
            if(res.isConfirmed) {
                let fd = new FormData(); fd.append('action', 'delete_message'); fd.append('message_id', id);
                fetch('backend.php', { method: 'POST', body: fd }).then(() => {
                    lastFetchedMsgId = 0; document.getElementById('msgBox').innerHTML = '';
                    let grid = document.getElementById('photosGrid'); if(grid) grid.innerHTML = '';
                    let emptyState = document.getElementById('photosEmptyState'); if(emptyState) emptyState.style.display = 'flex';
                    let fGrid = document.getElementById('filesList'); if(fGrid) fGrid.innerHTML = '';
                    let fEmpty = document.getElementById('filesEmptyState'); if(fEmpty) fEmpty.style.display = 'flex';
                    let fContent = document.getElementById('filesContent'); if(fContent) fContent.style.display = 'none';
                    fetchMessages(false);
                });
            }
        });
    }

    function toggleHeaderMenu(e) {
        e.stopPropagation(); let menu = document.getElementById('chatOptionsDropdown');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    function clearDeleteChat(type) {
        let msg = type === 'clear' ? "Clear all messages in this chat?" : "Delete this conversation?";
        Swal.fire({
            title: 'Are you sure?', text: msg, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes'
        }).then((res) => {
            if(res.isConfirmed) {
                let fd = new FormData(); fd.append('action', type + '_chat'); fd.append('conversation_id', activeConvId);
                fetch('backend.php', { method: 'POST', body: fd }).then(() => {
                    activeConvId = null;
                    document.getElementById('chatAreaEmpty').style.display = 'flex';
                    document.getElementById('chatAreaActive').style.display = 'none';
                    document.getElementById('groupInfoPanel').style.display = 'none';
                    loadSidebar();
                });
            }
        });
    }

    function startSmartPolling() {
        if(masterPollInterval) clearInterval(masterPollInterval);
        masterPollInterval = setInterval(() => {
            if (!document.hidden) { checkIncomingCalls(); loadSidebar(); if (activeConvId) { fetchMessages(false); } }
        }, 15000); 
    }

    function checkIncomingCalls() {
        if(currentIncomingCall) return;
        let fd = new FormData(); fd.append('action', 'check_incoming_call'); fd.append('conversation_id', activeConvId || 0);
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.has_call && !currentIncomingCall) {
                currentIncomingCall = data.call;
                document.getElementById('incomingCallerAvatar').src = data.call.caller_avatar;
                document.getElementById('incomingCallerName').textContent = data.call.display_label;
                document.getElementById('incomingCallLabel').textContent = data.call.call_type === 'audio' ? 'Incoming Voice Call' : 'Incoming Video Call';
                document.getElementById('incomingCallModal').style.display = 'flex';
            }
        }).catch(() => {});
    }

    function openGroupModal() { 
        document.getElementById('groupModal').style.display = 'flex'; selectedMembers.clear(); 
        document.getElementById('groupName').value = ''; document.getElementById('memberSearch').value = ''; searchForGroup(''); 
    }
    function closeGroupModal() { document.getElementById('groupModal').style.display = 'none'; }

    function searchForGroup(val) {
        let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val); 
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            let html = '';
            data.forEach(u => {
                let isSel = selectedMembers.has(u.id) ? 'background:var(--primary-light); border-color:var(--primary);' : 'background:var(--surface); border-color:var(--border-light);';
                let icon = selectedMembers.has(u.id) ? '<i class="ri-checkbox-circle-fill" style="color:var(--primary); font-size:1.2rem;"></i>' : '<i class="ri-checkbox-blank-circle-line" style="color:var(--text-muted); font-size:1.2rem;"></i>';
                html += `<div onclick="toggleMember(${u.id}, this)" style="padding:12px 16px; display:flex; align-items:center; gap:16px; cursor:pointer; border:1px solid; border-radius:var(--radius-sm); margin-bottom:8px; transition:0.2s; ${isSel}">
                            ${icon}
                            <img src="${u.profile_img}" loading="lazy" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                            <div style="font-weight:600; font-size:0.95rem;">${escapeHTML(u.display_name)}</div>
                        </div>`;
            });
            document.getElementById('groupUserList').innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--text-muted); font-weight: 500;">No users found</div>';
        });
    }

    function toggleMember(uid, el) {
        if(selectedMembers.has(uid)) {
            selectedMembers.delete(uid);
            el.style.background = 'var(--surface)'; el.style.borderColor = 'var(--border-light)';
            el.querySelector('i').className = 'ri-checkbox-blank-circle-line'; el.querySelector('i').style.color = 'var(--text-muted)';
        } else {
            selectedMembers.add(uid);
            el.style.background = 'var(--primary-light)'; el.style.borderColor = 'var(--primary)';
            el.querySelector('i').className = 'ri-checkbox-circle-fill'; el.querySelector('i').style.color = 'var(--primary)';
        }
    }

    function createGroup() {
        let name = document.getElementById('groupName').value.trim();
        if(!name || selectedMembers.size === 0) return Swal.fire('Wait', 'Group name and at least 1 member required.', 'warning');
        
        let fd = new FormData(); fd.append('action', 'create_group'); fd.append('group_name', name); fd.append('members', JSON.stringify(Array.from(selectedMembers)));
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if (data.status === 'success') { closeGroupModal(); loadConversation(data.conversation_id); }
        });
    }

    document.getElementById('userSearch').addEventListener('input', function(e) {
        let val = e.target.value.trim(); let results = document.getElementById('searchResults');
        results.style.display = val.length < 1 ? 'none' : 'block'; if (val.length < 1) return;
        
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            let fd = new FormData(); fd.append('action', 'search_users'); fd.append('term', val);
            fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                let html = data.map(u => `<div class="search-item" onclick="startChat(${u.id});"><img src="${u.profile_img}" loading="lazy" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:700; font-size:0.95rem;">${escapeHTML(u.display_name)}</div><div style="font-size:0.8rem;color:var(--text-muted);">${escapeHTML(u.role)}</div></div></div>`).join('');
                results.innerHTML = html || '<div style="padding:20px;text-align:center;color:var(--text-muted); font-weight: 500;">No users found</div>';
            });
        }, 300);
    });

    function startChat(userId) {
        document.getElementById('searchResults').style.display = 'none'; document.getElementById('userSearch').value = '';
        let fd = new FormData(); fd.append('action', 'start_chat'); fd.append('target_user_id', userId);
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => { 
            if(data.id) { switchMainTab('chat_view', document.querySelectorAll('.sidebar-secondary-teams .nav-icon')[0]); loadConversation(data.id); } 
        });
    }

    function startCall(type) {
        if(!activeConvId) return;
        let fd = new FormData(); fd.append('action', 'start_call'); fd.append('conversation_id', activeConvId); fd.append('call_type', type);
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            if(data.status === 'ok') { openEmbeddedMeeting(data.room_id, type); lastFetchedMsgId = 0; fetchMessages(false); }
        });
    }

    function openEmbeddedMeeting(roomId, type = 'video') {
        let overlay = document.getElementById('videoOverlay'); overlay.style.display = 'flex';
        document.getElementById('callTypeLabel').textContent = type === 'audio' ? 'Voice Call' : 'Video Meeting';
        
        if(jitsiApi) jitsiApi.dispose();
        
        const options = {
            roomName: roomId, width: '100%', height: '100%', parentNode: document.getElementById('jitsiContainer'),
            configOverwrite: { 
                startWithVideoMuted: type === 'audio', startAudioOnly: type === 'audio',
                prejoinPageEnabled: true, disableDeepLinking: true            
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false, SHOW_WATERMARK_FOR_GUESTS: false, SHOW_BRAND_WATERMARK: false, SHOW_PROMOTIONAL_CLOSE_PAGE: false,
                DEFAULT_LOGO_URL: '', DEFAULT_WELCOME_PAGE_LOGO_URL: '', APP_NAME: 'Workack Call', NATIVE_APP_NAME: 'Workack Call', PROVIDER_NAME: 'Workack', HIDE_INVITE_MORE_HEADER: true
            },
            userInfo: { displayName: myUserName }
        };
        jitsiApi = new JitsiMeetExternalAPI('meet.jit.si', options);
    }

    function closeCall() {
        if(jitsiApi) { jitsiApi.dispose(); jitsiApi = null; }
        document.getElementById('videoOverlay').style.display = 'none';
        let fd = new FormData(); fd.append('action', 'end_call_request'); fd.append('conversation_id', activeConvId);
        fetch('backend.php', { method: 'POST', body: fd });
    }

    function acceptIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData(); fd.append('action', 'answer_call'); fd.append('call_id', currentIncomingCall.id);
        fetch('backend.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
            document.getElementById('incomingCallModal').style.display = 'none';
            if(data.room_id) {
                if(activeConvId != data.conversation_id) loadConversation(data.conversation_id);
                openEmbeddedMeeting(data.room_id, data.call_type);
            }
            currentIncomingCall = null;
        });
    }

    function declineIncomingCall() {
        if(!currentIncomingCall) return;
        let fd = new FormData(); fd.append('action', 'decline_call'); fd.append('call_id', currentIncomingCall.id);
        fetch('backend.php', { method: 'POST', body: fd });
        document.getElementById('incomingCallModal').style.display = 'none'; currentIncomingCall = null;
    }

    function startTyping() {
        let fd = new FormData(); fd.append('action', 'start_typing'); fd.append('conversation_id', activeConvId); fetch('backend.php', { method: 'POST', body: fd });
    }
    function stopTyping() {
        clearTimeout(typingTimer); if(!activeConvId) return;
        let fd = new FormData(); fd.append('action', 'stop_typing'); fd.append('conversation_id', activeConvId); fetch('backend.php', { method: 'POST', body: fd });
    }

    function escapeHTML(str) { return str.replace(/[&<>'"]/g, tag => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[tag])); }
    
    function openNewScheduleModal() {
        document.getElementById('schTitle').value = ''; document.getElementById('schDetails').value = '';
        document.querySelectorAll('.sch-user-checkbox').forEach(cb => cb.checked = false);

        let now = new Date(); let yyyy = now.getFullYear(); let mm = String(now.getMonth() + 1).padStart(2, '0'); let dd = String(now.getDate()).padStart(2, '0');
        document.getElementById('schDate').value = `${yyyy}-${mm}-${dd}`;

        let nextHour = now.getHours() + 1; if (nextHour > 23) nextHour = 0; 
        let ampm = nextHour < 12 ? 'AM' : 'PM';
        let displayHour = nextHour === 0 ? 12 : (nextHour > 12 ? nextHour - 12 : nextHour);
        let timeString = `${String(displayHour).padStart(2, '0')}:00 ${ampm}`;

        let selectOptions = document.getElementById('schTime').options;
        for(let i=0; i < selectOptions.length; i++) { if(selectOptions[i].value === timeString) { document.getElementById('schTime').selectedIndex = i; break; } }
        document.getElementById('scheduleMeetingModal').style.display = 'flex';
    }

    // Init Page
    loadSidebar(); startSmartPolling();
</script>