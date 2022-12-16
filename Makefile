# The personal, minimalist, super-fast, database free, bookmarking service.
# Makefile for PHP code analysis & testing, documentation and release generation

BIN = vendor/bin

all: check_permissions test

##
# Docker test adapter
#
# Shaarli sources and vendored libraries are copied from a shared volume
# to a user-owned directory to enable running tests as a non-root user.
##
docker_%:
	rsync -az /shaarli/ ~/shaarli/
	cd ~/shaarli && make $*

##
# PHP_CodeSniffer
# Detects PHP syntax errors
# Documentation (usage, output formatting):
# - http://pear.php.net/manual/en/package.php.php-codesniffer.usage.php
# - http://pear.php.net/manual/en/package.php.php-codesniffer.reporting.php
##
PHPCS := $(BIN)/phpcs

code_sniffer:
	@$(PHPCS)

### - errors by Git author
code_sniffer_blame:
	@$(PHPCS) --report-gitblame

### - all errors/warnings
code_sniffer_full:
	@$(PHPCS) --report-full --report-width=200

### - errors grouped by kind
code_sniffer_source:
	@$(PHPCS) --report-source || exit 0

##
# Checks source file & script permissions
##
check_permissions:
	@echo "----------------------"
	@echo "Check file permissions"
	@echo "----------------------"
	@for file in `git ls-files | grep -v docker`; do \
		if [ -x $$file ]; then \
			errors=true; \
			echo "$${file} is executable"; \
		fi \
	done; [ -z $$errors ] || false

##
# PHPUnit
# Runs unitary and functional tests
# Generates an HTML coverage report if Xdebug is enabled
#
# See phpunit.xml for configuration
# https://phpunit.de/manual/current/en/appendixes.configuration.html
##
test: translate
	@echo "-------"
	@echo "PHPUNIT"
	@echo "-------"
	@mkdir -p sandbox coverage
	@$(BIN)/phpunit --coverage-php coverage/main.cov --bootstrap tests/bootstrap.php --testsuite unit-tests

locale_test_%:
	@UT_LOCALE=$*.utf8 \
		$(BIN)/phpunit \
		--coverage-php coverage/$(firstword $(subst _, ,$*)).cov \
		--bootstrap tests/languages/bootstrap.php \
		--testsuite language-$(firstword $(subst _, ,$*))

all_tests: test locale_test_de_DE locale_test_en_US locale_test_fr_FR
	@# --The current version is not compatible with PHP 7.2
	@#$(BIN)/phpcov merge --html coverage coverage
	@# --text doesn't work with phpunit 4.* (v5 requires PHP 5.6)
	@#$(BIN)/phpcov merge --text coverage/txt coverage

### download 3rd-party PHP libraries, including dev dependencies
composer_dependencies_dev: clean
	composer install --prefer-dist

##
# Custom release archive generation
#
# For each tagged revision, GitHub provides tar and zip archives that correspond
# to the output of git-archive
#
# These targets produce similar archives, featuring 3rd-party dependencies
# to ease deployment on shared hosting.
##
ARCHIVE_VERSION := shaarli-$$(git describe)-full
ARCHIVE_PREFIX=Shaarli/

release_archive: release_tar release_zip

### download 3rd-party PHP libraries
composer_dependencies: clean
	composer install --no-dev --prefer-dist
	find vendor/ -name ".git" -type d -exec rm -rf {} +

### download 3rd-party frontend libraries
frontend_dependencies:
	yarnpkg install

### Build frontend dependencies
build_frontend: frontend_dependencies
	yarnpkg run build

### generate a release tarball and include 3rd-party dependencies and translations
release_tar: composer_dependencies htmldoc translate build_frontend
	git archive --prefix=$(ARCHIVE_PREFIX) -o $(ARCHIVE_VERSION).tar HEAD
	tar rvf $(ARCHIVE_VERSION).tar --transform "s|^vendor|$(ARCHIVE_PREFIX)vendor|" vendor/
	tar rvf $(ARCHIVE_VERSION).tar --transform "s|^doc/html|$(ARCHIVE_PREFIX)doc/html|" doc/html/
	tar rvf $(ARCHIVE_VERSION).tar --transform "s|^tpl|$(ARCHIVE_PREFIX)tpl|" tpl/
	gzip $(ARCHIVE_VERSION).tar

### generate a release zip and include 3rd-party dependencies and translations
release_zip: composer_dependencies htmldoc translate build_frontend
	git archive --prefix=$(ARCHIVE_PREFIX) -o $(ARCHIVE_VERSION).zip -9 HEAD
	mkdir -p $(ARCHIVE_PREFIX)/doc
	mkdir -p $(ARCHIVE_PREFIX)/vendor
	rsync -a doc/html/ $(ARCHIVE_PREFIX)doc/html/
	zip -r $(ARCHIVE_VERSION).zip $(ARCHIVE_PREFIX)doc/
	rsync -a vendor/ $(ARCHIVE_PREFIX)vendor/
	zip -r $(ARCHIVE_VERSION).zip $(ARCHIVE_PREFIX)vendor/
	rsync -a tpl/ $(ARCHIVE_PREFIX)tpl/
	zip -r $(ARCHIVE_VERSION).zip $(ARCHIVE_PREFIX)tpl/
	rm -rf $(ARCHIVE_PREFIX)

##
# Targets for repository and documentation maintenance
##

### remove all unversioned files
clean:
	@git clean -df
	@rm -rf sandbox

### generate the AUTHORS file from Git commit information
authors:
	@cp .github/mailmap .mailmap
	@git shortlog -sne > AUTHORS
	@rm .mailmap

### generate phpDocumentor documentation
phpdoc: clean
	@docker run --rm -v $(PWD):/data -u `id -u`:`id -g` phpdoc/phpdoc

### generate HTML documentation from Markdown pages with MkDocs
htmldoc:
	python3 -m venv venv/
	bash -c 'source venv/bin/activate; \
	pip install wheel; \
	pip install mkdocs; \
	mkdocs build --clean'
	find doc/html/ -type f -exec chmod a-x '{}' \;
	rm -r venv


### Generate Shaarli's translation compiled file (.mo)
translate:
	@echo "----------------------"
	@echo "Compile translation files"
	@echo "----------------------"
	@for pofile in `find inc/languages/ -name shaarli.po`; do \
		echo "Compiling $$pofile"; \
		msgfmt -v "$$pofile" -o "`dirname "$$pofile"`/`basename "$$pofile" .po`.mo"; \
	done;

### Run ESLint check against Shaarli's JS files
eslint:
	@yarnpkg run eslint -c .dev/.eslintrc.js assets/vintage/js/
	@yarnpkg run eslint -c .dev/.eslintrc.js assets/default/js/
	@yarnpkg run eslint -c .dev/.eslintrc.js assets/common/js/

### Run CSSLint check against Shaarli's SCSS files
sasslint:
	@yarnpkg run stylelint --config .dev/.stylelintrc.js 'assets/default/scss/*.scss'
