<?php
if (!defined('CAMYNIA_LOADED')) {
    die('Direct access not permitted');
}

// Ensure user is logged in and is a supplier
if (!isLoggedIn() || !isSupplier()) {
    redirect('/login.php');
}

$currentUser = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - Supplier Portal - Camynia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 60px;
            --supplier-primary: #9b59b6;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: #2c3e50;
            color: #ecf0f1;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar-brand {
            padding: 20px;
            font-size: 24px;
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            text-decoration: none;
            display: block;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .sidebar-menu-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar-menu-link:hover {
            background: rgba(255,255,255,0.05);
            color: #fff;
        }
        .sidebar-menu-link.active {
            background: rgba(155, 89, 182, 0.2);
            color: #fff;
            border-left: 3px solid var(--supplier-primary);
        }
        .sidebar-menu-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 18px;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        .topbar {
            height: var(--header-height);
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        .content-wrapper {
            padding: 30px;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--supplier-primary) 0%, #8e44ad 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .stat-card {
            padding: 20px;
        }
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-card .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        .badge-supplier {
            background: var(--supplier-primary);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="/supplier/" class="sidebar-brand">
            <i class="bi bi-shield-check"></i> Supplier Portal
        </a>

        <div class="sidebar-menu">
            <a href="/supplier/" class="sidebar-menu-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <a href="/supplier/companies.php" class="sidebar-menu-link <?= $currentPage === 'companies' ? 'active' : '' ?>">
                <i class="bi bi-building"></i>
                <span>Companies</span>
            </a>

            <a href="/supplier/users.php" class="sidebar-menu-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>

            <a href="/supplier/billing.php" class="sidebar-menu-link <?= $currentPage === 'billing' ? 'active' : '' ?>">
                <i class="bi bi-credit-card"></i>
                <span>Billing</span>
            </a>

            <a href="/supplier/settings.php" class="sidebar-menu-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="d-flex align-items-center">
                <h6 class="mb-0 text-muted"><?= e($currentUser['company_name']) ?></h6>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn p-0 border-0 bg-transparent" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?= getInitials($currentUser['first_name'], $currentUser['last_name']) ?>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6></li>
                        <li><a class="dropdown-item" href="/supplier/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="/supplier/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <div class="content-wrapper">
            <?php
            $flash = getFlash();
            if ($flash):
            ?>
                <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
