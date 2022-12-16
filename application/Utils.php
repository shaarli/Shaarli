<?php

/**
 * Shaarli utilities
 */

/**
 * Format log using provided data.
 *
 * @param string      $message  the message to log
 * @param string|null $clientIp the client's remote IPv4/IPv6 address
 *
 * @return string Formatted message to log
 */
function format_log(string $message, string $clientIp = null): string
{
    $out = $message;

    if (!empty($clientIp)) {
        // Note: we keep the first dash to avoid breaking fail2ban configs
        $out = '- ' . $clientIp . ' - ' . $out;
    }

    return $out;
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
 *  built once with the combination of the date and item ID.
 *  e.g. smallHash('20111006_131924' . 142) --> eaWxtQ
 *
 * @warning before v0.8.1, smallhashes were built only with the date,
 *          and their value has been preserved.
 *
 * @param string $text Create a hash from this text.
 *
 * @return string generated small hash.
 */
function smallHash($text)
{
    $t = rtrim(base64_encode(hash('crc32', $text, true)), '=');
    return strtr($t, '+/', '-_');
}

/**
 * Tells if a string start with a substring
 *
 * @param string $haystack Given string.
 * @param string $needle   String to search at the beginning of $haystack.
 * @param bool   $case     Case sensitive.
 *
 * @return bool True if $haystack starts with $needle.
 */
function startsWith($haystack, $needle, $case = true)
{
    $needle = $needle ?? '';
    if ($case) {
        return (strcmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
    }
    return (strcasecmp(substr($haystack, 0, strlen($needle)), $needle) === 0);
}

/**
 * Tells if a string ends with a substring
 *
 * @param string $haystack Given string.
 * @param string $needle   String to search at the end of $haystack.
 * @param bool   $case     Case sensitive.
 *
 * @return bool True if $haystack ends with $needle.
 */
function endsWith($haystack, $needle, $case = true)
{
    if ($case) {
        return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
    }
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)), $needle) === 0);
}

/**
 * Htmlspecialchars wrapper
 * Support multidimensional array of strings.
 *
 * @param mixed $input Data to escape: a single string or an array of strings.
 *
 * @return string|array escaped.
 */
