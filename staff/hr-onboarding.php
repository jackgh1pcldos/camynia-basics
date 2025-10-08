<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Onboarding & Offboarding';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Create tables
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS onboarding_checklists (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            type ENUM('onboarding', 'offboarding') DEFAULT 'onboarding',
            status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
            start_date DATE,
            completion_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS onboarding_tasks (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            checklist_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            assigned_to INT UNSIGNED NULL,
            due_date DATE,
            completed BOOLEAN DEFAULT FALSE,
            completed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (checklist_id) REFERENCES onboarding_checklists(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    // Tables might exist
}

//Get onboarding/offboarding checklists
try {
    $view = $_GET['view'] ?? 'onboarding';

    $stmt = $db->prepare("
        SELECT oc.*, e.id as emp_id, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM onboarding_tasks WHERE checklist_id = oc.id) as total_tasks,
               (SELECT COUNT(*) FROM onboarding_tasks WHERE checklist_id = oc.id AND completed = 1) as completed_tasks
        FROM onboarding_checklists oc
        JOIN employees e ON oc.employee_id = e.id
        JOIN users u ON e.user_id = u.id
        WHERE oc.company_id = ? AND oc.type = ?
        ORDER BY oc.created_at DESC
    ");
    $stmt->execute([$companyId, $view]);
    $checklists = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Onboarding error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Onboarding & Offboarding</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Onboarding</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="?action=create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Checklist
            </a>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $view === 'onboarding' ? 'active' : '' ?>" href="?view=onboarding">
            <i class="bi bi-box-arrow-in-right"></i> Onboarding
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'offboarding' ? 'active' : '' ?>" href="?view=offboarding">
            <i class="bi bi-box-arrow-left"></i> Offboarding
        </a>
    </li>
</ul>

<!-- Checklists -->
<div class="row">
    <?php if (empty($checklists)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body p-5 text-center text-muted">
                    <i class="bi bi-clipboard-check" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No <?= ucfirst($view) ?> Checklists</h4>
                    <p>Create a checklist to track <?= $view ?> progress</p>
                    <a href="?action=create&type=<?= $view ?>" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Create Checklist
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($checklists as $checklist): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0"><?= e($checklist['first_name'] . ' ' . $checklist['last_name']) ?></h5>
                                <small class="text-muted">Started: <?= formatDate($checklist['start_date']) ?></small>
                            </div>
                            <?php
                            $statusColors = ['not_started' => 'secondary', 'in_progress' => 'primary', 'completed' => 'success'];
                            $color = $statusColors[$checklist['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $checklist['status'])) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Progress</span>
                                <span class="fw-semibold"><?= $checklist['completed_tasks'] ?> / <?= $checklist['total_tasks'] ?> tasks</span>
                            </div>
                            <?php
                            $progress = $checklist['total_tasks'] > 0 ? round(($checklist['completed_tasks'] / $checklist['total_tasks']) * 100) : 0;
                            ?>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-<?= $progress == 100 ? 'success' : 'primary' ?>" role="progressbar"
                                     style="width: <?= $progress ?>%" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= $progress ?>%
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6>Sample Tasks:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle text-success"></i> Complete paperwork</li>
                                <li><i class="bi bi-circle text-muted"></i> Set up workstation</li>
                                <li><i class="bi bi-circle text-muted"></i> IT equipment issued</li>
                                <li><i class="bi bi-circle text-muted"></i> Team introduction</li>
                            </ul>
                        </div>

                        <?php if ($checklist['completion_date']): ?>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle"></i> Completed on <?= formatDate($checklist['completion_date']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="?view=<?= $view ?>&id=<?= $checklist['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
