<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Front\Exception\CantLoginException;
use Shaarli\Front\Exception\LoginBannedException;
use Shaarli\Front\Exception\WrongTokenException;
use Shaarli\Render\TemplatePage;
use Shaarli\Security\CookieManager;
use Shaarli\Security\SessionManager;

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
            $this->checkLoginState($request->getServerParams());
        } catch (CantLoginException $e) {
            return $this->redirect($response, '/');
        }

        if (($request->getQueryParams()['login'] ?? null) !== null) {
            $this->assignView('username', escape($request->getQueryParams()['login'] ?? null));
        }

        $returnUrl = $request->getQueryParams()['returnurl'] ?? $request->getServerParams()['HTTP_REFERER'] ?? null;

        $this->assignView('returnurl', escape($returnUrl))
            ->assignView(
                'remember_user_default',
                $this->container->get('conf')->get('privacy.remember_user_default', true)
            )->assignView(
                'pagetitle',
                t('Login') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
            )
        ;

        return $this->respondWithTemplate($response, TemplatePage::LOGIN);
    }

    /**
     * POST /login - Process login
     */
    public function login(Request $request, Response $response): Response
    {
        if (!$this->container->get('sessionManager')->checkToken($request->getParsedBody()['token'] ?? null)) {
            throw new WrongTokenException();
        }

        try {
            $this->checkLoginState($request->getServerParams());
        } catch (CantLoginException $e) {
            return $this->redirect($response, '/');
        }

        if (
            !$this->container->get('loginManager')->checkCredentials(
                client_ip_id($request->getServerParams()),
                $request->getParsedBody()['login'] ?? null,
                $request->getParsedBody()['password'] ?? null
            )
        ) {
            $this->container->get('loginManager')->handleFailedLogin($request->getServerParams());

            $this->container->get('sessionManager')->setSessionParameter(
                SessionManager::KEY_ERROR_MESSAGES,
                [t('Wrong login/password.')]
            );

            // Call controller directly instead of unnecessary redirection
            return $this->index($request, $response);
        }

        $this->container->get('loginManager')->handleSuccessfulLogin($request->getServerParams());

        $cookiePath = $this->container->get('basePath') . '/';
        $expirationTime = $this->saveLongLastingSession($request, $cookiePath);
        $this->renewUserSession($request->getServerParams()['SERVER_NAME'] ?? null, $cookiePath, $expirationTime);

        // Force referer from given return URL
        $referer = $request->getParsedBody()['returnurl'] ?? null;

        return $this->redirectFromReferer($request, $response, ['login', 'install'], [], null, $referer);
    }

    /**
     * Make sure that the user is allowed to login and/or displaying the login page:
     *   - not already logged in
     *   - not open shaarli
     *   - not banned
     */
    protected function checkLoginState(array $serverParams): bool
    {
        if (
            $this->container->get('loginManager')->isLoggedIn()
            || $this->container->get('conf')->get('security.open_shaarli', false)
        ) {
            throw new CantLoginException();
        }

        if (true !== $this->container->get('loginManager')->canLogin($serverParams)) {
            throw new LoginBannedException();
        }

        return true;
    }

    /**
     * @return int Session duration in seconds
     */
    protected function saveLongLastingSession(Request $request, string $cookiePath): int
    {
        if (empty($request->getParsedBody()['longlastingsession']) ?? null) {
            // Standard session expiration (=when browser closes)
            $expirationTime = 0;
        } else {
            // Keep the session cookie even after the browser closes
            $this->container->get('sessionManager')->setStaySignedIn(true);
            $expirationTime = $this->container->get('sessionManager')->extendSession();
        }

        $this->container->get('cookieManager')->setCookieParameter(
            CookieManager::STAY_SIGNED_IN,
            $this->container->get('loginManager')->getStaySignedInToken(),
            $expirationTime,
            $cookiePath
        );

        return $expirationTime;
    }

    protected function renewUserSession(string $serverName, string $cookiePath, int $expirationTime): void
    {
        // Send cookie with the new expiration date to the browser
        $this->container->get('sessionManager')->destroy();
        $this->container->get('sessionManager')->cookieParameters(
            $expirationTime,
            $cookiePath,
            $serverName
        );
        $this->container->get('sessionManager')->start();
        $this->container->get('sessionManager')->regenerateId(true);
    }
}
