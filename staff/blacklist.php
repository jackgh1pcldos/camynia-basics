<?php
require_once __DIR__ . '/../includes/core/bootstrap.php';

$pageTitle = 'Blacklisted Words';
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Content Moderation</h1>
            <p class="text-muted mb-0">Manage blacklisted words and content filters</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWordModal">
            <i class="bi bi-plus-circle me-2"></i>Add Word
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4" id="statsCards">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statActive">-</div>
                            <div class="stat-label">Active Words</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statCritical">-</div>
                            <div class="stat-label">Critical Severity</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-chat-left-text"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statProfanity">-</div>
                            <div class="stat-label">Profanity</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <div class="ms-3">
                            <div class="stat-value" id="statCategories">-</div>
                            <div class="stat-label">Categories</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="filterActive">
                            <option value="1">Active</option>
                            <option value="0">Removed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Severity</label>
                        <select class="form-select" id="filterSeverity">
                            <option value="">All</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="filterCategory">
                            <option value="">All</option>
                            <option value="profanity">Profanity</option>
                            <option value="hate-speech">Hate Speech</option>
                            <option value="spam">Spam</option>
                            <option value="explicit">Explicit Content</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-secondary w-100" id="applyFilters">
                            <i class="bi bi-funnel me-1"></i>Apply
                        </button>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="searchBox" placeholder="Search words...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Words Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Word</th>
                                <th>Severity</th>
                                <th>Category</th>
                                <th>Added By</th>
                                <th>Date Added</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="wordsTable">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <nav aria-label="Page navigation" id="paginationContainer" class="mt-3"></nav>
            </div>
        </div>
    </div>
</div>

<!-- Add Word Modal -->
<div class="modal fade" id="addWordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Blacklisted Word</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addWordForm">
                    <div class="mb-3">
                        <label class="form-label">Word *</label>
                        <input type="text" class="form-control" id="addWord" required>
                        <small class="text-muted">Word will be converted to lowercase</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity *</label>
                        <select class="form-select" id="addSeverity" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="addCategory">
                            <option value="">Select category</option>
                            <option value="profanity">Profanity</option>
                            <option value="hate-speech">Hate Speech</option>
                            <option value="spam">Spam</option>
                            <option value="explicit">Explicit Content</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="addNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveWord">Add Word</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Word Modal -->
<div class="modal fade" id="editWordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Blacklisted Word</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editWordForm">
                    <input type="hidden" id="editWordId">
                    <div class="mb-3">
                        <label class="form-label">Word</label>
                        <input type="text" class="form-control" id="editWord" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity *</label>
                        <select class="form-select" id="editSeverity" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="editCategory">
                            <option value="">Select category</option>
                            <option value="profanity">Profanity</option>
                            <option value="hate-speech">Hate Speech</option>
                            <option value="spam">Spam</option>
                            <option value="explicit">Explicit Content</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="editNotes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateWord">Update Word</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentFilters = {
    active: 1,
    severity: '',
    category: ''
};

// Load stats
function loadStats() {
    fetch('api/blacklist.php?action=list&active=1&page=1')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const words = data.data;

                // Active words
                document.getElementById('statActive').textContent = data.pagination.total;

                // Critical severity count
                const criticalCount = words.filter(w => w.severity === 'critical').length;
                document.getElementById('statCritical').textContent = criticalCount;

                // Profanity category count (fetch all to get accurate count)
                fetch('api/blacklist.php?action=list&active=1&category=profanity&page=1')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('statProfanity').textContent = data.pagination.total;
                        }
                    });

                // Unique categories count
                const categories = new Set(words.map(w => w.category).filter(c => c));
                document.getElementById('statCategories').textContent = categories.size;
            }
        })
        .catch(err => {
            console.error('Error loading stats:', err);
        });
}

