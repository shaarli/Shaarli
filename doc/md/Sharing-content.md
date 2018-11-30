Content posted to Shaarli is separated in items called _Shaares_. For each Shaare,
you can customize the following aspects:

 * URL to link to
 * Title
 * Free-text description
 * Tags
 * Public/private status

--------------------------------------------------------------------------------

## Adding new Shaares

While logged in to your Shaarli, you can add new Shaares in several ways:

 * [+Shaare button](#shaare-button)
 * [Bookmarklet](#bookmarklet)
 * Third-party [apps and browser addons](Community-&-Related-software.md#mobile-apps)
 * [REST API](https://shaarli.github.io/api-documentation/)

### +Shaare button

 * While logged in to your Shaarli, click the **`+Shaare`** button located in the toolbar.
 * Enter the URL of a link you want to share.
 * Click `Add link`
 * The `New Shaare` dialog appears, allowing you to fill in the details of your Shaare.
   * The Description, Title, and Tags will help you find your Shaare later using tags or full-text search.
   * You can also check the “Private” box so that the link is saved but only visible to you (the logged-in user).
 * Click `Save`.

<!-- TODO Add screenshot of add/edit link dialog -->

### Bookmarklet

The _Bookmarklet_ \[[1](https://en.wikipedia.org/wiki/Bookmarklet)\] is a special
browser bookmark you can use to add new content to your Shaarli. This bookmarklet is
compatible with Firefox, Opera, Chrome and Safari. To set it up:

 * Access the `Tools` page from the button in the toolbar.
 * Drag the **`✚Shaare link` button** to your browser's bookmarks bar.

Once this is done, you can shaare any URL you are visiting simply by clicking the
bookmarklet in your browser! The same `New Shaare` dialog as above is displayed.

| Note | Websites which enforce Content Security Policy (CSP), such as github.com, disallow usage of bookmarklets. Unfortunately, there is nothing Shaarli can do about it. \[[1](https://github.com/shaarli/Shaarli/issues/196)]\ \[[2](https://bugzilla.mozilla.org/show_bug.cgi?id=866522)]\ \[[3](https://code.google.com/p/chromium/issues/detail?id=233903)]\ |
|---------|---------|

| Note | Under Opera, you can't drag'n drop the button: You have to right-click on it and add a bookmark to your personal toolbar. |
|---------|---------|

![](images/bookmarklet.png)


--------------------------------------------------------------------------------

## Editing Shaares

Any Shaare can edited by clicking its ![](images/edit_icon.png) `Edit` button.

Editing a Shaare will not change it's permalink, each permalink always points to the
latest revision of a Shaare.

--------------------------------------------------------------------------------

## Using shaarli as a blog, notepad, pastebin...

While adding or editing a link, leave the URL field blank to create a text-only
("note") post. This allows you to post any kind of text content, such as blog
articles, private or public notes, snippets... There is no character limit! You can
access your Shaare from its permalink.

