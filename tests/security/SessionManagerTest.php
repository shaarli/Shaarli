<?php
require_once 'tests/utils/FakeConfigManager.php';

// Initialize reference data _before_ PHPUnit starts a session
require_once 'tests/utils/ReferenceSessionIdHashes.php';
ReferenceSessionIdHashes::genAllHashes();

use PHPUnit\Framework\TestCase;
use Shaarli\Security\SessionManager;

/**
 * Test coverage for SessionManager
 */
class SessionManagerTest extends TestCase
{
    /** @var array Session ID hashes */
    protected static $sidHashes = null;

    /** @var \FakeConfigManager ConfigManager substitute for testing */
    protected $conf = null;

    /** @var array $_SESSION array for testing */
    protected $session = [];

    /** @var SessionManager Server-side session management abstraction */
    protected $sessionManager = null;

    /**
     * Assign reference data
     */
    public static function setUpBeforeClass()
    {
        self::$sidHashes = ReferenceSessionIdHashes::getHashes();
    }

    /**
     * Initialize or reset test resources
     */
    public function setUp()
    {
        $this->conf = new FakeConfigManager([
            'credentials.login' => 'johndoe',
            'credentials.salt' => 'salt',
            'security.session_protection_disabled' => false,
        ]);
        $this->session = [];
        $this->sessionManager = new SessionManager($this->session, $this->conf);
    }

    /**
     * Generate a session token
     */
    public function testGenerateToken()
    {
        $token = $this->sessionManager->generateToken();

        $this->assertEquals(1, $this->session['tokens'][$token]);
        $this->assertEquals(40, strlen($token));
    }

    /**
     * Check a session token
     */
    public function testCheckToken()
    {
        $token = '4dccc3a45ad9d03e5542b90c37d8db6d10f2b38b';
        $session = [
            'tokens' => [
                $token => 1,
            ],
        ];
        $sessionManager = new SessionManager($session, $this->conf);

        // check and destroy the token
        $this->assertTrue($sessionManager->checkToken($token));
        $this->assertFalse(isset($session['tokens'][$token]));

        // ensure the token has been destroyed
        $this->assertFalse($sessionManager->checkToken($token));
    }

    /**
     * Generate and check a session token
     */
    public function testGenerateAndCheckToken()
    {
        $token = $this->sessionManager->generateToken();

        // ensure a token has been generated
        $this->assertEquals(1, $this->session['tokens'][$token]);
        $this->assertEquals(40, strlen($token));

        // check and destroy the token
        $this->assertTrue($this->sessionManager->checkToken($token));
        $this->assertFalse(isset($this->session['tokens'][$token]));

        // ensure the token has been destroyed
        $this->assertFalse($this->sessionManager->checkToken($token));
    }

    /**
     * Check an invalid session token
     */
    public function testCheckInvalidToken()
    {
        $this->assertFalse($this->sessionManager->checkToken('4dccc3a45ad9d03e5542b90c37d8db6d10f2b38b'));
    }

    /**
     * Test SessionManager::checkId with a valid ID - TEST ALL THE HASHES!
     *
     * This tests extensively covers all hash algorithms / bit representations
     */
    public function testIsAnyHashSessionIdValid()
    {
        foreach (self::$sidHashes as $algo => $bpcs) {
            foreach ($bpcs as $bpc => $hash) {
                $this->assertTrue(SessionManager::checkId($hash));
            }
        }
    }

    /**
     * Test checkId with a valid ID - SHA-1 hashes
     */
    public function testIsSha1SessionIdValid()
    {
        $this->assertTrue(SessionManager::checkId(sha1('shaarli')));
    }

    /**
     * Test checkId with a valid ID - SHA-256 hashes
     */
    public function testIsSha256SessionIdValid()
    {
        $this->assertTrue(SessionManager::checkId(hash('sha256', 'shaarli')));
    }

    /**
     * Test checkId with a valid ID - SHA-512 hashes
     */
    public function testIsSha512SessionIdValid()
    {
        $this->assertTrue(SessionManager::checkId(hash('sha512', 'shaarli')));
    }

    /**
     * Test checkId with invalid IDs.
     */
    public function testIsSessionIdInvalid()
    {
        $this->assertFalse(SessionManager::checkId(''));
        $this->assertFalse(SessionManager::checkId([]));
        $this->assertFalse(
            SessionManager::checkId('c0ZqcWF3VFE2NmJBdm1HMVQ0ZHJ3UmZPbTFsNGhkNHI=')
        );
    }

