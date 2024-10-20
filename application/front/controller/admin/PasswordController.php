<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Front\Exception\OpenShaarliPasswordException;
use Shaarli\Front\Exception\ShaarliFrontException;
use Shaarli\Render\TemplatePage;
use Throwable;

/**
 * Class PasswordController
 *
 * Slim controller used to handle passwords update.
 */
class PasswordController extends ShaarliAdminController
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->assignView(
            'pagetitle',
            t('Change password') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
        );
    }

    /**
     * GET /admin/password - Displays the change password template
     */
    public function index(Request $request, Response $response): Response
    {
        return $this->respondWithTemplate($response, TemplatePage::CHANGE_PASSWORD);
    }

    /**
     * POST /admin/password - Change admin password - existing and new passwords need to be provided.
     */
    public function change(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        if ($this->container->get('conf')->get('security.open_shaarli', false)) {
            throw new OpenShaarliPasswordException();
        }

        $oldPassword = $request->getParsedBody()['oldpassword'] ?? null;
        $newPassword = $request->getParsedBody()['setpassword'] ?? null;

        if (empty($newPassword) || empty($oldPassword)) {
            $this->saveErrorMessage(t('You must provide the current and new password to change it.'));

            return $this->respondWithTemplate($response, TemplatePage::CHANGE_PASSWORD)
                ->withStatus(400);
        }

        // Make sure old password is correct.
        $oldHash = sha1(
            $oldPassword .
            $this->container->get('conf')->get('credentials.login') .
            $this->container->get('conf')->get('credentials.salt')
        );

        if ($oldHash !== $this->container->get('conf')->get('credentials.hash')) {
            $this->saveErrorMessage(t('The old password is not correct.'));

            return $this->respondWithTemplate($response, TemplatePage::CHANGE_PASSWORD)
                ->withStatus(400);
            ;
        }

        // Save new password
        // Salt renders rainbow-tables attacks useless.
        $this->container->get('conf')->set('credentials.salt', sha1(uniqid('', true) . '_' . mt_rand()));
        $this->container->get('conf')->set(
            'credentials.hash',
            sha1(
                $newPassword
                . $this->container->get('conf')->get('credentials.login')
                . $this->container->get('conf')->get('credentials.salt')
            )
        );

        try {
            $this->container->get('conf')->write($this->container->get('loginManager')->isLoggedIn());
        } catch (Throwable $e) {
            throw new ShaarliFrontException($e->getMessage(), 500, $e);
        }

        $this->saveSuccessMessage(t('Your password has been changed'));

        return $this->respondWithTemplate($response, TemplatePage::CHANGE_PASSWORD);
    }
}
