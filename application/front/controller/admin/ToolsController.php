<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Render\TemplatePage;

/**
 * Class ToolsController
 *
 * Slim controller used to display the tools page.
 */
class ToolsController extends ShaarliAdminController
{
    public function index(Request $request, Response $response): Response
    {
        $data = [
            'pageabsaddr' => index_url($request->getServerParams()),
            'sslenabled' => is_https($request->getServerParams()),
        ];

        $this->executePageHooks('render_tools', $data, TemplatePage::TOOLS);

        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        $this->assignView(
            'pagetitle',
            t('Tools') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
        );

        return $this->respondWithTemplate($response, TemplatePage::TOOLS);
    }
}
