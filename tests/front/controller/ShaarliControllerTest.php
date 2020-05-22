<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkFilter;
use Slim\Http\Response;

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

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new class($this->container) extends ShaarliController
        {
            public function assignView(string $key, $value): ShaarliController
            {
                return parent::assignView($key, $value);
            }

            public function render(string $template): string
            {
                return parent::render($template);
            }

            public function redirectFromReferer(
                Response $response,
                array $loopTerms = [],
                array $clearParams = []
            ): Response {
                return parent::redirectFromReferer($response, $loopTerms, $clearParams);
            }
        };
        $this->assignedValues = [];
    }

    public function testAssignView(): void
    {
        $this->createValidContainerMockSet();

        $this->assignTemplateVars($this->assignedValues);

        $self = $this->controller->assignView('variableName', 'variableValue');

        static::assertInstanceOf(ShaarliController::class, $self);
        static::assertSame('variableValue', $this->assignedValues['variableName']);
    }

    public function testRender(): void
    {
        $this->createValidContainerMockSet();

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
        $this->createValidContainerMockSet();

        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term not matched in the referer
     */
    public function testRedirectFromRefererWithUnmatchedLoopTerm(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($response, ['nope']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its path -> redirect to default
     */
    public function testRedirectFromRefererWithMatchingLoopTermInPath(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($response, ['nope', 'controller']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its query parameters -> redirect to default
     */
    public function testRedirectFromRefererWithMatchingLoopTermInQueryParam(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($response, ['nope', 'other']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['./'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its query value
     *                              -> we do not block redirection for query parameter values.
     */
    public function testRedirectFromRefererWithMatchingLoopTermInQueryValue(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($response, ['nope', 'param']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching the referer in its domain name
     *                              -> we do not block redirection for shaarli's hosts
     */
    public function testRedirectFromRefererWithLoopTermInDomain(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($response, ['shaarli']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?query=param&other=2'], $result->getHeader('location'));
    }

    /**
     * Test redirectFromReferer() - With a loop term matching a query parameter AND clear this query param
     *                              -> the param should be cleared before checking if it matches the redir loop terms
     */
    public function testRedirectFromRefererWithMatchingClearedParam(): void
    {
        $this->createValidContainerMockSet();

        $this->container->environment['HTTP_REFERER'] = 'http://shaarli.tld/subfolder/controller?query=param&other=2';

        $response = new Response();

        $result = $this->controller->redirectFromReferer($response, ['query'], ['query']);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/controller?other=2'], $result->getHeader('location'));
    }
}
