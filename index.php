<?php
require_once('includes/config.php'); 
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#3a57e8">
    <meta name="description" content="<?php echo web('webname'); ?> Jalwa Print Portal - High speed PVC card printing portal for Jan Seva Kendra and Cyber Cafes.">
    <title><?php echo web('webname'); ?> Jalwa Print Portal | Aadhaar, PAN, Voter ID, DL, RC Print</title>
    
    <link rel="icon" type="image/png" href="assets/img/Screensho.png">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- All CSS in One Place (Mobile + Desktop Optimized) -->
    <style>
        :root {
            --primary: #3a57e8;
            --primary-dark: #223cb6;
            --primary-light: #eff2fe;
            --secondary: #081135;
            --accent: #ff9900;
            --emerald: #10b981;
            --amber: #f59e0b;
            --purple: #8b5cf6;
            --cyan: #06b6d4;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --border-color: #e2e8f0;
            --card-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.05), 0 10px 15px -5px rgba(0, 0, 0, 0.02);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px; /* Adjust for sticky header */
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-up {
            animation: fadeUp 0.8s ease-out forwards;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }

        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }

        /* Helper Classes */
        .highlight { color: var(--primary); }
        .text-blue { color: var(--primary); }
        .text-amber { color: var(--amber); }
        .text-emerald { color: var(--emerald); }
        .text-cyan { color: var(--cyan); }
        .text-purple { color: var(--purple); }
        .text-white { color: #fff !important; }
        .text-white-80 { color: rgba(255,255,255,0.8); }
        .bg-amber { background-color: var(--amber) !important; color: #fff; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.65rem 1.6rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-align: center;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 8px 20px rgba(58, 87, 232, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(58, 87, 232, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            background: rgba(58, 87, 232, 0.05);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: #fff;
            transform: translateY(-3px);
        }

        .btn-lg {
            padding: 0.9rem 2.2rem;
            font-size: 1.05rem;
        }

        .btn-block { width: 100%; }

        .btn-whatsapp {
            background: linear-gradient(135deg, #25D366, #1ebd5a);
            color: #fff;
            box-shadow: 0 8px 20px rgba(37, 211, 102, 0.25);
        }

        .btn-whatsapp:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(37, 211, 102, 0.4);
        }

        /* Top Announcement Bar */
        .top-announcement-bar {
            background: var(--secondary);
            color: #fff;
            padding: 0.6rem 0;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .top-bar-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .top-status {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .status-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            padding: 0.2rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            letter-spacing: 0.5px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background: #34d399;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(52, 211, 153, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
        }

        .top-contact a {
            color: #e2e8f0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        .top-contact a:hover { color: #25D366; }

        /* Header Navigation */
        .navbar-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
            transition: var(--transition);
        }

        .navbar-header.scrolled {
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            background: rgba(255, 255, 255, 0.95);
        }

        .header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 80px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--secondary);
            letter-spacing: -0.5px;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(58, 87, 232, 0.3);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-links a {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: var(--transition);
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: var(--primary);
            transition: var(--transition);
        }

        .nav-links a:hover::after, .nav-links a.active::after { width: 100%; }
        .nav-links a:hover, .nav-links a.active { color: var(--primary); }

        .dropdown { position: relative; padding: 10px 0; }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: -20px;
            width: 260px;
            background: #fff;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-radius: 16px;
            padding: 1rem 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 100;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .dropdown-menu a::after { display: none; }
        
        .dropdown-menu a:hover {
            background: var(--primary-light);
            color: var(--primary);
            padding-left: 1.8rem;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--secondary);
        }

        /* Hero Section */
        .hero-section {
            padding: 6rem 0 5rem;
            background: radial-gradient(circle at top right, #eef2ff 0%, #f8fafc 100%);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(58,87,232,0.05) 0%, rgba(255,255,255,0) 70%);
            top: -100px;
            right: -100px;
            border-radius: 50%;
            z-index: 0;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            align-items: center;
            gap: 4rem;
            position: relative;
            z-index: 1;
        }

        .badge-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: #fff;
            color: var(--primary);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }

        .hero-text h1 {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.15;
            color: var(--secondary);
            margin-bottom: 1.2rem;
        }

        .hero-text p {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            max-width: 90%;
        }

        .quick-search-box {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            padding: 0.8rem;
            border-radius: 18px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.05);
            margin-bottom: 2.5rem;
            border: 1px solid rgba(255,255,255,0.8);
        }

        .quick-form {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .quick-select, .quick-input {
            padding: 0.9rem 1.2rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            outline: none;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .quick-select:focus, .quick-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(58, 87, 232, 0.1);
        }

        .quick-select {
            background: #f8fafc;
            font-weight: 600;
            color: var(--secondary);
        }

        .quick-input {
            flex: 1;
            min-width: 200px;
            background: #fff;
        }

        .hero-actions {
            display: flex;
            gap: 1.2rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .trust-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            padding-top: 1.8rem;
        }

        .trust-card {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .trust-card i {
            font-size: 1.8rem;
            color: var(--primary);
            background: var(--primary-light);
            padding: 10px;
            border-radius: 10px;
        }

        .trust-card strong {
            display: block;
            font-size: 0.95rem;
            color: var(--secondary);
        }

        .trust-card span {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Hero Visual / 3D Interactive Cards */
        .hero-visual {
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1000px;
        }

        .pvc-card-stack {
            position: relative;
            width: 100%;
            max-width: 400px;
            height: 320px;
            transform-style: preserve-3d;
        }

        .pvc-card {
            position: absolute;
            width: 320px;
            height: 200px;
            border-radius: 18px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
        }

        .card-aadhaar {
            top: 20px;
            left: 10px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            z-index: 2;
            transform: rotate(-5deg) translateZ(20px);
        }

        .card-pan {
            top: 90px;
            left: 50px;
            background: linear-gradient(135deg, #0f766e, #042f2e);
            z-index: 1;
            transform: rotate(4deg) translateZ(10px);
        }

        .pvc-card-stack:hover .card-aadhaar {
            transform: rotate(0deg) translateZ(40px) translateY(-20px);
            box-shadow: 0 30px 50px rgba(0,0,0,0.3);
        }

        .pvc-card-stack:hover .card-pan {
            transform: rotate(0deg) translateZ(10px) translateY(20px) translateX(20px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .pvc-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.6rem;
            border-radius: 8px;
            font-size: 0.7rem;
            letter-spacing: 1px;
        }

        .card-body {
            font-size: 1.35rem;
            letter-spacing: 3px;
            font-weight: 800;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .font-mono { font-family: 'Courier New', Courier, monospace; }

        .card-footer {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 600;
            opacity: 0.9;
        }

        /* Section Global */
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 1rem;
            letter-spacing: -0.5px;
        }

        .section-subtitle {
            text-align: center;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto 3.5rem;
            font-size: 1.05rem;
        }

        /* How it works */
        .how-it-works {
            padding: 6rem 0;
            background: var(--white);
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .process-card {
            background: #fff;
            padding: 2.5rem 1.5rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            position: relative;
            text-align: center;
            transition: var(--transition);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .process-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--cyan));
            opacity: 0;
            transition: var(--transition);
        }

        .process-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--card-shadow);
            border-color: transparent;
        }

        .process-card:hover::before { opacity: 1; }

        .step-num {
            position: absolute;
            top: -10px;
            right: 10px;
            font-size: 4rem;
            font-weight: 900;
            color: var(--bg-light);
            z-index: 0;
            transition: var(--transition);
        }

        .process-card:hover .step-num { color: var(--primary-light); }

        .step-icon {
            width: 70px;
            height: 70px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin: 0 auto 1.5rem;
            position: relative;
            z-index: 1;
            transition: var(--transition);
        }

        .process-card:hover .step-icon {
            background: var(--primary);
            color: #fff;
            transform: scale(1.1) rotate(5deg);
        }

        .process-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
            color: var(--secondary);
            position: relative;
            z-index: 1;
        }

        .process-card p {
            font-size: 0.9rem;
            color: var(--text-muted);
            position: relative;
            z-index: 1;
        }

        /* Services Grid */
        .services-section {
            padding: 6rem 0;
            background: #f8fafc;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2.5rem;
        }

        .service-card {
            background: var(--white);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.02);
            z-index: 1;
        }

        .service-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(58, 87, 232, 0.1);
            opacity: 0;
            transition: var(--transition);
            z-index: -1;
        }

        .service-card:hover {
            transform: translateY(-8px);
        }

        .service-card:hover::after { opacity: 1; }

        .service-icon {
            width: 65px;
            height: 65px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .service-card:hover .service-icon { transform: scale(1.1); }

        .icon-blue { background: #eff6ff; color: var(--primary); }
        .icon-amber { background: #fef3c7; color: var(--amber); }
        .icon-emerald { background: #d1fae5; color: var(--emerald); }
        .icon-orange { background: #ffedd5; color: #f97316; }
        .icon-purple { background: #f3e8ff; color: var(--purple); }
        .icon-white { background: rgba(255,255,255,0.15); color: #fff; backdrop-filter: blur(5px); }

        .service-card h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 0.8rem;
        }

        .service-card p {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .service-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #f1f5f9;
            padding-top: 1.2rem;
        }

        .price-badge {
            font-size: 0.85rem;
            font-weight: 800;
            background: #f8fafc;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            color: var(--text-main);
        }

        .link-btn {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            transition: var(--transition);
        }

        .link-btn i { transition: transform 0.3s ease; }
        .service-card:hover .link-btn i { transform: translateX(5px); }

        .csc-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
        }
        
        .csc-card::after { box-shadow: 0 20px 40px rgba(8, 17, 53, 0.4); }

        /* Contact Section */
        .contact-section {
            padding: 6rem 0;
            background: #fff;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 4rem;
            align-items: center;
        }

        .contact-info h3, .contact-form h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 1.2rem;
        }

        .contact-list { margin: 2rem 0 2.5rem; }

        .contact-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.2rem;
            font-size: 1.05rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .contact-list li i {
            color: var(--primary);
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .contact-form {
            background: #fff;
            padding: 3rem;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            border: 1px solid var(--border-color);
        }

        .form-group { margin-bottom: 1.5rem; }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 1rem 1.2rem;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            outline: none;
            font-size: 0.95rem;
            background: #f8fafc;
            transition: var(--transition);
        }

        .form-group input:focus, .form-group textarea:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(58, 87, 232, 0.1);
        }

        /* Footer */
        .footer {
            background: var(--secondary);
            color: #94a3b8;
            padding: 5rem 0 2rem;
            font-size: 0.95rem;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
            gap: 3rem;
            margin-bottom: 4rem;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 1.5rem;
        }

        .logo-icon-sm {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }

        .footer-social {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-icon {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.05);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .social-icon:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }

        .footer-column h3 {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .footer-links li { margin-bottom: 0.8rem; }
        .footer-links a { transition: var(--transition); }
        .footer-links a:hover { color: #fff; padding-left: 6px; }

        .footer-contact li {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .footer-whatsapp {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #25D366;
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-top: 0.8rem;
            transition: var(--transition);
        }
        
        .footer-whatsapp:hover { background: #1ebd5a; color: #fff; }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.9rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-bottom-links { display: flex; gap: 1.5rem; }
        .footer-bottom-links a:hover { color: #fff; }

        /* Floating WhatsApp & Back to Top */
        .floating-whatsapp {
            position: fixed;
            bottom: 30px;
            left: 30px;
            width: 60px;
            height: 60px;
            background: #25D366;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.4);
            z-index: 999;
            transition: var(--transition);
        }

        .floating-whatsapp:hover { transform: scale(1.1) rotate(5deg); }

        .back-to-top-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(58, 87, 232, 0.4);
            z-index: 999;
            transition: var(--transition);
        }

        .back-to-top-btn:hover { background: var(--primary-dark); transform: translateY(-3px); }

        /* ========================================================
            RESPONSIVE DESIGN (PC + TABLET + MOBILE)
        ======================================================== */
        @media (max-width: 1024px) {
            .hero-grid { grid-template-columns: 1fr; text-align: center; gap: 3rem; }
            .hero-text p { margin: 0 auto 2rem; }
            .badge-tag { margin: 0 auto 1.5rem; }
            .hero-actions, .quick-form { justify-content: center; }
            .trust-grid { justify-content: center; max-width: 600px; margin: 0 auto; }
            .services-grid { grid-template-columns: repeat(2, 1fr); }
            .process-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-top { grid-template-columns: 1fr 1fr; }
            .contact-grid { grid-template-columns: 1fr; gap: 2rem; }
        }

        @media (max-width: 768px) {
            .mobile-toggle { display: block; }
            .main-nav {
                position: absolute;
                top: 80px;
                left: -100%;
                width: 100%;
                background: #fff;
                box-shadow: 0 20px 30px rgba(0,0,0,0.1);
                transition: var(--transition);
                padding: 2rem 0;
                border-top: 1px solid var(--border-color);
            }
            .main-nav.active { left: 0; }
            .nav-links { flex-direction: column; align-items: flex-start; gap: 1.2rem; padding: 0 2rem; }
            .dropdown-menu { position: static; visibility: visible; opacity: 1; transform: none; box-shadow: none; border: none; padding-left: 1rem; width: 100%; border-left: 2px solid var(--border-color); border-radius: 0; margin-top: 10px; }
            .header-actions { display: none; }
            .hero-text h1 { font-size: 2.2rem; }
            .services-grid { grid-template-columns: 1fr; }
            .process-grid { grid-template-columns: 1fr; }
            .trust-grid { grid-template-columns: 1fr; gap: 1rem; }
            .footer-top { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; text-align: center; }
            .contact-form { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

    <!-- Top Announcement Bar -->
    <div class="top-announcement-bar">
        <div class="container top-bar-flex">
            <div class="top-status">
                <span class="status-badge"><span class="pulse-dot"></span> 24x7 ONLINE</span>
                <span class="top-msg">⚡ Aadhaar PVC Print, PAN Find & Voter Card Service Active</span>
            </div>
            <div class="top-contact">
                <a href="https://wa.me/91<?php echo web('phone'); ?>" target="_blank">
                    <i class="fab fa-whatsapp"></i> Helpline: +91 <?php echo web('phone'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Navigation Header -->
    <header class="navbar-header" id="main-header">
        <div class="container header-flex">
            <a href="index.php" class="brand-logo">
                <div class="logo-icon"><i class="fas fa-print"></i></div>
                <span><?php echo web('webname'); ?><span class="highlight">Portal</span></span>
            </a>
            
            <nav class="main-nav">
                <ul class="nav-links">
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li class="dropdown">
                        <a href="#services">Our Services <i class="fas fa-chevron-down drop-icon" style="font-size: 0.8rem; margin-left: 5px;"></i></a>
                        <div class="dropdown-menu">
                            <a href="/dashboard/login.php?type=aadhaar"><i class="fas fa-fingerprint text-blue"></i> Aadhaar PVC Print</a>
                            <a href="/dashboard/login.php?type=pan"><i class="fas fa-search text-amber"></i> PAN No. Find Portal</a>
                            <a href="/dashboard/login.php?type=voter"><i class="fas fa-vote-yea text-emerald"></i> Voter ID Card Print</a>
                            <a href="/dashboard/login.php?type=dl"><i class="fas fa-id-card text-cyan"></i> Driving License Print</a>
                            <a href="/dashboard/login.php?type=rc"><i class="fas fa-truck text-purple"></i> Vehicle RC Card Print</a>
                        </div>
                    </li>
                    <li><a href="#services">Pricing</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
            
            <div class="header-actions">
                <a href="dashboard/login.php" class="btn btn-outline">
                    <i class="fas fa-key"></i> Login
                </a>
                <a href="dashboard/register.php" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            </div>

            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle Menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <main id="main-content">
        <!-- HERO SECTION -->
        <section id="home" class="hero-section">
            <div class="container hero-grid">
                <div class="hero-text animate-up">
                    <div class="badge-tag">
                        <i class="fas fa-bolt"></i> जन सेवा केंद्र व साइबर कैफ़े के लिए #1 पोर्टल
                    </div>
                    
                    <h1>
                        <span class="highlight"><?php echo web('webname'); ?></span> Jalwa Print Portal
                    </h1>
                    
                    <p>
                        Print Aadhaar PVC Card, Find lost PAN Card numbers, EPIC Voter ID, Driving License & Vehicle RC in original HD card format. Instant 1-click PDF download engineered for cyber cafes across India.
                    </p>

                    <!-- Search / Quick Action -->
                    <div class="quick-search-box">
                        <form action="dashboard/login.php" method="GET" class="quick-form">
                            <select name="type" class="quick-select">
                                <option value="aadhaar">Aadhaar PVC Card</option>
                                <option value="pan">PAN Card Find & Print</option>
                                <option value="voter">EPIC Voter Card</option>
                                <option value="dl">Driving License</option>
                                <option value="rc">Vehicle RC Card</option>
                            </select>
                            <input type="text" name="query" placeholder="Enter Card / Document No..." class="quick-input" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-bolt"></i> Jalwa Print
                            </button>
                        </form>
                    </div>
                    
                    <div class="hero-actions">
                        <a href="dashboard/register.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-rocket"></i> Register Free Account
                        </a>
                        <a href="dashboard/login.php" class="btn btn-outline btn-lg">
                            <i class="fas fa-sign-in-alt"></i> Login Portal
                        </a>
                    </div>

                    <div class="trust-grid">
                        <div class="trust-card">
                            <i class="fas fa-shield-alt"></i>
                            <div>
                                <strong>100% Secure</strong>
                                <span>Auto-Crop Tech</span>
                            </div>
                        </div>
                        <div class="trust-card">
                            <i class="fas fa-download"></i>
                            <div>
                                <strong>Instant PDF</strong>
                                <span>CR80 PVC Size</span>
                            </div>
                        </div>
                        <div class="trust-card">
                            <i class="fas fa-rupee-sign"></i>
                            <div>
                                <strong>Lowest Rates</strong>
                                <span>From ₹5 / card</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Showcase Cards -->
                <div class="hero-visual animate-up" style="animation-delay: 0.2s;">
                    <div class="pvc-card-stack">
                        <div class="pvc-card card-aadhaar">
                            <div class="card-header">
                                <span><i class="fas fa-fingerprint"></i> Aadhaar PVC Card</span>
                                <span class="pvc-badge">HD ORIGINAL</span>
                            </div>
                            <div class="card-body font-mono">XXXX XXXX XXXX</div>
                            <div class="card-footer">
                                <span>RAMESH KUMAR VERMA</span>
                                <span>UIDAI Compliant</span>
                            </div>
                        </div>

                        <div class="pvc-card card-pan">
                            <div class="card-header">
                                <span><i class="fas fa-search"></i> PAN Find Portal</span>
                                <span class="pvc-badge bg-amber">INSTANT FIND</span>
                            </div>
                            <div class="card-body font-mono">ABCDE1234F</div>
                            <div class="card-footer">
                                <span>Income Tax Dept</span>
                                <span>Name + DOB Match</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- HOW IT WORKS -->
        <section id="how-it-works" class="how-it-works">
            <div class="container animate-up">
                <h2 class="section-title">How <span class="highlight"><?php echo web('webname'); ?></span> Works</h2>
                <p class="section-subtitle">Convert your documents to card format in just 4 easy steps without manual Photoshop editing.</p>

                <div class="process-grid">
                    <div class="process-card">
                        <div class="step-num">01</div>
                        <div class="step-icon"><i class="fas fa-file-signature"></i></div>
                        <h3>Enter Number</h3>
                        <p>Enter document number or upload official PDF to <?php echo web('webname'); ?> platform.</p>
                    </div>

                    <div class="process-card">
                        <div class="step-num">02</div>
                        <div class="step-icon"><i class="fas fa-crop-alt"></i></div>
                        <h3>Auto Crop Preview</h3>
                        <p>View automatic front-back PVC layout preview formatted to standard dimensions.</p>
                    </div>

                    <div class="process-card">
                        <div class="step-num">03</div>
                        <div class="step-icon"><i class="fas fa-wallet"></i></div>
                        <h3>Wallet Deduct</h3>
                        <p>Deduct print balance instantly from your retailer wallet. Instant UPI QR Recharge.</p>
                    </div>

                    <div class="process-card">
                        <div class="step-num">04</div>
                        <div class="step-icon"><i class="fas fa-file-pdf"></i></div>
                        <h3>Instant Download</h3>
                        <p>Download print-ready high-resolution PDF file ready for PVC thermal or inkjet printing.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SERVICES -->
        <section id="services" class="services-section">
            <div class="container animate-up">
                <h2 class="section-title">Our <span class="highlight">Services</span></h2>
                <p class="section-subtitle">Complete range of government document formatting and retrieval tools in one dashboard.</p>

                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-icon icon-blue"><i class="fas fa-fingerprint"></i></div>
                        <h3>Aadhaar Print</h3>
                        <p>Print Aadhaar in PVC card format with biometric or OTP verification. Barcode enhanced.</p>
                        <div class="service-footer">
                            <span class="price-badge">From ₹6 / print</span>
                            <a href="dashboard/login.php" class="link-btn">Print Now <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="service-card">
                        <div class="service-icon icon-amber"><i class="fas fa-vote-yea"></i></div>
                        <h3>Voter ID Card Print</h3>
                        <p>Print EPIC Voter ID card fetched directly from ECI national database with booth details.</p>
                        <div class="service-footer">
                            <span class="price-badge">From ₹5 / print</span>
                            <a href="dashboard/login.php" class="link-btn">Print Voter <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="service-card">
                        <div class="service-icon icon-emerald"><i class="fas fa-car"></i></div>
                        <h3>Driving License Print</h3>
                        <p>Print smart driving license cards via Parivahan/Sarathi database with complete categories.</p>
                        <div class="service-footer">
                            <span class="price-badge">From ₹8 / print</span>
                            <a href="dashboard/login.php" class="link-btn">Print DL <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="service-card">
                        <div class="service-icon icon-orange"><i class="fas fa-search"></i></div>
                        <h3>PAN No. Find Portal</h3>
                        <p>Find lost PAN numbers instantly using Name & DOB or Aadhaar number with NSDL layout.</p>
                        <div class="service-footer">
                            <span class="price-badge">From ₹5 / search</span>
                            <a href="dashboard/login.php" class="link-btn">Find PAN <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="service-card">
                        <div class="service-icon icon-purple"><i class="fas fa-truck"></i></div>
                        <h3>Vehicle RC Print</h3>
                        <p>Print vehicle Registration Certificates from Vahan 4.0 with fitness and insurance dates.</p>
                        <div class="service-footer">
                            <span class="price-badge">From ₹8 / print</span>
                            <a href="dashboard/login.php" class="link-btn">Print RC <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="service-card csc-card">
                        <div class="service-icon icon-white"><i class="fas fa-network-wired"></i></div>
                        <h3 class="text-white">CSC Special Portal</h3>
                        <p class="text-white-80">All-in-one package for Jan Seva Kendras and Cyber Cafes with wholesale print rates.</p>
                        <div class="service-footer">
                            <span class="price-badge bg-white text-blue">CSC Special</span>
                            <a href="dashboard/register.php" class="link-btn text-white">Join Partner <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONTACT -->
        <section id="contact" class="contact-section">
            <div class="container contact-grid animate-up">
                <div class="contact-info">
                    <h3>Contact Support</h3>
                    <p>Have queries regarding wallet recharge or retailer registration? Reach out to our dedicated support team anytime.</p>
                    <ul class="contact-list">
                        <li><i class="fas fa-phone"></i> +91 <?php echo web('phone'); ?></li>
                        <li><i class="fas fa-envelope"></i> Support@digitalshop</li>
                        <li><i class="fas fa-clock"></i> Monday-Saturday: 10 AM - 7 PM</li>
                    </ul>
                    <a href="https://wa.me/91<?php echo web('phone'); ?>" class="btn btn-whatsapp" target="_blank">
                        <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                    </a>
                </div>

                <form id="contactForm" class="contact-form">
                    <h3>Send Us a Message</h3>
                    <div class="form-group">
                        <input type="text" placeholder="Your Full Name *" required />
                    </div>
                    <div class="form-group">
                        <input type="email" placeholder="Your Email Address *" required />
                    </div>
                    <div class="form-group">
                        <input type="tel" placeholder="Your Phone Number *" required />
                    </div>
                    <div class="form-group">
                        <textarea rows="4" placeholder="Your Message or Inquiry *" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-paper-plane"></i> Send Message Now
                    </button>
                </form>
            </div>
        </section>
    </main>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="footer-top">
                <div class="footer-column">
                    <div class="footer-logo">
                        <div class="logo-icon-sm"><i class="fas fa-print"></i></div>
                        <span><?php echo web('webname'); ?><span class="highlight">Portal</span></span>
                    </div>
                    <p><?php echo web('webname'); ?> Aadhaar, PAN Card, Voter ID, Driving License and Vehicle RC instant PVC Card print generator.</p>
                    <div class="footer-social">
                        <a href="#" class="social-icon" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://wa.me/91<?php echo web('phone'); ?>" class="social-icon" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#services">Our Services</a></li>
                        <li><a href="#contact">Contact Support</a></li>
                        <li><a href="dashboard/login.php">Login</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Services</h3>
                    <ul class="footer-links">
                        <li><a href="dashboard/login.php?type=aadhaar">Aadhaar PVC Print</a></li>
                        <li><a href="dashboard/login.php?type=voter">Voter ID Card</a></li>
                        <li><a href="dashboard/login.php?type=dl">Driving License</a></li>
                        <li><a href="dashboard/login.php?type=pan">PAN Find Portal</a></li>
                        <li><a href="dashboard/login.php?type=rc">Vehicle RC Print</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Help & Support</h3>
                    <ul class="footer-contact">
                        <li><i class="fas fa-phone"></i> +91 <?php echo web('phone'); ?></li>
                        <li><i class="fas fa-envelope"></i> Support@digitalshop</li>
                        <li><i class="fas fa-clock"></i> 10 AM - 7 PM</li>
                        <li>
                            <a href="https://wa.me/91<?php echo web('phone'); ?>" class="footer-whatsapp">
                                <i class="fab fa-whatsapp"></i> WhatsApp Help
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo web('webname'); ?>. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="privacy-policy.php">Privacy Policy</a>
                    <a href="term-of-use.php">Terms of Use</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Floating Buttons -->
    <a href="https://wa.me/91<?php echo web('phone'); ?>" class="floating-whatsapp" target="_blank" aria-label="Contact WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <button id="back-to-top" class="back-to-top-btn" title="Back to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- JavaScript -->
    <script>
        // Mobile Navigation Toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const mainNav = document.querySelector('.main-nav');

        mobileToggle.addEventListener('click', () => {
            mainNav.classList.toggle('active');
            const icon = mobileToggle.querySelector('i');
            if (mainNav.classList.contains('active')) {
                icon.classList.replace('fa-bars', 'fa-times');
            } else {
                icon.classList.replace('fa-times', 'fa-bars');
            }
        });

        // Close menu on link click
        document.querySelectorAll('.nav-links a').forEach(link => {
            link.addEventListener('click', () => {
                mainNav.classList.remove('active');
                document.querySelector('#mobileToggle i')?.classList.replace('fa-times', 'fa-bars');
            });
        });

        // Header Scroll & Back to Top behavior
        window.addEventListener('scroll', function() {
            const header = document.getElementById('main-header');
            const btt = document.getElementById('back-to-top');
            
            if (window.scrollY > 40) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            if (window.scrollY > 300) {
                btt.style.display = 'flex';
            } else {
                btt.style.display = 'none';
            }
            
            // Simple reveal animation on scroll
            document.querySelectorAll('.animate-up:not(.animated)').forEach(el => {
                if (el.getBoundingClientRect().top < window.innerHeight - 50) {
                    el.style.animationPlayState = 'running';
                    el.classList.add('animated');
                }
            });
        });

        document.getElementById('back-to-top').addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Contact Form Handler
        document.getElementById('contactForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('धन्यवाद! आपका संदेश प्राप्त हो गया है, हमारी टीम जल्द ही आपसे संपर्क करेगी।');
            this.reset();
        });
        
        // --- SECURE ANTI-DEV TOOLS SCRIPT (CLEANED) ---
        // Prevent Right Click
        document.addEventListener('contextmenu', (e) => e.preventDefault());

        // Prevent Keyboard Shortcuts (F12, Ctrl+Shift+I/J/C, Ctrl+U)
        document.onkeydown = function(e) {
            if (e.keyCode === 123 || // F12
               (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J' || e.key === 'C')) || 
               (e.ctrlKey && e.key === 'u' || e.key === 'U')) {
                e.preventDefault();
                return false;
            }
        };
    </script>
    <!-- Disable DevTool Library (Optional as requested) -->
    <script disable-devtool-auto src="https://cdn.jsdelivr.net/npm/disable-devtool@latest"></script>
</body>
</html>