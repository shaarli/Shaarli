<?php

namespace Shaarli\Tests;

use DateTime;

class UtilsEnTest extends UtilsTest
{
    /**
     * Test for international date formatter class. Other tests
     * will fail without it!
     */
    public function testIntlDateFormatter()
    {
        $this->assertTrue(class_exists('IntlDateFormatter'));
    }

    /**
     * Test date_format().
     */
    public function testDateFormat()
    {
        $current = setlocale(LC_ALL, 0);
        autoLocale('en_US.UTF-8');
        $date = DateTime::createFromFormat('Ymd_His', '20170102_201112');
        // Replace No-Break-Space with simple space
        $formatedDate = str_replace(' ', ' ', format_date($date, true, true));
        $this->assertRegExp('/January 2, 2017 (at )?8:11:12 PM GMT\+0?3(:00)?/', $formatedDate);
        setlocale(LC_ALL, $current);
    }

    /**
     * Test date_format() without time.
     */
    public function testDateFormatNoTime()
    {
        $current = setlocale(LC_ALL, 0);
        autoLocale('en_US.UTF-8');
        $date = DateTime::createFromFormat('Ymd_His', '20170102_201112');
        $this->assertRegExp('/January 2, 2017/', format_date($date, false, true));
        setlocale(LC_ALL, $current);
    }

    /**
     * Test date_format() using DateTime
     */
    public function testDateFormatDefault()
    {
        $date = DateTime::createFromFormat('Ymd_His', '20170102_101112');
        $this->assertEquals('January 2, 2017 10:11:12 AM GMT+03:00', format_date($date, true, false));
    }

    /**
     * Test date_format() using DateTime
     */
    public function testDateFormatDefaultNoTime()
    {
        $date = DateTime::createFromFormat('Ymd_His', '20170201_101112');
        $this->assertEquals('February 1, 2017', format_date($date, false, false));
    }

    /**
     * Test autoLocale with a simple value
     */
    public function testAutoLocaleValid()
    {
        $current = setlocale(LC_ALL, 0);
        $header = 'de-de';
        autoLocale($header);
        $this->assertEquals('de_DE.utf8', setlocale(LC_ALL, 0));

        setlocale(LC_ALL, $current);
    }

    /**
     * Test autoLocale with an alternative locale value
     */
    public function testAutoLocaleValidAlternative()
    {
        $current = setlocale(LC_ALL, 0);
        $header = 'de_de.UTF8';
        autoLocale($header);
        $this->assertEquals('de_DE.utf8', setlocale(LC_ALL, 0));

        setlocale(LC_ALL, $current);
    }

    /**
     * Test autoLocale with multiples value, the first one is valid
     */
    public function testAutoLocaleMultipleFirstValid()
    {
        $current = setlocale(LC_ALL, 0);
        $header = 'de-de;en-us';
        autoLocale($header);
        $this->assertEquals('de_DE.utf8', setlocale(LC_ALL, 0));

        setlocale(LC_ALL, $current);
    }

    /**
     * Test autoLocale with multiples value, the second one is available
     */
    public function testAutoLocaleMultipleSecondAvailable()
    {
        $current = setlocale(LC_ALL, 0);
        $header = 'mgg_IN,fr-fr';
        autoLocale($header);
        $this->assertEquals('fr_FR.utf8', setlocale(LC_ALL, 0));

        setlocale(LC_ALL, $current);
    }

    /**
     * Test autoLocale without value: defaults to en_US.
     */
    public function testAutoLocaleBlank()
    {
        $current = setlocale(LC_ALL, 0);
        autoLocale('');
        $this->assertEquals('en_US.UTF-8', setlocale(LC_ALL, 0));

        setlocale(LC_ALL, $current);
    }

    /**
     * Test autoLocale with an unavailable value: defaults to en_US.
     */
    public function testAutoLocaleUnavailable()
    {
        $current = setlocale(LC_ALL, 0);
        autoLocale('mgg_IN');
        $this->assertEquals('en_US.UTF-8', setlocale(LC_ALL, 0));

        setlocale(LC_ALL, $current);
    }
}
