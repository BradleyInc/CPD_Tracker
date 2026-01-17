<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD Tracker - <?php echo $pageTitle ?? 'Professional Development'; ?></title>
    
    <!-- External CSS Files -->
    <link rel="stylesheet" href="css/style.css">
    <?php 
    // Load admin.css for admin/manager/partner pages
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    $admin_prefixes = ['admin', 'manager', 'partner', 'system_admin'];
    foreach ($admin_prefixes as $prefix) {
        if (strpos($current_page, $prefix) === 0) {
            echo '<link rel="stylesheet" href="css/admin.css">';
            break;
        }
    }
    ?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
            line-height: 1.6;
        }
        
        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e1e8ed;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
            text-decoration: none;
        }
        
        .main-nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-link {
            color: #5a6c7d;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
            position: relative;
        }
        
        .nav-link:hover {
            background: #f5f7fa;
            color: #667eea;
        }
        
        .nav-link.active {
            color: #667eea;
            background: #eef2ff;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown-toggle {
            background: none;
            border: none;
            color: #5a6c7d;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .dropdown-toggle:hover {
            background: #f5f7fa;
            color: #667eea;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            min-width: 200px;
            z-index: 1000;
            margin-top: 0.5rem;
            overflow: hidden;
            border: 1px solid #e1e8ed;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .dropdown-menu a:hover {
            background: #f8fafc;
            color: #667eea;
        }
        
        .dropdown-menu a:last-child {
            border-bottom: none;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }
        
        .user-menu-toggle {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .user-menu-toggle:hover {
            background: #f5f7fa;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        /* Form Modal Styles */
        .form-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .form-modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .form-modal h2 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4b5563;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #0da271;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #4b5563;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                height: auto;
                padding: 1rem 0;
            }
            
            .main-nav {
                margin-top: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .header-actions {
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="dashboard.php" class="logo">CPD Tracker</a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="main-nav">
                <a href="dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
                <a href="user_goals.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'user_goals.php') ? 'active' : ''; ?>">Goals</a>
                <a href="teams.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'teams.php') ? 'active' : ''; ?>">Teams</a>
                <a href="reports.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">Reports</a>
                
                <?php if (isset($_SESSION['user_role'])): ?>
                    <?php if ($_SESSION['user_role'] === 'super_admin'): ?>
                        <a href="system_admin_dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'system_admin_dashboard.php') ? 'active' : ''; ?>">🚀 System Admin</a>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin'): ?>
                        <a href="admin_dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php') ? 'active' : ''; ?>">Admin Panel</a>
                    <?php elseif ($_SESSION['user_role'] === 'manager'): ?>
                        <a href="manager_dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manager_dashboard.php') ? 'active' : ''; ?>">Manager Dashboard</a>
                    <?php elseif ($_SESSION['user_role'] === 'partner'): ?>
                        <a href="partner_dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'partner_dashboard.php') ? 'active' : ''; ?>">Partner Dashboard</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <!-- Import/Export Dropdown -->
                <div class="dropdown">
                    <button class="dropdown-toggle" id="importExportToggle">
                        📁 Import/Export
                        <span style="font-size: 0.8rem;">▼</span>
                    </button>
                    <div class="dropdown-menu" id="importExportDropdown">
                        <a href="import_ics.php">
                            <span>📅</span> Import from Calendar
                        </a>
                        <a href="import_csv.php">
                            <span>📊</span> Bulk Import CSV
                        </a>
                        <a href="#" onclick="event.preventDefault(); document.getElementById('exportModal').style.display='block';">
                            <span>📄</span> Export Records
                        </a>
                    </div>
                </div>

                <!-- User Dropdown -->
                <div class="user-menu">
                    <button class="user-menu-toggle" id="userMenuToggle">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                        <span style="font-size: 0.8rem;">▼</span>
                    </button>
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="profile.php">
                            <span>👤</span> Profile
                        </a>
                        <a href="settings.php">
                            <span>⚙️</span> Settings
                        </a>
                        <a href="logout.php">
                            <span>🚪</span> Logout
                        </a>
                    </div>
                    <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
                    <button class="btn-primary" onclick="document.getElementById('addEntryModal').style.display='block'">+ New Entry</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="container">

<!-- Export Modal (Global - Available on all pages) -->
<div id="exportModal" class="form-modal">
    <div class="form-modal-content">
        <span class="close" onclick="document.getElementById('exportModal').style.display='none'" style="position: absolute; right: 1rem; top: 1rem; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</span>
        <h2>Export CPD Records</h2>
        <form method="GET" id="exportForm">
            <div class="form-group">
                <label>Start Date:</label>
                <input type="date" name="start_date" id="exportStartDate">
            </div>
            <div class="form-group">
                <label>End Date:</label>
                <input type="date" name="end_date" id="exportEndDate">
            </div>
            <div class="form-group">
                <label>Category:</label>
                <select name="category" id="exportCategory">
                    <option value="all">All Categories</option>
                    <option value="Training">Training</option>
                    <option value="Conference">Conference</option>
                    <option value="Reading">Reading</option>
                    <option value="Online Course">Online Course</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" formaction="export_csv.php" class="btn btn-success">📊 Export CSV</button>
                <button type="submit" formaction="export_pdf.php" class="btn btn-danger">📄 Export PDF</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('exportModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Dropdown functionality
    function setupDropdown(toggleId, dropdownId) {
        const toggle = document.getElementById(toggleId);
        const dropdown = document.getElementById(dropdownId);
        
        if (!toggle || !dropdown) return;
        
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
            
            // Close other dropdowns
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            allDropdowns.forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('show');
                }
            });
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    });
    
    // Close modals when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.form-modal');
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    };
    
    // Set up all dropdowns
    document.addEventListener('DOMContentLoaded', function() {
        setupDropdown('importExportToggle', 'importExportDropdown');
        setupDropdown('userMenuToggle', 'userDropdown');
    });
</script>