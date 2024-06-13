<?php

declare(strict_types=1);

namespace Shaarli;

use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Helper class extending \PHPUnit\Framework\TestCase.
 * Used to make Shaarli UT run on multiple versions of PHPUnit.
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    protected ServerRequestFactory $serverRequestFactory;
    protected RequestFactory $requestFactory;
    protected ResponseFactory $responseFactory;

    protected function initRequestResponseFactories(): void
    {
        $this->requestFactory = new RequestFactory();
        $this->serverRequestFactory = new ServerRequestFactory();
        $this->responseFactory = new ResponseFactory();
    }

    /**
     * expectExceptionMessageRegExp has been removed and replaced by expectExceptionMessageMatches in PHPUnit 9.
     */
    public function expectExceptionMessageRegExp(string $regularExpression): void
    {
        if (method_exists($this, 'expectExceptionMessageMatches')) {
            $this->expectExceptionMessageMatches($regularExpression);
        } else {
            parent::expectExceptionMessageRegExp($regularExpression);
        }
    }

    /**
     * assertContains is now used for iterable, strings should use assertStringContainsString
     */
    public function assertContainsPolyfill($expected, $actual, string $message = ''): void
    {
        if (is_string($actual) && method_exists($this, 'assertStringContainsString')) {
            static::assertStringContainsString($expected, $actual, $message);
        } else {
            static::assertContains($expected, $actual, $message);
        }
    }

    /**
     * assertNotContains is now used for iterable, strings should use assertStringNotContainsString
     */
    public function assertNotContainsPolyfill($expected, $actual, string $message = ''): void
    {
        if (is_string($actual) && method_exists($this, 'assertStringNotContainsString')) {
            static::assertStringNotContainsString($expected, $actual, $message);
        } else {
            static::assertNotContains($expected, $actual, $message);
        }
    }

    /**
     * assertFileNotExists has been renamed in assertFileDoesNotExist
     */
    public static function assertFileNotExists(string $filename, string $message = ''): void
    {
        if (method_exists(TestCase::class, 'assertFileDoesNotExist')) {
            static::assertFileDoesNotExist($filename, $message);
        } else {
            parent::assertFileNotExists($filename, $message);
        }
    }

    /**
     * assertRegExp has been renamed in assertMatchesRegularExpression
     */
    public static function assertRegExp(string $pattern, string $string, string $message = ''): void
    {
        if (method_exists(TestCase::class, 'assertMatchesRegularExpression')) {
            static::assertMatchesRegularExpression($pattern, $string, $message);
        } else {
            parent::assertRegExp($pattern, $string, $message);
        }
    }

    public function isInTestsContext(): bool
    {
        return true;
    }
}
