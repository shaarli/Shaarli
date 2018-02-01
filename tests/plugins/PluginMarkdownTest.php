<?php
use Shaarli\Config\ConfigManager;

/**
 * PluginMarkdownTest.php
 */

require_once 'application/Utils.php';
require_once 'plugins/markdown/markdown.php';

/**
 * Class PluginMarkdownTest
 * Unit test for the Markdown plugin
 */
class PluginMarkdownTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigManager instance.
     */
    protected $conf;

    /**
     * Reset plugin path
     */
    public function setUp()
    {
        PluginManager::$PLUGINS_PATH = 'plugins';
        $this->conf = new ConfigManager('tests/utils/config/configJson');
        $this->conf->set('security.allowed_protocols', ['ftp', 'magnet']);
    }

    /**
     * Test render_linklist hook.
     * Only check that there is basic markdown rendering.
     */
    public function testMarkdownLinklist()
    {
        $markdown = '# My title' . PHP_EOL . 'Very interesting content.';
        $data = array(
            'links' => array(
                0 => array(
                    'description' => $markdown,
                ),
            ),
        );

        $data = hook_markdown_render_linklist($data, $this->conf);
        $this->assertNotFalse(strpos($data['links'][0]['description'], '<h1>'));
        $this->assertNotFalse(strpos($data['links'][0]['description'], '<p>'));
    }

    /**
     * Test render_daily hook.
     * Only check that there is basic markdown rendering.
     */
    public function testMarkdownDaily()
    {
        $markdown = '# My title' . PHP_EOL . 'Very interesting content.';
        $data = array(
            // Columns data
            'linksToDisplay' => array(
                // nth link
                0 => array(
                    'formatedDescription' => $markdown,
                ),
            ),
        );

        $data = hook_markdown_render_daily($data, $this->conf);
        $this->assertNotFalse(strpos($data['linksToDisplay'][0]['formatedDescription'], '<h1>'));
        $this->assertNotFalse(strpos($data['linksToDisplay'][0]['formatedDescription'], '<p>'));
    }

    /**
     * Test reverse_text2clickable().
     */
    public function testReverseText2clickable()
    {
        $text = 'stuff http://hello.there/is=someone#here otherstuff';
        $clickableText = text2clickable($text, '');
        $reversedText = reverse_text2clickable($clickableText);
        $this->assertEquals($text, $reversedText);
    }

    /**
     * Test reverse_nl2br().
     */
    public function testReverseNl2br()
    {
        $text = 'stuff' . PHP_EOL . 'otherstuff';
        $processedText = nl2br($text);
        $reversedText = reverse_nl2br($processedText);
        $this->assertEquals($text, $reversedText);
    }

    /**
     * Test reverse_space2nbsp().
     */
    public function testReverseSpace2nbsp()
    {
        $text = ' stuff' . PHP_EOL . '  otherstuff  and another';
        $processedText = space2nbsp($text);
        $reversedText = reverse_space2nbsp($processedText);
        $this->assertEquals($text, $reversedText);
    }

    /**
     * Test sanitize_html().
     */
    public function testSanitizeHtml()
    {
        $input = '< script src="js.js"/>';
        $input .= '< script attr>alert(\'xss\');</script>';
        $input .= '<style> * { display: none }</style>';
        $output = escape($input);
        $input .= '<a href="#" onmouseHover="alert(\'xss\');" attr="tt">link</a>';
        $output .= '<a href="#"  attr="tt">link</a>';
        $input .= '<a href="#" onmouseHover=alert(\'xss\'); attr="tt">link</a>';
        $output .= '<a href="#"  attr="tt">link</a>';
        $this->assertEquals($output, sanitize_html($input));
        // Do not touch escaped HTML.
        $input = escape($input);
        $this->assertEquals($input, sanitize_html($input));
    }

    /**
     * Test the no markdown tag.
     */
    public function testNoMarkdownTag()
    {
        $str = 'All _work_ and `no play` makes Jack a *dull* boy.';
        $data = array(
            'links' => array(array(
                'description' => $str,
                'tags' => NO_MD_TAG,
                'taglist' => array(NO_MD_TAG),
            ))
        );

        $processed = hook_markdown_render_linklist($data, $this->conf);
        $this->assertEquals($str, $processed['links'][0]['description']);

        $processed = hook_markdown_render_feed($data, $this->conf);
        $this->assertEquals($str, $processed['links'][0]['description']);

        $data = array(
            // Columns data
            'linksToDisplay' => array(
                // nth link
                0 => array(
                    'formatedDescription' => $str,
                    'tags' => NO_MD_TAG,
                    'taglist' => array(),
                ),
            ),
        );

        $data = hook_markdown_render_daily($data, $this->conf);
        $this->assertEquals($str, $data['linksToDisplay'][0]['formatedDescription']);
    }

    /**
     * Test that a close value to nomarkdown is not understand as nomarkdown (previous value `.nomarkdown`).
     */
    public function testNoMarkdownNotExcactlyMatching()
    {
        $str = 'All _work_ and `no play` makes Jack a *dull* boy.';
        $data = array(
            'links' => array(array(
                'description' => $str,
                'tags' => '.' . NO_MD_TAG,
                'taglist' => array('.'. NO_MD_TAG),
            ))
        );

        $data = hook_markdown_render_feed($data, $this->conf);
        $this->assertContains('<em>', $data['links'][0]['description']);
    }

    /**
     * Make sure that the generated HTML match the reference HTML file.
     */
    public function testMarkdownGlobalProcessDescription()
    {
        $md = file_get_contents('tests/plugins/resources/markdown.md');
        $md = format_description($md);
        $html = file_get_contents('tests/plugins/resources/markdown.html');

        $data = process_markdown(
            $md,
            $this->conf->get('security.markdown_escape', true),
            $this->conf->get('security.allowed_protocols')
        );
        $this->assertEquals($html, $data);
    }

    /**
     * Make sure that the HTML tags are escaped.
     */
    public function testMarkdownWithHtmlEscape()
    {
        $md = '**strong** <strong>strong</strong>';
        $html = '<div class="markdown"><p><strong>strong</strong> &lt;strong&gt;strong&lt;/strong&gt;</p></div>';
        $data = array(
            'links' => array(
                0 => array(
                    'description' => $md,
                ),
            ),
        );
        $data = hook_markdown_render_linklist($data, $this->conf);
        $this->assertEquals($html, $data['links'][0]['description']);
    }

    /**
     * Make sure that the HTML tags aren't escaped with the setting set to false.
     */
    public function testMarkdownWithHtmlNoEscape()
    {
        $this->conf->set('security.markdown_escape', false);
        $md = '**strong** <strong>strong</strong>';
        $html = '<div class="markdown"><p><strong>strong</strong> <strong>strong</strong></p></div>';
        $data = array(
            'links' => array(
                0 => array(
                    'description' => $md,
                ),
            ),
        );
        $data = hook_markdown_render_linklist($data, $this->conf);
        $this->assertEquals($html, $data['links'][0]['description']);
    }
}
