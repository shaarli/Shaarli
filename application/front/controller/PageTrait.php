<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use Shaarli\Bookmark\BookmarkFilter;

trait PageTrait
{
    protected function assignView(string $name, $value): self
    {
        $this->container->get('pageBuilder')->assign($name, $value);

        return $this;
    }

    /**
     * Call plugin hooks for header, footer and includes, specifying which page will be rendered.
     * Then assign generated data to RainTPL.
     */
    protected function executeDefaultHooks(string $template): void
    {
        $common_hooks = [
            'includes',
            'header',
            'footer',
        ];

        $parameters = $this->buildPluginParameters($template);

        foreach ($common_hooks as $name) {
            $pluginData = [];
            $this->container->get('pluginManager')->executeHooks(
                'render_' . $name,
                $pluginData,
                $parameters
            );
            $this->assignView('plugins_' . $name, $pluginData);
        }
    }

    protected function buildPluginParameters(?string $template): array
    {
        $basePath = $this->container->get('basePath') ?? '';
        return [
            'target' => $template,
            'loggedin' => $this->container->get('loginManager')->isLoggedIn(),
            'basePath' => $this->container->get('basePath'),
            'rootPath' => preg_replace('#/index\.php$#', '', $basePath),
            'bookmarkService' => $this->container->get('bookmarkService')
        ];
    }

    protected function render(string $template): string
    {
        // Legacy key that used to be injected by PluginManager
        $this->assignView('_PAGE_', $template);
        $this->assignView('template', $template);

        $this->assignView('linkcount', $this->container->get('bookmarkService')->count(BookmarkFilter::$ALL));
        $this->assignView('privateLinkcount', $this->container->get('bookmarkService')
            ->count(BookmarkFilter::$PRIVATE));

        $this->executeDefaultHooks($template);

        $this->assignView('plugin_errors', $this->container->get('pluginManager')->getErrors());

        $basePath = $this->container->get('basePath') ?? '';
        return $this->container->get('pageBuilder')->render($template, $basePath);
    }
}
