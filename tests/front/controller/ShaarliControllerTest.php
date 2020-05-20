<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller;

use PHPUnit\Framework\TestCase;
use Shaarli\Bookmark\BookmarkFilter;

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
}
