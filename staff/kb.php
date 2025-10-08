<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Knowledge Base';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];
$userId = $currentUser['id'];

$view = $_GET['view'] ?? 'all';
$categoryId = $_GET['category'] ?? null;
$search = $_GET['q'] ?? '';

// Get all categories for sidebar
try {
    $stmt = $db->prepare("
        SELECT id, name, slug, icon, color, parent_id,
               (SELECT COUNT(*) FROM kb_articles WHERE category_id = kb_categories.id AND company_id = ?) as article_count
        FROM kb_categories
        WHERE company_id = ? OR company_id IS NULL
        ORDER BY sort_order, name
    ");
    $stmt->execute([$companyId, $companyId]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('KB categories error: ' . $e->getMessage());
    $categories = [];
}

// Get articles based on filters
try {
    $sql = "
        SELECT a.*, u.first_name, u.last_name, c.name as category_name, c.color as category_color,
               (SELECT COUNT(*) FROM kb_article_views WHERE article_id = a.id) as total_views
        FROM kb_articles a
        LEFT JOIN users u ON a.author_id = u.id
        LEFT JOIN kb_categories c ON a.category_id = c.id
        WHERE (a.company_id = ? OR a.company_id IS NULL)
    ";

    $params = [$companyId];

    if ($view === 'my') {
        $sql .= " AND a.author_id = ?";
        $params[] = $userId;
    } elseif ($view === 'draft') {
        $sql .= " AND a.status = 'draft'";
    } elseif ($view === 'review') {
        $sql .= " AND a.status = 'in_review'";
    } elseif ($view === 'published') {
        $sql .= " AND a.status = 'published'";
    } elseif ($view === 'needs_review') {
        $sql .= " AND a.status = 'published' AND a.next_review_at <= CURDATE()";
    }

    if ($categoryId) {
        $sql .= " AND a.category_id = ?";
        $params[] = $categoryId;
    }

    if ($search) {
        $sql .= " AND (MATCH(a.title, a.summary, a.content_html, a.keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                  OR a.title LIKE ? OR a.summary LIKE ?)";
        $params[] = $search;
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY a.updated_at DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll();

    // Get stats
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as review_count,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_count,
            SUM(CASE WHEN status = 'published' AND next_review_at <= CURDATE() THEN 1 ELSE 0 END) as needs_review_count
        FROM kb_articles
        WHERE company_id = ? OR company_id IS NULL
    ");
    $stmt->execute([$companyId]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log('KB articles error: ' . $e->getMessage());
    $articles = [];
    $stats = ['total' => 0, 'draft_count' => 0, 'review_count' => 0, 'published_count' => 0, 'needs_review_count' => 0];
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-book"></i> Knowledge Base</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Knowledge Base</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="/staff/kb-editor.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> New Article
            </a>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                <i class="bi bi-files"></i>
            </div>
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total Articles</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                <i class="bi bi-pencil-square"></i>
            </div>
            <div class="stat-value"><?= $stats['draft_count'] ?></div>
            <div class="stat-label">Drafts</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                <i class="bi bi-eye"></i>
            </div>
            <div class="stat-value"><?= $stats['review_count'] ?></div>
            <div class="stat-label">In Review</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="stat-icon" style="background: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-value"><?= $stats['needs_review_count'] ?></div>
            <div class="stat-label">Needs Review</div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Sidebar Categories -->
    <div class="col-lg-3">
        <div class="card mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0">Categories</h6>
            </div>
            <div class="list-group list-group-flush">
                <a href="?view=all" class="list-group-item list-group-item-action <?= $view === 'all' ? 'active' : '' ?>">
                    <i class="bi bi-grid"></i> All Articles
                    <span class="badge bg-primary float-end"><?= $stats['total'] ?></span>
                </a>
                <a href="?view=my" class="list-group-item list-group-item-action <?= $view === 'my' ? 'active' : '' ?>">
                    <i class="bi bi-person"></i> My Articles
                </a>
                <a href="?view=draft" class="list-group-item list-group-item-action <?= $view === 'draft' ? 'active' : '' ?>">
                    <i class="bi bi-pencil"></i> Drafts
                    <span class="badge bg-warning float-end"><?= $stats['draft_count'] ?></span>
                </a>
                <a href="?view=review" class="list-group-item list-group-item-action <?= $view === 'review' ? 'active' : '' ?>">
                    <i class="bi bi-eye"></i> In Review
                    <span class="badge bg-info float-end"><?= $stats['review_count'] ?></span>
                </a>
                <a href="?view=published" class="list-group-item list-group-item-action <?= $view === 'published' ? 'active' : '' ?>">
                    <i class="bi bi-check-circle"></i> Published
                    <span class="badge bg-success float-end"><?= $stats['published_count'] ?></span>
                </a>
                <a href="?view=needs_review" class="list-group-item list-group-item-action <?= $view === 'needs_review' ? 'active' : '' ?>">
                    <i class="bi bi-exclamation-triangle"></i> Needs Review
                    <span class="badge bg-danger float-end"><?= $stats['needs_review_count'] ?></span>
                </a>
            </div>
        </div>

        <?php if (!empty($categories)): ?>
        <div class="card">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Browse by Category</h6>
                <a href="/staff/kb-settings.php?tab=categories" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-gear"></i>
                </a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= $cat['id'] ?>"
                       class="list-group-item list-group-item-action <?= $categoryId == $cat['id'] ? 'active' : '' ?>">
                        <?php if ($cat['icon']): ?>
                            <i class="bi bi-<?= e($cat['icon']) ?>"></i>
                        <?php endif; ?>
                        <?= e($cat['name']) ?>
                        <span class="badge bg-secondary float-end"><?= $cat['article_count'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Content - Articles List -->
    <div class="col-lg-9">
        <!-- Search Bar -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" name="q" class="form-control"
                               placeholder="Search articles..." value="<?= e($search) ?>">
                        <input type="hidden" name="view" value="<?= e($view) ?>">
                        <?php if ($categoryId): ?>
                            <input type="hidden" name="category" value="<?= e($categoryId) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Articles List -->
        <?php if (empty($articles)): ?>
            <div class="card">
                <div class="card-body text-center p-5">
                    <i class="bi bi-file-earmark-text" style="font-size: 64px; color: #ddd;"></i>
                    <h4 class="mt-3">No Articles Found</h4>
                    <p class="text-muted">
                        <?= $search ? 'Try a different search term' : 'Get started by creating your first article' ?>
                    </p>
                    <a href="/staff/kb-editor.php" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-lg"></i> Create Article
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($articles as $article): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-9">
                                <h5 class="card-title mb-2">
                                    <a href="/staff/kb-editor.php?id=<?= $article['id'] ?>" class="text-decoration-none">
                                        <?= e($article['title']) ?>
                                    </a>
                                </h5>

                                <div class="mb-2">
                                    <?php
                                    $statusColors = [
                                        'draft' => 'secondary',
                                        'in_review' => 'warning',
                                        'published' => 'success',
                                        'archived' => 'dark'
                                    ];
                                    $statusColor = $statusColors[$article['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>"><?= ucfirst(str_replace('_', ' ', $article['status'])) ?></span>

                                    <?php if ($article['visibility'] !== 'public'): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-lock"></i> <?= ucfirst(str_replace('_', ' ', $article['visibility'])) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($article['category_name']): ?>
                                        <span class="badge" style="background-color: <?= e($article['category_color'] ?? '#6c757d') ?>">
                                            <?= e($article['category_name']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($article['template_type'] !== 'general'): ?>
                                        <span class="badge bg-light text-dark">
                                            <i class="bi bi-layout-text-window"></i> <?= ucfirst(str_replace('-', ' ', $article['template_type'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($article['summary']): ?>
                                    <p class="card-text text-muted mb-2">
                                        <?= e(truncate($article['summary'], 200)) ?>
                                    </p>
                                <?php endif; ?>

                                <small class="text-muted">
                                    <i class="bi bi-person"></i> <?= e($article['first_name'] . ' ' . $article['last_name']) ?> •
                                    <i class="bi bi-clock"></i> Updated <?= timeAgo($article['updated_at']) ?> •
                                    <i class="bi bi-eye"></i> <?= number_format($article['total_views']) ?> views
                                    <?php if ($article['helpful_count'] > 0): ?>
                                        • <i class="bi bi-hand-thumbs-up"></i> <?= $article['helpful_count'] ?>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="col-md-3 text-end">
                                <div class="btn-group-vertical btn-group-sm">
                                    <a href="/staff/kb-editor.php?id=<?= $article['id'] ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="/kb/a/<?= e($article['slug']) ?>" class="btn btn-outline-secondary" target="_blank">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <?php if ($article['status'] === 'draft'): ?>
                                        <button class="btn btn-outline-success" onclick="submitForReview(<?= $article['id'] ?>)">
                                            <i class="bi bi-send"></i> Submit
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function submitForReview(articleId) {
    if (confirm('Submit this article for review?')) {
        // Would implement AJAX call here
        window.location.href = '/staff/kb-editor.php?id=' + articleId + '&action=submit_review';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
