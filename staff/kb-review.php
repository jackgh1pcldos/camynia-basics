<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'KB Review Queue';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];
$userId = $currentUser['id'];

$error = '';

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request';
    } elseif (isset($_POST['approve_article'])) {
        try {
            $articleId = $_POST['article_id'];
            $db->beginTransaction();

            // Get current article
            $stmt = $db->prepare("SELECT * FROM kb_articles WHERE id = ? AND company_id = ?");
            $stmt->execute([$articleId, $companyId]);
            $article = $stmt->fetch();

            if ($article) {
                $newVersion = ($article['version'] ?? 0) + 1;

                // Create version snapshot
                $stmt = $db->prepare("
                    INSERT INTO kb_article_versions (article_id, version_number, title, content_html, content_markdown, published_by, change_summary)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $articleId, $newVersion, $article['title'], $article['content_html'],
                    $article['content_markdown'], $userId, $_POST['review_notes'] ?? 'Approved by reviewer'
                ]);

                // Update article
                $stmt = $db->prepare("
                    UPDATE kb_articles SET
                        status = 'published',
                        published_at = NOW(),
                        version = ?,
                        reviewed_by = ?,
                        reviewed_at = NOW(),
                        last_reviewed_at = NOW(),
                        next_review_at = IF(review_cycle_days IS NOT NULL, DATE_ADD(NOW(), INTERVAL review_cycle_days DAY), NULL)
                    WHERE id = ?
                ");
                $stmt->execute([$newVersion, $userId, $articleId]);

                // Log
                $stmt = $db->prepare("
                    INSERT INTO kb_audit_log (article_id, user_id, action, version_number, changes, ip_address)
                    VALUES (?, ?, 'reviewed', ?, ?, ?)
                ");
                $stmt->execute([
                    $articleId, $userId, $newVersion,
                    json_encode(['status' => 'approved', 'notes' => $_POST['review_notes'] ?? '']),
                    $_SERVER['REMOTE_ADDR']
                ]);

                $db->commit();
                setFlash('success', 'Article approved and published!');
            }
        } catch (PDOException $e) {
            $db->rollBack();
            setFlash('danger', 'Failed to approve article');
        }
        redirect('/staff/kb-review.php');
    } elseif (isset($_POST['request_changes'])) {
        try {
            $articleId = $_POST['article_id'];

            $stmt = $db->prepare("
                UPDATE kb_articles SET
                    status = 'draft',
                    reviewed_by = ?,
                    reviewed_at = NOW()
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$userId, $articleId, $companyId]);

            // Log
            $stmt = $db->prepare("
                INSERT INTO kb_audit_log (article_id, user_id, action, changes, ip_address)
                VALUES (?, ?, 'reviewed', ?, ?)
            ");
            $stmt->execute([
                $articleId, $userId,
                json_encode(['status' => 'changes_requested', 'notes' => $_POST['review_notes'] ?? '']),
                $_SERVER['REMOTE_ADDR']
            ]);

            setFlash('warning', 'Changes requested. Article sent back to draft.');
        } catch (PDOException $e) {
            setFlash('danger', 'Failed to request changes');
        }
        redirect('/staff/kb-review.php');
    }
}

// Get articles pending review
try {
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, u.email, c.name as category_name,
               DATEDIFF(NOW(), a.updated_at) as days_waiting
        FROM kb_articles a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN kb_categories c ON a.category_id = c.id
        WHERE (a.company_id = ? OR a.company_id IS NULL)
        AND a.status = 'in_review'
        ORDER BY a.updated_at ASC
    ");
    $stmt->execute([$companyId]);
    $pendingArticles = $stmt->fetchAll();
} catch (PDOException $e) {
    $pendingArticles = [];
}

// Get articles needing recertification
try {
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, c.name as category_name,
               DATEDIFF(NOW(), a.last_reviewed_at) as days_since_review
        FROM kb_articles a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN kb_categories c ON a.category_id = c.id
        WHERE (a.company_id = ? OR a.company_id IS NULL)
        AND a.status = 'published'
        AND a.next_review_at IS NOT NULL
        AND a.next_review_at <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY a.next_review_at ASC
    ");
    $stmt->execute([$companyId]);
    $needsReview = $stmt->fetchAll();
} catch (PDOException $e) {
    $needsReview = [];
}

// Get recently reviewed
try {
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, r.first_name as reviewer_first, r.last_name as reviewer_last
        FROM kb_articles a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN users r ON a.reviewed_by = r.id
        WHERE (a.company_id = ? OR a.company_id IS NULL)
        AND a.reviewed_at IS NOT NULL
        ORDER BY a.reviewed_at DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId]);
    $recentlyReviewed = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentlyReviewed = [];
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-eye-fill"></i> Review Queue</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/kb.php">Knowledge Base</a></li>
                    <li class="breadcrumb-item active">Review Queue</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="/staff/kb.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to KB
            </a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value"><?= count($pendingArticles) ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-value"><?= count($needsReview) ?></div>
            <div class="stat-label">Needs Recertification</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-value"><?= count($recentlyReviewed) ?></div>
            <div class="stat-label">Recently Reviewed</div>
        </div>
    </div>
</div>

