<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Bookmark\BookmarkFilter;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Uri;

/**
 * Class ShaarliControllerTest
 *
 * This class is used to test default behavior of ShaarliVisitorController abstract class.
 * It uses a dummy non abstract controller.
 */
class ShaarliVisitorControllerTest extends TestCase
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

        $this->controller = new class ($this->container) extends ShaarliVisitorController
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
                array $clearParams = [],
                string $anchor = null,
                ?string $referer = null
            ): Response {
                return parent::redirectFromReferer($request, $response, $loopTerms, $clearParams, $anchor, $referer);
            }
        };
        $this->assignedValues = [];
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

        $this->container->get('bookmarkService')
            ->method('count')
            ->willReturnCallback(function (string $visibility): int {
                return $visibility === BookmarkFilter::$PRIVATE ? 5 : 10;
            })
        ;

        $this->container->get('pluginManager')
            ->method('executeHooks')
            ->willReturnCallback(function (string $hook, array &$data, array $params): array {
                return $data[$hook] = $params;
            });
        $this->container->get('pluginManager')->method('getErrors')->willReturn(['error']);

        $this->container->get('loginManager')->method('isLoggedIn')->willReturn(true);

        $render = $this->controller->render('templateName');

        static::assertSame('templateName', $render);

        static::assertSame('templateName', $this->assignedValues['_PAGE_']);
        static::assertSame('templateName', $this->assignedValues['template']);

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
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term not matched in the referer
     */
    public function testRedirectFromRefererWithUnmatchedLoopTerm(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['nope']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its path -> redirect to default
     */
    public function testRedirectFromRefererWithMatchingLoopTermInPath(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['nope', 'controller']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its query parameters -> redirect to default
     */
    public function testRedirectFromRefererWithMatchingLoopTermInQueryParam(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['nope', 'other']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its query value
     *                              -> we do not block redirection for query parameter values.
     */
    public function testRedirectFromRefererWithMatchingLoopTermInQueryValue(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['nope', 'param']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its domain name
     *                              -> we do not block redirection for shaarli's hosts
     */
    public function testRedirectFromRefererWithLoopTermInDomain(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['shaarli']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching a query parameter AND clear this query param
     *                              -> the param should be cleared before checking if it matches the redir loop terms
     */
    public function testRedirectFromRefererWithMatchingClearedParam(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://shaarli/subfolder/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['query'], ['query']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - From another domain -> we ignore the given referrer.
     */
    public function testRedirectExternalReferer(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'shaarli',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://other.domain.tld/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['query'], ['query']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - From another domain -> we ignore the given referrer.
     */
    public function testRedirectExternalRefererExplicitDomainName(): void
    {
        $request = (new FakeRequest('GET', new Uri('', '')))->withServerParams([
            'SERVER_NAME' => 'my.shaarli.tld',
            'SERVER_PORT' => '80',
            'HTTP_REFERER' => 'http://your.shaarli.tld/controller?query=param&other=2'
        ]);

        $response = new SlimResponse();

        $result = $this->controller->redirectFromReferer($request, $response, ['query'], ['query']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/'], $result->getHeader('location'));
    }
}
