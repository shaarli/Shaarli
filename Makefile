# Shaarli, the personal, minimalist, super-fast, no-database delicious clone.
# Makefile for PHP code analysis & testing

# Prerequisites:
# - install Composer, either:
#   - from your distro's package manager;
#   - from the official website (https://getcomposer.org/download/);
# - install/update test dependencies:
#   $ composer install  # 1st setup
#   $ composer update
# - install Xdebug for PHPUnit code coverage reports:
#   - see http://xdebug.org/docs/install
#   - enable in php.ini

BIN = vendor/bin
PHP_SOURCE = index.php application tests plugins
PHP_COMMA_SOURCE = index.php,application,tests,plugins

all: static_analysis_summary test

##
# Concise status of the project
# These targets are non-blocking: || exit 0
##

static_analysis_summary: code_sniffer_source copy_paste mess_detector_summary
	@echo

##
# PHP_CodeSniffer
# Detects PHP syntax errors
# Documentation (usage, output formatting):
# - http://pear.php.net/manual/en/package.php.php-codesniffer.usage.php
# - http://pear.php.net/manual/en/package.php.php-codesniffer.reporting.php
##

code_sniffer: code_sniffer_full

### - errors filtered by coding standard: PEAR, PSR1, PSR2, Zend...
PHPCS_%:
	@$(BIN)/phpcs $(PHP_SOURCE) --report-full --report-width=200 --standard=$*

### - errors by Git author
code_sniffer_blame:
	@$(BIN)/phpcs $(PHP_SOURCE) --report-gitblame

### - all errors/warnings
code_sniffer_full:
	@$(BIN)/phpcs $(PHP_SOURCE) --report-full --report-width=200

### - errors grouped by kind
code_sniffer_source:
	@$(BIN)/phpcs $(PHP_SOURCE) --report-source || exit 0

##
# PHP Copy/Paste Detector
# Detects code redundancy
# Documentation: https://github.com/sebastianbergmann/phpcpd
##

copy_paste:
	@echo "-----------------------"
	@echo "PHP COPY/PASTE DETECTOR"
	@echo "-----------------------"
	@$(BIN)/phpcpd $(PHP_SOURCE) || exit 0
	@echo

##
# PHP Mess Detector
# Detects PHP syntax errors, sorted by category
# Rules documentation: http://phpmd.org/rules/index.html
##
MESS_DETECTOR_RULES = cleancode,codesize,controversial,design,naming,unusedcode

mess_title:
	@echo "-----------------"
	@echo "PHP MESS DETECTOR"
	@echo "-----------------"

###  - all warnings
mess_detector: mess_title
	@$(BIN)/phpmd $(PHP_COMMA_SOURCE) text $(MESS_DETECTOR_RULES) | sed 's_.*\/__'

### - all warnings + HTML output contains links to PHPMD's documentation
mess_detector_html:
	@$(BIN)/phpmd $(PHP_COMMA_SOURCE) html $(MESS_DETECTOR_RULES) \
	--reportfile phpmd.html || exit 0

### - warnings grouped by message, sorted by descending frequency order
mess_detector_grouped: mess_title
	@$(BIN)/phpmd $(PHP_SOURCE) text $(MESS_DETECTOR_RULES) \
	| cut -f 2 | sort | uniq -c | sort -nr

### - summary: number of warnings by rule set
mess_detector_summary: mess_title
	@for rule in $$(echo $(MESS_DETECTOR_RULES) | tr ',' ' '); do \
		warnings=$$($(BIN)/phpmd $(PHP_COMMA_SOURCE) text $$rule | wc -l); \
		printf "$$warnings\t$$rule\n"; \
	done;

##
# PHPUnit
# Runs unitary and functional tests
# Generates an HTML coverage report if Xdebug is enabled
#
# See phpunit.xml for configuration
# https://phpunit.de/manual/current/en/appendixes.configuration.html
##
test:
	@echo "-------"
	@echo "PHPUNIT"
	@echo "-------"
	@$(BIN)/phpunit tests

##
# Targets for repository and documentation maintenance
##

### remove all unversioned files
clean:
	@git clean -df

### update the local copy of the documentation
doc: clean
	@rm -rf doc
	@git clone https://github.com/shaarli/Shaarli.wiki.git doc
	@rm -rf doc/.git

### Generate a custom sidebar
#
# Sidebar content:
#  - convert GitHub-flavoured relative links to standard Markdown
#  - trim HTML, only keep the list (<ul>[...]</ul>) part
htmlsidebar:
	@echo '<div id="local-sidebar">' > doc/sidebar.html
	@awk 'BEGIN { FS = "[\\[\\]]{2}" }'\
	'm = /\[/ { t=$$2; gsub(/ /, "-", $$2); print $$1"["t"]("$$2".html)"$$3 }'\
	'!m { print $$0 }' doc/_Sidebar.md > doc/tmp.md
	@pandoc -f markdown -t html5 -s doc/tmp.md | awk '/(ul>|li>)/' >> doc/sidebar.html
	@echo '</div>' >> doc/sidebar.html
	@rm doc/tmp.md

### Convert local markdown documentation to HTML
#
# For all pages:
#  - infer title from the file name
#  - convert GitHub-flavoured relative links to standard Markdown
#  - insert the sidebar menu
htmlpages:
	@for file in `find doc/ -maxdepth 1 -name "*.md"`; do \
		base=`basename $$file .md`; \
		sed -i "1i #$${base//-/ }" $$file; \
		awk 'BEGIN { FS = "[\\[\\]]{2}" }'\
	'm = /\[/ { t=$$2; gsub(/ /, "-", $$2); print $$1"["t"]("$$2".html)"$$3 }'\
	'!m { print $$0 }' $$file > doc/tmp.md; \
		mv doc/tmp.md $$file; \
		pandoc -f markdown_github -t html5 -s \
			-c "github-markdown.css" \
			-T Shaarli -M pagetitle:"$${base//-/ }" -B doc/sidebar.html \
			-o doc/$$base.html $$file; \
	done;

htmldoc: doc htmlsidebar htmlpages
