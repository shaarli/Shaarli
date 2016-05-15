#!/usr/bin/env ruby
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
require 'rexml/document'
require 'cgi'

REXML::Document.new($stdin).elements.each('//textarea | //input[@type = "text" or @type = "hidden"]') do |element|
  puts "#{CGI.escape(element.attributes['name'] || '')}=#{CGI.escape(element.attributes['value'] || '')}&"
end
