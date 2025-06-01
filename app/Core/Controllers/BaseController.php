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
     * Render a view from app/Views/{view}.php
     * $data is an associative array of variables to extract into the view.
     */
    protected function render(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . "/Views/{$view}.php";
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$viewFile}");
        }

        extract($data, EXTR_SKIP);
        // Make $logger available inside every view if needed:
        $logger = $this->logger;

        require $viewFile;
    }
}
