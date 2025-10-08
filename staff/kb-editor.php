<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'KB Editor';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];
$userId = $currentUser['id'];

$articleId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'edit';
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (isset($_POST['save_article'])) {
        try {
            $db->beginTransaction();

            $title = $_POST['title'];
            $slug = $_POST['slug'] ?: generateSlug($title);
            $contentHtml = $_POST['content_html'];
            $contentMarkdown = $_POST['content_markdown'] ?? null;

            if ($articleId) {
                // Update existing article
                $stmt = $db->prepare("
                    UPDATE kb_articles SET
                        title = ?, slug = ?, summary = ?, content_html = ?, content_markdown = ?,
                        category_id = ?, template_type = ?, visibility = ?, allowed_roles = ?,
                        meta_title = ?, meta_description = ?, keywords = ?,
                        review_cycle_days = ?, publish_at = ?, unpublish_at = ?,
                        updated_at = NOW()
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([
                    $title, $slug, $_POST['summary'], $contentHtml, $contentMarkdown,
                    $_POST['category_id'] ?: null, $_POST['template_type'], $_POST['visibility'],
                    json_encode($_POST['allowed_roles'] ?? []),
                    $_POST['meta_title'], $_POST['meta_description'], $_POST['keywords'],
                    $_POST['review_cycle_days'] ?: null, $_POST['publish_at'] ?: null, $_POST['unpublish_at'] ?: null,
                    $articleId, $companyId
                ]);

                // Log the update
                $stmt = $db->prepare("
                    INSERT INTO kb_audit_log (article_id, user_id, action, changes, ip_address)
                    VALUES (?, ?, 'updated', ?, ?)
                ");
                $stmt->execute([$articleId, $userId, json_encode(['title' => $title]), $_SERVER['REMOTE_ADDR']]);

                $success = 'Article updated successfully!';
            } else {
                // Create new article
                $stmt = $db->prepare("
                    INSERT INTO kb_articles (
                        company_id, author_id, title, slug, summary, content_html, content_markdown,
                        category_id, template_type, visibility, allowed_roles,
                        meta_title, meta_description, keywords,
                        review_cycle_days, publish_at, unpublish_at, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
                ");
                $stmt->execute([
                    $companyId, $userId, $title, $slug, $_POST['summary'], $contentHtml, $contentMarkdown,
                    $_POST['category_id'] ?: null, $_POST['template_type'], $_POST['visibility'],
                    json_encode($_POST['allowed_roles'] ?? []),
                    $_POST['meta_title'], $_POST['meta_description'], $_POST['keywords'],
                    $_POST['review_cycle_days'] ?: null, $_POST['publish_at'] ?: null, $_POST['unpublish_at'] ?: null
                ]);
                $articleId = $db->lastInsertId();

                // Log creation
                $stmt = $db->prepare("
                    INSERT INTO kb_audit_log (article_id, user_id, action, ip_address)
                    VALUES (?, ?, 'created', ?)
                ");
                $stmt->execute([$articleId, $userId, $_SERVER['REMOTE_ADDR']]);

                $success = 'Article created successfully!';
            }

            // Handle tags
            if (isset($_POST['tags'])) {
                // Delete existing tags
                $stmt = $db->prepare("DELETE FROM kb_article_tags WHERE article_id = ?");
                $stmt->execute([$articleId]);

                // Add new tags
                $tags = array_filter(array_map('trim', explode(',', $_POST['tags'])));
                foreach ($tags as $tagName) {
                    // Get or create tag
                    $tagSlug = generateSlug($tagName);
                    $stmt = $db->prepare("
                        INSERT INTO kb_tags (company_id, name, slug)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
                    ");
                    $stmt->execute([$companyId, $tagName, $tagSlug]);
                    $tagId = $db->lastInsertId();

                    // Link tag to article
                    $stmt = $db->prepare("INSERT IGNORE INTO kb_article_tags (article_id, tag_id) VALUES (?, ?)");
                    $stmt->execute([$articleId, $tagId]);
                }
            }

            $db->commit();
            redirect("/staff/kb-editor.php?id=$articleId&success=1");
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to save article: ' . $e->getMessage();
        }
    } elseif (isset($_POST['publish_article'])) {
        try {
            $db->beginTransaction();

            // Create version snapshot
            $stmt = $db->prepare("
                SELECT title, content_html, content_markdown, version
                FROM kb_articles WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$articleId, $companyId]);
            $current = $stmt->fetch();

            $newVersion = ($current['version'] ?? 0) + 1;

            $stmt = $db->prepare("
                INSERT INTO kb_article_versions (article_id, version_number, title, content_html, content_markdown, published_by, change_summary)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $articleId, $newVersion, $current['title'], $current['content_html'],
                $current['content_markdown'], $userId, $_POST['change_summary'] ?? 'Published'
            ]);

            // Update article status
            $stmt = $db->prepare("
                UPDATE kb_articles SET
                    status = 'published',
                    published_at = NOW(),
                    version = ?,
                    last_reviewed_at = NOW(),
                    next_review_at = IF(review_cycle_days IS NOT NULL, DATE_ADD(NOW(), INTERVAL review_cycle_days DAY), NULL)
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$newVersion, $articleId, $companyId]);

            // Log publish
            $stmt = $db->prepare("
                INSERT INTO kb_audit_log (article_id, user_id, action, version_number, ip_address)
                VALUES (?, ?, 'published', ?, ?)
            ");
            $stmt->execute([$articleId, $userId, $newVersion, $_SERVER['REMOTE_ADDR']]);

            $db->commit();
            setFlash('success', 'Article published successfully!');
            redirect("/staff/kb-editor.php?id=$articleId");
        } catch (PDOException $e) {
            $db->rollBack();
            $error = 'Failed to publish article: ' . $e->getMessage();
        }
    } elseif (isset($_POST['submit_review'])) {
        try {
            $stmt = $db->prepare("
                UPDATE kb_articles SET status = 'in_review' WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$articleId, $companyId]);

            $stmt = $db->prepare("
                INSERT INTO kb_audit_log (article_id, user_id, action, ip_address)
                VALUES (?, ?, 'updated', ?)
            ");
            $stmt->execute([$articleId, $userId, $_SERVER['REMOTE_ADDR']]);

            setFlash('success', 'Article submitted for review!');
            redirect("/staff/kb-editor.php?id=$articleId");
        } catch (PDOException $e) {
            $error = 'Failed to submit for review';
        }
    }
}

// Get article data
$article = null;
$tags = [];
if ($articleId) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, u.first_name, u.last_name,
                   r.first_name as reviewer_first_name, r.last_name as reviewer_last_name
            FROM kb_articles a
            LEFT JOIN users u ON a.author_id = u.id
            LEFT JOIN users r ON a.reviewed_by = r.id
            WHERE a.id = ? AND (a.company_id = ? OR a.company_id IS NULL)
        ");
        $stmt->execute([$articleId, $companyId]);
        $article = $stmt->fetch();

        if (!$article) {
            setFlash('danger', 'Article not found');
            redirect('/staff/kb.php');
        }

        // Get tags
        $stmt = $db->prepare("
            SELECT t.name FROM kb_tags t
            JOIN kb_article_tags at ON t.id = at.tag_id
            WHERE at.article_id = ?
        ");
        $stmt->execute([$articleId]);
        $tags = array_column($stmt->fetchAll(), 'name');
    } catch (PDOException $e) {
        error_log('KB editor error: ' . $e->getMessage());
    }
}

// Get categories
try {
    $stmt = $db->prepare("
        SELECT * FROM kb_categories
        WHERE company_id = ? OR company_id IS NULL
        ORDER BY sort_order, name
    ");
    $stmt->execute([$companyId]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$pageTitle = $article ? 'Edit: ' . $article['title'] : 'New Article';
?>

<style>
.editor-container {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    min-height: 400px;
}
#content-editor {
    min-height: 400px;
    padding: 1rem;
}
.toolbar {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.5rem;
}
.toolbar button {
    border: none;
    background: none;
    padding: 0.5rem;
    cursor: pointer;
    border-radius: 0.25rem;
}
.toolbar button:hover {
    background: #e9ecef;
}
.version-badge {
    font-size: 0.75rem;
}
</style>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>
                <i class="bi bi-pencil-square"></i>
                <?= $article ? 'Edit Article' : 'New Article' ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/kb.php">Knowledge Base</a></li>
                    <li class="breadcrumb-item active"><?= $article ? 'Edit' : 'New' ?></li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="/staff/kb.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success || isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $success ?: 'Changes saved successfully!' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="post" id="articleForm">
    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

    <div class="row">
        <!-- Main Editor -->
        <div class="col-lg-8">
            <!-- Title & Summary -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Article Title *</label>
                        <input type="text" name="title" id="title" class="form-control form-control-lg"
                               value="<?= e($article['title'] ?? '') ?>" required
                               placeholder="How to reset your password...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">URL Slug</label>
                        <div class="input-group">
                            <span class="input-group-text">/kb/a/</span>
                            <input type="text" name="slug" id="slug" class="form-control"
                                   value="<?= e($article['slug'] ?? '') ?>"
                                   placeholder="auto-generated-from-title">
                        </div>
                        <small class="text-muted">Leave blank to auto-generate</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Summary (Excerpt)</label>
                        <textarea name="summary" class="form-control" rows="2"
                                  placeholder="Brief description shown in article listings..."><?= e($article['summary'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Content Editor -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Content</h6>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active" data-mode="visual">
                                <i class="bi bi-eye"></i> Visual
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-mode="html">
                                <i class="bi bi-code"></i> HTML
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Toolbar -->
                    <div class="toolbar">
                        <button type="button" onclick="formatDoc('bold')" title="Bold">
                            <i class="bi bi-type-bold"></i>
                        </button>
                        <button type="button" onclick="formatDoc('italic')" title="Italic">
                            <i class="bi bi-type-italic"></i>
                        </button>
                        <button type="button" onclick="formatDoc('underline')" title="Underline">
                            <i class="bi bi-type-underline"></i>
                        </button>
                        <span class="mx-2">|</span>
                        <button type="button" onclick="formatDoc('formatBlock', 'h2')" title="Heading 2">
                            <strong>H2</strong>
                        </button>
                        <button type="button" onclick="formatDoc('formatBlock', 'h3')" title="Heading 3">
                            <strong>H3</strong>
                        </button>
                        <button type="button" onclick="formatDoc('formatBlock', 'p')" title="Paragraph">
                            <i class="bi bi-paragraph"></i>
                        </button>
                        <span class="mx-2">|</span>
                        <button type="button" onclick="formatDoc('insertUnorderedList')" title="Bullet List">
                            <i class="bi bi-list-ul"></i>
                        </button>
                        <button type="button" onclick="formatDoc('insertOrderedList')" title="Numbered List">
                            <i class="bi bi-list-ol"></i>
                        </button>
                        <span class="mx-2">|</span>
                        <button type="button" onclick="formatDoc('createLink')" title="Insert Link">
                            <i class="bi bi-link-45deg"></i>
                        </button>
                        <button type="button" onclick="insertImage()" title="Insert Image">
                            <i class="bi bi-image"></i>
                        </button>
                        <button type="button" onclick="insertCode()" title="Code Block">
                            <i class="bi bi-code-square"></i>
                        </button>
                        <span class="mx-2">|</span>
                        <button type="button" onclick="insertCallout('info')" title="Info Callout">
                            <i class="bi bi-info-circle"></i>
                        </button>
                        <button type="button" onclick="insertCallout('warning')" title="Warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </button>
                        <button type="button" onclick="insertCallout('tip')" title="Tip">
                            <i class="bi bi-lightbulb"></i>
                        </button>
                    </div>

                    <!-- Editor -->
                    <div id="content-editor" contenteditable="true" class="editor-container">
                        <?= $article['content_html'] ?? '<p>Start writing your article...</p>' ?>
                    </div>
                    <textarea name="content_html" id="content_html" style="display: none;"></textarea>
                    <textarea name="content_markdown" id="content_markdown" style="display: none;"></textarea>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Publish Box -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Publish</h6>
                </div>
                <div class="card-body">
                    <?php if ($article): ?>
                        <div class="mb-3">
                            <strong>Status:</strong>
                            <?php
                            $statusColors = ['draft' => 'secondary', 'in_review' => 'warning', 'published' => 'success', 'archived' => 'dark'];
                            $color = $statusColors[$article['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= ucfirst(str_replace('_', ' ', $article['status'])) ?></span>
                        </div>
                        <?php if ($article['version'] > 0): ?>
                            <div class="mb-3">
                                <strong>Version:</strong> <span class="badge bg-info"><?= $article['version'] ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <small class="text-muted">
                                Created: <?= formatDate($article['created_at']) ?><br>
                                Updated: <?= timeAgo($article['updated_at']) ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <button type="submit" name="save_article" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Draft
                        </button>

                        <?php if ($article && $article['status'] === 'draft'): ?>
                            <button type="submit" name="submit_review" class="btn btn-warning">
                                <i class="bi bi-send"></i> Submit for Review
                            </button>
                        <?php endif; ?>

                        <?php if ($article && in_array($article['status'], ['draft', 'in_review'])): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#publishModal">
                                <i class="bi bi-check-circle"></i> Publish Now
                            </button>
                        <?php endif; ?>

                        <?php if ($article): ?>
                            <a href="?id=<?= $article['id'] ?>&view=versions" class="btn btn-outline-secondary">
                                <i class="bi bi-clock-history"></i> View History
                            </a>
                            <a href="/kb/a/<?= e($article['slug']) ?>" class="btn btn-outline-info" target="_blank">
                                <i class="bi bi-eye"></i> Preview
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Category & Classification -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Category & Classification</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- No Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"
                                    <?= isset($article) && $article['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Template Type</label>
                        <select name="template_type" class="form-select">
                            <option value="general" <?= isset($article) && $article['template_type'] == 'general' ? 'selected' : '' ?>>General</option>
                            <option value="how-to" <?= isset($article) && $article['template_type'] == 'how-to' ? 'selected' : '' ?>>How-To Guide</option>
                            <option value="faq" <?= isset($article) && $article['template_type'] == 'faq' ? 'selected' : '' ?>>FAQ</option>
                            <option value="runbook" <?= isset($article) && $article['template_type'] == 'runbook' ? 'selected' : '' ?>>Runbook</option>
                            <option value="release-notes" <?= isset($article) && $article['template_type'] == 'release-notes' ? 'selected' : '' ?>>Release Notes</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control"
                               value="<?= e(implode(', ', $tags)) ?>"
                               placeholder="tag1, tag2, tag3">
                        <small class="text-muted">Comma-separated</small>
                    </div>
                </div>
            </div>

            <!-- Visibility & Access -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Visibility & Access</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Who can view this?</label>
                        <select name="visibility" class="form-select">
                            <option value="public" <?= isset($article) && $article['visibility'] == 'public' ? 'selected' : '' ?>>
                                🌍 Public (Anyone)
                            </option>
                            <option value="private_company" <?= isset($article) && $article['visibility'] == 'private_company' ? 'selected' : '' ?>>
                                🔒 Company Only
                            </option>
                            <option value="private_roles" <?= isset($article) && $article['visibility'] == 'private_roles' ? 'selected' : '' ?>>
                                👥 Specific Roles
                            </option>
                            <option value="global_supplier_only" <?= isset($article) && $article['visibility'] == 'global_supplier_only' ? 'selected' : '' ?>>
                                🏢 Supplier Only
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SEO Settings -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">SEO Settings</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control"
                               value="<?= e($article['meta_title'] ?? '') ?>"
                               placeholder="Leave blank to use article title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2"
                                  placeholder="SEO description..."><?= e($article['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keywords</label>
                        <input type="text" name="keywords" class="form-control"
                               value="<?= e($article['keywords'] ?? '') ?>"
                               placeholder="keyword1, keyword2">
                    </div>
                </div>
            </div>

            <!-- Review Cycle -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Review & Scheduling</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Review Cycle (Days)</label>
                        <input type="number" name="review_cycle_days" class="form-control"
                               value="<?= e($article['review_cycle_days'] ?? '') ?>"
                               placeholder="90">
                        <small class="text-muted">How often to review this article</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Publish At</label>
                        <input type="datetime-local" name="publish_at" class="form-control"
                               value="<?= $article['publish_at'] ? date('Y-m-d\TH:i', strtotime($article['publish_at'])) : '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unpublish At</label>
                        <input type="datetime-local" name="unpublish_at" class="form-control"
                               value="<?= $article['unpublish_at'] ? date('Y-m-d\TH:i', strtotime($article['unpublish_at'])) : '' ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Publish Modal -->
<?php if ($article && in_array($article['status'], ['draft', 'in_review'])): ?>
<div class="modal fade" id="publishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Publish Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You're about to publish <strong><?= e($article['title']) ?></strong>.</p>
                    <p>This will create version <strong><?= ($article['version'] ?? 0) + 1 ?></strong>.</p>

                    <div class="mb-3">
                        <label class="form-label">Change Summary</label>
                        <textarea name="change_summary" class="form-control" rows="3"
                                  placeholder="Describe what changed in this version..."><?php if ($article['version'] == 0): ?>Initial publication<?php endif; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="publish_article" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Publish Article
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-save content to hidden textarea before submit
document.getElementById('articleForm').addEventListener('submit', function() {
    document.getElementById('content_html').value = document.getElementById('content-editor').innerHTML;
});

// Auto-generate slug from title
document.getElementById('title').addEventListener('blur', function() {
    const slugField = document.getElementById('slug');
    if (!slugField.value) {
        slugField.value = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
});

// Editor functions
function formatDoc(cmd, value = null) {
    document.execCommand(cmd, false, value);
    document.getElementById('content-editor').focus();
}

function insertImage() {
    const url = prompt('Enter image URL:');
    if (url) {
        document.execCommand('insertImage', false, url);
    }
}

function insertCode() {
    const code = prompt('Enter code:');
    if (code) {
        document.execCommand('insertHTML', false, '<pre><code>' + code + '</code></pre>');
    }
}

function insertCallout(type) {
    const colors = {
        'info': '#0dcaf0',
        'warning': '#ffc107',
        'tip': '#198754'
    };
    const icons = {
        'info': 'info-circle',
        'warning': 'exclamation-triangle',
        'tip': 'lightbulb'
    };
    const text = prompt('Callout text:');
    if (text) {
        const html = `<div style="padding: 1rem; border-left: 4px solid ${colors[type]}; background: ${colors[type]}22; margin: 1rem 0;">
            <i class="bi bi-${icons[type]}"></i> <strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${text}
        </div>`;
        document.execCommand('insertHTML', false, html);
    }
}

// Editor mode toggle
document.querySelectorAll('[data-mode]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('[data-mode]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        const editor = document.getElementById('content-editor');
        if (this.dataset.mode === 'html') {
            const html = editor.innerHTML;
            editor.textContent = html;
            editor.contentEditable = 'true';
        } else {
            const text = editor.textContent;
            editor.innerHTML = text;
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