function escape($input)
{
    if (null === $input) {
        return null;
    }

    if (is_bool($input) || is_int($input) || is_float($input) || $input instanceof DateTimeInterface) {
        return $input;
    }

    if (is_array($input)) {
        $out = [];
        foreach ($input as $key => $value) {
            $out[escape($key)] = escape($value);
        }
        return $out;
    }
    return htmlspecialchars($input, ENT_COMPAT, 'UTF-8', false);
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
 * Sanitize link before rendering.
 *
 * @param array $link Link to escape.
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
function generateLocation($referer, $host, $loopTerms = [])
{
    $finalReferer = './?';

    // No referer if it contains any value in $loopCriteria.
    foreach (array_filter($loopTerms) as $value) {
        if (strpos($referer, $value) !== false) {
            return $finalReferer;
        }
    }

    // Remove port from HTTP_HOST
    if ($pos = strpos($host, ':')) {
        $host = substr($host, 0, $pos);
    }

    $refererHost = parse_url($referer, PHP_URL_HOST) ?? '';
    if (!empty($referer) && (strpos($refererHost, $host) !== false || startsWith('?', $refererHost))) {
        $finalReferer = $referer;
    }

    return $finalReferer;
}

/**
 * Sniff browser language to set the locale automatically.
 * Note that is may not work on your server if the corresponding locale is not installed.
 *
 * @param string $headerLocale Locale send in HTTP headers (e.g. "fr,fr-fr;q=0.8,en;q=0.5,en-us;q=0.3").
 **/
function autoLocale($headerLocale)
{
    // Default if browser does not send HTTP_ACCEPT_LANGUAGE
    $locales = ['en_US.UTF-8', 'en_US.utf8', 'en_US'];
    if (! empty($headerLocale)) {
        if (preg_match_all('/([a-z]{2,3})[-_]?([a-z]{2})?,?/i', $headerLocale, $matches, PREG_SET_ORDER)) {
            $attempts = [];
            foreach ($matches as $match) {
                $first = [strtolower($match[1]), strtoupper($match[1])];
                $separators = ['_', '-'];
                $encodings = ['utf8', 'UTF-8'];
                if (!empty($match[2])) {
                    $second = [strtoupper($match[2]), strtolower($match[2])];
                    $items = [$first, $separators, $second, ['.'], $encodings];
                } else {
                    $items = [$first, $separators, $first, ['.'], $encodings];
                }
                $attempts = array_merge($attempts, iterator_to_array(cartesian_product_generator($items)));
            }

            if (! empty($attempts)) {
                $locales = array_merge(array_map('implode', $attempts), $locales);
            }
        }
    }

    setlocale(LC_ALL, $locales);
}

/**
 * Build a Generator object representing the cartesian product from given $items.
 *
 * Example:
 *   [['a'], ['b', 'c']]
 * will generate:
 *   [
 *      ['a', 'b'],
 *      ['a', 'c'],
 *   ]
 *
 * @param array $items array of array of string
 *
 * @return Generator representing the cartesian product of given array.
 *
 * @see https://en.wikipedia.org/wiki/Cartesian_product
 */
function cartesian_product_generator($items)
{
    if (empty($items)) {
        yield [];
    }
    $subArray = array_pop($items);
    if (empty($subArray)) {
        return;
    }
    foreach (cartesian_product_generator($items) as $item) {
        foreach ($subArray as $value) {
            yield $item + [count($item) => $value];
        }
    }
}

/**
 * Generates a default API secret.
 *
 * Note that the random-ish methods used in this function are predictable,
 * which makes them NOT suitable for crypto.
 * BUT the random string is salted with the salt and hashed with the username.
 * It makes the generated API secret secured enough for Shaarli.
 *
 * PHP 7 provides random_int(), designed for cryptography.
 * More info: http://stackoverflow.com/questions/4356289/php-random-string-generator

 * @param string $username Shaarli login username
 * @param string $salt     Shaarli password hash salt
 *
 * @return string|bool Generated API secret, 12 char length.
 *                     Or false if invalid parameters are provided (which will make the API unusable).
 */
function generate_api_secret($username, $salt)
{
    if (empty($username) || empty($salt)) {
        return false;
    }

    return str_shuffle(substr(hash_hmac('sha512', uniqid($salt), $username), 10, 12));
}

/**
 * Trim string, replace sequences of whitespaces by a single space.
 * PHP equivalent to `normalize-space` XSLT function.
 *
 * @param string $string Input string.
 *
 * @return mixed Normalized string.
 */
function normalize_spaces($string)
{
    return preg_replace('/\s{2,}/', ' ', trim($string ?? ''));
}

/**
 * Format the date according to the locale.
 *
 * Requires php-intl to display international datetimes,
 * otherwise default format '%c' will be returned.
 *
 * @param DateTimeInterface $date to format.
 * @param bool              $time Displays time if true.
 * @param bool              $intl Use international format if true.
 *
 * @return bool|string Formatted date, or false if the input is invalid.
 */
function format_date($date, $time = true, $intl = true)
{
    if (! $date instanceof DateTimeInterface) {
        return false;
    }

    if (! $intl || ! class_exists('IntlDateFormatter')) {
        $format = 'F j, Y';
        if ($time) {
            $format .= ' h:i:s A \G\M\TP';
        }
        return $date->format($format);
    }
    $formatter = new IntlDateFormatter(
        setlocale(LC_TIME, 0),
        IntlDateFormatter::LONG,
        $time ? IntlDateFormatter::LONG : IntlDateFormatter::NONE
    );
    $formatter->setTimeZone($date->getTimezone());

    return $formatter->format($date);
}

/**
 * Format the date month according to the locale.
 *
 * @param DateTimeInterface $date to format.
 *
 * @return bool|string Formatted date, or false if the input is invalid.
 */
function format_month(DateTimeInterface $date)
{
    if (! $date instanceof DateTimeInterface) {
        return false;
    }

    return strftime('%B', $date->getTimestamp());
}


/**
 * Check if the input is an integer, no matter its real type.
 *
 * PHP is a bit messy regarding this:
 *   - is_int returns false if the input is a string
 *   - ctype_digit returns false if the input is an integer or negative
 *
 * @param mixed $input value
 *
 * @return bool true if the input is an integer, false otherwise
 */
function is_integer_mixed($input)
{
    if (is_array($input) || is_bool($input) || is_object($input)) {
        return false;
    }
    $input = strval($input);
    return ctype_digit($input) || (startsWith($input, '-') && ctype_digit(substr($input, 1)));
}

/**
 * Convert post_max_size/upload_max_filesize (e.g. '16M') parameters to bytes.
 *
 * @param string $val Size expressed in string.
 *
 * @return int Size expressed in bytes.
 */
function return_bytes($val)
{
    if (is_integer_mixed($val) || $val === '0' || empty($val)) {
        return $val;
    }
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = intval(substr($val, 0, -1));
    switch ($last) {
        case 'g':
            $val *= 1024;
        // do no break in order 1024^2 for each unit
        case 'm':
            $val *= 1024;
        // do no break in order 1024^2 for each unit
        case 'k':
            $val *= 1024;
    }
    return $val;
}

/**
 * Return a human readable size from bytes.
 *
 * @param int $bytes value
 *
 * @return string Human readable size
 */
function human_bytes($bytes)
{
    if ($bytes === '') {
        return t('Setting not set');
    }
    if (! is_integer_mixed($bytes)) {
        return $bytes;
    }
    $bytes = intval($bytes);
    if ($bytes === 0) {
        return t('Unlimited');
    }

    $units = [t('B'), t('kiB'), t('MiB'), t('GiB')];
    for ($i = 0; $i < count($units) && $bytes >= 1024; ++$i) {
        $bytes /= 1024;
    }

    return round($bytes) . $units[$i];
}

/**
 * Try to determine max file size for uploads (POST).
 * Returns an integer (in bytes) or formatted depending on $format.
 *
 * @param mixed $limitPost   post_max_size PHP setting
 * @param mixed $limitUpload upload_max_filesize PHP setting
 * @param bool  $format      Format max upload size to human readable size
 *
 * @return int|string max upload file size
 */
function get_max_upload_size($limitPost, $limitUpload, $format = true)
{
    $size1 = return_bytes($limitPost);
    $size2 = return_bytes($limitUpload);
    // Return the smaller of two:
    $maxsize = min($size1, $size2);
    return $format ? human_bytes($maxsize) : $maxsize;
}

/**
 * Sort the given array alphabetically using php-intl if available.
 * Case sensitive.
 *
 * Note: doesn't support multidimensional arrays
 *
 * @param array $data    Input array, passed by reference
 * @param bool  $reverse Reverse sort if set to true
 * @param bool  $byKeys  Sort the array by keys if set to true, by value otherwise.
 */
function alphabetical_sort(&$data, $reverse = false, $byKeys = false)
{
    $callback = function ($a, $b) use ($reverse) {
        // Collator is part of PHP intl.
        if (class_exists('Collator')) {
            $collator = new Collator(setlocale(LC_COLLATE, 0));
            if (!intl_is_failure(intl_get_error_code())) {
                return $collator->compare($a, $b) * ($reverse ? -1 : 1);
            }
        }

        return strcasecmp($a, $b) * ($reverse ? -1 : 1);
    };

    if ($byKeys) {
        uksort($data, $callback);
    } else {
        usort($data, $callback);
    }
}

/**
 * Wrapper function for translation which match the API
 * of gettext()/_() and ngettext().
 *
 * @param string $text      Text to translate.
 * @param string $nText     The plural message ID.
 * @param int    $nb        The number of items for plural forms.
 * @param string $domain    The domain where the translation is stored (default: shaarli).
 * @param array  $variables Associative array of variables to replace in translated text.
 * @param bool   $fixCase   Apply `ucfirst` on the translated string, might be useful for strings with variables.
 *
 * @return string Text translated.
 */
function t($text, $nText = '', $nb = 1, $domain = 'shaarli', $variables = [], $fixCase = false)
{
    $postFunction = $fixCase ? 'ucfirst' : function ($input) {
        return $input;
    };

    return $postFunction(dn__($domain, $text, $nText, $nb, $variables));
}

/**
 * Converts an exception into a printable stack trace string.
 */
function exception2text(Throwable $e): string
{
    return $e->getMessage() . PHP_EOL . $e->getFile() . $e->getLine() . PHP_EOL . $e->getTraceAsString();
}
