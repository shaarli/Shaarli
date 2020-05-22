<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Visitor;

use Shaarli\Front\Exception\LoginBannedException;
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
    public function index(Request $request, Response $response): Response
    {
        if ($this->container->loginManager->isLoggedIn()
            || $this->container->conf->get('security.open_shaarli', false)
        ) {
            return $response->withRedirect('./');
        }

        $userCanLogin = $this->container->loginManager->canLogin($request->getServerParams());
        if ($userCanLogin !== true) {
            throw new LoginBannedException();
        }

        if ($request->getParam('username') !== null) {
            $this->assignView('username', escape($request->getParam('username')));
        }

        $this
            ->assignView('returnurl', escape($request->getServerParam('HTTP_REFERER')))
            ->assignView('remember_user_default', $this->container->conf->get('privacy.remember_user_default', true))
            ->assignView('pagetitle', t('Login') .' - '. $this->container->conf->get('general.title', 'Shaarli'))
        ;

        return $response->write($this->render('loginform'));
    }
}
