<div id="chat_view" style="display: flex; width: 100%; height: 100%;">
    <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); text-align:center; padding:20px; z-index:5;" id="chatAreaEmpty">
        <div style="width: 120px; height: 120px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 24px;">
            <i class="ri-chat-smile-3-line" style="font-size:4rem; color: var(--primary);"></i>
        </div>
        <h3 style="font-size: 1.5rem; color: var(--text-dark); margin-bottom: 10px; font-weight: 700;">Workack Team Chat</h3>
        <p style="font-size: 1rem; max-width: 300px;">Select a conversation from the sidebar or start a new chat to connect with your team.</p>
    </div>
    
    <div id="chatAreaActive" class="chat-main-column" style="display: none;"></div>
    
    <div id="groupInfoPanel" class="group-info-panel">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--surface); position: sticky; top: 0; z-index: 10;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-dark);">Group Members</h3>
            <button class="btn-icon-small" onclick="closeGroupInfo()"><i class="ri-close-line"></i></button>
        </div>
        <div style="padding: 20px; border-bottom: 1px solid var(--border); background: var(--surface);">
            <button onclick="openAddMemberModal()" style="width: 100%; padding: 10px; background: var(--primary-light); border: 1px dashed var(--primary); color: var(--primary); border-radius: var(--radius-sm); font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s;">
                <i class="ri-user-add-fill"></i> Add people
            </button>
        </div>
        <div id="groupMembersList" style="flex: 1; overflow-y: auto; padding: 10px 20px;"></div>
    </div>
</div>