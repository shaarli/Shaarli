<?php
/**
 * Shaarli utilities
 */

/**
 * Logs a message to a text file
 *
 * The log format is compatible with fail2ban.
 *
 * @param string $logFile  where to write the logs
 * @param string $clientIp the client's remote IPv4/IPv6 address
 * @param string $message  the message to log
 */
function logm($logFile, $clientIp, $message)
{
    file_put_contents(
        $logFile,
        date('Y/m/d H:i:s').' - '.$clientIp.' - '.strval($message).PHP_EOL,
        FILE_APPEND
    );
}

/**
 *  Returns the small hash of a string, using RFC 4648 base64url format
 *
 *  Small hashes:
 *   - are unique (well, as unique as crc32, at last)
 *   - are always 6 characters long.
 *   - only use the following characters: a-z A-Z 0-9 - _ @
 *   - are NOT cryptographically secure (they CAN be forged)
 *
 *  In Shaarli, they are used as a tinyurl-like link to individual entries,
 *  e.g. smallHash('20111006_131924') --> yZH23w
 */
function smallHash($text)
{
    $t = rtrim(base64_encode(hash('crc32', $text, true)), '=');
    return strtr($t, '+/', '-_');
}

/**
 * Tells if a string start with a substring
 */
function startsWith($haystack, $needle, $case=true)
{
    if ($case) {
        return (strcmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
    }
    return (strcasecmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
}

/**
 * Tells if a string ends with a substring
 */
function endsWith($haystack, $needle, $case=true)
{
    if ($case) {
        return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
    }
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
}

/**
 * Htmlspecialchars wrapper
 *
 * @param string $str the string to escape.
 *
 * @return string escaped.
 */
function escape($str)
{
    return htmlspecialchars($str, ENT_COMPAT, 'UTF-8', false);
}

/**
 * Reverse the escape function.
 *
 * @param string $str the string to unescape.
 *
 * @return string unescaped string.
 */
function unescape($str)
{
    return htmlspecialchars_decode($str);
}

/**
 * Link sanitization before templating
 */
function sanitizeLink(&$link)
{
    $link['url'] = escape($link['url']); // useful?
    $link['title'] = escape($link['title']);
    $link['description'] = escape($link['description']);
    $link['tags'] = escape($link['tags']);
}

/**
 * Checks if a string represents a valid date

 * @param string $format The expected DateTime format of the string
 * @param string $string A string-formatted date
 *
 * @return bool whether the string is a valid date
 *
 * @see http://php.net/manual/en/class.datetime.php
 * @see http://php.net/manual/en/datetime.createfromformat.php
 */
function checkDateFormat($format, $string)
{
    $date = DateTime::createFromFormat($format, $string);
    return $date && $date->format($string) == $string;
}

/**
 * Generate a header location from HTTP_REFERER.
 * Make sure the referer is Shaarli itself and prevent redirection loop.
 *
 * @param string $referer - HTTP_REFERER.
 * @param string $host - Server HOST.
 * @param array $loopTerms - Contains list of term to prevent redirection loop.
 *
 * @return string $referer - final referer.
 */
function generateLocation($referer, $host, $loopTerms = array())
{
    $finalReferer = '?';

    // No referer if it contains any value in $loopCriteria.
    foreach ($loopTerms as $value) {
        if (strpos($referer, $value) !== false) {
            return $finalReferer;
        }
    }

    // Remove port from HTTP_HOST
    if ($pos = strpos($host, ':')) {
        $host = substr($host, 0, $pos);
    }

    $refererHost = parse_url($referer, PHP_URL_HOST);
    if (!empty($referer) && (strpos($refererHost, $host) !== false || startsWith('?', $refererHost))) {
        $finalReferer = $referer;
    }

    return $finalReferer;
}

/**
 * Validate session ID to prevent Full Path Disclosure.
 *
 * See #298.
 * The session ID's format depends on the hash algorithm set in PHP settings
 *
 * @param string $sessionId Session ID
 *
 * @return true if valid, false otherwise.
 *
 * @see http://php.net/manual/en/function.hash-algos.php
 * @see http://php.net/manual/en/session.configuration.php
 */
function is_session_id_valid($sessionId)
{
    if (empty($sessionId)) {
        return false;
    }

    if (!$sessionId) {
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9,-]{2,128}$/', $sessionId)) {
        return false;
    }

    return true;
}

/**
 * In a string, converts URLs to clickable links.
 *
 * @param string $text       input string.
 * @param string $redirector if a redirector is set, use it to gerenate links.
 *
 * @return string returns $text with all links converted to HTML links.
 *
 * @see Function inspired from http://www.php.net/manual/en/function.preg-replace.php#85722
 */
function text2clickable($text, $redirector)
{
    $regex = '!(((?:https?|ftp|file)://|apt:|magnet:)\S+[[:alnum:]]/?)!si';

    if (empty($redirector)) {
        return preg_replace($regex, '<a href="$1">$1</a>', $text);
    }
    // Redirector is set, urlencode the final URL.
    return preg_replace_callback(
        $regex,
        function ($matches) use ($redirector) {
            return '<a href="' . $redirector . urlencode($matches[1]) .'">'. $matches[1] .'</a>';
        },
        $text
    );
}

/**
 * This function inserts &nbsp; where relevant so that multiple spaces are properly displayed in HTML
 * even in the absence of <pre>  (This is used in description to keep text formatting).
 *
 * @param string $text input text.
 *
 * @return string formatted text.
 */
function space2nbsp($text)
{
    return preg_replace('/(^| ) /m', '$1&nbsp;', $text);
}

/**
 * Format Shaarli's description
 * TODO: Move me to ApplicationUtils when it's ready.
 *
 * @param string $description shaare's description.
 * @param string $redirector  if a redirector is set, use it to gerenate links.
 *
 * @return string formatted description.
 */
function format_description($description, $redirector) {
    return nl2br(space2nbsp(text2clickable($description, $redirector)));
}
