<?php

require_once 'application/api/Api.php';

/**
 * Class DummyApi
 *
 * An Api extension with a single dummy service available.
 */
class DummyApi extends Api
{
    /**
     * @var array Overrides Api allowed methods.
     */
    protected static $allowedMethod = array('getDummy');

    /**
     * Dummy API service for tests purpose.
     *
     * @param array  $queryParams Request parameters.
     * @param array  $pathParams  Path parameters.
     * @param string $body            Request body as a string.
     *
     * @return ApiResponse with code, headers, and body based on given data.
     */
    public function getDummy($queryParams, $pathParams, $body)
    {
        return new ApiResponse(
            666,
            array('header1', 'header2'),
            array(
                'query' => $queryParams,
                'path' => $pathParams,
                'body' => $body,
            )
        );
    }
}
