<?php

namespace Shaarli\Bookmark;

use Shaarli\TestCase;

/**
 * Class LinkUtilsTest.
 */
class LinkUtilsTest extends TestCase
{
    /**
     * Test html_extract_title() when the title is found.
     */
    public function testHtmlExtractExistentTitle()
    {
        $title = 'Read me please.';
        $html = '<html><meta>stuff</meta><title>' . $title . '</title></html>';
        $this->assertEquals($title, html_extract_title($html));
        $html = '<html><title>' . $title . '</title>blabla<title>another</title></html>';
        $this->assertEquals($title, html_extract_title($html));
    }

    /**
     * Test html_extract_title() when the title is not found.
     */
    public function testHtmlExtractNonExistentTitle()
    {
        $html = '<html><meta>stuff</meta></html>';
        $this->assertFalse(html_extract_title($html));
    }

    /**
     * Test headers_extract_charset() when the charset is found.
     */
    public function testHeadersExtractExistentCharset()
    {
        $charset = 'x-MacCroatian';
        $headers = 'text/html; charset=' . $charset;
        $this->assertEquals(strtolower($charset), header_extract_charset($headers));
    }

    /**
     * Test headers_extract_charset() when the charset is found with odd quotes.
     */
    public function testHeadersExtractExistentCharsetWithQuotes()
    {
        $charset = 'x-MacCroatian';
        $headers = 'text/html; charset="' . $charset . '"otherstuff="test"';
        $this->assertEquals(strtolower($charset), header_extract_charset($headers));

        $headers = 'text/html; charset=\'' . $charset . '\'otherstuff="test"';
        $this->assertEquals(strtolower($charset), header_extract_charset($headers));
    }

    /**
     * Test headers_extract_charset() when the charset is not found.
     */
    public function testHeadersExtractNonExistentCharset()
    {
        $headers = '';
        $this->assertFalse(header_extract_charset($headers));

        $headers = 'text/html';
        $this->assertFalse(header_extract_charset($headers));
    }

    /**
     * Test html_extract_charset() when the charset is found.
     */
    public function testHtmlExtractExistentCharset()
    {
        $charset = 'x-MacCroatian';
        $html = '<html><meta>stuff2</meta><meta charset="' . $charset . '"/></html>';
        $this->assertEquals(strtolower($charset), html_extract_charset($html));
    }

    /**
     * Test html_extract_charset() when the charset is not found.
     */
    public function testHtmlExtractNonExistentCharset()
    {
        $html = '<html><meta>stuff</meta></html>';
        $this->assertFalse(html_extract_charset($html));
        $html = '<html><meta>stuff</meta><meta charset=""/></html>';
        $this->assertFalse(html_extract_charset($html));
    }

    /**
     * Test html_extract_tag() when the tag <meta name= is found.
     */
    public function testHtmlExtractExistentNameTag()
    {
        $description = 'Bob and Alice share cookies.';

        // Simple one line
        $html = '<html><meta>stuff2</meta><meta name="description" content="' . $description . '"/></html>';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // Simple OpenGraph
        $html = '<meta property="og:description" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // Simple reversed OpenGraph
        $html = '<meta content="' . $description . '" property="og:description">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // ItemProp OpenGraph
        $html = '<meta itemprop="og:description" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph without quotes
        $html = '<meta property=og:description content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph reversed without quotes
        $html = '<meta content="' . $description . '" property=og:description>';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph with noise
        $html = '<meta tag1="content1" property="og:description" tag2="content2" content="' .
            $description . '" tag3="content3">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph reversed with noise
        $html = '<meta tag1="content1" content="' . $description . '" ' .
            'tag3="content3" tag2="content2" property="og:description">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph multiple properties start
        $html = '<meta property="unrelated og:description" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph multiple properties end
        $html = '<meta property="og:description unrelated" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph multiple properties both end
        $html = '<meta property="og:unrelated1 og:description og:unrelated2" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph multiple properties both end with noise
        $html = '<meta tag1="content1" property="og:unrelated1 og:description og:unrelated2" ' .
            'tag2="content2" content="' . $description . '" tag3="content3">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph reversed multiple properties start
        $html = '<meta content="' . $description . '" property="unrelated og:description">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph reversed multiple properties end
        $html = '<meta content="' . $description . '" property="og:description unrelated">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph reversed multiple properties both end
        $html = '<meta content="' . $description . '" property="og:unrelated1 og:description og:unrelated2">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // OpenGraph reversed multiple properties both end with noise
        $html = '<meta tag1="content1" content="' . $description . '" tag2="content2" ' .
            'property="og:unrelated1 og:description og:unrelated2" tag3="content3">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        // Suggestion from #1375
        $html = '<meta property="og:description" name="description" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));
    }

