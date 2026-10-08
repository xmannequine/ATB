<?php
// =============================================
// landing.php - SmartBin Private System Entry
// =============================================

require_once 'config/config.php';
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Get flash messages
$flash = getFlash();

?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartBin - Automated Waste Segregation & Monitoring</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================================
           CUSTOM COLOR PALETTE
           Primary:    #8BC34A  (Sage Green)
           Secondary:  #C5E1A5  (Light Green)
           Accent:     #4CAF50  (Forest Green)
           Background: #F5F5F5  (Light Gray)
           Text:       #424242  (Charcoal Gray)
           ============================================================ */
        
        :root {
            --primary: #8BC34A;
            --primary-dark: #7CB342;
            --secondary: #C5E1A5;
            --accent: #4CAF50;
            --accent-dark: #388E3C;
            --bg: #F5F5F5;
            --bg-white: #FFFFFF;
            --text: #424242;
            --text-light: #757575;
            --border: #E0E0E0;
            --shadow: rgba(66, 66, 66, 0.08);
            --card-bg: #FFFFFF;
            --hover-bg: rgba(139, 195, 74, 0.08);
        }
        
        /* Dark Mode */
        [data-theme="dark"] {
            --bg: #1a1a1a;
            --bg-white: #2d2d2d;
            --text: #E8E8E8;
            --text-light: #B0B0B0;
            --border: #404040;
            --shadow: rgba(0, 0, 0, 0.3);
            --card-bg: #2d2d2d;
            --hover-bg: rgba(139, 195, 74, 0.12);
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg);
            min-height: 100vh;
            transition: background 0.3s ease;
            position: relative;
        }
        
        /* Background with Overlay */
        .bg-overlay {
            position: fixed;
            inset: 0;
            z-index: 0;
        }
        .bg-overlay .bg-image {
            position: absolute;
            inset: 0;
            background: url('loginbg.jpg') center/cover no-repeat;
            opacity: 0.06;
        }
        [data-theme="dark"] .bg-overlay .bg-image {
            opacity: 0.03;
        }
        .bg-overlay .gradient {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(139, 195, 74, 0.05), rgba(76, 175, 80, 0.05));
        }
        [data-theme="dark"] .bg-overlay .gradient {
            background: linear-gradient(135deg, rgba(139, 195, 74, 0.02), rgba(76, 175, 80, 0.02));
        }
        
        /* Decorative Blobs */
        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
            opacity: 0.10;
        }
        .blob-1 {
            top: -10%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: var(--primary);
            animation: float 8s ease-in-out infinite;
        }
        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: var(--accent);
            animation: float 10s ease-in-out infinite reverse;
        }
        [data-theme="dark"] .blob {
            opacity: 0.06;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.1); }
            66% { transform: translate(-20px, 30px) scale(0.9); }
        }
        
        /* Glass Card */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px var(--shadow);
            transition: all 0.3s ease;
        }
        
        .glass-card-light {
            background: var(--card-bg);
            border: 1px solid var(--border);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
        }
        
        /* Feature Card */
        .feature-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: default;
        }
        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 195, 74, 0.25);
            box-shadow: 0 20px 40px -12px rgba(139, 195, 74, 0.12);
            background: var(--hover-bg);
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            transition: all 0.3s ease;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139, 195, 74, 0.25);
        }
        
        .btn-secondary {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            transition: all 0.3s ease;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-secondary:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
            transform: translateY(-2px);
        }
        
        .btn-nav {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            transition: all 0.3s ease;
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .btn-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 195, 74, 0.25);
        }
        
        /* Nav Link */
        .nav-link {
            position: relative;
            color: var(--text-light);
            transition: color 0.3s ease;
        }
        .nav-link:hover {
            color: var(--text);
        }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        
        /* Stat Number */
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text);
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }
        
        /* Pulse Animation */
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.8); }
        }
        .pulse-dot {
            animation: pulse-dot 2s ease-in-out infinite;
        }
        
        /* Slide Up Animation */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .slide-up {
            animation: slideUp 0.6s ease-out forwards;
            opacity: 0;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        
        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 0.65rem 0.9rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        .theme-toggle:hover {
            border-color: var(--primary);
            background: var(--hover-bg);
            transform: scale(1.05);
        }
        
        /* Team Member Avatar */
        .member-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .theme-toggle span {
                display: none;
            }
            .stat-number {
                font-size: 1.5rem;
            }
        }
        
    </style>
