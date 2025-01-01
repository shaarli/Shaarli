<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\Container\ShaarliContainer;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ShaarliVisitorController
 *
 * All controllers accessible by visitors (non logged in users) should extend this abstract class.
 * Contains a few helper function for template rendering, plugins, etc.
 *
 * @package Shaarli\Front\Controller\Visitor
 */
abstract class ShaarliVisitorController
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
        // Legacy key that used to be injected by PluginManager
        $this->assignView('_PAGE_', $template);
        $this->assignView('template', $template);

        $this->assignView('linkcount', $this->container->bookmarkService->count(BookmarkFilter::$ALL));
        $this->assignView('publicLinkcount', $this->container->bookmarkService->count(BookmarkFilter::$PUBLIC));
        $this->assignView('privateLinkcount', $this->container->bookmarkService->count(BookmarkFilter::$PRIVATE));

        $this->executeDefaultHooks($template);

        $this->assignView('plugin_errors', $this->container->pluginManager->getErrors());

        return $this->container->pageBuilder->render($template, $this->container->basePath);
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
            $this->container->pluginManager->executeHooks(
                'render_' . $name,
                $pluginData,
                $parameters
            );
            $this->assignView('plugins_' . $name, $pluginData);
        }
    }

    protected function executePageHooks(string $hook, array &$data, string $template = null): void
    {
        $this->container->pluginManager->executeHooks(
            $hook,
            $data,
            $this->buildPluginParameters($template)
        );
    }

    protected function buildPluginParameters(?string $template): array
    {
        return [
            'target' => $template,
            'loggedin' => $this->container->loginManager->isLoggedIn(),
            'basePath' => $this->container->basePath,
            'rootPath' => preg_replace('#/index\.php$#', '', $this->container->basePath),
            'bookmarkService' => $this->container->bookmarkService
        ];
    }

    /**
     * Simple helper which prepend the base path to redirect path.
     *
     * @param Response $response
     * @param string $path Absolute path, e.g.: `/`, or `/admin/shaare/123` regardless of install directory
     *
     * @return Response updated
     */
    protected function redirect(Response $response, string $path): Response
    {
        return $response->withRedirect($this->container->basePath . $path);
    }

    /**
     * Generates a redirection to the previous page, based on the HTTP_REFERER.
     * It fails back to the home page.
     *
     * @param array $loopTerms   Terms to remove from path and query string to prevent direction loop.
     * @param array $clearParams List of parameter to remove from the query string of the referrer.
     */
    protected function redirectFromReferer(
        Request $request,
        Response $response,
        array $loopTerms = [],
        array $clearParams = [],
        string $anchor = null
    ): Response {
        $defaultPath = $this->container->basePath . '/';
        $referer = $this->container->environment['HTTP_REFERER'] ?? null;

        if (null !== $referer) {
            $currentUrl = parse_url($referer);
            // If the referer is not related to Shaarli instance, redirect to default
            if (
                isset($currentUrl['host'])
                && strpos(index_url($this->container->environment), $currentUrl['host']) === false
            ) {
                return $response->withRedirect($defaultPath);
            }

            parse_str($currentUrl['query'] ?? '', $params);
            $path = $currentUrl['path'] ?? $defaultPath;
        } else {
            $params = [];
            $path = $defaultPath;
        }

        // Prevent redirection loop
        if (isset($currentUrl)) {
            foreach ($clearParams as $value) {
                unset($params[$value]);
            }

            $checkQuery = implode('', array_keys($params));
            foreach ($loopTerms as $value) {
                if (strpos($path . $checkQuery, $value) !== false) {
                    $params = [];
                    $path = $defaultPath;
                    break;
                }
            }
        }

        $queryString = count($params) > 0 ? '?' . http_build_query($params) : '';
        $anchor = $anchor ? '#' . $anchor : '';

        return $response->withRedirect($path . $queryString . $anchor);
    }
}