    /**
     * Test html_extract_tag() with double quoted content containing single quote, and the opposite.
     */
    public function testHtmlExtractExistentNameTagWithMixedQuotes(): void
    {
        $description = 'Bob and Alice share M&M\'s.';

        $html = '<meta property="og:description" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        $html = '<meta tag1="content1" property="og:unrelated1 og:description og:unrelated2" ' .
            'tag2="content2" content="' . $description . '" tag3="content3">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        $html = '<meta property="og:description" name="description" content="' . $description . '">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        $description = 'Bob and Alice share "cookies".';

        $html = '<meta property="og:description" content=\'' . $description . '\'>';
        $this->assertEquals($description, html_extract_tag('description', $html));

        $html = '<meta tag1="content1" property="og:unrelated1 og:description og:unrelated2" ' .
            'tag2="content2" content=\'' . $description . '\' tag3="content3">';
        $this->assertEquals($description, html_extract_tag('description', $html));

        $html = '<meta property="og:description" name="description" content=\'' . $description . '\'>';
        $this->assertEquals($description, html_extract_tag('description', $html));
    }

    /**
     * Test html_extract_tag() when the tag <meta name= is not found.
     */
    public function testHtmlExtractNonExistentNameTag()
    {
        $html = '<html><meta>stuff2</meta><meta name="image" content="img"/></html>';
        $this->assertFalse(html_extract_tag('description', $html));

        // Partial meta tag
        $html = '<meta content="Brief description">';
        $this->assertFalse(html_extract_tag('description', $html));

        $html = '<meta property="og:description">';
        $this->assertFalse(html_extract_tag('description', $html));

        $html = '<meta tag1="content1" property="og:description">';
        $this->assertFalse(html_extract_tag('description', $html));

        $html = '<meta property="og:description" tag1="content1">';
        $this->assertFalse(html_extract_tag('description', $html));

        $html = '<meta tag1="content1" content="Brief description">';
        $this->assertFalse(html_extract_tag('description', $html));

        $html = '<meta content="Brief description" tag1="content1">';
        $this->assertFalse(html_extract_tag('description', $html));
    }

    /**
     * Test html_extract_tag() when the tag <meta property="og: is found.
     */
    public function testHtmlExtractExistentOgTag()
    {
        $description = 'Bob and Alice share cookies.';
        $html = '<html><meta>stuff2</meta><meta property="og:description" content="' . $description . '"/></html>';
        $this->assertEquals($description, html_extract_tag('description', $html));
    }

    /**
     * Test html_extract_tag() when the tag <meta property="og: is not found.
     */
    public function testHtmlExtractNonExistentOgTag()
    {
        $html = '<html><meta>stuff2</meta><meta name="image" content="img"/></html>';
        $this->assertFalse(html_extract_tag('description', $html));
    }

    public function testHtmlExtractDescriptionFromGoogleRealCase(): void
    {
        $html = 'id="gsr"><meta content="Fêtes de fin d\'année" property="twitter:title"><meta ' .
                'content="Bonnes fêtes de fin d\'année ! #GoogleDoodle" property="twitter:description">' .
                '<meta content="Bonnes fêtes de fin d\'année ! #GoogleDoodle" property="og:description">' .
                '<meta content="summary_large_image" property="twitter:card"><meta co'
        ;
        $this->assertSame('Bonnes fêtes de fin d\'année ! #GoogleDoodle', html_extract_tag('description', $html));
    }

    /**
     * Test the header callback with valid value
     */
    public function testCurlHeaderCallbackOk(): void
    {
        $callback = get_curl_header_callback($charset, 'ut_curl_getinfo_ok');
        $data = [
            'HTTP/1.1 200 OK',
            'Server: GitHub.com',
            'Date: Sat, 28 Oct 2017 12:01:33 GMT',
            'Content-Type: text/html; charset=utf-8',
            'Status: 200 OK',
        ];

        foreach ($data as $chunk) {
            static::assertIsInt($callback(null, $chunk));
        }

        static::assertSame('utf-8', $charset);
    }

