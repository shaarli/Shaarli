<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkFilter;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Uri;

/**
 * Class ShaarliControllerTest
 *
 * This class is used to test default behavior of ShaarliController abstract class.
 * It uses a dummy non abstract controller.
 */
class ShaarliControllerTest extends TestCase
{
    use FrontControllerMockHelper;

    /** @var LoginController */
    protected $controller;

    /** @var mixed[] List of variable assigned to the template */
    protected $assignedValues;

    /** @var Request */
    protected $request;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new class($this->container) extends ShaarliVisitorController
        {
            public function assignView(string $key, $value): ShaarliVisitorController
            {
                return parent::assignView($key, $value);
            }

            public function render(string $template): string
            {
                return parent::render($template);
            }

            public function redirectFromReferer(
                Request $request,
                Response $response,
                array $loopTerms = [],
                array $clearParams = []
            ): Response {
                return parent::redirectFromReferer($request, $response, $loopTerms, $clearParams);
            }
        };
        $this->assignedValues = [];

        $this->request = $this->createMock(Request::class);
        $this->request->method('getUri')->willReturnCallback(function (): Uri {
            $uri = $this->createMock(Uri::class);
            $uri->method('getBasePath')->willReturn('/subfolder');

            return $uri;
        });
    }

    public function testAssignView(): void
    {
        $this->assignTemplateVars($this->assignedValues);

        $self = $this->controller->assignView('variableName', 'variableValue');

        static::assertInstanceOf(ShaarliVisitorController::class, $self);
        static::assertSame('variableValue', $this->assignedValues['variableName']);
    }

    public function testRender(): void
    {
        $this->assignTemplateVars($this->assignedValues);

        $this->container->bookmarkService
            ->method('count')
            ->willReturnCallback(function (string $visibility): int {
                return $visibility === BookmarkFilter::$PRIVATE ? 5 : 10;
            })
        ;

        $this->container->pluginManager
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array &$data, array $params): array {
                return $data[$hook] = $params;
            });
        $this->container->pluginManager->method('getErrors')->willReturn(['error']);

        $this->container->loginManager->method('isLoggedIn')->willReturn(true);

        $render = $this->controller->render('templateName');

        static::assertSame('templateName', $render);

        static::assertSame(10, $this->assignedValues['linkcount']);
        static::assertSame(5, $this->assignedValues['privateLinkcount']);
        static::assertSame(['error'], $this->assignedValues['plugin_errors']);

        static::assertSame('templateName', $this->assignedValues['plugins_includes']['render_includes']['target']);
        static::assertTrue($this->assignedValues['plugins_includes']['render_includes']['loggedin']);
        static::assertSame('templateName', $this->assignedValues['plugins_header']['render_header']['target']);
        static::assertTrue($this->assignedValues['plugins_header']['render_header']['loggedin']);
        static::assertSame('templateName', $this->assignedValues['plugins_footer']['render_footer']['target']);
        static::assertTrue($this->assignedValues['plugins_footer']['render_footer']['loggedin']);
    }

    /**
     * Test redirectFromReferer() - Default behaviour
     */
    public function testRedirectFromRefererDefault(): void
    {
        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($this->request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term not matched in the referer
     */
    public function testRedirectFromRefererWithUnmatchedLoopTerm(): void
    {
        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($this->request, $response, ['nope']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its path -> redirect to default
     */
    public function testRedirectFromRefererWithMatchingLoopTermInPath(): void
    {
        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($this->request, $response, ['nope', 'controller']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its query parameters -> redirect to default
     */
    public function testRedirectFromRefererWithMatchingLoopTermInQueryParam(): void
    {
        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($this->request, $response, ['nope', 'other']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its query value
     *                              -> we do not block redirection for query parameter values.
     */
    public function testRedirectFromRefererWithMatchingLoopTermInQueryValue(): void
    {
        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($this->request, $response, ['nope', 'param']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its domain name
     *                              -> we do not block redirection for shaarli's hosts
     */
    public function testRedirectFromRefererWithLoopTermInDomain(): void
    {
        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($this->request, $response, ['shaarli']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching a query parameter AND clear this query param
     *                              -> the param should be cleared before checking if it matches the redir loop terms
     */
    public function testRedirectFromRefererWithMatchingClearedParam(): void
    {
        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($this->request, $response, ['query'], ['query']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?other=2'], $result->getHeader('location'));
    }
}
