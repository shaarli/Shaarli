<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Slim\Http\Request;
use Slim\Http\Response;

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
            'pageabsaddr' => index_url($this->container->environment),
            'sslenabled' => is_https($this->container->environment),
        ];

        $this->executeHooks($data);

        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        $this->assignView('pagetitle', t('Tools') .' - '. $this->container->conf->get('general.title', 'Shaarli'));

        return $response->write($this->render('tools'));
    }

    /**
     * @param mixed[] $data Variables passed to the template engine
     *
     * @return mixed[] Template data after active plugins render_picwall hook execution.
     */
    protected function executeHooks(array $data): array
    {
        $this->container->pluginManager->executeHooks(
            'render_tools',
            $data
        );

        return $data;
    }
}
