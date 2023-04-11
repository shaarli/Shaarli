<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Bookmark\BookmarkFilter;

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
    /** @var Container */
    protected $container;

    /** @param Container $container Slim container (extended for attribute completion). */
    public function __construct(Container $container)
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
        $this->container->get('pageBuilder')->assign($name, $value);

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

        $this->assignView('linkcount', $this->container->get('bookmarkService')->count(BookmarkFilter::$ALL));
        $this->assignView('privateLinkcount', $this->container->get('bookmarkService')
            ->count(BookmarkFilter::$PRIVATE));

        $this->executeDefaultHooks($template);

        $this->assignView('plugin_errors', $this->container->get('pluginManager')->getErrors());

        return $this->container->get('pageBuilder')->render($template, $this->container->get('basePath'));
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

    protected function executePageHooks(string $hook, array &$data, string $template = null): void
    {
        $this->container->get('pluginManager')->executeHooks(
            $hook,
            $data,
            $this->buildPluginParameters($template)
        );
    }

    protected function buildPluginParameters(?string $template): array
    {
        return [
            'target' => $template,
            'loggedin' => $this->container->get('loginManager')->isLoggedIn(),
            'basePath' => $this->container->get('basePath'),
            'rootPath' => preg_replace('#/index\.php$#', '', $this->container->get('basePath')),
            'bookmarkService' => $this->container->get('bookmarkService')
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
    protected function redirect(Response $response, string $path, bool $addBasePath = true): Response
    {
        $basePath = $addBasePath ? $this->container->get('basePath') : '';
        return $response
            ->withHeader('Location', $basePath . $path)
            ->withStatus(302);
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
        string $anchor = null,
        string $referer = null
    ): Response {
        $defaultPath = $this->container->get('basePath') . '/';
        $referer = $referer ?? $request->getServerParams()['HTTP_REFERER'] ?? null;

        if (null !== $referer) {
            $currentUrl = parse_url($referer);
            // If the referer is not related to Shaarli instance, redirect to default
            if (
                isset($currentUrl['host'])
                && strpos(index_url($request->getServerParams() ?? null), $currentUrl['host']) === false
            ) {
                return $this->redirect($response, $defaultPath, false);
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

        return $this->redirect($response, $path . $queryString . $anchor, false);
    }

    /**
     * Simple helper, which writes data as JSON to the body
     *
     * @param Response $response
     * @param array $data
     * @param ?int $jsonStyle
     * @return Response
     */
    protected function respondWithJson(Response $response, array $data, ?int $jsonStyle = 0): Response
    {
        $response->getBody()->write(json_encode($data, $jsonStyle));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Simple helper, which writes data to the body
     *
     * @param Response $response
     * @param string $data
     * @return Response
     */
    protected function respondWithBody(Response $response, string $data): Response
    {
        $response->getBody()->write($data);
        return $response;
    }

    /**
     * Simple helper, which uses a template
     *
     * @param Response $response
     * @param string $template
     * @return Response
     */
    protected function respondWithTemplate(Response $response, string $template): Response
    {
        $response->getBody()->write($this->render($template));
        return $response;
    }
}