    /**
     * Test the download callback with valid value
     */
    public function testCurlDownloadCallbackOk(): void
    {
        $charset = 'utf-8';
        $callback = get_curl_download_callback(
            $charset,
            $title,
            $desc,
            $keywords,
            false,
            ' '
        );

        $data = [
            'th=device-width">'
                . '<title>Refactoring · GitHub</title>'
                . '<link rel="search" type="application/opensea',
            '<title>ignored</title>'
                . '<meta name="description" content="desc" />'
                . '<meta name="keywords" content="key1,key2" />',
        ];

        foreach ($data as $chunk) {
            static::assertSame(strlen($chunk), $callback(null, $chunk));
        }

        static::assertSame('utf-8', $charset);
        static::assertSame('Refactoring · GitHub', $title);
        static::assertEmpty($desc);
        static::assertEmpty($keywords);
    }

    /**
     * Test the header callback with valid value
     */
    public function testCurlHeaderCallbackNoCharset(): void
    {
        $callback = get_curl_header_callback($charset, 'ut_curl_getinfo_no_charset');
        $data = [
            'HTTP/1.1 200 OK',
        ];

        foreach ($data as $chunk) {
            static::assertSame(strlen($chunk), $callback(null, $chunk));
        }

        static::assertFalse($charset);
    }

    /**
     * Test the download callback with valid values and no charset
     */
    public function testCurlDownloadCallbackOkNoCharset(): void
    {
        $charset = null;
        $callback = get_curl_download_callback(
            $charset,
            $title,
            $desc,
            $keywords,
            false,
            ' '
        );

        $data = [
            'end' => 'th=device-width">'
                . '<title>Refactoring · GitHub</title>'
                . '<link rel="search" type="application/opensea',
            '<title>ignored</title>'
            . '<meta name="description" content="desc" />'
            . '<meta name="keywords" content="key1,key2" />',
        ];

        foreach ($data as $chunk) {
            static::assertSame(strlen($chunk), $callback(null, $chunk));
        }

        $this->assertEmpty($charset);
        $this->assertEquals('Refactoring · GitHub', $title);
        $this->assertEmpty($desc);
        $this->assertEmpty($keywords);
    }

    /**
     * Test the download callback with valid values and no charset
     */
    public function testCurlDownloadCallbackOkHtmlCharset(): void
    {
        $charset = null;
        $callback = get_curl_download_callback(
            $charset,
            $title,
            $desc,
            $keywords,
            false,
            ' '
        );

        $data = [
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />',
            'end' => 'th=device-width">'
                . '<title>Refactoring · GitHub</title>'
                . '<link rel="search" type="application/opensea',
            '<title>ignored</title>'
            . '<meta name="description" content="desc" />'
            . '<meta name="keywords" content="key1,key2" />',
        ];
        foreach ($data as $chunk) {
            static::assertSame(strlen($chunk), $callback(null, $chunk));
        }

        $this->assertEquals('utf-8', $charset);
        $this->assertEquals('Refactoring · GitHub', $title);
        $this->assertEmpty($desc);
        $this->assertEmpty($keywords);
    }

    /**
     * Test the download callback with valid values and no title
     */
    public function testCurlDownloadCallbackOkNoTitle(): void
    {
        $charset = 'utf-8';
        $callback = get_curl_download_callback(
            $charset,
            $title,
            $desc,
            $keywords,
            false,
            ' '
        );

        $data = [
            'end' => 'th=device-width">Refactoring · GitHub<link rel="search" type="application/opensea',
            'ignored',
        ];

        foreach ($data as $chunk) {
            static::assertSame(strlen($chunk), $callback(null, $chunk));
        }

        $this->assertEquals('utf-8', $charset);
        $this->assertEmpty($title);
        $this->assertEmpty($desc);
        $this->assertEmpty($keywords);
    }

    /**
     * Test the header callback with an invalid content type.
     */
    public function testCurlHeaderCallbackInvalidContentType(): void
    {
        $callback = get_curl_header_callback($charset, 'ut_curl_getinfo_ct_ko');
        $data = [
            'HTTP/1.1 200 OK',
        ];

        static::assertFalse($callback(null, $data[0]));
        static::assertNull($charset);
    }

    /**
     * Test the header callback with an invalid response code.
     */
    public function testCurlHeaderCallbackInvalidResponseCode(): void
    {
        $callback = get_curl_header_callback($charset, 'ut_curl_getinfo_rc_ko');

        static::assertFalse($callback(null, ''));
        static::assertNull($charset);
    }

