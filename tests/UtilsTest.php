<?php
/**
 * Utilities' tests
 */

require_once 'application/Utils.php';
require_once 'application/Languages.php';
require_once 'tests/utils/ReferenceSessionIdHashes.php';

// Initialize reference data before PHPUnit starts a session
ReferenceSessionIdHashes::genAllHashes();


/**
 * Unitary tests for Shaarli utilities
 */
class UtilsTest extends PHPUnit_Framework_TestCase
{
    // Session ID hashes
    protected static $sidHashes = null;

    // Log file
    protected static $testLogFile = 'tests.log';

    // Expected log date format
    protected static $dateFormat = 'Y/m/d H:i:s';

    /**
     * @var string Save the current timezone.
     */
    protected static $defaultTimeZone;


    /**
     * Assign reference data
     */
    public static function setUpBeforeClass()
    {
        self::$sidHashes = ReferenceSessionIdHashes::getHashes();
        self::$defaultTimeZone = date_default_timezone_get();
        // Timezone without DST for test consistency
        date_default_timezone_set('Africa/Nairobi');
    }

    /**
     * Reset the timezone
     */
    public static function tearDownAfterClass()
    {
        date_default_timezone_set(self::$defaultTimeZone);
    }

    /**
     * Resets test data before each test
     */
    protected function setUp()
    {
        if (file_exists(self::$testLogFile)) {
            unlink(self::$testLogFile);
        }
    }

    /**
     * Returns a list of the elements from the last logged entry
     *
     * @return list (date, ip address, message)
     */
    protected function getLastLogEntry()
    {
        $logFile = file(self::$testLogFile);
        return explode(' - ', trim(array_pop($logFile), PHP_EOL));
    }

    /**
     * Log a message to a file - IPv4 client address
     */
    public function testLogmIp4()
    {
        $logMessage = 'IPv4 client connected';
        logm(self::$testLogFile, '127.0.0.1', $logMessage);
        list($date, $ip, $message) = $this->getLastLogEntry();

        $this->assertInstanceOf(
            'DateTime',
            DateTime::createFromFormat(self::$dateFormat, $date)
        );
        $this->assertTrue(
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
        );
        $this->assertEquals($logMessage, $message);
    }

    /**
     * Log a message to a file - IPv6 client address
     */
    public function testLogmIp6()
    {
        $logMessage = 'IPv6 client connected';
        logm(self::$testLogFile, '2001:db8::ff00:42:8329', $logMessage);
        list($date, $ip, $message) = $this->getLastLogEntry();

        $this->assertInstanceOf(
            'DateTime',
            DateTime::createFromFormat(self::$dateFormat, $date)
        );
        $this->assertTrue(
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
        );
        $this->assertEquals($logMessage, $message);
    }

    /**
     * Represent a link by its hash
     */
    public function testSmallHash()
    {
        $this->assertEquals('CyAAJw', smallHash('http://test.io'));
        $this->assertEquals(6, strlen(smallHash('https://github.com')));
    }

    /**
     * Look for a substring at the beginning of a string
     */
    public function testStartsWithCaseInsensitive()
    {
        $this->assertTrue(startsWith('Lorem ipsum', 'lorem', false));
        $this->assertTrue(startsWith('Lorem ipsum', 'LoReM i', false));
    }

    /**
     * Look for a substring at the beginning of a string (case-sensitive)
     */
    public function testStartsWithCaseSensitive()
    {
        $this->assertTrue(startsWith('Lorem ipsum', 'Lorem', true));
        $this->assertFalse(startsWith('Lorem ipsum', 'lorem', true));
        $this->assertFalse(startsWith('Lorem ipsum', 'LoReM i', true));
    }

    /**
     * Look for a substring at the beginning of a string (Unicode)
     */
    public function testStartsWithSpecialChars()
    {
        $this->assertTrue(startsWith('å!ùµ', 'å!', false));
        $this->assertTrue(startsWith('µ$åù', 'µ$', true));
    }

    /**
     * Look for a substring at the end of a string
     */
    public function testEndsWithCaseInsensitive()
    {
        $this->assertTrue(endsWith('Lorem ipsum', 'ipsum', false));
        $this->assertTrue(endsWith('Lorem ipsum', 'm IpsUM', false));
    }

    /**
     * Look for a substring at the end of a string (case-sensitive)
     */
    public function testEndsWithCaseSensitive()
    {
        $this->assertTrue(endsWith('lorem Ipsum', 'Ipsum', true));
        $this->assertFalse(endsWith('lorem Ipsum', 'ipsum', true));
        $this->assertFalse(endsWith('lorem Ipsum', 'M IPsuM', true));
    }

