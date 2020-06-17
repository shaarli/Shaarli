<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\UploadedFileInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ImportController
 *
 * Slim controller used to display Shaarli data import page,
 * and import bookmarks from Netscape Bookmarks file.
 */
class ImportController extends ShaarliAdminController
{
    /**
     * GET /admin/import - Display import page
     */
    public function index(Request $request, Response $response): Response
    {
        $this->assignView(
            'maxfilesize',
            get_max_upload_size(
                ini_get('post_max_size'),
                ini_get('upload_max_filesize'),
                false
            )
        );
        $this->assignView(
            'maxfilesizeHuman',
            get_max_upload_size(
                ini_get('post_max_size'),
                ini_get('upload_max_filesize'),
                true
            )
        );
        $this->assignView('pagetitle', t('Import') .' - '. $this->container->conf->get('general.title', 'Shaarli'));

        return $response->write($this->render('import'));
    }

    /**
     * POST /admin/import - Process import file provided and create bookmarks
     */
    public function import(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $file = ($request->getUploadedFiles() ?? [])['filetoupload'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            $this->saveErrorMessage(t('No import file provided.'));

            return $this->redirect($response, '/admin/import');
        }


        // Import bookmarks from an uploaded file
        if (0 === $file->getSize()) {
            // The file is too big or some form field may be missing.
            $msg = sprintf(
                t(
                    'The file you are trying to upload is probably bigger than what this webserver can accept'
                    .' (%s). Please upload in smaller chunks.'
                ),
                get_max_upload_size(ini_get('post_max_size'), ini_get('upload_max_filesize'))
            );
            $this->saveErrorMessage($msg);

            return $this->redirect($response, '/admin/import');
        }

        $status = $this->container->netscapeBookmarkUtils->import($request->getParams(), $file);

        $this->saveSuccessMessage($status);

        return $this->redirect($response, '/admin/import');
    }
}