    /**
     * Test the header callback with an invalid content type and response code.
     */
    public function testCurlHeaderCallbackInvalidContentTypeAndResponseCode(): void
    {
        $callback = get_curl_header_callback($charset, 'ut_curl_getinfo_rs_ct_ko');

        static::assertFalse($callback(null, ''));
        static::assertNull($charset);
    }

    /**
     * Test the download callback with valid value, and retrieve_description option enabled.
     */
    public function testCurlDownloadCallbackOkWithDesc(): void
    {
        $charset = 'utf-8';
        $callback = get_curl_download_callback(
            $charset,
            $title,
            $desc,
            $keywords,
            true,
            ' '
        );
        $data = [
            'th=device-width">'
                . '<title>Refactoring · GitHub</title>'
                . '<link rel="search" type="application/opensea',
            'end' => '<title>ignored</title>'
            . '<meta name="description" content="link desc" />'
            . '<meta name="keywords" content="key1,key2" />',
        ];

        foreach ($data as $chunk) {
            static::assertSame(strlen($chunk), $callback(null, $chunk));
        }

        $this->assertEquals('utf-8', $charset);
        $this->assertEquals('Refactoring · GitHub', $title);
        $this->assertEquals('link desc', $desc);
        $this->assertEquals('key1 key2', $keywords);
    }

    /**
     * Test the download callback with valid value, and retrieve_description option enabled,
     * but no desc or keyword defined in the page.
     */
    public function testCurlDownloadCallbackOkWithDescNotFound(): void
    {
        $charset = 'utf-8';
        $callback = get_curl_download_callback(
            $charset,
            $title,
            $desc,
            $keywords,
            true,
            'ut_curl_getinfo_ok'
        );
        $data = [
            'th=device-width">'
                . '<title>Refactoring · GitHub</title>'
                . '<link rel="search" type="application/opensea',
            'end' => '<title>ignored</title>',
        ];

        foreach ($data as $chunk) {
            static::assertSame(strlen($chunk), $callback(null, $chunk));
        }

        $this->assertEquals('utf-8', $charset);
        $this->assertEquals('Refactoring · GitHub', $title);
        $this->assertEmpty($desc);
        $this->assertEmpty($keywords);
    }

    /**
     * Test text2clickable.
     */
    public function testText2clickable()
    {
        $text = 'stuff http://hello.there/is=someone#here otherstuff';
        $expectedText = 'stuff <a href="http://hello.there/is=someone#here">'
            . 'http://hello.there/is=someone#here</a> otherstuff';
        $processedText = text2clickable($text);
        $this->assertEquals($expectedText, $processedText);

        $text = 'stuff http://hello.there/is=someone#here(please) otherstuff';
        $expectedText = 'stuff <a href="http://hello.there/is=someone#here(please)">'
            . 'http://hello.there/is=someone#here(please)</a> otherstuff';
        $processedText = text2clickable($text);
        $this->assertEquals($expectedText, $processedText);

        $text = 'stuff http://hello.there/is=someone#here(please)&no otherstuff';
        $text = 'stuff http://hello.there/is=someone#here(please)&no otherstuff';
        $expectedText = 'stuff <a href="http://hello.there/is=someone#here(please)&no">'
            . 'http://hello.there/is=someone#here(please)&no</a> otherstuff';
        $processedText = text2clickable($text);
        $this->assertEquals($expectedText, $processedText);
    }

    /**
     * Test testSpace2nbsp.
     */
    public function testSpace2nbsp()
    {
        $text = '  Are you   thrilled  by flags   ?' . PHP_EOL . ' Really?';
        $expectedText = '&nbsp; Are you &nbsp; thrilled &nbsp;by flags &nbsp; ?' . PHP_EOL . '&nbsp;Really?';
        $processedText = space2nbsp($text);
        $this->assertEquals($expectedText, $processedText);
    }

