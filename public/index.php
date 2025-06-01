<?php
declare(strict_types=1);

// 1. Show all PHP errors (for dev; hide later in prod)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. Define paths
define('BASE_PATH', dirname(__DIR__));        // project-root
define('APP_PATH', BASE_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/Views');

// 3. Autoload via Composer
require BASE_PATH . '/vendor/autoload.php';

use OmniSIS\Core\Logger;
use OmniSIS\Core\Controllers\HomeController;

// 4. Instantiate the singleton logger
$logger = Logger::getInstance();

// 5. Log that we hit the front controller
$logger->info('Front controller invoked', [
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
]);

// 6. Parse the “route” from the rewritten URL (via .htaccess → index.php?route=…)
$route = $_GET['route'] ?? '';
$parts = array_filter(explode('/', $route));
$controllerName = 'Home';
$actionName     = 'index';

// If URL was /foo/bar, $parts[0] = 'foo', $parts[1] = 'bar'
if (isset($parts[0]) && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $parts[0])) {
    $controllerName = ucfirst($parts[0]);
}
if (isset($parts[1]) && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $parts[1])) {
    $actionName = $parts[1];
}

$controllerClass = "OmniSIS\\Core\\Controllers\\{$controllerName}Controller";
$actionMethod    = $actionName;

// 7. Check class exists
if (!class_exists($controllerClass)) {
    http_response_code(404);
    echo "Controller not found: {$controllerClass}";
    exit;
}

// 8. Instantiate and call the action
$controller = new $controllerClass($logger);
if (!method_exists($controller, $actionMethod)) {
    http_response_code(404);
    echo "Action not found: {$actionMethod}() in {$controllerClass}";
    exit;
}

try {
    $controller->{$actionMethod}();
} catch (\Throwable $e) {
    // Log uncaught exceptions
    $logger->error('Uncaught exception in controller', [
        'exception' => $e->getMessage(),
        'file'      => $e->getFile() . ':' . $e->getLine(),
    ]);
    http_response_code(500);
    echo "An unexpected error occurred.";
}