</head>
<body>
    
    <!-- Background -->
    <div class="bg-overlay">
        <div class="bg-image"></div>
        <div class="gradient"></div>
    </div>
    
    <!-- Decorative Blobs -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    
   
    
    <!-- ============================================= -->
    <!-- NAVIGATION – logo.png in place of icon        -->
    <!-- ============================================= -->
    <nav class="fixed top-0 left-0 right-0 z-50 px-6 py-4 bg-[var(--card-bg)]/90 backdrop-blur-md border-b border-[var(--border)]">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[var(--primary)] to-[var(--accent)] flex items-center justify-center shadow-lg shadow-[var(--primary)]/20 overflow-hidden">
                    <img src="logo3.jpg" alt="CT-ABS Logo" class="w-full h-full object-cover">
                </div>
                <span class="text-xl font-bold text-[var(--text)]">CT-<span class="text-[var(--primary)]">ABS</span></span>
            </div>
            
            <!-- Private system access -->
            <a href="login.php" class="btn-nav">
                <i class="fas fa-lock mr-2"></i> Sign In
            </a>
        </div>
    </nav>

    <!-- ============================================= -->
    <!-- HERO SECTION – logo + tagline                 -->
    <!-- ============================================= -->
    <section class="min-h-screen flex items-center justify-center px-6 pt-20">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Left Content -->
                <div class="slide-up">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-[var(--primary)]/10 border border-[var(--primary)]/20 rounded-full text-[var(--primary)] text-sm font-medium mb-6">
                        <i class="fas fa-circle text-[6px] text-[var(--accent)] pulse-dot"></i>
                        <span>Private System · Authorized Users Only</span>
                    </div>
                    
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-[var(--text)] leading-tight mb-4">
                        <!-- Logo image instead of plain text -->
                        <img src="logo5.png" alt="CT-ABS Logo" class="h-auto md:h-30 lg:h-auto w-80 inline-block mb-2">
                        <br>
                        <span class="gradient-text">Automated Bin Segregation &amp; Monitoring</span>
                    </h1>
                    
                    <p class="text-lg text-[var(--text-light)] mb-8 max-w-lg leading-relaxed">
                        This system is restricted to authorized users. Sign in to access real‑time monitoring
                        of the automated waste segregation system with integrated shredder, AI‑powered sensors,
                        solar‑powered operation, and fill‑level alerts.
                    </p>
                    
                    <div class="flex flex-wrap gap-4">
                        <a href="login.php" class="btn-primary">
                            <i class="fas fa-lock"></i> Sign In to Continue
                        </a>
                    </div>
                    
                    <div class="flex items-center gap-3 mt-10 pt-8 border-t border-[var(--border)] text-sm text-[var(--text-light)]">
                        <i class="fas fa-shield-alt text-[var(--accent)]"></i>
                        <span>Operational data is available after authentication.</span>
                    </div>
                </div>
                
                <!-- ============================================================ -->
                <!-- UPGRADED RESTRICTED ACCESS PANEL                              -->
                <!-- ============================================================ -->
                <div class="slide-up delay-2 flex justify-center">
                    <div class="glass-card rounded-3xl p-8 w-full max-w-md">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-[var(--primary)] to-[var(--accent)] flex items-center justify-center shadow-lg shadow-[var(--primary)]/20">
                                    <i class="fas fa-shield-alt text-white text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-[var(--text)] font-semibold">Secure Access</div>
                                    <div class="text-xs text-[var(--text-light)]">CT‑ABS Private System</div>
                                </div>
                            </div>
                            <span class="flex items-center gap-1.5 text-xs text-[var(--accent)]">
                                <i class="fas fa-lock text-[10px]"></i> PRIVATE
                            </span>
                        </div>

                        <div class="space-y-3 mb-6">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-chart-line text-[var(--accent)] mt-1"></i>
                                <div>
                                    <div class="font-medium text-[var(--text)] text-sm">Real‑Time Fill Levels</div>
                                    <p class="text-xs text-[var(--text-light)]">Monitor bin capacities and receive alerts when nearing full.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-robot text-[var(--accent)] mt-1"></i>
                                <div>
                                    <div class="font-medium text-[var(--text)] text-sm">AI Segregation Logs</div>
                                    <p class="text-xs text-[var(--text-light)]">View detailed logs of waste classification and sorting events.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-cogs text-[var(--accent)] mt-1"></i>
                                <div>
                                    <div class="font-medium text-[var(--text)] text-sm">Shredder &amp; Solar Status</div>
                                    <p class="text-xs text-[var(--text-light)]">Check operational health of shredder and solar energy system.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-history text-[var(--accent)] mt-1"></i>
                                <div>
                                    <div class="font-medium text-[var(--text)] text-sm">Historical Analytics</div>
                                    <p class="text-xs text-[var(--text-light)]">Access trends and generate reports for sustainability tracking.</p>
                                </div>
                            </div>
                        </div>

                        <a href="login.php" class="btn-primary w-full justify-center mt-2">
                            <i class="fas fa-sign-in-alt"></i> Sign In to Access
                        </a>

                        <div class="flex justify-center gap-5 mt-5 text-xs text-[var(--text-light)]">
                            <span><i class="fas fa-lock mr-1 text-[var(--accent)]"></i> Encrypted</span>
                            <span><i class="fas fa-user-check mr-1 text-[var(--accent)]"></i> Role‑based access</span>
                            <span><i class="fas fa-clock mr-1 text-[var(--accent)]"></i> 24/7 monitoring</span>
                        </div>
                    </div>
                </div>
                <!-- END UPGRADED PANEL -->
                <!-- ============================================================ -->
            </div>
        </div>
    </section>

    <!-- ============================================= -->
    <!-- FEATURES SECTION                              -->
    <!-- ============================================= -->
    <section id="features" class="py-20 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <span class="inline-block px-4 py-1.5 bg-[var(--primary)]/10 border border-[var(--primary)]/20 rounded-full text-[var(--primary)] text-sm font-medium mb-4">
                    Core Features
                </span>
                <h2 class="text-3xl md:text-4xl font-bold text-[var(--text)]">System Capabilities</h2>
                <p class="text-[var(--text-light)] mt-2 max-w-2xl mx-auto">Built to automate waste segregation, reduce pollution, and promote sustainability</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Feature 1: Automatic Detection & Segregation -->
                <div class="glass-card-light feature-card rounded-2xl p-6 border border-[var(--border)] slide-up delay-1">
                    <div class="w-14 h-14 rounded-xl bg-[var(--primary)]/10 border border-[var(--primary)]/20 flex items-center justify-center text-[var(--primary)] mb-4">
                        <i class="fas fa-robot text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--text)] mb-2">AI‑Powered Detection &amp; Segregation</h3>
                    <p class="text-sm text-[var(--text-light)] leading-relaxed">Uses an AI camera, capacitive, inductive, and ultrasonic sensors to automatically identify and sort biodegradable, plastic, and metal waste.</p>
                </div>
                
                <!-- Feature 2: Integrated Shredder -->
                <div class="glass-card-light feature-card rounded-2xl p-6 border border-[var(--border)] slide-up delay-2">
                    <div class="w-14 h-14 rounded-xl bg-[var(--accent)]/10 border border-[var(--accent)]/20 flex items-center justify-center text-[var(--accent)] mb-4">
                        <i class="fas fa-cogs text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--text)] mb-2">Integrated Shredder Mechanism</h3>
                    <p class="text-sm text-[var(--text-light)] leading-relaxed">Shreds biodegradable and plastic waste to improve composting and recycling efficiency, reducing volume and preparing materials for further processing.</p>
                </div>
                
                <!-- Feature 3: Real‑Time IoT Monitoring -->
                <div class="glass-card-light feature-card rounded-2xl p-6 border border-[var(--border)] slide-up delay-3">
                    <div class="w-14 h-14 rounded-xl bg-[var(--secondary)]/20 border border-[var(--secondary)]/30 flex items-center justify-center text-[var(--accent)] mb-4">
                        <i class="fas fa-wifi text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--text)] mb-2">Real‑Time IoT / Web Monitoring</h3>
                    <p class="text-sm text-[var(--text-light)] leading-relaxed">Web‑based dashboard displays fill levels, system performance, and historical data, accessible from anywhere with an internet connection.</p>
                </div>
                
                <!-- Feature 4: Solar‑Powered Operation -->
                <div class="glass-card-light feature-card rounded-2xl p-6 border border-[var(--border)] slide-up delay-1">
                    <div class="w-14 h-14 rounded-xl bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center text-yellow-500 mb-4">
                        <i class="fas fa-solar-panel text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--text)] mb-2">Solar‑Powered Energy System</h3>
                    <p class="text-sm text-[var(--text-light)] leading-relaxed">Equipped with solar panels for sustainable energy, reducing operational costs and carbon footprint while ensuring reliable off‑grid functionality.</p>
                </div>
                
                <!-- Feature 5: Fill‑Level Alerts & Logging -->
                <div class="glass-card-light feature-card rounded-2xl p-6 border border-[var(--border)] slide-up delay-2">
                    <div class="w-14 h-14 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-500 mb-4">
                        <i class="fas fa-bell text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--text)] mb-2">Fill‑Level Alerts &amp; Digital Logging</h3>
                    <p class="text-sm text-[var(--text-light)] leading-relaxed">Automated notifications when bins reach capacity, plus a digital waste logging database to track disposal patterns and support data‑driven decisions.</p>
                </div>
                
                <!-- Feature 6: Enterprise Security -->
                <div class="glass-card-light feature-card rounded-2xl p-6 border border-[var(--border)] slide-up delay-3">
                    <div class="w-14 h-14 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-500 mb-4">
                        <i class="fas fa-shield-alt text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-[var(--text)] mb-2">Role‑Based Access &amp; Security</h3>
                    <p class="text-sm text-[var(--text-light)] leading-relaxed">Encrypted data transmission, secure authentication, and role‑based access control ensure that only authorized users can view and manage the system.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================= -->
    <!-- TEAM SECTION                                 -->
    <!-- ============================================= -->
    <section id="team" class="py-20 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="text-center mb-12">
                <span class="inline-block px-4 py-1.5 bg-[var(--primary)]/10 border border-[var(--primary)]/20 rounded-full text-[var(--primary)] text-sm font-medium mb-4">
                    <i class="fas fa-users mr-2"></i> CT-ABS Development Team
                </span>
                <h2 class="text-3xl md:text-4xl font-bold text-[var(--text)]">Meet the Team</h2>
                <p class="text-[var(--text-light)] mt-2 max-w-2xl mx-auto">The brilliant minds behind CT-ABS</p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Member 1 -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-1">
                    <div class="member-avatar mx-auto bg-gradient-to-br from-[#8BC34A] to-[#4CAF50]">JB</div>
                    <h4 class="text-[var(--text)] font-semibold mt-3">Balagtas, Jelaine Rose B.</h4>
                    <p class="text-sm text-[var(--text-light)]">Team Member</p>
                </div>
                
                <!-- Member 2 -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-2">
                    <div class="member-avatar mx-auto bg-gradient-to-br from-[#2196F3] to-[#0D47A1]">JD</div>
                    <h4 class="text-[var(--text)] font-semibold mt-3">Dela Cruz, John Sherwin T.</h4>
                    <p class="text-sm text-[var(--text-light)]">Team Member</p>
                </div>
                
                <!-- Member 3 -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-3">
                    <div class="member-avatar mx-auto bg-gradient-to-br from-[#FF9800] to-[#E65100]">JF</div>
                    <h4 class="text-[var(--text)] font-semibold mt-3">Fajardo, Janine Esperanza E.</h4>
                    <p class="text-sm text-[var(--text-light)]">Team Member</p>
                </div>
                
                <!-- Member 4 -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-4">
                    <div class="member-avatar mx-auto bg-gradient-to-br from-[#9C27B0] to-[#4A148C]">JF</div>
                    <h4 class="text-[var(--text)] font-semibold mt-3">Fajardo, John Benett A.</h4>
                    <p class="text-sm text-[var(--text-light)]">Team Member</p>
                </div>
                
                <!-- Member 5 -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-1">
                    <div class="member-avatar mx-auto bg-gradient-to-br from-[#E91E63] to-[#880E4F]">DP</div>
                    <h4 class="text-[var(--text)] font-semibold mt-3">Payongayong, Deniel Louis</h4>
                    <p class="text-sm text-[var(--text-light)]">Team Member</p>
                </div>
                
                <!-- Member 6 -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-2">
                    <div class="member-avatar mx-auto bg-gradient-to-br from-[#00BCD4] to-[#006064]">JP</div>
                    <h4 class="text-[var(--text)] font-semibold mt-3">Pedeglorio, Jasper G.</h4>
                    <p class="text-sm text-[var(--text-light)]">Team Member</p>
                </div>
                
                <!-- Member 7 -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-3">
                    <div class="member-avatar mx-auto bg-gradient-to-br from-[#FF5722] to-[#BF360C]">AV</div>
                    <h4 class="text-[var(--text)] font-semibold mt-3">Velasquez, Albert M.</h4>
                    <p class="text-sm text-[var(--text-light)]">Team Member</p>
                </div>
                
                <!-- Team Badge -->
                <div class="glass-card-light rounded-2xl p-6 border border-[var(--border)] text-center slide-up delay-4 flex flex-col items-center justify-center">
                    <div class="w-14 h-14 rounded-xl bg-[var(--primary)]/10 border border-[var(--primary)]/20 flex items-center justify-center text-[var(--primary)] mb-3">
                        <i class="fas fa-code text-2xl"></i>
                    </div>
                    <h4 class="text-[var(--text)] font-semibold">7 Members</h4>
                    <p class="text-sm text-[var(--text-light)]">SmartBin Team</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================= -->
    <!-- CALL TO ACTION                               -->
    <!-- ============================================= -->
    <section class="py-20 px-6">
        <div class="max-w-4xl mx-auto glass-card rounded-3xl p-12 text-center border border-[var(--border)]">
            <h2 class="text-3xl md:text-4xl font-bold text-[var(--text)] mb-4">
                Access the CT-ABS Monitoring Dashboard
            </h2>
            <p class="text-[var(--text-light)] text-lg mb-8 max-w-2xl mx-auto leading-relaxed">
                Sign in with an authorized account to view real‑time bin status, segregation logs, and system performance.
            </p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="login.php" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Access Secure Login
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================= -->
    <!-- FOOTER – logo.png instead of trash icon      -->
    <!-- ============================================= -->
    <footer class="py-8 px-6 border-t border-[var(--border)]">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-4 text-sm text-[var(--text-light)]">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-[var(--primary)] to-[var(--accent)] flex items-center justify-center overflow-hidden">
                    <img src="logo.png" alt="CT-ABS Logo" class="w-full h-full object-cover">
                </div>
                <span class="text-[var(--text)] font-semibold">CT-ABS</span>
                <span class="text-[var(--border)]">|</span>
                <span>&copy; <?php echo date('Y'); ?> All rights reserved</span>
            </div>
            <div class="flex items-center gap-6">
                <span><i class="fas fa-lock mr-1"></i> Authorized access only</span>
            </div>
            <div class="flex items-center gap-4">
                <span><i class="fas fa-code mr-1"></i> v<?php echo APP_VERSION; ?></span>
                <span class="flex items-center gap-1">
                    <i class="fas fa-circle text-[6px] text-[var(--accent)]"></i> 
                    Operational
                </span>
            </div>
        </div>
    </footer>

    <script>
        // ============================================================
        // Theme Toggle
        // ============================================================
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        
        const savedTheme = localStorage.getItem('smartbin-theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateThemeUI(savedTheme);
        
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('smartbin-theme', next);
                updateThemeUI(next);
            });
        }
        
        function updateThemeUI(theme) {
            if (theme === 'dark') {
                if (themeIcon) themeIcon.className = 'fas fa-sun';
                if (themeLabel) themeLabel.textContent = 'Light';
            } else {
                if (themeIcon) themeIcon.className = 'fas fa-moon';
                if (themeLabel) themeLabel.textContent = 'Dark';
            }
        }
        
        // ============================================================
        // Smooth scroll for anchor links
        // ============================================================
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // ============================================================
        // Intersection Observer for fade-in animations
        // ============================================================
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                }
            });
        }, { threshold: 0.1 });
        
        document.querySelectorAll('.slide-up').forEach(el => {
            observer.observe(el);
        });
    </script>
</body>
</html>