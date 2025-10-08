<?php
if (!defined('CAMYNIA_LOADED')) {
    die('Direct access not permitted');
}

// Ensure user is logged in and not a supplier
if (!isLoggedIn() || isSupplier()) {
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
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= e($currentUser['company_name']) ?> - Camynia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 60px;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
        .sidebar-brand:hover {
            color: #fff;
        }
        .sidebar-menu {
            padding: 20px 0;
        }
        .sidebar-menu-item {
            position: relative;
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
            background: rgba(52, 152, 219, 0.2);
            color: #fff;
            border-left: 3px solid #3498db;
        }
        .sidebar-menu-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 18px;
        }
        .sidebar-dropdown {
            display: none;
            background: rgba(0,0,0,0.2);
        }
        .sidebar-dropdown.show {
            display: block;
        }
        .sidebar-dropdown .sidebar-menu-link {
            padding-left: 52px;
            font-size: 14px;
        }
        .sidebar-menu-link .dropdown-arrow {
            margin-left: auto;
            transition: transform 0.3s;
        }
        .sidebar-menu-link[aria-expanded="true"] .dropdown-arrow {
            transform: rotate(180deg);
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
        .user-menu {
            position: relative;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .breadcrumb {
            background: none;
            padding: 0;
            margin: 5px 0 0 0;
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
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        .badge {
            padding: 5px 10px;
            font-weight: 500;
        }
        .btn {
            padding: 8px 20px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="/staff/" class="sidebar-brand">
            <i class="bi bi-building"></i> Camynia
        </a>

        <div class="sidebar-menu">
            <div class="sidebar-menu-item">
                <a href="/staff/" class="sidebar-menu-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <!-- HR Module -->
            <div class="sidebar-menu-item">
                <a href="#" class="sidebar-menu-link" data-bs-toggle="collapse" data-bs-target="#hrMenu"
                   aria-expanded="<?= strpos($currentPage, 'hr-') === 0 ? 'true' : 'false' ?>">
                    <i class="bi bi-people"></i>
                    <span>Human Resources</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="sidebar-dropdown collapse <?= strpos($currentPage, 'hr-') === 0 ? 'show' : '' ?>" id="hrMenu">
                    <a href="/staff/hr-dashboard.php" class="sidebar-menu-link <?= $currentPage === 'hr-dashboard' ? 'active' : '' ?>">
                        <span>HR Dashboard</span>
                    </a>
                    <a href="/staff/hr-employees.php" class="sidebar-menu-link <?= $currentPage === 'hr-employees' ? 'active' : '' ?>">
                        <span>Employees (HRIS)</span>
                    </a>
                    <a href="/staff/hr-timeoff.php" class="sidebar-menu-link <?= $currentPage === 'hr-timeoff' ? 'active' : '' ?>">
                        <span>Time Off</span>
                    </a>
                    <a href="/staff/hr-attendance.php" class="sidebar-menu-link <?= $currentPage === 'hr-attendance' ? 'active' : '' ?>">
                        <span>Attendance</span>
                    </a>
                    <a href="/staff/hr-onboarding.php" class="sidebar-menu-link <?= $currentPage === 'hr-onboarding' ? 'active' : '' ?>">
                        <span>Onboarding</span>
                    </a>
                    <a href="/staff/hr-performance.php" class="sidebar-menu-link <?= $currentPage === 'hr-performance' ? 'active' : '' ?>">
                        <span>Performance</span>
                    </a>
                    <a href="/staff/hr-recruiting.php" class="sidebar-menu-link <?= $currentPage === 'hr-recruiting' ? 'active' : '' ?>">
                        <span>Recruiting</span>
                    </a>
                    <a href="/staff/hr-training.php" class="sidebar-menu-link <?= $currentPage === 'hr-training' ? 'active' : '' ?>">
                        <span>Training</span>
                    </a>
                    <a href="/staff/hr-compensation.php" class="sidebar-menu-link <?= $currentPage === 'hr-compensation' ? 'active' : '' ?>">
                        <span>Compensation</span>
                    </a>
                    <a href="/staff/hr-benefits.php" class="sidebar-menu-link <?= $currentPage === 'hr-benefits' ? 'active' : '' ?>">
                        <span>Benefits</span>
                    </a>
                    <a href="/staff/hr-analytics.php" class="sidebar-menu-link <?= $currentPage === 'hr-analytics' ? 'active' : '' ?>">
                        <span>Analytics</span>
                    </a>
                </div>
            </div>

            <!-- Knowledge Base -->
            <div class="sidebar-menu-item">
                <a href="#" class="sidebar-menu-link" data-bs-toggle="collapse" data-bs-target="#kbMenu"
                   aria-expanded="<?= strpos($currentPage, 'kb') === 0 ? 'true' : 'false' ?>">
                    <i class="bi bi-book"></i>
                    <span>Knowledge Base</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="sidebar-dropdown collapse <?= strpos($currentPage, 'kb') === 0 ? 'show' : '' ?>" id="kbMenu">
                    <a href="/staff/kb.php" class="sidebar-menu-link <?= $currentPage === 'kb' ? 'active' : '' ?>">
                        <span>All Articles</span>
                    </a>
                    <a href="/staff/kb-editor.php" class="sidebar-menu-link <?= $currentPage === 'kb-editor' ? 'active' : '' ?>">
                        <span>New Article</span>
                    </a>
                    <a href="/staff/kb-review.php" class="sidebar-menu-link <?= $currentPage === 'kb-review' ? 'active' : '' ?>">
                        <span>Review Queue</span>
                    </a>
                    <a href="/staff/kb-settings.php" class="sidebar-menu-link <?= $currentPage === 'kb-settings' ? 'active' : '' ?>">
                        <span>Categories & Settings</span>
                    </a>
                    <a href="/kb/" class="sidebar-menu-link" target="_blank">
                        <span>Public KB <i class="bi bi-box-arrow-up-right ms-1"></i></span>
                    </a>
                </div>
            </div>

            <!-- Self Service -->
            <div class="sidebar-menu-item">
                <a href="/staff/self-service.php" class="sidebar-menu-link <?= $currentPage === 'self-service' ? 'active' : '' ?>">
                    <i class="bi bi-person-circle"></i>
                    <span>Self Service</span>
                </a>
            </div>

            <!-- Helpdesk -->
            <div class="sidebar-menu-item">
                <a href="#" class="sidebar-menu-link" data-bs-toggle="collapse" data-bs-target="#helpdeskMenu"
                   aria-expanded="<?= strpos($currentPage, 'helpdesk/') !== false || in_array($currentPage, ['tickets', 'ticket', 'queues', 'priorities', 'templates', 'reports']) ? 'true' : 'false' ?>">
                    <i class="bi bi-headset"></i>
                    <span>Helpdesk</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="sidebar-dropdown collapse <?= strpos($currentPage, 'helpdesk/') !== false || in_array($currentPage, ['tickets', 'ticket', 'queues', 'priorities', 'templates', 'reports']) ? 'show' : '' ?>" id="helpdeskMenu">
                    <a href="/staff/helpdesk/tickets.php" class="sidebar-menu-link <?= $currentPage === 'tickets' ? 'active' : '' ?>">
                        <span>All Tickets</span>
                    </a>
                    <a href="/staff/helpdesk/queues.php" class="sidebar-menu-link <?= $currentPage === 'queues' ? 'active' : '' ?>">
                        <span>Queues</span>
                    </a>
                    <a href="/staff/helpdesk/priorities.php" class="sidebar-menu-link <?= $currentPage === 'priorities' ? 'active' : '' ?>">
                        <span>Priorities</span>
                    </a>
                    <a href="/staff/helpdesk/templates.php" class="sidebar-menu-link <?= $currentPage === 'templates' ? 'active' : '' ?>">
                        <span>Templates</span>
                    </a>
                    <a href="/staff/helpdesk/reports.php" class="sidebar-menu-link <?= $currentPage === 'reports' ? 'active' : '' ?>">
                        <span>Reports</span>
                    </a>
                </div>
            </div>

            <!-- Finance & Procurement -->
            <div class="sidebar-menu-item">
                <a href="#" class="sidebar-menu-link" data-bs-toggle="collapse" data-bs-target="#financeMenu"
                   aria-expanded="<?= strpos($currentPage, 'finance/') !== false ? 'true' : 'false' ?>">
                    <i class="bi bi-cash-stack"></i>
                    <span>Finance</span>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </a>
                <div class="sidebar-dropdown collapse <?= strpos($currentPage, 'finance/') !== false ? 'show' : '' ?>" id="financeMenu">
                    <a href="/staff/finance/vendors.php" class="sidebar-menu-link <?= $currentPage === 'vendors' ? 'active' : '' ?>">
                        <span>Vendors</span>
                    </a>
                    <a href="/staff/finance/catalog.php" class="sidebar-menu-link <?= $currentPage === 'catalog' ? 'active' : '' ?>">
                        <span>Catalog / Items</span>
                    </a>
                    <a href="/staff/finance/requisitions.php" class="sidebar-menu-link <?= $currentPage === 'requisitions' ? 'active' : '' ?>">
                        <span>Requisitions</span>
                    </a>
                    <a href="/staff/finance/purchase-orders.php" class="sidebar-menu-link <?= $currentPage === 'purchase-orders' ? 'active' : '' ?>">
                        <span>Purchase Orders</span>
                    </a>
                    <a href="/staff/finance/receiving.php" class="sidebar-menu-link <?= $currentPage === 'receiving' ? 'active' : '' ?>">
                        <span>Goods Receipt</span>
                    </a>
                    <a href="/staff/finance/invoices.php" class="sidebar-menu-link <?= $currentPage === 'invoices' ? 'active' : '' ?>">
                        <span>AP Invoices</span>
                    </a>
                    <a href="/staff/finance/expenses.php" class="sidebar-menu-link <?= $currentPage === 'expenses' ? 'active' : '' ?>">
                        <span>Expenses</span>
                    </a>
                    <a href="/staff/finance/inventory.php" class="sidebar-menu-link <?= $currentPage === 'inventory' ? 'active' : '' ?>">
                        <span>Inventory</span>
                    </a>
                    <a href="/staff/finance/assets.php" class="sidebar-menu-link <?= $currentPage === 'assets' ? 'active' : '' ?>">
                        <span>Fixed Assets</span>
                    </a>
                    <a href="/staff/finance/budgets.php" class="sidebar-menu-link <?= $currentPage === 'budgets' ? 'active' : '' ?>">
                        <span>Budgets</span>
                    </a>
                    <a href="/staff/finance/approvals.php" class="sidebar-menu-link <?= $currentPage === 'approvals' ? 'active' : '' ?>">
                        <span>Approval Rules</span>
                    </a>
                    <a href="/staff/finance/reports.php" class="sidebar-menu-link <?= $currentPage === 'finance-reports' ? 'active' : '' ?>">
                        <span>Finance Reports</span>
                    </a>
                </div>
            </div>

            <!-- Moderation -->
            <div class="sidebar-menu-item">
                <a href="/staff/blacklist.php" class="sidebar-menu-link <?= $currentPage === 'blacklist' ? 'active' : '' ?>">
                    <i class="bi bi-shield-exclamation"></i>
                    <span>Content Moderation</span>
                </a>
            </div>

            <!-- Settings -->
            <div class="sidebar-menu-item">
                <a href="/staff/settings.php" class="sidebar-menu-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
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
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <span class="badge bg-danger rounded-pill" style="font-size: 9px; padding: 2px 5px;">3</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#">No new notifications</a></li>
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="btn p-0 border-0 bg-transparent" type="button" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?= getInitials($currentUser['first_name'], $currentUser['last_name']) ?>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?= e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6></li>
                        <li><a class="dropdown-item" href="/staff/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="/staff/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
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
