<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Article History';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];
$userId = $currentUser['id'];

$articleId = $_GET['id'] ?? null;
$compareVersion = $_GET['compare'] ?? null;

if (!$articleId) {
    redirect('/staff/kb.php');
}

// Handle rollback
if (isset($_POST['rollback_version'])) {
    try {
        $versionId = $_POST['version_id'];

        $db->beginTransaction();

        // Get version content
        $stmt = $db->prepare("SELECT * FROM kb_article_versions WHERE id = ? AND article_id = ?");
        $stmt->execute([$versionId, $articleId]);
        $version = $stmt->fetch();

        if ($version) {
            // Update article with version content
            $stmt = $db->prepare("
                UPDATE kb_articles SET
                    title = ?, content_html = ?, content_markdown = ?,
                    version = version + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $version['title'],
                $version['content_html'],
                $version['content_markdown'],
                $articleId
            ]);

            // Log rollback
            $stmt = $db->prepare("
                INSERT INTO kb_audit_log (article_id, user_id, action, version_number, changes, ip_address)
                VALUES (?, ?, 'rolled_back', ?, ?, ?)
            ");
            $stmt->execute([
                $articleId,
                $userId,
                $version['version_number'],
                json_encode(['rolled_back_to' => $version['version_number']]),
                $_SERVER['REMOTE_ADDR']
            ]);

            $db->commit();
            setFlash('success', "Rolled back to version {$version['version_number']}");
        }

        redirect("/staff/kb-history.php?id=$articleId");
    } catch (PDOException $e) {
        $db->rollBack();
        setFlash('danger', 'Failed to rollback version');
        redirect("/staff/kb-history.php?id=$articleId");
    }
}

