<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'KB Settings';
include __DIR__ . '/includes/header.php';

$db = Database::getInstance()->getConnection();
$companyId = $currentUser['company_id'];
$userId = $currentUser['id'];

$tab = $_GET['tab'] ?? 'categories';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request';
    } elseif (isset($_POST['add_category'])) {
        try {
            $slug = generateSlug($_POST['name']);
            $stmt = $db->prepare("
                INSERT INTO kb_categories (company_id, parent_id, name, slug, description, icon, color, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $_POST['parent_id'] ?: null,
                $_POST['name'],
                $slug,
                $_POST['description'],
                $_POST['icon'],
                $_POST['color'],
                $_POST['sort_order'] ?: 0
            ]);
            setFlash('success', 'Category added successfully!');
            redirect('/staff/kb-settings.php?tab=categories');
        } catch (PDOException $e) {
            $error = 'Failed to add category: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_category'])) {
        try {
            $slug = $_POST['slug'] ?: generateSlug($_POST['name']);
            $stmt = $db->prepare("
                UPDATE kb_categories SET
                    name = ?, slug = ?, description = ?, icon = ?, color = ?, sort_order = ?, parent_id = ?, is_visible = ?
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $slug,
                $_POST['description'],
                $_POST['icon'],
                $_POST['color'],
                $_POST['sort_order'],
                $_POST['parent_id'] ?: null,
                isset($_POST['is_visible']) ? 1 : 0,
                $_POST['category_id'],
                $companyId
            ]);
            setFlash('success', 'Category updated successfully!');
            redirect('/staff/kb-settings.php?tab=categories');
        } catch (PDOException $e) {
            $error = 'Failed to update category';
        }
    } elseif (isset($_POST['delete_category'])) {
        try {
            $stmt = $db->prepare("DELETE FROM kb_categories WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['category_id'], $companyId]);
            setFlash('success', 'Category deleted successfully!');
            redirect('/staff/kb-settings.php?tab=categories');
        } catch (PDOException $e) {
            $error = 'Failed to delete category';
        }
    } elseif (isset($_POST['add_collection'])) {
        try {
            $slug = generateSlug($_POST['name']);
            $stmt = $db->prepare("
                INSERT INTO kb_collections (company_id, name, slug, description, icon, color, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $_POST['name'],
                $slug,
                $_POST['description'],
                $_POST['icon'],
                $_POST['color'],
                $_POST['sort_order'] ?: 0
            ]);
            setFlash('success', 'Collection added successfully!');
            redirect('/staff/kb-settings.php?tab=collections');
        } catch (PDOException $e) {
            $error = 'Failed to add collection';
        }
    } elseif (isset($_POST['update_collection'])) {
        try {
            $slug = $_POST['slug'] ?: generateSlug($_POST['name']);
            $stmt = $db->prepare("
                UPDATE kb_collections SET
                    name = ?, slug = ?, description = ?, icon = ?, color = ?, sort_order = ?, is_visible = ?
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $slug,
                $_POST['description'],
                $_POST['icon'],
                $_POST['color'],
                $_POST['sort_order'],
                isset($_POST['is_visible']) ? 1 : 0,
                $_POST['collection_id'],
                $companyId
            ]);
            setFlash('success', 'Collection updated successfully!');
            redirect('/staff/kb-settings.php?tab=collections');
        } catch (PDOException $e) {
            $error = 'Failed to update collection';
        }
    } elseif (isset($_POST['delete_collection'])) {
        try {
            $stmt = $db->prepare("DELETE FROM kb_collections WHERE id = ? AND company_id = ?");
            $stmt->execute([$_POST['collection_id'], $companyId]);
            setFlash('success', 'Collection deleted successfully!');
            redirect('/staff/kb-settings.php?tab=collections');
        } catch (PDOException $e) {
            $error = 'Failed to delete collection';
        }
    }
}

