<?php
/**
 * Login Page
 * Handles authentication for all user types
 */

require_once __DIR__ . '/includes/core/bootstrap.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $user = currentUser();
    if ($user['is_supplier']) {
        redirect('/supplier/');
    } else {
        redirect('/staff/');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            // Find user by email
            $stmt = $db->prepare("
                SELECT u.*, s.company_name as supplier_name, c.company_name, c.id as company_id
                FROM users u
                LEFT JOIN suppliers s ON u.supplier_id = s.id
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE u.email = ? AND u.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful login
                Session::regenerate();

                // Store user data in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'is_supplier' => $user['is_supplier'],
                    'is_company_admin' => $user['is_company_admin'],
                    'company_id' => $user['company_id'],
                    'supplier_id' => $user['supplier_id'],
                    'company_name' => $user['company_name'] ?? $user['supplier_name']
                ];

                // Update last login
                $stmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Redirect based on user type
                if ($user['is_supplier']) {
                    redirect('/supplier/');
                } else {
                    redirect('/staff/');
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Camynia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 450px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #dee2e6;
        }
        .divider span {
            background: #fff;
            padding: 0 15px;
            position: relative;
            color: #6c757d;
        }
        .sso-btn {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }
        .sso-btn:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="text-center mb-4">
            <h1 class="text-white mb-2">Camynia</h1>
            <p class="text-white-50">Business Management Platform</p>
        </div>

        <div class="card">
            <div class="card-body p-5">
                <h3 class="text-center mb-4">Sign In</h3>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php
                $flash = getFlash();
                if ($flash):
                ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">Sign In</button>

                    <div class="text-center">
                        <a href="/forgot-password.php" class="text-decoration-none">Forgot password?</a>
                    </div>
                </form>

                <div class="divider">
                    <span>Or continue with</span>
                </div>

                <div class="sso-buttons">
                    <a href="/auth/google.php" class="sso-btn text-decoration-none text-dark">
                        <svg width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Sign in with Google
                    </a>

                    <a href="/auth/microsoft.php" class="sso-btn text-decoration-none text-dark">
                        <svg width="20" height="20" viewBox="0 0 23 23">
                            <path fill="#f3f3f3" d="M0 0h23v23H0z"/>
                            <path fill="#f35325" d="M1 1h10v10H1z"/>
                            <path fill="#81bc06" d="M12 1h10v10H12z"/>
                            <path fill="#05a6f0" d="M1 12h10v10H1z"/>
                            <path fill="#ffba08" d="M12 12h10v10H12z"/>
                        </svg>
                        Sign in with Microsoft
                    </a>

                    <a href="/auth/discord.php" class="sso-btn text-decoration-none text-dark">
                        <svg width="20" height="20" viewBox="0 0 71 55" fill="#5865F2">
                            <path d="M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.038 50.6034 51.2554 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1066 30.1693C30.1066 34.1136 27.28 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7636 23.0133 47.3178 23.0133C50.9 23.0133 53.7545 26.2532 53.6986 30.1693C53.6986 34.1136 50.9 37.3253 47.3178 37.3253Z"/>
                        </svg>
                        Sign in with Discord
                    </a>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="/public/" class="text-white text-decoration-none">
                <i class="bi bi-arrow-left"></i> Back to homepage
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
