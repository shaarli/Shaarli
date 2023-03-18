# Release Shaarli

## Requirements

This guide assumes that you have:

- a GPG key matching your GitHub authentication credentials/email (the email address identified by the GPG key is the same as the one in your `~/.gitconfig`)
- a GitHub fork of Shaarli
- a local clone of your Shaarli fork, with the following remotes:
    - `origin` pointing to your GitHub fork
    - `upstream` pointing to the main Shaarli repository
- maintainer permissions on the main Shaarli repository, to:
    - push the signed tag
    - create a new release
- [Composer](https://getcomposer.org/) needs to be installed
- The [venv](https://docs.python.org/3/library/venv.html) Python 3 module needs to be installed for HTML documentation generation.

## Release notes and `CHANGELOG.md`

GitHub allows drafting the release notes for the upcoming release, from the [Releases](https://github.com/shaarli/Shaarli/releases) page. This way, the release note can be drafted while contributions are merged to `master`. See http://keepachangelog.com/en/0.3.0/ for changelog formatting.

`CHANGELOG.md` should contain the same information as the release note draft for the upcoming version. Update it to:

- add new entries (additions, fixes, etc.)
- mark the current version as released by setting its date and link
- add a new section for the future unreleased version

```bash
## [v0.x.y](https://github.com/shaarli/Shaarli/releases/tag/v0.x.y) - UNRELEASES

### Added

### Changed

### Fixed

### Removed

### Deprecated

### Security

```


## Update the list of Git contributors

```bash
$ make generate_authors
$ git commit -s -m "Update AUTHORS"
```

## Create and merge a Pull Request

Create a Pull Request to marge changes from your remote, into `master` in the community Shaarli repository, and have it merged.


## Create the release branch and update shaarli_version.php

```bash
# fetch latest changes from master to your local copy
git checkout master
git pull upstream master

# If releasing a new minor version, create a release branch
$ git checkout -b v0.x
# Otherwise just use the existing one
$ git checkout v0.x

# Get the latest changes
$ git merge master

# Check that everything went fine:
$ make test

# Bump shaarli_version.php from dev to 0.x.0, **without the v**
$ vim shaarli_version.php
$ git add shaarli_version
$ git commit -s -m "Bump Shaarli version to v0.x.0"
$ git push upstream v0.x
```

## Create and push a signed tag

Git [tags](http://git-scm.com/book/en/v2/Distributed-Git-Maintaining-a-Project#Tagging-Your-Releases) are used to identify specific revisions with a unique version number that follows [semantic versioning](https://semver.org/)

```bash
# update your local copy
git checkout v0.5
git pull upstream v0.5

# create a signed tag
git tag -s -m "Release v0.5.0" v0.5.0

# push the tag to upstream
git push --tags upstream
```

Here is how to verify a signed tag. [`v0.5.0`](https://github.com/shaarli/Shaarli/releases/tag/v0.5.0) is the first GPG-signed tag pushed on the Community Shaarli. Let's have a look at its signature!

```bash
# update the list of available tags
git fetch upstream

# get the SHA1 reference of the tag
git show-ref tags/v0.5.0
# gives: f7762cf803f03f5caf4b8078359a63783d0090c1 refs/tags/v0.5.0

# verify the tag signature information
git verify-tag f7762cf803f03f5caf4b8078359a63783d0090c1
# gpg: Signature made Thu 30 Jul 2015 11:46:34 CEST using RSA key ID 4100DF6F
# gpg: Good signature from "VirtualTam <virtualtam@flibidi.net>" [ultimate]
```

## Publish the GitHub release

- In the `master` banch, update version badges in `README.md` to point to the newly released Shaarli version
- Update the previously drafted [release](https://github.com/shaarli/Shaarli/releases) (notes, tag) and publish it
- Profit!


## Generate full release zip archives

Release archives will contain Shaarli code plus all required third-party libraries. They are useful for users who:

- have no SSH access, no possibility to install PHP packages/server extensions, no possibility to run scripts (shared hosting)
- do not want to install build/dev dependencies on their server

 `git checkout` the appropriate branch, then:

```bash
# checkout the appropriate branch
git checkout 0.x.y
# generate zip archives
make release_archive
```

This will create `shaarli-v0.x.y-full.tar`, `shaarli-v0.x.y-full.zip`. These archives need to be manually uploaded on the previously created GitHub [release](https://github.com/shaarli/Shaarli/releases).


### Update the `latest` branch

```bash
# checkout the 'latest' branch
git checkout latest
# merge changes from your newly published release branch
git merge v0.x.y
# fix eventual conflicts with git mergetool...
# run tests
make test
# push the latest branch
git push upstream latest
```
