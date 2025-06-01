<?php
declare(strict_types=1);

// 1. Let PHP report all errors during development (adjust in production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. Define a couple of constants
define('BASE_PATH', dirname(__DIR__));           // project root
define('APP_PATH', BASE_PATH . '/app');
define('CORE_PATH', BASE_PATH . '/core');
define('VIEW_PATH', APP_PATH . '/Views');

// 3. Autoload everything
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Logger;
use App\Controllers\HomeController;

// 4. Instantiate the global logger (this will also create storage/logs if needed)
$logger = Logger::getInstance();

// 5. Example: log that we hit index.php
$logger->info('Front controller reached', [
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
]);

// 6. Very basic routing: ?route=controller/action
$route = $_GET['route'] ?? '';
$parts = array_filter(explode('/', $route));
$controllerName = 'Home';
$actionName     = 'index';

if (count($parts) >= 1 && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $parts[0])) {
    $controllerName = ucfirst($parts[0]);
}
if (count($parts) >= 2 && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $parts[1])) {
    $actionName = $parts[1];
}

$controllerClass = "App\\Controllers\\{$controllerName}Controller";
$actionMethod    = "{$actionName}";

if (!class_exists($controllerClass)) {
    http_response_code(404);
    echo "Controller not found: {$controllerClass}";
    exit;
}

$controller = new $controllerClass($logger);

if (!method_exists($controller, $actionMethod)) {
    http_response_code(404);
    echo "Action not found: {$actionMethod}() in controller {$controllerClass}";
    exit;
}

// 7. Call the controller action
try {
    $controller->{$actionMethod}();
} catch (\Throwable $e) {
    // Log the exception as an ERROR
    $logger->error('Uncaught exception in controller action', [
        'exception' => $e->getMessage(),
        'file'      => $e->getFile() . ':' . $e->getLine(),
    ]);
    http_response_code(500);
    echo "An unexpected error occurred.";
}
