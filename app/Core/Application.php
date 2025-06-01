<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use Dotenv\Dotenv;
use OmniSIS\Core\Logger;

/**
 * Application bootstraps and dispatches incoming HTTP requests.
 *
 * Responsibilities:
 *   1. Load environment variables via phpdotenv.
 *   2. Instantiate the Logger singleton (which reads APP_DEBUG from env).
 *   3. Log that the front controller was hit.
 *   4. Parse “route” from $_GET['route'].
 *   5. Map route (hard-coded for now) to a controller class and action.
 *   6. Validate that the controller class and action exist.
 *   7. Instantiate the controller, inject Logger, and invoke the action.
 *   8. Catch any uncaught exceptions and render a 500 response.
 */
final class Application
{
    private Logger $logger;

    public function __construct()
    {
        // ─── 1) LOAD .env USING phpdotenv ─────────────────────────────────────────
        // Dotenv will look for a ".env" file in the project root (one level above "app/")
      // before:
// $dotenv = Dotenv::createImmutable(dirname(__DIR__, 1));
// after:
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->safeLoad();

        // safeLoad(): if .env is missing, it will not throw. If you prefer an exception,
        // use ->load() instead.

        // ─── 2) INSTANTIATE LOGGER (NOW APP_DEBUG IS AVAILABLE) ────────────────────
        $this->logger = Logger::getInstance();
        $this->logger->debug('Application::__construct - Logger instantiated', [
            'loggerClass' => get_class($this->logger),
        ]);
    }

    /**
     * Bootstraps and executes the controller/action.
     */
    public function run(): void
    {
        // ─── SMOKE TEST (OPTIONAL): EMIT ONE MESSAGE AT EACH LEVEL ─────────────────
        $this->logger->debug('*** TEST: DEBUG level message ***');
        $this->logger->info ('*** TEST: INFO level message ***');
        $this->logger->warn ('*** TEST: WARN level message ***');
        $this->logger->error('*** TEST: ERROR level message ***');

        // ─── 3) Log that we reached the front controller ──────────────────────────
        $this->logger->info('Application::run - Front controller invoked', [
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ]);

        // ─── 4) Pull “route” from the rewritten URL (via .htaccess → index.php?route=…) ──
        $rawRoute = $_GET['route'] ?? '';
        $this->logger->debug('Application::run - raw $_GET[\'route\']', [
            'route' => $rawRoute,
        ]);

        // ─── 5) Determine controller and action (hard-coded to Home::index) ────────
        $controllerName  = 'Home';
        $actionName      = 'index';
        $controllerClass = "OmniSIS\\Core\\Controllers\\{$controllerName}Controller";
        $actionMethod    = $actionName;

        // ─── 6) Check that the controller class exists ─────────────────────────────
        if (! class_exists($controllerClass)) {
            $this->logger->error('Application::run - Controller class not found', [
                'controllerClass' => $controllerClass,
            ]);
            http_response_code(404);
            echo "Controller not found: {$controllerClass}";
            return;
        }

        // ─── 7) Instantiate the controller, injecting Logger ───────────────────────
        /** @var \OmniSIS\Core\Controllers\BaseController $controller */
        $controller = new $controllerClass($this->logger);

        // ─── 8) Check that the action method exists ────────────────────────────────
        if (! method_exists($controller, $actionMethod)) {
            $this->logger->error('Application::run - Action method not found', [
                'actionMethod'    => $actionMethod,
                'controllerClass' => $controllerClass,
            ]);
            http_response_code(404);
            echo "Action not found: {$actionMethod}() in {$controllerClass}";
            return;
        }

        // ─── 9) Call the action inside a try/catch for any uncaught exceptions ───
        try {
            $this->logger->debug("Application::run - Calling {$controllerClass}::{$actionMethod}()");
            $controller->{$actionMethod}();
            $this->logger->debug("Application::run - Returned from {$controllerClass}::{$actionMethod}()");
        } catch (\Throwable $e) {
            $this->logger->error('Application::run - Uncaught exception in controller', [
                'exception' => $e->getMessage(),
                'fileLine'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            $this->logger->debug('Application::run - Rendering 500 response');
            http_response_code(500);
            echo "An unexpected error occurred.";
            $this->logger->debug('Application::run - After echoing error message');
        }
    }
}