    /**
     * Look for a substring at the end of a string (Unicode)
     */
    public function testEndsWithSpecialChars()
    {
        $this->assertTrue(endsWith('å!ùµ', 'ùµ', false));
        $this->assertTrue(endsWith('µ$åù', 'åù', true));
    }

    /**
     * Check valid date strings, according to a DateTime format
     */
    public function testCheckValidDateFormat()
    {
        $this->assertTrue(checkDateFormat('Ymd', '20150627'));
        $this->assertTrue(checkDateFormat('Y-m-d', '2015-06-27'));
    }

    /**
     * Check erroneous date strings, according to a DateTime format
     */
    public function testCheckInvalidDateFormat()
    {
        $this->assertFalse(checkDateFormat('Ymd', '2015'));
        $this->assertFalse(checkDateFormat('Y-m-d', '2015-06'));
        $this->assertFalse(checkDateFormat('Ymd', 'DeLorean'));
    }

    /**
     * Test generate location with valid data.
     */
    public function testGenerateLocation() {
        $ref = 'http://localhost/?test';
        $this->assertEquals($ref, generateLocation($ref, 'localhost'));
        $ref = 'http://localhost:8080/?test';
        $this->assertEquals($ref, generateLocation($ref, 'localhost:8080'));
        $ref = '?localreferer#hash';
        $this->assertEquals($ref, generateLocation($ref, 'localhost:8080'));
    }

    /**
     * Test generate location - anti loop.
     */
    public function testGenerateLocationLoop() {
        $ref = 'http://localhost/?test';
        $this->assertEquals('?', generateLocation($ref, 'localhost', array('test')));
    }

    public function testGenerateLocationAnchor() {
        $ref = '?#foo';
        $this->assertEquals($ref, generateLocation($ref, 'localhost', array('addlink', 'post', 'edit_link')));
    }

    /**
     * Test generate location - from other domain.
     */
    public function testGenerateLocationOut() {
        $ref = 'http://somewebsite.com/?test';
        $this->assertEquals('?', generateLocation($ref, 'localhost'));
    }

    /**
     * Test is_session_id_valid with a valid ID - TEST ALL THE HASHES!
     *
     * This tests extensively covers all hash algorithms / bit representations
     */
    public function testIsAnyHashSessionIdValid()
    {
        foreach (self::$sidHashes as $algo => $bpcs) {
            foreach ($bpcs as $bpc => $hash) {
                $this->assertTrue(is_session_id_valid($hash));
            }
        }
    }

    /**
     * Test is_session_id_valid with a valid ID - SHA-1 hashes
     */
    public function testIsSha1SessionIdValid()
    {
        $this->assertTrue(is_session_id_valid(sha1('shaarli')));
    }

    /**
     * Test is_session_id_valid with a valid ID - SHA-256 hashes
     */
    public function testIsSha256SessionIdValid()
    {
        $this->assertTrue(is_session_id_valid(hash('sha256', 'shaarli')));
    }

    /**
     * Test is_session_id_valid with a valid ID - SHA-512 hashes
     */
    public function testIsSha512SessionIdValid()
    {
        $this->assertTrue(is_session_id_valid(hash('sha512', 'shaarli')));
    }

    /**
     * Test is_session_id_valid with invalid IDs.
     */
    public function testIsSessionIdInvalid()
    {
        $this->assertFalse(is_session_id_valid(''));
        $this->assertFalse(is_session_id_valid(array()));
        $this->assertFalse(
            is_session_id_valid('c0ZqcWF3VFE2NmJBdm1HMVQ0ZHJ3UmZPbTFsNGhkNHI=')
        );
    }
    
    /**
     * Test generateSecretApi.
     */
    public function testGenerateSecretApi()
    {
        $this->assertEquals(12, strlen(generate_api_secret('foo', 'bar')));
    }

    /**
     * Test generateSecretApi with invalid parameters.
     */
    public function testGenerateSecretApiInvalid()
    {
        $this->assertFalse(generate_api_secret('', ''));
        $this->assertFalse(generate_api_secret(false, false));
    }

    /**
     * Test normalize_spaces.
     */
    public function testNormalizeSpace()
    {
        $str = ' foo   bar is   important ';
        $this->assertEquals('foo bar is important', normalize_spaces($str));
        $this->assertEquals('foo', normalize_spaces('foo'));
        $this->assertEquals('', normalize_spaces(''));
        $this->assertEquals(null, normalize_spaces(null));
    }

