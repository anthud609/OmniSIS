<?php
declare(strict_types=1);

namespace OmniSIS\Core\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->logger->debug('HomeController::index - entry');

        // Example: log something at INFO
        $this->logger->info('HomeController@index called');

        // Pass data to the view
        $data = [
            'title'   => 'My MVC Home',
            'message' => 'You are seeing the home page.'
        ];
        $this->logger->debug('HomeController::index - prepared $data', [
            'title'   => $data['title'],
            'message_length' => strlen($data['message']),
        ]);

        $this->logger->debug('HomeController::index - calling render()', [
            'viewName' => 'home',
        ]);
        $this->render('home', $data);
        $this->logger->debug('HomeController::index - after render()');
    }
}
