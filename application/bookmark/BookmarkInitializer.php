<?php

declare(strict_types=1);

namespace Shaarli\Bookmark;

/**
 * Class BookmarkInitializer
 *
 * This class is used to initialized default bookmarks after a fresh install of Shaarli.
 * It should be only called if the datastore file does not exist(users might want to delete the default bookmarks).
 *
 * To prevent data corruption, it does not overwrite existing bookmarks,
 * even though there should not be any.
 *
 * We disable this because otherwise it creates indentation issues, and heredoc is not supported by PHP gettext.
 * @phpcs:disable Generic.Files.LineLength.TooLong
 *
 * @package Shaarli\Bookmark
 */
class BookmarkInitializer
{
    /** @var BookmarkServiceInterface */
    protected $bookmarkService;

    /**
     * BookmarkInitializer constructor.
     *
     * @param BookmarkServiceInterface $bookmarkService
     */
    public function __construct(BookmarkServiceInterface $bookmarkService)
    {
        $this->bookmarkService = $bookmarkService;
    }

    /**
     * Initialize the data store with default bookmarks
     */
    public function initialize(): void
    {
        $bookmark = new Bookmark();
        $bookmark->setTitle('Calm Jazz Music - YouTube ' . t('(private bookmark with thumbnail demo)'));
        $bookmark->setUrl('https://www.youtube.com/watch?v=DVEUcbPkb-c');
        $bookmark->setDescription(t(
            'Shaarli will automatically pick up the thumbnail for links to a variety of websites.

Explore your new Shaarli instance by trying out controls and menus.
Visit the project on [Github](https://github.com/shaarli/Shaarli) or [the documentation](https://shaarli.readthedocs.io/en/master/) to learn more about Shaarli.

Now you can edit or delete the default shaares.
'
        ));
        $bookmark->setTagsString('shaarli help thumbnail');
        $bookmark->setPrivate(true);
        $this->bookmarkService->add($bookmark, false);

        $bookmark = new Bookmark();
        $bookmark->setTitle(t('Note: Shaare descriptions'));
        $bookmark->setDescription(t(
            'Adding a shaare without entering a URL creates a text-only "note" post such as this one.
This note is private, so you are the only one able to see it while logged in.

You can use this to keep notes, post articles, code snippets, and much more.

The Markdown formatting setting allows you to format your notes and bookmark description:

### Title headings

#### Multiple headings levels
  * bullet lists
  * _italic_ text
  * **bold** text
  * ~~strike through~~ text
  * `code` blocks
  * images
  * [links](https://en.wikipedia.org/wiki/Markdown)

Markdown also supports tables:

| Name    | Type      | Color  | Qty   |
| ------- | --------- | ------ | ----- |
| Orange  | Fruit     | Orange | 126   |
| Apple   | Fruit     | Any    | 62    |
| Lemon   | Fruit     | Yellow | 30    |
| Carrot  | Vegetable | Red    | 14    |
'
        ));
        $bookmark->setTagsString('shaarli help');
        $bookmark->setPrivate(true);
        $this->bookmarkService->add($bookmark, false);

        $bookmark = new Bookmark();
        $bookmark->setTitle(
            'Shaarli - ' . t('The personal, minimalist, super fast, database-free, bookmarking service')
        );
        $bookmark->setDescription(t(
            'Welcome to Shaarli!

Shaarli allows you to bookmark your favorite pages, and share them with others or store them privately.
You can add a description to your bookmarks, such as this one, and tag them.

Create a new shaare by clicking the `+Shaare` button, or using any of the recommended tools (browser extension, mobile app, bookmarklet, REST API, etc.).

You can easily retrieve your links, even with thousands of them, using the internal search engine, or search through tags (e.g. this Shaare is tagged with `shaarli` and `help`).
Hashtags such as #shaarli #help are also supported.
You can also filter the available [RSS feed](/feed/atom) and picture wall by tag or plaintext search.

We hope that you will enjoy using Shaarli, maintained with ❤️ by the community!
Feel free to open [an issue](https://github.com/shaarli/Shaarli/issues) if you have a suggestion or encounter an issue.
'
        ));
        $bookmark->setTagsString('shaarli help');
        $this->bookmarkService->add($bookmark, false);
    }
}
