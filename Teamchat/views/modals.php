<div id="createMeetingModal" class="modal-overlay">
    <div class="modal" style="width: 400px; padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <h3 style="font-size:1.3rem; font-weight: 700; color: var(--text-dark);">Instant Meeting</h3>
            <button class="btn-icon-small" onclick="document.getElementById('createMeetingModal').style.display='none'" style="border:none;"><i class="ri-close-line" style="font-size: 1.5rem;"></i></button>
        </div>
        <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Meeting Title</label>
        <input type="text" id="newMeetTitle" value="Meeting with <?php echo htmlspecialchars($my_username); ?>" style="margin-bottom:24px;">
        <button class="btn-primary" onclick="createAndCopyMeetLink()"><i class="ri-links-line" style="vertical-align: middle; margin-right: 5px;"></i> Create & Copy Link</button>
    </div>
</div>

<div id="joinMeetingModal" class="modal-overlay">
    <div class="modal" style="width: 400px; padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <h3 style="font-size:1.3rem; font-weight: 700; color: var(--text-dark);">Join Meeting</h3>
            <button class="btn-icon-small" onclick="document.getElementById('joinMeetingModal').style.display='none'" style="border:none;"><i class="ri-close-line" style="font-size: 1.5rem;"></i></button>
        </div>
        <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Meeting ID <span style="color:#ef4444;">*</span></label>
        <input type="text" id="joinMeetId" placeholder="e.g. Workack-Meet-xyz123" style="margin-bottom:8px;">
        <p id="joinMeetError" style="color: #ef4444; font-size: 0.85rem; margin-bottom: 20px; display: none; font-weight: 500;"><i class="ri-error-warning-fill"></i> Meeting ID is required</p>
        
        <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Passcode (Optional)</label>
        <input type="text" placeholder="Enter passcode if any" style="margin-bottom:30px;">
        
        <button class="btn-primary" onclick="joinManualMeet()" id="joinMeetBtn" style="background:var(--border); color:var(--text-muted);"><i class="ri-login-box-line" style="vertical-align: middle; margin-right: 5px;"></i> Join Meeting</button>
    </div>
</div>

