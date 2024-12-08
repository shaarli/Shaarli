## Contributing to Shaarli (community repository)

### Bugs and feature requests
**Reporting bugs, feature requests: issues management**

You can look through existing bugs/requests and help reporting them [here](https://github.com/shaarli/Shaarli/issues).

Constructive input/experience reports/helping other users is welcome.

The general guideline of the fork is to keep Shaarli simple (project and code maintenance, and features-wise), while providing customization capabilities (plugin system, making more settings configurable).

Check the [milestones](https://github.com/shaarli/Shaarli/milestones) to see what issues have priority.

 * The issues list should preferably contain **only tasks that can be actioned immediately**. Anyone should be able to open the issues list, pick one and start working on it immediately.
 * If you have a clear idea of a **feature you expect, or have a specific bug/defect to report**, [search the issues list, both open and closed](https://github.com/shaarli/Shaarli/issues?q=is%3Aissue) to check if it has been discussed, and comment on the appropriate issue. If you can't find one, please open a [new issue](https://github.com/shaarli/Shaarli/issues/new)
 * The **[general discussion](https://github.com/shaarli/Shaarli/issues/308)** issue can be used for general announcements or project-related discussion.
 * You can also join instant discussion at https://gitter.im/shaarli/Shaarli.
 
### Documentation

The [official documentation](http://shaarli.readthedocs.io/en/rtfd/) is generated from [Markdown](https://daringfireball.net/projects/markdown/syntax) documents in the `doc/md/` directory. HTML documentation is generated using [Mkdocs](http://www.mkdocs.org/). [Read the Docs](https://readthedocs.org/) provides hosting for the online documentation. 

To edit the documentation, please edit the appropriate `doc/md/*.md` files (and optionally `make htmlpages` to preview changes to HTML files). Then submit your changes as a Pull Request. Have a look at the MkDocs documentation and configuration file `mkdocs.yml` if you need to add/remove/rename/reorder pages.

### Translations
Currently Shaarli has no translation/internationalization/localization system available and is single-language. You can help by proposing an i18n system (issue https://github.com/shaarli/Shaarli/issues/121)

### Beta testing
You can help testing Shaarli releases by immediately upgrading your installation after a [new version has been releases](https://github.com/shaarli/Shaarli/releases).

All current development happens in [Pull Requests](https://github.com/shaarli/Shaarli/pulls). You can test proposed patches by cloning the Shaarli repo, adding the Pull Request branch and `git checkout` to it. You can also merge multiple Pull Requests to a testing branch.

```bash
git clone https://github.com/shaarli/Shaarli
git remote add pull-request-25 owner/cool-new-feature
git remote add pull-request-26 anotherowner/bugfix
git remote update
git checkout -b testing
git merge cool-new-feature
git merge bugfix
```
Or see [Checkout Github Pull Requests locally](https://gist.github.com/piscisaureus/3342247)

Please report any problem you might find.


### Contributing code

#### Adding your own changes

 * Pick or open an issue
 * Fork the Shaarli repository on github
 * `git clone`  your fork
 * starting from branch ` master`, switch to a new branch (eg. `git checkout -b my-awesome-feature`)
 * edit the required files (from the Github web interface or your text editor)
 * add and commit your changes with a meaningful commit message (eg `Cool new feature, fixes issue #1001`)
 * run unit tests against your patched version, see [Running unit tests](https://shaarli.readthedocs.io/en/master/Unit-tests/#run-unit-tests)
 * Open your fork in the Github web interface and click the "Compare and Pull Request" button, enter required info and submit your Pull Request.

All changes you will do on the `my-awesome-feature`  in the future will be added to your Pull Request. Don't work directly on the master branch, don't do unrelated work on your  `my-awesome-feature` branch.

#### Contributing to an existing Pull Request

TODO

#### Useful links
If you are not familiar with Git or Github, here are a few links to set you on track:

 * https://try.github.io/ - 10 minutes Github workflow interactive tutorial
 * http://ndpsoftware.com/git-cheatsheet.html - A Git cheatsheet
 * http://www.wei-wang.com/ExplainGitWithD3 - Helps you understand some basic Git concepts visually
 * https://www.atlassian.com/git/tutorial - Git tutorials
 * https://www.atlassian.com/git/workflows - Git workflows
 * http://git-scm.com/book - The official Git book, multiple languages
 * http://www.vogella.com/tutorials/Git/article.html - Git tutorials
 * http://think-like-a-git.net/resources.html - Guide to Git
 * http://gitready.com/ - medium to advanced Git docs/tips/blog/articles
 * https://github.com/btford/participating-in-open-source - Participating in Open Source
