<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Recruiting & ATS';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

$view = $_GET['view'] ?? 'jobs';
$action = $_GET['action'] ?? 'list';
$jobId = $_GET['id'] ?? null;
$error = '';

// First, let's create the tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS job_postings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            department_id INT UNSIGNED NULL,
            employment_type ENUM('full-time', 'part-time', 'contractor', 'intern') DEFAULT 'full-time',
            location VARCHAR(255),
            salary_min DECIMAL(12,2),
            salary_max DECIMAL(12,2),
            description TEXT,
            requirements TEXT,
            status ENUM('draft', 'open', 'closed', 'filled') DEFAULT 'draft',
            posted_date DATE,
            closing_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
            INDEX idx_company_status (company_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS job_applications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_posting_id INT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            resume_url VARCHAR(500),
            cover_letter TEXT,
            status ENUM('new', 'screening', 'interview', 'offer', 'rejected', 'hired') DEFAULT 'new',
            notes TEXT,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (job_posting_id) REFERENCES job_postings(id) ON DELETE CASCADE,
            INDEX idx_job_status (job_posting_id, status)
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
    } elseif (isset($_POST['create_job'])) {
        // Create job posting
        try {
            $stmt = $db->prepare("
                INSERT INTO job_postings (
                    company_id, title, department_id, employment_type, location,
                    salary_min, salary_max, description, requirements, status, posted_date, closing_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $_POST['title'],
                $_POST['department_id'] ?: null,
                $_POST['employment_type'],
                $_POST['location'],
                $_POST['salary_min'] ?: null,
                $_POST['salary_max'] ?: null,
                $_POST['description'],
                $_POST['requirements'],
                $_POST['status'],
                $_POST['posted_date'] ?: null,
                $_POST['closing_date'] ?: null
            ]);

            setFlash('success', 'Job posting created successfully!');
            redirect('/staff/hr-recruiting.php');
        } catch (PDOException $e) {
            $error = 'Failed to create job posting: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_job'])) {
        // Update job posting
        try {
            $stmt = $db->prepare("
                UPDATE job_postings SET
                    title = ?, department_id = ?, employment_type = ?, location = ?,
                    salary_min = ?, salary_max = ?, description = ?, requirements = ?,
                    status = ?, posted_date = ?, closing_date = ?
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $_POST['title'],
                $_POST['department_id'] ?: null,
                $_POST['employment_type'],
                $_POST['location'],
                $_POST['salary_min'] ?: null,
                $_POST['salary_max'] ?: null,
                $_POST['description'],
                $_POST['requirements'],
                $_POST['status'],
                $_POST['posted_date'] ?: null,
                $_POST['closing_date'] ?: null,
                $jobId,
                $companyId
            ]);

            setFlash('success', 'Job posting updated successfully!');
            redirect('/staff/hr-recruiting.php');
        } catch (PDOException $e) {
            $error = 'Failed to update job posting';
        }
    } elseif (isset($_POST['update_application_status'])) {
        // Update application status
        try {
            $stmt = $db->prepare("
                UPDATE job_applications
                SET status = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['status'],
                $_POST['notes'],
                $_POST['application_id']
            ]);

            setFlash('success', 'Application status updated!');
            redirect('/staff/hr-recruiting.php?view=applicants&job_id=' . $_POST['job_posting_id']);
        } catch (PDOException $e) {
            $error = 'Failed to update application status';
        }
    }
}

