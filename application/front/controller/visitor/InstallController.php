<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Front\Exception\AlreadyInstalledException;
use Shaarli\Front\Exception\ResourcePermissionException;
use Shaarli\Helper\ApplicationUtils;
use Shaarli\Languages;
use Shaarli\Render\TemplatePage;
use Shaarli\Security\SessionManager;

/**
 * Slim controller used to render install page, and create initial configuration file.
 */
class InstallController extends ShaarliVisitorController
{
    public const SESSION_TEST_KEY = 'session_tested';
    public const SESSION_TEST_VALUE = 'Working';

    public function __construct(Container $container)
    {
        parent::__construct($container);

        if (is_file($this->container->get('conf')->getConfigFileExt())) {
            throw new AlreadyInstalledException();
        }
    }

    /**
     * Display the install template page.
     * Also test file permissions and sessions beforehand.
     */
    public function index(Request $request, Response $response): Response
    {
        // Before installation, we'll make sure that permissions are set properly, and sessions are working.
        $this->checkPermissions();

        if (
            static::SESSION_TEST_VALUE
            !== $this->container->get('sessionManager')->getSessionParameter(static::SESSION_TEST_KEY)
        ) {
            $this->container->get('sessionManager')
                ->setSessionParameter(static::SESSION_TEST_KEY, static::SESSION_TEST_VALUE);

            return $this->redirect($response, '/install/session-test');
        }

        [$continents, $cities] = generateTimeZoneData(timezone_identifiers_list(), date_default_timezone_get());

        $this->assignView('continents', $continents);
        $this->assignView('cities', $cities);
        $this->assignView('languages', Languages::getAvailableLanguages());

        $phpEol = new \DateTimeImmutable(ApplicationUtils::getPhpEol(PHP_VERSION));

        $permissions = array_merge(
            ApplicationUtils::checkResourcePermissions($this->container->get('conf')),
            ApplicationUtils::checkDatastoreMutex()
        );

        $this->assignView('php_version', PHP_VERSION);
        $this->assignView('php_eol', format_date($phpEol, false));
        $this->assignView('php_has_reached_eol', $phpEol < new \DateTimeImmutable());
        $this->assignView('php_extensions', ApplicationUtils::getPhpExtensionsRequirement());
        $this->assignView('permissions', $permissions);

        $this->assignView('pagetitle', t('Install Shaarli'));

        return $this->respondWithTemplate($response, TemplatePage::INSTALL);
    }

    /**
     * Route checking that the session parameter has been properly saved between two distinct requests.
     * If the session parameter is preserved, redirect to install template page, otherwise displays error.
     */
    public function sessionTest(Request $request, Response $response): Response
    {
        // This part makes sure sessions works correctly.
        // (Because on some hosts, session.save_path may not be set correctly,
        // or we may not have write access to it.)
        if (
            static::SESSION_TEST_VALUE
            !== $this->container->get('sessionManager')->getSessionParameter(static::SESSION_TEST_KEY)
        ) {
            // Step 2: Check if data in session is correct.
            $msg = t(
                '<pre>Sessions do not seem to work correctly on your server.<br>' .
                'Make sure the variable "session.save_path" is set correctly in your PHP config, ' .
                'and that you have write access to it.<br>' .
                'It currently points to %s.<br>' .
                'On some browsers, accessing your server via a hostname like \'localhost\' ' .
                'or any custom hostname without a dot causes cookie storage to fail. ' .
                'We recommend accessing your server via it\'s IP address or Fully Qualified Domain Name.<br>'
            );
            $msg = sprintf($msg, $this->container->get('sessionManager')->getSavePath());

            $this->assignView('message', $msg);

            return $this->respondWithTemplate($response, TemplatePage::ERROR);
        }

        return $this->redirect($response, '/install');
    }

    /**
     * Save installation form and initialize config file and datastore if necessary.
     */
    public function save(Request $request, Response $response): Response
    {
        $timezone = 'UTC';
        if (
            !empty($request->getParsedBody()['continent'] ?? null)
            && !empty($request->getParsedBody()['city'] ?? null)
            && isTimeZoneValid(
                $request->getParsedBody()['continent'] ?? null,
                $request->getParsedBody()['city'] ?? null
            )
        ) {
            $timezone = ($request->getParsedBody()['continent'] ?? null) . '/' .
                ($request->getParsedBody()['city'] ?? null);
        }
        $this->container->get('conf')->set('general.timezone', $timezone);

        $login = $request->getParsedBody()['setlogin'] ?? null;
        $this->container->get('conf')->set('credentials.login', $login);
        $salt = sha1(uniqid('', true) . '_' . mt_rand());
        $this->container->get('conf')->set('credentials.salt', $salt);
        $this->container->get('conf')
            ->set('credentials.hash', sha1(($request->getParsedBody()['setpassword'] ?? null) . $login . $salt));

        if (!empty($request->getParsedBody()['title'] ?? null)) {
            $this->container->get('conf')->set('general.title', escape($request->getParsedBody()['title'] ?? null));
        } else {
            $this->container->get('conf')->set(
                'general.title',
                t('Shared Bookmarks')
            );
        }

        $this->container->get('conf')
            ->set('translation.language', escape($request->getParsedBody()['language'] ?? null));
        $this->container->get('conf')
            ->set('updates.check_updates', !empty($request->getParsedBody()['updateCheck'] ?? null));
        $this->container->get('conf')->set('api.enabled', !empty($request->getParsedBody()['enableApi'] ?? null));
        $this->container->get('conf')->set(
            'api.secret',
            generate_api_secret(
                $this->container->get('conf')->get('credentials.login'),
                $this->container->get('conf')->get('credentials.salt')
            )
        );
        $this->container->get('conf')->set('general.header_link', $this->container->get('basePath') . '/');

        try {
            // Everything is ok, let's create config file.
            $this->container->get('conf')->write($this->container->get('loginManager')->isLoggedIn());
        } catch (\Exception $e) {
            $this->assignView('message', t('Error while writing config file after configuration update.'));
            $this->assignView('stacktrace', $e->getMessage() . PHP_EOL . $e->getTraceAsString());

            return $this->respondWithTemplate($response, TemplatePage::ERROR);
        }

        $this->container->get('sessionManager')->setSessionParameter(
            SessionManager::KEY_SUCCESS_MESSAGES,
            [t('Shaarli is now configured. Please login and start shaaring your bookmarks!')]
        );

        return $this->redirect($response, '/login');
    }

    protected function checkPermissions(): bool
    {
        // Ensure Shaarli has proper access to its resources
        $errors = ApplicationUtils::checkResourcePermissions($this->container->get('conf'), true);
        if (empty($errors)) {
            return true;
        }

        $message = t('Insufficient permissions:') . PHP_EOL;
        foreach ($errors as $error) {
            $message .= PHP_EOL . $error;
        }

        throw new ResourcePermissionException($message);
    }
}
