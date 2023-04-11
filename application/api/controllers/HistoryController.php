<?php

namespace Shaarli\Api\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shaarli\Api\Exceptions\ApiBadParametersException;
use Shaarli\ResponseUtils;

/**
 * Class History
 *
 * REST API Controller: /history
 *
 * @package Shaarli\Api\Controllers
 */
class HistoryController extends ApiController
{
    /**
     * Service providing operation regarding Shaarli datastore and settings.
     *
     * @param Request  $request  Slim request.
     * @param Response $response Slim response.
     *
     * @return Response response.
     *
     * @throws ApiBadParametersException Invalid parameters.
     */
    public function getHistory($request, $response)
    {
        $history = $this->history->getHistory();

        // Return history operations from the {offset}th, starting from {since}.
        $since = \DateTime::createFromFormat(\DateTime::ATOM, $request->getQueryParams()['since'] ?? null);
        $offset = $request->getQueryParams()['offset'] ?? null;
        if (empty($offset)) {
            $offset = 0;
        } elseif (ctype_digit($offset)) {
            $offset = (int) $offset;
        } else {
            throw new ApiBadParametersException('Invalid offset');
        }

        // limit parameter is either a number of bookmarks or 'all' for everything.
        $limit = $request->getQueryParams()['limit'] ?? null;
        if (empty($limit)) {
            $limit = count($history);
        } elseif (ctype_digit($limit)) {
            $limit = (int) $limit;
        } else {
            throw new ApiBadParametersException('Invalid limit');
        }

        $out = [];
        $i = 0;
        foreach ($history as $entry) {
            if ((! empty($since) && $entry['datetime'] <= $since) || count($out) >= $limit) {
                break;
            }
            if (++$i > $offset) {
                $out[$i] = $entry;
                $out[$i]['datetime'] = $out[$i]['datetime']->format(\DateTime::ATOM);
            }
        }
        $out = array_values($out);

        return $this->respondWithJson($response, $out, $this->jsonStyle)->withStatus(200);
    }
}
