<?php
declare(strict_types=1);

namespace OmniSIS\Core\Controllers;

class HomeController extends BaseController
{
    public function index(): void
    {
        // Example: log something
        $this->logger->info('HomeController@index called');

        // Pass data to the view
        $data = [
            'title'   => 'My MVC Home',
            'message' => 'You are seeing the home page.'
        ];

        $this->render('home', $data);
    }
}
