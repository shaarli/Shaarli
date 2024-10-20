# Plugin system

## Developer API

### What can I do with plugins?

The plugin system lets you:

- insert content into specific places across templates.
- alter data before templates rendering.
- alter data before saving new links.


### How can I create a plugin for Shaarli?

First, chose a plugin name, such as `demo_plugin`.

Under `plugin` folder, create a folder named with your plugin name. Then create a <plugin_name>.meta file and a <plugin_name>.php file in that folder.

You should have the following tree view:

```
| index.php
| plugins/
|---| demo_plugin/
|   |---| demo_plugin.meta
|   |---| demo_plugin.php
```

### Plugin initialization

At the beginning of Shaarli execution, all enabled plugins are loaded. At this point, the plugin system looks for an `init()` function in the <plugin_name>.php to execute and run it if it exists. This function must be named this way, and takes the `ConfigManager` as parameter.

    <plugin_name>_init($conf)

This function can be used to create initial data, load default settings, etc. But also to set *plugin errors*. If the initialization function returns an array of strings, they will be understand as errors, and displayed in the header to logged in users.

The plugin system also looks for a `description` variable in the <plugin_name>.meta file, to be displayed in the plugin administration page.

    description="The plugin does this and that."

### Understanding hooks

A plugin is a set of functions. Each function will be triggered by the plugin system at certain point in Shaarli execution.

These functions need to be named with this pattern:

```
hook_<plugin_name>_<hook_name>($data, $conf)
```

Parameters:

