<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camynia - Business Management Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .hero {
            background: var(--primary-gradient);
            color: white;
            padding: 100px 0;
            min-height: 500px;
            display: flex;
            align-items: center;
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/public/">
                <i class="bi bi-building"></i> Camynia
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2 text-white" href="/installer/">Get Started</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-3 fw-bold mb-4">All-in-One Business Management</h1>
                    <p class="lead mb-4">
                        Streamline your HR, recruiting, training, and employee management in one powerful platform.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="/installer/" class="btn btn-light btn-lg px-4">
                            Start Free Trial
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4">
                            Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="bi bi-laptop" style="font-size: 200px; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold">Powerful Features</h2>
                <p class="lead text-muted">Everything you need to manage your workforce effectively</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                            <i class="bi bi-people"></i>
                        </div>
                        <h4>Employee Management</h4>
                        <p class="text-muted">Comprehensive HRIS with employee profiles, org charts, and document management.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto" style="background: rgba(46, 204, 113, 0.1); color: #2ecc71;">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h4>Time Off & Attendance</h4>
                        <p class="text-muted">Track time off requests, manage approvals, and monitor attendance with ease.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto" style="background: rgba(155, 89, 182, 0.1); color: #9b59b6;">
                            <i class="bi bi-briefcase"></i>
                        </div>
                        <h4>Recruiting & ATS</h4>
                        <p class="text-muted">Post jobs, track applicants, and streamline your hiring process.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto" style="background: rgba(241, 196, 15, 0.1); color: #f1c40f;">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h4>Performance Reviews</h4>
                        <p class="text-muted">Conduct reviews, set goals, and track employee performance over time.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto" style="background: rgba(231, 76, 60, 0.1); color: #e74c3c;">
                            <i class="bi bi-book"></i>
                        </div>
                        <h4>Training & Compliance</h4>
                        <p class="text-muted">Assign courses, track certifications, and ensure compliance.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto" style="background: rgba(52, 73, 94, 0.1); color: #34495e;">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                        <h4>Analytics & Reporting</h4>
                        <p class="text-muted">Gain insights with comprehensive HR analytics and custom reports.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5" style="background: var(--primary-gradient);">
        <div class="container text-center text-white py-5">
            <h2 class="display-5 fw-bold mb-4">Ready to Get Started?</h2>
            <p class="lead mb-4">Join thousands of companies using Camynia to manage their workforce</p>
            <a href="/installer/" class="btn btn-light btn-lg px-5">
                Start Your Free Trial
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="bi bi-building"></i> Camynia</h5>
                    <p class="text-muted">Modern business management platform for the digital age.</p>
                </div>
                <div class="col-md-4">
                    <h6>Product</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">Features</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Pricing</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Documentation</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6>Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">About</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Contact</a></li>
                        <li><a href="/login.php" class="text-muted text-decoration-none">Login</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-secondary">
            <div class="text-center text-muted">
                <small>&copy; 2025 Camynia. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