    /**
     * Test hashtags auto-link.
     */
    public function testHashtagAutolink()
    {
        $index = 'http://domain.tld/';
        $rawDescription = '#hashtag\n
            # nothashtag\n
            test#nothashtag #hashtag \#nothashtag\n
            test #hashtag #hashtag test #hashtag.test\n
            #hashtag #hashtag-nothashtag #hashtag_hashtag\n
            What is #ашок anyway?\n
            カタカナ #カタカナ」カタカナ\n';
        $autolinkedDescription = hashtag_autolink($rawDescription, $index);

        $this->assertContainsPolyfill($this->getHashtagLink('hashtag', $index), $autolinkedDescription);
        $this->assertNotContainsPolyfill(' #hashtag', $autolinkedDescription);
        $this->assertNotContainsPolyfill('>#nothashtag', $autolinkedDescription);
        $this->assertContainsPolyfill($this->getHashtagLink('ашок', $index), $autolinkedDescription);
        $this->assertContainsPolyfill($this->getHashtagLink('カタカナ', $index), $autolinkedDescription);
        $this->assertContainsPolyfill($this->getHashtagLink('hashtag_hashtag', $index), $autolinkedDescription);
        $this->assertNotContainsPolyfill($this->getHashtagLink('hashtag-nothashtag', $index), $autolinkedDescription);
    }

    /**
     * Test hashtags auto-link without index URL.
     */
    public function testHashtagAutolinkNoIndex()
    {
        $rawDescription = 'blabla #hashtag x#nothashtag';
        $autolinkedDescription = hashtag_autolink($rawDescription);

        $this->assertContainsPolyfill($this->getHashtagLink('hashtag'), $autolinkedDescription);
        $this->assertNotContainsPolyfill(' #hashtag', $autolinkedDescription);
        $this->assertNotContainsPolyfill('>#nothashtag', $autolinkedDescription);
    }

    /**
     * Test is_note with note URLs.
     */
    public function testIsNote()
    {
        $this->assertTrue(is_note('?'));
        $this->assertTrue(is_note('?abcDEf'));
        $this->assertTrue(is_note('?_abcDEf#123'));
    }

    /**
     * Test is_note with non note URLs.
     */
    public function testIsNotNote()
    {
        $this->assertFalse(is_note(''));
        $this->assertFalse(is_note('nope'));
        $this->assertFalse(is_note('https://github.com/shaarli/Shaarli/?hi'));
    }

