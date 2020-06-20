<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Exception;
use Slim\Http\Request;
use Slim\Http\Response;

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
        $pluginMeta = $this->container->pluginManager->getPluginsMeta();

        // Split plugins into 2 arrays: ordered enabled plugins and disabled.
        $enabledPlugins = array_filter($pluginMeta, function ($v) {
            return ($v['order'] ?? false) !== false;
        });
        $enabledPlugins = load_plugin_parameter_values($enabledPlugins, $this->container->conf->get('plugins', []));
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
            t('Plugin Administration') .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );

        return $response->write($this->render('pluginsadmin'));
    }

    /**
     * POST /admin/plugins - Update Shaarli's configuration
     */
    public function save(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        try {
            $parameters = $request->getParams() ?? [];

            $this->executeHooks($parameters);

            if (isset($parameters['parameters_form'])) {
                unset($parameters['parameters_form']);
                foreach ($parameters as $param => $value) {
                    $this->container->conf->set('plugins.'. $param, escape($value));
                }
            } else {
                $this->container->conf->set('general.enabled_plugins', save_plugin_config($parameters));
            }

            $this->container->conf->write($this->container->loginManager->isLoggedIn());
            $this->container->history->updateSettings();

            $this->saveSuccessMessage(t('Setting successfully saved.'));
        } catch (Exception $e) {
            $this->saveErrorMessage(
                t('ERROR while saving plugin configuration: ') . PHP_EOL . $e->getMessage()
            );
        }

        return $this->redirect($response, '/admin/plugins');
    }

    /**
     * @param mixed[] $data Variables passed to the template engine
     *
     * @return mixed[] Template data after active plugins render_picwall hook execution.
     */
    protected function executeHooks(array $data): array
    {
        $this->container->pluginManager->executeHooks(
            'save_plugin_parameters',
            $data
        );

        return $data;
    }
}
