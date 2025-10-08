<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

$db = Database::getInstance()->getConnection();

// Get article slug from URL
$slug = $_GET['slug'] ?? basename($_SERVER['REQUEST_URI']);

// Determine company scope
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

// Get article
try {
    $sql = "
        SELECT a.*, c.name as category_name, c.slug as category_slug, c.color as category_color,
               u.first_name, u.last_name,
               (SELECT COUNT(*) FROM kb_article_views WHERE article_id = a.id) as view_count,
               (SELECT COUNT(*) FROM kb_article_feedback WHERE article_id = a.id AND is_helpful = 1) as helpful_count,
               (SELECT COUNT(*) FROM kb_article_feedback WHERE article_id = a.id AND is_helpful = 0) as not_helpful_count
        FROM kb_articles a
        LEFT JOIN kb_categories c ON a.category_id = c.id
        LEFT JOIN users u ON a.author_id = u.id
        WHERE a.slug = ?
        AND a.status = 'published'
        AND a.visibility = 'public'
        AND (a.publish_at IS NULL OR a.publish_at <= NOW())
        AND (a.unpublish_at IS NULL OR a.unpublish_at > NOW())
    ";

    if ($companyId) {
        $sql .= " AND (a.company_id = ? OR a.company_id IS NULL)";
        $params = [$slug, $companyId];
    } else {
        $sql .= " AND a.company_id IS NULL";
        $params = [$slug];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $article = $stmt->fetch();

    if (!$article) {
        http_response_code(404);
        die('Article not found');
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Error loading article');
}

// Track view
try {
    $stmt = $db->prepare("
        INSERT INTO kb_article_views (article_id, ip_address, user_agent)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$article['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
} catch (PDOException $e) {
    // Silently fail view tracking
}

// Handle feedback submission
if (isset($_POST['submit_feedback'])) {
    try {
        $isHelpful = $_POST['is_helpful'] === '1';
        $comment = $_POST['comment'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO kb_article_feedback (article_id, is_helpful, comment, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$article['id'], $isHelpful, $comment, $_SERVER['REMOTE_ADDR']]);

        header('Location: ' . $_SERVER['REQUEST_URI'] . '?feedback=submitted');
        exit;
    } catch (PDOException $e) {
        $feedbackError = true;
    }
}

// Get related articles
try {
    $relatedSql = "
        SELECT a.*, c.name as category_name, c.color as category_color,
               (SELECT COUNT(*) FROM kb_article_views WHERE article_id = a.id) as view_count
        FROM kb_articles a
        LEFT JOIN kb_categories c ON a.category_id = c.id
        WHERE a.id != ?
        AND a.status = 'published'
        AND a.visibility = 'public'
        AND (a.category_id = ? OR a.id IN (
            SELECT at2.article_id FROM kb_article_tags at1
            JOIN kb_article_tags at2 ON at1.tag_id = at2.tag_id
            WHERE at1.article_id = ? AND at2.article_id != ?
        ))
    ";

    if ($companyId) {
        $relatedSql .= " AND (a.company_id = ? OR a.company_id IS NULL)";
        $relatedParams = [$article['id'], $article['category_id'], $article['id'], $article['id'], $companyId];
    } else {
        $relatedSql .= " AND a.company_id IS NULL";
        $relatedParams = [$article['id'], $article['category_id'], $article['id'], $article['id']];
    }

    $relatedSql .= " ORDER BY RAND() LIMIT 3";

    $stmt = $db->prepare($relatedSql);
    $stmt->execute($relatedParams);
    $relatedArticles = $stmt->fetchAll();
} catch (PDOException $e) {
    $relatedArticles = [];
}

// Get tags
try {
    $stmt = $db->prepare("
        SELECT t.* FROM kb_tags t
        JOIN kb_article_tags at ON t.id = at.tag_id
        WHERE at.article_id = ?
    ");
    $stmt->execute([$article['id']]);
    $tags = $stmt->fetchAll();
} catch (PDOException $e) {
    $tags = [];
}

$pageTitle = $article['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($article['meta_title'] ?: $article['title']) ?> - <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e($article['meta_description'] ?: $article['summary']) ?>">
    <?php if ($article['meta_keywords']): ?>
        <meta name="keywords" content="<?= e($article['meta_keywords']) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css">
    <style>
        :root {
            --kb-primary: #3498db;
            --kb-secondary: #2c3e50;
        }
        .article-header {
            background: linear-gradient(135deg, var(--kb-primary) 0%, var(--kb-secondary) 100%);
            color: white;
            padding: 3rem 0 2rem 0;
        }
        .article-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #2c3e50;
        }
        .article-content h2 {
            margin-top: 2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        .article-content h3 {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        .article-content pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
        }
        .article-content img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .article-content table {
            width: 100%;
            margin: 1rem 0;
        }
        .article-content blockquote {
            border-left: 4px solid var(--kb-primary);
            padding-left: 1rem;
            margin: 1rem 0;
            color: #6c757d;
            font-style: italic;
        }
        .toc {
            position: sticky;
            top: 20px;
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
        }
        .toc-link {
            display: block;
            padding: 0.25rem 0;
            color: #495057;
            text-decoration: none;
            transition: color 0.2s;
        }
        .toc-link:hover {
            color: var(--kb-primary);
        }
        .toc-link.active {
            color: var(--kb-primary);
            font-weight: 600;
        }
        .toc-link.level-3 {
            padding-left: 1rem;
            font-size: 0.9rem;
        }
        .feedback-section {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 2rem;
            margin: 3rem 0;
        }
        .related-article {
            transition: transform 0.2s;
        }
        .related-article:hover {
            transform: translateY(-5px);
        }
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="article-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb text-white-50">
                    <li class="breadcrumb-item"><a href="/kb/" class="text-white">Knowledge Base</a></li>
                    <?php if ($article['category_name']): ?>
                        <li class="breadcrumb-item">
                            <a href="/kb/?category=<?= e($article['category_slug']) ?>" class="text-white">
                                <?= e($article['category_name']) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active text-white"><?= e($article['title']) ?></li>
                </ol>
            </nav>

            <h1 class="display-5 fw-bold mb-3"><?= e($article['title']) ?></h1>

            <?php if ($article['summary']): ?>
                <p class="lead"><?= e($article['summary']) ?></p>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-3 align-items-center mt-4">
                <?php if ($article['category_name']): ?>
                    <span class="badge" style="background-color: <?= e($article['category_color'] ?? '#6c757d') ?>; font-size: 0.9rem;">
                        <?= e($article['category_name']) ?>
                    </span>
                <?php endif; ?>
                <span class="text-white-50">
                    <i class="bi bi-person-circle"></i> <?= e($article['first_name'] . ' ' . $article['last_name']) ?>
                </span>
                <span class="text-white-50">
                    <i class="bi bi-calendar"></i> <?= formatDate($article['published_at'] ?? $article['created_at']) ?>
                </span>
                <span class="text-white-50">
                    <i class="bi bi-eye"></i> <?= number_format($article['view_count']) ?> views
                </span>
                <?php if ($article['reading_time_minutes']): ?>
                    <span class="text-white-50">
                        <i class="bi bi-clock"></i> <?= $article['reading_time_minutes'] ?> min read
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($tags)): ?>
                <div class="mt-3">
                    <?php foreach ($tags as $tag): ?>
                        <a href="/kb/?tag=<?= e($tag['slug']) ?>" class="badge bg-light text-dark text-decoration-none me-1">
                            #<?= e($tag['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Table of Contents (Desktop) -->
            <div class="col-lg-3 d-none d-lg-block">
                <div class="toc">
                    <h6 class="fw-bold mb-3">Table of Contents</h6>
                    <nav id="toc-nav"></nav>
                </div>
            </div>

            <!-- Article Content -->
            <div class="col-lg-9">
                <div class="article-content">
                    <?= $article['content_html'] ?>
                </div>

                <!-- Article Footer -->
                <div class="mt-5 pt-4 border-top">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="text-muted mb-1"><small>Last updated: <?= formatDate($article['updated_at']) ?></small></p>
                            <?php if ($article['next_review_at']): ?>
                                <p class="text-muted mb-0"><small>Next review: <?= formatDate($article['next_review_at']) ?></small></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <a href="#" onclick="window.print(); return false;" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="bi bi-printer"></i> Print
                            </a>
                            <a href="mailto:?subject=<?= urlencode($article['title']) ?>&body=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-share"></i> Share
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Feedback Section -->
                <div class="feedback-section">
                    <?php if (isset($_GET['feedback']) && $_GET['feedback'] === 'submitted'): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Thank you for your feedback!
                        </div>
                    <?php else: ?>
                        <h4 class="mb-3">Was this article helpful?</h4>
                        <form method="post" id="feedbackForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <div class="d-flex gap-2 mb-3">
                                <button type="button" class="btn btn-lg btn-outline-success flex-fill" onclick="submitFeedback(1)">
                                    <i class="bi bi-hand-thumbs-up"></i> Yes, it helped
                                </button>
                                <button type="button" class="btn btn-lg btn-outline-danger flex-fill" onclick="submitFeedback(0)">
                                    <i class="bi bi-hand-thumbs-down"></i> No, not helpful
                                </button>
                            </div>
                            <input type="hidden" name="is_helpful" id="is_helpful">
                            <div id="feedbackComment" style="display: none;">
                                <textarea name="comment" class="form-control mb-2" rows="3" placeholder="Tell us how we can improve this article (optional)"></textarea>
                                <button type="submit" name="submit_feedback" class="btn btn-primary">Submit Feedback</button>
                            </div>
                        </form>

                        <?php if ($article['helpful_count'] + $article['not_helpful_count'] > 0): ?>
                            <div class="mt-3 text-muted small">
                                <i class="bi bi-bar-chart"></i>
                                <?php
                                $total = $article['helpful_count'] + $article['not_helpful_count'];
                                $percentage = round(($article['helpful_count'] / $total) * 100);
                                ?>
                                <?= $article['helpful_count'] ?> of <?= $total ?> found this helpful (<?= $percentage ?>%)
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Related Articles -->
                <?php if (!empty($relatedArticles)): ?>
                    <div class="mt-5">
                        <h4 class="mb-4">Related Articles</h4>
                        <div class="row g-3">
                            <?php foreach ($relatedArticles as $related): ?>
                                <div class="col-md-4">
                                    <div class="card related-article h-100">
                                        <div class="card-body">
                                            <?php if ($related['category_name']): ?>
                                                <span class="badge mb-2" style="background-color: <?= e($related['category_color'] ?? '#6c757d') ?>">
                                                    <?= e($related['category_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <h6 class="card-title">
                                                <a href="/kb/a/<?= e($related['slug']) ?>" class="text-decoration-none text-dark">
                                                    <?= e($related['title']) ?>
                                                </a>
                                            </h6>
                                            <?php if ($related['summary']): ?>
                                                <p class="card-text small text-muted">
                                                    <?= e(truncate($related['summary'], 100)) ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-eye"></i> <?= number_format($related['view_count']) ?>
                                                </small>
                                                <a href="/kb/a/<?= e($related['slug']) ?>" class="btn btn-sm btn-outline-primary">
                                                    Read <i class="bi bi-arrow-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Back to KB -->
                <div class="mt-5 text-center">
                    <a href="/kb/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Knowledge Base
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        // Syntax highlighting for code blocks
        hljs.highlightAll();

        // Generate Table of Contents
        const content = document.querySelector('.article-content');
        const tocNav = document.getElementById('toc-nav');
        const headings = content.querySelectorAll('h2, h3');

        headings.forEach((heading, index) => {
            // Add ID to heading
            const id = 'heading-' + index;
            heading.id = id;

            // Create TOC link
            const link = document.createElement('a');
            link.href = '#' + id;
            link.textContent = heading.textContent;
            link.className = 'toc-link';
            if (heading.tagName === 'H3') {
                link.classList.add('level-3');
            }
            tocNav.appendChild(link);
        });

        // Highlight active TOC item on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    document.querySelectorAll('.toc-link').forEach(link => {
                        link.classList.toggle('active', link.getAttribute('href') === '#' + id);
                    });
                }
            });
        }, { rootMargin: '-100px 0px -66%' });

        headings.forEach(heading => observer.observe(heading));

        // Feedback submission
        function submitFeedback(helpful) {
            document.getElementById('is_helpful').value = helpful;
            document.getElementById('feedbackComment').style.display = 'block';

            // Auto-submit if helpful
            if (helpful === 1) {
                document.getElementById('feedbackForm').submit();
            }
        }
    </script>
</body>
</html>
