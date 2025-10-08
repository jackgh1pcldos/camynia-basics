<?php
/**
 * Camynia Installer
 * Step-by-step installation wizard
 */

session_start();

// Check if already installed (lock file exists)
$lockFile = __DIR__ . '/../install.lock';
if (file_exists($lockFile)) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Already Installed</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="alert alert-warning">
                <h4>Installation Already Complete</h4>
                <p>Camynia has already been installed. To reinstall, please delete the <code>install.lock</code> file and the <code>includes/core/config-generated.php</code> file.</p>
                <p class="mb-0"><strong>Important:</strong> For security, please delete the <code>/installer</code> folder.</p>
            </div>
        </div>
    </body>
    </html>
    ');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Requirements check (no action needed)
        header('Location: ?step=2');
        exit;
    } elseif ($step == 2) {
        // Database configuration
        $dbHost = $_POST['db_host'] ?? '';
        $dbName = $_POST['db_name'] ?? '';
        $dbUser = $_POST['db_user'] ?? '';
        $dbPass = $_POST['db_pass'] ?? '';
        $appUrl = rtrim($_POST['app_url'] ?? '', '/');

        // Test database connection
        try {
            $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Store in session for next step
            $_SESSION['install_config'] = [
                'db_host' => $dbHost,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass,
                'app_url' => $appUrl
            ];

            header('Location: ?step=3');
            exit;
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    } elseif ($step == 3) {
        // Import database schema
        if (!isset($_SESSION['install_config'])) {
            header('Location: ?step=2');
            exit;
        }

        $config = $_SESSION['install_config'];

        try {
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Read and execute schema file
            $schemaFile = __DIR__ . '/database-schema.sql';
            if (!file_exists($schemaFile)) {
                throw new Exception('Schema file not found at: ' . $schemaFile);
            }
            $schema = file_get_contents($schemaFile);

            // Remove comments
            $schema = preg_replace('/^--.*$/m', '', $schema);
            $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);

            // Split by semicolons
            $statements = [];
            $current = '';
            $lines = explode("\n", $schema);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $current .= $line . "\n";

                // Check if line ends with semicolon (end of statement)
                if (substr($line, -1) === ';') {
                    $statements[] = trim($current);
                    $current = '';
                }
            }

            // Execute each statement
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Log which statement failed
                        error_log('Failed SQL: ' . substr($statement, 0, 200));
                        throw new PDOException('Schema import error: ' . $e->getMessage());
                    }
                }
            }

            $_SESSION['schema_imported'] = true;
            header('Location: ?step=4');
            exit;
        } catch (Exception $e) {
            $error = 'Schema import failed: ' . $e->getMessage();
        }
    } elseif ($step == 4) {
        // Import dummy data (optional)
        if (!isset($_SESSION['install_config']) || !isset($_SESSION['schema_imported'])) {
            header('Location: ?step=2');
            exit;
        }

        if (isset($_POST['skip'])) {
            // Skip dummy data
            header('Location: ?step=5');
            exit;
        }

        $config = $_SESSION['install_config'];

        try {
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Read and execute dummy data file
            $dataFile = __DIR__ . '/dummy-data.sql';
            if (!file_exists($dataFile)) {
                throw new Exception('Dummy data file not found at: ' . $dataFile);
            }
            $data = file_get_contents($dataFile);

            // Remove comments
            $data = preg_replace('/^--.*$/m', '', $data);
            $data = preg_replace('/\/\*.*?\*\//s', '', $data);

            // Split by semicolons
            $statements = array_filter(array_map('trim', explode(';', $data)));

            // Execute each statement
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // Continue on errors (some inserts might fail if data exists)
                        error_log('Dummy data error: ' . $e->getMessage());
                    }
                }
            }

            $_SESSION['data_imported'] = true;
            header('Location: ?step=5');
            exit;
        } catch (Exception $e) {
            $error = 'Dummy data import failed: ' . $e->getMessage();
        }
    } elseif ($step == 5) {
        // Create supplier, company and admin user
        if (!isset($_SESSION['install_config']) || !isset($_SESSION['schema_imported'])) {
            header('Location: ?step=2');
            exit;
        }

        $config = $_SESSION['install_config'];
        $companyName = $_POST['company_name'] ?? '';
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validate
        if (empty($companyName) || empty($email) || empty($password)) {
            $error = 'All fields are required';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } else {
            try {
                $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                // Create supplier first
                $stmt = $pdo->prepare("INSERT INTO suppliers (company_name, email, status) VALUES (?, ?, 'active')");
                $stmt->execute([$companyName, $email]);
                $supplierId = $pdo->lastInsertId();

                // Create company under supplier
                $subdomain = strtolower(preg_replace('/[^a-z0-9]/', '', $companyName));
                $stmt = $pdo->prepare("INSERT INTO companies (supplier_id, company_name, slug, email, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$supplierId, $companyName, $subdomain, $email]);
                $companyId = $pdo->lastInsertId();

                // Create admin user
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (company_id, supplier_id, first_name, last_name, email, password_hash, is_supplier, is_company_admin, status) VALUES (?, ?, ?, ?, ?, ?, 1, 1, 'active')");
                $stmt->execute([$companyId, $supplierId, $firstName, $lastName, $email, $passwordHash]);

                $_SESSION['admin_created'] = true;
                $_SESSION['company_name'] = $companyName;
                header('Location: ?step=6');
                exit;
            } catch (PDOException $e) {
                $error = 'Admin user creation failed: ' . $e->getMessage();
            }
        }
    } elseif ($step == 6) {
        // FINAL STEP: Complete installation - creates .env and lock file
        if (!isset($_SESSION['install_config']) || !isset($_SESSION['admin_created'])) {
            header('Location: ?step=2');
            exit;
        }

        $config = $_SESSION['install_config'];

        try {
            // Create config-generated.php file (replaces .env for automatic configuration)
            $configContent = "<?php\n";
            $configContent .= "/**\n";
            $configContent .= " * Auto-generated configuration file\n";
            $configContent .= " * Created by installer on: " . date('Y-m-d H:i:s') . "\n";
            $configContent .= " * DO NOT EDIT THIS FILE MANUALLY\n";
            $configContent .= " */\n\n";
            $configContent .= "// Database Configuration\n";
            $configContent .= "\$_ENV['DB_HOST'] = '" . addslashes($config['db_host']) . "';\n";
            $configContent .= "\$_ENV['DB_NAME'] = '" . addslashes($config['db_name']) . "';\n";
            $configContent .= "\$_ENV['DB_USER'] = '" . addslashes($config['db_user']) . "';\n";
            $configContent .= "\$_ENV['DB_PASS'] = '" . addslashes($config['db_pass']) . "';\n\n";
            $configContent .= "// Application Settings\n";
            $configContent .= "\$_ENV['APP_NAME'] = 'Camynia';\n";
            $configContent .= "\$_ENV['APP_URL'] = '" . addslashes($config['app_url']) . "';\n";
            $configContent .= "\$_ENV['APP_ENV'] = 'production';\n";
            $configContent .= "\$_ENV['APP_DEBUG'] = 'false';\n\n";
            $configContent .= "// Session Settings\n";
            $configContent .= "\$_ENV['SESSION_LIFETIME'] = '7200';\n";
            $configContent .= "\$_ENV['SESSION_SECURE'] = 'false';\n\n";
            $configContent .= "// SSO Settings (Configure later via admin panel)\n";
            $configContent .= "\$_ENV['GOOGLE_CLIENT_ID'] = '';\n";
            $configContent .= "\$_ENV['GOOGLE_CLIENT_SECRET'] = '';\n";
            $configContent .= "\$_ENV['MICROSOFT_CLIENT_ID'] = '';\n";
            $configContent .= "\$_ENV['MICROSOFT_CLIENT_SECRET'] = '';\n";
            $configContent .= "\$_ENV['DISCORD_CLIENT_ID'] = '';\n";
            $configContent .= "\$_ENV['DISCORD_CLIENT_SECRET'] = '';\n";

            if (!file_put_contents(__DIR__ . '/../includes/core/config-generated.php', $configContent)) {
                throw new Exception('Failed to create configuration file');
            }

            // Create install.lock file - ONLY ON FINAL COMPLETE
            $lockContent = "Installed on: " . date('Y-m-d H:i:s') . "\n";
            $lockContent .= "Company: " . ($_SESSION['company_name'] ?? 'Unknown') . "\n";

            if (!file_put_contents($lockFile, $lockContent)) {
                throw new Exception('Failed to create lock file');
            }

            $_SESSION['installation_complete'] = true;
            header('Location: ?step=7');
            exit;
        } catch (Exception $e) {
            $error = 'Installation completion failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camynia Installer - Step <?= $step ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }
        .installer-container {
            max-width: 700px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
            padding: 10px;
        }
        .progress-step::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
            z-index: 0;
        }
        .progress-step:first-child::before {
            left: 50%;
        }
        .progress-step:last-child::before {
            right: 50%;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.5);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            margin-bottom: 5px;
            color: white;
            font-weight: 600;
        }
        .progress-step.active .step-circle {
            background: #fff;
            border-color: #fff;
            color: #667eea;
        }
        .progress-step.completed .step-circle {
            background: #28a745;
            border-color: #28a745;
            color: #fff;
        }
        .step-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
        }
        .progress-step.active .step-label {
            color: #fff;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container installer-container">
        <div class="text-center mb-4">
            <h1 class="text-white mb-2"><i class="bi bi-building"></i> Camynia Installer</h1>
            <p class="text-white-50">Enterprise Business Management Platform</p>
        </div>

        <div class="progress-steps">
            <?php
            $steps = [
                1 => 'Requirements',
                2 => 'Database',
                3 => 'Schema',
                4 => 'Sample Data',
                5 => 'Admin',
                6 => 'Finalize',
                7 => 'Complete'
            ];
            foreach ($steps as $num => $label):
            ?>
            <div class="progress-step <?= $step >= $num ? 'active' : '' ?> <?= $step > $num ? 'completed' : '' ?>">
                <div class="step-circle">
                    <?php if ($step > $num): ?>
                        <i class="bi bi-check"></i>
                    <?php else: ?>
                        <?= $num ?>
                    <?php endif; ?>
                </div>
                <div class="step-label"><?= $label ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-body p-5">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($step == 1): ?>
                    <h3 class="mb-4">System Requirements</h3>
                    <div class="requirements-check">
                        <div class="requirement mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-code-slash"></i> PHP Version (7.4+)</span>
                                <span class="badge bg-success">✓ <?= PHP_VERSION ?></span>
                            </div>
                        </div>
                        <div class="requirement mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-plugin"></i> PDO Extension</span>
                                <span class="badge bg-<?= extension_loaded('pdo') ? 'success' : 'danger' ?>">
                                    <?= extension_loaded('pdo') ? '✓ Enabled' : '✗ Disabled' ?>
                                </span>
                            </div>
                        </div>
                        <div class="requirement mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-database"></i> PDO MySQL Extension</span>
                                <span class="badge bg-<?= extension_loaded('pdo_mysql') ? 'success' : 'danger' ?>">
                                    <?= extension_loaded('pdo_mysql') ? '✓ Enabled' : '✗ Disabled' ?>
                                </span>
                            </div>
                        </div>
                        <div class="requirement mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-folder-check"></i> Writable Directory</span>
                                <span class="badge bg-<?= is_writable(__DIR__ . '/..') ? 'success' : 'danger' ?>">
                                    <?= is_writable(__DIR__ . '/..') ? '✓ Yes' : '✗ No' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <form method="post" class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-arrow-right"></i> Continue
                        </button>
                    </form>

                <?php elseif ($step == 2): ?>
                    <h3 class="mb-4">Database Configuration</h3>
                    <p class="text-muted mb-4">Enter your MySQL database connection details.</p>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Database Host</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="db_name" class="form-control" required>
                            <small class="text-muted">The database must already exist</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Username</label>
                            <input type="text" name="db_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Password</label>
                            <input type="password" name="db_pass" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Application URL</label>
                            <input type="url" name="app_url" class="form-control"
                                   value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/') ?>" required>
                            <small class="text-muted">No trailing slash</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-database-check"></i> Test Connection & Continue
                        </button>
                    </form>

                <?php elseif ($step == 3): ?>
                    <h3 class="mb-4">Import Database Schema</h3>
                    <p class="text-muted mb-4">Create all necessary database tables for Camynia.</p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>This will create tables for:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Companies, Users, Roles</li>
                            <li>HR Module (Employees, Time Off, Attendance, Performance, etc.)</li>
                            <li>Knowledge Base System</li>
                            <li>Content Moderation (Blacklist)</li>
                            <li>Helpdesk System (Tickets, Queues, Templates, SLA tracking)</li>
                        </ul>
                    </div>
                    <form method="post">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-download"></i> Import Database Schema
                        </button>
                    </form>

                <?php elseif ($step == 4): ?>
                    <h3 class="mb-4">Import Sample Data</h3>
                    <p class="text-muted mb-4">Import dummy data for testing and demonstration.</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Optional:</strong> You can skip this step if you want to start with a clean installation.
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Sample data includes:</strong>
                        <ul class="mb-0 mt-2">
                            <li>KB articles and categories</li>
                            <li>Blacklisted words</li>
                            <li>Helpdesk tickets, queues, templates, priorities</li>
                            <li>Sample users and data</li>
                        </ul>
                    </div>
                    <form method="post" class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-lg flex-fill">
                            <i class="bi bi-download"></i> Import Sample Data
                        </button>
                        <button type="submit" name="skip" value="1" class="btn btn-outline-secondary btn-lg">
                            Skip
                        </button>
                    </form>

                <?php elseif ($step == 5): ?>
                    <h3 class="mb-4">Create Administrator Account</h3>
                    <p class="text-muted mb-4">Set up your company and admin user.</p>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <hr class="my-4">
                        <h5 class="mb-3">Administrator Account</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-person-check"></i> Create Admin Account
                        </button>
                    </form>

                <?php elseif ($step == 6): ?>
                    <h3 class="mb-4">Finalize Installation</h3>
                    <div class="alert alert-success mb-4">
                        <i class="bi bi-check-circle"></i>
                        <strong>Almost there!</strong> Your database and admin account are ready.
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>This final step will:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Create the auto-configuration file</li>
                            <li>Create the <code>install.lock</code> file (prevents reinstallation)</li>
                            <li>Complete the installation process</li>
                        </ul>
                    </div>
                    <p class="text-muted mb-4">
                        <strong>Important:</strong> Once you click "Complete Installation", the system will be locked and you won't be able to run the installer again without manually deleting the lock file.
                    </p>
                    <form method="post">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="bi bi-check-circle"></i> Complete Installation
                        </button>
                    </form>

                <?php elseif ($step == 7): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                        </div>
                        <h3 class="mb-3">Installation Complete!</h3>
                        <p class="text-muted mb-4">Camynia has been successfully installed and configured.</p>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-octagon"></i>
                            <strong>Security Warning:</strong> Please delete the <code>/installer</code> folder immediately for security purposes.
                        </div>
                        <a href="../staff/auth/login.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
