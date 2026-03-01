<?php
// includes/header.php

// 1. Load Config if not already loaded
if (!isset($pdo)) {
    // Adjust path relative to where this header is included
    // Assuming structure: /includes/header.php -> /config/config.php
    $configPath = __DIR__ . '/../config/config.php'; 
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

// 2. Define Helper Function 'e' (Escape) if not exists
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// 3. Get Current User (if authentication system is active)
$user = function_exists('current_user') ? current_user($pdo) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>A's to Ace — Career Pathway</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">

  <style>
    /* --- CSS VARIABLES --- */
    :root{
      --primary: #1a346e;       /* Deep Navy */
      --primary-light: #2c4a8c;
      --brand-blue: #2563eb;    /* Royal Blue */
      --accent: #ff6b6b;        /* Coral */
      --muted: #64748b;         /* Slate Gray */
      --bg: #f7faf9;            
      --text-main: #0f172a;
      --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      --trans: 220ms cubic-bezier(.2,.9,.2,1);
    }

    /* Reset & Base */
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--text-main);line-height:1.5;overflow-x:hidden;}
    a{color:inherit;text-decoration:none; transition: color 0.2s;}
    
    .container{max-width:1180px;margin:0 auto;padding:0 20px;}

    /* Buttons */
    .btn{display:inline-block;padding:10px 18px;border-radius:9px;font-weight:600;cursor:pointer;transition:all var(--trans);font-size:0.9rem;}
    .btn-primary{background:linear-gradient(90deg,var(--primary),#1d4ed8);color:#fff;box-shadow:0 4px 12px rgba(37,99,235,0.2)}
    .btn-ghost{background:transparent;border:1px solid rgba(26, 52, 110, 0.2);color:var(--primary)}
    .btn-sm{padding:8px 14px; font-size:0.85rem;}

    /* --- HEADER STRUCTURE --- */
    header{position:relative;height:90px;top:0;background:rgba(255,255,255,0.95);backdrop-filter:blur(6px);z-index:100;box-shadow:0 2px 8px rgba(12,17,23,0.04); border-bottom: 1px solid rgba(0,0,0,0.05);}
    
    /* Navbar Flex Container */
    .nav {
        height:72px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        flex-wrap: nowrap;
    }
    
    /* Brand Wrapper */
    .brand-wrapper {
        display:flex;
        align-items:center;
        gap:12px;
        font-weight:700;
        color:var(--primary);
        min-width: 0;
        flex-shrink: 1;
    }
    .brand-wrapper .logo-square {
        width:32px;
        height:32px;
        background:linear-gradient(135deg,var(--primary),#1d4ed8);
        border-radius:8px;
        display:flex;
        align-items:center;
        justify-content:center;
        color:#fff; 
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .brand-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Desktop Nav Links */
    .nav-links{display:flex;gap:18px;align-items:center}
    .nav-links a{font-weight:500;color:var(--muted);font-size:.92rem;padding:6px 10px;border-radius:6px}
    .nav-links a:hover{color:var(--primary);background:rgba(37,99,235,0.05)}

    /* Auth Section */
    .auth{display:flex;gap:12px;align-items:center}
    .user-welcome {font-size: 0.85rem; color: var(--muted); text-align: right; line-height: 1.2;}
    .user-welcome strong { color: var(--primary); display: block;}

    /* Mobile Menu Button */
    .mobile-menu-btn {
        display:none; 
        border:0;
        background:transparent;
        font-size:26px;
        cursor:pointer;
        color:var(--primary);
        padding: 5px;
        margin-left: 10px;
        flex-shrink: 0;
    }

    /* Mobile Nav Dropdown */
    .mobile-nav{
        display:none;
        flex-direction:column; /* Stack vertically for better mobile layout */
        gap:15px;
        padding:20px;
        background:#fff;
        box-shadow:0 8px 20px rgba(0,0,0,0.05);
        border-radius:10px;
        margin-top:5px;
        border: 1px solid #eee;
    }

    /* Background Animation */
    .area{position:fixed;top:0;left:0;width:100%;height:100%;z-index:-1;overflow:hidden;pointer-events:none;}
    .circles{position:absolute;top:0;left:0;width:100%;height:100%;overflow:hidden;margin:0;padding:0;}
    .circles li{position:absolute;display:block;list-style:none;width:20px;height:20px;background:rgba(37, 99, 235, 0.1);animation:animate 25s linear infinite;bottom:-150px;border-radius:4px;}
    /* Animation Keyframes defined below */
    .circles li:nth-child(1){left:25%;width:80px;height:80px;animation-delay:0s;}
    .circles li:nth-child(2){left:10%;width:20px;height:20px;animation-delay:2s;animation-duration:12s;}
    .circles li:nth-child(3){left:70%;width:20px;height:20px;animation-delay:4s;}
    .circles li:nth-child(4){left:40%;width:60px;height:60px;animation-delay:0s;animation-duration:18s;}
    @keyframes animate {
        0%{ transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 0; }
        100%{ transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; }
    }

    /* RESPONSIVE BREAKPOINTS */
    @media (max-width: 820px){
        .nav-links, .auth{display:none}
        .mobile-menu-btn{display:block}
    }
    
    @media (max-width: 360px) {
        .brand-text .title { font-size: 0.9rem; }
    }
      
     

/* =========================================
   Header Mobile Optimization
   ========================================= */
@media (max-width: 640px) {
    header .container {
        flex-direction: row;
        gap: 0.75rem;
        padding: 1rem;
    }
    
    .con{
        flex-direction: row;
        gap: 0.75rem;
        padding: 1rem;
    }
    
    header nav {
        flex-wrap: wrap;
        justify-content: center;
        text-align: center;
        line-height: 1.8;
    }
    
    header nav span {
        display: block;
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 0.5rem;
        margin-bottom: 0.25rem;
        margin-right: 0;
    }
}
  </style>
</head>
<body>

<div class="area" aria-hidden="true">
    <ul class="circles">
        <li></li><li></li><li></li><li></li><li></li>
    </ul>
</div>

<header>
    <div class="container nav">
        <a href="<?= e(BASE_URL) ?>/index.php" class="brand-wrapper">
            <div class="logo-square">
                 <img src="<?= e(BASE_URL) ?>/../../public/images/Career_Pathway.jpg" alt="CP" style="width:100%; height:100%; object-fit:cover;">
            </div>
            <div class="brand-text">
                <div class="title" style="line-height:1.2;">Career Pathway — GASC</div>
                <div style="font-size:.72rem;color:var(--muted);font-weight:400;">A's to Ace</div>
            </div>
        </a>

        <nav class="nav-links">
            <a href="<?= e(BASE_URL) ?>/index.php#clubs">Clubs</a>
            <a href="<?= e(BASE_URL) ?>/index.php#about">About</a>
            <a href="<?= e(BASE_URL) ?>/index.php#features">Features</a>
        </nav>

        <div class="auth">
            <?php if ($user): ?>
                <div class="user-welcome">
                    <span>Welcome,</span>
                    <strong><?= e($user['full_name'] ?? $user['roll_no']) ?></strong>
                </div>
                
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(BASE_URL) ?>/admin/dashboard.php">Admin</a>
                <?php endif; ?>

                <a class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>/attendee/dashboard.php">Dashboard</a>
                <a href="<?= e(BASE_URL) ?>/auth/logout.php" style="font-size:0.9rem; color:var(--muted); margin-left:5px;">Logout</a>

            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(BASE_URL) ?>/auth/login.php">Login</a>
                <a class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>/auth/register.php">Register</a>
            <?php endif; ?>
        </div>

        <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
    </div>

    <div class="con" >
        <div id="mobileNav" class="mobile-nav">
            
            <!-- <div style="display:flex; flex-direction:column; gap:10px; border-bottom:1px solid #eee; padding-bottom:15px;">
                <a href="<?= e(BASE_URL) ?>/index.php#clubs" style="color:var(--primary); font-weight:600;">Clubs</a>
                <a href="<?= e(BASE_URL) ?>/index.php#about" style="color:var(--primary); font-weight:600;">About</a>
                <a href="<?= e(BASE_URL) ?>/index.php#features" style="color:var(--primary); font-weight:600;">Features</a>
            </div> -->

            <?php if ($user): ?>
                <div style="color:var(--primary); font-weight:700; font-size:0.95rem;">
                    Hi, <?= e($user['full_name'] ?? $user['roll_no']) ?>
                </div>
                
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="<?= e(BASE_URL) ?>/admin/dashboard.php" style="color:var(--brand-blue);">Admin Panel</a>
                    <?php endif; ?>
                    
                    <a class="btn btn-primary btn-sm" style="text-align:center;" href="<?= e(BASE_URL) ?>/attendee/dashboard.php">Dashboard</a>
                    <a class="btn btn-ghost btn-sm" style="text-align:center; color:var(--accent); border-color:var(--accent);" href="<?= e(BASE_URL) ?>/auth/logout.php">Logout</a>
                </div>
            <?php else: ?>
                <div style="display:flex; gap:10px;">
                    <a class="btn btn-ghost" style="flex:1; text-align:center;" href="<?= e(BASE_URL) ?>/auth/login.php">Login</a>
                    <a class="btn btn-primary" style="flex:1; text-align:center;" href="<?= e(BASE_URL) ?>/auth/register.php">Register</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
    // Simple toggle logic for mobile menu
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        var nav = document.getElementById('mobileNav');
        if (nav.style.display === 'flex') {
            nav.style.display = 'none';
            this.textContent = '☰';
        } else {
            nav.style.display = 'flex';
            this.textContent = '✕';
        }
    });
</script>