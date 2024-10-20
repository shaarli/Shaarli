<?php

declare(strict_types=1);

namespace Shaarli\DemoPlugin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Front\Controller\Admin\ShaarliAdminController;

class DemoPluginController extends ShaarliAdminController
{
    public function index(Request $request, Response $response): Response
    {
        $this->assignView(
            'content',
            '<div class="center">' .
                'This is a demo page. I have access to Shaarli container, so I\'m free to do whatever I want here.' .
            '</div>'
        );

        return $response->write($this->render('pluginscontent'));
    }
}
