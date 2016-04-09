#!/bin/sh
#
#  Copyright (c) 2015-2016 Marcus Rohrmoser http://mro.name/me. All rights reserved.
#
#  This program is free software: you can redistribute it and/or modify
#  it under the terms of the GNU General Public License as published by
#  the Free Software Foundation, either version 3 of the License, or
#  (at your option) any later version.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#  You should have received a copy of the GNU General Public License
#  along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

export USERNAME="tast"
export PASSWORD="tust"
export BASE_URL="http://127.0.0.1:8000"

# Check preliminaries
curl --version >/dev/null || { echo "I need curl." && exit 101 ; }
xmllint --version 2> /dev/null || { echo "I need xmllint." && exit 102 ; }
ruby --version > /dev/null || { echo "I need ruby." && exit 103 ; }

# figure out
# - test scripts dir
# - auxiliary scripts dir
# - webapp (shaarli) root 
# - working dir while testing

cd "$(dirname "$0")"
TEST_DIR="$(pwd)"
TEMPDIR="$(mktemp -u -t shaarli.api.XXXXXXXXXX)"
WEBAPPROOT="${TEST_DIR}/../.."

# terminal colors (require bash)
# http://www.tldp.org/HOWTO/Bash-Prompt-HOWTO/x329.html
# http://wiki.bash-hackers.org/scripting/terminalcodes
FGC_NONE="\033[0m"
FGC_GRAY="\033[1;30m"
FGC_RED="\033[1;31m"
FGC_GREEN="\033[1;32m"
FGC_YELLOW="\033[1;33m"
FGC_BLUE="\033[1;34m"
FGC_PURPLE="\033[1;35m"
FGC_CYAN="\033[1;36m"
FGC_WHITE="\033[1;37m"
BGC_GRAY="\033[7;30m"
BGC_RED="\033[7;31m"
BGC_GREEN="\033[7;32m"
BGC_YELLOW="\033[7;33m"
BGC_BLUE="\033[7;34m"
BGC_PURPLE="\033[7;35m"
BGC_CYAN="\033[7;36m"
BGC_WHITE="\033[7;37m"

echo "\$ curl --version" ; curl --version

status_code=0
test_counter=1
echo "1..$(ls "${TEST_DIR}"/test-*.sh | wc -l)"
for tst in "${TEST_DIR}"/test-*.sh
do
  test_name="$(basename "${tst}")"
  echo -n "travis_fold:start:${test_name}\r"
  echo -n "# run ${test_counter} - ${test_name} "

  # prepare a clean test environment from scratch
  cd "${TEST_DIR}"
  rm -rf "${TEMPDIR}"
  mkdir "${TEMPDIR}"

  # clear data, caches etc.
  rm "${WEBAPPROOT}/data"/*
  git reset --hard
  git status

  cd "${TEMPDIR}"

  # http://robbiemackay.com/2013/05/03/automating-behat-and-mink-tests-with-travis-ci/
  # webserver setup
  php -S 127.0.0.1:8000 -t "${WEBAPPROOT}" 1> php.stdout 2> php.stderr &
  sleep 1 # how could we get rid of this stupid sleep?

  ls -l "${WEBAPPROOT}/index.php" >/dev/null || { echo "ouch" && exit 2 ; }

  curl --silent --show-error \
    --url "${BASE_URL}" \
    --data-urlencode "setlogin=${USERNAME}" \
    --data-urlencode "setpassword=${PASSWORD}" \
    --data-urlencode "continent=Europe" \
    --data-urlencode "city=Brussels" \
    --data-urlencode "title=Review Shaarli" \
    --data-urlencode "Save=Save config" \
    --output /dev/null

  # execute each test
  /usr/bin/env bash "${tst}"
  code=$?

  killall php 1>/dev/null 2>&1
  wait
  cd "${TEMPDIR}"

  if [ ${code} -ne 0 ] ; then
    for f in curl.* "${WEBAPPROOT}/data/log.txt" "${WEBAPPROOT}/data/ipbans.php" "${WEBAPPROOT}/data/config.php" ; do
      printf " _\$_cat%-50s\n" "_${f}_" | tr ' _' '# '
      cat "${f}"
    done
    echo " "
  fi
  echo -n "travis_fold:end:${test_name}\r"

  if [ ${code} -eq 0 ] ; then
    echo "${FGC_GREEN}ok ${test_counter}${FGC_NONE} - ${test_name}"
  else
    echo "${FGC_RED}not ok ${test_counter}${FGC_NONE} - ${test_name} (code: ${code})"
    status_code=1
  fi
  test_counter=$((test_counter+1))
done

rm -rf "${TEMPDIR}"
exit ${status_code}
