<div id="chat_view" style="display: flex; width: 100%; height: 100%;">
    <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-muted); text-align:center; padding:20px; z-index:5;" id="chatAreaEmpty">
        <div style="width: 120px; height: 120px; background: var(--primary-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 24px;">
            <i class="ri-chat-smile-3-line" style="font-size:4rem; color: var(--primary);"></i>
        </div>
        <h3 style="font-size: 1.5rem; color: var(--text-dark); margin-bottom: 10px; font-weight: 700;">Workack Team Chat</h3>
        <p style="font-size: 1rem; max-width: 300px;">Select a conversation from the sidebar or start a new chat to connect with your team.</p>
    </div>
    
    <div id="chatAreaActive" class="chat-main-column" style="display: none;">
        <div class="chat-header" id="chatHeader">
            <div style="display:flex; align-items:center;">
                <button id="mobileBackBtn" class="btn-icon" style="margin-right:12px; box-shadow:none; border:none; background:var(--hover-bg);" onclick="backToList()"><i class="ri-arrow-left-line"></i></button>
                <div style="position:relative;">
                     <img src="" id="headerAvatar" class="avatar" loading="lazy" style="width:42px;height:42px;margin:0;border:none;">
                     <span style="position:absolute; bottom:0; right:-2px; width:14px; height:14px; border:2px solid var(--surface); border-radius:50%; background-color:#22c55e;"></span>
                </div>
                
                <div style="margin-left: 16px;">
                    <h3 id="headerName" style="font-size:1.15rem; color:var(--text-dark); margin:0; line-height:1.2; font-weight:700;">Loading...</h3>
                    <span id="typingIndicator" style="font-size:0.8rem; color:var(--primary); height:16px; display:block; font-style:italic; font-weight: 500;"></span>
                </div>
                
                <div class="header-nav" style="margin-left: 40px;">
                    <div class="header-nav-item active" onclick="switchInnerTab('chat')">Chat</div>
                    <div class="header-nav-item" onclick="switchInnerTab('files')">Files</div>
                    <div class="header-nav-item" onclick="switchInnerTab('photos')">Photos</div>
                </div>
            </div>
            
            <div class="header-actions">
                <button class="btn-icon" onclick="startCall('video')" title="Video Call"><i class="ri-vidicon-line"></i></button>
                <button class="btn-icon" onclick="startCall('audio')" title="Voice Call"><i class="ri-phone-line"></i></button>
                <button class="btn-icon" id="headerInfoBtn" style="display:none;" onclick="toggleGroupInfo()" title="Group Info"><i class="ri-group-line"></i></button>
                <button class="btn-icon" onclick="toggleHeaderMenu(event)"><i class="ri-more-2-fill"></i></button>
                <div id="chatOptionsDropdown">
                    <button onclick="clearDeleteChat('clear')"><i class="ri-eraser-line"></i> Clear Chat</button>
                    <button onclick="clearDeleteChat('delete')" style="color: #ef4444;"><i class="ri-delete-bin-line"></i> Delete Chat</button>
                </div>
            </div>
        </div>
        
        <div id="chatMessagesContainer" style="display:flex; flex-direction:column; flex:1; height:100%; overflow:hidden;">
            <div class="messages-box" id="msgBox" onscroll="handleScroll()"></div>
            
            <div id="editModeBar">
                <div style="display:flex; align-items:center; gap:10px;"><i class="ri-edit-2-fill"></i> <span>Editing message...</span></div>
                <i class="ri-close-line" style="cursor:pointer; font-size:1.4rem; color: var(--text-dark);" onclick="cancelEdit()" title="Cancel Edit"></i>
            </div>
            
            <div class="input-area">
                <div id="filePreview">
                    <div style="display: flex; align-items: center; gap: 12px; overflow: hidden;">
                        <div style="width: 36px; height: 36px; background: var(--primary-light); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                            <i class="ri-file-text-fill" style="color: var(--primary); font-size: 1.2rem;"></i>
                        </div>
                        <span id="filePreviewName" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; color: var(--text-dark);">filename.pdf</span>
                    </div>
                    <button class="btn-icon-small" onclick="clearFile()" style="border:none; box-shadow:none;"><i class="ri-close-line" style="color: #ef4444; font-size: 1.4rem;"></i></button>
                </div>
                <div class="input-wrapper">
                    <input type="file" id="fileUpload" hidden onchange="queueFile(this)">
                    <input type="text" id="msgInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') submitMessage()">
                    <div class="input-tools">
                        <button class="btn-tool" title="Emoji" onclick="toggleEmojiPicker(event)"><i class="ri-emotion-line"></i></button>
                        <label for="fileUpload" class="btn-tool" title="Attach file"><i class="ri-attachment-2"></i></label>
                        <button class="btn-tool btn-send" onclick="submitMessage()" title="Send"><i class="ri-send-plane-2-fill"></i></button>
                    </div>
                </div>
                <div id="emojiPicker"></div>
            </div>
        </div>

        <div id="chatFilesContainer" style="display:none; flex-direction:column; flex:1; height:100%; background:var(--surface); overflow-y:auto; padding:40px;">
            <div id="filesEmptyState" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center;">
                <div style="width: 160px; height: 160px; background: linear-gradient(135deg, var(--hover-bg), var(--primary-light)); border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 30px; box-shadow: var(--shadow-md);">
                    <i class="ri-folder-upload-fill" style="font-size: 6rem; color: var(--primary);"></i>
                </div>
                <h3 style="font-size: 1.4rem; color: var(--text-dark); margin-bottom: 12px; font-weight: 800;">Share files in this chat</h3>
                <p style="font-size: 1rem; color: var(--text-muted); margin-bottom: 30px; max-width: 300px;">When you upload files to this tab, they will be securely shared with the conversation.</p>
                <button class="btn-primary" style="width: auto; padding: 12px 32px;" onclick="document.getElementById('fileUpload').click()">
                    <i class="ri-upload-2-line" style="vertical-align: middle; margin-right: 8px;"></i> Upload File
                </button>
            </div>
            <div id="filesContent" style="display:none; width:100%; flex-direction:column; max-width: 800px; margin: 0 auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <h3 style="font-weight: 700; font-size: 1.2rem;">Shared Files</h3>
                    <button class="btn-primary" style="width: auto; padding: 8px 20px; font-size: 0.9rem;" onclick="document.getElementById('fileUpload').click()"><i class="ri-upload-2-line"></i> Upload</button>
                </div>
                <div id="filesList" style="display:flex; flex-direction:column; gap:12px;"></div>
            </div>
        </div>
        
        <div id="chatPhotosContainer" style="display:none; flex-direction:column; flex:1; height:100%; background:var(--surface); overflow-y:auto; padding:40px;">
            <div id="photosEmptyState" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center;">
                <div style="width: 160px; height: 160px; background: linear-gradient(135deg, var(--hover-bg), var(--primary-light)); border-radius: 50%; display:flex; align-items:center; justify-content:center; margin-bottom: 30px; box-shadow: var(--shadow-md);">
                    <i class="ri-image-2-fill" style="font-size: 6rem; color: var(--primary);"></i>
                </div>
                <h3 style="font-size: 1.4rem; color: var(--text-dark); margin-bottom: 12px; font-weight: 800;">No photos shared yet</h3>
                <p style="font-size: 1rem; color: var(--text-muted); margin-bottom: 30px; max-width: 300px;">Photos and images sent in the chat will automatically appear here as a gallery.</p>
            </div>
            <div id="photosGrid" style="display:none; width:100%; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; align-content: start; max-width: 1000px; margin: 0 auto;"></div>
        </div>
    </div>
    
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