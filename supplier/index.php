<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$supplierId = $currentUser['supplier_id'];

// Get stats
try {
    // Total companies
    $stmt = $db->prepare("SELECT COUNT(*) FROM companies WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $totalCompanies = $stmt->fetchColumn();

    // Active companies
    $stmt = $db->prepare("SELECT COUNT(*) FROM companies WHERE supplier_id = ? AND status = 'active'");
    $stmt->execute([$supplierId]);
    $activeCompanies = $stmt->fetchColumn();

    // Total users across all companies
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM users u
        JOIN companies c ON u.company_id = c.id
        WHERE c.supplier_id = ?
    ");
    $stmt->execute([$supplierId]);
    $totalUsers = $stmt->fetchColumn();

    // Recent companies
    $stmt = $db->prepare("
        SELECT * FROM companies
        WHERE supplier_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$supplierId]);
    $recentCompanies = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Supplier dashboard error: ' . $e->getMessage());
}
?>

<div class="page-header mb-4">
    <h1>Supplier Dashboard</h1>
    <p class="text-muted">Welcome back, <?= e($currentUser['first_name']) ?>!</p>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                <i class="bi bi-building"></i>
            </div>
            <div class="stat-value"><?= $totalCompanies ?? 0 ?></div>
            <div class="stat-label">Total Companies</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?= $activeCompanies ?? 0 ?></div>
            <div class="stat-label">Active Companies</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= $totalUsers ?? 0 ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
</div>

<!-- Recent Companies -->
<div class="card">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent Companies</h5>
        <a href="/supplier/companies.php" class="btn btn-sm btn-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentCompanies)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-building" style="font-size: 64px;"></i>
                <h4 class="mt-3">No Companies Yet</h4>
                <p>Add your first company to get started</p>
                <a href="/supplier/companies.php?action=add" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-circle"></i> Add Company
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCompanies as $company): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($company['company_name']) ?></div>
                                    <small class="text-muted"><?= e($company['slug'] ?? 'No slug') ?></small>
                                </td>
                                <td><?= e($company['email']) ?></td>
                                <td>
                                    <?php
                                    $statusColors = ['active' => 'success', 'suspended' => 'warning', 'cancelled' => 'danger'];
                                    $color = $statusColors[$company['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($company['status']) ?></span>
                                </td>
                                <td><?= formatDate($company['created_at']) ?></td>
                                <td>
                                    <a href="/supplier/companies.php?action=view&id=<?= $company['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="/supplier/companies.php?action=add" class="btn btn-outline-primary w-100">
                            <i class="bi bi-building me-2"></i>Add Company
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="/supplier/users.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-people me-2"></i>Manage Users
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="/supplier/billing.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-credit-card me-2"></i>View Billing
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="/supplier/settings.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-gear me-2"></i>Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
