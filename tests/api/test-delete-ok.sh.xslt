<?xml version="1.0" encoding="UTF-8"?>
<!--

 Copyright (c) 2015-2016 Marcus Rohrmoser http://mro.name/me. All rights reserved.

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.


 Find all 'delete_link' POST forms and list lf_linkdate and token.
 
 $ xsltproc - -html ../tests/test-delete-ok.sh.xslt curl.tmp.html

 http://www.w3.org/TR/xslt
-->
<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    exclude-result-prefixes="xsl"
    version="1.0">
  <xsl:output method="text"/>

  <xsl:template match="/">
    <xsl:for-each select="html/body//form[@method='POST' and .//input/@name='delete_link']">
      <xsl:value-of select=".//input[@name='lf_linkdate']/@value"/><xsl:text> </xsl:text><xsl:value-of select=".//input[@name='token']/@value"/><xsl:text>
</xsl:text>
    </xsl:for-each>
  </xsl:template>

</xsl:stylesheet>