// Load words
function loadWords(page = 1) {
    currentPage = page;

    const params = new URLSearchParams({
        action: 'list',
        page: page,
        active: currentFilters.active,
        ...(currentFilters.severity && { severity: currentFilters.severity }),
        ...(currentFilters.category && { category: currentFilters.category })
    });

    fetch(`api/blacklist.php?${params}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderWords(data.data);
                renderPagination(data.pagination);
            } else {
                alert('Error loading words: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error loading words');
        });
}

// Render words table
function renderWords(words) {
    const tbody = document.getElementById('wordsTable');

    if (words.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No words found</td></tr>';
        return;
    }

    tbody.innerHTML = words.map(word => {
        const severityColors = {
            low: 'success',
            medium: 'warning',
            high: 'danger',
            critical: 'dark'
        };

        return `
            <tr>
                <td><strong>${escapeHtml(word.word)}</strong></td>
                <td><span class="badge bg-${severityColors[word.severity]}">${word.severity}</span></td>
                <td>${word.category ? escapeHtml(word.category) : '<em class="text-muted">None</em>'}</td>
                <td>${escapeHtml(word.added_by_name)}</td>
                <td>${formatDate(word.created_at)}</td>
                <td>
                    ${word.is_active == 1
                        ? '<span class="badge bg-success">Active</span>'
                        : `<span class="badge bg-secondary">Removed</span><br><small class="text-muted">By ${escapeHtml(word.removed_by_name)}<br>${formatDate(word.removed_at)}</small>`
                    }
                </td>
                <td>
                    ${word.is_active == 1 ? `
                        <button class="btn btn-sm btn-outline-primary" onclick="editWord(${word.id})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeWord(${word.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    ` : ''}
                    ${word.notes ? `
                        <button class="btn btn-sm btn-outline-secondary" onclick="showNotes('${escapeHtml(word.notes)}')">
                            <i class="bi bi-info-circle"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

// Render pagination
function renderPagination(pagination) {
    const container = document.getElementById('paginationContainer');

    if (pagination.totalPages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '<ul class="pagination justify-content-center mb-0">';

    // Previous
    html += `
        <li class="page-item ${pagination.page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadWords(${pagination.page - 1}); return false;">Previous</a>
        </li>
    `;

    // Pages
    for (let i = 1; i <= pagination.totalPages; i++) {
        if (i === 1 || i === pagination.totalPages || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
            html += `
                <li class="page-item ${i === pagination.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="loadWords(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === pagination.page - 3 || i === pagination.page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Next
    html += `
        <li class="page-item ${pagination.page === pagination.totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadWords(${pagination.page + 1}); return false;">Next</a>
        </li>
    `;

    html += '</ul>';
    container.innerHTML = html;
}

// Add word
document.getElementById('saveWord').addEventListener('click', function() {
    const word = document.getElementById('addWord').value.trim();
    const severity = document.getElementById('addSeverity').value;
    const category = document.getElementById('addCategory').value;
    const notes = document.getElementById('addNotes').value.trim();

    if (!word) {
        alert('Please enter a word');
        return;
    }

    fetch('api/blacklist.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ word, severity, category, notes })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addWordModal')).hide();
            document.getElementById('addWordForm').reset();
            loadStats();
            loadWords(currentPage);
            alert('Word added successfully');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error adding word');
    });
});

// Edit word
function editWord(id) {
    fetch(`api/blacklist.php?action=list&active=1`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const word = data.data.find(w => w.id == id);
                if (word) {
                    document.getElementById('editWordId').value = word.id;
                    document.getElementById('editWord').value = word.word;
                    document.getElementById('editSeverity').value = word.severity;
                    document.getElementById('editCategory').value = word.category || '';
                    document.getElementById('editNotes').value = word.notes || '';

                    new bootstrap.Modal(document.getElementById('editWordModal')).show();
                }
            }
        });
}

// Update word
document.getElementById('updateWord').addEventListener('click', function() {
    const id = document.getElementById('editWordId').value;
    const severity = document.getElementById('editSeverity').value;
    const category = document.getElementById('editCategory').value;
    const notes = document.getElementById('editNotes').value.trim();

    fetch('api/blacklist.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, severity, category, notes })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editWordModal')).hide();
            loadStats();
            loadWords(currentPage);
            alert('Word updated successfully');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error updating word');
    });
});

// Remove word
function removeWord(id) {
    if (!confirm('Are you sure you want to remove this word from the blacklist?')) {
        return;
    }

    fetch('api/blacklist.php?action=remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadStats();
            loadWords(currentPage);
            alert('Word removed successfully');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('Error removing word');
    });
}

// Show notes
function showNotes(notes) {
    alert(notes);
}

// Apply filters
document.getElementById('applyFilters').addEventListener('click', function() {
    currentFilters.active = document.getElementById('filterActive').value;
    currentFilters.severity = document.getElementById('filterSeverity').value;
    currentFilters.category = document.getElementById('filterCategory').value;
    loadWords(1);
});

// Search
let searchTimeout;
document.getElementById('searchBox').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();

    if (query.length === 0) {
        loadWords(1);
        return;
    }

    searchTimeout = setTimeout(() => {
        fetch(`api/blacklist.php?action=search&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderWords(data.data);
                    document.getElementById('paginationContainer').innerHTML = '';
                }
            });
    }, 300);
});

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

// Initial load
loadStats();
loadWords(1);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
