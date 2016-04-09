#
#  Copyright (c) 2015 Marcus Rohrmoser http://mro.name/me. All rights reserved.
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

# insipired by https://github.com/lehmannro/assert.sh but much more primitive.

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

assert_fail() {
  local CODE="${1}" MESSAGE="${2}"
  echo "${BGC_RED}assert_fail (${CODE}): ${MESSAGE}${FGC_NONE}"
  exit $1
}

assert_equal() {
  local EXPECTED="${1}" ACTUAL="${2}" CODE="${3}" MESSAGE="${4}"
  if [ ! "${EXPECTED}" = "${ACTUAL}" ] ; then
    echo "${BGC_RED}assert_equal${FGC_NONE} (${CODE}): ${MESSAGE}  expected: \"${FGC_GREEN}${EXPECTED}${FGC_NONE}\" != actual: \"${FGC_RED}${ACTUAL}${FGC_NONE}\""
    exit $3
  fi
}
