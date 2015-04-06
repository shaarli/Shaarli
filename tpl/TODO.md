**TODO: Rewrite the bookmarklet as a plugin (quicktags)**


#### Plugin implementation in tools dialog
see https://github.com/nodiscc/Shaarli/commit/f0b4d7ad220d82813c60c9e54b5ee33a913caada for plugin impl reference

**in index.php**, add to `renderPage()`, after `$PAGE = new pageBuilder;`:

    $tools_plugins = array();`

then in the foreach loop `foreach ($GLOBALS['config']['PLUGINS'] as $plugin`:
```php
        $plugin_tools = $plugin_base . ".tools";
        if (file_exists($GLOBALS['config']['RAINTPL_TPL'] . $plugin_tools . ".html")) {
            $tools_plugins[] = $plugin_tools;
        }
```

and just below the loop

```phpquic
    $PAGE->assign("plugins_tools", $tools_plugins);
```


**in tools.html**, add plugin loop (`tpl/plugins/*/*.tools.html`)

```php
    {loop="$plugins_tools"}
          <span>{include="$value"}</span>
    {/loop}
```

#### The plugin itself


Define a list of tags in `data/options.php` or `tpl/plugins/quicktag/options.php`:

```php
    $GLOBALS['config']['QUICKTAGS']= array( \
      "readlater"=>"Read Later", \
      "documentation"=>"📖", \
      "todo"=>"To Do list", \
      "music"=>"♪" \
    );
```

**in tpl/plugins/quicktags/quicktags.tools.html**, loop over $key=>$value from `$GLOBALS['config']['QUICKTAGS']`; create an html section like:

```html

<h3>Quick Tags</h3>
Bookmarklets that help you adding links to quick lists

    <A HREF=BOOKMArKLETCODEFROMHELL&private=1&tags=$key>✚$value</a> - <a href="?searchtags=$key">$value</a>

```

Will render like

----------------------------------------------
<h3>Quick Tags</h3>
Bookmarklets that help you adding links to quick lists

 * [✚Read Later](http://my.shaarli.url/bookmarklet&tags=readlater&private=1) `<- Add the page to the Read Later list` [Read Later](http://my.shaarli.url/?searchtags=readlater)
 * [✚📖](http://my.shaarli.url/bookmarklet&tags=documentation&private=1) `<- Add the page to the 📖 list` [📖](http://my.shaarli.url/?searchtags=documentation)
 * [✚To Do list](http://my.shaarli.url/bookmarklet&tags=todo&private=1) `<- Add the page to the To Do list list` [To Do list](http://my.shaarli.url/?searchtags=todo)
 * [✚♪](http://my.shaarli.url/bookmarklet&tags=music&private=1) `<- Add the page to the ♪ list` [♪](http://my.shaarli.url/?searchtags=music)

-------------------------------------------------

Later: add optional private/public mode for each list  
Laater: add optional quick access buttons to the toolbar
