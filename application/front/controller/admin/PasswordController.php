<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Shaarli\Container\ShaarliContainer;
use Shaarli\Front\Exception\OpenShaarliPasswordException;
use Shaarli\Front\Exception\ShaarliFrontException;
use Slim\Http\Request;
use Slim\Http\Response;
use Throwable;

/**
 * Class PasswordController
 *
 * Slim controller used to handle passwords update.
 */
class PasswordController extends ShaarliAdminController
{
    public function __construct(ShaarliContainer $container)
    {
        parent::__construct($container);

        $this->assignView(
            'pagetitle',
            t('Change password') .' - '. $this->container->conf->get('general.title', 'Shaarli')
        );
    }

    /**
     * GET /password - Displays the change password template
     */
    public function index(Request $request, Response $response): Response
    {
        return $response->write($this->render('changepassword'));
    }

    /**
     * POST /password - Change admin password - existing and new passwords need to be provided.
     */
    public function change(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        if ($this->container->conf->get('security.open_shaarli', false)) {
            throw new OpenShaarliPasswordException();
        }

        $oldPassword = $request->getParam('oldpassword');
        $newPassword = $request->getParam('setpassword');

        if (empty($newPassword) || empty($oldPassword)) {
            $this->saveErrorMessage(t('You must provide the current and new password to change it.'));

            return $response
                ->withStatus(400)
                ->write($this->render('changepassword'))
            ;
        }

        // Make sure old password is correct.
        $oldHash = sha1(
            $oldPassword .
            $this->container->conf->get('credentials.login') .
            $this->container->conf->get('credentials.salt')
        );

        if ($oldHash !== $this->container->conf->get('credentials.hash')) {
            $this->saveErrorMessage(t('The old password is not correct.'));

            return $response
                ->withStatus(400)
                ->write($this->render('changepassword'))
            ;
        }

        // Save new password
        // Salt renders rainbow-tables attacks useless.
        $this->container->conf->set('credentials.salt', sha1(uniqid('', true) .'_'. mt_rand()));
        $this->container->conf->set(
            'credentials.hash',
            sha1(
                $newPassword
                . $this->container->conf->get('credentials.login')
                . $this->container->conf->get('credentials.salt')
            )
        );

        try {
            $this->container->conf->write($this->container->loginManager->isLoggedIn());
        } catch (Throwable $e) {
            throw new ShaarliFrontException($e->getMessage(), 500, $e);
        }

        $this->saveSuccessMessage(t('Your password has been changed'));

        return $response->write($this->render('changepassword'));
    }
}
