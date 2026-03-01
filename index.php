<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="A's to Ace - Career Pathway Automation System for Gobi Arts & Science College" />
  <title>A's to Ace — Career Pathway</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root{
      /* Brand Colors */
      --primary: #1a346e;       /* Deep Navy */
      --brand-blue: #2563eb;    /* REQUESTED BLUE */
      --brand-blue-hover: #1d4ed8;
      --accent: #ff6b6b;        /* Coral */
      
      /* Neutrals */
      --text-main: #0f172a;
      --text-muted: #64748b;
      --bg-body: #f8fafc;
      --card-bg: #ffffff;
      
      /* Effects */
      --glass: rgba(255, 255, 255, 0.8);
      --glass-border: rgba(255, 255, 255, 0.5);
      --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
      --radius: 16px;
      --trans: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* --- RESET & BASE --- */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; font-size: 16px; }
    body { 
      font-family: 'Poppins', sans-serif; 
      background: var(--bg-body); 
      color: var(--text-main); 
      line-height: 1.6; 
      overflow-x: hidden;
    }
    a { text-decoration: none; color: inherit; transition: var(--trans); }
    img { max-width: 100%; display: block; height: auto; }
    h1, h2, h3, h4 { font-family: 'Merriweather', serif; line-height: 1.2; }

    /* --- UTILITIES --- */
    .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
    .section { padding: 100px 0; position: relative; }
    .text-center { text-align: center; }
    .muted { color: var(--text-muted); }
    
    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: var(--trans);
      letter-spacing: 0.3px;
    }
    .btn-primary {
      background: var(--brand-blue);
      color: #fff;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
    .btn-primary:hover {
      background: var(--brand-blue-hover);
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(37, 99, 235, 0.4);
    }
    .btn-ghost {
      background: white;
      border: 1px solid #e2e8f0;
      color: var(--primary);
    }
    .btn-ghost:hover {
      border-color: var(--brand-blue);
      color: var(--brand-blue);
      background: #eff6ff;
    }

    /* --- HEADER & NAV --- */
    header {
      position: sticky;
      top: 0;
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      z-index: 1000;
      border-bottom: 1px solid rgba(255,255,255,0.3);
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .nav {
      height: 80px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .logo-square {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, var(--brand-blue), var(--primary));
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 20px;
      overflow: hidden;
    }
    .logo-square img { width: 100%; height: 100%; object-fit: cover; }
    .brand-text h1 {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.1rem;
      color: var(--primary);
      margin: 0;
    }
    .brand-text p {
      font-size: 0.75rem;
      color: var(--text-muted);
      line-height: 1;
    }

    /* Desktop Links */
    .nav-links { display: flex; gap: 32px; align-items: center; }
    .nav-links a {
      font-weight: 500;
      color: var(--text-muted);
      font-size: 0.95rem;
      position: relative;
    }
    .nav-links a:hover { color: var(--brand-blue); }
    .nav-links a::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -4px;
      left: 0;
      background: var(--brand-blue);
      transition: width 0.3s;
    }
    .nav-links a:hover::after { width: 100%; }

    .auth-buttons { display: flex; gap: 12px; }

    /* Mobile Menu Trigger */
    .mobile-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--primary);
      cursor: pointer;
    }

    /* --- MOBILE DRAWER --- */
    .mobile-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.4);
      backdrop-filter: blur(4px);
      z-index: 2000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s;
    }
    .mobile-drawer {
      position: fixed;
      top: 0;
      right: -100%; 
      width: 85%;
      max-width: 300px;
      height: 100vh;
      background: #fff;
      z-index: 2001;
      padding: 24px;
      display: flex;
      flex-direction: column;
      box-shadow: -8px 0 24px rgba(0,0,0,0.1);
      transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .mobile-drawer.active { right: 0; }
    .mobile-overlay.active { opacity: 1; pointer-events: all; }

    .drawer-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
      border-bottom: 1px solid #f1f5f9;
      padding-bottom: 16px;
    }
    .close-btn {
      background: #f1f5f9;
      border: none;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      color: var(--text-muted);
      font-size: 1.2rem;
      cursor: pointer;
    }
    .mobile-links { display: flex; flex-direction: column; gap: 20px; }
    .mobile-links a { font-size: 1.1rem; font-weight: 500; color: var(--primary); }
    .mobile-auth { margin-top: auto; display: grid; gap: 12px; }
    .mobile-auth .btn { width: 100%; }

    /* --- HERO SECTION --- */
    .hero {
      min-height: 85vh;
      display: flex;
      align-items: center;
      background: linear-gradient(135deg, rgba(26, 52, 110, 0.95), rgba(37, 99, 235, 0.85)), url('/public/images/stage.png');
      background-size: cover;
      background-position: center;
      color: white;
      padding-top: 120px; 
      overflow: hidden;
    }
    .hero-content { position: relative; z-index: 2; max-width: 800px; }
    .badge {
      display: inline-block;
      padding: 8px 16px;
      background: rgba(255,255,255,0.15);
      backdrop-filter: blur(4px);
      border: 1px solid rgba(255,255,255,0.2);
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 24px;
      color: #e2e8f0;
    }
    .hero h1 {
      font-size: clamp(2.5rem, 5vw, 4rem);
      margin-bottom: 20px;
      text-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .hero p {
      font-size: clamp(1rem, 2vw, 1.2rem);
      color: #cbd5e1;
      margin-bottom: 40px;
      max-width: 650px;
    }
    .hero-buttons { display: flex; gap: 16px; flex-wrap: wrap; }
    .hero-buttons .btn-ghost { 
      background: transparent; 
      color: white; 
      border-color: rgba(255,255,255,0.4); 
    }
    .hero-buttons .btn-ghost:hover {
      background: white;
      color: var(--brand-blue);
    }

    /* --- ABOUT & INVITATION STYLING --- */
    .about-grid {
      display: grid;
      grid-template-columns: 1fr 0.8fr;
      gap: 60px;
      align-items: center;
    }
    .event-card {
      background: white;
      border-radius: var(--radius);
      padding: 30px;
      box-shadow: var(--shadow-lg);
      border: 1px solid #f1f5f9;
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 24px;
      margin: 30px 0;
    }
    .event-item h4 { font-family: 'Poppins', sans-serif; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
    .event-item span { font-weight: 600; color: var(--primary); }

    /* New Invitation Style with Glassmorphism and Blob */
    .invitation-wrapper {
      position: relative;
      padding: 20px;
      display: flex;
      justify-content: center;
    }
    /* Decorative Blob Background */
    .invitation-wrapper::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 110%;
      height: 110%;
      background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
      border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
      z-index: 0;
      animation: morph 8s ease-in-out infinite;
    }
    @keyframes morph {
        0%, 100% { border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; }
        34% { border-radius: 70% 30% 50% 50% / 30% 30% 70% 70%; }
        67% { border-radius: 100% 60% 60% 100% / 100% 100% 60% 60%; }
    }

    .invitation-img {
      position: relative;
      z-index: 2;
      border-radius: 20px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      border: 8px solid rgba(255, 255, 255, 0.8);
      transition: all 0.4s ease;
      transform: rotate(2deg);
      max-width: 100%;
      /* Glass backdrop behind image */
      backdrop-filter: blur(10px);
    }
    .invitation-wrapper:hover .invitation-img {
      transform: rotate(0deg) scale(1.02);
      border-color: #fff;
    }

    /* --- ALUMNI GRID --- */
    .alumni-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 40px;
    }
    .alumni-card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      transition: var(--trans);
    }
    .alumni-card:hover {
      border-color: var(--brand-blue);
      transform: translateY(-5px);
      box-shadow: var(--shadow-sm);
    }
    .alumni-role { font-size: 0.9rem; color: var(--text-muted); margin: 4px 0 8px; }
    .alumni-company { font-weight: 600; color: var(--brand-blue); font-size: 0.9rem; }

    /* --- GALLERY --- */
    .gallery-section {
      background: var(--primary);
      padding: 80px 0;
      overflow: hidden;
    }
    .marquee-container {
      width: 100%;
      overflow: hidden;
      white-space: nowrap;
      position: relative;
    }
    .marquee-container:hover .marquee-track { animation-play-state: paused; }
    .marquee-track {
      display: inline-flex;
      gap: 20px;
      animation: scroll 30s linear infinite;
    }
    .marquee-track img {
      height: 250px;
      width: 350px;
      object-fit: cover;
      border-radius: 12px;
      border: 4px solid rgba(255,255,255,0.1);
      cursor: zoom-in;
      transition: transform 0.2s ease;
    }
    .marquee-track img:hover { transform: scale(1.03); border-color: var(--brand-blue); }
    @keyframes scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }

    /* Modal */
    .modal {
      display: none; position: fixed; z-index: 5000; left: 0; top: 0;
      width: 100%; height: 100%; overflow: hidden; background-color: rgba(0,0,0,0.95);
      backdrop-filter: blur(5px); align-items: center; justify-content: center;
    }
    .modal.active { display: flex; }
    .modal-content {
      max-width: 90%; max-height: 90vh; border-radius: 4px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.5); animation: zoomIn 0.3s;
    }
    @keyframes zoomIn { from {transform: scale(0.8); opacity: 0;} to {transform: scale(1); opacity: 1;} }
    .modal-close, .modal-prev, .modal-next {
      position: absolute; color: white; font-size: 40px; font-weight: bold;
      cursor: pointer; transition: 0.3s; user-select: none;
      background: rgba(255,255,255,0.1); width: 60px; height: 60px;
      display: flex; align-items: center; justify-content: center; border-radius: 50%;
    }
    .modal-close { top: 20px; right: 30px; }
    .modal-prev { left: 30px; top: 50%; transform: translateY(-50%); }
    .modal-next { right: 30px; top: 50%; transform: translateY(-50%); }
    .modal-close:hover, .modal-prev:hover, .modal-next:hover { background: var(--brand-blue); }

    /* --- EXPANDED VERTICAL TIMELINE --- */
    .timeline-section { background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); }
    .timeline-container {
      max-width: 800px;
      margin: 0 auto;
      position: relative;
      padding: 20px 0;
    }
    /* Vertical Line */
    .timeline-container::before {
      content: '';
      position: absolute;
      top: 0;
      bottom: 0;
      left: 20px; 
      width: 3px;
      background: #e2e8f0;
      border-radius: 2px;
    }
    .timeline-entry {
      position: relative;
      margin-bottom: 40px;
      padding-left: 60px; /* Space for line and dot */
    }
    .timeline-dot {
      position: absolute;
      left: 9px; /* Centered on the 3px line at left:20px */
      top: 0;
      width: 24px;
      height: 24px;
      background: var(--brand-blue);
      border: 4px solid #fff;
      border-radius: 50%;
      box-shadow: 0 0 0 3px rgba(37,99,235,0.2);
      z-index: 2;
    }
    .timeline-content {
      background: white;
      padding: 24px;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      border: 1px solid #f1f5f9;
      transition: var(--trans);
    }
    .timeline-content:hover {
      transform: translateX(5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      border-color: var(--brand-blue);
    }
    .timeline-date {
      display: inline-block;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--brand-blue);
      background: #eff6ff;
      padding: 4px 10px;
      border-radius: 20px;
      margin-bottom: 10px;
    }
    .timeline-content h3 {
      font-size: 1.2rem;
      color: var(--primary);
      margin-bottom: 8px;
    }
    
    /* --- CLUBS --- */
    .clubs-section { background: #fff; }
    .clubs-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 24px;
      margin-top: 40px;
    }
    .club-card {
      background: white;
      border-radius: var(--radius);
      padding: 24px;
      border: 1px solid #e2e8f0;
      transition: var(--trans);
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .club-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(37, 99, 235, 0.1);
      border-color: rgba(37, 99, 235, 0.3);
    }
    .club-icon {
      width: 60px;
      height: 60px;
      background: #eff6ff;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
    }
    .club-icon img { width: 32px; }
    .club-tag {
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--accent);
      background: #fff1f2;
      padding: 4px 10px;
      border-radius: 20px;
      align-self: flex-start;
      margin-bottom: 12px;
    }
    .club-title { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
    .club-desc { font-size: 0.95rem; color: var(--text-muted); margin-bottom: 20px; flex-grow: 1; }

    /* --- FEATURES --- */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 30px;
    }
    .feature-card {
      text-align: center;
      padding: 30px 20px;
      background: white;
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      transition: var(--trans);
    }
    .feature-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
    .feature-icon { font-size: 2.5rem; margin-bottom: 16px; display: inline-block; }

    /* --- STATS --- */
    .stats-container {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-top: 40px;
    }
    .stat-box {
      background: var(--brand-blue);
      color: white;
      padding: 30px 20px;
      border-radius: 16px;
      text-align: center;
    }
    .stat-number { font-size: 2rem; font-weight: 700; font-family: 'Merriweather', serif; }
    .stat-label { font-size: 0.9rem; opacity: 0.9; margin-top: 5px; }

    /* --- FOOTER --- */
    footer { background: #0f172a; color: white; padding: 80px 0 30px; }
    .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 40px;
      margin-bottom: 60px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      padding-bottom: 40px;
    }
    .social-links { display: flex; gap: 10px; margin-top: 20px; }
    .social-btn {
      width: 40px;
      height: 40px;
      background: rgba(255,255,255,0.05);
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      transition: var(--trans);
    }
    .social-btn:hover { background: var(--brand-blue); transform: translateY(-3px); }
    .social-btn svg { width: 20px; height: 20px; fill: white; }
    .footer-links ul { list-style: none; }
    .footer-links li { margin-bottom: 12px; }
    .footer-links a { color: #94a3b8; }
    .footer-links a:hover { color: white; padding-left: 5px; }

    /* --- RESPONSIVE BREAKPOINTS --- */
    @media (max-width: 900px) {
      .nav-links, .auth-buttons { display: none; }
      .mobile-toggle { display: block; }
      .about-grid { grid-template-columns: 1fr; }
      .footer-grid { grid-template-columns: 1fr; gap: 40px; }
      .stats-container { grid-template-columns: 1fr 1fr; }
      .modal-content { max-width: 95%; }
      .modal-prev { left: 10px; }
      .modal-next { right: 10px; }
    }
  </style>
</head>
<body>

  
    
  <header>
    <div class="container nav">
      <div class="brand">
        <div class="logo-square"><img src="/public/images/Career_Pathway.jpg" alt="logo"></div>
        <div class="brand-text">
          <h1>Career Pathway</h1>
          <p>Gobi Arts & Science College</p>
        </div>
      </div>

      <nav class="nav-links">
        <a href="#clubs">Clubs</a>
        <a href="#about">About</a>
        <a href="#features">Features</a>
        <a href="#impact">Impact</a>
      </nav>

      <div class="auth-buttons">
        <a class="btn btn-ghost" href="/auth/login.php">Login</a>
        <a class="btn btn-primary" href="/auth/register.php">Register</a>
      </div>

      <button class="mobile-toggle" id="mobileMenuBtn" aria-label="Open menu">☰</button>
    </div>
  </header>

  <div class="mobile-overlay" id="mobileOverlay"></div>
  <div class="mobile-drawer" id="mobileDrawer">
    <div class="drawer-header">
      <div class="brand">
        <div class="logo-square">CP</div>
        <div class="brand-text">
          <h1>Career Pathway</h1>
        </div>
      </div>
      <button class="close-btn" id="closeMenuBtn">✕</button>
    </div>
    <nav class="mobile-links" id="mobileLinks">
      <a href="#clubs">Clubs</a>
      <a href="#about">About</a>
      <a href="#features">Features</a>
      <a href="#impact">Impact</a>
    </nav>
    <div class="mobile-auth">
      <a class="btn btn-ghost" href="/auth/login.php">Login</a>
      <a class="btn btn-primary" href="/auth/register.php">Register Now</a>
    </div>
  </div>

  <section class="hero">
    <div class="container hero-content">
      <span class="badge">Estd. Jan 2025</span>
      <h1>From A’s to Ace</h1>
      <p>The complete ecosystem for students: Assessment, Programming, Mentorship, and Communication skills. A structured pathway to your corporate future.</p>

      <div class="hero-buttons">
        <a class="btn btn-primary" href="#clubs">Explore the 4 A's</a>
        <a class="btn btn-ghost" href="#features">How it Works</a>
      </div>
    </div>
  </section>

  <section class="section" id="about">
    <div class="container">
      <div class="about-grid">
        <div>
          <h4 style="color:var(--brand-blue); font-weight:700; letter-spacing:1px; margin-bottom:10px;">THE ORIGIN</h4>
          <h2 style="font-size:2.5rem; margin-bottom:20px; color:var(--primary);">The Grand Launch</h2>
          <p class="muted" style="margin-bottom:30px;">
            Inaugurated on 20th January 2025, Career Pathway bridges the gap between campus learning and corporate expectations through four dedicated clubs.
          </p>

          <div class="event-card">
            <div class="event-item">
              <h4>Date</h4>
              <span>20 Jan, 2025</span>
            </div>
            <div class="event-item">
              <h4>Venue</h4>
              <span>KMS Hall</span>
            </div>
            <div class="event-item">
              <h4>Attendees</h4>
              <span>1000+ Students</span>
            </div>
            
            <div class="event-item" style="grid-column: 1 / -1;">
              <h4>Chief Guest & Main Mentor</h4>
              <div style="display: flex; align-items: center; gap: 15px; margin-top: 5px;">
                <img src="/public/images/balaji_sir.jpeg" alt="Mr. K. Balaji" style="width: 150px; height: 200px; border-radius: 50%; object-fit: cover; border: 3px solid var(--brand-blue); box-shadow: 0 4px 10px rgba(37,99,235,0.2);">
                <div>
                  <span style="display: block; font-size: 1.1rem; font-weight: 700; color: var(--primary);">Mr. K. Balaji - MCA ( BATCH 1992 - 1995 ) </span>
                  <span style="display: block; font-size: 0.85rem; color: var(--text-muted); line-height: 1.3;">Managing Director, J.P. Morgan Chase</span>
                  <span style="display: inline-block; background: #e0f2fe; color: var(--brand-blue); font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; margin-top: 4px; font-weight: 600; border: 1px solid #bae6fd;">Main Mentor</span>
                </div>
              </div>
            </div>
          </div>

          <h3 style="margin-top:40px; font-size:1.5rem;">Alumni Speakers</h3>
          <div class="alumni-grid">
            <div class="alumni-card">
              <div class="alumni-name"><strong>Ms. D. Jayavarthini</strong></div>
              <div class="alumni-role">Android Developer</div>
              <div class="alumni-company">Kambaa Inc. (CBE)</div>
            </div>

            <div class="alumni-card">
              <div class="alumni-name"><strong>Mr. M. Nishanth</strong></div>
              <div class="alumni-role">Jr. Software Engineer</div>
              <div class="alumni-company">Cartrabbit (CBE)</div>
            </div>

            <div class="alumni-card">
              <div class="alumni-name"><strong>Ms. K. V. Deepika</strong></div>
              <div class="alumni-role">Jr. Software Developer</div>
              <div class="alumni-company">SolutionChamps (CBE)</div>
            </div>

            <div class="alumni-card">
              <div class="alumni-name"><strong>Ms. S. Dhanalakshmi</strong></div>
              <div class="alumni-role">Software Engineer</div>
              <div class="alumni-company">J.P. Morgan Chase (BLR)</div>
            </div>

            <div class="alumni-card">
              <div class="alumni-name"><strong>Mr. Raja M Appachi</strong></div>
              <div class="alumni-role">Founder & CEO</div>
              <div class="alumni-company">DoWhisle (USA)</div>
            </div>
          </div>
        </div>

        <div class="invitation-wrapper">
           <img src="invitation.png" alt="Inauguration Invitation" class="invitation-img">
        </div>
      </div>
    </div>
  </section>

  <section class="gallery-section">
    <div class="container text-center" style="margin-bottom:40px;">
      <h2 style="color:white;">Event Gallery</h2>
      <p style="color:rgba(255,255,255,0.7);">Hover to pause. Click an image to enlarge.</p>
    </div>
    
    <div class="marquee-container">
      <div class="marquee-track" id="galleryTrack"> 
         <img src="/public/images/page_3_image_3.png" alt="Audience" class="gallery-img">
         <img src="/public/images/page_3_image_1.png" alt="Audience" class="gallery-img">
         <img src="/public/images/page_3_image_2.png" alt="Audience" class="gallery-img">
         <img src="/public/images/page_3_image_4.png" alt="Audience" class="gallery-img">
         <img src="/public/images/page_1_image_7.png" alt="Event Stage" class="gallery-img">
         <img src="/public/images/page_2_image_7.png" alt="Crowd View" class="gallery-img">
         <img src="/public/images/stage.png" alt="Chief Guest" class="gallery-img">
         <img src="/public/images/stage2.png" alt="Alumni" class="gallery-img">
         <img src="/public/images/page_2_image_8.png" alt="Audience" class="gallery-img">         
         <img src="/public/images/page_3_image_3.png" alt="Audience" class="gallery-img">
         <img src="/public/images/page_3_image_1.png" alt="Audience" class="gallery-img">
      </div>
    </div>
  </section>

  <div id="lightbox" class="modal">
    <span class="modal-close" id="lightboxClose">&times;</span>
    <span class="modal-prev" id="lightboxPrev">&#10094;</span>
    <img class="modal-content" id="lightboxImg">
    <span class="modal-next" id="lightboxNext">&#10095;</span>
  </div>

  <section class="section timeline-section">
    <div class="container">
      <div class="text-center" style="margin-bottom:60px;">
        <h2 style="color:var(--primary);">Pathway Milestones</h2>
        <p class="muted">A timeline of our workshops, launches, and reviews.</p>
      </div>

      <div class="timeline-container">
        
        <div class="timeline-entry">
          <span class="timeline-dot"></span>
          <div class="timeline-content">
            <span class="timeline-date">Jan 20, 2025</span>
            <h3>The Grand Launch</h3>
            <p class="muted">
              Official inauguration of Career Pathway and its 4 clubs (Alphabet, Aptitude, Apex, Alumni) at KMR Auditorium.<br>
              <strong>Chief Guest:</strong> Mr. K. Balaji (MD, J.P. Morgan).
            </p>
          </div>
        </div>

        <div class="timeline-entry">
          <span class="timeline-dot"></span>
          <div class="timeline-content">
            <span class="timeline-date">Mar 04, 2025</span>
            <h3>Mock Interviews (Alphabet Club)</h3>
            <p class="muted">
              Conducted online for I-B.Sc students to strengthen communication skills. Included self-introduction drills and feedback session.
            </p>
          </div>
        </div>

        <div class="timeline-entry">
          <span class="timeline-dot"></span>
          <div class="timeline-content">
            <span class="timeline-date">Jun 29, 2025</span>
            <h3>Internship Project Review</h3>
            <p class="muted">
              An online critical review meeting coordinated by Dowhistle & Coaction Team. Alumni mentors provided insights on student projects.
            </p>
          </div>
        </div>

        <div class="timeline-entry">
          <span class="timeline-dot"></span>
          <div class="timeline-content">
            <span class="timeline-date">Aug 17, 2025</span>
            <h3>Aptitude: Coding & Decoding</h3>
            <p class="muted">
              An online session by the Aptitude Club to strengthen logical reasoning and analytical abilities among I-B.Sc students.
            </p>
          </div>
        </div>

        <div class="timeline-entry">
          <span class="timeline-dot"></span>
          <div class="timeline-content">
            <span class="timeline-date">Sep 20, 2025</span>
            <h3>Outreach Program</h3>
            <p class="muted">
              Introduction of Career Pathway to <strong>Govt College for Women, Madurai</strong>. Shared vision, objectives, and key activities.
            </p>
          </div>
        </div>

        <div class="timeline-entry">
          <span class="timeline-dot"></span>
          <div class="timeline-content">
            <span class="timeline-date">Oct 18, 2025</span>
            <h3>GitHub & HackerRank Session</h3>
            <p class="muted">
              <strong>Resource Person:</strong> Mr. Amirthalingam S (Apex Club Secretary).<br>
              A focused session for MCA (2025-2027 Batch) on competitive programming platforms.
            </p>
          </div>
        </div>

      </div>
    </div>
  </section>

 <section class="section clubs-section" id="clubs">
    <div class="container">
      <div class="text-center" style="margin-bottom:60px;">
        <h2 style="color:var(--primary); font-size:2.5rem;">The Four A's</h2>
        <p class="muted">Choose your wing and start building your profile.</p>
      </div>

      <div class="clubs-grid">
        
        <article class="club-card">
          <div class="club-tag">PROGRAMMING</div>
          <div class="club-icon"><img src="/public/images/apex.png" alt="Icon"></div>
          <h3 class="club-title">Apex Club</h3>
          
          <div style="margin-bottom: 15px; font-size: 0.9rem; color: var(--text-muted);">
            <strong>Key Activities:</strong>
            <ul style="padding-left: 20px; margin-top: 5px; list-style-type: disc;">
              <li>Practice LeetCode & HackerRank problems.</li>
              <li>Monthly Hackathons with rewards.</li>
              <li>Periodic GitHub commit reviews.</li>
              <li>Solving top interview questions.</li>
            </ul>
          </div>

          <div style="background: #f1f5f9; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
            <span style="display:block; font-size:0.75rem; font-weight:700; color:var(--brand-blue); text-transform:uppercase;">Faculty Mentors</span>
            <span style="font-size:0.85rem; color:var(--primary); font-weight:600;">
             Dr. G.T. Prabavathi &<br>Mr. R. Sathishkumar
            </span>
          </div>
        </article>

        <article class="club-card">
          <div class="club-tag">NETWORKING</div>
          <div class="club-icon"><img src="/public/images/alumni.png" alt="Icon"></div>
          <h3 class="club-title">Alumni Club</h3>
          
          <div style="margin-bottom: 15px; font-size: 0.9rem; color: var(--text-muted);">
            <strong>Key Activities:</strong>
            <ul style="padding-left: 20px; margin-top: 5px; list-style-type: disc;">
              <li>Creating & Reviewing LinkedIn profiles.</li>
              <li>Resume reviews & Mock interviews.</li>
              <li>Establishing connections with alumni.</li>
              <li>Gaining internship leads.</li>
            </ul>
          </div>

          <div style="background: #f1f5f9; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
            <span style="display:block; font-size:0.75rem; font-weight:700; color:var(--brand-blue); text-transform:uppercase;">Faculty Mentor</span>
            <span style="font-size:0.85rem; color:var(--primary); font-weight:600;">
             Dr. B. Srinivasan
            </span>
          </div>
        </article>

        <article class="club-card">
          <div class="club-tag">LOGIC</div>
          <div class="club-icon"><img src="/public/images/aptitude.png" alt="Icon"></div>
          <h3 class="club-title">Aptitude Club</h3>
          
          <div style="margin-bottom: 15px; font-size: 0.9rem; color: var(--text-muted);">
            <strong>Key Activities:</strong>
            <ul style="padding-left: 20px; margin-top: 5px; list-style-type: disc;">
              <li>Daily aptitude problem solving.</li>
              <li>Weekly Tests and Quizzes.</li>
              <li>Sharing online practice resources.</li>
              <li>Coding and Decoding sessions.</li>
            </ul>
          </div>

          <div style="background: #f1f5f9; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
            <span style="display:block; font-size:0.75rem; font-weight:700; color:var(--brand-blue); text-transform:uppercase;">Faculty Mentor</span>
            <span style="font-size:0.85rem; color:var(--primary); font-weight:600;">
             Dr. G.A. Mylavathi
            </span>
          </div>
        </article>

        <article class="club-card">
          <div class="club-tag">COMMUNICATION</div>
          <div class="club-icon"><img src="/public/images/alphabet.png" alt="Icon"></div>
          <h3 class="club-title">Alphabet Club</h3>
          
          <div style="margin-bottom: 15px; font-size: 0.9rem; color: var(--text-muted);">
            <strong>Key Activities:</strong>
            <ul style="padding-left: 20px; margin-top: 5px; list-style-type: disc;">
              <li>Everyday communication in English.</li>
              <li>Weekly Group Discussions.</li>
              <li>Weekly Grammar Explanations.</li>
              <li>Reading 6 minutes per day.</li>
            </ul>
          </div>

          <div style="background: #f1f5f9; padding: 10px; border-radius: 8px; margin-bottom: 20px;">
            <span style="display:block; font-size:0.75rem; font-weight:700; color:var(--brand-blue); text-transform:uppercase;">Faculty Mentor</span>
            <span style="font-size:0.85rem; color:var(--primary); font-weight:600;">
             Dr. K. Prabhusundhar
            </span>
          </div>
        </article>

      </div>
    </div>
 </section>

  <section class="section" id="features" style="background:#f1f5f9;">
    <div class="container">
    <h2 style="color: var(--primary);font-size: 2.5rem;margin: 20px auto;text-align: center;">Features</h2>
      <div class="features-grid">
        <div class="feature-card">
          <span class="feature-icon">📝</span>
          <h4>Automated Tests</h4>
          <p class="muted">Instant scoring and analytics.</p>
        </div>
        <div class="feature-card">
          <span class="feature-icon">📅</span>
          <h4>Alumni Booking</h4>
          <p class="muted">Schedule mentorship sessions.</p>
        </div>
        <div class="feature-card">
          <span class="feature-icon">💼</span>
          <h4>Internship Portal</h4>
          <p class="muted">Verified listings & tracking.</p>
        </div>
        <div class="feature-card">
          <span class="feature-icon">🔔</span>
          <h4>Smart Alerts</h4>
          <p class="muted">Never miss a deadline.</p>
        </div>
      </div>

		<h2 style="color: var(--primary);font-size: 2.5rem;margin: 50px auto;text-align: center;">Impact</h2>
        
		<div class="stats-container" id="impact">
      
        <div class="stat-box">
          <div class="stat-number">1000+</div>
          <div class="stat-label">Students</div>
        </div>
        <div class="stat-box">
          <div class="stat-number">500+</div>
          <div class="stat-label">Tests Taken</div>
        </div>
        <div class="stat-box">
          <div class="stat-number">20+</div>
          <div class="stat-label">Placements</div>
        </div>
        <div class="stat-box">
          <div class="stat-number">50+</div>
          <div class="stat-label">Expert Talks</div>
        </div>
      </div>
    </div>
  </section>

 <section class="section" style="background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);">
   <div class="container">
     <div class="text-center" style="margin-bottom:50px;">
       <h2 style="color:var(--primary); font-size:2rem;">Student Voices</h2>
       <p class="muted">Hear from students who are paving their way to success.</p>
     </div>

     <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
       
       <div class="review-card" style="background:white; padding:30px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 6px rgba(0,0,0,0.05); position:relative;">
         <div style="font-size:3rem; color:#eff6ff; position:absolute; top:20px; right:20px; font-family:serif; line-height:1;">"</div>
         <p style="color:#475569; font-style:italic; margin-bottom:20px; position:relative; z-index:1;">
           "My vision was to bridge the gap between academic learning and industry expectations. Through Career Pathway, I strengthened my leadership and gained industry exposure through internships at CloudFrame and KoralTech."
         </p>
         <div style="display:flex; align-items:center; gap:15px;">
           <div style="width:45px; height:45px; border-radius:50%; background:#dbeafe; color:var(--brand-blue); display:flex; align-items:center; justify-content:center; font-weight:700;">A</div>
           <div>
             <div style="font-weight:700; color:var(--primary); font-size:0.95rem;">Aruljothi N</div>
             <div style="font-size:0.8rem; color:#94a3b8;">Chairman, Career Pathway</div>
           </div>
         </div>
       </div>

       <div class="review-card" style="background:white; padding:30px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 6px rgba(0,0,0,0.05); position:relative;">
         <div style="font-size:3rem; color:#eff6ff; position:absolute; top:20px; right:20px; font-family:serif; line-height:1;">"</div>
         <p style="color:#475569; font-style:italic; margin-bottom:20px; position:relative; z-index:1;">
           "Through my active involvement, I secured an internship via alumni connections, enhanced my technical skills, and built a strong professional network."
         </p>
         <div style="display:flex; align-items:center; gap:15px;">
           <div style="width:45px; height:45px; border-radius:50%; background:#fce7f3; color:#db2777; display:flex; align-items:center; justify-content:center; font-weight:700;">U</div>
           <div>
             <div style="font-weight:700; color:var(--primary); font-size:0.95rem;">Umasri G</div>
             <div style="font-size:0.8rem; color:#94a3b8;">Secretary, Alumni Club</div>
           </div>
         </div>
       </div>

       <div class="review-card" style="background:white; padding:30px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 6px rgba(0,0,0,0.05); position:relative;">
         <div style="font-size:3rem; color:#eff6ff; position:absolute; top:20px; right:20px; font-family:serif; line-height:1;">"</div>
         <p style="color:#475569; font-style:italic; margin-bottom:20px; position:relative; z-index:1;">
           "The Apex Club gave me a chance to learn tools like GitHub and LeetCode. I learned leadership, team coordination, and gained confidence in public speaking."
         </p>
         <div style="display:flex; align-items:center; gap:15px;">
           <div style="width:45px; height:45px; border-radius:50%; background:#dcfce7; color:#16a34a; display:flex; align-items:center; justify-content:center; font-weight:700;">K</div>
           <div>
             <div style="font-weight:700; color:var(--primary); font-size:0.95rem;">Kavipriya G</div>
             <div style="font-size:0.8rem; color:#94a3b8;">Joint Secretary, Apex Club</div>
           </div>
         </div>
       </div>

        <div class="review-card" style="background:white; padding:30px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 6px rgba(0,0,0,0.05); position:relative;">
            <div style="font-size:3rem; color:#eff6ff; position:absolute; top:20px; right:20px; font-family:serif; line-height:1;">"</div>
            <p style="color:#475569; font-style:italic; margin-bottom:20px; position:relative; z-index:1;">
              "It strengthened my leadership, communication, and problem-solving abilities & helped me build a career-focused mindset, giving clarity about professional growth."
            </p>
            <div style="display:flex; align-items:center; gap:15px;">
              <div style="width:45px; height:45px; border-radius:50%; background:#e0e7ff; color:#4f46e5; display:flex; align-items:center; justify-content:center; font-weight:700;">N</div>
              <div>
                <div style="font-weight:700; color:var(--primary); font-size:0.95rem;">Nithyasri</div>
                <div style="font-size:0.8rem; color:#94a3b8;">Joint Secretary, Aptitude Club</div>
              </div>
            </div>
          </div>

          <div class="review-card" style="background:white; padding:30px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 6px rgba(0,0,0,0.05); position:relative;">
            <div style="font-size:3rem; color:#eff6ff; position:absolute; top:20px; right:20px; font-family:serif; line-height:1;">"</div>
            <p style="color:#475569; font-style:italic; margin-bottom:20px; position:relative; z-index:1;">
              "I regularly attend weekly tests, so my aptitude and logical thinking have improved, and my communication skills have also improved."
            </p>
            <div style="display:flex; align-items:center; gap:15px;">
              <div style="width:45px; height:45px; border-radius:50%; background:#fee2e2; color:#ef4444; display:flex; align-items:center; justify-content:center; font-weight:700;">V</div>
              <div>
                <div style="font-weight:700; color:var(--primary); font-size:0.95rem;">Vignesh M</div>
                <div style="font-size:0.8rem; color:#94a3b8;">Team Member, Aptitude Club</div>
              </div>
            </div>
          </div>

          <div class="review-card" style="background:white; padding:30px; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 6px rgba(0,0,0,0.05); position:relative;">
            <div style="font-size:3rem; color:#eff6ff; position:absolute; top:20px; right:20px; font-family:serif; line-height:1;">"</div>
            <p style="color:#475569; font-style:italic; margin-bottom:20px; position:relative; z-index:1;">
              "I gained valuable leadership experience and improved my collaboration and communication skills while working as a secretary."
            </p>
            <div style="display:flex; align-items:center; gap:15px;">
              <div style="width:45px; height:45px; border-radius:50%; background:#ffedd5; color:#f97316; display:flex; align-items:center; justify-content:center; font-weight:700;">S</div>
              <div>
                <div style="font-weight:700; color:var(--primary); font-size:0.95rem;">N. Saranya</div>
                <div style="font-size:0.8rem; color:#94a3b8;">Secretary, Alphabet Club</div>
              </div>
            </div>
          </div>

     </div>
   </div>
 </section>

  <section style="padding:80px 0; background:white; text-align:center;">
    <div class="container">
      <h2 style="color:var(--primary); margin-bottom:15px;">Ready to Ace your Career?</h2>
      <p class="muted" style="margin-bottom:30px;">Join the structured pathway today.</p>
      <a class="btn btn-primary" href="/auth/register.php" style="padding:16px 32px; font-size:1.1rem;">Student Registration</a>
    </div>
  </section>

  <footer>
    <div class="container">
      <div class="footer-grid">
        <div>
          <div style="font-family:'Merriweather',serif;font-size:1.15rem;color:#fff;margin-bottom:6px">Gobi Arts & Science College</div>
          <div style="color:#9fb9b6;font-size:.9rem;margin-top:8px">Karattadipalayam, Gobichettipalayam,</div>
          <div style="color:#9fb9b6;font-size:.9rem;margin-top:6px">Erode — 638453, Tamil Nadu, India</div>

          <div style="display:flex;margin: 20px 0;gap: 10px;" class="socials-row" aria-label="Social links">
            <a class="social-btn" href="https://www.instagram.com/gobiartsandsciencecollege/" target="_blank" rel="noopener" aria-label="Instagram">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 6.1A4.9 4.9 0 1 0 16.9 13 4.9 4.9 0 0 0 12 8.1zm6.4-3.6a1.14 1.14 0 1 1-1.14 1.14A1.14 1.14 0 0 1 18.4 4.5zM12 10.5A1.5 1.5 0 1 1 10.5 12 1.5 1.5 0 0 1 12 10.5z"/>
              </svg>
            </a>
            <a class="social-btn" href="https://www.facebook.com/people/Gobi-Arts-Science-College/100092003531286/?mibextid=ZbWKwL" target="_blank" rel="noopener" aria-label="Facebook">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M22 12a10 10 0 1 0-11.5 9.9v-7H8.5v-3h2V9a3 3 0 0 1 3-3h2v3h-2a1 1 0 0 0-1 1v1.9h3l-.5 3h-2.5v7A10 10 0 0 0 22 12z"/>
              </svg>
            </a>
            <a class="social-btn" href="https://www.linkedin.com/in/gobi-arts-and-science-college-304b3a273/?originalSubdomain=in" target="_blank" rel="noopener" aria-label="LinkedIn">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M6.94 6.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5zM4.5 8.5h4v11h-4zM10.5 8.5h3.8v1.6h.1a4.2 4.2 0 0 1 3.8-2.1c4.1 0 4.9 2.7 4.9 6.2v7.3h-4v-6.5c0-1.6 0-3.6-2.2-3.6-2.2 0-2.5 1.7-2.5 3.5v6.6h-4z"/>
              </svg>
            </a>
            <a class="social-btn" href="https://x.com/GASCgobi36?t=fX_j8WUqzWYnQ1LG-jPoaA&s=09" target="_blank" rel="noopener" aria-label="Twitter">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M22 5.9c-.6.3-1.2.5-1.9.6.7-.5 1.3-1.3 1.6-2.2-.6.4-1.3.7-2.1.9A3.2 3.2 0 0 0 12.4 8c0 .3 0 .6.1.9C8.5 8.8 5.3 6.7 3.1 3.8c-.3.6-.5 1.3-.5 2 0 1.4.7 2.6 1.8 3.3-.5 0-1-.2-1.4-.4 0 2.1 1.4 3.9 3.4 4.3-.4.1-.9.1-1.3.1-.3 0-.6 0-.9-.1.6 1.9 2.3 3.3 4.2 3.3A6.5 6.5 0 0 1 2 20.4 9.1 9.1 0 0 0 7 22c6.6 0 10.2-5.5 10.2-10.3v-.5c.7-.5 1.3-1.2 1.7-2z"/>
              </svg>
            </a>
            <a class="social-btn" href="https://gascgobi.ac.in/" target="_blank" rel="noopener" aria-label="College Website">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2zm6.93 6h-2.62a15.093 15.093 0 0 0-1.17-3.52A8.025 8.025 0 0 1 18.93 8zM12 4a13.238 13.238 0 0 1 1.84 4H10.2A13.238 13.238 0 0 1 12 4zM4.07 8a8.025 8.025 0 0 1 3.79-3.52A15.093 15.093 0 0 0 6.69 8zm0 8h2.62a15.093 15.093 0 0 0 1.17 3.52A8.025 8.025 0 0 1 4.07 16zm6.11 0h3.64A13.238 13.238 0 0 1 12 20a13.238 13.238 0 0 1-1.84-4zm5.13 3.52A15.093 15.093 0 0 0 18.31 16h2.62a8.025 8.025 0 0 1-3.79 3.52zM10.2 14a13.238 13.238 0 0 1-.92-4h5.44a13.238 13.238 0 0 1-.92 4z"/>
              </svg>
            </a>
          </div>
        </div>

        <div>
          <div style="color:#cfeae7;font-weight:700;margin-bottom:8px">Platform</div>
          <ul style="color:#9fb9b6;line-height:1.9;margin-left: 8px;">
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#clubs">Clubs</a></li>
            <li><a href="#features">Features</a></li>
          </ul>
        </div>

        <div>
          <div style="color:#cfeae7;font-weight:700;margin-bottom:8px">Contact</div>
          <div style="color:#9fb9b6;line-height:1.9;margin-left: 8px;">
            E-Mail :
            <a href="mailto:careerpathway2k25@gmail.com?subject=Inquiry%20about%20A's%20to%20Ace&body=Hi%20team%2C%0A%0AI%20have%20a%20question%20about...">
              careerpathway2k25@gmail.com
            </a>
          </div>
        </div>
      </div>

      <div style="text-align:center;color:#87bfb8;margin-top:20px;font-size:.95rem">&copy; 2025 Career Pathway Automation System — All rights reserved</div>
    </div>
  </footer>

  <script>
    // --- MOBILE MENU LOGIC ---
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const closeBtn = document.getElementById('closeMenuBtn');
    const drawer = document.getElementById('mobileDrawer');
    const overlay = document.getElementById('mobileOverlay');
    const links = document.querySelectorAll('#mobileLinks a');

    function toggleMenu() {
      const isActive = drawer.classList.contains('active');
      if (isActive) {
        drawer.classList.remove('active');
        overlay.classList.remove('active');
      } else {
        drawer.classList.add('active');
        overlay.classList.add('active');
      }
    }

    mobileBtn.addEventListener('click', toggleMenu);
    closeBtn.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', toggleMenu);

    links.forEach(link => {
      link.addEventListener('click', toggleMenu);
    });

    // --- LIGHTBOX (GALLERY) LOGIC ---
    const galleryImages = document.querySelectorAll('.gallery-img');
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxClose = document.getElementById('lightboxClose');
    const lightboxNext = document.getElementById('lightboxNext');
    const lightboxPrev = document.getElementById('lightboxPrev');

    let currentIndex = 0;
    
    // Store image sources in an array (avoid duplicates if necessary, here we just take all sources)
    const imageSources = Array.from(galleryImages).map(img => img.src);

    function openLightbox(index) {
      currentIndex = index;
      lightboxImg.src = imageSources[currentIndex];
      lightbox.classList.add('active');
    }

    function closeLightbox() {
      lightbox.classList.remove('active');
    }

    function nextImage(e) {
      e.stopPropagation(); // prevent closing
      currentIndex = (currentIndex + 1) % imageSources.length;
      lightboxImg.src = imageSources[currentIndex];
    }

    function prevImage(e) {
      e.stopPropagation(); // prevent closing
      currentIndex = (currentIndex - 1 + imageSources.length) % imageSources.length;
      lightboxImg.src = imageSources[currentIndex];
    }

    // Event Listeners for Images
    galleryImages.forEach((img, index) => {
      img.addEventListener('click', () => openLightbox(index));
    });

    // Event Listeners for Controls
    lightboxClose.addEventListener('click', closeLightbox);
    lightboxNext.addEventListener('click', nextImage);
    lightboxPrev.addEventListener('click', prevImage);
    
    // Close on background click
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) {
        closeLightbox();
      }
    });

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      if (!lightbox.classList.contains('active')) return;
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowRight') nextImage(e);
      if (e.key === 'ArrowLeft') prevImage(e);
    });

    // --- SCROLL REVEAL ANIMATION ---
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, { threshold: 0.1 });

    const animatedElements = document.querySelectorAll('.club-card, .feature-card, .alumni-card, .timeline-entry');
    animatedElements.forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'all 0.6s ease-out';
      observer.observe(el);
    });
  </script>
      <?php include 'chat.php'; ?>
</body>
</html>