<div id="scheduleMeetingModal" class="modal-overlay">
    <div class="modal" style="width: 850px; max-width: 95vw; padding: 0; overflow: hidden; display: flex; flex-direction: column; max-height: 90vh;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding: 20px 32px; border-bottom: 1px solid var(--border); background: var(--surface);">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="width: 40px; height: 40px; background: var(--primary-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                    <i class="ri-calendar-event-fill" style="color: var(--primary); font-size: 1.4rem;"></i>
                </div>
                <h3 style="font-size:1.3rem; font-weight: 700; color: var(--text-dark);">Schedule Meeting</h3>
            </div>
            <div style="display:flex; gap: 12px;">
                <button onclick="document.getElementById('scheduleMeetingModal').style.display='none'" style="background:var(--surface); border:1px solid var(--border); padding:10px 20px; border-radius:var(--radius-sm); font-weight:600; cursor:pointer; color: var(--text-dark); transition: 0.2s;">Cancel</button>
                <button class="btn-primary" style="width: auto; padding: 10px 24px;" onclick="saveScheduledMeeting()">Save & Send Invites</button>
            </div>
        </div>
        <div style="padding: 32px; background: var(--bg-light); overflow-y: auto; flex: 1;">
            <input type="text" id="schTitle" placeholder="Add meeting title..." style="width:100%; padding:15px 20px; border:1px solid var(--border); border-radius: var(--radius-md); font-size: 1.4rem; font-weight: 600; outline:none; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
            
            <div style="display: flex; gap: 24px; margin-bottom: 24px;">
                <div style="flex: 1;">
                    <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Date</label>
                    <input type="date" id="schDate" value="<?php echo date('Y-m-d'); ?>" style="box-shadow: var(--shadow-sm);">
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Time</label>
                    <select id="schTime" style="box-shadow: var(--shadow-sm);">
                        <?php 
                        for ($h = 0; $h < 24; $h++) {
                            $hf = $h == 0 ? 12 : ($h > 12 ? $h - 12 : $h);
                            $ap = $h < 12 ? 'AM' : 'PM';
                            $tsStr = sprintf("%02d:00 %s", $hf, $ap);
                            echo "<option value=\"$tsStr\">$tsStr</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
                <p style="font-size: 0.95rem; color: var(--text-dark); margin-bottom: 16px; font-weight: 700;">Add Attendees <span style="font-weight:400; color:var(--text-muted); font-size:0.85rem;">(Select people to invite)</span></p>
                <div id="schUserList" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; max-height: 200px; overflow-y: auto; padding-right: 10px;">
                    <?php foreach($all_users as $u): if($u['id'] != $my_id): ?>
                        <label style="display: flex; align-items: center; cursor: pointer; font-size: 0.95rem; padding: 10px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-light); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary)'; this.style.background='var(--primary-light)'" onmouseout="this.style.borderColor='var(--border-light)'; this.style.background='transparent'">
                            <input type="checkbox" class="sch-user-checkbox" value="<?php echo $u['id']; ?>" style="margin-right: 12px; width: 18px; height: 18px; accent-color: var(--primary);">
                            <img src="<?php echo $u['profile_img']; ?>" loading="lazy" style="width: 32px; height: 32px; border-radius: 50%; margin-right: 12px; object-fit:cover;">
                            <div style="display: flex; flex-direction: column;">
                                <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($u['name']); ?></span> 
                                <span style="color:var(--text-muted); font-size:0.8rem;"><?php echo htmlspecialchars($u['role']); ?></span>
                            </div>
                        </label>
                    <?php endif; endforeach; ?>
                </div>
            </div>
            
            <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Meeting Description / Agenda</label>
            <textarea id="schDetails" placeholder="Type details for this new meeting..." style="height:120px; resize:vertical; box-shadow: var(--shadow-sm);"></textarea>
        </div>
    </div>
</div>

<div id="videoOverlay">
    <div class="video-overlay-header">
        <h3 style="margin:0; font-size:1.2rem; display:flex; align-items:center; gap:12px; font-weight: 600;">
            <i class="ri-record-circle-fill" style="color:#ef4444; font-size:1.1rem; animation: pulse 1.5s infinite;"></i> <span id="callTypeLabel">Live Meeting</span>
        </h3>
        <button onclick="closeCall()" style="background:#ef4444; border:none; color:white; padding:8px 20px; border-radius:var(--radius-sm); cursor:pointer; font-weight:700; display:flex; align-items:center; gap:8px; font-size: 1rem; transition: background 0.2s;">
            <i class="ri-phone-x-fill"></i> End Call
        </button>
    </div>
    <div id="jitsiContainer" style="flex:1;"></div>
</div>

<div id="incomingCallModal" class="modal-overlay">
    <div class="incoming-call-box">
        <div style="position: relative; width: 100px; height: 100px; margin: 0 auto 20px;">
            <img id="incomingCallerAvatar" src="" style="width:100%; height:100%; border-radius:50%; object-fit:cover; border:4px solid var(--primary); box-shadow: var(--shadow-md);">
            <div style="position: absolute; bottom: 0; right: 0; width: 24px; height: 24px; background: #22c55e; border-radius: 50%; border: 3px solid var(--surface);"></div>
        </div>
        <h3 id="incomingCallerName" style="font-size:1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom:8px;">Incoming Call</h3>
        <p id="incomingCallLabel" style="color:var(--text-muted); font-size:1rem; font-weight: 500;">Video Call</p>
        <div style="display:flex; gap:24px; justify-content:center; margin-top:35px;">
            <button onclick="declineIncomingCall()" style="background:#ef4444; color:white; width:60px; height:60px; border-radius:50%; border:none; cursor:pointer; font-size:1.8rem; box-shadow: var(--shadow-md); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"><i class="ri-phone-fill" style="transform: rotate(135deg); display: inline-block;"></i></button>
            <button onclick="acceptIncomingCall()" style="background:#22c55e; color:white; width:60px; height:60px; border-radius:50%; border:none; cursor:pointer; font-size:1.8rem; box-shadow: var(--shadow-md); animation: jump 1s infinite;"><i class="ri-phone-fill"></i></button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="groupModal">
    <div class="modal" style="width: 450px; padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <h3 style="font-size:1.4rem; font-weight: 700; color: var(--text-dark);">Create Group</h3>
            <button class="btn-icon-small" style="border:none;" onclick="closeGroupModal()"><i class="ri-close-line" style="font-size:1.5rem;"></i></button>
        </div>
        <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Group Subject</label>
        <input type="text" id="groupName" placeholder="e.g. Marketing Team" style="margin-bottom:20px;">
        
        <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 8px; display: block;">Add Members</label>
        <div style="position: relative;">
            <i class="ri-search-line" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" id="memberSearch" placeholder="Search people..." oninput="searchForGroup(this.value)" style="padding-left: 40px; margin-bottom: 12px;">
        </div>
        
        <div id="groupUserList" style="max-height:250px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-md); margin-bottom:24px; padding: 5px;"></div>
        
        <button class="btn-primary" onclick="createGroup()">Create Group</button>
    </div>
</div>

<div class="modal-overlay" id="addMemberModal">
    <div class="modal" style="width: 450px; padding: 32px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
            <h3 style="font-size:1.4rem; font-weight: 700; color: var(--text-dark);">Add to Group</h3>
            <button class="btn-icon-small" style="border:none;" onclick="closeAddMemberModal()"><i class="ri-close-line" style="font-size:1.5rem;"></i></button>
        </div>
        <div style="position: relative;">
            <i class="ri-search-line" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            <input type="text" id="addMemberSearch" placeholder="Search people..." oninput="searchForAddMember(this.value)" style="padding-left: 40px; margin-bottom: 12px;">
        </div>
        <div id="addMemberUserList" style="max-height:250px; overflow-y:auto; border:1px solid var(--border); border-radius:var(--radius-md); margin-bottom:24px; padding: 5px;"></div>
        <button class="btn-primary" onclick="submitAddMembers()">Add Selected Members</button>
    </div>
</div>