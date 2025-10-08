<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'KB Analytics';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];

// Date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Overall KB stats
try {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) as total_articles,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_articles,
            SUM(view_count) as total_views,
            SUM(helpful_count) as total_helpful,
            SUM(not_helpful_count) as total_not_helpful
        FROM kb_articles
        WHERE company_id = ? OR company_id IS NULL
    ");
    $stmt->execute([$companyId]);
    $overallStats = $stmt->fetch();

    $helpfulnessRate = ($overallStats['total_helpful'] + $overallStats['total_not_helpful']) > 0
        ? round(($overallStats['total_helpful'] / ($overallStats['total_helpful'] + $overallStats['total_not_helpful'])) * 100, 1)
        : 0;
} catch (PDOException $e) {
    $overallStats = ['total_articles' => 0, 'published_articles' => 0, 'total_views' => 0, 'total_helpful' => 0, 'total_not_helpful' => 0];
    $helpfulnessRate = 0;
}

// Top viewed articles
try {
    $stmt = $db->prepare("
        SELECT a.*, c.name as category_name,
               (SELECT COUNT(*) FROM kb_article_views WHERE article_id = a.id AND DATE(created_at) BETWEEN ? AND ?) as period_views
        FROM kb_articles a
        LEFT JOIN kb_categories c ON a.category_id = c.id
        WHERE (a.company_id = ? OR a.company_id IS NULL)
        AND a.status = 'published'
        ORDER BY period_views DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate, $endDate, $companyId]);
    $topArticles = $stmt->fetchAll();
} catch (PDOException $e) {
    $topArticles = [];
}

// Search analytics
try {
    $stmt = $db->prepare("
        SELECT query, COUNT(*) as search_count, AVG(results_count) as avg_results
        FROM kb_search_queries
        WHERE (company_id = ? OR company_id IS NULL)
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY query
        ORDER BY search_count DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId, $startDate, $endDate]);
    $topSearches = $stmt->fetchAll();
} catch (PDOException $e) {
    $topSearches = [];
}

// Zero-result searches
try {
    $stmt = $db->prepare("
        SELECT query, COUNT(*) as search_count
        FROM kb_search_queries
        WHERE (company_id = ? OR company_id IS NULL)
        AND results_count = 0
        AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY query
        ORDER BY search_count DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId, $startDate, $endDate]);
    $zeroResultSearches = $stmt->fetchAll();
} catch (PDOException $e) {
    $zeroResultSearches = [];
}

// Category performance
try {
    $stmt = $db->prepare("
        SELECT c.name, c.color,
               COUNT(DISTINCT a.id) as article_count,
               SUM(a.view_count) as total_views,
               SUM(a.helpful_count) as total_helpful,
               SUM(a.not_helpful_count) as total_not_helpful
        FROM kb_categories c
        LEFT JOIN kb_articles a ON c.id = a.category_id AND a.status = 'published'
        WHERE (c.company_id = ? OR c.company_id IS NULL)
        AND c.is_visible = 1
        GROUP BY c.id
        ORDER BY total_views DESC
    ");
    $stmt->execute([$companyId]);
    $categoryStats = $stmt->fetchAll();
} catch (PDOException $e) {
    $categoryStats = [];
}

// Author productivity
try {
    $stmt = $db->prepare("
        SELECT u.first_name, u.last_name,
               COUNT(a.id) as article_count,
               SUM(a.view_count) as total_views,
               AVG(a.helpful_count / NULLIF(a.helpful_count + a.not_helpful_count, 0)) * 100 as avg_helpfulness
        FROM users u
        JOIN kb_articles a ON u.id = a.author_id
        WHERE (a.company_id = ? OR a.company_id IS NULL)
        AND a.status = 'published'
        GROUP BY u.id
        ORDER BY article_count DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId]);
    $authorStats = $stmt->fetchAll();
} catch (PDOException $e) {
    $authorStats = [];
}

// Views over time (last 30 days)
try {
    $stmt = $db->prepare("
        SELECT DATE(v.created_at) as date, COUNT(*) as views
        FROM kb_article_views v
        JOIN kb_articles a ON v.article_id = a.id
        WHERE (a.company_id = ? OR a.company_id IS NULL)
        AND DATE(v.created_at) BETWEEN ? AND ?
        GROUP BY DATE(v.created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$companyId, $startDate, $endDate]);
    $viewsOverTime = $stmt->fetchAll();
} catch (PDOException $e) {
    $viewsOverTime = [];
}

// Articles needing review
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM kb_articles
        WHERE (company_id = ? OR company_id IS NULL)
        AND status = 'published'
        AND next_review_at IS NOT NULL
        AND next_review_at <= CURDATE()
    ");
    $stmt->execute([$companyId]);
    $needsReviewCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $needsReviewCount = 0;
}
?>