    /**
     * Test tags_str2array with whitespace separator.
     */
    public function testTagsStr2ArrayWithSpaceSeparator(): void
    {
        $separator = ' ';

        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('tag1 tag2 tag3', $separator));
        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('tag1  tag2     tag3', $separator));
        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('   tag1  tag2     tag3   ', $separator));
        static::assertSame(['tag1@', 'tag2,', '.tag3'], tags_str2array('   tag1@  tag2,     .tag3   ', $separator));
        static::assertSame([], tags_str2array('', $separator));
        static::assertSame([], tags_str2array('   ', $separator));
        static::assertSame([], tags_str2array(null, $separator));
    }

    /**
     * Test tags_str2array with @ separator.
     */
    public function testTagsStr2ArrayWithCharSeparator(): void
    {
        $separator = '@';

        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('tag1@tag2@tag3', $separator));
        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('tag1@@@@tag2@@@@tag3', $separator));
        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('@@@tag1@@@tag2@@@@tag3@@', $separator));
        static::assertSame(
            ['tag1#', 'tag2, and other', '.tag3'],
            tags_str2array('@@@   tag1#     @@@ tag2, and other @@@@.tag3@@', $separator)
        );
        static::assertSame([], tags_str2array('', $separator));
        static::assertSame([], tags_str2array('   ', $separator));
        static::assertSame([], tags_str2array(null, $separator));
    }

    /**
     * Test tags_str2array with / separator.
     */
    public function testTagsStr2ArrayWithRegexDelimiterSeparator(): void
    {
        $separator = '/';

        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('tag1/tag2/tag3', $separator));
        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('tag1////tag2////tag3', $separator));
        static::assertSame(['tag1', 'tag2', 'tag3'], tags_str2array('///tag1///tag2////tag3//', $separator));
        static::assertSame(
            ['tag1#', 'tag2, and other', '.tag3'],
            tags_str2array('///   tag1#     /// tag2, and other ////.tag3//', $separator)
        );
        static::assertSame([], tags_str2array('', $separator));
        static::assertSame([], tags_str2array('   ', $separator));
        static::assertSame([], tags_str2array(null, $separator));
    }

    /**
     * Test tags_array2str with ' ' separator.
     */
    public function testTagsArray2StrWithSpaceSeparator(): void
    {
        $separator = ' ';

        static::assertSame('tag1 tag2 tag3', tags_array2str(['tag1', 'tag2', 'tag3'], $separator));
        static::assertSame('tag1, tag2@ tag3', tags_array2str(['tag1,', 'tag2@', 'tag3'], $separator));
        static::assertSame('tag1 tag2 tag3', tags_array2str(['   tag1   ', 'tag2', 'tag3   '], $separator));
        static::assertSame('tag1 tag2 tag3', tags_array2str(['   tag1   ', ' ', 'tag2', '   ', 'tag3   '], $separator));
        static::assertSame('tag1', tags_array2str(['   tag1   '], $separator));
        static::assertSame('', tags_array2str(['  '], $separator));
        static::assertSame('', tags_array2str([], $separator));
        static::assertSame('', tags_array2str(null, $separator));
    }

    /**
     * Test tags_array2str with @ separator.
     */
    public function testTagsArray2StrWithCharSeparator(): void
    {
        $separator = '@';

        static::assertSame('tag1@tag2@tag3', tags_array2str(['tag1', 'tag2', 'tag3'], $separator));
        static::assertSame('tag1,@tag2@tag3', tags_array2str(['tag1,', 'tag2@', 'tag3'], $separator));
        static::assertSame(
            'tag1@tag2, and other@tag3',
            tags_array2str(['@@@@ tag1@@@', ' @tag2, and other @', 'tag3@@@@'], $separator)
        );
        static::assertSame('tag1@tag2@tag3', tags_array2str(['@@@tag1@@@', '@', 'tag2', '@@@', 'tag3@@@'], $separator));
        static::assertSame('tag1', tags_array2str(['@@@@tag1@@@@'], $separator));
        static::assertSame('', tags_array2str(['@@@'], $separator));
        static::assertSame('', tags_array2str([], $separator));
        static::assertSame('', tags_array2str(null, $separator));
    }

    /**
     * Test tags_array2str with @ separator.
     */
    public function testTagsFilterWithSpaceSeparator(): void
    {
        $separator = ' ';

        static::assertSame(['tag1', 'tag2', 'tag3'], tags_filter(['tag1', 'tag2', 'tag3'], $separator));
        static::assertSame(['tag1,', 'tag2@', 'tag3'], tags_filter(['tag1,', 'tag2@', 'tag3'], $separator));
        static::assertSame(['tag1', 'tag2', 'tag3'], tags_filter(['   tag1   ', 'tag2', 'tag3   '], $separator));
        static::assertSame(
            ['tag1', 'tag2', 'tag3'],
            tags_filter(['   tag1   ', ' ', 'tag2', '   ', 'tag3   '], $separator)
        );
        static::assertSame(['tag1'], tags_filter(['   tag1   '], $separator));
        static::assertSame([], tags_filter(['  '], $separator));
        static::assertSame([], tags_filter([], $separator));
        static::assertSame([], tags_filter(null, $separator));
    }

    /**
     * Test tags_array2str with @ separator.
     */
    public function testTagsArrayFilterWithSpaceSeparator(): void
    {
        $separator = '@';

        static::assertSame(['tag1', 'tag2', 'tag3'], tags_filter(['tag1', 'tag2', 'tag3'], $separator));
        static::assertSame(['tag1,', 'tag2#', 'tag3'], tags_filter(['tag1,', 'tag2#', 'tag3'], $separator));
        static::assertSame(
            ['tag1', 'tag2, and other', 'tag3'],
            tags_filter(['@@@@ tag1@@@', ' @tag2, and other @', 'tag3@@@@'], $separator)
        );
        static::assertSame(
            ['tag1', 'tag2', 'tag3'],
            tags_filter(['@@@tag1@@@', '@', 'tag2', '@@@', 'tag3@@@'], $separator)
        );
        static::assertSame(['tag1'], tags_filter(['@@@@tag1@@@@'], $separator));
        static::assertSame([], tags_filter(['@@@'], $separator));
        static::assertSame([], tags_filter([], $separator));
        static::assertSame([], tags_filter(null, $separator));
    }

    /**
     * Util function to build an hashtag link.
     *
     * @param string $hashtag Hashtag name.
     * @param string $index Index URL.
     *
     * @return string HTML hashtag link.
     */
    private function getHashtagLink($hashtag, $index = '')
    {
        $hashtagLink = '<a href="' . $index . './add-tag/$1" title="Hashtag $1">#$1</a>';
        return str_replace('$1', $hashtag, $hashtagLink);
    }
}
