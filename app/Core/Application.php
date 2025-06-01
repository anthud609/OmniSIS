<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use OmniSIS\Core\Logger;

/**
 * Application bootstraps and dispatches incoming HTTP requests.
 * 
 * Responsibilities:
 *  1. Instantiate the Logger singleton.
 *  2. Log that the front controller was hit.
 *  3. Parse “route” from $_GET['route'].
 *  4. Map route (hard-coded for now) to a controller class and action.
 *  5. Validate that the controller class and action exist.
 *  6. Instantiate the controller, inject Logger, and invoke the action.
 *  7. Catch any uncaught exceptions and render a 500 response.
 */
final class Application
{
    private Logger $logger;

    public function __construct()
    {
        // 1) Instantiate the shared Logger
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
        // 2) Log that we reached the front controller
        $this->logger->info('Application::run - Front controller invoked', [
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ]);

        // 3) Pull “route” from the rewritten URL (via .htaccess → index.php?route=…)
        $rawRoute = $_GET['route'] ?? '';
        $this->logger->debug('Application::run - raw $_GET[\'route\']', [
            'route' => $rawRoute,
        ]);

        // 4) Determine controller and action (hard-coded to Home::index)
        $controllerName = 'Home';
        $actionName     = 'index';
        $controllerClass = "OmniSIS\\Core\\Controllers\\{$controllerName}Controller";
        $actionMethod    = $actionName;

        // 5) Check that the controller class exists
        if (! class_exists($controllerClass)) {
            $this->logger->error('Application::run - Controller class not found', [
                'controllerClass' => $controllerClass,
            ]);
            http_response_code(404);
            echo "Controller not found: {$controllerClass}";
            return;
        }

        // 6) Instantiate the controller, injecting Logger
        /** @var \OmniSIS\Core\Controllers\BaseController $controller */
        $controller = new $controllerClass($this->logger);

        // 7) Check that the action method exists
        if (! method_exists($controller, $actionMethod)) {
            $this->logger->error('Application::run - Action method not found', [
                'actionMethod'    => $actionMethod,
                'controllerClass' => $controllerClass,
            ]);
            http_response_code(404);
            echo "Action not found: {$actionMethod}() in {$controllerClass}";
            return;
        }

        // 8) Call the action inside a try/catch for any uncaught exceptions
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
