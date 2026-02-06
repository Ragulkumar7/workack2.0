<?php
// include('db_config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-light: #f7f7f7;
            --white: #ffffff;
            --primary-orange: #ff5b37; 
            --text-dark: #333333;
            --text-muted: #666666;
            --border-light: #e3e3e3;
            /* Narrower Sidebar Width for Icon View */
            --sidebar-width: 70px; 
        }

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; display: flex; }
        
        /* ADJUSTED SIDEBAR: Matches image_599039.png */
        .sidebar-wrapper { 
            width: var(--sidebar-width); 
            background: var(--white); 
            height: 100vh; 
            position: fixed; 
            border-right: 1px solid var(--border-light); 
            z-index: 100;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 20px;
        }

        .sidebar-logo { margin-bottom: 30px; }
        .sidebar-logo img { width: 40px; height: 40px; }

        .sidebar-nav-icons { display: flex; flex-direction: column; gap: 25px; }
        .sidebar-nav-icons a { 
            color: var(--text-muted); 
            font-size: 20px; 
            position: relative; 
            text-decoration: none; 
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Green status dots from image */
        .sidebar-nav-icons a::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            width: 6px;
            height: 6px;
            background: #28a745;
            border-radius: 50%;
        }

        .sidebar-nav-icons a.active { color: var(--primary-orange); }
        .sidebar-nav-icons a.active-bg { background: #fff4f2; border-radius: 8px; padding: 10px; color: var(--primary-orange); }

        .main-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 5px; }

        .top-settings-nav { display: flex; background: var(--white); padding: 0 15px; border-radius: 8px; border: 1px solid var(--border-light); margin-bottom: 30px; overflow-x: auto; }
        .top-nav-item { padding: 15px 20px; text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 500; white-space: nowrap; border-bottom: 3px solid transparent; cursor: pointer; }
        .top-nav-item.active { color: var(--primary-orange); border-bottom: 3px solid var(--primary-orange); }

        .settings-container { display: grid; grid-template-columns: 280px 1fr; gap: 30px; }
        .side-nav-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; padding: 15px 0; height: fit-content; }
        .nav-link { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; color: var(--text-dark); text-decoration: none; font-size: 14px; cursor: pointer; }
        .submenu { display: none; background: #fafafa; padding-left: 10px; }
        .submenu.show { display: block; }
        .submenu-item { display: block; padding: 10px 20px; color: var(--text-muted); text-decoration: none; font-size: 13px; cursor: pointer; }
        .submenu-item:hover, .submenu-item.active { color: var(--primary-orange); }

        .content-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 8px; padding: 30px; display: none; }
        .content-card.active { display: block; }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 25px; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }
        .form-section-title { font-size: 14px; font-weight: 700; margin: 20px 0; color: #333; }

        .profile-photo-box { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background: #fcfcfc; border-radius: 8px; border: 1px dashed var(--border-light); }
        .photo-circle { width: 80px; height: 80px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ccc; overflow: hidden; }
        .photo-circle img { width: 100%; height: 100%; object-fit: cover; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; margin-bottom: 8px; font-weight: 500; }
        input, select { width: 100%; padding: 12px; border: 1px solid var(--border-light); border-radius: 6px; font-size: 14px; background-color: white; }
        .btn-save { background: var(--primary-orange); color: white; padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; }
        .btn-cancel { background: white; color: var(--text-dark); border: 1px solid var(--border-light); padding: 12px 25px; border-radius: 6px; margin-right: 10px; cursor: pointer; }

        .security-item { border-bottom: 1px solid var(--border-light); padding: 20px 0; display: flex; justify-content: space-between; align-items: center; }
        .btn-action { padding: 8px 16px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid var(--border-light); background: white; }
        .btn-black { background: #111; color: white; border: none; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 450px; position: relative; }
        
        .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 20px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary-orange); }
        input:checked + .slider:before { transform: translateX(20px); }

        .notif-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .notif-table th { text-align: left; padding: 15px; font-size: 13px; color: var(--text-muted); border-bottom: 1px solid var(--border-light); background: #fafafa; }
        .notif-table td { padding: 15px; border-bottom: 1px solid var(--border-light); font-size: 14px; }

        .grid-box-container { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 15px; }
        .setting-box { border: 1px solid var(--border-light); padding: 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .setting-box h5 { margin: 0; font-size: 15px; font-weight: 600; }
        .theme-preview { width: 40px; height: 40px; border-radius: 50%; display: inline-block; cursor: pointer; margin-right: 10px; border: 2px solid #ddd; }
        .storage-box { border: 1px solid var(--border-light); padding: 20px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        .backup-table { width: 100%; border-collapse: collapse; }
        .backup-table td, .backup-table th { padding: 12px; border-bottom: 1px solid var(--border-light); }
    </style>
</head>
<body>

    <div class="sidebar-wrapper">
        <div class="sidebar-logo">
            <img src="https://smarthr.dreamsguy.com/assets/img/logo.png" alt="Logo">
        </div>
        <div class="sidebar-nav-icons">
            <a href="#" class="active"><i class="fas fa-home"></i></a>
            <a href="#"><i class="fas fa-th-large"></i></a>
            <a href="#"><i class="fas fa-user-friends"></i></a>
            <a href="#"><i class="fas fa-clipboard-list"></i></a>
            <a href="#"><i class="fas fa-users"></i></a>
            <a href="#"><i class="fas fa-user-circle"></i></a>
            <a href="#"><i class="fas fa-shopping-cart"></i></a>
            <a href="#"><i class="fas fa-wallet"></i></a>
            <a href="#"><i class="fas fa-file-alt"></i></a>
            <a href="#" class="active-bg"><i class="fas fa-lock"></i></a>
            <a href="#"><i class="fas fa-project-diagram"></i></a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="page-header">
            <h1>Settings</h1>
            <div class="breadcrumb" id="breadcrumb-text">Settings / General Settings / Profile Settings</div>
        </div>

        <div class="top-settings-nav">
            <div class="top-nav-item active" onclick="showSection('profile', this)"><i class="fas fa-cog"></i> General Settings</div>
            <div class="top-nav-item" onclick="showSection('email-templates', this)"><i class="fas fa-server"></i> System Settings</div>
            
            <div class="top-nav-item"><i class="fas fa-th"></i> Other Settings</div>
        </div>

        <div class="settings-container">
            <aside class="side-nav-card">
                <div class="nav-link" onclick="toggleSub('general-submenu', 'arrow-gen')">
                    <span>General Settings</span><i class="fas fa-chevron-down" id="arrow-gen"></i>
                </div>
                <div class="submenu show" id="general-submenu">
                    <div class="submenu-item active" onclick="showSection('profile', this)">» Profile Settings</div>
                    <div class="submenu-item" onclick="showSection('security', this)">Security Settings</div>
                    <div class="submenu-item" onclick="showSection('notifications', this)">Notifications</div>
                </div>

                <div class="nav-link" onclick="toggleSub('system-submenu', 'arrow-sys')">
                    <span>System Settings</span><i class="fas fa-chevron-down" id="arrow-sys"></i>
                </div>
                <div class="submenu" id="system-submenu">
                    <div class="submenu-item" onclick="showSection('email-templates', this)">Email Templates</div>
                    <div class="submenu-item" onclick="showSection('sms-settings', this)">SMS Settings</div>
                    <div class="submenu-item" onclick="showSection('otp-settings', this)">OTP Settings</div>
                </div>

                <div class="nav-link" onclick="toggleSub('other-submenu', 'arrow-oth')">
                    <span>Other Settings</span><i class="fas fa-chevron-down" id="arrow-oth"></i>
                </div>
                <div class="submenu" id="other-submenu">
                    <div class="submenu-item" onclick="showSection('storage', this)">Storage</div>
                    <div class="submenu-item" onclick="showSection('theme', this)">Theme Settings</div>
                    <div class="submenu-item" onclick="showSection('backup', this)">Backup</div>
                    <div class="submenu-item" onclick="showSection('clear-cache', this)">Clear Cache</div>
                </div>
            </aside>

            <div id="profile-card" class="content-card active">
                <div class="card-title">Profile Settings</div>
                <form id="profileForm">
                    <div class="form-section-title">Basic Information</div>
                    <div class="profile-photo-box">
                        <div class="photo-circle" id="photo-preview"><i class="fas fa-image fa-2x"></i></div>
                        <div>
                            <div style="font-weight:600; font-size:14px;">Profile Photo</div>
                            <div style="color:var(--text-muted); font-size:12px; margin-bottom:10px;">Recommended size: 40px x 40px</div>
                            <input type="file" id="file-upload" style="display:none;" onchange="previewImage(this)">
                            <button type="button" class="btn-save" style="padding: 6px 15px; font-size:12px;" onclick="document.getElementById('file-upload').click()">Upload</button>
                            <button type="button" class="btn-cancel" style="padding: 6px 15px; font-size:12px; border:none;">Cancel</button>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="input-group"><label>First Name</label><input type="text" placeholder="Adrian"></div>
                        <div class="input-group"><label>Last Name</label><input type="text" placeholder="Herman"></div>
                        <div class="input-group"><label>Email</label><input type="email" placeholder="adrianherman@example.com"></div>
                        <div class="input-group"><label>Phone</label><input type="text" placeholder="9876543210"></div>
                    </div>
                    <div class="form-section-title">Address Information</div>
                    <div class="input-group"><label>Address</label><input type="text" placeholder="Enter full address"></div>
                    <div class="form-grid">
                        <div class="input-group"><label>Country</label><input type="text" placeholder="Select Country"></div>
                        <div class="input-group"><label>State/Province</label><input type="text" placeholder="Select State"></div>
                        <div class="input-group"><label>City</label><input type="text" placeholder="Select City"></div>
                        <div class="input-group"><label>Postal Code</label><input type="text" placeholder="Enter Postal Code"></div>
                    </div>
                    <div style="text-align: right; margin-top: 30px;"><button type="submit" class="btn-save">Save Changes</button></div>
                </form>
            </div>

            <div id="security-card" class="content-card">
                <div class="card-title">Security Settings</div>
                <div class="security-item"><div class="security-info"><h4>Password</h4><p>Set a unique password</p></div><button class="btn-action btn-black" onclick="openModal('passwordModal')">Change Password</button></div>
                <div class="security-item"><div class="security-info"><h4>Two Factor Authentication</h4><p>Receive codes via SMS/Email</p></div><button class="btn-action btn-black" onclick="openModal('enableModal')">Enable</button></div>
                <div class="security-item"><div class="security-info"><h4>Phone Verification</h4><p>Verified Mobile: +99264710583</p></div><button class="btn-action btn-black" onclick="openModal('changeModal')">Change</button></div>
                <div class="security-item"><div class="security-info"><h4>Device Management</h4><p>Devices associated with account</p></div><button class="btn-action btn-black" onclick="openModal('manageModal')">Manage</button></div>
                <div class="security-item"><div class="security-info"><h4>Account Activity</h4><p>Activities of the account</p></div><button class="btn-action btn-black" onclick="openModal('viewModal')">View</button></div>
                <div class="security-item" style="border:none;"><div class="security-info"><h4>Deactivate Account</h4><p>Shutdown your account temporarily</p></div><button class="btn-action btn-black" onclick="openModal('deactivateModal')">Deactivate</button></div>
            </div>

            <div id="notifications-card" class="content-card">
                <div class="card-title">Notifications</div>
                <table class="notif-table">
                    <thead><tr><th>Modules</th><th align="center">Push</th><th align="center">SMS</th><th align="center">Email</th></tr></thead>
                    <tbody>
                        <tr><td><strong>New Hire Notifications</strong></td><td align="center"><label class="switch"><input type="checkbox" checked><span class="slider"></span></label></td><td align="center"><label class="switch"><input type="checkbox" checked><span class="slider"></span></label></td><td align="center"><label class="switch"><input type="checkbox" checked><span class="slider"></span></label></td></tr>
                        <tr><td><strong>Time Off Requests</strong></td><td align="center"><label class="switch"><input type="checkbox" checked><span class="slider"></span></label></td><td align="center"><label class="switch"><input type="checkbox"><span class="slider"></span></label></td><td align="center"><label class="switch"><input type="checkbox" checked><span class="slider"></span></label></td></tr>
                    </tbody>
                </table>
            </div>

            <div id="email-templates-card" class="content-card">
                <div style="display:flex; justify-content:space-between; align-items:center;" class="card-title">
                    <span>Email Templates</span>
                    <button class="btn-save" style="padding:8px 15px; font-size:12px;">+ Add Template</button>
                </div>
                <div class="grid-box-container">
                    <div class="setting-box"><h5>Email Verification</h5><div class="actions"><i class="fas fa-edit"></i><i class="fas fa-trash"></i></div></div>
                    <div class="setting-box"><h5>Welcome Email</h5><div class="actions"><i class="fas fa-edit"></i><i class="fas fa-trash"></i></div></div>
                    <div class="setting-box"><h5>Leave Request</h5><div class="actions"><i class="fas fa-edit"></i><i class="fas fa-trash"></i></div></div>
                    <div class="setting-box"><h5>Performance Review</h5><div class="actions"><i class="fas fa-edit"></i><i class="fas fa-trash"></i></div></div>
                    <div class="setting-box"><h5>Training Session</h5><div class="actions"><i class="fas fa-edit"></i><i class="fas fa-trash"></i></div></div>
                    <div class="setting-box"><h5>Password Reset</h5><div class="actions"><i class="fas fa-edit"></i><i class="fas fa-trash"></i></div></div>
                </div>
            </div>

            <div id="sms-settings-card" class="content-card">
                <div class="card-title">SMS Settings</div>
                <div class="grid-box-container">
                    <div class="setting-box"><h5>Nexmo</h5><label class="switch"><input type="checkbox" checked><span class="slider"></span></label></div>
                    <div class="setting-box"><h5>2Factor</h5><label class="switch"><input type="checkbox"><span class="slider"></span></label></div>
                    <div class="setting-box"><h5>Twilio</h5><label class="switch"><input type="checkbox"><span class="slider"></span></label></div>
                </div>
            </div>

            <div id="otp-settings-card" class="content-card">
                <div class="card-title">OTP Settings</div>
                <div class="form-grid" style="grid-template-columns: 1fr;">
                    <div class="input-group"><label>OTP Type</label><select><option>SMS OTP</option><option>Email OTP</option></select></div>
                    <div class="input-group"><label>OTP Digit Limit</label><select><option>4 Digits</option><option>6 Digits</option></select></div>
                </div>
                <div style="text-align: right;"><button class="btn-save">Save OTP Config</button></div>
            </div>

            <div id="theme-card" class="content-card">
                <div class="card-title">Theme Settings</div>
                <div class="input-group"><label>Theme Mode</label>
                    <div style="display:flex; gap:20px;">
                        <label><input type="radio" name="theme" checked> Light Mode</label>
                        <label><input type="radio" name="theme"> Dark Mode</label>
                    </div>
                </div>
                <div class="input-group"><label>Primary Theme Color</label>
                    <div style="display:flex;">
                        <div class="theme-preview" style="background:#ff5b37; border-color:#000;"></div>
                        <div class="theme-preview" style="background:#2196f3;"></div>
                        <div class="theme-preview" style="background:#4caf50;"></div>
                    </div>
                </div>
                <button class="btn-save">Apply Theme</button>
            </div>

            <div id="storage-card" class="content-card">
                <div class="card-title">Storage Settings</div>
                <div class="storage-box"><div><i class="fas fa-server"></i> Local Storage</div><label class="switch"><input type="checkbox" checked><span class="slider"></span></label></div>
                <div class="storage-box"><div><i class="fab fa-aws"></i> AWS S3</div><label class="switch"><input type="checkbox"><span class="slider"></span></label></div>
            </div>

            <div id="backup-card" class="content-card">
                <div class="card-title" style="display:flex; justify-content:space-between;">Database Backup <span><button class="btn-save" style="font-size:12px;">Generate Now</button></span></div>
                <table class="backup-table">
                    <thead><tr><th>File Name</th><th>Size</th><th>Date</th><th align="right">Action</th></tr></thead>
                    <tbody><tr><td>db_backup_2024.sql</td><td>2.4 MB</td><td>11 Sep 2024</td><td align="right"><i class="fas fa-download"></i></td></tr></tbody>
                </table>
            </div>

            <div id="clear-cache-card" class="content-card">
                <div class="card-title">Clear Cache</div>
                <p style="background:#fff4f2; padding:15px; border-radius:6px; color:#ff5b37; font-size:14px;"><i class="fas fa-info-circle"></i> This will clear all temporary sessions.</p>
                <div style="text-align: right; margin-top: 20px;"><button class="btn-save">Clear All Cache</button></div>
            </div>
        </div>
    </div>

    <div id="passwordModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Change Password</h3><span onclick="closeModal('passwordModal')" class="close-modal">&times;</span></div><input type="password" placeholder="Old Password" style="margin-bottom:10px;"><input type="password" placeholder="New Password"><button class="btn-save" style="margin-top:20px;">Update</button></div></div>
    <div id="enableModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Enable 2FA</h3><span onclick="closeModal('enableModal')" class="close-modal">&times;</span></div><button class="btn-save">Enable</button></div></div>
    <div id="changeModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Change Phone</h3><span onclick="closeModal('changeModal')" class="close-modal">&times;</span></div><input type="text" placeholder="New Number"><button class="btn-save" style="margin-top:20px;">Verify</button></div></div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function toggleSub(id, arrowId) { 
            document.getElementById(id).classList.toggle("show"); 
            document.getElementById(arrowId).style.transform = document.getElementById(id).classList.contains("show") ? "rotate(180deg)" : "rotate(0deg)";
        }
        function showSection(sectionId, element) {
            document.querySelectorAll('.content-card').forEach(card => card.classList.remove('active'));
            document.querySelectorAll('.submenu-item').forEach(item => { item.classList.remove('active'); item.innerText = item.innerText.replace('» ', ''); });
            document.getElementById(sectionId + '-card').classList.add('active');
            element.classList.add('active'); element.innerText = '» ' + element.innerText;
            document.getElementById('breadcrumb-text').innerText = 'Settings / ' + element.parentElement.previousElementSibling.innerText + ' / ' + element.innerText.replace('» ', '');
        }
        function previewImage(input) { if (input.files && input.files[0]) { var reader = new FileReader(); reader.onload = function(e) { document.getElementById('photo-preview').innerHTML = '<img src="' + e.target.result + '">'; }; reader.readAsDataURL(input.files[0]); } }
        window.onclick = function(event) { if (event.target.className === 'modal') { event.target.style.display = "none"; } }
        window.onload = function() { document.getElementById("arrow-gen").style.transform = "rotate(180deg)"; };
    </script>
</body>
</html>