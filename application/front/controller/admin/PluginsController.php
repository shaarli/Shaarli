<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Render\TemplatePage;

/**
 * Class PluginsController
 *
 * Slim controller used to handle Shaarli plugins configuration page (display + save new config).
 */
class PluginsController extends ShaarliAdminController
{
    /**
     * GET /admin/plugins - Displays the configuration page
     */
    public function index(Request $request, Response $response): Response
    {
        $pluginMeta = $this->container->get('pluginManager')->getPluginsMeta();

        // Split plugins into 2 arrays: ordered enabled plugins and disabled.
        $enabledPlugins = array_filter($pluginMeta, function ($v) {
            return ($v['order'] ?? false) !== false;
        });
        $enabledPlugins = load_plugin_parameter_values($enabledPlugins, $this->container->get('conf')
            ->get('plugins', []));
        uasort(
            $enabledPlugins,
            function ($a, $b) {
                return $a['order'] - $b['order'];
            }
        );
        $disabledPlugins = array_filter($pluginMeta, function ($v) {
            return ($v['order'] ?? false) === false;
        });

        $this->assignView('enabledPlugins', $enabledPlugins);
        $this->assignView('disabledPlugins', $disabledPlugins);
        $this->assignView(
            'pagetitle',
            t('Plugin Administration') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
        );

        return $this->respondWithTemplate($response, TemplatePage::PLUGINS_ADMIN);
    }

    /**
     * POST /admin/plugins - Update Shaarli's configuration
     */
    public function save(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        try {
            $parameters = $request->getParsedBody() ?? [];

            $this->executePageHooks('save_plugin_parameters', $parameters);

            if (isset($parameters['parameters_form'])) {
                unset($parameters['parameters_form']);
                unset($parameters['token']);
                foreach ($parameters as $param => $value) {
                    $this->container->get('conf')->set('plugins.' . $param, escape($value));
                }
            } else {
                $this->container->get('conf')->set('general.enabled_plugins', save_plugin_config($parameters));
            }

            $this->container->get('conf')->write($this->container->get('loginManager')->isLoggedIn());
            $this->container->get('history')->updateSettings();

            $this->saveSuccessMessage(t('Setting successfully saved.'));
        } catch (Exception $e) {
            $this->saveErrorMessage(
                t('Error while saving plugin configuration: ') . PHP_EOL . $e->getMessage()
            );
        }

        return $this->redirect($response, '/admin/plugins');
    }
}
