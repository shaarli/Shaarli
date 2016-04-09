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
[ "${USERNAME}" != "" ]         || assert_fail 1 "How strange, USERNAME is unset."
[ "${PASSWORD}" != "" ]         || assert_fail 2 "How strange, PASSWORD is unset."
[ "${BASE_URL}" != "" ]         || assert_fail 3 "How strange, BASE_URL is unset."

echo "###################################################"
echo "## non-logged-in GET /?post return: 302 "
http_code=$(curl --url "${BASE_URL}/?post" \
  --cookie curl.cook --cookie-jar curl.cook \
  --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{http_code}' 2>/dev/null)
assert_equal 302 "${http_code}" 35 "login check."

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
[ "${errmsg}" = "" ] || assert_fail 107 "error: '${errmsg}'"
TOKEN=$(xmllint --html --nowarning --xpath 'string(/html/body//form[@name="loginform"]//input[@name="token"]/@value)' curl.tmp.html)
# string(..) http://stackoverflow.com/a/18390404

# the precise length doesn't matter, it just has to be significantly larger than ''
[ $(printf "%s" ${TOKEN} | wc -c) -eq 40 ] || assert_fail 6 "expected TOKEN of 40 characters, but found ${TOKEN} of $(printf "%s" ${TOKEN} | wc -c)"

echo "######################################################"
echo "## Step 2: follow the redirect, do the login and redirect to ?do=changepasswd "
echo "POST ${LOCATION}"
rm curl.tmp.*
LOCATION=$(curl --url "${LOCATION}" \
  --data-urlencode "login=${USERNAME}" \
  --data-urlencode "password=${PASSWORD}" \
  --data-urlencode "token=${TOKEN}" \
  --data-urlencode "returnurl=${BASE_URL}/?do=changepasswd" \
  --cookie curl.cook --cookie-jar curl.cook \
  --location --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{url_effective}' 2>/dev/null)
# todo:
errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
[ "${errmsg}" = "" ] || assert_fail 108 "error: '${errmsg}'"
[ "${BASE_URL}/?do=changepasswd" = "${LOCATION}" ] || assert_fail 108 "expected to be redirected to do=changepassword, but got '${LOCATION}'"

# [ 1 -eq $(xmllint --html --nowarning --xpath "count(/html/body//a[@href = '?do=logout'])" curl.tmp.html 2>/dev/null) ] || assert_fail 13 "I expected a logout link."

# check presence of various mandatory form fields:
for field in oldpassword setpassword token
do
  [ $(xmllint --html --nowarning --xpath "count(/html/body//form[@name = 'changepasswordform']//input[@name='${field}'])" curl.tmp.html) -eq 1 ] || assert_fail 8 "expected to have a '${field}'"
done


echo "###################################################"
echo "## logged-in GET /?post return: 200 "
http_code=$(curl --url "${BASE_URL}/?post" \
  --cookie curl.cook --cookie-jar curl.cook \
  --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{http_code}' 2>/dev/null)
assert_equal 200 "${http_code}" 90 "login check."