    /**
     * Test arrays_combine
     */
    public function testCartesianProductGenerator()
    {
        $arr = [['ab', 'cd'], ['ef', 'gh'], ['ij', 'kl'], ['m']];
        $expected = [
            ['ab', 'ef', 'ij', 'm'],
            ['ab', 'ef', 'kl', 'm'],
            ['ab', 'gh', 'ij', 'm'],
            ['ab', 'gh', 'kl', 'm'],
            ['cd', 'ef', 'ij', 'm'],
            ['cd', 'ef', 'kl', 'm'],
            ['cd', 'gh', 'ij', 'm'],
            ['cd', 'gh', 'kl', 'm'],
        ];
        $this->assertEquals($expected, iterator_to_array(cartesian_product_generator($arr)));
    }

    /**
     * Test date_format() with invalid parameter.
     */
    public function testDateFormatInvalid()
    {
        $this->assertFalse(format_date([]));
        $this->assertFalse(format_date(null));
    }

    /**
     * Test is_integer_mixed with valid values
     */
    public function testIsIntegerMixedValid()
    {
        $this->assertTrue(is_integer_mixed(12));
        $this->assertTrue(is_integer_mixed('12'));
        $this->assertTrue(is_integer_mixed(-12));
        $this->assertTrue(is_integer_mixed('-12'));
        $this->assertTrue(is_integer_mixed(0));
        $this->assertTrue(is_integer_mixed('0'));
        $this->assertTrue(is_integer_mixed(0x0a));
    }

    /**
     * Test is_integer_mixed with invalid values
     */
    public function testIsIntegerMixedInvalid()
    {
        $this->assertFalse(is_integer_mixed(true));
        $this->assertFalse(is_integer_mixed(false));
        $this->assertFalse(is_integer_mixed([]));
        $this->assertFalse(is_integer_mixed(['test']));
        $this->assertFalse(is_integer_mixed([12]));
        $this->assertFalse(is_integer_mixed(new DateTime()));
        $this->assertFalse(is_integer_mixed('0x0a'));
        $this->assertFalse(is_integer_mixed('12k'));
        $this->assertFalse(is_integer_mixed('k12'));
        $this->assertFalse(is_integer_mixed(''));
    }

    /**
     * Test return_bytes
     */
    public function testReturnBytes()
    {
        $this->assertEquals(2 * 1024, return_bytes('2k'));
        $this->assertEquals(2 * 1024, return_bytes('2K'));
        $this->assertEquals(2 * (pow(1024, 2)), return_bytes('2m'));
        $this->assertEquals(2 * (pow(1024, 2)), return_bytes('2M'));
        $this->assertEquals(2 * (pow(1024, 3)), return_bytes('2g'));
        $this->assertEquals(2 * (pow(1024, 3)), return_bytes('2G'));
        $this->assertEquals(374, return_bytes('374'));
        $this->assertEquals(374, return_bytes(374));
        $this->assertEquals(0, return_bytes('0'));
        $this->assertEquals(0, return_bytes(0));
        $this->assertEquals(-1, return_bytes('-1'));
        $this->assertEquals(-1, return_bytes(-1));
        $this->assertEquals('', return_bytes(''));
    }

    /**
     * Test human_bytes
     */
    public function testHumanBytes()
    {
        $this->assertEquals('2kiB', human_bytes(2 * 1024));
        $this->assertEquals('2kiB', human_bytes(strval(2 * 1024)));
        $this->assertEquals('2MiB', human_bytes(2 * (pow(1024, 2))));
        $this->assertEquals('2MiB', human_bytes(strval(2 * (pow(1024, 2)))));
        $this->assertEquals('2GiB', human_bytes(2 * (pow(1024, 3))));
        $this->assertEquals('2GiB', human_bytes(strval(2 * (pow(1024, 3)))));
        $this->assertEquals('374B', human_bytes(374));
        $this->assertEquals('374B', human_bytes('374'));
        $this->assertEquals('Unlimited', human_bytes('0'));
        $this->assertEquals('Unlimited', human_bytes(0));
        $this->assertEquals('Setting not set', human_bytes(''));
    }

    /**
     * Test get_max_upload_size
     */
    public function testGetMaxUploadSize()
    {
        $this->assertEquals('1MiB', get_max_upload_size(2097152, '1024k'));
        $this->assertEquals('1MiB', get_max_upload_size('1m', '2m'));
        $this->assertEquals('100B', get_max_upload_size(100, 100));
    }
}
