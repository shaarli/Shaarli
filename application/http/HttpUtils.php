<?php

use Shaarli\Http\Url;

/**
 * GET an HTTP URL to retrieve its content
 * Uses the cURL library or a fallback method
 *
 * @param string          $url                URL to get (http://...)
 * @param int             $timeout            network timeout (in seconds)
 * @param int             $maxBytes           maximum downloaded bytes (default: 4 MiB)
 * @param callable|string $curlHeaderFunction Optional callback called during the download of headers
 *                                            (CURLOPT_HEADERFUNCTION)
 * @param callable|string $curlWriteFunction  Optional callback called during the download (cURL CURLOPT_WRITEFUNCTION).
 *                                            Can be used to add download conditions on the
 *                                            headers (response code, content type, etc.).
 *
 * @return array HTTP response headers, downloaded content
 *
 * Output format:
 *  [0] = associative array containing HTTP response headers
 *  [1] = URL content (downloaded data)
 *
 * Example:
 *  list($headers, $data) = get_http_response('http://sebauvage.net/');
 *  if (strpos($headers[0], '200 OK') !== false) {
 *      echo 'Data type: '.htmlspecialchars($headers['Content-Type']);
 *  } else {
 *      echo 'There was an error: '.htmlspecialchars($headers[0]);
 *  }
 *
 * @see https://secure.php.net/manual/en/ref.curl.php
 * @see https://secure.php.net/manual/en/functions.anonymous.php
 * @see https://secure.php.net/manual/en/function.preg-split.php
 * @see https://secure.php.net/manual/en/function.explode.php
 * @see http://stackoverflow.com/q/17641073
 * @see http://stackoverflow.com/q/9183178
 * @see http://stackoverflow.com/q/1462720
 */
function get_http_response(
    $url,
    $timeout = 30,
    $maxBytes = 4194304,
    $curlHeaderFunction = null,
    $curlWriteFunction = null
) {
    $urlObj = new Url($url);
    $cleanUrl = $urlObj->idnToAscii();

    if (!filter_var($cleanUrl, FILTER_VALIDATE_URL) || !$urlObj->isHttp()) {
        return [[0 => 'Invalid HTTP UrlUtils'], false];
    }

    $userAgent =
        'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:115.0)'
        . ' Gecko/20100101 Firefox/115.0';
    $acceptLanguage =
        substr(get_locale(LC_COLLATE), 0, 2) . ',en-US;q=0.7,en;q=0.3';
    $maxRedirs = 3;

    if (!function_exists('curl_init')) {
        return get_http_response_fallback(
            $cleanUrl,
            $timeout,
            $maxBytes,
            $userAgent,
            $acceptLanguage,
            $maxRedirs
        );
    }

    $ch = curl_init($cleanUrl);
    if ($ch === false) {
        return [[0 => 'curl_init() error'], false];
    }

    // General cURL settings
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // Default header download if the $curlHeaderFunction is not defined
    curl_setopt($ch, CURLOPT_HEADER, !is_callable($curlHeaderFunction));
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        ['Accept-Language: ' . $acceptLanguage]
    );
    curl_setopt($ch, CURLOPT_MAXREDIRS, $maxRedirs);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

    // Max download size management
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024 * 16);
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    if (is_callable($curlHeaderFunction)) {
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, $curlHeaderFunction);
    }
    if (is_callable($curlWriteFunction)) {
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $curlWriteFunction);
    }
    curl_setopt(
        $ch,
        CURLOPT_PROGRESSFUNCTION,
        function ($arg0, $arg1, $arg2, $arg3, $arg4) use ($maxBytes) {
            $downloaded = $arg2;

            // Non-zero return stops downloading
            return ($downloaded > $maxBytes) ? 1 : 0;
        }
    );

    $response = curl_exec($ch);
    $errorNo = curl_errno($ch);
    $errorStr = curl_error($ch);
    $headSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false) {
        if ($errorNo == CURLE_COULDNT_RESOLVE_HOST) {
            /*
             * Workaround to match fallback method behaviour
             * Removing this would require updating
             * GetHttpUrlTest::testGetInvalidRemoteUrl()
             */
            return [false, false];
        }
        return [[0 => 'curl_exec() error: ' . $errorStr], false];
    }

    // Formatting output like the fallback method
    $rawHeaders = substr($response, 0, $headSize);

    // Keep only headers from latest redirection
    $rawHeadersArrayRedirs = explode("\r\n\r\n", trim($rawHeaders));
    $rawHeadersLastRedir = end($rawHeadersArrayRedirs);

    $content = substr($response, $headSize);
    $headers = [];
    foreach (preg_split('~[\r\n]+~', $rawHeadersLastRedir) as $line) {
        if (empty($line) || ctype_space($line)) {
            continue;
        }
        $splitLine = explode(': ', $line, 2);
        if (count($splitLine) > 1) {
            $key = $splitLine[0];
            $value = $splitLine[1];
            if (array_key_exists($key, $headers)) {
                if (!is_array($headers[$key])) {
                    $headers[$key] = [0 => $headers[$key]];
                }
                $headers[$key][] = $value;
            } else {
                $headers[$key] = $value;
            }
        } else {
            $headers[] = $splitLine[0];
        }
    }

    return [$headers, $content];
}

