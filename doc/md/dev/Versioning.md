# Versioning

If you're maintaining a 3rd party tool for Shaarli (theme, plugin, etc.), It's important to understand how Shaarli branches work ensure your tool stays compatible.


## `release` branch

This branch points to the latest release. It recommended to use it to get the latest tested changes.


## `master` branch

The `master` branch is the development branch. Any new change MUST go through this branch using Pull Requests.

- This branch shouldn't be used for production as it isn't necessary stable.
- It is not guaranteed to be compatible with 3rd-party software/themes/plugins.
- Official plugins, themes and libraries (contained within Shaarli organization repos) must be compatible with the master branch.


## Releases

For every release, we manually generate a .zip file which contains all Shaarli dependencies, making Shaarli's installation only one step.


## `v0.x` branch

The `v0.x` branch points to the latest `v0.x.y` release.

If a major bug affects the original `v0.x.0` release, we may [backport](https://en.wikipedia.org/wiki/Backporting) a fix for this bug from master, to the `v0.x` branch, and create a new bugfix release (eg. `v0.x.1`) from this branch. This allows users of the original release to upgrade to the fixed version, without having to upgrade to a completely new minor/major release.



## Advices on 3rd party git repos workflow

### Versioning

Any time a new Shaarli release is published, you should publish a new release of your repo if the changes affected you since the latest release (take a look at the [changelog](https://github.com/shaarli/Shaarli/releases) (*Draft* means not released yet) and the commit log (like [`tpl` folder](https://github.com/shaarli/Shaarli/commits/master/tpl/default) for themes)). You can either:

- use the Shaarli version number, with your repo version. For example, if Shaarli `v0.8.3` is released, publish a `v0.8.3-1` release, where `v0.8.3` states Shaarli compatibility and `-1` is your own version digit for the current Shaarli version.
- use your own versioning scheme, and state Shaarli compatibility in the release description.

Using this, any user will be able to pick the release matching his own Shaarli version.


### Major bugfix backport releases

To be able to support backported fixes, it recommended to use our workflow:

```bash
# In master, fix the major bug
git commit -m "Katastrophe"
git push origin master
# Get your commit hash
git log --format="%H" -n 1
# Create a new branch from your latest release, let's say v0.8.2-1 (the tag name)
git checkout -b katastrophe v0.8.2-1
# Backport the fix commit to your brand new branch
git cherry-pick <fix commit hash>
git push origin katastrophe
# Then you just have to make a new release from the `katastrophe` branch tagged `v0.8.3-1`
```
