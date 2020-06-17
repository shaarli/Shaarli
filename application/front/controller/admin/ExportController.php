<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use DateTime;
use Shaarli\Bookmark\Bookmark;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class ExportController
 *
 * Slim controller used to display Shaarli data export page,
 * and process the bookmarks export as a Netscape Bookmarks file.
 */
class ExportController extends ShaarliAdminController
{
    /**
     * GET /admin/export - Display export page
     */
    public function index(Request $request, Response $response): Response
    {
        $this->assignView('pagetitle', t('Export') .' - '. $this->container->conf->get('general.title', 'Shaarli'));

        return $response->write($this->render('export'));
    }

    /**
     * POST /admin/export - Process export, and serve download file named
     *                      bookmarks_(all|private|public)_datetime.html
     */
    public function export(Request $request, Response $response): Response
    {
        $this->checkToken($request);

        $selection = $request->getParam('selection');

        if (empty($selection)) {
            $this->saveErrorMessage(t('Please select an export mode.'));

            return $this->redirect($response, '/admin/export');
        }

        $prependNoteUrl = filter_var($request->getParam('prepend_note_url') ?? false, FILTER_VALIDATE_BOOLEAN);

        try {
            $formatter = $this->container->formatterFactory->getFormatter('raw');

            $this->assignView(
                'links',
                $this->container->netscapeBookmarkUtils->filterAndFormat(
                    $formatter,
                    $selection,
                    $prependNoteUrl,
                    index_url($this->container->environment)
                )
            );
        } catch (\Exception $exc) {
            $this->saveErrorMessage($exc->getMessage());

            return $this->redirect($response, '/admin/export');
        }

        $now = new DateTime();
        $response = $response->withHeader('Content-Type', 'text/html; charset=utf-8');
        $response = $response->withHeader(
            'Content-disposition',
            'attachment; filename=bookmarks_'.$selection.'_'.$now->format(Bookmark::LINK_DATE_FORMAT).'.html'
        );

        $this->assignView('date', $now->format(DateTime::RFC822));
        $this->assignView('eol', PHP_EOL);
        $this->assignView('selection', $selection);

        return $response->write($this->render('export.bookmarks'));
    }
}
