<?php
declare(strict_types=1);

echo "<h1>Social Donor Platform - Setup Test</h1>";

// Test 1: Check PHP version
echo "<h2>1. PHP Version Check</h2>";
$phpVersion = PHP_VERSION;
echo "Current PHP Version: <strong>{$phpVersion}</strong><br>";
if (version_compare($phpVersion, '8.0.0', '>=')) {
    echo "✅ PHP version is compatible (8.0+ required)<br>";
} else {
    echo "❌ PHP version is too old. Please upgrade to PHP 8.0 or higher<br>";
}

// Test 2: Check required extensions
echo "<h2>2. Required PHP Extensions</h2>";
$requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'json'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ {$ext} - Loaded<br>";
    } else {
        echo "❌ {$ext} - Missing<br>";
    }
}

// Test 3: Check directory structure
echo "<h2>3. Directory Structure Check</h2>";
$requiredDirs = ['uploads', 'uploads/profiles', 'uploads/documents', 'logs', 'php'];
foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ {$dir}/ - Exists<br>";
        if (is_writable($dir)) {
            echo "   ✅ Writable<br>";
        } else {
            echo "   ❌ Not writable<br>";
        }
    } else {
        echo "❌ {$dir}/ - Missing<br>";
    }
}

// Test 4: Check .htaccess
echo "<h2>4. .htaccess File</h2>";
if (file_exists('.htaccess')) {
    echo "✅ .htaccess - Exists<br>";
} else {
    echo "❌ .htaccess - Missing<br>";
}

// Test 5: Test database connection
echo "<h2>5. Database Connection Test</h2>";
try {
    // Load config if exists
    if (file_exists('php/config.php')) {
        require_once 'php/config.php';
        $pdo = get_db_connection();
        echo "✅ Database connection successful<br>";
        
        // Test basic query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'socialdonor'");
        $result = $stmt->fetch();
        echo "✅ Database query successful - Found {$result['count']} tables<br>";
    } else {
        echo "❌ Config file not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "<br>";
}

// Test 6: Check file upload settings
echo "<h2>6. File Upload Settings</h2>";
echo "Max Upload File Size: " . ini_get('upload_max_filesize') . "<br>";
echo "Max POST Size: " . ini_get('post_max_size') . "<br>";
echo "File Uploads Enabled: " . (ini_get('file_uploads') ? 'Yes' : 'No') . "<br>";

// Test 7: Check security settings
echo "<h2>7. Security Settings</h2>";
echo "Display Errors: " . (ini_get('display_errors') ? 'On (should be Off in production)' : 'Off') . "<br>";
echo "Error Reporting: " . (ini_get('error_reporting') ? 'Enabled' : 'Disabled') . "<br>";

// Test 8: Check session settings
echo "<h2>8. Session Settings</h2>";
echo "Session Save Path: " . ini_get('session.save_path') . "<br>";
echo "Session Cookie Secure: " . (ini_get('session.cookie_secure') ? 'Yes' : 'No') . "<br>";
echo "Session Cookie HttpOnly: " . (ini_get('session.cookie_httponly') ? 'Yes' : 'No') . "<br>";

// Test 9: Check for .env file
echo "<h2>9. Environment Configuration</h2>";
if (file_exists('.env')) {
    echo "✅ .env file exists<br>";
} else {
    echo "⚠️ .env file not found. Copy .env.example to .env and configure<br>";
}

// Test 10: Check for database.sql
echo "<h2>10. Database Schema</h2>";
if (file_exists('database.sql')) {
    echo "✅ database.sql exists<br>";
    $size = filesize('database.sql');
    echo "   File size: " . round($size / 1024, 2) . " KB<br>";
} else {
    echo "❌ database.sql not found<br>";
}

echo "<h2>Setup Complete!</h2>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Copy .env.example to .env and configure your settings</li>";
echo "<li>Import database.sql into your MySQL server</li>";
echo "<li>Update database credentials in php/config.php</li>";
echo "<li>Test the application by accessing the frontend</li>";
echo "</ol>";

echo "<p><small>Generated: " . date('Y-m-d H:i:s') . "</small></p>";
?>
