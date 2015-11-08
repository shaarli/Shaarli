<?php

/**
 * Router tests
 */

require_once 'application/Router.php';

/**
 * Unit tests for Router
 */
class RouterTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test findPage: login page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageLoginValid()
    {
        $this->assertEquals(
            Router::$PAGE_LOGIN,
            Router::findPage('do=login', array(), false)
        );

        $this->assertEquals(
            Router::$PAGE_LOGIN,
            Router::findPage('do=login', array(), 1)
        );

        $this->assertEquals(
            Router::$PAGE_LOGIN,
            Router::findPage('do=login&stuff', array(), false)
        );
    }

    /**
     * Test findPage: login page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageLoginInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_LOGIN,
            Router::findPage('do=login', array(), true)
        );

        $this->assertNotEquals(
            Router::$PAGE_LOGIN,
            Router::findPage('do=other', array(), false)
        );
    }

    /**
     * Test findPage: picwall page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPagePicwallValid()
    {
        $this->assertEquals(
            Router::$PAGE_PICWALL,
            Router::findPage('do=picwall', array(), false)
        );

        $this->assertEquals(
            Router::$PAGE_PICWALL,
            Router::findPage('do=picwall', array(), true)
        );
    }

    /**
     * Test findPage: picwall page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPagePicwallInvalid()
    {
        $this->assertEquals(
            Router::$PAGE_PICWALL,
            Router::findPage('do=picwall&stuff', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_PICWALL,
            Router::findPage('do=other', array(), false)
        );
    }

    /**
     * Test findPage: tagcloud page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageTagcloudValid()
    {
        $this->assertEquals(
            Router::$PAGE_TAGCLOUD,
            Router::findPage('do=tagcloud', array(), false)
        );

        $this->assertEquals(
            Router::$PAGE_TAGCLOUD,
            Router::findPage('do=tagcloud', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_TAGCLOUD,
            Router::findPage('do=tagcloud&stuff', array(), false)
        );
    }

    /**
     * Test findPage: tagcloud page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageTagcloudInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_TAGCLOUD,
            Router::findPage('do=other', array(), false)
        );
    }

    /**
     * Test findPage: linklist page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageLinklistValid()
    {
        $this->assertEquals(
            Router::$PAGE_LINKLIST,
            Router::findPage('', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_LINKLIST,
            Router::findPage('whatever', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_LINKLIST,
            Router::findPage('whatever', array(), false)
        );

        $this->assertEquals(
            Router::$PAGE_LINKLIST,
            Router::findPage('do=tools', array(), false)
        );
    }

    /**
     * Test findPage: tools page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageToolsValid()
    {
        $this->assertEquals(
            Router::$PAGE_TOOLS,
            Router::findPage('do=tools', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_TOOLS,
            Router::findPage('do=tools&stuff', array(), true)
        );
    }

    /**
     * Test findPage: tools page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageToolsInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_TOOLS,
            Router::findPage('do=tools', array(), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_TOOLS,
            Router::findPage('do=tools', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_TOOLS,
            Router::findPage('do=other', array(), true)
        );
    }

    /**
     * Test findPage: changepasswd page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageChangepasswdValid()
    {
        $this->assertEquals(
            Router::$PAGE_CHANGEPASSWORD,
            Router::findPage('do=changepasswd', array(), true)
        );
        $this->assertEquals(
            Router::$PAGE_CHANGEPASSWORD,
            Router::findPage('do=changepasswd&stuff', array(), true)
        );

    }

    /**
     * Test findPage: changepasswd page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageChangepasswdInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_CHANGEPASSWORD,
            Router::findPage('do=changepasswd', array(), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_CHANGEPASSWORD,
            Router::findPage('do=changepasswd', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_CHANGEPASSWORD,
            Router::findPage('do=other', array(), true)
        );
    }
    /**
     * Test findPage: configure page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageConfigureValid()
    {
        $this->assertEquals(
            Router::$PAGE_CONFIGURE,
            Router::findPage('do=configure', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_CONFIGURE,
            Router::findPage('do=configure&stuff', array(), true)
        );
    }

    /**
     * Test findPage: configure page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageConfigureInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_CONFIGURE,
            Router::findPage('do=configure', array(), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_CONFIGURE,
            Router::findPage('do=configure', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_CONFIGURE,
            Router::findPage('do=other', array(), true)
        );
    }

    /**
     * Test findPage: changetag page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageChangetagValid()
    {
        $this->assertEquals(
            Router::$PAGE_CHANGETAG,
            Router::findPage('do=changetag', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_CHANGETAG,
            Router::findPage('do=changetag&stuff', array(), true)
        );
    }

    /**
     * Test findPage: changetag page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageChangetagInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_CHANGETAG,
            Router::findPage('do=changetag', array(), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_CHANGETAG,
            Router::findPage('do=changetag', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_CHANGETAG,
            Router::findPage('do=other', array(), true)
        );
    }

    /**
     * Test findPage: addlink page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageAddlinkValid()
    {
        $this->assertEquals(
            Router::$PAGE_ADDLINK,
            Router::findPage('do=addlink', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_ADDLINK,
            Router::findPage('do=addlink&stuff', array(), true)
        );
    }

    /**
     * Test findPage: addlink page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageAddlinkInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_ADDLINK,
            Router::findPage('do=addlink', array(), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_ADDLINK,
            Router::findPage('do=addlink', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_ADDLINK,
            Router::findPage('do=other', array(), true)
        );
    }

    /**
     * Test findPage: export page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageExportValid()
    {
        $this->assertEquals(
            Router::$PAGE_EXPORT,
            Router::findPage('do=export', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_EXPORT,
            Router::findPage('do=export&stuff', array(), true)
        );
    }

    /**
     * Test findPage: export page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageExportInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_EXPORT,
            Router::findPage('do=export', array(), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_EXPORT,
            Router::findPage('do=export', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_EXPORT,
            Router::findPage('do=other', array(), true)
        );
    }

    /**
     * Test findPage: import page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageImportValid()
    {
        $this->assertEquals(
            Router::$PAGE_IMPORT,
            Router::findPage('do=import', array(), true)
        );

        $this->assertEquals(
            Router::$PAGE_IMPORT,
            Router::findPage('do=import&stuff', array(), true)
        );
    }

    /**
     * Test findPage: import page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageImportInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_IMPORT,
            Router::findPage('do=import', array(), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_IMPORT,
            Router::findPage('do=import', array(), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_IMPORT,
            Router::findPage('do=other', array(), true)
        );
    }

    /**
     * Test findPage: editlink page output.
     * Valid: page should be return.
     *
     * @return void
     */
    public function testFindPageEditlinkValid()
    {
        $this->assertEquals(
            Router::$PAGE_EDITLINK,
            Router::findPage('whatever', array('edit_link' => 1), true)
        );

        $this->assertEquals(
            Router::$PAGE_EDITLINK,
            Router::findPage('', array('edit_link' => 1), true)
        );


        $this->assertEquals(
            Router::$PAGE_EDITLINK,
            Router::findPage('whatever', array('post' => 1), true)
        );

        $this->assertEquals(
            Router::$PAGE_EDITLINK,
            Router::findPage('whatever', array('post' => 1, 'edit_link' => 1), true)
        );
    }

    /**
     * Test findPage: editlink page output.
     * Invalid: page shouldn't be return.
     *
     * @return void
     */
    public function testFindPageEditlinkInvalid()
    {
        $this->assertNotEquals(
            Router::$PAGE_EDITLINK,
            Router::findPage('whatever', array('edit_link' => 1), false)
        );

        $this->assertNotEquals(
            Router::$PAGE_EDITLINK,
            Router::findPage('whatever', array('edit_link' => 1), 1)
        );

        $this->assertNotEquals(
            Router::$PAGE_EDITLINK,
            Router::findPage('whatever', array(), true)
        );
    }
}
