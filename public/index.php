<?php
declare(strict_types=1);

// 1. Show all PHP errors (for dev; hide later in prod)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 2. Define paths
define('BASE_PATH', dirname(__DIR__));        // project-root
define('APP_PATH', BASE_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/Views');

file_put_contents('php://stderr', "[FrontController DEBUG] Starting index.php\n");
file_put_contents('php://stderr', "[FrontController DEBUG] BASE_PATH = " . BASE_PATH . "\n");
file_put_contents('php://stderr', "[FrontController DEBUG] APP_PATH = " . APP_PATH . "\n");
file_put_contents('php://stderr', "[FrontController DEBUG] VIEW_PATH = " . VIEW_PATH . "\n");

// 3. Autoload via Composer
file_put_contents('php://stderr', "[FrontController DEBUG] require vendor/autoload.php\n");
require BASE_PATH . '/vendor/autoload.php';

// 4. Instantiate the singleton logger
file_put_contents('php://stderr', "[FrontController DEBUG] Calling Logger::getInstance()\n");
use OmniSIS\Core\Logger;
use OmniSIS\Core\Controllers\HomeController;

/** @var Logger $logger */
$logger = Logger::getInstance();
$logger->debug('FrontController: Logger instantiated', [
    'loggerClass' => get_class($logger),
]);

// 5. Log that we hit the front controller
$logger->info('Front controller invoked', [
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
]);

// 6. Parse the “route” from the rewritten URL (via .htaccess → index.php?route=…)
$route = $_GET['route'] ?? '';
$logger->debug('FrontController: raw $_GET[\'route\']', ['route' => $route]);



$controllerName = 'Home';
$actionName     = 'index';



$controllerClass = "OmniSIS\\Core\\Controllers\\{$controllerName}Controller";
$actionMethod    = $actionName;


// 7. Check class exists
if (!class_exists($controllerClass)) {
    $logger->error('FrontController: controller class not found', [
        'controllerClass' => $controllerClass
    ]);
    http_response_code(404);
    echo "Controller not found: {$controllerClass}";
    exit;
}

// 8. Instantiate and call the action
$controller = new $controllerClass($logger);

if (!method_exists($controller, $actionMethod)) {
    $logger->error('FrontController: action method not found', [
        'actionMethod'    => $actionMethod,
        'controllerClass' => $controllerClass
    ]);
    http_response_code(404);
    echo "Action not found: {$actionMethod}() in {$controllerClass}";
    exit;
}

// 9. Call the controller action
try {
    $logger->debug('FrontController: about to call controller->{$actionMethod}()');
    $controller->{$actionMethod}();
    $logger->debug('FrontController: returned from controller->{$actionMethod}()');
} catch (\Throwable $e) {
    // Log uncaught exceptions
    $logger->error('FrontController: Uncaught exception in controller', [
        'exception' => $e->getMessage(),
        'file'      => $e->getFile() . ':' . $e->getLine(),
    ]);
    $logger->debug('FrontController: rendering 500 response');
    http_response_code(500);
    echo "An unexpected error occurred.";
    $logger->debug('FrontController: after echoing error message');
}
