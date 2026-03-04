<?php
/**
 * UBIDS Student ID Card Photo Portal - Installation Script
 * 
 * This script will set up the database and create the initial admin user
 */

// Define application constant
define('UBIDS_PORTAL', true);

// Include configuration
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if already installed
if (is_installed()) {
    die('<h1>Already Installed</h1><p>The UBIDS ID Card Portal is already installed. To reinstall, delete the <code>installed.lock</code> file.</p>');
}

// Process installation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? 'ubids_portal';
    $db_user = $_POST['db_user'] ?? 'root';
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_username = $_POST['admin_username'] ?? 'admin';
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $errors[] = 'Database connection details are required';
    }
    
    if (empty($admin_username) || empty($admin_password) || empty($admin_email)) {
        $errors[] = 'Admin account details are required';
    }
    
    if (!validate_email($admin_email)) {
        $errors[] = 'Invalid admin email address';
    }
    
    if (strlen($admin_password) < 8) {
        $errors[] = 'Admin password must be at least 8 characters long';
    }
    
    if (empty($errors)) {
        try {
            // Test database connection
            $dsn = "mysql:host=$db_host;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");
            
            // Read and execute SQL schema
            $schema_file = __DIR__ . '/database_schema.sql';
            if (file_exists($schema_file)) {
                $schema = file_get_contents($schema_file);
                
                // Remove comments and split into statements
                $statements = array_filter(array_map('trim', explode(';', $schema)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        $pdo->exec($statement);
                    }
                }
                
                // Update admin user
                $password_hash = hash_password($admin_password);
                $pdo->prepare("UPDATE admins SET username = ?, password_hash = ?, full_name = ? WHERE username = 'admin'")
                   ->execute([$admin_username, $password_hash, $admin_email]);
                
                // Create installation lock file
                file_put_contents(__DIR__ . '/installed.lock', date('Y-m-d H:i:s'));
                
                // Redirect to success page
                header('Location: install.php?success=1');
                exit;
                
            } else {
                $errors[] = 'Database schema file not found';
            }
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Display errors
    if (!empty($errors)) {
        $error_html = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">';
        $error_html .= '<strong>Installation Errors:</strong><ul class="list-disc ml-4 mt-2">';
        foreach ($errors as $error) {
            $error_html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $error_html .= '</ul></div>';
    }
}

// Check for successful installation
if (isset($_GET['success'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation Complete - UBIDS Portal</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="h-8 w-8 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Installation Complete!</h1>
                <p class="text-gray-600 mb-6">The UBIDS ID Card Portal has been successfully installed.</p>
                
                <div class="space-y-3 text-left">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="font-medium text-blue-800 mb-2">Next Steps:</h3>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Delete the <code>install.php</code> file for security</li>
                            <li>• Configure your web server for production</li>
                            <li>• Set up SSL certificate for HTTPS</li>
                            <li>• Import student data into the database</li>
                        </ul>
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h3 class="font-medium text-green-800 mb-2">Quick Links:</h3>
                        <div class="space-y-2">
                            <a href="admin/login.php" class="block text-sm text-green-700 hover:text-green-900">Admin Login →</a>
                            <a href="index.php" class="block text-sm text-green-700 hover:text-green-900">Student Portal →</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install UBIDS Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Lora', serif; }
        .display-font { font-family: 'Syne', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-2xl w-full">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto h-16 w-16 bg-green-800 rounded-full flex items-center justify-center mb-4">
                    <svg class="h-10 w-10 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <h1 class="display-font text-3xl font-bold text-gray-900">UBIDS Portal Installation</h1>
                <p class="text-gray-600 mt-2">Set up your Student ID Card Photo Portal</p>
            </div>

            <!-- Installation Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <?php if (isset($error_html)): echo $error_html; endif; ?>
                
                <form method="POST" class="space-y-6">
                    <!-- Database Configuration -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Database Configuration</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Host</label>
                                <input type="text" name="db_host" value="localhost" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
                                <input type="text" name="db_name" value="ubids_portal" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Username</label>
                                <input type="text" name="db_user" value="root" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Database Password</label>
                                <input type="password" name="db_pass"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                        </div>
                    </div>

                    <!-- Admin Account -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Administrator Account</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Username</label>
                                <input type="text" name="admin_username" value="admin" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                                <input type="email" name="admin_email" placeholder="admin@ubids.edu.gh" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Password</label>
                                <input type="password" name="admin_password" required minlength="8"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                <input type="password" name="admin_password_confirm" required minlength="8"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-800">
                            </div>
                        </div>
                    </div>

                    <!-- System Requirements Check -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2">System Requirements</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center">
                                <span class="<?php echo version_compare(PHP_VERSION, '8.2.0', '>=') ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo version_compare(PHP_VERSION, '8.2.0', '>=') ? '✓' : '✗'; ?>
                                </span>
                                <span class="ml-2">PHP <?php echo PHP_VERSION; ?> (required: 8.2+)</span>
                            </div>
                            <div class="flex items-center">
                                <span class="<?php echo extension_loaded('pdo_mysql') ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo extension_loaded('pdo_mysql') ? '✓' : '✗'; ?>
                                </span>
                                <span class="ml-2">MySQL PDO Extension</span>
                            </div>
                            <div class="flex items-center">
                                <span class="<?php echo extension_loaded('gd') ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo extension_loaded('gd') ? '✓' : '✗'; ?>
                                </span>
                                <span class="ml-2">GD Extension</span>
                            </div>
                            <div class="flex items-center">
                                <span class="<?php echo extension_loaded('fileinfo') ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo extension_loaded('fileinfo') ? '✓' : '✗'; ?>
                                </span>
                                <span class="ml-2">Fileinfo Extension</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" 
                                class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-800">
                            Install Portal
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-sm text-gray-500">
                <p>UBIDS Student ID Card Photo Portal v1.0.0</p>
                <p>© 2024 University of Business and Integrated Development Studies</p>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="admin_password"]').value;
            const confirm = document.querySelector('input[name="admin_password_confirm"]').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Password confirmation does not match!');
                return false;
            }
        });
    </script>
</body>
</html>
