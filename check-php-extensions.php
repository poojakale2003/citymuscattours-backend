<?php
/**
 * PHP Extensions Check Script
 * This will show which PHP extensions are loaded when accessed via web server
 */

header('Content-Type: text/html; charset=utf-8');

// Load Composer autoloader so Dotenv and other dependencies are available
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    echo '<div style="padding:10px;background:#ffdddd;border:1px solid #ffaaaa;">';
    echo '<strong>Warning:</strong> vendor/autoload.php not found. Run <code>composer install</code> in the project root.';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Extensions Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h1>PHP Extensions Check</h1>
    
    <div class="info">
        <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
        <strong>PHP.ini Location:</strong> <?php echo php_ini_loaded_file(); ?><br>
        <strong>Server API:</strong> <?php echo php_sapi_name(); ?>
    </div>

    <h2>PDO Extensions Status</h2>
    <table>
        <tr>
            <th>Extension</th>
            <th>Status</th>
            <th>Details</th>
        </tr>
        <tr>
            <td>PDO</td>
            <td><?php echo extension_loaded('pdo') ? '<span class="success">✓ Loaded</span>' : '<span class="error">✗ Not Loaded</span>'; ?></td>
            <td><?php echo extension_loaded('pdo') ? 'PDO extension is available' : 'PDO extension is missing'; ?></td>
        </tr>
        <tr>
            <td>PDO MySQL (pdo_mysql)</td>
            <td><?php echo extension_loaded('pdo_mysql') ? '<span class="success">✓ Loaded</span>' : '<span class="error">✗ Not Loaded</span>'; ?></td>
            <td>
                <?php 
                if (extension_loaded('pdo_mysql')) {
                    echo 'PDO MySQL driver is available';
                    echo '<br>Available PDO drivers: ' . implode(', ', PDO::getAvailableDrivers());
                } else {
                    echo 'PDO MySQL driver is NOT loaded. You need to enable it in php.ini';
                }
                ?>
            </td>
        </tr>
    </table>

    <h2>Database Connection Test</h2>
    <?php
    if (extension_loaded('pdo_mysql')) {
        try {
            require_once __DIR__ . '/src/config/env.php';
            Env::load();
            $config = Env::get('db');
            
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['user'], $config['pass']);
            
            echo '<p class="success">✓ Database connection successful!</p>';
            echo '<p>MySQL Version: ';
            $stmt = $pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            echo $version['version'] . '</p>';
            
        } catch (PDOException $e) {
            echo '<p class="error">✗ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    } else {
        echo '<p class="error">Cannot test database connection - PDO MySQL extension is not loaded.</p>';
    }
    ?>

    <h2>All Loaded Extensions</h2>
    <div class="info">
        <?php
        $extensions = get_loaded_extensions();
        sort($extensions);
        echo implode(', ', $extensions);
        ?>
    </div>

    <hr>
    <p><small>If pdo_mysql is not loaded, restart Apache after enabling it in php.ini</small></p>
</body>
</html>

