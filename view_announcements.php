<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | Notice Board</title>
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
            --sidebar-width: 260px;
        }

        body { background-color: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; margin: 0; display: flex; }
        
        /* Sidebar Placeholder */
        .sidebar-wrapper { width: var(--sidebar-width); background: var(--white); height: 100vh; position: fixed; border-right: 1px solid var(--border-light); z-index: 100; }
        
        .main-wrapper { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 30px; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 24px; margin: 0; font-weight: 600; }

        .announcement-grid { display: grid; grid-template-columns: 1fr 380px; gap: 30px; }

        /* Main Featured Card */
        .featured-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .featured-img-container { width: 100%; height: 400px; background: #eee; position: relative; }
        .featured-img-container img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s ease; }
        
        .featured-body { padding: 35px; }
        .post-title { font-size: 28px; font-weight: 700; margin: 10px 0; color: var(--text-dark); }
        .post-meta { display: flex; gap: 20px; font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
        .post-meta i { color: var(--primary-orange); margin-right: 5px; }
        .post-content { line-height: 1.8; color: #4b5563; font-size: 16px; }

        /* Side List Styling */
        .side-card { background: var(--white); border: 1px solid var(--border-light); border-radius: 12px; padding: 25px; position: sticky; top: 30px; }
        .side-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; border-bottom: 3px solid var(--primary-orange); display: inline-block; padding-bottom: 5px; }
        
        .news-item { display: flex; gap: 15px; padding: 15px; border-radius: 10px; cursor: pointer; transition: 0.3s; margin-bottom: 10px; border: 1px solid transparent; }
        .news-item:hover { background: #fff8f6; border-color: #ffe0d8; }
        .news-item.active { background: #fff1f0; border-color: var(--primary-orange); border-left: 5px solid var(--primary-orange); }
        
        .news-date { width: 55px; height: 55px; background: var(--white); border: 1px solid var(--border-light); border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; color: var(--text-dark); }
        .news-date span { font-size: 10px; text-transform: uppercase; color: var(--primary-orange); }

        .news-text h4 { font-size: 15px; margin: 0 0 4px; font-weight: 600; color: var(--text-dark); }
        .news-text p { font-size: 12px; color: var(--text-muted); margin: 0; }

        .badge { padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .bg-urgent { background: #fff1f0; color: #ff5b37; border: 1px solid #ffcfc5; }
    </style>
</head>
<body>

    <div class="sidebar-wrapper"></div>

    <div class="main-wrapper">
        <div class="page-header">
            <h1>Notice Board</h1>
        </div>

        <div class="announcement-grid">
            <div id="mainView">
                <div class="featured-card">
                    <div class="featured-img-container">
                        <img id="mainImg" src="https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80" alt="Featured">
                    </div>
                    <div class="featured-body">
                        <span id="mainBadge" class="badge bg-urgent">Urgent Update</span>
                        <h2 id="mainTitle" class="post-title">New Office Policy Regarding Hybrid Work 2026</h2>
                        <div class="post-meta">
                            <span><i class="fas fa-calendar-alt"></i> <span id="mainDate">06 Feb 2026</span></span>
                            <span><i class="fas fa-user-circle"></i> Corporate HR</span>
                        </div>
                        <div id="mainDesc" class="post-content">
                            We are happy to announce our new transition to a hybrid work model. This change is designed to give our employees more flexibility while maintaining our collaborative office culture. Team leads will be reaching out soon to discuss department-specific schedules.
                        </div>
                    </div>
                </div>
            </div>

            <aside>
                <div class="side-card">
                    <div class="side-title">Latest Updates</div>
                    
                    <div class="news-item active" onclick="updateContent(this, 'New Office Policy Regarding Hybrid Work 2026', 'Urgent Update', '06 Feb 2026', 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80', 'We are happy to announce our new transition to a hybrid work model. This change is designed to give our employees more flexibility while maintaining our collaborative office culture.')">
                        <div class="news-date">06<span>Feb</span></div>
                        <div class="news-text"><h4>Hybrid Work Update</h4><p>New flexibility guidelines...</p></div>
                    </div>

                    <div class="news-item" onclick="updateContent(this, 'Pongal Festival 2026 Holidays', 'Holiday Notice', '14 Jan 2026', 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?auto=format&fit=crop&w=1200&q=80', 'Wishing everyone a very Happy Pongal! Please note that the office will remain closed from Jan 14 to Jan 16 for the festivities. Enjoy the holidays with your family!')">
                        <div class="news-date">14<span>Jan</span></div>
                        <div class="news-text"><h4>Pongal Holidays</h4><p>Office closure announcement...</p></div>
                    </div>

                    <div class="news-item" onclick="updateContent(this, 'Annual IT Security Cybersecurity Audit', 'Security Alert', '02 Feb 2026', 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1200&q=80', 'Our annual IT security audit is starting this week. Please ensure all your systems are updated and change your passwords according to the new complexity requirements.')">
                        <div class="news-date">02<span>Feb</span></div>
                        <div class="news-text"><h4>IT Security Audit</h4><p>Required system updates...</p></div>
                    </div>

                    <div class="news-item" onclick="updateContent(this, 'Neoera Monthly Town Hall Meeting', 'Event', '28 Jan 2026', 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=1200&q=80', 'Join us for our monthly town hall in the main cafeteria. We will be celebrating the monthly achievements and announcing the Employee of the Month!')">
                        <div class="news-date">28<span>Jan</span></div>
                        <div class="news-text"><h4>Monthly Town Hall</h4><p>Join us at 4:00 PM...</p></div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        function updateContent(element, title, badge, date, img, desc) {
            // Main Card update logic
            const mainImg = document.getElementById('mainImg');
            mainImg.style.opacity = '0'; // Fade out effect
            
            setTimeout(() => {
                document.getElementById('mainTitle').innerText = title;
                document.getElementById('mainBadge').innerText = badge;
                document.getElementById('mainDate').innerText = date;
                document.getElementById('mainDesc').innerText = desc;
                mainImg.src = img;
                mainImg.style.opacity = '1'; // Fade in effect
            }, 300);

            // Active Class update
            document.querySelectorAll('.news-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');
        }
    </script>
</body>
</html>