# Usage

## Features

For any item posted to Shaarli (called a _Shaare_), you can customize the following aspects:

- URL to link to
- Title
- Free-text description
- Tags
- Public/private status


### Adding/editing Shaares

While logged in to your Shaarli, you can add, edit or delete Shaares:

- Using the **+Shaare** button: enter the URL you want to share, click `Add link`, fill in the details of your Shaare, and `Save`
- Using the [Bookmarklet](https://en.wikipedia.org/wiki/Bookmarklet): drag the `✚Shaare link` button from the `Tools` page to your browser's bookmarks bar, click it to share the current page.
- Using [apps and browser addons](Community-and-related-software.md#mobile-apps)
- Using the [REST API](https://shaarli.github.io/api-documentation/)
- Any Shaare can edited by clicking its ![](images/edit_icon.png) `Edit` button.

The `Shaare a new link` dialog also allows you to create multiple bookmarks at once (unfold the `bulk creation` section and add one URL per line).


### Tags

Tags can be be used to organize and categorize your Shaares:

- You can rename, merge and delete tags from the _Tools_ menu or the [tag cloud/list](#tag-cloud)
- Tags are auto-completed (from the list of existing tags) in all dialogs
- Tags can be combined with text in [search](#search) queries


### Public/private Shaares

Additional filter buttons can be found at the top left of the Shaare list **only when logged in**:

- **Only show private Shaares:** Private shares can be searched by clicking the `only show private links` toggle button top left of the Shaares list (only when logged in)


### Sticky Shaares

Enabling the _Sticky_ mode on a Shaare will make it "stick" to the top of the Shaares list (first item of the first page), bypassing the normal sort order (most recent first). DIsabling the _Sticky_ mode will move back the Shaare to its initial position on the link list.

Sticky-ing a Shaare does not bypass active filters/search terms.


### Permalinks

Permalinks are fixed, short links attached to each Shaare. Editing a Shaare will not change it's permalink, each permalink always points to the latest revision of a Shaare.


### Text-only (note) Shaares

Shaarli can be used as a minimal blog, notepad, pastebin...: While adding or editing a Shaare, leave the URL field blank to create a text-only ("note") post. This allows you to post any kind of text content, such as blog articles, private or public notes, snippets... There is no character limit! You can access your post from its permalink.


### Search

- **Plain text search:** Use `Search text` to search in all fields of all Shaares (Title, URL, Description...). Use double-quotes (example `"exact search"`) to search for the exact expression.
- **Tags search:** `Filter by tags` allow only displaying Shaares tagged with one or multiple tags (use space to separate tags). A plus sign `+` is optional and will restrict suggested tags to only those starting with the string (example: `pr` will hint `apron` and `printer` but `+pr` will only hint printer).
- **Hidden tags:** tags starting with a dot `.` (example `.secret`) are private. They can only be seen and searched when logged in.
- **Exclude text/tags:** Use the `-` operator before a word or tag to exclude Shaares matching this word from search results (`NOT` operator).
- **Optional tags:** Use the `~` operator before multiple tags to search for any one of them (`OR` operator). Note that the OR operator only works if there are multiple tags with a tilde. A search for `webdesign ~ai ~youtube` search would match `webdesign AND (ai OR youtube)`. A search for `webdesign ~youtube` is equivalent to `+webdesign +youtube`.
- **Wildcard tag search:** An asterisk (`*`) can be used as a wildcard and will match any number of characters. Wildcards can appear in the middle of a search term or at the end (example: pro\*in\* will match programming and protein).
- **Untagged links:** Shaares without tags can be searched by clicking the `untagged` toggle button top left of the Shaares list (only when logged in).

Both exclude patterns and exact searches can be combined with normal searches (example `"exact search" term otherterm -notthis "very exact" stuff -notagain`). Only AND (and NOT) text search is currently supported.

Active search terms are displayed on top of the link list. To remove terms/tags from the current search, click the `x` next to any of them, or simply clear text/tag search fields.

Shaarli's search can be added to your Web browser's search engines by clicking the `+` Shaarli icon in your browser's search engine selector ([example for Firefox](https://support.mozilla.org/en-US/kb/add-or-remove-search-engine-firefox#w_add-a-search-engine-from-the-search-bar)).


### Tag cloud

The `Tag cloud` page displays a "cloud" or list view of all tags in your Shaarli (most frequently used tags are displayed with a bigger font size)


- **Tags list:** click on `Most used` or `Alphabetical` to display tags as a list. You can also edit/delete tags for this page.
- Click on any tag to search all Shaares matching this tag.
- **Filtering the tag cloud/list:** Click on the counter next to a tag to show other tags of Shaares with this tag. Repeat this any number of times to further filter the tag cloud. Click `List all links with those tags` to display Shaares matching your current tag filter set.



### RSS feeds

RSS/ATOM feeds feeds are available (in ATOM with `/feed/atom` and RSS with `/feed/rss`)

- **Filtering RSS feeds:** RSS feeds and picture wall can also be restricted to only return items matching a text/tag search. For example, search for `photography` (text or tags) in Shaarli, then click the `RSS Feed` button. A feed with only matching results is displayed.
- Add the `&nb` parameter in feed URLs to specify the number of Shaares you want in a feed (default if not specified: `50`). The keyword `all` is available if you want everything.
- Add the `&permalinks` parameter in feed URLs to point permalinks to the corresponding Shaarli entry/link instead of the direct, Shaare URL attribute

![](images/rss-filter-1.png) ![](images/rss-filter-2.png)

```bash
# examples
https://shaarli.mydomain.org/feed/atom?permalinks
https://shaarli.mydomain.org/feed/atom?permalinks&nb=42
https://shaarli.mydomain.org/feed/atom?permalinks&nb=all
https://shaarli.mydomain.org/feed/rss?searchtags=nature
https://shaarli.mydomain.org/links/picture-wall?searchterm=poney
```


### Picture wall

- The picture wall can be filtered by text or tags search in the same way as [RSS feeds](#rss-feeds)


### Import/export

To **export Shaares as a HTML file**, under _Tools > Export_, choose:

- `Export all` to export both public and private Shaares
- `Export public` to export public Shaares only
- `Export private` to export private Shaares only

Restore by using the `Import` feature.

- These exports contain the full data (URL, title, tags, date, description, public/private status of your Shaares)
- They can also be imported to your web browser bookmarks.

To **import a HTML bookmarks file** exported from your browser, just use the `Import` feature. For each "folder" in the bookmarks you imported, a new tag will be created (for example a bookmark in `Movies > Sci-fi` folder will be tagged `Movies` `Sci-fi`).