    /**
     * Store login information after a successful login
     */
    public function testStoreLoginInfo()
    {
        $this->sessionManager->storeLoginInfo('ip_id');

        $this->assertGreaterThan(time(), $this->session['expires_on']);
        $this->assertEquals('ip_id', $this->session['ip']);
        $this->assertEquals('johndoe', $this->session['username']);
    }

    /**
     * Extend a server-side session by SessionManager::$SHORT_TIMEOUT
     */
    public function testExtendSession()
    {
        $this->sessionManager->extendSession();

        $this->assertGreaterThan(time(), $this->session['expires_on']);
        $this->assertLessThanOrEqual(
            time() + SessionManager::$SHORT_TIMEOUT,
            $this->session['expires_on']
        );
    }

    /**
     * Extend a server-side session by SessionManager::$LONG_TIMEOUT
     */
    public function testExtendSessionStaySignedIn()
    {
        $this->sessionManager->setStaySignedIn(true);
        $this->sessionManager->extendSession();

        $this->assertGreaterThan(time(), $this->session['expires_on']);
        $this->assertGreaterThan(
            time() + SessionManager::$LONG_TIMEOUT - 10,
            $this->session['expires_on']
        );
        $this->assertLessThanOrEqual(
            time() + SessionManager::$LONG_TIMEOUT,
            $this->session['expires_on']
        );
    }

    /**
     * Unset session variables after logging out
     */
    public function testLogout()
    {
        $this->session = [
            'ip' => 'ip_id',
            'expires_on' => time() + 1000,
            'username' => 'johndoe',
            'visibility' => 'public',
            'untaggedonly' => false,
        ];
        $this->sessionManager->logout();

        $this->assertFalse(isset($this->session['ip']));
        $this->assertFalse(isset($this->session['expires_on']));
        $this->assertFalse(isset($this->session['username']));
        $this->assertFalse(isset($this->session['visibility']));
        $this->assertFalse(isset($this->session['untaggedonly']));
    }

    /**
     * The session is active and expiration time has been reached
     */
    public function testHasExpiredTimeElapsed()
    {
        $this->session['expires_on'] = time() - 10;

        $this->assertTrue($this->sessionManager->hasSessionExpired());
    }

    /**
     * The session is active and expiration time has not been reached
     */
    public function testHasNotExpired()
    {
        $this->session['expires_on'] = time() + 1000;

        $this->assertFalse($this->sessionManager->hasSessionExpired());
    }

    /**
     * Session hijacking protection is disabled, we assume the IP has not changed
     */
    public function testHasClientIpChangedNoSessionProtection()
    {
        $this->conf->set('security.session_protection_disabled', true);

        $this->assertFalse($this->sessionManager->hasClientIpChanged(''));
    }

    /**
     * The client IP identifier has not changed
     */
    public function testHasClientIpChangedNope()
    {
        $this->session['ip'] = 'ip_id';
        $this->assertFalse($this->sessionManager->hasClientIpChanged('ip_id'));
    }

    /**
     * The client IP identifier has changed
     */
    public function testHasClientIpChanged()
    {
        $this->session['ip'] = 'ip_id_one';
        $this->assertTrue($this->sessionManager->hasClientIpChanged('ip_id_two'));
    }

    /**
     * Test creating an entry in the session array
     */
    public function testSetSessionParameterCreate(): void
    {
        $this->sessionManager->setSessionParameter('abc', 'def');

        static::assertSame('def', $this->session['abc']);
    }

    /**
     * Test updating an entry in the session array
     */
    public function testSetSessionParameterUpdate(): void
    {
        $this->session['abc'] = 'ghi';

        $this->sessionManager->setSessionParameter('abc', 'def');

        static::assertSame('def', $this->session['abc']);
    }

    /**
     * Test updating an entry in the session array with null value
     */
    public function testSetSessionParameterUpdateNull(): void
    {
        $this->session['abc'] = 'ghi';

        $this->sessionManager->setSessionParameter('abc', null);

        static::assertArrayHasKey('abc', $this->session);
        static::assertNull($this->session['abc']);
    }

    /**
     * Test deleting an existing entry in the session array
     */
    public function testDeleteSessionParameter(): void
    {
        $this->session['abc'] = 'def';

        $this->sessionManager->deleteSessionParameter('abc');

        static::assertArrayNotHasKey('abc', $this->session);
    }

    /**
     * Test deleting a non existent entry in the session array
     */
    public function testDeleteSessionParameterNotExisting(): void
    {
        $this->sessionManager->deleteSessionParameter('abc');

        static::assertArrayNotHasKey('abc', $this->session);
    }
}
