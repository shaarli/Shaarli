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

fetch_token() {
  echo "GET $1" 1>&2
  # http://unix.stackexchange.com/a/157219
  LOCATION=$(curl --get --url "$1" \
    --cookie curl.cook --cookie-jar curl.cook \
    --location --output curl.tmp.html \
    --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
    --write-out '%{url_effective}' 2>/dev/null)
  # todo:
  errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
  [ "${errmsg}" = "" ] || assert_fail 107 "error: '${errmsg}'"
  echo $(xmllint --html --nowarning --xpath 'string(/html/body//form[@name="loginform"]//input[@name="token"]/@value)' curl.tmp.html)
  # string(..) http://stackoverflow.com/a/18390404
}

echo "#### Test wrong token"
rm curl.*
LOCATION="${BASE_URL}/?do=login"
TOKEN="just some bogus"

echo "POST ${LOCATION}"
LOCATION=$(curl --url "${LOCATION}" \
  --data-urlencode "login=${USERNAME}" \
  --data-urlencode "password=${PASSWORD}" \
  --data-urlencode "token=${TOKEN}" \
  --data-urlencode "returnurl=${BASE_URL}/?do=changepasswd" \
  --cookie curl.cook --cookie-jar curl.cook \
  --location --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{url_effective}' 2>/dev/null)
errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
[ "${errmsg}" = "alert(\"Wrong login/password.\");document.location='?do=login';" ] || assert_fail 59 "error: '${errmsg}'"



echo "#### Test wrong username"
rm curl.*
LOCATION="${BASE_URL}/?do=login"
TOKEN="$(fetch_token "${LOCATION}")"
# the precise length doesn't matter, it just has to be significantly larger than ''
[ $(printf "%s" ${TOKEN} | wc -c) -eq 40 ] || assert_fail 68 "expected TOKEN of 40 characters, but found ${TOKEN} of $(printf "%s" ${TOKEN} | wc -c)"

echo "POST ${LOCATION}"
LOCATION=$(curl --url "${LOCATION}" \
  --data-urlencode "login=f o o" \
  --data-urlencode "password=${PASSWORD}" \
  --data-urlencode "token=${TOKEN}" \
  --data-urlencode "returnurl=${BASE_URL}/?do=changepasswd" \
  --cookie curl.cook --cookie-jar curl.cook \
  --location --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{url_effective}' 2>/dev/null)
errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
[ "${errmsg}" = "alert(\"Wrong login/password.\");document.location='?do=login';" ] || assert_fail 81 "error: '${errmsg}'"



echo "#### Test wrong password"
rm curl.*
LOCATION="${BASE_URL}/?do=login"
TOKEN="$(fetch_token "${LOCATION}")"
# the precise length doesn't matter, it just has to be significantly larger than ''
[ $(printf "%s" ${TOKEN} | wc -c) -eq 40 ] || assert_fail 90 "expected TOKEN of 40 characters, but found ${TOKEN} of $(printf "%s" ${TOKEN} | wc -c)"

echo "POST ${LOCATION}"
LOCATION=$(curl --url "${LOCATION}" \
  --data-urlencode "login=${USERNAME}" \
  --data-urlencode "password=f o o" \
  --data-urlencode "token=${TOKEN}" \
  --data-urlencode "returnurl=${BASE_URL}/?do=changepasswd" \
  --cookie curl.cook --cookie-jar curl.cook \
  --location --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{url_effective}' 2>/dev/null)
errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
[ "${errmsg}" = "alert(\"Wrong login/password.\");document.location='?do=login';" ] || assert_fail 103 "error: '${errmsg}'"



echo "#### Test wrong password (again)"
rm curl.*
LOCATION="${BASE_URL}/?do=login"
TOKEN="$(fetch_token "${LOCATION}")"
# the precise length doesn't matter, it just has to be significantly larger than ''
[ $(printf "%s" ${TOKEN} | wc -c) -eq 40 ] || assert_fail 112 "expected TOKEN of 40 characters, but found ${TOKEN} of $(printf "%s" ${TOKEN} | wc -c)"

echo "POST ${LOCATION}"
LOCATION=$(curl --url "${LOCATION}" \
  --data-urlencode "login=${USERNAME}" \
  --data-urlencode "password=f o o" \
  --data-urlencode "token=${TOKEN}" \
  --data-urlencode "returnurl=${BASE_URL}/?do=changepasswd" \
  --cookie curl.cook --cookie-jar curl.cook \
  --location --output curl.tmp.html \
  --trace-ascii curl.tmp.trace --dump-header curl.tmp.head \
  --write-out '%{url_effective}' 2>/dev/null)
errmsg=$(xmllint --html --nowarning --xpath 'string(/html[1 = count(*)]/head[1 = count(*)]/script[starts-with(.,"alert(")])' curl.tmp.html)
[ "${errmsg}" = "alert(\"Wrong login/password.\");document.location='?do=login';" ] || assert_fail 125 "error: '${errmsg}'"



echo "#### Test banned ip (4 previous failures)"
rm curl.*
LOCATION="${BASE_URL}/?do=login"
TOKEN="$(fetch_token "${LOCATION}")"
errmsg=$(xmllint --html --nowarning --xpath 'string(normalize-space(/html/body//*[@id="headerform"]))' curl.tmp.html)
assert_equal "You have been banned from login after too many failed attempts. Try later." "${errmsg}" 134 "expected ban."
