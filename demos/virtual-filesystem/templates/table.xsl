<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" encoding="UTF-8" omit-xml-declaration="yes" indent="yes"/>
    <xsl:strip-space elements="*"/>

    <xsl:include href="_table.xsl"/>

    <!-- AJAX endpoint: return only the table fragment -->
    <xsl:template match="/data">
        <xsl:call-template name="render-table"/>
    </xsl:template>

</xsl:stylesheet>
