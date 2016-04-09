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
CWD="$(pwd)" && cd "$(dirname "$0")" && SCRIPT_DIR="$(pwd)" && cd "${CWD}" && . "${SCRIPT_DIR}/assert.sh"

# Check preliminaries
curl --version >/dev/null       || assert_fail 101 "I need curl."
xmllint --version 2> /dev/null  || assert_fail 102 "I need xmllint (libxml2)."
xsltproc --version 1>/dev/null  || assert_fail 102 "I need xsltproc (libxml2)."
[ "${USERNAME}" != "" ]         || assert_fail 1 "How strange, USERNAME is unset."
[ "${PASSWORD}" != "" ]         || assert_fail 2 "How strange, PASSWORD is unset."
[ "${BASE_URL}" != "" ]         || assert_fail 3 "How strange, BASE_URL is unset."
[ -r "$0".xslt ]                || assert_fail 4 "How strange, helper xslt script '$0.xslt' not readable."

echo "####################################################"
echo "## Step 1: fetch token to login "
echo "GET ${BASE_URL}?do=login"
rm curl.tmp.*
# http://unix.stackexchange.com/a/157219
LOCATION=$(curl --get --url "${BASE_URL}/?do=login" \
  --cookie curl.cook --cookie-jar curl.cook \
  --location --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{url_effective}' 2>/dev/null)
# todo:
errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
assert_equal "" "${errmsg}" 42 "fetch token error"
TOKEN=$(xmllint --html --nowarning --xpath 'string(/html/body//form[@name="loginform"]//input[@name="token"]/@value)' curl.tmp.html)
# string(..) http://stackoverflow.com/a/18390404

# the precise length doesn't matter, it just has to be significantly larger than ''
assert_equal 40 $(printf "%s" ${TOKEN} | wc -c) 47 "expected TOKEN of 40 characters, but found ${TOKEN} of $(printf "%s" ${TOKEN} | wc -c)"

echo "######################################################"
echo "## Step 2: follow the redirect, do the login and redirect to ${BASE_URL}/? "
echo "POST ${LOCATION}"
rm curl.tmp.*
LOCATION=$(curl --url "${LOCATION}" \
  --data-urlencode "login=${USERNAME}" \
  --data-urlencode "password=${PASSWORD}" \
  --data-urlencode "token=${TOKEN}" \
  --data-urlencode "returnurl=${BASE_URL}/?" \
  --cookie curl.cook --cookie-jar curl.cook \
  --location --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{url_effective}' 2>/dev/null)
errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
assert_equal "" "${errmsg}" 64 "do login error"
assert_equal "${BASE_URL}/?" "${LOCATION}" 65 "redirect to BASE_URL"

# check pre-condition
echo "###################################################"
echo "## Logged-in Atom feed prior doing anything (should have 2 entries)"
curl --url "${BASE_URL}/?do=atom" \
  --silent --show-error \
  --cookie curl.cook --cookie-jar curl.cook \
  --output curl.tmp.atom
entries=$(xmllint --xpath 'count(/*/*[local-name()="entry"])' curl.tmp.atom)
assert_equal "2" "${entries}" 74 "Atom feed entries"


# now figure out the precise lf_linkdate and token for each entry to delete
while true
do
  # re-extract the token from the most recent HTTP response as it's consumed after each
  # HTTP request. So a simple for loop doesn't do the trick.

  line="$(xsltproc --html --nonet "$0".xslt curl.tmp.html 2>/dev/null | head -n 1)"
  [ "" = "$line" ] && break

  echo "$line" | while read lf_linkdate token
  do
    echo "lf_linkdate=${lf_linkdate}  token=${token}"
    http_code=$(curl --url "${BASE_URL}/" \
      --data-urlencode "lf_linkdate=${lf_linkdate}" \
      --data-urlencode "token=${token}" \
      --data-urlencode "delete_link=" \
      --cookie curl.cook --cookie-jar curl.cook \
      --location --output curl.tmp.html \
      --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
      --write-out '%{http_code}' 2>/dev/null)
    assert_equal "200" "${http_code}" 92 "POST lf_linkdate=${lf_linkdate}&token=..."
    break # process only one line at a time.
  done
done


# check post-condition
echo "###################################################"
echo "## Logged-in Atom feed after deleting all entries"
curl --url "${BASE_URL}/?do=atom" \
  --silent --show-error \
  --cookie curl.cook --cookie-jar curl.cook \
  --output curl.tmp.atom
entries=$(xmllint --xpath 'count(/*/*[local-name()="entry"])' curl.tmp.atom)
assert_equal "0" "${entries}" 103 "Atom feed entries"