<!-- Pending Review -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-hourglass-split text-warning"></i> Pending Review</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pendingArticles)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-check-circle" style="font-size: 64px; color: #28a745;"></i>
                <h4 class="mt-3">All Caught Up!</h4>
                <p>No articles waiting for review</p>
            </div>
        <?php else: ?>
            <?php foreach ($pendingArticles as $article): ?>
                <div class="border-bottom p-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="mb-2"><?= e($article['title']) ?></h5>

                            <div class="mb-2">
                                <span class="badge bg-warning">In Review</span>
                                <?php if ($article['category_name']): ?>
                                    <span class="badge bg-secondary"><?= e($article['category_name']) ?></span>
                                <?php endif; ?>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-layout-text-window"></i> <?= ucfirst(str_replace('-', ' ', $article['template_type'])) ?>
                                </span>
                            </div>

                            <?php if ($article['summary']): ?>
                                <p class="text-muted mb-2"><?= e(truncate($article['summary'], 200)) ?></p>
                            <?php endif; ?>

                            <small class="text-muted">
                                <i class="bi bi-person"></i> <?= e($article['first_name'] . ' ' . $article['last_name']) ?>
                                • <i class="bi bi-clock"></i> Submitted <?= timeAgo($article['updated_at']) ?>
                                <?php if ($article['days_waiting'] > 3): ?>
                                    <span class="badge bg-danger ms-2">Waiting <?= $article['days_waiting'] ?> days</span>
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="col-md-4 text-end">
                            <div class="btn-group-vertical btn-group-sm mb-2">
                                <a href="/staff/kb-editor.php?id=<?= $article['id'] ?>" class="btn btn-outline-primary" target="_blank">
                                    <i class="bi bi-eye"></i> Review Article
                                </a>
                                <a href="/kb/a/<?= e($article['slug']) ?>" class="btn btn-outline-secondary" target="_blank">
                                    <i class="bi bi-box-arrow-up-right"></i> Preview
                                </a>
                                <button class="btn btn-success" onclick="approveArticle(<?= $article['id'] ?>, '<?= e($article['title']) ?>')">
                                    <i class="bi bi-check-lg"></i> Approve & Publish
                                </button>
                                <button class="btn btn-warning" onclick="requestChanges(<?= $article['id'] ?>, '<?= e($article['title']) ?>')">
                                    <i class="bi bi-arrow-return-left"></i> Request Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Needs Recertification -->
<?php if (!empty($needsReview)): ?>
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> Needs Recertification</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Last Reviewed</th>
                        <th>Due Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($needsReview as $article): ?>
                        <tr>
                            <td>
                                <strong><?= e($article['title']) ?></strong>
                                <?php if (strtotime($article['next_review_at']) < time()): ?>
                                    <span class="badge bg-danger ms-2">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($article['category_name'] ?? '-') ?></td>
                            <td><?= e($article['first_name'] . ' ' . $article['last_name']) ?></td>
                            <td><?= formatDate($article['last_reviewed_at']) ?></td>
                            <td>
                                <?= formatDate($article['next_review_at']) ?>
                                <?php if (strtotime($article['next_review_at']) < time()): ?>
                                    <br><small class="text-danger"><?= abs($article['days_since_review'] - $article['review_cycle_days']) ?> days overdue</small>
                                <?php else: ?>
                                    <br><small class="text-muted">In <?= ceil((strtotime($article['next_review_at']) - time()) / 86400) ?> days</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/staff/kb-editor.php?id=<?= $article['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-pencil"></i> Review Now
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recently Reviewed -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recently Reviewed</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentlyReviewed)): ?>
            <div class="p-4 text-center text-muted">
                <p>No recently reviewed articles</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Author</th>
                            <th>Reviewer</th>
                            <th>Status</th>
                            <th>Reviewed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentlyReviewed as $article): ?>
                            <tr>
                                <td>
                                    <a href="/staff/kb-editor.php?id=<?= $article['id'] ?>">
                                        <?= e($article['title']) ?>
                                    </a>
                                </td>
                                <td><?= e($article['first_name'] . ' ' . $article['last_name']) ?></td>
                                <td><?= e($article['reviewer_first'] . ' ' . $article['reviewer_last']) ?></td>
                                <td>
                                    <?php
                                    $statusColors = ['draft' => 'secondary', 'published' => 'success', 'archived' => 'dark'];
                                    $color = $statusColors[$article['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= ucfirst($article['status']) ?></span>
                                </td>
                                <td><?= timeAgo($article['reviewed_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="article_id" id="approve_article_id">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Approve Article</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Approve and publish <strong id="approve_article_title"></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">Review Notes (optional)</label>
                        <textarea name="review_notes" class="form-control" rows="3" placeholder="Any comments for the author..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will publish the article and create a new version.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="approve_article" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Approve & Publish
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Changes Modal -->
<div class="modal fade" id="changesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="article_id" id="changes_article_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Request Changes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Request changes to <strong id="changes_article_title"></strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">What needs to be changed? *</label>
                        <textarea name="review_notes" class="form-control" rows="4" required
                                  placeholder="Please revise the introduction section for clarity..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> The article will be sent back to draft status for the author to revise.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="request_changes" class="btn btn-warning">
                        <i class="bi bi-arrow-return-left"></i> Request Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveArticle(id, title) {
    document.getElementById('approve_article_id').value = id;
    document.getElementById('approve_article_title').textContent = title;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function requestChanges(id, title) {
    document.getElementById('changes_article_id').value = id;
    document.getElementById('changes_article_title').textContent = title;
    new bootstrap.Modal(document.getElementById('changesModal')).show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
