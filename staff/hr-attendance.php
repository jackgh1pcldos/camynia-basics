<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Attendance & Timesheets';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];
$userId = $currentUser['id'];

// Create tables
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS time_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            clock_in DATETIME NOT NULL,
            clock_out DATETIME NULL,
            break_minutes INT DEFAULT 0,
            notes TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_company_user_date (company_id, user_id, clock_in)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    // Table might exist
}

// Handle clock in/out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        setFlash('danger', 'Invalid request');
    } elseif (isset($_POST['clock_in'])) {
        try {
            $stmt = $db->prepare("
                INSERT INTO time_entries (company_id, user_id, clock_in)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$companyId, $userId]);
            setFlash('success', 'Clocked in successfully!');
        } catch (PDOException $e) {
            setFlash('danger', 'Failed to clock in');
        }
        redirect('/staff/hr-attendance.php');
    } elseif (isset($_POST['clock_out'])) {
        try {
            $stmt = $db->prepare("
                UPDATE time_entries
                SET clock_out = NOW(), break_minutes = ?
                WHERE id = ? AND user_id = ? AND company_id = ?
            ");
            $stmt->execute([$_POST['break_minutes'] ?? 0, $_POST['entry_id'], $userId, $companyId]);
            setFlash('success', 'Clocked out successfully!');
        } catch (PDOException $e) {
            setFlash('danger', 'Failed to clock out');
        }
        redirect('/staff/hr-attendance.php');
    }
}

// Get current active entry
try {
    $stmt = $db->prepare("
        SELECT * FROM time_entries
        WHERE user_id = ? AND clock_out IS NULL
        ORDER BY clock_in DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $activeEntry = $stmt->fetch();

    // Get today's entries
    $stmt = $db->prepare("
        SELECT te.*, u.first_name, u.last_name
        FROM time_entries te
        JOIN users u ON te.user_id = u.id
        WHERE te.company_id = ? AND DATE(te.clock_in) = CURDATE()
        ORDER BY te.clock_in DESC
    ");
    $stmt->execute([$companyId]);
    $todayEntries = $stmt->fetchAll();

    // Get this week's entries
    $stmt = $db->prepare("
        SELECT te.*, u.first_name, u.last_name
        FROM time_entries te
        JOIN users u ON te.user_id = u.id
        WHERE te.company_id = ? AND WEEK(te.clock_in) = WEEK(CURDATE())
        ORDER BY te.clock_in DESC
    ");
    $stmt->execute([$companyId]);
    $weekEntries = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Attendance error: ' . $e->getMessage());
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>Attendance & Timesheets</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/hr-dashboard.php">HR</a></li>
                    <li class="breadcrumb-item active">Attendance</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<!-- Clock In/Out Card -->
<div class="row mb-4">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-body text-center p-5">
                <?php if ($activeEntry): ?>
                    <div class="mb-4">
                        <i class="bi bi-clock text-success" style="font-size: 48px;"></i>
                        <h4 class="mt-3">Currently Clocked In</h4>
                        <p class="text-muted">Started at <?= formatDateTime($activeEntry['clock_in'], 'g:i A') ?></p>
                        <div id="timer" class="h2 text-primary"></div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="entry_id" value="<?= $activeEntry['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Break Time (minutes)</label>
                            <input type="number" name="break_minutes" class="form-control" value="0" min="0">
                        </div>

                        <button type="submit" name="clock_out" class="btn btn-danger btn-lg">
                            <i class="bi bi-clock-history"></i> Clock Out
                        </button>
                    </form>

                    <script>
                    function updateTimer() {
                        const clockIn = new Date('<?= $activeEntry['clock_in'] ?>');
                        const now = new Date();
                        const diff = now - clockIn;

                        const hours = Math.floor(diff / 3600000);
                        const minutes = Math.floor((diff % 3600000) / 60000);
                        const seconds = Math.floor((diff % 60000) / 1000);

                        document.getElementById('timer').textContent =
                            `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }

                    setInterval(updateTimer, 1000);
                    updateTimer();
                    </script>
                <?php else: ?>
                    <div class="mb-4">
                        <i class="bi bi-clock text-secondary" style="font-size: 48px;"></i>
                        <h4 class="mt-3">Ready to Start Your Day?</h4>
                        <p class="text-muted"><?= date('l, F j, Y - g:i A') ?></p>
                    </div>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <button type="submit" name="clock_in" class="btn btn-success btn-lg">
                            <i class="bi bi-clock"></i> Clock In
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Today's Time Entries -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Today's Time Entries</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($todayEntries)): ?>
            <div class="p-4 text-center text-muted">
                <p>No time entries for today</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Break</th>
                            <th>Total Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayEntries as $entry): ?>
                            <?php
                            $totalHours = 0;
                            if ($entry['clock_out']) {
                                $diff = strtotime($entry['clock_out']) - strtotime($entry['clock_in']);
                                $totalHours = round(($diff / 3600) - ($entry['break_minutes'] / 60), 2);
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?= getInitials($entry['first_name'], $entry['last_name']) ?>
                                        </div>
                                        <div class="fw-semibold"><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></div>
                                    </div>
                                </td>
                                <td><?= formatDateTime($entry['clock_in'], 'g:i A') ?></td>
                                <td><?= $entry['clock_out'] ? formatDateTime($entry['clock_out'], 'g:i A') : '<span class="badge bg-success">Active</span>' ?></td>
                                <td><?= $entry['break_minutes'] ?> min</td>
                                <td><?= $entry['clock_out'] ? $totalHours . ' hrs' : '-' ?></td>
                                <td>
                                    <?php
                                    $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                                    $color = $statusColors[$entry['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($entry['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- This Week's Summary -->
<div class="card">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">This Week's Summary</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($weekEntries)): ?>
            <div class="p-4 text-center text-muted">
                <p>No time entries this week</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Total Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekEntries as $entry): ?>
                            <?php
                            $totalHours = 0;
                            if ($entry['clock_out']) {
                                $diff = strtotime($entry['clock_out']) - strtotime($entry['clock_in']);
                                $totalHours = round(($diff / 3600) - ($entry['break_minutes'] / 60), 2);
                            }
                            ?>
                            <tr>
                                <td><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></td>
                                <td><?= formatDate($entry['clock_in']) ?></td>
                                <td><?= formatDateTime($entry['clock_in'], 'g:i A') ?></td>
                                <td><?= $entry['clock_out'] ? formatDateTime($entry['clock_out'], 'g:i A') : '-' ?></td>
                                <td><?= $entry['clock_out'] ? $totalHours . ' hrs' : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= $statusColors[$entry['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($entry['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
