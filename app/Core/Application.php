<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use OmniSIS\Core\Logger;

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

    public function run(): void
    {
        //
        // ─── SMOKE TEST: EMIT ONE MESSAGE AT EACH LEVEL ────────────────────────────
        //
        $this->logger->debug('*** TEST: DEBUG level message ***');
        $this->logger->info ('*** TEST: INFO level message ***');
        $this->logger->warn ('*** TEST: WARN level message ***');
        $this->logger->error('*** TEST: ERROR level message ***');

        //
        // ─── REST OF YOUR EXISTING DISPATCH LOGIC ─────────────────────────────────
        //

        // 2) Log that we reached the front controller
        $this->logger->info('Application::run - Front controller invoked', [
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method'      => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ]);

        // 3) Pull “route” from query string (via .htaccess → index.php?route=…)
        $rawRoute = $_GET['route'] ?? '';
        $this->logger->debug('Application::run - raw $_GET[\'route\']', [
            'route' => $rawRoute,
        ]);

        // 4) Determine controller and action (hard-coded to Home::index)
        $controllerName  = 'Home';
        $actionName      = 'index';
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