<style>
.analytics-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
}
.analytics-card h3 {
    font-size: 36px;
    font-weight: 700;
    margin: 10px 0;
}
.analytics-card p {
    margin: 0;
    opacity: 0.9;
}
.metric-card {
    border-left: 4px solid #3498db;
    transition: transform 0.2s;
}
.metric-card:hover {
    transform: translateX(5px);
}
.chart-container {
    position: relative;
    height: 300px;
}
.trend-up {
    color: #27ae60;
}
.trend-down {
    color: #e74c3c;
}
</style>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-graph-up"></i> KB Analytics</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/kb.php">Knowledge Base</a></li>
                    <li class="breadcrumb-item active">Analytics</li>
                </ol>
            </nav>
        </div>
        <div>
            <form method="get" class="d-flex gap-2">
                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Filter
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Key Metrics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card analytics-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="bi bi-file-text" style="font-size: 32px; opacity: 0.8;"></i>
            <h3><?= number_format($overallStats['total_articles']) ?></h3>
            <p>Total Articles</p>
            <small><?= $overallStats['published_articles'] ?> published</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card analytics-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="bi bi-eye" style="font-size: 32px; opacity: 0.8;"></i>
            <h3><?= number_format($overallStats['total_views']) ?></h3>
            <p>Total Views</p>
            <small>All time</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card analytics-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <i class="bi bi-hand-thumbs-up" style="font-size: 32px; opacity: 0.8;"></i>
            <h3><?= $helpfulnessRate ?>%</h3>
            <p>Helpfulness Rate</p>
            <small><?= number_format($overallStats['total_helpful']) ?> helpful votes</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card analytics-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <i class="bi bi-exclamation-triangle" style="font-size: 32px; opacity: 0.8;"></i>
            <h3><?= $needsReviewCount ?></h3>
            <p>Needs Review</p>
            <small>Recertification required</small>
        </div>
    </div>
</div>

<!-- Views Over Time Chart -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-graph-up"></i> Views Over Time</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="viewsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Top Articles -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Viewed Articles</h5>
                <span class="badge bg-primary"><?= count($topArticles) ?> articles</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topArticles)): ?>
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-inbox" style="font-size: 48px;"></i>
                        <p class="mt-2">No article views in this period</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Article</th>
                                    <th>Category</th>
                                    <th>Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topArticles as $i => $article): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <a href="/staff/kb-editor.php?id=<?= $article['id'] ?>" class="text-decoration-none">
                                                <?= e(truncate($article['title'], 50)) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($article['category_name']): ?>
                                                <span class="badge" style="background-color: #6c757d;">
                                                    <?= e($article['category_name']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= number_format($article['period_views']) ?></strong>
                                            <small class="text-muted">(<?= number_format($article['view_count']) ?> total)</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Searches -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-search"></i> Top Searches</h5>
                <span class="badge bg-primary"><?= count($topSearches) ?> queries</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topSearches)): ?>
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-search" style="font-size: 48px;"></i>
                        <p class="mt-2">No searches in this period</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Query</th>
                                    <th>Searches</th>
                                    <th>Avg Results</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topSearches as $i => $search): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <code><?= e($search['query']) ?></code>
                                        </td>
                                        <td><strong><?= number_format($search['search_count']) ?></strong></td>
                                        <td>
                                            <?php if ($search['avg_results'] > 0): ?>
                                                <span class="text-success">
                                                    <?= number_format($search['avg_results'], 1) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-danger">0</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Zero-Result Searches -->
    <div class="col-lg-6">
        <div class="card border-warning">
            <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-warning"><i class="bi bi-exclamation-triangle"></i> Zero-Result Searches</h5>
                <span class="badge bg-warning"><?= count($zeroResultSearches) ?> gaps</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($zeroResultSearches)): ?>
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-check-circle" style="font-size: 48px; color: #27ae60;"></i>
                        <p class="mt-2">All searches returned results!</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning m-3">
                        <i class="bi bi-lightbulb"></i> <strong>Content Gap Alert:</strong> These searches found no results. Consider creating articles for these topics.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Query</th>
                                    <th>Times Searched</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zeroResultSearches as $search): ?>
                                    <tr>
                                        <td><code><?= e($search['query']) ?></code></td>
                                        <td><strong><?= number_format($search['search_count']) ?></strong></td>
                                        <td>
                                            <a href="/staff/kb-editor.php?suggested_title=<?= urlencode(ucwords($search['query'])) ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-plus-circle"></i> Create Article
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Performance -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-folder"></i> Category Performance</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($categoryStats)): ?>
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-folder" style="font-size: 48px;"></i>
                        <p class="mt-2">No categories yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Articles</th>
                                    <th>Views</th>
                                    <th>Helpfulness</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoryStats as $cat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: <?= e($cat['color'] ?? '#6c757d') ?>">
                                                <?= e($cat['name']) ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($cat['article_count']) ?></td>
                                        <td><strong><?= number_format($cat['total_views']) ?></strong></td>
                                        <td>
                                            <?php
                                            $catHelpful = ($cat['total_helpful'] + $cat['total_not_helpful']) > 0
                                                ? round(($cat['total_helpful'] / ($cat['total_helpful'] + $cat['total_not_helpful'])) * 100, 1)
                                                : 0;
                                            ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-success" style="width: <?= $catHelpful ?>%">
                                                    <?= $catHelpful ?>%
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
    </div>
</div>

<!-- Author Productivity -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-people"></i> Author Productivity</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($authorStats)): ?>
                    <div class="text-center p-4 text-muted">
                        <i class="bi bi-people" style="font-size: 48px;"></i>
                        <p class="mt-2">No published articles yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Author</th>
                                    <th>Articles Published</th>
                                    <th>Total Views</th>
                                    <th>Avg Views/Article</th>
                                    <th>Avg Helpfulness</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($authorStats as $author): ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($author['first_name'] . ' ' . $author['last_name']) ?></strong>
                                        </td>
                                        <td><?= number_format($author['article_count']) ?></td>
                                        <td><?= number_format($author['total_views']) ?></td>
                                        <td><?= number_format($author['total_views'] / $author['article_count'], 1) ?></td>
                                        <td>
                                            <?php if ($author['avg_helpfulness']): ?>
                                                <span class="badge bg-success"><?= round($author['avg_helpfulness'], 1) ?>%</span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Views Over Time Chart
const ctx = document.getElementById('viewsChart');
const viewsData = <?= json_encode($viewsOverTime) ?>;

const labels = viewsData.map(d => d.date);
const data = viewsData.map(d => d.views);

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Article Views',
            data: data,
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
