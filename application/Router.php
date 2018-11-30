<?php

/**
 * Class Router
 *
 * (only displayable pages here)
 */
class Router
{
    public static $AJAX_THUMB_UPDATE = 'ajax_thumb_update';

    public static $PAGE_LOGIN = 'login';

    public static $PAGE_PICWALL = 'picwall';

    public static $PAGE_TAGCLOUD = 'tagcloud';

    public static $PAGE_TAGLIST = 'taglist';

    public static $PAGE_DAILY = 'daily';

    public static $PAGE_FEED_ATOM = 'atom';

    public static $PAGE_FEED_RSS = 'rss';

    public static $PAGE_TOOLS = 'tools';

    public static $PAGE_CHANGEPASSWORD = 'changepasswd';

    public static $PAGE_CONFIGURE = 'configure';

    public static $PAGE_CHANGETAG = 'changetag';

    public static $PAGE_ADDLINK = 'addlink';

    public static $PAGE_EDITLINK = 'edit_link';

    public static $PAGE_DELETELINK = 'delete_link';

    public static $PAGE_PINLINK = 'pin';

    public static $PAGE_EXPORT = 'export';

    public static $PAGE_IMPORT = 'import';

    public static $PAGE_OPENSEARCH = 'opensearch';

    public static $PAGE_LINKLIST = 'linklist';

    public static $PAGE_PLUGINSADMIN = 'pluginadmin';

    public static $PAGE_SAVE_PLUGINSADMIN = 'save_pluginadmin';

    public static $PAGE_THUMBS_UPDATE = 'thumbs_update';

    public static $GET_TOKEN = 'token';

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
     * @return string page found.
     */
    public static function findPage($query, $get, $loggedIn)
    {
        $loggedIn = ($loggedIn === true) ? true : false;

        if (empty($query) && !isset($get['edit_link']) && !isset($get['post'])) {
            return self::$PAGE_LINKLIST;
        }

        if (startsWith($query, 'do='. self::$PAGE_LOGIN) && $loggedIn === false) {
            return self::$PAGE_LOGIN;
        }

        if (startsWith($query, 'do='. self::$PAGE_PICWALL)) {
            return self::$PAGE_PICWALL;
        }

        if (startsWith($query, 'do='. self::$PAGE_TAGCLOUD)) {
            return self::$PAGE_TAGCLOUD;
        }

        if (startsWith($query, 'do='. self::$PAGE_TAGLIST)) {
            return self::$PAGE_TAGLIST;
        }

        if (startsWith($query, 'do='. self::$PAGE_OPENSEARCH)) {
            return self::$PAGE_OPENSEARCH;
        }

        if (startsWith($query, 'do='. self::$PAGE_DAILY)) {
            return self::$PAGE_DAILY;
        }

        if (startsWith($query, 'do='. self::$PAGE_FEED_ATOM)) {
            return self::$PAGE_FEED_ATOM;
        }

        if (startsWith($query, 'do='. self::$PAGE_FEED_RSS)) {
            return self::$PAGE_FEED_RSS;
        }

        if (startsWith($query, 'do='. self::$PAGE_THUMBS_UPDATE)) {
            return self::$PAGE_THUMBS_UPDATE;
        }

        if (startsWith($query, 'do='. self::$AJAX_THUMB_UPDATE)) {
            return self::$AJAX_THUMB_UPDATE;
        }

        // At this point, only loggedin pages.
        if (!$loggedIn) {
            return self::$PAGE_LINKLIST;
        }

        if (startsWith($query, 'do='. self::$PAGE_TOOLS)) {
            return self::$PAGE_TOOLS;
        }

        if (startsWith($query, 'do='. self::$PAGE_CHANGEPASSWORD)) {
            return self::$PAGE_CHANGEPASSWORD;
        }

        if (startsWith($query, 'do='. self::$PAGE_CONFIGURE)) {
            return self::$PAGE_CONFIGURE;
        }

        if (startsWith($query, 'do='. self::$PAGE_CHANGETAG)) {
            return self::$PAGE_CHANGETAG;
        }

        if (startsWith($query, 'do='. self::$PAGE_ADDLINK)) {
            return self::$PAGE_ADDLINK;
        }

        if (isset($get['edit_link']) || isset($get['post'])) {
            return self::$PAGE_EDITLINK;
        }

        if (isset($get['delete_link'])) {
            return self::$PAGE_DELETELINK;
        }

        if (startsWith($query, 'do='. self::$PAGE_PINLINK)) {
            return self::$PAGE_PINLINK;
        }

        if (startsWith($query, 'do='. self::$PAGE_EXPORT)) {
            return self::$PAGE_EXPORT;
        }

        if (startsWith($query, 'do='. self::$PAGE_IMPORT)) {
            return self::$PAGE_IMPORT;
        }

        if (startsWith($query, 'do='. self::$PAGE_PLUGINSADMIN)) {
            return self::$PAGE_PLUGINSADMIN;
        }

        if (startsWith($query, 'do='. self::$PAGE_SAVE_PLUGINSADMIN)) {
            return self::$PAGE_SAVE_PLUGINSADMIN;
        }

        if (startsWith($query, 'do='. self::$GET_TOKEN)) {
            return self::$GET_TOKEN;
        }

        return self::$PAGE_LINKLIST;
    }
}
