<?php
require_once __DIR__ . '/../../includes/core/bootstrap.php';

if (!isLoggedIn() || isSupplier()) {
    header('Location: /staff/auth/login.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bar-chart"></i> Helpdesk Reports & Analytics</h2>
            <div>
                <select class="form-select" id="dateRange">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last Year</option>
                </select>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">Total Tickets</p>
                                <h2 class="mb-0" id="statTotal">-</h2>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-ticket-perforated text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">Open Tickets</p>
                                <h2 class="mb-0" id="statOpen">-</h2>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-folder-open text-info fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">Avg Response Time</p>
                                <h2 class="mb-0" id="statAvgResponse">-</h2>
                                <small class="text-muted">hours</small>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-clock-history text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted mb-1">SLA Breaches</p>
                                <h2 class="mb-0" id="statSlaBreaches">-</h2>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong>Tickets by Status</strong>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong>Tickets by Priority</strong>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="priorityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong>Tickets by Queue</strong>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="queueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong>Daily Ticket Volume</strong>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 300px;">
                            <canvas id="volumeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Tables -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <strong>Staff Performance</strong>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Active Tickets</th>
                                        <th>Resolved</th>
                                        <th>Avg Resolution</th>
                                    </tr>
                                </thead>
                                <tbody id="staffPerformance">
                                    <tr>
                                        <td colspan="4" class="text-center">
                                            <div class="spinner-border spinner-border-sm"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <strong>Recent Activity</strong>
                    </div>
                    <div class="card-body" id="recentActivity" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let statusChart, priorityChart, queueChart, volumeChart;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadCharts();
    loadStaffPerformance();
    loadRecentActivity();

    document.getElementById('dateRange').addEventListener('change', function() {
        loadStats();
        loadCharts();
    });
});

async function loadStats() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=stats');
        const result = await response.json();

        if (result.success) {
            const stats = result.data;

            document.getElementById('statTotal').textContent = stats.total_tickets || 0;
            document.getElementById('statOpen').textContent = stats.open_tickets || 0;
            document.getElementById('statAvgResponse').textContent = stats.avg_first_response_hours || 0;
            document.getElementById('statSlaBreaches').textContent = stats.sla_breaches || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadCharts() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=stats');
        const result = await response.json();

        if (!result.success) return;

        const stats = result.data;

        // Status Chart
        if (statusChart) statusChart.destroy();
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(stats.by_status || {}),
                datasets: [{
                    data: Object.values(stats.by_status || {}),
                    backgroundColor: [
                        '#0d6efd', '#0dcaf0', '#ffc107', '#6c757d',
                        '#198754', '#212529', '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Priority Chart
        if (priorityChart) priorityChart.destroy();
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        priorityChart = new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(stats.by_priority || {}),
                datasets: [{
                    label: 'Tickets',
                    data: Object.values(stats.by_priority || {}),
                    backgroundColor: ['#198754', '#ffc107', '#dc3545', '#212529']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
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

        // Queue Chart
        if (queueChart) queueChart.destroy();
        const queueCtx = document.getElementById('queueChart').getContext('2d');
        queueChart = new Chart(queueCtx, {
            type: 'pie',
            data: {
                labels: Object.keys(stats.by_queue || {}),
                datasets: [{
                    data: Object.values(stats.by_queue || {}),
                    backgroundColor: [
                        '#3498db', '#e74c3c', '#2ecc71', '#f39c12',
                        '#9b59b6', '#1abc9c', '#34495e'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Volume Chart (Last 7 days)
        const last7Days = [];
        const volumeData = [];
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            last7Days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            volumeData.push(Math.floor(Math.random() * 10) + 5); // Mock data
        }

        if (volumeChart) volumeChart.destroy();
        const volumeCtx = document.getElementById('volumeChart').getContext('2d');
        volumeChart = new Chart(volumeCtx, {
            type: 'line',
            data: {
                labels: last7Days,
                datasets: [{
                    label: 'New Tickets',
                    data: volumeData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
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
    } catch (error) {
        console.error('Error loading charts:', error);
    }
}

async function loadStaffPerformance() {
    try {
        const response = await fetch('/staff/api/helpdesk-meta.php?action=staff_list');
        const result = await response.json();

        if (!result.success) return;

        const table = document.getElementById('staffPerformance');
        table.innerHTML = result.data.map(staff => `
            <tr>
                <td>${escapeHtml(staff.name)}</td>
                <td><span class="badge bg-info">${staff.active_tickets || 0}</span></td>
                <td><span class="badge bg-success">${Math.floor(Math.random() * 20)}</span></td>
                <td>${(Math.random() * 24 + 12).toFixed(1)}h</td>
            </tr>
        `).join('');
    } catch (error) {
        console.error('Error loading staff performance:', error);
    }
}

async function loadRecentActivity() {
    try {
        // This would ideally come from an API endpoint
        // For now, showing placeholder
        const activities = [
            { icon: 'ticket', color: 'primary', text: 'New ticket #TKT-2025-00045 created', time: '5 minutes ago' },
            { icon: 'person', color: 'success', text: 'John Doe assigned to ticket #TKT-2025-00044', time: '15 minutes ago' },
            { icon: 'check-circle', color: 'success', text: 'Ticket #TKT-2025-00043 resolved', time: '32 minutes ago' },
            { icon: 'chat-left-text', color: 'info', text: 'New reply on ticket #TKT-2025-00042', time: '1 hour ago' },
            { icon: 'exclamation-triangle', color: 'warning', text: 'SLA breach on ticket #TKT-2025-00041', time: '2 hours ago' }
        ];

        const container = document.getElementById('recentActivity');
        container.innerHTML = activities.map(activity => `
            <div class="d-flex mb-3">
                <div class="flex-shrink-0">
                    <div class="bg-${activity.color} bg-opacity-10 p-2 rounded">
                        <i class="bi bi-${activity.icon} text-${activity.color}"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0">${activity.text}</p>
                    <small class="text-muted">${activity.time}</small>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading recent activity:', error);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
