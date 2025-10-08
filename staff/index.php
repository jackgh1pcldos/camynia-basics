<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Get stats
$stats = [
    'total_employees' => 0,
    'active_employees' => 0,
    'pending_timeoff' => 0,
    'open_positions' => 0
];

try {
    // Total employees
    $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $stats['total_employees'] = $stmt->fetchColumn();

    // Active employees
    $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$companyId]);
    $stats['active_employees'] = $stmt->fetchColumn();

    // Pending time off requests
    $stmt = $db->prepare("SELECT COUNT(*) FROM time_off_requests WHERE company_id = ? AND status = 'pending'");
    $stmt->execute([$companyId]);
    $stats['pending_timeoff'] = $stmt->fetchColumn();

    // Recent employees
    $stmt = $db->prepare("
        SELECT e.*, u.first_name, u.last_name, u.email, u.avatar,
               d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.company_id = ?
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$companyId]);
    $recentEmployees = $stmt->fetchAll();

    // Upcoming time off
    $stmt = $db->prepare("
        SELECT t.*, u.first_name, u.last_name
        FROM time_off_requests t
        JOIN users u ON t.user_id = u.id
        WHERE t.company_id = ? AND t.status = 'approved'
        AND t.start_date >= CURDATE()
        ORDER BY t.start_date ASC
        LIMIT 5
    ");
    $stmt->execute([$companyId]);
    $upcomingTimeOff = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= $stats['total_employees'] ?></div>
            <div class="stat-label">Total Employees</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?= $stats['active_employees'] ?></div>
            <div class="stat-label">Active Employees</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?= $stats['pending_timeoff'] ?></div>
            <div class="stat-label">Pending Time Off</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                <i class="bi bi-briefcase"></i>
            </div>
            <div class="stat-value"><?= $stats['open_positions'] ?></div>
            <div class="stat-label">Open Positions</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Employees -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Recent Employees</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentEmployees)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 48px;"></i>
                        <p class="mt-2">No employees yet</p>
                        <a href="/staff/hr-employees.php" class="btn btn-sm btn-primary">Add Employee</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Hire Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentEmployees as $emp): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2">
                                                    <?= getInitials($emp['first_name'], $emp['last_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                                                    <small class="text-muted"><?= e($emp['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= e($emp['department_name'] ?? '-') ?></td>
                                        <td><?= formatDate($emp['hire_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white text-center">
                        <a href="/staff/hr-employees.php" class="text-decoration-none">View All Employees →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upcoming Time Off -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Upcoming Time Off</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcomingTimeOff)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-check" style="font-size: 48px;"></i>
                        <p class="mt-2">No upcoming time off</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Dates</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingTimeOff as $timeoff): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($timeoff['first_name'] . ' ' . $timeoff['last_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= ucfirst($timeoff['type']) ?></span>
                                        </td>
                                        <td>
                                            <small><?= formatDate($timeoff['start_date']) ?> - <?= formatDate($timeoff['end_date']) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white text-center">
                        <a href="/staff/hr-timeoff.php" class="text-decoration-none">View All Time Off →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="/staff/hr-employees.php?action=add" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus me-2"></i>Add Employee
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="/staff/hr-timeoff.php?action=add" class="btn btn-outline-success w-100">
                            <i class="bi bi-calendar-plus me-2"></i>Request Time Off
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="/staff/hr-recruiting.php?action=add" class="btn btn-outline-info w-100">
                            <i class="bi bi-briefcase me-2"></i>Post Job
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="/staff/hr-analytics.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-graph-up me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
