## Plain text search

Use the `Search text` field to search in _any_ of the fields of all links (Title, URL, Description...)

**Exclude text/tags:** Use the `-` operator before a word or tag (example `-uninteresting`) to prevent entries containing (or tagged) `uninteresting` from showing up in the search results.

**Exact text search:** Use double-quotes (example `"exact search"`) to search for the exact expression.

Both exclude patterns and exact searches can be combined with normal searches (example `"exact search" term otherterm -notthis "very exact" stuff -notagain`)

## Tags search

Use the `Filter by tags` field to restrict displayed links to entries tagged with one or multiple tags (use space to separate tags).  

**Hidden tags:** Tags starting with a dot `.` (example `.secret`) are private. They can only be seen and searched when logged in.

### Tag cloud

The `Tag cloud` page diplays a "cloud" view of all tags in your Shaarli.

 * More frequently used tags are displayed with a bigger font size.
 * When sorting by `Most used` or `Alphabetical`, tags are displayed as a _list_, along with counters and edit/delete button for each tag.
 * Clicking on any tag will display a list of all Shaares matching this tag.
 * Clicking on the counter next to a tag `example` , will filter the tag cloud to only display tags found in Shaares tagged `example`. Repeat this any number of times to further filter the tag cloud. Click `List all links with those tags` to display Shaares matching your current tag filter.

## Filtering RSS feeds/Picture wall

RSS feeds can also be restricted to only return items matching a text/tag search: see [RSS feeds](RSS feeds).

## Filter buttons

Filter buttons can be found at the top left of the link list. They allow you to apply different filters to the list:

 * **Private links:** When this toggle button is enabled, only shaares set to `private` will be shown.
 * **Untagged links:** When the this toggle button is enabled (top left of the link list), only shaares _without any tags_ will be shown in the link list.
 
Filter buttons are only available when logged in.
