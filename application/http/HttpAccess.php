<?php

declare(strict_types=1);

namespace Shaarli\Http;

/**
 * Class HttpAccess
 *
 * This is mostly an OOP wrapper for HTTP functions defined in `HttpUtils`.
 * It is used as dependency injection in Shaarli's container.
 *
 * @package Shaarli\Http
 */
class HttpAccess
{
    public function getHttpResponse($url, $timeout = 30, $maxBytes = 4194304, $curlWriteFunction = null)
    {
        return get_http_response($url, $timeout, $maxBytes, $curlWriteFunction);
    }

    public function getCurlDownloadCallback(
        &$charset,
        &$title,
        &$description,
        &$keywords,
        $retrieveDescription,
        $curlGetInfo = 'curl_getinfo'
    ) {
        return get_curl_download_callback(
            $charset,
            $title,
            $description,
            $keywords,
            $retrieveDescription,
            $curlGetInfo
        );
    }
}
