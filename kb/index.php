<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$db = Database::getInstance()->getConnection();

// Get search query
$search = $_GET['q'] ?? '';
$categorySlug = $_GET['category'] ?? null;

// Determine company scope (for company-specific KB)
$companyId = null;
$companySlug = $_GET['company'] ?? null;
if ($companySlug) {
    $stmt = $db->prepare("SELECT id, company_name FROM companies WHERE slug = ?");
    $stmt->execute([$companySlug]);
    $company = $stmt->fetch();
    if ($company) {
        $companyId = $company['id'];
    }
}

// Get categories
try {
    $sql = "SELECT * FROM kb_categories WHERE is_visible = 1";
    if ($companyId) {
        $sql .= " AND (company_id = ? OR company_id IS NULL)";
        $params = [$companyId];
    } else {
        $sql .= " AND company_id IS NULL";
        $params = [];
    }
    $sql .= " ORDER BY sort_order, name";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Get featured/popular articles
try {
    $sql = "
        SELECT a.*, c.name as category_name, c.color as category_color,
               (SELECT COUNT(*) FROM kb_article_views WHERE article_id = a.id) as view_count
        FROM kb_articles a
        LEFT JOIN kb_categories c ON a.category_id = c.id
        WHERE a.status = 'published'
        AND a.visibility = 'public'
        AND (a.publish_at IS NULL OR a.publish_at <= NOW())
        AND (a.unpublish_at IS NULL OR a.unpublish_at > NOW())
    ";

    if ($companyId) {
        $sql .= " AND (a.company_id = ? OR a.company_id IS NULL)";
        $params = [$companyId];
    } else {
        $sql .= " AND a.company_id IS NULL";
        $params = [];
    }

    if ($search) {
        $sql .= " AND (MATCH(a.title, a.summary, a.content_html, a.keywords) AGAINST(? IN NATURAL LANGUAGE MODE)
                  OR a.title LIKE ? OR a.summary LIKE ?)";
        $params[] = $search;
        $params[] = "%$search%";
        $params[] = "%$search%";

        // Log search
        $logStmt = $db->prepare("
            INSERT INTO kb_search_queries (company_id, query, results_count, ip_address)
            VALUES (?, ?, 0, ?)
        ");
        $logStmt->execute([$companyId, $search, $_SERVER['REMOTE_ADDR']]);
    }

    if ($categorySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $categorySlug;
    }

    $sql .= " ORDER BY a.published_at DESC LIMIT 12";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll();

    // Update search results count
    if ($search && !empty($articles)) {
        $db->prepare("UPDATE kb_search_queries SET results_count = ? WHERE query = ? ORDER BY id DESC LIMIT 1")
           ->execute([count($articles), $search]);
    }
} catch (PDOException $e) {
    error_log('KB public error: ' . $e->getMessage());
    $articles = [];
}

$pageTitle = $search ? "Search: $search" : ($categorySlug ? "Category" : "Knowledge Base");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --kb-primary: #3498db;
            --kb-secondary: #2c3e50;
        }
        .kb-hero {
            background: linear-gradient(135deg, var(--kb-primary) 0%, var(--kb-secondary) 100%);
            color: white;
            padding: 4rem 0 3rem 0;
        }
        .kb-search {
            max-width: 600px;
            margin: 0 auto;
        }
        .category-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            height: 100%;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .article-card {
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .category-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .kb-footer {
            background: #f8f9fa;
            padding: 3rem 0;
            margin-top: 4rem;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="kb-hero">
        <div class="container">
            <div class="text-center mb-4">
                <h1 class="display-4 fw-bold mb-3">
                    <i class="bi bi-book"></i>
                    How can we help you?
                </h1>
                <p class="lead">Search our knowledge base for answers</p>
            </div>

            <!-- Search Bar -->
            <div class="kb-search">
                <form method="get" action="/kb/">
                    <div class="input-group input-group-lg">
                        <input type="text" name="q" class="form-control"
                               placeholder="Search for articles..."
                               value="<?= e($search) ?>"
                               autofocus>
                        <button class="btn btn-light" type="submit">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <?php if (!$search && !$categorySlug): ?>
            <!-- Browse by Category -->
            <div class="text-center mb-5">
                <h2 class="mb-4">Browse by Category</h2>
            </div>

            <div class="row g-4 mb-5">
                <?php foreach ($categories as $cat): ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="?category=<?= e($cat['slug']) ?>" class="text-decoration-none">
                            <div class="card category-card">
                                <div class="card-body text-center p-4">
                                    <div class="category-icon" style="color: <?= e($cat['color'] ?? '#6c757d') ?>">
                                        <i class="bi bi-<?= e($cat['icon'] ?: 'folder') ?>"></i>
                                    </div>
                                    <h5 class="card-title mb-2"><?= e($cat['name']) ?></h5>
                                    <?php if ($cat['description']): ?>
                                        <p class="card-text text-muted small"><?= e($cat['description']) ?></p>
                                    <?php endif; ?>
                                    <?php
                                    // Get article count
                                    $countSql = "SELECT COUNT(*) FROM kb_articles WHERE category_id = ? AND status = 'published' AND visibility = 'public'";
                                    $countStmt = $db->prepare($countSql);
                                    $countStmt->execute([$cat['id']]);
                                    $articleCount = $countStmt->fetchColumn();
                                    ?>
                                    <span class="badge bg-primary"><?= $articleCount ?> articles</span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Articles Section -->
        <?php if ($search || $categorySlug): ?>
            <div class="mb-4">
                <h3>
                    <?php if ($search): ?>
                        Search Results for "<?= e($search) ?>"
                        <span class="badge bg-secondary"><?= count($articles) ?> found</span>
                    <?php elseif ($categorySlug): ?>
                        <?php
                        $catName = 'Articles';
                        foreach ($categories as $cat) {
                            if ($cat['slug'] === $categorySlug) {
                                $catName = $cat['name'];
                                break;
                            }
                        }
                        ?>
                        <?= e($catName) ?>
                    <?php endif; ?>
                </h3>
                <a href="/kb/" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Categories
                </a>
            </div>
        <?php else: ?>
            <div class="text-center mb-4">
                <h2>Popular Articles</h2>
            </div>
        <?php endif; ?>

        <?php if (empty($articles)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-search" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h4 class="mt-3">No Articles Found</h4>
                    <p class="text-muted">
                        <?= $search ? 'Try different search terms' : 'No articles available in this category yet' ?>
                    </p>
                    <a href="/kb/" class="btn btn-primary mt-2">
                        <i class="bi bi-arrow-left"></i> Go Back
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($articles as $article): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card article-card h-100">
                            <div class="card-body">
                                <?php if ($article['category_name']): ?>
                                    <span class="badge mb-2" style="background-color: <?= e($article['category_color'] ?? '#6c757d') ?>">
                                        <?= e($article['category_name']) ?>
                                    </span>
                                <?php endif; ?>
                                <h5 class="card-title">
                                    <a href="/kb/a/<?= e($article['slug']) ?>" class="text-decoration-none text-dark">
                                        <?= e($article['title']) ?>
                                    </a>
                                </h5>
                                <?php if ($article['summary']): ?>
                                    <p class="card-text text-muted small">
                                        <?= e(truncate($article['summary'], 120)) ?>
                                    </p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">
                                        <i class="bi bi-eye"></i> <?= number_format($article['view_count']) ?> views
                                    </small>
                                    <a href="/kb/a/<?= e($article['slug']) ?>" class="btn btn-sm btn-outline-primary">
                                        Read More <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="kb-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>About</h5>
                    <p class="text-muted">Knowledge Base powered by <?= e(APP_NAME) ?></p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/kb/" class="text-decoration-none">Home</a></li>
                        <?php if (isLoggedIn()): ?>
                            <li><a href="/staff/kb.php" class="text-decoration-none">Manage KB</a></li>
                        <?php else: ?>
                            <li><a href="/login.php" class="text-decoration-none">Staff Login</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Need More Help?</h5>
                    <p class="text-muted">Can't find what you're looking for?</p>
                    <a href="/support" class="btn btn-primary">Contact Support</a>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center text-muted">
                <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
