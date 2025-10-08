<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Training & Compliance';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Create tables
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS training_courses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            type ENUM('mandatory', 'optional') DEFAULT 'optional',
            duration_hours DECIMAL(5,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS training_enrollments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            assigned_date DATE,
            due_date DATE,
            completion_date DATE NULL,
            status ENUM('not_started', 'in_progress', 'completed', 'overdue') DEFAULT 'not_started',
            score DECIMAL(5,2) NULL,
            FOREIGN KEY (course_id) REFERENCES training_courses(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_enrollment (course_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    // Tables might exist
}

// Get data
try {
    $view = $_GET['view'] ?? 'courses';

    if ($view === 'courses') {
        $stmt = $db->prepare("
            SELECT c.*,
                   (SELECT COUNT(*) FROM training_enrollments WHERE course_id = c.id) as total_enrollments,
                   (SELECT COUNT(*) FROM training_enrollments WHERE course_id = c.id AND status = 'completed') as completed_enrollments
            FROM training_courses c
            WHERE c.company_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$companyId]);
        $courses = $stmt->fetchAll();

    } elseif ($view === 'enrollments') {
        $stmt = $db->prepare("
            SELECT te.*, tc.title as course_title, tc.type, tc.duration_hours,
                   u.first_name, u.last_name
            FROM training_enrollments te
            JOIN training_courses tc ON te.course_id = tc.id
            JOIN users u ON te.user_id = u.id
            WHERE tc.company_id = ?
            ORDER BY te.assigned_date DESC
        ");
        $stmt->execute([$companyId]);
        $enrollments = $stmt->fetchAll();
    }

} catch (PDOException $e) {
    error_log('Training error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Training & Compliance</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Training</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="?action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Course
            </a>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $view === 'courses' ? 'active' : '' ?>" href="?view=courses">
            <i class="bi bi-book"></i> Courses
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $view === 'enrollments' ? 'active' : '' ?>" href="?view=enrollments">
            <i class="bi bi-people"></i> Enrollments
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#">
            <i class="bi bi-award"></i> Certifications
        </a>
    </li>
</ul>

<?php if ($view === 'courses'): ?>
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Training Courses</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($courses)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-book" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Training Courses</h4>
                    <p>Create courses to train your team</p>
                    <a href="?action=add" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Add Course
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Course Title</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Enrollments</th>
                                <th>Completion Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <?php
                                $completionRate = $course['total_enrollments'] > 0
                                    ? round(($course['completed_enrollments'] / $course['total_enrollments']) * 100)
                                    : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($course['title']) ?></div>
                                        <small class="text-muted"><?= e(truncate($course['description'], 60)) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $course['type'] === 'mandatory' ? 'danger' : 'secondary' ?>">
                                            <?= ucfirst($course['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= $course['duration_hours'] ?> hours</td>
                                    <td><?= $course['total_enrollments'] ?> students</td>
                                    <td>
                                        <div class="progress" style="height: 20px; min-width: 100px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?= $completionRate ?>%"
                                                 aria-valuenow="<?= $completionRate ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= $completionRate ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" title="Assign">
                                                <i class="bi bi-person-plus"></i>
                                            </button>
                                            <button class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </button>
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

<?php elseif ($view === 'enrollments'): ?>
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Course Enrollments</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($enrollments)): ?>
                <div class="p-4 text-center text-muted">
                    <p>No enrollments yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Course</th>
                                <th>Type</th>
                                <th>Assigned</th>
                                <th>Due Date</th>
                                <th>Completion</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2">
                                                <?= getInitials($enrollment['first_name'], $enrollment['last_name']) ?>
                                            </div>
                                            <div class="fw-semibold"><?= e($enrollment['first_name'] . ' ' . $enrollment['last_name']) ?></div>
                                        </div>
                                    </td>
                                    <td><?= e($enrollment['course_title']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $enrollment['type'] === 'mandatory' ? 'danger' : 'secondary' ?>">
                                            <?= ucfirst($enrollment['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatDate($enrollment['assigned_date']) ?></td>
                                    <td><?= formatDate($enrollment['due_date']) ?></td>
                                    <td><?= $enrollment['completion_date'] ? formatDate($enrollment['completion_date']) : '-' ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'not_started' => 'secondary',
                                            'in_progress' => 'primary',
                                            'completed' => 'success',
                                            'overdue' => 'danger'
                                        ];
                                        $color = $statusColors[$enrollment['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $enrollment['status'])) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