/**
 * GET an HTTP URL to retrieve its content (fallback method)
 *
 * @param string $cleanUrl       URL to get (http://... valid and in ASCII form)
 * @param int    $timeout        network timeout (in seconds)
 * @param int    $maxBytes       maximum downloaded bytes
 * @param string $userAgent      "User-Agent" header
 * @param string $acceptLanguage "Accept-Language" header
 * @param int    $maxRedr        maximum amount of redirections followed
 *
 * @return array HTTP response headers, downloaded content
 *
 * Output format:
 *  [0] = associative array containing HTTP response headers
 *  [1] = URL content (downloaded data)
 *
 * @see http://php.net/manual/en/function.file-get-contents.php
 * @see http://php.net/manual/en/function.stream-context-create.php
 * @see http://php.net/manual/en/function.get-headers.php
 */
function get_http_response_fallback(
    $cleanUrl,
    $timeout,
    $maxBytes,
    $userAgent,
    $acceptLanguage,
    $maxRedr
) {
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'user_agent' => $userAgent,
            'header' => "Accept: */*\r\n"
                . 'Accept-Language: ' . $acceptLanguage
        ]
    ];

    stream_context_set_default($options);
    list($headers, $finalUrl) = get_redirected_headers($cleanUrl, $maxRedr);
    if (! $headers || strpos($headers[0], '200 OK') === false) {
        $options['http']['request_fulluri'] = true;
        stream_context_set_default($options);
        list($headers, $finalUrl) = get_redirected_headers($cleanUrl, $maxRedr);
    }

    if (! $headers) {
        return [$headers, false];
    }

    try {
        // TODO: catch Exception in calling code (thumbnailer)
        $context = stream_context_create($options);
        $content = file_get_contents($finalUrl, false, $context, -1, $maxBytes);
    } catch (Exception $exc) {
        return [[0 => 'HTTP Error'], $exc->getMessage()];
    }

    return [$headers, $content];
}

/**
 * Retrieve HTTP headers, following n redirections (temporary and permanent ones).
 *
 * @param string $url              initial URL to reach.
 * @param int    $redirectionLimit max redirection follow.
 *
 * @return array HTTP headers, or false if it failed.
 */
function get_redirected_headers($url, $redirectionLimit = 3)
{
    $headers = get_headers($url, 1);
    if (!empty($headers['location']) && empty($headers['Location'])) {
        $headers['Location'] = $headers['location'];
    }

    // Headers found, redirection found, and limit not reached.
    if (
        $redirectionLimit-- > 0
        && !empty($headers)
        && (strpos($headers[0], '301') !== false || strpos($headers[0], '302') !== false)
        && !empty($headers['Location'])
    ) {
        $redirection = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
        if ($redirection != $url) {
            $redirection = getAbsoluteUrl($url, $redirection);
            return get_redirected_headers($redirection, $redirectionLimit);
        }
    }

    return [$headers, $url];
}