// Get categories
try {
    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM kb_articles WHERE category_id = c.id) as article_count,
               p.name as parent_name
        FROM kb_categories c
        LEFT JOIN kb_categories p ON c.parent_id = p.id
        WHERE c.company_id = ? OR c.company_id IS NULL
        ORDER BY c.sort_order, c.name
    ");
    $stmt->execute([$companyId]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Get collections
try {
    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM kb_collection_articles WHERE collection_id = c.id) as article_count
        FROM kb_collections c
        WHERE c.company_id = ? OR c.company_id IS NULL
        ORDER BY c.sort_order, c.name
    ");
    $stmt->execute([$companyId]);
    $collections = $stmt->fetchAll();
} catch (PDOException $e) {
    $collections = [];
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><i class="bi bi-gear"></i> KB Settings</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/staff/">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="/staff/kb.php">Knowledge Base</a></li>
                    <li class="breadcrumb-item active">Settings</li>
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

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'categories' ? 'active' : '' ?>" href="?tab=categories">
            <i class="bi bi-folder"></i> Categories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'collections' ? 'active' : '' ?>" href="?tab=collections">
            <i class="bi bi-collection"></i> Collections
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'tags' ? 'active' : '' ?>" href="?tab=tags">
            <i class="bi bi-tags"></i> Tags
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'templates' ? 'active' : '' ?>" href="?tab=templates">
            <i class="bi bi-layout-text-window"></i> Templates
        </a>
    </li>
</ul>

<?php if ($tab === 'categories'): ?>
    <!-- Categories Management -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Categories</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-lg"></i> Add Category
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($categories)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-folder" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Categories Yet</h4>
                    <p>Create your first category to organize articles</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="40"></th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Parent</th>
                                <th>Articles</th>
                                <th>Sort Order</th>
                                <th>Visible</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <?php if ($cat['icon']): ?>
                                            <i class="bi bi-<?= e($cat['icon']) ?>" style="color: <?= e($cat['color'] ?? '#6c757d') ?>; font-size: 1.5rem;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($cat['name']) ?></strong>
                                        <?php if ($cat['description']): ?>
                                            <br><small class="text-muted"><?= e(truncate($cat['description'], 60)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= e($cat['slug']) ?></code></td>
                                    <td><?= $cat['parent_name'] ? e($cat['parent_name']) : '-' ?></td>
                                    <td><span class="badge bg-primary"><?= $cat['article_count'] ?></span></td>
                                    <td><?= $cat['sort_order'] ?></td>
                                    <td>
                                        <?php if ($cat['is_visible']): ?>
                                            <i class="bi bi-eye text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-eye-slash text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick='editCategory(<?= json_encode($cat) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteCategory(<?= $cat['id'] ?>, '<?= e($cat['name']) ?>')">
                                                <i class="bi bi-trash"></i>
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Category Name *</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Parent Category</label>
                                <select name="parent_id" class="form-select">
                                    <option value="">-- No Parent (Top Level) --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Icon (Bootstrap Icon)</label>
                                <input type="text" name="icon" class="form-control" placeholder="book">
                                <small class="text-muted">e.g., book, folder, star</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control" value="#6c757d">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="category_id" id="edit_cat_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Category Name *</label>
                                <input type="text" name="name" id="edit_cat_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" id="edit_cat_sort" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" id="edit_cat_slug" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_cat_desc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Parent Category</label>
                                <select name="parent_id" id="edit_cat_parent" class="form-select">
                                    <option value="">-- No Parent (Top Level) --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Icon (Bootstrap Icon)</label>
                                <input type="text" name="icon" id="edit_cat_icon" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Color</label>
                                <input type="color" name="color" id="edit_cat_color" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_visible" id="edit_cat_visible" checked>
                                    <label class="form-check-label" for="edit_cat_visible">
                                        Visible to users
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="category_id" id="delete_cat_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete category <strong id="delete_cat_name"></strong>?</p>
                        <p class="text-danger">Articles in this category will not be deleted, just uncategorized.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_category" class="btn btn-danger">Delete Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'collections'): ?>
    <!-- Collections Management -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Collections</h5>
                <small class="text-muted">Curated groups of articles (e.g., "Getting Started", "Onboarding")</small>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCollectionModal">
                <i class="bi bi-plus-lg"></i> Add Collection
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($collections)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-collection" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Collections Yet</h4>
                    <p>Create your first collection to group related articles</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="40"></th>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Articles</th>
                                <th>Sort Order</th>
                                <th>Visible</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collections as $col): ?>
                                <tr>
                                    <td>
                                        <?php if ($col['icon']): ?>
                                            <i class="bi bi-<?= e($col['icon']) ?>" style="color: <?= e($col['color'] ?? '#6c757d') ?>; font-size: 1.5rem;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($col['name']) ?></strong>
                                        <?php if ($col['description']): ?>
                                            <br><small class="text-muted"><?= e(truncate($col['description'], 60)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= e($col['slug']) ?></code></td>
                                    <td><span class="badge bg-primary"><?= $col['article_count'] ?></span></td>
                                    <td><?= $col['sort_order'] ?></td>
                                    <td>
                                        <?php if ($col['is_visible']): ?>
                                            <i class="bi bi-eye text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-eye-slash text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?tab=collection_articles&id=<?= $col['id'] ?>" class="btn btn-outline-info">
                                                <i class="bi bi-list"></i> Manage
                                            </a>
                                            <button class="btn btn-outline-primary" onclick='editCollection(<?= json_encode($col) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteCollection(<?= $col['id'] ?>, '<?= e($col['name']) ?>')">
                                                <i class="bi bi-trash"></i>
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

    <!-- Add Collection Modal -->
    <div class="modal fade" id="addCollectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Collection Name *</label>
                                <input type="text" name="name" class="form-control" required placeholder="e.g., Getting Started">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Icon</label>
                                <input type="text" name="icon" class="form-control" placeholder="collection">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Color</label>
                                <input type="color" name="color" class="form-control" value="#6c757d">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_collection" class="btn btn-primary">Add Collection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Collection Modal -->
    <div class="modal fade" id="editCollectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="collection_id" id="edit_col_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Collection Name *</label>
                                <input type="text" name="name" id="edit_col_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" id="edit_col_sort" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" id="edit_col_slug" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_col_desc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Icon</label>
                                <input type="text" name="icon" id="edit_col_icon" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Color</label>
                                <input type="color" name="color" id="edit_col_color" class="form-control">
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_visible" id="edit_col_visible" checked>
                            <label class="form-check-label" for="edit_col_visible">
                                Visible to users
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_collection" class="btn btn-primary">Update Collection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Collection Modal -->
    <div class="modal fade" id="deleteCollectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                    <input type="hidden" name="collection_id" id="delete_col_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete collection <strong id="delete_col_name"></strong>?</p>
                        <p class="text-muted">Articles will not be deleted, just removed from this collection.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_collection" class="btn btn-danger">Delete Collection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'tags'): ?>
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Tags</h5>
            <small class="text-muted">Tags are automatically created when you add them to articles</small>
        </div>
        <div class="card-body">
            <?php
            try {
                $stmt = $db->prepare("
                    SELECT t.*, COUNT(at.article_id) as usage_count
                    FROM kb_tags t
                    LEFT JOIN kb_article_tags at ON t.id = at.tag_id
                    WHERE t.company_id = ?
                    GROUP BY t.id
                    ORDER BY usage_count DESC, t.name
                ");
                $stmt->execute([$companyId]);
                $tags = $stmt->fetchAll();
            } catch (PDOException $e) {
                $tags = [];
            }
            ?>

            <?php if (empty($tags)): ?>
                <div class="text-center text-muted p-4">
                    <i class="bi bi-tags" style="font-size: 64px;"></i>
                    <h4 class="mt-3">No Tags Yet</h4>
                    <p>Tags are automatically created when you add them to articles</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                        <span class="badge bg-secondary" style="font-size: 1rem; padding: 0.5rem 1rem;">
                            <?= e($tag['name']) ?>
                            <span class="badge bg-dark"><?= $tag['usage_count'] ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'templates'): ?>
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Article Templates</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-list-check"></i> How-To Guide</h6>
                            <p class="card-text text-muted">Step-by-step instructions for completing a task</p>
                            <small class="text-muted">Structure: Problem → Solution → Steps → Result</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-question-circle"></i> FAQ</h6>
                            <p class="card-text text-muted">Question and answer format</p>
                            <small class="text-muted">Structure: Question → Short Answer → Details</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-terminal"></i> Runbook</h6>
                            <p class="card-text text-muted">Technical procedures and troubleshooting</p>
                            <small class="text-muted">Structure: Prerequisites → Steps → Verification → Troubleshooting</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-megaphone"></i> Release Notes</h6>
                            <p class="card-text text-muted">Product updates and changes</p>
                            <small class="text-muted">Structure: What's New → Improvements → Bug Fixes → Breaking Changes</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function editCategory(cat) {
    document.getElementById('edit_cat_id').value = cat.id;
    document.getElementById('edit_cat_name').value = cat.name;
    document.getElementById('edit_cat_slug').value = cat.slug;
    document.getElementById('edit_cat_desc').value = cat.description || '';
    document.getElementById('edit_cat_parent').value = cat.parent_id || '';
    document.getElementById('edit_cat_icon').value = cat.icon || '';
    document.getElementById('edit_cat_color').value = cat.color || '#6c757d';
    document.getElementById('edit_cat_sort').value = cat.sort_order || 0;
    document.getElementById('edit_cat_visible').checked = cat.is_visible == 1;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

function deleteCategory(id, name) {
    document.getElementById('delete_cat_id').value = id;
    document.getElementById('delete_cat_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteCategoryModal')).show();
}

function editCollection(col) {
    document.getElementById('edit_col_id').value = col.id;
    document.getElementById('edit_col_name').value = col.name;
    document.getElementById('edit_col_slug').value = col.slug;
    document.getElementById('edit_col_desc').value = col.description || '';
    document.getElementById('edit_col_icon').value = col.icon || '';
    document.getElementById('edit_col_color').value = col.color || '#6c757d';
    document.getElementById('edit_col_sort').value = col.sort_order || 0;
    document.getElementById('edit_col_visible').checked = col.is_visible == 1;
    new bootstrap.Modal(document.getElementById('editCollectionModal')).show();
}

function deleteCollection(id, name) {
    document.getElementById('delete_col_id').value = id;
    document.getElementById('delete_col_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteCollectionModal')).show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
