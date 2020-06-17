<?php

namespace Shaarli\Netscape;

use DateTime;
use DateTimeZone;
use Exception;
use Katzgrau\KLogger\Logger;
use Psr\Log\LogLevel;
use Shaarli\Bookmark\Bookmark;
use Shaarli\Bookmark\BookmarkServiceInterface;
use Shaarli\Config\ConfigManager;
use Shaarli\Formatter\BookmarkFormatter;
use Shaarli\History;
use Shaarli\NetscapeBookmarkParser\NetscapeBookmarkParser;

/**
 * Utilities to import and export bookmarks using the Netscape format
 */
class NetscapeBookmarkUtils
{
    /** @var BookmarkServiceInterface */
    protected $bookmarkService;

    /** @var ConfigManager */
    protected $conf;

    /** @var History */
    protected $history;

    public function __construct(BookmarkServiceInterface $bookmarkService, ConfigManager $conf, History $history)
    {
        $this->bookmarkService = $bookmarkService;
        $this->conf = $conf;
        $this->history = $history;
    }

    /**
     * Filters bookmarks and adds Netscape-formatted fields
     *
     * Added fields:
     * - timestamp  link addition date, using the Unix epoch format
     * - taglist    comma-separated tag list
     *
     * @param BookmarkFormatter        $formatter       instance
     * @param string                   $selection       Which bookmarks to export: (all|private|public)
     * @param bool                     $prependNoteUrl  Prepend note permalinks with the server's URL
     * @param string                   $indexUrl        Absolute URL of the Shaarli index page
     *
     * @return array The bookmarks to be exported, with additional fields
     *
     * @throws Exception Invalid export selection
     */
    public function filterAndFormat(
        $formatter,
        $selection,
        $prependNoteUrl,
        $indexUrl
    ) {
        // see tpl/export.html for possible values
        if (!in_array($selection, array('all', 'public', 'private'))) {
            throw new Exception(t('Invalid export selection:') . ' "' . $selection . '"');
        }

        $bookmarkLinks = array();
        foreach ($this->bookmarkService->search([], $selection) as $bookmark) {
            $link = $formatter->format($bookmark);
            $link['taglist'] = implode(',', $bookmark->getTags());
            if ($bookmark->isNote() && $prependNoteUrl) {
                $link['url'] = $indexUrl . $link['url'];
            }

            $bookmarkLinks[] = $link;
        }

        return $bookmarkLinks;
    }

    /**
     * Imports Web bookmarks from an uploaded Netscape bookmark dump
     *
     * @param array                    $post            Server $_POST parameters
     * @param array                    $files           Server $_FILES parameters
     *
     * @return string Summary of the bookmark import status
     */
    public function import($post, $files)
    {
        $start = time();
        $filename = $files['filetoupload']['name'];
        $filesize = $files['filetoupload']['size'];
        $data = file_get_contents($files['filetoupload']['tmp_name']);

        if (preg_match('/<!DOCTYPE NETSCAPE-Bookmark-file-1>/i', $data) === 0) {
            return self::importStatus($filename, $filesize);
        }

        // Overwrite existing bookmarks?
        $overwrite = !empty($post['overwrite']);

        // Add tags to all imported bookmarks?
        if (empty($post['default_tags'])) {
            $defaultTags = array();
        } else {
            $defaultTags = preg_split(
                '/[\s,]+/',
                escape($post['default_tags'])
            );
        }

        // bookmarks are imported as public by default
        $defaultPrivacy = 0;

        $parser = new NetscapeBookmarkParser(
            true,                           // nested tag support
            $defaultTags,                   // additional user-specified tags
            strval(1 - $defaultPrivacy),    // defaultPub = 1 - defaultPrivacy
            $this->conf->get('resource.data_dir') // log path, will be overridden
        );
        $logger = new Logger(
            $this->conf->get('resource.data_dir'),
            !$this->conf->get('dev.debug') ? LogLevel::INFO : LogLevel::DEBUG,
            [
                'prefix' => 'import.',
                'extension' => 'log',
            ]
        );
        $parser->setLogger($logger);
        $bookmarks = $parser->parseString($data);

        $importCount = 0;
        $overwriteCount = 0;
        $skipCount = 0;

        foreach ($bookmarks as $bkm) {
            $private = $defaultPrivacy;
            if (empty($post['privacy']) || $post['privacy'] == 'default') {
                // use value from the imported file
                $private = $bkm['pub'] == '1' ? 0 : 1;
            } elseif ($post['privacy'] == 'private') {
                // all imported bookmarks are private
                $private = 1;
            } elseif ($post['privacy'] == 'public') {
                // all imported bookmarks are public
                $private = 0;
            }

            $link = $this->bookmarkService->findByUrl($bkm['uri']);
            $existingLink = $link !== null;
            if (! $existingLink) {
                $link = new Bookmark();
            }

            if ($existingLink !== false) {
                if ($overwrite === false) {
                    // Do not overwrite an existing link
                    $skipCount++;
                    continue;
                }

                $link->setUpdated(new DateTime());
                $overwriteCount++;
            } else {
                $newLinkDate = new DateTime('@' . strval($bkm['time']));
                $newLinkDate->setTimezone(new DateTimeZone(date_default_timezone_get()));
                $link->setCreated($newLinkDate);
            }

            $link->setTitle($bkm['title']);
            $link->setUrl($bkm['uri'], $this->conf->get('security.allowed_protocols'));
            $link->setDescription($bkm['note']);
            $link->setPrivate($private);
            $link->setTagsString($bkm['tags']);

            $this->bookmarkService->addOrSet($link, false);
            $importCount++;
        }

        $this->bookmarkService->save();
        $this->history->importLinks();

        $duration = time() - $start;

        return $this->importStatus(
            $filename,
            $filesize,
            $importCount,
            $overwriteCount,
            $skipCount,
            $duration
        );
    }

    /**
     * Generates an import status summary
     *
     * @param string $filename       name of the file to import
     * @param int    $filesize       size of the file to import
     * @param int    $importCount    how many bookmarks were imported
     * @param int    $overwriteCount how many bookmarks were overwritten
     * @param int    $skipCount      how many bookmarks were skipped
     * @param int    $duration       how many seconds did the import take
     *
     * @return string Summary of the bookmark import status
     */
    protected function importStatus(
        $filename,
        $filesize,
        $importCount = 0,
        $overwriteCount = 0,
        $skipCount = 0,
        $duration = 0
    ) {
        $status = sprintf(t('File %s (%d bytes) '), $filename, $filesize);
        if ($importCount == 0 && $overwriteCount == 0 && $skipCount == 0) {
            $status .= t('has an unknown file format. Nothing was imported.');
        } else {
            $status .= vsprintf(
                t(
                    'was successfully processed in %d seconds: '
                    . '%d bookmarks imported, %d bookmarks overwritten, %d bookmarks skipped.'
                ),
                [$duration, $importCount, $overwriteCount, $skipCount]
            );
        }
        return $status;
    }
}
