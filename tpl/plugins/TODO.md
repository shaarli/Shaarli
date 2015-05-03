https://github.com/shaarli/Shaarli/issues/181 -  Add Disqus or Isso comments box on a permalink page  

 * http://posativ.org/isso/
 * install debian package https://packages.debian.org/sid/isso  
 * configure server http://posativ.org/isso/docs/configuration/server/  
 * configure client http://posativ.org/isso/docs/configuration/client/  
 * http://posativ.org/isso/docs/quickstart/ and add `<script data-isso="//comments.example.tld/" src="//comments.example.tld/js/embed.min.js"></script>` to includes.html template; then add `<section id="isso-thread"></section>` in the linklist template where you want the comments (in the linklist_plugins loop for example)
 

Problem: by default, Isso thread ID is guessed from the current url (only one thread per page).  
if we want multiple threads on a single page (shaarli linklist), we must use : the `data-isso-id` client config,
with data-isso-id being the permalink of an item.

`<section data-isso-id="aH7klxW" id="isso-thread"></section>` 
`data-isso-id: Set a custom thread id, defaults to current URI.`

Problem: feature is currently broken https://github.com/posativ/isso/issues/27 

Another option, only display isso threads when current URL is a permalink (`\?(A-Z|a-z|0-9|-){7}`) (only show thread
when displaying only this link), and just display a "comments" button on each linklist item. Optionally show the comment
count on each item using the API (http://posativ.org/isso/docs/extras/api/#get-comment-count). API requests can be done
by raintpl `{function` or client-side with js. The former should be faster if isso and shaarli are on ther same server.

Showing all full isso threads in the linklist would destroy layout

-----------------------------------------------------------

http://www.git-attitude.fr/2014/11/04/git-rerere/ for the merge
