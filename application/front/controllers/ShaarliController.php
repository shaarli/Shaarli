<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Container\ShaarliContainer;

abstract class ShaarliController
{
    /** @var ShaarliContainer */
    protected $container;

    /** @param ShaarliContainer $container Slim container (extended for attribute completion). */
    public function __construct(ShaarliContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Assign variables to RainTPL template through the PageBuilder.
     *
     * @param mixed $value Value to assign to the template
     */
    protected function assignView(string $name, $value): self
    {
        $this->container->pageBuilder->assign($name, $value);

        return $this;
    }

    /**
     * Assign variables to RainTPL template through the PageBuilder.
     *
     * @param mixed $data Values to assign to the template and their keys
     */
    protected function assignAllView(array $data): self
    {
        foreach ($data as $key => $value) {
            $this->assignView($key, $value);
        }

        return $this;
    }

    protected function render(string $template): string
    {
        $this->assignView('linkcount', $this->container->bookmarkService->count(BookmarkFilter::$ALL));
        $this->assignView('privateLinkcount', $this->container->bookmarkService->count(BookmarkFilter::$PRIVATE));
        $this->assignView('plugin_errors', $this->container->pluginManager->getErrors());

        $this->executeDefaultHooks($template);

        return $this->container->pageBuilder->render($template);
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

        foreach ($common_hooks as $name) {
            $plugin_data = [];
            $this->container->pluginManager->executeHooks(
                'render_' . $name,
                $plugin_data,
                [
                    'target' => $template,
                    'loggedin' => $this->container->loginManager->isLoggedIn()
                ]
            );
            $this->assignView('plugins_' . $name, $plugin_data);
        }
    }
}
