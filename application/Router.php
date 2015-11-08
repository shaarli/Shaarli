<?php

/**
 * Class Router
 *
 * (only displayable pages here)
 */
class Router
{
    public static $PAGE_LOGIN = 'login';

    public static $PAGE_PICWALL = 'picwall';

    public static $PAGE_TAGCLOUD = 'tagcloud';

    public static $PAGE_TOOLS = 'tools';

    public static $PAGE_CHANGEPASSWORD = 'changepasswd';

    public static $PAGE_CONFIGURE = 'configure';

    public static $PAGE_CHANGETAG = 'changetag';

    public static $PAGE_ADDLINK = 'addlink';

    public static $PAGE_EDITLINK = 'edit_link';

    public static $PAGE_EXPORT = 'export';

    public static $PAGE_IMPORT = 'import';

    public static $PAGE_LINKLIST = 'linklist';

    /**
     * Reproducing renderPage() if hell, to avoid regression.
     *
     * This highlights how bad this needs to be rewrite,
     * but let's focus on plugins for now.
     *
     * @param string $query    $_SERVER['QUERY_STRING'].
     * @param array  $get      $_SERVER['GET'].
     * @param bool   $loggedIn true if authenticated user.
     *
     * @return self::page found.
     */
    public static function findPage($query, $get, $loggedIn)
    {
        $loggedIn = ($loggedIn === true) ? true : false;

        if (empty($query) && !isset($get['edit_link']) && !isset($get['post'])) {
            return self::$PAGE_LINKLIST;
        }

        if (startswith($query, 'do='. self::$PAGE_LOGIN) && $loggedIn === false) {
            return self::$PAGE_LOGIN;
        }

        if (startswith($query, 'do='. self::$PAGE_PICWALL)) {
            return self::$PAGE_PICWALL;
        }

        if (startswith($query, 'do='. self::$PAGE_TAGCLOUD)) {
            return self::$PAGE_TAGCLOUD;
        }

        // At this point, only loggedin pages.
        if (!$loggedIn) {
            return self::$PAGE_LINKLIST;
        }

        if (startswith($query, 'do='. self::$PAGE_TOOLS)) {
            return self::$PAGE_TOOLS;
        }

        if (startswith($query, 'do='. self::$PAGE_CHANGEPASSWORD)) {
            return self::$PAGE_CHANGEPASSWORD;
        }

        if (startswith($query, 'do='. self::$PAGE_CONFIGURE)) {
            return self::$PAGE_CONFIGURE;
        }

        if (startswith($query, 'do='. self::$PAGE_CHANGETAG)) {
            return self::$PAGE_CHANGETAG;
        }

        if (startswith($query, 'do='. self::$PAGE_ADDLINK)) {
            return self::$PAGE_ADDLINK;
        }

        if (isset($get['edit_link']) || isset($get['post'])) {
            return self::$PAGE_EDITLINK;
        }

        if (startswith($query, 'do='. self::$PAGE_EXPORT)) {
            return self::$PAGE_EXPORT;
        }

        if (startswith($query, 'do='. self::$PAGE_IMPORT)) {
            return self::$PAGE_IMPORT;
        }

        return self::$PAGE_LINKLIST;
    }
}