// Get data based on view
try {
    if ($view === 'jobs') {
        // Get job postings
        $stmt = $db->prepare("
            SELECT j.*, d.name as department_name,
                   (SELECT COUNT(*) FROM job_applications WHERE job_posting_id = j.id) as application_count
            FROM job_postings j
            LEFT JOIN departments d ON j.department_id = d.id
            WHERE j.company_id = ?
            ORDER BY j.created_at DESC
        ");
        $stmt->execute([$companyId]);
        $jobPostings = $stmt->fetchAll();

        // Get stats
        $stmt = $db->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'open'");
        $stmt->execute([$companyId]);
        $openJobs = $stmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM job_applications ja
            JOIN job_postings jp ON ja.job_posting_id = jp.id
            WHERE jp.company_id = ? AND ja.status = 'new'
        ");
        $stmt->execute([$companyId]);
        $newApplications = $stmt->fetchColumn();

        // If editing, get job details
        if ($action === 'edit' && $jobId) {
            $stmt = $db->prepare("SELECT * FROM job_postings WHERE id = ? AND company_id = ?");
            $stmt->execute([$jobId, $companyId]);
            $job = $stmt->fetch();

            if (!$job) {
                setFlash('danger', 'Job posting not found');
                redirect('/staff/hr-recruiting.php');
            }
        }

    } elseif ($view === 'applicants') {
        // Get applications for a specific job
        $jobId = $_GET['job_id'] ?? null;

        if ($jobId) {
            $stmt = $db->prepare("
                SELECT * FROM job_postings
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$jobId, $companyId]);
            $job = $stmt->fetch();

            $stmt = $db->prepare("
                SELECT * FROM job_applications
                WHERE job_posting_id = ?
                ORDER BY applied_at DESC
            ");
            $stmt->execute([$jobId]);
            $applications = $stmt->fetchAll();
        }
    }

    // Get departments for dropdowns
    $stmt = $db->prepare("SELECT id, name FROM departments WHERE company_id = ? ORDER BY name");
    $stmt->execute([$companyId]);
    $departments = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Recruiting error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Recruiting & ATS</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Recruiting</li>
                </ol>
            </nav>
        </div>
        <?php if ($view === 'jobs' && $action === 'list'): ?>
            <div>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Post Job
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $view === 'jobs' ? 'active' : '' ?>" href="?view=jobs">
            <i class="bi bi-briefcase"></i> Job Postings
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#" onclick="alert('Select a job to view applicants'); return false;">
            <i class="bi bi-people"></i> All Applicants
        </a>
    </li>
</ul>

<?php if ($view === 'jobs' && $action === 'list'): ?>
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                    <i class="bi bi-briefcase"></i>
                </div>
                <div class="stat-value"><?= $openJobs ?? 0 ?></div>
                <div class="stat-label">Open Positions</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                    <i class="bi bi-file-earmark-person"></i>
                </div>
                <div class="stat-value"><?= $newApplications ?? 0 ?></div>
                <div class="stat-label">New Applications</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                    <i class="bi bi-list-check"></i>
                </div>
                <div class="stat-value"><?= count($jobPostings ?? []) ?></div>
                <div class="stat-label">Total Postings</div>
            </div>
        </div>
    </div>

    <!-- Job Postings List -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Job Postings</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($jobPostings)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-briefcase" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Job Postings</h4>
                    <p>Create your first job posting to start recruiting</p>
                    <a href="?action=add" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle"></i> Post Job
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Department</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Salary Range</th>
                                <th>Status</th>
                                <th>Applications</th>
                                <th>Posted Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobPostings as $posting): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($posting['title']) ?></div>
                                    </td>
                                    <td><?= e($posting['department_name'] ?? '-') ?></td>
                                    <td><span class="badge bg-secondary"><?= ucfirst($posting['employment_type']) ?></span></td>
                                    <td><?= e($posting['location'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($posting['salary_min'] && $posting['salary_max']): ?>
                                            <?= formatCurrency($posting['salary_min']) ?> - <?= formatCurrency($posting['salary_max']) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'draft' => 'secondary',
                                            'open' => 'success',
                                            'closed' => 'warning',
                                            'filled' => 'info'
                                        ];
                                        $color = $statusColors[$posting['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $color ?>"><?= ucfirst($posting['status']) ?></span>
                                    </td>
                                    <td>
                                        <a href="?view=applicants&job_id=<?= $posting['id'] ?>" class="badge bg-primary">
                                            <?= $posting['application_count'] ?> applicants
                                        </a>
                                    </td>
                                    <td><?= $posting['posted_date'] ? formatDate($posting['posted_date']) : '-' ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?view=applicants&job_id=<?= $posting['id'] ?>" class="btn btn-outline-secondary" title="View Applicants">
                                                <i class="bi bi-people"></i>
                                            </a>
                                            <a href="?action=edit&id=<?= $posting['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
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

<?php elseif ($view === 'jobs' && ($action === 'add' || $action === 'edit')): ?>
    <!-- Add/Edit Job Form -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><?= $action === 'add' ? 'Post New Job' : 'Edit Job Posting' ?></h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                <h6 class="mb-3">Job Details</h6>
                <div class="row mb-3">
                    <div class="col-md-8">
                        <label class="form-label">Job Title *</label>
                        <input type="text" name="title" class="form-control"
                               value="<?= e($job['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <option value="draft" <?= isset($job) && $job['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="open" <?= isset($job) && $job['status'] == 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="closed" <?= isset($job) && $job['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                            <option value="filled" <?= isset($job) && $job['status'] == 'filled' ? 'selected' : '' ?>>Filled</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"
                                    <?= isset($job) && $job['department_id'] == $dept['id'] ? 'selected' : '' ?>>
                                    <?= e($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Employment Type *</label>
                        <select name="employment_type" class="form-select" required>
                            <option value="full-time" <?= isset($job) && $job['employment_type'] == 'full-time' ? 'selected' : '' ?>>Full-Time</option>
                            <option value="part-time" <?= isset($job) && $job['employment_type'] == 'part-time' ? 'selected' : '' ?>>Part-Time</option>
                            <option value="contractor" <?= isset($job) && $job['employment_type'] == 'contractor' ? 'selected' : '' ?>>Contractor</option>
                            <option value="intern" <?= isset($job) && $job['employment_type'] == 'intern' ? 'selected' : '' ?>>Intern</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control"
                               value="<?= e($job['location'] ?? '') ?>"
                               placeholder="e.g., Remote, New York, NY, Hybrid">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Minimum Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="salary_min" class="form-control" step="1000"
                                   value="<?= $job['salary_min'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Maximum Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="salary_max" class="form-control" step="1000"
                                   value="<?= $job['salary_max'] ?? '' ?>">
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Posted Date</label>
                        <input type="date" name="posted_date" class="form-control"
                               value="<?= $job['posted_date'] ?? date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Closing Date</label>
                        <input type="date" name="closing_date" class="form-control"
                               value="<?= $job['closing_date'] ?? '' ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Job Description *</label>
                    <textarea name="description" class="form-control" rows="6" required><?= e($job['description'] ?? '') ?></textarea>
                    <small class="text-muted">Describe the role, responsibilities, and what makes this position unique</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Requirements *</label>
                    <textarea name="requirements" class="form-control" rows="6" required><?= e($job['requirements'] ?? '') ?></textarea>
                    <small class="text-muted">List qualifications, skills, experience, and education requirements</small>
                </div>

                <hr class="my-4">

                <div class="d-flex gap-2">
                    <button type="submit" name="<?= $action === 'add' ? 'create_job' : 'update_job' ?>" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> <?= $action === 'add' ? 'Post Job' : 'Update Job' ?>
                    </button>
                    <a href="/staff/hr-recruiting.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($view === 'applicants' && $jobId): ?>
    <!-- Applicants List -->
    <?php if (!$job): ?>
        <div class="alert alert-danger">Job posting not found</div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4><?= e($job['title']) ?></h4>
                        <p class="text-muted mb-0">
                            <?= e($job['location'] ?? 'Location TBD') ?> •
                            <span class="badge bg-<?= $statusColors[$job['status']] ?? 'secondary' ?>"><?= ucfirst($job['status']) ?></span>
                        </p>
                    </div>
                    <a href="?view=jobs" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Jobs
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Applications (<?= count($applications ?? []) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($applications)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 64px;"></i>
                        <h4 class="mt-3">No Applications Yet</h4>
                        <p>Applications will appear here when candidates apply</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Contact</th>
                                    <th>Applied</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2">
                                                    <?= getInitials($app['first_name'], $app['last_name']) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small>
                                                <?= e($app['email']) ?><br>
                                                <?= e($app['phone'] ?? 'No phone') ?>
                                            </small>
                                        </td>
                                        <td><small><?= timeAgo($app['applied_at']) ?></small></td>
                                        <td>
                                            <?php
                                            $appStatusColors = [
                                                'new' => 'primary',
                                                'screening' => 'info',
                                                'interview' => 'warning',
                                                'offer' => 'success',
                                                'rejected' => 'danger',
                                                'hired' => 'success'
                                            ];
                                            $color = $appStatusColors[$app['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $color ?>"><?= ucfirst($app['status']) ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                    data-bs-target="#applicationModal<?= $app['id'] ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>

                                            <!-- Application Modal -->
                                            <div class="modal fade" id="applicationModal<?= $app['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>Email:</strong> <?= e($app['email']) ?></p>
                                                            <p><strong>Phone:</strong> <?= e($app['phone'] ?? 'N/A') ?></p>
                                                            <p><strong>Applied:</strong> <?= formatDateTime($app['applied_at']) ?></p>

                                                            <?php if ($app['cover_letter']): ?>
                                                                <hr>
                                                                <h6>Cover Letter</h6>
                                                                <p><?= nl2br(e($app['cover_letter'])) ?></p>
                                                            <?php endif; ?>

                                                            <hr>
                                                            <form method="post">
                                                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                                <input type="hidden" name="job_posting_id" value="<?= $jobId ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label">Status</label>
                                                                    <select name="status" class="form-select">
                                                                        <option value="new" <?= $app['status'] == 'new' ? 'selected' : '' ?>>New</option>
                                                                        <option value="screening" <?= $app['status'] == 'screening' ? 'selected' : '' ?>>Screening</option>
                                                                        <option value="interview" <?= $app['status'] == 'interview' ? 'selected' : '' ?>>Interview</option>
                                                                        <option value="offer" <?= $app['status'] == 'offer' ? 'selected' : '' ?>>Offer</option>
                                                                        <option value="rejected" <?= $app['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                                        <option value="hired" <?= $app['status'] == 'hired' ? 'selected' : '' ?>>Hired</option>
                                                                    </select>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Notes</label>
                                                                    <textarea name="notes" class="form-control" rows="3"><?= e($app['notes'] ?? '') ?></textarea>
                                                                </div>

                                                                <button type="submit" name="update_application_status" class="btn btn-primary">Update</button>
                                                            </form>
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
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
