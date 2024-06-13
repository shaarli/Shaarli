<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\UploadedFileInterface;
use Shaarli\Netscape\NetscapeBookmarkUtils;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Shaarli\Tests\Utils\FakeRequest;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UploadedFileFactory;

class ImportControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ImportController */
    protected $controller;

    public function setUp(): void
    {
        $this->initRequestResponseFactories();
        $this->createContainer();

        $this->controller = new ImportController($this->container);
    }

    /**
     * Test displaying import page
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('import', (string) $result->getBody());

        static::assertSame('Import - Shaarli', $assignedVariables['pagetitle']);
        static::assertIsInt($assignedVariables['maxfilesize']);
        static::assertRegExp('/\d+[KM]iB/', $assignedVariables['maxfilesizeHuman']);
    }

    /**
     * Test importing a file with default and valid parameters
     */
    public function testImportDefault(): void
    {
        $uploadedFileFactory = new UploadedFileFactory();
        $streamFactory = new StreamFactory();

        $parameters = [
            'abc' => 'def',
            'other' => 'param',
        ];

        $requestFile = $uploadedFileFactory->createUploadedFile($streamFactory->createStream(), 123);

        $request = $this->requestFactory->createRequest('POST', 'http://shaarli')
            ->withParsedBody($parameters)
            ->withUploadedFiles(['filetoupload' => $requestFile]);

        $response = $this->responseFactory->createResponse();
        $this->container->set('netscapeBookmarkUtils', $this->createMock(NetscapeBookmarkUtils::class));
        $this->container->get('netscapeBookmarkUtils')
            ->expects(static::once())
            ->method('import')
            ->willReturnCallback(
                function (
                    array $post,
                    UploadedFileInterface $file
                ) use (
                    $parameters,
                    $requestFile
                ): string {
                    static::assertSame($parameters, $post);
                    static::assertSame($requestFile, $file);

                    return 'status';
                }
            )
        ;

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_SUCCESS_MESSAGES, ['status'])
        ;

        $result = $this->controller->import($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/import'], $result->getHeader('location'));
    }

    /**
     * Test posting an import request - without import file
     */
    public function testImportFileMissing(): void
    {
        $request = $this->requestFactory->createRequest('GET', 'http://shaarli');
        $response = $this->responseFactory->createResponse();

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['No import file provided.'])
        ;

        $result = $this->controller->import($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/import'], $result->getHeader('location'));
    }

    /**
     * Test posting an import request - with an empty file
     */
    public function testImportEmptyFile(): void
    {
        $uploadedFileFactory = new UploadedFileFactory();
        $streamFactory = new StreamFactory();

        $requestFile = $uploadedFileFactory->createUploadedFile(
            $streamFactory->createStream('')
        );

        $request = $this->requestFactory->createRequest('POST', 'http://shaarli')
            ->withUploadedFiles(['filetoupload' => $requestFile]);
        $response = $this->responseFactory->createResponse();

        $this->container->set('netscapeBookmarkUtils', $this->createMock(NetscapeBookmarkUtils::class));
        $this->container->get('netscapeBookmarkUtils')->expects(static::never())->method('filterAndFormat');

        $this->container->get('sessionManager')
            ->expects(static::once())
            ->method('setSessionParameter')
            ->willReturnCallback(function (string $key, array $value): SessionManager {
                static::assertSame(SessionManager::KEY_ERROR_MESSAGES, $key);
                static::assertStringStartsWith('The file you are trying to upload is probably bigger', $value[0]);

                return $this->container->get('sessionManager');
            })
        ;

        $result = $this->controller->import($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/import'], $result->getHeader('location'));
    }
}
