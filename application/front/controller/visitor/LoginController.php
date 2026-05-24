<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Front\Exception\CantLoginException;
use Shaarli\Front\Exception\LoginBannedException;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Render\TemplatePage;
use Shaarli\Security\CookieManager;
use Shaarli\Security\SessionManager;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class LoginController
 *
 * Slim controller used to render the login page.
 *
 * The login page is not available if the user is banned
 * or if open shaarli setting is enabled.
 */
class LoginController extends ShaarliVisitorController
{
    /**
     * GET /login - Display the login page.
     */
    public function index(Request $request, Response $response): Response
    {
        try {
            $this->checkLoginState();
        } catch (CantLoginException $e) {
            return $this->redirect($response, '/');
        }

        if ($request->getParam('login') !== null) {
            $this->assignView('username', escape($request->getParam('login')));
        }

        $returnUrl = $request->getParam('returnurl') ?? $this->container->environment['HTTP_REFERER'] ?? null;

        $this
            ->assignView('returnurl', escape($returnUrl))
            ->assignView('remember_user_default', $this->container->conf->get('privacy.remember_user_default', true))
            ->assignView('pagetitle', t('Login') . ' - ' . $this->container->conf->get('general.title', 'Shaarli'))
        ;

        return $response->write($this->render(TemplatePage::LOGIN));
    }

    /**
     * POST /login - Process login
     */
    public function login(Request $request, Response $response): Response
    {
        if (!$this->container->sessionManager->checkToken($request->getParam('token'))) {
            throw new WrongTokenException();
        }

        try {
            $this->checkLoginState();
        } catch (CantLoginException $e) {
            return $this->redirect($response, '/');
        }

        if (
            !$this->container->loginManager->checkCredentials(
                client_ip_id($this->container->environment),
                $request->getParam('login'),
                $request->getParam('password')
            )
        ) {
            $this->container->loginManager->handleFailedLogin($this->container->environment);

            $this->container->sessionManager->setSessionParameter(
                SessionManager::KEY_ERROR_MESSAGES,
                [t('Wrong login/password.')]
            );

            // Call controller directly instead of unnecessary redirection
            return $this->index($request, $response);
        }

        $this->container->loginManager->handleSuccessfulLogin($this->container->environment);

        $cookiePath = $this->container->basePath . '/';
        $expirationTime = $this->saveLongLastingSession($request, $cookiePath);
        $this->renewUserSession($cookiePath, $expirationTime);

        // Force referer from given return URL
        $this->container->environment['HTTP_REFERER'] = $request->getParam('returnurl');

        return $this->redirectFromReferer($request, $response, ['login', 'install']);
    }

    /**
     * Make sure that the user is allowed to login and/or displaying the login page:
     *   - not already logged in
     *   - not open shaarli
     *   - not banned
     */
    protected function checkLoginState(): bool
    {
        if (
            $this->container->loginManager->isLoggedIn()
            || $this->container->conf->get('security.open_shaarli', false)
        ) {
            throw new CantLoginException();
        }

        if (true !== $this->container->loginManager->canLogin($this->container->environment)) {
            throw new LoginBannedException();
        }

        return true;
    }

    /**
     * @return int Session duration in seconds
     */
    protected function saveLongLastingSession(Request $request, string $cookiePath): int
    {
        if (empty($request->getParam('longlastingsession'))) {
            // Standard session expiration (=when browser closes)
            $expirationTime = 0;
        } else {
            // Keep the session cookie even after the browser closes
            $this->container->sessionManager->setStaySignedIn(true);
            $expirationTime = $this->container->sessionManager->extendSession();
        }

        $this->container->cookieManager->setCookieParameter(
            CookieManager::STAY_SIGNED_IN,
            $this->container->loginManager->getStaySignedInToken(),
            $expirationTime,
            $cookiePath
        );

        return $expirationTime;
    }

    protected function renewUserSession(string $cookiePath, int $expirationTime): void
    {
        // Send cookie with the new expiration date to the browser
        $this->container->sessionManager->cookieParameters(
            $expirationTime,
            $cookiePath,
            $this->container->environment['SERVER_NAME']
        );
    }
}
