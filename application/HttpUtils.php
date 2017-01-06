<?php
/**
 * GET an HTTP URL to retrieve its content
 * Uses the cURL library or a fallback method 
 *
 * @param string $url      URL to get (http://...)
 * @param int    $timeout  network timeout (in seconds)
 * @param int    $maxBytes maximum downloaded bytes (default: 4 MiB)
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
function get_http_response($url, $timeout = 30, $maxBytes = 4194304)
{
    $urlObj = new Url($url);
    $cleanUrl = $urlObj->idnToAscii();

    if (!filter_var($cleanUrl, FILTER_VALIDATE_URL) || !$urlObj->isHttp()) {
        return array(array(0 => 'Invalid HTTP Url'), false);
    }

    $userAgent =
        'Mozilla/5.0 (X11; Fedora; Linux x86_64; rv:45.0)'
        . ' Gecko/20100101 Firefox/45.0';
    $acceptLanguage =
        substr(setlocale(LC_COLLATE, 0), 0, 2) . ',en-US;q=0.7,en;q=0.3';
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
        return array(array(0 => 'curl_init() error'), false);
    }

    // General cURL settings
    curl_setopt($ch, CURLOPT_AUTOREFERER,       true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    true);
    curl_setopt($ch, CURLOPT_HEADER,            true);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array('Accept-Language: ' . $acceptLanguage)
    );
    curl_setopt($ch, CURLOPT_MAXREDIRS,         $maxRedirs);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,    true);
    curl_setopt($ch, CURLOPT_TIMEOUT,           $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT,         $userAgent);

    // Max download size management
    curl_setopt($ch, CURLOPT_BUFFERSIZE,        1024);
    curl_setopt($ch, CURLOPT_NOPROGRESS,        false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION,
        function($arg0, $arg1, $arg2, $arg3, $arg4 = 0) use ($maxBytes)
        {
            if (version_compare(phpversion(), '5.5', '<')) {
                // PHP version lower than 5.5
                // Callback has 4 arguments
                $downloaded = $arg1;
            } else {
                // Callback has 5 arguments
                $downloaded = $arg2;
            }
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
            return array(false, false);
        }
        return array(array(0 => 'curl_exec() error: ' . $errorStr), false);
    }

    // Formatting output like the fallback method
    $rawHeaders = substr($response, 0, $headSize);

    // Keep only headers from latest redirection
    $rawHeadersArrayRedirs = explode("\r\n\r\n", trim($rawHeaders));
    $rawHeadersLastRedir = end($rawHeadersArrayRedirs);

    $content = substr($response, $headSize);
    $headers = array();
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
                    $headers[$key] = array(0 => $headers[$key]);
                }
                $headers[$key][] = $value;
            } else {
                $headers[$key] = $value;
            }
        } else {
            $headers[] = $splitLine[0];
        }
    }

    return array($headers, $content);
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
    $options = array(
        'http' => array(
            'method' => 'GET',
            'timeout' => $timeout,
            'user_agent' => $userAgent,
            'header' => "Accept: */*\r\n"
                . 'Accept-Language: ' . $acceptLanguage
        )
    );

    stream_context_set_default($options);
    list($headers, $finalUrl) = get_redirected_headers($cleanUrl, $maxRedr);
    if (! $headers || strpos($headers[0], '200 OK') === false) {
        $options['http']['request_fulluri'] = true;
        stream_context_set_default($options);
        list($headers, $finalUrl) = get_redirected_headers($cleanUrl, $maxRedr);
    }

    if (! $headers) {
        return array($headers, false);
    }

    try {
        // TODO: catch Exception in calling code (thumbnailer)
        $context = stream_context_create($options);
        $content = file_get_contents($finalUrl, false, $context, -1, $maxBytes);
    } catch (Exception $exc) {
        return array(array(0 => 'HTTP Error'), $exc->getMessage());
    }

    return array($headers, $content);
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
    if ($redirectionLimit-- > 0
        && !empty($headers)
        && (strpos($headers[0], '301') !== false || strpos($headers[0], '302') !== false)
        && !empty($headers['Location'])) {

        $redirection = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
        if ($redirection != $url) {
            $redirection = getAbsoluteUrl($url, $redirection);
            return get_redirected_headers($redirection, $redirectionLimit);
        }
    }

    return array($headers, $url);
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
    $final = $parts['scheme'] .'://'. $parts['host'];
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

            if (($scheme == 'http' && $port != '80')
                || ($scheme == 'https' && $port != '443')
            ) {
                $port = ':' . $port;
            } else {
                $port = '';
            }
        }

        return $scheme.'://'.$server['SERVER_NAME'].$port;
    }

    // SSL detection
    if ((! empty($server['HTTPS']) && strtolower($server['HTTPS']) == 'on')
        || (isset($server['SERVER_PORT']) && $server['SERVER_PORT'] == '443')) {
        $scheme = 'https';
    }

    // Do not append standard port values
    if (($scheme == 'http' && $server['SERVER_PORT'] != '80')
        || ($scheme == 'https' && $server['SERVER_PORT'] != '443')) {
        $port = ':'.$server['SERVER_PORT'];
    }

    return $scheme.'://'.$server['SERVER_NAME'].$port;
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
    $scriptname = $server['SCRIPT_NAME'];
    if (endsWith($scriptname, 'index.php')) {
        $scriptname = substr($scriptname, 0, -9);
    }
    return server_url($server) . $scriptname;
}

/**
 * Returns the absolute URL of the current script, with the query
 *
 * If the resource is "index.php", then it is removed (for better-looking URLs)
 *
 * @param array $server the $_SERVER array
 *
 * @return string the absolute URL of the current script, with the query
 */
function page_url($server)
{
    if (! empty($server['QUERY_STRING'])) {
        return index_url($server).'?'.$server['QUERY_STRING'];
    }
    return index_url($server);
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
