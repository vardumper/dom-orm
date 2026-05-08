<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <!-- Named template: emits the full filesystem table -->
    <xsl:template name="render-table">
        <table>
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Type</th>
                    <th scope="col">Size</th>
                    <th scope="col">Created</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="1" style="padding-bottom:4px;padding-top:4px;padding-left:24px;"><xsl:copy-of select="document('../assets/images/folder.svg')/*"/></td><td colspan="4"><em>Root</em></td>
                </tr>
                <xsl:variable name="root-files" select="item[@type='file']"/>
                <xsl:for-each select="item[@type='folder']">
                    <xsl:apply-templates select=".">
                        <xsl:with-param name="prefix" select="''"/>
                        <xsl:with-param name="is-last"
                            select="position() = last() and count($root-files) = 0"/>
                    </xsl:apply-templates>
                </xsl:for-each>
                <xsl:for-each select="$root-files">
                    <xsl:apply-templates select=".">
                        <xsl:with-param name="prefix" select="''"/>
                        <xsl:with-param name="is-last" select="position() = last()"/>
                    </xsl:apply-templates>
                </xsl:for-each>
            </tbody>
        </table>
    </xsl:template>

    <!-- Folder row + recurse into children -->
    <xsl:template match="item[@type='folder']">
        <xsl:param name="prefix" select="''"/>
        <xsl:param name="is-last" select="false()"/>

        <tr>
            <td>
                <xsl:call-template name="render-prefix">
                    <xsl:with-param name="prefix" select="$prefix"/>
                </xsl:call-template>
                <xsl:choose>
                    <xsl:when test="$is-last">
                        <img src="assets/images/terminal-node.svg" alt="&#x2514;&#x2500;&#x2500; " class="tree-node"/>
                    </xsl:when>
                    <xsl:otherwise>
                        <img src="assets/images/intermediate-node.svg" alt="&#x251C;&#x2500;&#x2500; " class="tree-node"/>
                    </xsl:otherwise>
                </xsl:choose>
                <xsl:text> </xsl:text><img src="assets/images/folder.svg" alt=""/><xsl:text> </xsl:text><span contenteditable="true" spellcheck="false" data-id="{@id}" data-type="folder"><xsl:value-of select="fragment[@name='name']"/></span><span class="row-actions">
                    <button class="secondary" data-target="dialog-add-folder" data-parent-id="{@id}" title="New folder inside"><xsl:copy-of select="document('../assets/images/folder-add.svg')/*"/></button>
                    <xsl:text> </xsl:text><button class="contrast" data-target="dialog-upload-file" data-parent-id="{@id}" data-parent-name="{fragment[@name='name']}" title="Upload file into folder"><xsl:copy-of select="document('../assets/images/document-add.svg')/*"/></button>
                    <xsl:text> </xsl:text><button class="delete" data-action="delete" data-id="{@id}" data-type="folder" title="Delete folder"><xsl:copy-of select="document('../assets/images/trash.svg')/*"/></button>
                </span>
            </td>
            <td>folder</td>
            <td>
                <xsl:value-of select="count(group[@type='folders']/item) + count(group[@type='files']/item)"/>
                <xsl:text> items</xsl:text>
            </td>
            <td>
                <time datetime="{fragment[@name='createdAt']}">
                    <xsl:call-template name="format-datetime">
                        <xsl:with-param name="dt" select="fragment[@name='createdAt']"/>
                    </xsl:call-template>
                </time>
            </td>
        </tr>

        <!--
            child-prefix: append '1' (show stem) when current is NOT last,
                          append '0' (show blank) when current IS last.
        -->
        <xsl:variable name="child-prefix"
            select="concat($prefix, substring('10', 1 + boolean($is-last), 1))"/>

        <xsl:variable name="child-files" select="group[@type='files']/item[@type='file']"/>

        <xsl:for-each select="group[@type='folders']/item[@type='folder']">
            <xsl:apply-templates select=".">
                <xsl:with-param name="prefix" select="$child-prefix"/>
                <xsl:with-param name="is-last"
                    select="position() = last() and count($child-files) = 0"/>
            </xsl:apply-templates>
        </xsl:for-each>

        <xsl:for-each select="$child-files">
            <xsl:apply-templates select=".">
                <xsl:with-param name="prefix" select="$child-prefix"/>
                <xsl:with-param name="is-last" select="position() = last()"/>
            </xsl:apply-templates>
        </xsl:for-each>
    </xsl:template>

    <!-- File row -->
    <xsl:template match="item[@type='file']">
        <xsl:param name="prefix" select="''"/>
        <xsl:param name="is-last" select="false()"/>

        <tr>
            <td>
                <xsl:call-template name="render-prefix">
                    <xsl:with-param name="prefix" select="$prefix"/>
                </xsl:call-template>
                <xsl:choose>
                    <xsl:when test="$is-last">
                        <img src="assets/images/terminal-node.svg" alt="&#x2514;&#x2500;&#x2500; " class="tree-node"/>
                    </xsl:when>
                    <xsl:otherwise>
                        <img src="assets/images/intermediate-node.svg" alt="&#x251C;&#x2500;&#x2500; " class="tree-node"/>
                    </xsl:otherwise>
                </xsl:choose>
                <xsl:text> </xsl:text><img src="assets/images/document.svg" alt=""/><xsl:text> </xsl:text><span contenteditable="true" spellcheck="false" data-id="{@id}" data-type="file"><xsl:value-of select="fragment[@name='name']"/></span><span class="row-actions">
                    <button class="secondary" data-action="preview" data-id="{@id}" data-name="{fragment[@name='name']}" data-mime="{fragment[@name='mimeType']}" title="Preview file"><xsl:copy-of select="document('../assets/images/eye.svg')/*"/></button>
                    <xsl:text> </xsl:text><button class="delete" data-action="delete" data-id="{@id}" data-type="file" title="Delete file"><xsl:copy-of select="document('../assets/images/trash.svg')/*"/></button>
                </span>
            </td>
            <td><xsl:value-of select="fragment[@name='mimeType']"/></td>
            <td>
                <xsl:call-template name="format-size">
                    <xsl:with-param name="bytes" select="fragment[@name='size']"/>
                </xsl:call-template>
            </td>
            <td>
                <time datetime="{fragment[@name='createdAt']}">
                    <xsl:call-template name="format-datetime">
                        <xsl:with-param name="dt" select="fragment[@name='createdAt']"/>
                    </xsl:call-template>
                </time>
            </td>
        </tr>
    </xsl:template>

    <!-- Human-readable file size -->
    <xsl:template name="format-size">
        <xsl:param name="bytes"/>
        <xsl:choose>
            <xsl:when test="$bytes >= 1073741824">
                <xsl:value-of select="format-number($bytes div 1073741824, '0.#')"/>
                <xsl:text> GB</xsl:text>
            </xsl:when>
            <xsl:when test="$bytes >= 1048576">
                <xsl:value-of select="format-number($bytes div 1048576, '0.#')"/>
                <xsl:text> MB</xsl:text>
            </xsl:when>
            <xsl:when test="$bytes >= 1024">
                <xsl:value-of select="format-number($bytes div 1024, '0.#')"/>
                <xsl:text> KB</xsl:text>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$bytes"/>
                <xsl:text> B</xsl:text>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <!-- Short datetime: 2026-04-24 06:50 -->
    <xsl:template name="format-datetime">
        <xsl:param name="dt"/>
        <xsl:value-of select="substring($dt, 1, 10)"/>
        <xsl:text> </xsl:text>
        <xsl:value-of select="substring($dt, 12, 5)"/>
    </xsl:template>

    <!-- Emit one image per ancestor char in prefix: '1'=stem, '0'=blank -->
    <xsl:template name="render-prefix">
        <xsl:param name="prefix"/>
        <xsl:if test="string-length($prefix) > 0">
            <xsl:choose>
                <xsl:when test="substring($prefix, 1, 1) = '1'">
                    <img src="assets/images/stem-node.svg" alt="&#x2502; " class="tree-node"/>
                </xsl:when>
                <xsl:otherwise>
                    <img src="assets/images/blank-node.svg" alt=" " class="tree-node"/>
                </xsl:otherwise>
            </xsl:choose>
            <xsl:call-template name="render-prefix">
                <xsl:with-param name="prefix" select="substring($prefix, 2)"/>
            </xsl:call-template>
        </xsl:if>
    </xsl:template>

</xsl:stylesheet>
