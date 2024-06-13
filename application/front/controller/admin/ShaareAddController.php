<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Formatter\BookmarkMarkdownFormatter;
use Shaarli\Render\TemplatePage;

class ShaareAddController extends ShaarliAdminController
{
    /**
     * GET /admin/add-shaare - Displays the form used to create a new bookmark from an URL
     */
    public function addShaare(Request $request, Response $response): Response
    {
        $tags = $this->container->get('bookmarkService')->bookmarksCountPerTag();
        if ($this->container->get('conf')->get('formatter') === 'markdown') {
            $tags[BookmarkMarkdownFormatter::NO_MD_TAG] = 1;
        }

        $this->assignView(
            'pagetitle',
            t('Shaare a new link') . ' - ' . $this->container->get('conf')->get('general.title', 'Shaarli')
        );
        $this->assignView('tags', $tags);
        $this->assignView('default_private_links', $this->container->get('conf')
            ->get('privacy.default_private_links', false));
        $this->assignView('async_metadata', $this->container->get('conf')->get('general.enable_async_metadata', true));

        return $this->respondWithTemplate($response, TemplatePage::ADDLINK);
    }
}