/**
 * Get an absolute URL from a complete one, and another absolute/relative URL.
 *
 * @param string $originalUrl The original complete URL.
 * @param string $newUrl      The new one, absolute or relative.
 *
 * @return string Final URL:
 *   - $newUrl if it was already an absolute URL.
 *   - if it was relative, absolute URL from $originalUrl path.
 */
function getAbsoluteUrl($originalUrl, $newUrl)
{
    $newScheme = parse_url($newUrl, PHP_URL_SCHEME);
    // Already an absolute URL.
    if (!empty($newScheme)) {
        return $newUrl;
    }

    $parts = parse_url($originalUrl);
    $final = $parts['scheme'] . '://' . $parts['host'];
    $final .= (!empty($parts['port'])) ? $parts['port'] : '';
    $final .= '/';
    if ($newUrl[0] != '/') {
        $final .= substr(ltrim($parts['path'], '/'), 0, strrpos($parts['path'], '/'));
    }
    $final .= ltrim($newUrl, '/');
    return $final;
}

/**
 * Returns the server's base URL: scheme://domain.tld[:port]
 *
 * @param array $server the $_SERVER array
 *
 * @return string the server's base URL
 *
 * @see http://www.ietf.org/rfc/rfc7239.txt
 * @see http://www.ietf.org/rfc/rfc6648.txt
 * @see http://stackoverflow.com/a/3561399
 * @see http://stackoverflow.com/q/452375
 */
function server_url($server)
{
    $scheme = 'http';
    $port = '';

    // Shaarli is served behind a proxy
    if (isset($server['HTTP_X_FORWARDED_PROTO'])) {
        // Keep forwarded scheme
        if (strpos($server['HTTP_X_FORWARDED_PROTO'], ',') !== false) {
            $schemes = explode(',', $server['HTTP_X_FORWARDED_PROTO']);
            $scheme = trim($schemes[0]);
        } else {
            $scheme = $server['HTTP_X_FORWARDED_PROTO'];
        }

        if (isset($server['HTTP_X_FORWARDED_PORT'])) {
            // Keep forwarded port
            if (strpos($server['HTTP_X_FORWARDED_PORT'], ',') !== false) {
                $ports = explode(',', $server['HTTP_X_FORWARDED_PORT']);
                $port = trim($ports[0]);
            } else {
                $port = $server['HTTP_X_FORWARDED_PORT'];
            }

            // This is a workaround for proxies that don't forward the scheme properly.
            // Connecting over port 443 has to be in HTTPS.
            // See https://github.com/shaarli/Shaarli/issues/1022
            if ($port == '443') {
                $scheme = 'https';
            }

            if (
                ($scheme == 'http' && $port != '80')
                || ($scheme == 'https' && $port != '443')
            ) {
                $port = ':' . $port;
            } else {
                $port = '';
            }
        }

        if (isset($server['HTTP_X_FORWARDED_HOST'])) {
            // Keep forwarded host
            if (strpos($server['HTTP_X_FORWARDED_HOST'], ',') !== false) {
                $hosts = explode(',', $server['HTTP_X_FORWARDED_HOST']);
                $host = trim($hosts[0]);
            } else {
                $host = $server['HTTP_X_FORWARDED_HOST'];
            }
        } else {
            $host = $server['SERVER_NAME'];
        }

        return $scheme . '://' . $host . $port;
    }

    // SSL detection
    if (
        (! empty($server['HTTPS']) && strtolower($server['HTTPS']) == 'on')
        || (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] == '443')
    ) {
        $scheme = 'https';
    }

    // Do not append standard port values
    if (
        ($scheme == 'http' && $server['SERVER_PORT'] != '80')
        || ($scheme == 'https' && $server['SERVER_PORT'] != '443')
    ) {
        $port = ':' . $server['SERVER_PORT'];
    }

    return $scheme . '://' . $server['SERVER_NAME'] . $port;
}

/**
 * Returns the absolute URL of the current script, without the query
 *
 * If the resource is "index.php", then it is removed (for better-looking URLs)
 *
 * @param array $server the $_SERVER array
 *
 * @return string the absolute URL of the current script, without the query
 */
