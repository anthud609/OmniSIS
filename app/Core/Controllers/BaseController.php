<?php
declare(strict_types=1);

namespace OmniSIS\Core\Controllers;

use OmniSIS\Core\Logger;

abstract class BaseController
{
    protected Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Render a view from app/Core/Views/{view}.php
     * $data is an associative array of variables to extract into the view.
     */
    protected function render(string $view, array $data = []): void
    {
        $this->logger->debug('BaseController::render - entering', [
            'view' => $view,
            'data_keys' => implode(',', array_keys($data)),
        ]);

        // Compute view filename
        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";
        $this->logger->debug('BaseController::render - computed viewFile', [
            'viewFile' => $viewFile,
        ]);

        if (!file_exists($viewFile)) {
            $this->logger->error('BaseController::render - view file not found', [
                'viewFile' => $viewFile,
            ]);
            throw new \RuntimeException("View not found: {$viewFile}");
        }

        $this->logger->debug('BaseController::render - extracting data', [
            'data_count' => count($data),
        ]);
        extract($data, EXTR_SKIP);

        // Make $logger available inside every view
        $logger = $this->logger;
        $this->logger->debug('BaseController::render - before requiring view', [
            'viewFile' => $viewFile,
        ]);

        require $viewFile;

        $this->logger->debug('BaseController::render - view included successfully', [
            'viewFile' => $viewFile,
        ]);
    }
}
