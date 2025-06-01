<?php
declare(strict_types=1);

// 1. Show all PHP errors (for dev; hide later in production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. Define project-root constants
define('BASE_PATH', dirname(__DIR__));           // project-root/
define('APP_PATH', BASE_PATH . '/app');          // project-root/app/
define('VIEW_PATH', APP_PATH . '/Views');        // project-root/app/Views/

// 3. Autoload via Composer (PSR-4)
require BASE_PATH . '/vendor/autoload.php';

// 4. Bootstrap and run the Application
$app = new \OmniSIS\Core\Application();
$app->run();
