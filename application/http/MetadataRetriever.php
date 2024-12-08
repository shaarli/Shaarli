<?php

declare(strict_types=1);

namespace Shaarli\Http;

use Shaarli\Config\ConfigManager;

/**
 * HTTP Tool used to extract metadata from external URL (title, description, etc.).
 */
class MetadataRetriever
{
    /** @var ConfigManager */
    protected $conf;

    /** @var HttpAccess */
    protected $httpAccess;

    public function __construct(ConfigManager $conf, HttpAccess $httpAccess)
    {
        $this->conf = $conf;
        $this->httpAccess = $httpAccess;
    }

    /**
     * Retrieve metadata for given URL.
     *
     * @return array [
     *                  'title' => <remote title>,
     *                  'description' => <remote description>,
     *                  'tags' => <remote keywords>,
     *               ]
     */
    public function retrieve(string $url): array
    {
        $charset = null;
        $title = null;
        $description = null;
        $tags = null;

        // Short timeout to keep the application responsive
        // The callback will fill $charset and $title with data from the downloaded page.
        $this->httpAccess->getHttpResponse(
            $url,
            $this->conf->get('general.download_timeout', 30),
            $this->conf->get('general.download_max_size', 4194304),
            $this->httpAccess->getCurlHeaderCallback($charset),
            $this->httpAccess->getCurlDownloadCallback(
                $charset,
                $title,
                $description,
                $tags,
                $this->conf->get('general.retrieve_description'),
                $this->conf->get('general.tags_separator', ' ')
            )
        );

        if (!empty($title) && strtolower($charset) !== 'utf-8') {
            $title = mb_convert_encoding($title, 'utf-8', $charset);
        }
        if (!empty($description) && strtolower($charset) !== 'utf-8') {
            $description = mb_convert_encoding($description, 'utf-8', $charset);
        }
        if (!empty($tags) && strtolower($charset) !== 'utf-8') {
            $tags = mb_convert_encoding($tags, 'utf-8', $charset);
        }

        return array_map([$this, 'cleanMetadata'], [
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
        ]);
    }

    protected function cleanMetadata($data): ?string
    {
        return !is_string($data) || empty(trim($data)) ? null : trim($data);
    }
}
