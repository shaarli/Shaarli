<?php

declare(strict_types=1);

namespace Shaarli\Render;

interface TemplatePage
{
    public const ERROR_404 = '404';
    public const ADDLINK = 'addlink';
    public const CHANGE_PASSWORD = 'changepassword';
    public const CHANGE_TAG = 'changetag';
    public const CONFIGURE = 'configure';
    public const DAILY = 'daily';
    public const DAILY_RSS = 'dailyrss';
    public const EDIT_LINK = 'editlink';
    public const EDIT_LINK_BATCH = 'editlink.batch';
    public const ERROR = 'error';
    public const EXPORT = 'export';
    public const NETSCAPE_EXPORT_BOOKMARKS = 'export.bookmarks';
    public const FEED_ATOM = 'feed.atom';
    public const FEED_RSS = 'feed.rss';
    public const IMPORT = 'import';
    public const INSTALL = 'install';
    public const LINKLIST = 'linklist';
    public const LOGIN = 'loginform';
    public const OPEN_SEARCH = 'opensearch';
    public const PICTURE_WALL = 'picwall';
    public const PLUGINS_ADMIN = 'pluginsadmin';
    public const PLUGINS_CONTENT = 'pluginscontent';
    public const SERVER = 'server';
    public const TAG_CLOUD = 'tag.cloud';
    public const TAG_LIST = 'tag.list';
    public const THUMBNAILS = 'thumbnails';
    public const TOOLS = 'tools';
}