function index_url($server)
{
    if (defined('SHAARLI_ROOT_URL') && null !== SHAARLI_ROOT_URL) {
        return rtrim(SHAARLI_ROOT_URL, '/') . '/';
    }

    $scriptname = !empty($server['SCRIPT_NAME']) ? $server['SCRIPT_NAME'] : '/';
    if (endsWith($scriptname, 'index.php')) {
        $scriptname = substr($scriptname, 0, -9);
    }
    return server_url($server) . $scriptname;
}

/**
 * Returns the absolute URL of the current script, with current route and query
 *
 * If the resource is "index.php", then it is removed (for better-looking URLs)
 *
 * @param array $server the $_SERVER array
 *
 * @return string the absolute URL of the current script, with the query
 */
function page_url($server)
{
    $scriptname = $server['SCRIPT_NAME'] ?? '';
    if (endsWith($scriptname, 'index.php')) {
        $scriptname = substr($scriptname, 0, -9);
    }

    $route = preg_replace('@^' . $scriptname . '@', '', $server['REQUEST_URI'] ?? '');
    if (! empty($server['QUERY_STRING'])) {
        return index_url($server) . $route . '?' . $server['QUERY_STRING'];
    }

    return index_url($server) . $route;
}

/**
 * Retrieve the initial IP forwarded by the reverse proxy.
 *
 * Inspired from: https://github.com/zendframework/zend-http/blob/master/src/PhpEnvironment/RemoteAddress.php
 *
 * @param array $server     $_SERVER array which contains HTTP headers.
 * @param array $trustedIps List of trusted IP from the configuration.
 *
 * @return string|bool The forwarded IP, or false if none could be extracted.
 */
function getIpAddressFromProxy($server, $trustedIps)
{
    $forwardedIpHeader = 'HTTP_X_FORWARDED_FOR';
    if (empty($server[$forwardedIpHeader])) {
        return false;
    }

    $ips = preg_split('/\s*,\s*/', $server[$forwardedIpHeader]);
    $ips = array_diff($ips, $trustedIps);
    if (empty($ips)) {
        return false;
    }

    return array_pop($ips);
}


/**
 * Return an identifier based on the advertised client IP address(es)
 *
 * This aims at preventing session hijacking from users behind the same proxy
 * by relying on HTTP headers.
 *
 * See:
 * - https://secure.php.net/manual/en/reserved.variables.server.php
 * - https://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php
 * - https://stackoverflow.com/questions/12233406/preventing-session-hijacking
 * - https://stackoverflow.com/questions/21354859/trusting-x-forwarded-for-to-identify-a-visitor
 *
 * @param array $server The $_SERVER array
 *
 * @return string An identifier based on client IP address information
 */
function client_ip_id($server)
{
    $ip = $server['REMOTE_ADDR'];

    if (isset($server['HTTP_X_FORWARDED_FOR'])) {
        $ip = $ip . '_' . $server['HTTP_X_FORWARDED_FOR'];
    }
    if (isset($server['HTTP_CLIENT_IP'])) {
        $ip = $ip . '_' . $server['HTTP_CLIENT_IP'];
    }
    return $ip;
}


/**
 * Returns true if Shaarli's currently browsed in HTTPS.
 * Supports reverse proxies (if the headers are correctly set).
 *
 * @param array $server $_SERVER.
 *
 * @return bool true if HTTPS, false otherwise.
 */
function is_https($server)
{

    if (isset($server['HTTP_X_FORWARDED_PORT'])) {
        // Keep forwarded port
        if (strpos($server['HTTP_X_FORWARDED_PORT'], ',') !== false) {
            $ports = explode(',', $server['HTTP_X_FORWARDED_PORT']);
            $port = trim($ports[0]);
        } else {
            $port = $server['HTTP_X_FORWARDED_PORT'];
        }

        if ($port == '443') {
            return true;
        }
    }

    return ! empty($server['HTTPS']);
}

/**
 * Get cURL callback function for CURLOPT_WRITEFUNCTION
 *
 * @param string $charset     to extract from the downloaded page (reference)
 * @param string $curlGetInfo Optionally overrides curl_getinfo function
 *
 * @return Closure
 */
