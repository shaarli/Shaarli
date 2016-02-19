<?php
require_once 'application/BookmarkUtils.php';

/**
 * Unitary tests for bookmark imports
 */
class BookmarkUtilsTest extends PHPUnit_Framework_TestCase
{
    /**
     * Import success notification
     */
    public function test_generate_successful_import_notification()
    {
        $name = '/home/ringo/starrs.html';
        $size = 1968;
        $count = 312;
        $this->assertEquals(
            '<script>alert("File '.$name.' ('.$size.' bytes) was successfully processed: '
            .$count.' links imported.");document.location=\'?\';</script>',
            generate_import_notification($name, $size, $count)
        );
    }

    /**
     * Import failure notification
     */
    public function test_generate_failed_import_notification()
    {
        $name = 'D:\\Users\Ringo\My Documents\starrs.html';
        $size = 9;
        $this->assertEquals(
            '<script>alert("File '.$name.' ('.$size.' bytes) has an unknown file format. '
            .'Nothing was imported.");document.location=\'?\';</script>',
            generate_import_notification($name, $size)
        );
        $this->assertEquals(
            '<script>alert("File '.$name.' ('.$size.' bytes) has an unknown file format. '
            .'Nothing was imported.");document.location=\'?\';</script>',
            generate_import_notification($name, $size, 0)
        );
    }
}