- data: see [$data section](https://shaarli.readthedocs.io/en/master/Plugin-System/#plugins-data)
- conf: the `ConfigManager` instance.

For example, if my plugin want to add data to the header, this function is needed:

    hook_demo_plugin_render_header

If this function is declared, and the plugin enabled, it will be called every time Shaarli is rendering the header.


### Plugin's data

#### Parameters

Every hook function has a `$data` parameter. Its content differs for each hooks.

**This parameter needs to be returned every time**, otherwise data is lost.

    return $data;

#### Special data

Special additional data are passed to every hook through the
`$data` parameter to give you access to additional context, and services.

Complete list:

  * `_PAGE_` (string): if the current hook is used to render a template, its name is passed through this additional parameter.
  * `_LOGGEDIN_` (bool): whether the user is logged in or not.
  * `_BASE_PATH_` (string): if Shaarli instance is hosted under a subfolder, contains the subfolder path to `index.php` (e.g. `https://domain.tld/shaarli/` -> `/shaarli/`).
  * `_BOOKMARK_SERVICE_` (`BookmarkServiceInterface`): bookmark service instance, for advanced usage.

Example:

```php
if ($data['_PAGE_'] === TemplatePage::LINKLIST && $data['LOGGEDIN'] === true) {
    // Do something for logged in users when the link list is rendered
}
```

#### Filling templates placeholder

Template placeholders are displayed in template in specific places.

RainTPL displays every element contained in the placeholder's array. These element can be added by plugins.

For example, let's add a value in the placeholder `top_placeholder` which is displayed at the top of my page:

```php
$data['top_placeholder'][] = 'My content';
# OR
array_push($data['top_placeholder'], 'My', 'content');

return $data;
```


#### Data manipulation

When a page is displayed, every variable send to the template engine is passed to plugins before that in `$data`.

The data contained by this array can be altered before template rendering.

For example, in linklist, it is possible to alter every title:

```php
// mind the reference if you want $data to be altered
foreach ($data['links'] as &$value) {
    // String reverse every title.
    $value['title'] = strrev($value['title']);
}

return $data;
```

### Metadata

Every plugin needs a `<plugin_name>.meta` file, which is in fact an `.ini` file (`KEY="VALUE"`), to be listed in plugin administration.

Each file contain two keys:

- `description`: plugin description
- `parameters`: user parameter names, separated by a `;`.
- `parameter.<PARAMETER_NAME>`: add a text description the specified parameter.

> Note: In PHP, `parse_ini_file()` seems to want strings to be between by quotes `"` in the ini file.

### Register plugin's routes

Shaarli lets you register custom Slim routes for your plugin.

To register a route, the plugin must include a function called `function <plugin_name>_register_routes(): array`.

This method must return an array of routes, each entry must contain the following keys:

  - `method`: HTTP method, `GET/POST/PUT/PATCH/DELETE`
  - `route` (path): without prefix, e.g. `/up/{variable}`
     It will be later prefixed by `/plugin/<plugin name>/`.
  - `callable` string, function name or FQN class's method to execute, e.g. `demo_plugin_custom_controller`.

Callable functions or methods must have `ServerRequestInterface as Request` and `ResponseInterface as Response` parameters
and return a `ResponseInterface`. We recommend creating a dedicated class and extend either
`ShaarliVisitorController` or `ShaarliAdminController` to use helper functions they provide.

A dedicated plugin template is available for rendering content: `pluginscontent.html` using `content` placeholder.

> **Warning**: plugins are not able to use RainTPL template engine for their content due to technical restrictions.
> RainTPL does not allow to register multiple template folders, so all HTML rendering must be done within plugin
> custom controller.

Check out the `demo_plugin` for a live example: `GET <shaarli_url>/plugin/demo_plugin/custom`.

### Understanding relative paths

Because Shaarli is a self-hosted tool, an instance can either be installed at the root directory, or under a subfolder.
This means that you can *never* use absolute paths (eg `/plugins/mything/file.png`).

If a file needs to be included in server end, use simple relative path:
`PluginManager::$PLUGINS_PATH . '/mything/template.html'`.

If it needs to be included in front end side (e.g. an image),
the relative path must be prefixed with special data:

  * if it's a link that will need to be processed by Shaarli, use `_BASE_PATH_`:
    for e.g. `$data['_BASE_PATH_'] . '/admin/tools`.
  * if you want to include an asset, you need to add the root URL (base path without `/index.php`, for people using Shaarli without URL rewriting), then use `_ROOT_PATH_`:
    for e.g
`$['_ROOT_PATH_'] . '/' . PluginManager::$PLUGINS_PATH . '/mything/picture.png`.

Note that special placeholders for CSS and JS files (respectively `css_files` and `js_files`) are already prefixed
with the root path in template files.

### It's not working!

Use `demo_plugin` as a functional example. It covers most of the plugin system features.

If it's still not working, please [open an issue](https://github.com/shaarli/Shaarli/issues/new).


### Hooks

| Hooks         | Description   |
| ------------- |:-------------:|
| [render_header](#render_header) | Allow plugin to add content in page headers. |
| [render_includes](#render_includes) | Allow plugin to include their own CSS files. |
| [render_footer](#render_footer) | Allow plugin to add content in page footer and include their own JS files. |
| [render_linklist](#render_linklist) | It allows to add content at the begining and end of the page, after every link displayed and to alter link data. |
| [render_editlink](#render_editlink) |  Allow to add fields in the form, or display elements. |
| [render_tools](#render_tools) |  Allow to add content at the end of the page. |
| [render_picwall](#render_picwall) |  Allow to add content at the top and bottom of the page. |
| [render_tagcloud](#render_tagcloud) |  Allow to add content at the top and bottom of the page, and after all tags. |
| [render_taglist](#render_taglist) |  Allow to add content at the top and bottom of the page, and after all tags. |
| [render_daily](#render_daily) |  Allow to add content at the top and bottom of the page, the bottom of each link and to alter data. |
| [render_feed](#render_feed) | Allow to do add tags in RSS and ATOM feeds. |
| [save_link](#save_link) | Allow to alter the link being saved in the datastore. |
| [delete_link](#delete_link) | Allow to do an action before a link is deleted from the datastore. |
| [save_plugin_parameters](#save_plugin_parameters) | Allow to manipulate plugin parameters before they're saved. |
| [filter_search_entry](#filter_search_entry) | Add custom filters to Shaarli search engine |


#### render_header

Triggered on every page - allows plugins to add content in page headers.


##### Data

`$data` is an array containing:

  - [Special data](#special-data)

##### Template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `buttons_toolbar`: after the list of buttons in the header.

![buttons_toolbar_example](http://i.imgur.com/ssJUOrt.png)

- `fields_toolbar`: after search fields in the header.

> Note: This will only be called in linklist.

![fields_toolbar_example](http://i.imgur.com/3GMifI2.png)


#### render_includes

Triggered on every page - allows plugins to include their own CSS files.

##### data

`$data` is an array containing:

  - [Special data](#special-data)

##### Template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `css_files`: called after loading default CSS.

> Note: only add the path of the CSS file. E.g: `plugins/demo_plugin/custom_demo.css`.


#### render_footer

Triggered on every page.

Allow plugin to add content in page footer and include their own JS files.

##### data

`$data` is an array containing:

  - [Special data](#special-data)

##### Template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `text`: called after the end of the footer text.
- `endofpage`: called at the end of the page.

![text_example](http://i.imgur.com/L5S2YEH.png)

- `js_files`: called at the end of the page, to include custom JS scripts.

> Note: only add the path of the JS file. E.g: `plugins/demo_plugin/custom_demo.js`.


#### render_linklist

Triggered when `linklist` is displayed (list of links, permalink, search, tag filtered, etc.).

It allows to add content at the begining and end of the page, after every link displayed and to alter link data.

##### data

`$data` is an array containing:

  - All templates data, including links.
  - [Special data](#special-data)

##### template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `action_plugin`: next to the button "private only" at the top and bottom of the page.

![action_plugin_example](http://i.imgur.com/Q12PWg0.png)

- `link_plugin`: for every link, between permalink and link URL.

![link_plugin_example](http://i.imgur.com/3oDPhWx.png)

- `plugin_start_zone`: before displaying the template content.

![plugin_start_zone_example](http://i.imgur.com/OVBkGy3.png)

- `plugin_end_zone`: after displaying the template content.

![plugin_end_zone_example](http://i.imgur.com/6IoRuop.png)


#### render_editlink

Triggered when the link edition form is displayed.

Allow to add fields in the form, or display elements.

##### data

`$data` is an array containing:

  - All templates data.
  - [Special data](#special-data)

##### template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `edit_link_plugin`: after tags field.

![edit_link_plugin_example](http://i.imgur.com/5u17Ens.png)


#### render_tools

Triggered when the "tools" page is displayed.

Allow to add content at the end of the page.

##### data

`$data` is an array containing:

  - All templates data.
  - [Special data](#special-data)

##### template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `tools_plugin`: at the end of the page.

![tools_plugin_example](http://i.imgur.com/Bqhu9oQ.png)


#### render_picwall

Triggered when picwall is displayed.

Allow to add content at the top and bottom of the page.

##### data

`$data` is an array containing:

  - All templates data.
  - [Special data](#special-data)

##### template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `plugin_start_zone`: before displaying the template content.
- `plugin_end_zone`: after displaying the template content.

![plugin_start_end_zone_example](http://i.imgur.com/tVTQFER.png)


#### render_tagcloud

Triggered when tagcloud is displayed.

Allow to add content at the top and bottom of the page.

##### data

`$data` is an array containing:

  - All templates data.
  - [Special data](#special-data)

##### Template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `plugin_start_zone`: before displaying the template content.
- `plugin_end_zone`: after displaying the template content.

For each tag, the following placeholder can be used:

- `tag_plugin`: after each tag

![plugin_start_end_zone_example](http://i.imgur.com/vHmyT3a.png)


#### render_taglist

Triggered when taglist is displayed - allows to add content at the top and bottom of the page.

##### data

`$data` is an array containing:

  - All templates data.
  - [Special data](#special-data)

##### Template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `plugin_start_zone`: before displaying the template content.
- `plugin_end_zone`: after displaying the template content.

For each tag, the following placeholder can be used:

- `tag_plugin`: after each tag

#### render_daily

Triggered when tagcloud is displayed.

Allow to add content at the top and bottom of the page, the bottom of each link and to alter data.


##### data

`$data` is an array containing:

  - All templates data, including links.
  - [Special data](#special-data)

##### Template placeholders

Items can be displayed in templates by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `link_plugin`: used at bottom of each link.

![link_plugin_example](http://i.imgur.com/hzhMfSZ.png)

- `plugin_start_zone`: before displaying the template content.
- `plugin_end_zone`: after displaying the template content.


#### render_feed

Triggered when the ATOM or RSS feed is displayed.

Allow to add tags in the feed, either in the header or for each items. Items (links) can also be altered before being rendered.

##### data

`$data` is an array containing:

  - All templates data, including links.
  - [Special data](#special-data)

##### Template placeholders

Tags can be added in feeds by adding an entry in `$data['<placeholder>']` array.

List of placeholders:

- `feed_plugins_header`: used as a header tag in the feed.

For each links:

- `feed_plugins`: additional tag for every link entry.


#### save_link

Triggered when a link is save (new link or edit).

Allow to alter the link being saved in the datastore.

##### data

`$data` is an array containing the link being saved:

- id
- title
- url
- shorturl
- description
- private
- tags
- created
- updated

Also [special data](#special-data).


#### delete_link

Triggered when a link is deleted.

Allow to execute any action before the link is actually removed from the datastore

##### data

`$data` is an array containing the link being deleted:

- id
- title
- url
- shorturl
- description
- private
- tags
- created
- updated

Also [special data](#special-data).

#### save_plugin_parameters

Triggered when the plugin parameters are saved from the plugin administration page.

Plugins can perform an action every times their settings are updated.
For example it is used to update the CSS file of the `default_colors` plugins.

##### data

`$data` input contains the `$_POST` array.

So if the plugin has a parameter called `MYPLUGIN_PARAMETER`,
the array will contain an entry with `MYPLUGIN_PARAMETER` as a key.

Also [special data](#special-data).

#### filter_search_entry

Triggered for *every* bookmark when Shaarli's BookmarkService method `search()` is used.
Any custom filter can be added to filter out bookmarks from search results.

The hook **must** return either:
  - `true` to keep bookmark entry in search result set
  - `false` to discard bookmark entry in result set

> Note: custom filters are called *before* default filters are applied.

##### Parameters

- `Shaarli\Bookmark\Bookmark` object: entry to evaluate
- $context `array`: additional information provided depending on what search is currently used,
the user request, etc.

## Guide for template designers

### Plugin administration

Your theme must include a plugin administration page: `pluginsadmin.html`.

> Note: repo's template link needs to be added when the PR is merged.

Use the default one as an example.

Aside from classic RainTPL loops, plugins order is handle by JavaScript. You can just include `plugin_admin.js`, only if:

- you're using a table.
- you call orderUp() and orderUp() onclick on arrows.
- you add data-line and data-order to your rows.

Otherwise, you can use your own JS as long as this field is send by the form:

<input type="hidden" name="order_{$key}" value="{$counter}">

### Placeholder system

In order to make plugins work with every custom themes, you need to add variable placeholder in your templates.

It's a RainTPL loop like this:

    {loop="$plugin_variable"}
        {$value}
    {/loop}

You should enable `demo_plugin` for testing purpose, since it uses every placeholder available.

### List of placeholders

**page.header.html**

At the end of the menu:

    {loop="$plugins_header.buttons_toolbar"}
        {$value}
    {/loop}

At the end of file, before clearing floating blocks:

    {if="!empty($plugin_errors) && $is_logged_in"}
        <ul class="errors">
            {loop="plugin_errors"}
                <li>{$value}</li>
            {/loop}
        </ul>
    {/if}

**includes.html**

At the end of the file:

```html
{loop="$plugins_includes.css_files"}
<link type="text/css" rel="stylesheet" href="{$value}#"/>
{/loop}
```

**page.footer.html**

At the end of your footer notes:

```html
{loop="$plugins_footer.text"}
     {$value}
{/loop}
```

At the end of file:

```html
{loop="$plugins_footer.js_files"}
     <script src="{$value}#"></script>
{/loop}
```

**linklist.html**

After search fields:

```html
{loop="$plugins_header.fields_toolbar"}
     {$value}
{/loop}
```

Before displaying the link list (after paging):

```html
{loop="$plugin_start_zone"}
     {$value}
{/loop}
```

For every links (icons):

```html
{loop="$value.link_plugin"}
    <span>{$value}</span>
{/loop}
```

Before end paging:

```html
{loop="$plugin_end_zone"}
     {$value}
{/loop}
```

**linklist.paging.html**

After the "private only" icon:

```html
{loop="$action_plugin"}
     {$value}
{/loop}
```

**editlink.html**

After tags field:

```html
{loop="$edit_link_plugin"}
     {$value}
{/loop}
```

**tools.html**

After the last tool:

```html
{loop="$tools_plugin"}
     {$value}
{/loop}
```

**picwall.html**

Top:

```html
<div id="plugin_zone_start_picwall" class="plugin_zone">
    {loop="$plugin_start_zone"}
        {$value}
    {/loop}
</div>
```

Bottom:

```html
<div id="plugin_zone_end_picwall" class="plugin_zone">
    {loop="$plugin_end_zone"}
        {$value}
    {/loop}
</div>
```

**tagcloud.html**

Top:

```html
   <div id="plugin_zone_start_tagcloud" class="plugin_zone">
        {loop="$plugin_start_zone"}
            {$value}
        {/loop}
    </div>
```

Bottom:

```html
    <div id="plugin_zone_end_tagcloud" class="plugin_zone">
        {loop="$plugin_end_zone"}
            {$value}
        {/loop}
    </div>
```

**daily.html**

Top:

```html
<div id="plugin_zone_start_picwall" class="plugin_zone">
     {loop="$plugin_start_zone"}
         {$value}
     {/loop}
</div>
```

After every link:

```html
<div class="dailyEntryFooter">
     {loop="$link.link_plugin"}
          {$value}
     {/loop}
</div>
```

Bottom:

```html
<div id="plugin_zone_end_picwall" class="plugin_zone">
    {loop="$plugin_end_zone"}
        {$value}
    {/loop}
</div>
```

**feed.atom.xml** and **feed.rss.xml**:

In headers tags section:
```xml
{loop="$feed_plugins_header"}
  {$value}
{/loop}
```

After each entry:
```xml
{loop="$value.feed_plugins"}
  {$value}
{/loop}
```