function get_curl_header_callback(
    &$charset,
    $curlGetInfo = 'curl_getinfo'
) {
    $isRedirected = false;

    return function ($ch, $data) use ($curlGetInfo, &$charset, &$isRedirected) {
        $responseCode = $curlGetInfo($ch, CURLINFO_RESPONSE_CODE);
        $chunkLength = strlen($data);
        if (!empty($responseCode) && in_array($responseCode, [301, 302])) {
            $isRedirected = true;
            return $chunkLength;
        }
        if (!empty($responseCode) && $responseCode !== 200) {
            return false;
        }
        // After a redirection, the content type will keep the previous request value
        // until it finds the next content-type header.
        if (! $isRedirected || strpos(strtolower($data), 'content-type') !== false) {
            $contentType = $curlGetInfo($ch, CURLINFO_CONTENT_TYPE);
        }
        if (!empty($contentType) && strpos($contentType, 'text/html') === false) {
            return false;
        }
        if (!empty($contentType) && empty($charset)) {
            $charset = header_extract_charset($contentType);
        }

        return $chunkLength;
    };
}

/**
 * Get cURL callback function for CURLOPT_WRITEFUNCTION
 *
 * @param string $charset     to extract from the downloaded page (reference)
 * @param string $title       to extract from the downloaded page (reference)
 * @param string $description to extract from the downloaded page (reference)
 * @param string $keywords    to extract from the downloaded page (reference)
 * @param bool   $retrieveDescription Automatically tries to retrieve description and keywords from HTML content
 * @param string $curlGetInfo Optionally overrides curl_getinfo function
 *
 * @return Closure
 */
function get_curl_download_callback(
    &$charset,
    &$title,
    &$description,
    &$keywords,
    $retrieveDescription,
    $tagsSeparator
) {
    $currentChunk = 0;
    $foundChunk = null;

    /**
     * cURL callback function for CURLOPT_WRITEFUNCTION (called during the download).
     *
     * While downloading the remote page, we check that the HTTP code is 200 and content type is 'html/text'
     * Then we extract the title and the charset and stop the download when it's done.
     *
     * @param resource $ch   cURL resource
     * @param string   $data chunk of data being downloaded
     *
     * @return int|bool length of $data or false if we need to stop the download
     */
    return function (
        $ch,
        $data
    ) use (
        $retrieveDescription,
        $tagsSeparator,
        &$charset,
        &$title,
        &$description,
        &$keywords,
        &$currentChunk,
        &$foundChunk
    ) {
        $chunkLength = strlen($data);
        $currentChunk++;

        if (empty($charset)) {
            $charset = html_extract_charset($data);
        }
        if (empty($title)) {
            $title = html_extract_title($data);
            $foundChunk = ! empty($title) ? $currentChunk : $foundChunk;
        }
        if (empty($title)) {
            $title = html_extract_tag('title', $data);
            $foundChunk = ! empty($title) ? $currentChunk : $foundChunk;
        }
        if ($retrieveDescription && empty($description)) {
            $description = html_extract_tag('description', $data);
            $foundChunk = ! empty($description) ? $currentChunk : $foundChunk;
        }
        if ($retrieveDescription && empty($keywords)) {
            $keywords = html_extract_tag('keywords', $data);
            if (! empty($keywords)) {
                $foundChunk = $currentChunk;
                // Keywords use the format tag1, tag2 multiple words, tag
                // So we split the result with `,`, then if a tag contains the separator we replace it by `-`.
                $keywords = tags_array2str(array_map(function (string $keyword) use ($tagsSeparator): string {
                    return tags_array2str(tags_str2array($keyword, $tagsSeparator), '-');
                }, tags_str2array($keywords, ',')), $tagsSeparator);
            }
        }

        // We got everything we want, stop the download.
        // If we already found either the title, description or keywords,
        // it's highly unlikely that we'll found the other metas further than
        // in the same chunk of data or the next one. So we also stop the download after that.
        if (
            (!empty($responseCode) && !empty($contentType) && !empty($charset)) && $foundChunk !== null
            && (! $retrieveDescription
                || $foundChunk < $currentChunk
                || (!empty($title) && !empty($description) && !empty($keywords))
            )
        ) {
            return false;
        }

        return $chunkLength;
    };
}
