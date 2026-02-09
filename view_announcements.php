<?php
/**
 * Page: View Announcements (Read-Only)
 * Access: Employee, Team Lead, Team Leader
 */

// 1. SESSION START & SECURITY CHECK
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartHR | Announcement</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        /* --- PROFESSIONAL THEME VARIABLES --- */
        :root {
            --bg-body: #f8fafc;        /* Softer background */
            --bg-card: #ffffff;
            --primary-color: #ff5b37;  /* Brand Orange */
            --primary-hover: #e04f2e;
            --text-main: #1e293b;      /* Dark Slate */
            --text-muted: #64748b;     /* Muted Blue-Grey */
            --border-color: #e2e8f0;
            --sidebar-width: 260px;    /* Match your sidebar settings */
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            overflow-x: hidden;
            font-size: 14px;
        }

        /* --- LAYOUT WRAPPER --- */
        #mainContent { 
            margin-left: 95px; /* Adjust based on sidebar state */
            padding: 30px; 
            transition: margin-left 0.3s ease;
            width: calc(100% - 95px);
            min-height: 100vh;
        }
        #mainContent.main-shifted {
            margin-left: 315px; 
            width: calc(100% - 315px);
        }

        /* --- PAGE HEADER --- */
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 24px; font-weight: 700; color: var(--text-main); margin: 0; letter-spacing: -0.5px; }
        .breadcrumb { font-size: 13px; color: var(--text-muted); margin-top: 4px; }

        /* --- GRID LAYOUT --- */
        .announcement-layout { 
            display: grid; 
            grid-template-columns: 1fr 380px; 
            gap: 25px; 
            align-items: start; 
        }

        /* --- FEATURED CARD (LEFT SIDE) --- */
        .featured-card { 
            background: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: var(--shadow-md);
            height: 100%; /* Full height */
        }
        
        .featured-img-wrapper { 
            width: 100%; 
            height: 380px; 
            background: #f1f5f9; 
            position: relative; 
            overflow: hidden;
        }
        .featured-img-wrapper img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            transition: opacity 0.4s ease-in-out;
        }
        
        .featured-content { padding: 30px; }
        
        /* Typography */
        .badge { 
            display: inline-block; 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 11px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            margin-bottom: 15px;
        }
        .bg-urgent { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .bg-event  { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }
        .bg-holiday{ background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .bg-policy { background: #f3e8ff; color: #9333ea; border: 1px solid #e9d5ff; }

        .post-title { 
            font-size: 26px; 
            font-weight: 800; 
            margin: 0 0 15px 0; 
            line-height: 1.3; 
            color: var(--text-main);
            transition: opacity 0.3s ease;
        }
        
        .post-meta { 
            display: flex; 
            gap: 20px; 
            font-size: 13px; 
            color: var(--text-muted); 
            margin-bottom: 25px; 
            border-bottom: 1px solid var(--border-color); 
            padding-bottom: 15px; 
        }
        .post-meta span { display: flex; align-items: center; gap: 6px; font-weight: 500; }
        .post-meta i { color: var(--primary-color); }

        .post-desc { 
            line-height: 1.7; 
            color: #475569; 
            font-size: 15px; 
            transition: opacity 0.3s ease;
        }

        /* --- SIDEBAR LIST (RIGHT SIDE) --- */
        .side-panel { 
            background: var(--bg-card); 
            border: 1px solid var(--border-color); 
            border-radius: 16px; 
            padding: 20px; 
            position: sticky; 
            top: 20px; 
            box-shadow: var(--shadow-sm);
        }
        .side-header { 
            font-size: 16px; 
            font-weight: 700; 
            margin-bottom: 20px; 
            padding-bottom: 10px; 
            border-bottom: 2px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .side-header span { color: var(--primary-color); }

        .news-item { 
            display: flex; 
            gap: 15px; 
            padding: 12px; 
            border-radius: 10px; 
            cursor: pointer; 
            transition: all 0.2s ease; 
            margin-bottom: 8px; 
            border: 1px solid transparent; 
        }
        .news-item:hover { 
            background: #fff7ed; 
            border-color: #ffedd5; 
            transform: translateX(3px);
        }
        .news-item.active { 
            background: #fff1f0; 
            border-color: var(--primary-color); 
            box-shadow: var(--shadow-sm);
        }

        .date-box { 
            width: 50px; 
            height: 50px; 
            background: #ffffff; 
            border: 1px solid var(--border-color); 
            border-radius: 10px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            font-weight: 700; 
            flex-shrink: 0; 
            color: var(--text-main);
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        .date-box small { 
            font-size: 10px; 
            text-transform: uppercase; 
            color: var(--primary-color); 
            margin-top: -2px; 
        }
        .date-box span { font-size: 16px; line-height: 1; }

        .news-info h4 { 
            font-size: 14px; 
            margin: 0 0 4px; 
            font-weight: 600; 
            color: var(--text-main); 
            line-height: 1.4;
        }
        .news-info p { 
            font-size: 12px; 
            color: var(--text-muted); 
            margin: 0; 
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 1024px) {
            .announcement-layout { grid-template-columns: 1fr; }
            .side-panel { position: static; margin-top: 30px; }
        }
    </style>
</head>
<body>

    <?php include('sidebars.php'); ?>

    <div id="mainContent">
        
        <div class="page-header">
            <h1>Announcement</h1>
            <div class="breadcrumb">Dashboard / Company Updates</div>
        </div>

        <div class="announcement-layout">
            
            <article class="featured-card">
                <div class="featured-img-wrapper">
                    <img id="mainImg" src="https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80" alt="Announcement Banner">
                </div>
                <div class="featured-content">
                    <span id="mainBadge" class="badge bg-urgent">Urgent Update</span>
                    <h2 id="mainTitle" class="post-title">New Office Policy Regarding Hybrid Work 2026</h2>
                    
                    <div class="post-meta">
                        <span><i class="far fa-calendar-alt"></i> <span id="mainDate">06 Feb 2026</span></span>
                        <span><i class="far fa-user-circle"></i> Corporate HR</span>
                        <span><i class="far fa-clock"></i> 10:30 AM</span>
                    </div>
                    
                    <div id="mainDesc" class="post-desc">
                        We are happy to announce our new transition to a hybrid work model. This change is designed to give our employees more flexibility while maintaining our collaborative office culture. Team leads will be reaching out soon to discuss department-specific schedules. Please ensure you update your availability in the HR portal by next Friday.
                    </div>
                </div>
            </article>

            <aside class="side-panel">
                <div class="side-header">
                    Latest Updates <span><i class="fas fa-rss"></i></span>
                </div>
                
                <div class="news-item active" onclick="updateView(this, {
                    title: 'New Office Policy Regarding Hybrid Work 2026',
                    badge: 'Urgent Update',
                    badgeClass: 'bg-urgent',
                    date: '06 Feb 2026',
                    img: 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1200&q=80',
                    desc: 'We are happy to announce our new transition to a hybrid work model. This change is designed to give our employees more flexibility while maintaining our collaborative office culture. Team leads will be reaching out soon to discuss department-specific schedules.'
                })">
                    <div class="date-box"><span>06</span><small>FEB</small></div>
                    <div class="news-info">
                        <h4>Hybrid Work Update</h4>
                        <p>New flexibility guidelines regarding remote work...</p>
                    </div>
                </div>

                <div class="news-item" onclick="updateView(this, {
                    title: 'Pongal Festival 2026 Holidays',
                    badge: 'Holiday Notice',
                    badgeClass: 'bg-holiday',
                    date: '14 Jan 2026',
                    img: 'https://images.unsplash.com/photo-1583417319070-4a69db38a482?auto=format&fit=crop&w=1200&q=80',
                    desc: 'Wishing everyone a very Happy Pongal! Please note that the office will remain closed from Jan 14 to Jan 16 for the festivities. We hope you enjoy the holidays with your family and friends. Emergency support teams will remain on standby.'
                })">
                    <div class="date-box"><span>14</span><small>JAN</small></div>
                    <div class="news-info">
                        <h4>Pongal Holidays</h4>
                        <p>Office closure announcement for the festival...</p>
                    </div>
                </div>

                <div class="news-item" onclick="updateView(this, {
                    title: 'Annual IT Security & Cybersecurity Audit',
                    badge: 'Security Alert',
                    badgeClass: 'bg-urgent',
                    date: '02 Feb 2026',
                    img: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1200&q=80',
                    desc: 'Our annual IT security audit is starting this week. Please ensure all your systems are updated and change your passwords according to the new complexity requirements sent to your email. Do not share your credentials with anyone.'
                })">
                    <div class="date-box"><span>02</span><small>FEB</small></div>
                    <div class="news-info">
                        <h4>IT Security Audit</h4>
                        <p>Mandatory password updates and system checks...</p>
                    </div>
                </div>

                <div class="news-item" onclick="updateView(this, {
                    title: 'Neoera Monthly Town Hall Meeting',
                    badge: 'Event',
                    badgeClass: 'bg-event',
                    date: '28 Jan 2026',
                    img: 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=1200&q=80',
                    desc: 'Join us for our monthly town hall in the main cafeteria. We will be celebrating the monthly achievements, announcing the Employee of the Month, and discussing the roadmap for Q1. Snacks will be served!'
                })">
                    <div class="date-box"><span>28</span><small>JAN</small></div>
                    <div class="news-info">
                        <h4>Monthly Town Hall</h4>
                        <p>Join us at 4:00 PM in the Cafeteria...</p>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <script>
        function updateView(element, data) {
            // 1. Elements to Update
            const imgEl = document.getElementById('mainImg');
            const titleEl = document.getElementById('mainTitle');
            const badgeEl = document.getElementById('mainBadge');
            const dateEl = document.getElementById('mainDate');
            const descEl = document.getElementById('mainDesc');

            // 2. Add Fade Out Effect
            imgEl.style.opacity = '0';
            titleEl.style.opacity = '0.5';
            descEl.style.opacity = '0.5';

            // 3. Update Content after short delay
            setTimeout(() => {
                titleEl.innerText = data.title;
                badgeEl.innerText = data.badge;
                badgeEl.className = `badge ${data.badgeClass}`; // Dynamic Badge Color
                dateEl.innerText = data.date;
                descEl.innerText = data.desc;
                imgEl.src = data.img;

                // 4. Fade In
                imgEl.style.opacity = '1';
                titleEl.style.opacity = '1';
                descEl.style.opacity = '1';
            }, 300);

            // 5. Update Sidebar Active State
            document.querySelectorAll('.news-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');
        }
    </script>
</body>
</html>