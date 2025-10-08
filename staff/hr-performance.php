<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Performance Management';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

$view = $_GET['view'] ?? 'reviews';
$action = $_GET['action'] ?? 'list';
$error = '';

// Create tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS performance_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            reviewer_id INT UNSIGNED NOT NULL,
            review_period VARCHAR(50),
            review_date DATE,
            overall_rating DECIMAL(3,2),
            strengths TEXT,
            areas_for_improvement TEXT,
            goals TEXT,
            comments TEXT,
            status ENUM('draft', 'submitted', 'completed') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_company_employee (company_id, employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS goals (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            employee_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            target_date DATE,
            progress INT DEFAULT 0,
            status ENUM('not_started', 'in_progress', 'completed', 'cancelled') DEFAULT 'not_started',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            INDEX idx_company_employee (company_id, employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    // Tables might already exist
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request';
    } elseif (isset($_POST['create_review'])) {
        try {
            $stmt = $db->prepare("
                INSERT INTO performance_reviews (
                    company_id, employee_id, reviewer_id, review_period, review_date,
                    overall_rating, strengths, areas_for_improvement, goals, comments, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $_POST['employee_id'],
                $currentUser['id'],
                $_POST['review_period'],
                $_POST['review_date'],
                $_POST['overall_rating'],
                $_POST['strengths'],
                $_POST['areas_for_improvement'],
                $_POST['goals'],
                $_POST['comments'],
                $_POST['status']
            ]);

            setFlash('success', 'Performance review created successfully!');
            redirect('/staff/hr-performance.php');
        } catch (PDOException $e) {
            $error = 'Failed to create review: ' . $e->getMessage();
        }
    } elseif (isset($_POST['create_goal'])) {
        try {
            $stmt = $db->prepare("
                INSERT INTO goals (company_id, employee_id, title, description, target_date, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $_POST['employee_id'],
                $_POST['title'],
                $_POST['description'],
                $_POST['target_date'],
                $_POST['status']
            ]);

            setFlash('success', 'Goal created successfully!');
            redirect('/staff/hr-performance.php?view=goals');
        } catch (PDOException $e) {
            $error = 'Failed to create goal';
        }
    }
}

// Get data
try {
    if ($view === 'reviews') {
        $stmt = $db->prepare("
            SELECT pr.*,
                   e.id as emp_id,
                   u.first_name, u.last_name,
                   r.first_name as reviewer_first_name, r.last_name as reviewer_last_name
            FROM performance_reviews pr
            JOIN employees e ON pr.employee_id = e.id
            JOIN users u ON e.user_id = u.id
            JOIN users r ON pr.reviewer_id = r.id
            WHERE pr.company_id = ?
            ORDER BY pr.review_date DESC
        ");
        $stmt->execute([$companyId]);
        $reviews = $stmt->fetchAll();

    } elseif ($view === 'goals') {
        $stmt = $db->prepare("
            SELECT g.*,
                   e.id as emp_id,
                   u.first_name, u.last_name
            FROM goals g
            JOIN employees e ON g.employee_id = e.id
            JOIN users u ON e.user_id = u.id
            WHERE g.company_id = ?
            ORDER BY g.target_date ASC
        ");
        $stmt->execute([$companyId]);
        $goals = $stmt->fetchAll();
    }

    // Get employees for dropdowns
    $stmt = $db->prepare("
        SELECT e.id, u.first_name, u.last_name
        FROM employees e
        JOIN users u ON e.user_id = u.id
        WHERE e.company_id = ? AND e.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$companyId]);
    $employees = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Performance error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Performance Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Performance</li>
                </ol>
            </nav>
        </div>
        <div>
            <?php if ($view === 'reviews' && $action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> New Review
                </a>
            <?php elseif ($view === 'goals' && $action === 'list'): ?>
                <a href="?view=goals&action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Goal
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $view === 'reviews' ? 'active' : '' ?>" href="?view=reviews">
            <i class="bi bi-clipboard-check"></i> Reviews
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'goals' ? 'active' : '' ?>" href="?view=goals">
            <i class="bi bi-bullseye"></i> Goals & OKRs
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">
            <i class="bi bi-chat-dots"></i> 1-on-1s
        </a>
    </li>
</ul>

<?php if ($view === 'reviews' && $action === 'list'): ?>
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Performance Reviews</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($reviews)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-clipboard-check" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Performance Reviews</h4>
                    <p>Start tracking employee performance</p>
                    <a href="?action=add" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Create Review
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Review Period</th>
                                <th>Review Date</th>
                                <th>Overall Rating</th>
                                <th>Reviewer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?= getInitials($review['first_name'], $review['last_name']) ?>
                                            </div>
                                            <div class="fw-semibold"><?= e($review['first_name'] . ' ' . $review['last_name']) ?></div>
                                        </div>
                                    </td>
                                    <td><?= e($review['review_period']) ?></td>
                                    <td><?= formatDate($review['review_date']) ?></td>
                                    <td>
                                        <strong><?= number_format($review['overall_rating'], 1) ?></strong> / 5.0
                                    </td>
                                    <td><?= e($review['reviewer_first_name'] . ' ' . $review['reviewer_last_name']) ?></td>
                                    <td>
                                        <?php
                                        $statusColors = ['draft' => 'secondary', 'submitted' => 'warning', 'completed' => 'success'];
                                        $color = $statusColors[$review['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst($review['status']) ?></span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                data-bs-target="#reviewModal<?= $review['id'] ?>">
                                            <i class="bi bi-eye"></i> View
                                        </button>

                                        <!-- Review Modal -->
                                        <div class="modal fade" id="reviewModal<?= $review['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Performance Review - <?= e($review['first_name'] . ' ' . $review['last_name']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <strong>Review Period:</strong> <?= e($review['review_period']) ?><br>
                                                            <strong>Overall Rating:</strong> <?= number_format($review['overall_rating'], 1) ?> / 5.0
                                                        </div>
                                                        <hr>
                                                        <h6>Strengths</h6>
                                                        <p><?= nl2br(e($review['strengths'])) ?></p>
                                                        <h6>Areas for Improvement</h6>
                                                        <p><?= nl2br(e($review['areas_for_improvement'])) ?></p>
                                                        <h6>Goals for Next Period</h6>
                                                        <p><?= nl2br(e($review['goals'])) ?></p>
                                                        <?php if ($review['comments']): ?>
                                                            <h6>Additional Comments</h6>
                                                            <p><?= nl2br(e($review['comments'])) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($view === 'reviews' && $action === 'add'): ?>
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Create Performance Review</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee *</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- Select Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Review Period *</label>
                        <input type="text" name="review_period" class="form-control"
                               placeholder="e.g., Q1 2025, Annual 2024" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Review Date *</label>
                        <input type="date" name="review_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Overall Rating (1-5) *</label>
                        <input type="number" name="overall_rating" class="form-control"
                               min="1" max="5" step="0.1" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Strengths *</label>
                    <textarea name="strengths" class="form-control" rows="4" required
                              placeholder="What did the employee do well?"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Areas for Improvement *</label>
                    <textarea name="areas_for_improvement" class="form-control" rows="4" required
                              placeholder="What can the employee improve on?"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Goals for Next Period *</label>
                    <textarea name="goals" class="form-control" rows="4" required
                              placeholder="What goals should the employee work towards?"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Additional Comments</label>
                    <textarea name="comments" class="form-control" rows="3"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" name="create_review" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Create Review
                    </button>
                    <a href="/staff/hr-performance.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($view === 'goals' && $action === 'list'): ?>
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Goals & OKRs</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($goals)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-bullseye" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Goals Set</h4>
                    <p>Start setting goals for your team</p>
                    <a href="?view=goals&action=add" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Add Goal
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Goal</th>
                                <th>Target Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($goals as $goal): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($goal['first_name'] . ' ' . $goal['last_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e($goal['title']) ?></div>
                                        <small class="text-muted"><?= e(truncate($goal['description'], 80)) ?></small>
                                    </td>
                                    <td><?= formatDate($goal['target_date']) ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?= $goal['progress'] ?>%"
                                                 aria-valuenow="<?= $goal['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= $goal['progress'] ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'not_started' => 'secondary',
                                            'in_progress' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $color = $statusColors[$goal['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $goal['status'])) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($view === 'goals' && $action === 'add'): ?>
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Add Goal</h5>
        </div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <div class="mb-3">
                    <label class="form-label">Employee *</label>
                    <select name="employee_id" class="form-select" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Goal Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Target Date *</label>
                        <input type="date" name="target_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" name="create_goal" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Add Goal
                    </button>
                    <a href="/staff/hr-performance.php?view=goals" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