// Get article
try {
    $stmt = $db->prepare("
        SELECT a.*, u.first_name, u.last_name, c.name as category_name
        FROM kb_articles a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN kb_categories c ON a.category_id = c.id
        WHERE a.id = ? AND (a.company_id = ? OR a.company_id IS NULL)
    ");
    $stmt->execute([$articleId, $companyId]);
    $article = $stmt->fetch();

    if (!$article) {
        setFlash('danger', 'Article not found');
        redirect('/staff/kb.php');
    }
} catch (PDOException $e) {
    redirect('/staff/kb.php');
}

// Get version history
try {
    $stmt = $db->prepare("
        SELECT v.*, u.first_name, u.last_name
        FROM kb_article_versions v
        LEFT JOIN users u ON v.published_by = u.id
        WHERE v.article_id = ?
        ORDER BY v.version_number DESC
    ");
    $stmt->execute([$articleId]);
    $versions = $stmt->fetchAll();
} catch (PDOException $e) {
    $versions = [];
}

// Get audit log
try {
    $stmt = $db->prepare("
        SELECT l.*, u.first_name, u.last_name
        FROM kb_audit_log l
        JOIN users u ON l.user_id = u.id
        WHERE l.article_id = ?
        ORDER BY l.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$articleId]);
    $auditLog = $stmt->fetchAll();
} catch (PDOException $e) {
    $auditLog = [];
}
?>

<style>
.diff-added {
    background-color: #d4edda;
    color: #155724;
}
.diff-removed {
    background-color: #f8d7da;
    color: #721c24;
}
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}
.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}
.timeline-dot {
    position: absolute;
    left: -24px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #0d6efd;
}
</style>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-clock-history"></i> Article History</h1>
            <h5 class="text-muted"><?= e($article['title']) ?></h5>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/kb.php">Knowledge Base</a></li>
                    <li class="breadcrumb-item"><a href="/staff/kb-editor.php?id=<?= $articleId ?>">Article</a></li>
                    <li class="breadcrumb-item active">History</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="/staff/kb-editor.php?id=<?= $articleId ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Editor
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Versions List -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0">Version History</h6>
            </div>
            <div class="card-body">
                <?php if (empty($versions)): ?>
                    <div class="text-center text-muted p-4">
                        <i class="bi bi-clock-history" style="font-size: 48px;"></i>
                        <p class="mt-2">No published versions yet</p>
                        <small>Versions are created when you publish the article</small>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($versions as $v): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <span class="badge bg-primary">v<?= $v['version_number'] ?></span>
                                            <?php if ($v['version_number'] == $article['version']): ?>
                                                <span class="badge bg-success">Current</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 small"><?= e($v['title']) ?></p>
                                        <?php if ($v['change_summary']): ?>
                                            <p class="mb-1 small text-muted">
                                                <i class="bi bi-info-circle"></i> <?= e($v['change_summary']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            <i class="bi bi-person"></i> <?= e($v['first_name'] . ' ' . $v['last_name']) ?>
                                            • <i class="bi bi-clock"></i> <?= formatDate($v['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="btn-group-vertical btn-group-sm">
                                        <button class="btn btn-outline-secondary" onclick="viewVersion(<?= $v['id'] ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <?php if ($v['version_number'] != $article['version']): ?>
                                            <button class="btn btn-outline-primary" onclick="compareVersion(<?= $v['version_number'] ?>)">
                                                <i class="bi bi-shuffle"></i> Compare
                                            </button>
                                            <button class="btn btn-outline-warning" onclick="rollbackVersion(<?= $v['id'] ?>, <?= $v['version_number'] ?>)">
                                                <i class="bi bi-arrow-counterclockwise"></i> Restore
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Audit Log -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white">
                <h6 class="mb-0">Activity Log</h6>
            </div>
            <div class="card-body">
                <?php if (empty($auditLog)): ?>
                    <div class="text-center text-muted p-4">
                        <i class="bi bi-journal-text" style="font-size: 48px;"></i>
                        <p class="mt-2">No activity yet</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($auditLog as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="card">
                                    <div class="card-body py-2 px-3">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?= e($log['first_name'] . ' ' . $log['last_name']) ?></strong>
                                                <?php
                                                $actionIcons = [
                                                    'created' => 'plus-circle',
                                                    'updated' => 'pencil',
                                                    'published' => 'check-circle',
                                                    'unpublished' => 'x-circle',
                                                    'archived' => 'archive',
                                                    'deleted' => 'trash',
                                                    'reviewed' => 'eye',
                                                    'rolled_back' => 'arrow-counterclockwise'
                                                ];
                                                $icon = $actionIcons[$log['action']] ?? 'circle';
                                                ?>
                                                <span class="badge bg-secondary ms-2">
                                                    <i class="bi bi-<?= $icon ?>"></i> <?= ucfirst(str_replace('_', ' ', $log['action'])) ?>
                                                </span>
                                                <?php if ($log['version_number']): ?>
                                                    <span class="badge bg-info">v<?= $log['version_number'] ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted"><?= timeAgo($log['created_at']) ?></small>
                                        </div>
                                        <?php if ($log['changes']): ?>
                                            <small class="text-muted d-block mt-1">
                                                <?= e(json_encode(json_decode($log['changes']), JSON_PRETTY_PRINT)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Version Preview Modal -->
<div class="modal fade" id="versionPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Version Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="versionContent">
                <div class="text-center p-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rollback Confirmation Modal -->
<div class="modal fade" id="rollbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="version_id" id="rollback_version_id">
                <div class="modal-header">
                    <h5 class="modal-title">Restore Version</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will restore the article to version <strong id="rollback_version_num"></strong>.
                    </div>
                    <p>The current content will be replaced with the selected version. This action creates a new version.</p>
                    <p class="mb-0 text-muted">The current version will still be available in history.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="rollback_version" class="btn btn-warning">
                        <i class="bi bi-arrow-counterclockwise"></i> Restore Version
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewVersion(versionId) {
    const modal = new bootstrap.Modal(document.getElementById('versionPreviewModal'));
    modal.show();

    // In a real implementation, you'd fetch the version content via AJAX
    document.getElementById('versionContent').innerHTML = '<p class="p-4">Version content would be loaded here via AJAX</p>';
}

function compareVersion(versionNum) {
    window.location.href = `?id=<?= $articleId ?>&compare=${versionNum}`;
}

function rollbackVersion(versionId, versionNum) {
    document.getElementById('rollback_version_id').value = versionId;
    document.getElementById('rollback_version_num').textContent = versionNum;
    new bootstrap.Modal(document.getElementById('rollbackModal')).show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
