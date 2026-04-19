<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" encoding="UTF-8" omit-xml-declaration="yes" indent="yes"/>
    <xsl:strip-space elements="*"/>

    <xsl:param name="raw-xml"/>

    <!-- Full page -->
    <xsl:template match="/data">
        <html lang="en" data-theme="light">
            <head>
                <meta charset="UTF-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <meta name="color-scheme" content="light" />
                <title>Virtual Filesystem</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css" />
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.colors.min.css" />
                <script src="assets/js/modal.js"></script>
                <script src="assets/js/filesystem.js" defer="defer"></script>
                <style>
                    table td { padding-top: 0; padding-bottom: 0; vertical-align: middle; }
                    table td img.tree-node { width: 28px; height: 28px; vertical-align: middle; }
                    [data-tooltip] { cursor: text!important; border-bottom: none; }
                    [contenteditable] { outline: 2px solid transparent; border-radius: 2px; padding: 1px 4px; }
                    [contenteditable]:focus { outline: 2px solid #D47DE4; }
                    [data-tooltip]:not(a,button,input,[role=button]) { border-bottom: 0px none; }
                    html,body {height: 100vh;padding-block: 0;}
                    body>footer {position: sticky;top: 100vh;padding-block:0;}
                    footer {margin-bottom: 0; padding-bottom:0}
                    textarea.code { font-family: monospace; font-size: 0.8em; border-radius: 0; margin-bottom:0; }
                    progress {margin:0;padding:0;border-radius:0;width:100%;visibility:hidden;}
                    tr:hover td { background-color: #f0f0f0; }
                    td.actions button { padding: 2px 4px; border: none; box-shadow: none; }
                    td.actions button svg { width: 20px; height: 20px; vertical-align: middle; display: inline-block; } 
                    td.actions button.delete { background-color: var(--pico-color-red-650) !important }
                </style>
            </head>
            <body>
                <main class="container">
                    <section>
                        <hgroup>
                            <h1>Virtual Filesystem Demo</h1>
                            <p>This is a demo of a virtual filesystem created with <a href="https://github.com/vardumper/dom-orm">DOM-ORM</a></p>
                        </hgroup>
                        <small>
                            Try renaming files/folders by clicking on their names. Create new folders with the "Add Folder" button.
                        </small>
                    </section>
                    <section>
                        <button class="secondary" data-target="dialog-add-folder">
                            <xsl:copy-of select="document('assets/images/folder-add.svg')/*"/>
                            New Folder
                        </button>
                        <xsl:text> </xsl:text>
                        <button class="contrast" data-target="dialog-upload-file">
                            <xsl:copy-of select="document('assets/images/document-add.svg')/*"/>
                            Upload File
                        </button>
                    </section>
                    <section>
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Size</th>
                                    <th scope="col">Created</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding-bottom:4px;padding-top:4px;padding-left:24px;"><xsl:copy-of select="document('assets/images/folder.svg')/*"/></td><td colspan="4"><em>Root</em></td>
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
                    </section>

                    <dialog id="dialog-add-folder" data-action="/api/folder/add" data-method="post">
                        <article>
                            <header>
                                <button aria-label="Close" rel="prev"></button>
                                <p>
                                    <strong>New Folder</strong>
                                </p>
                            </header>
                            <input type="hidden" name="parentId" id="folderParentId" value=""/>
                            <label for="folderName">Name</label>
                            <input type="text" name="name" id="folderName" autocomplete="off" required="required"/>
                            <footer>
                                <button class="secondary">Cancel</button>
                                <button>Save</button>
                            </footer>
                        </article>
                    </dialog>

                    <dialog id="dialog-upload-file">
                        <article>
                            <header>
                                <button aria-label="Close" rel="prev"></button>
                                <p>
                                    <strong>Upload File</strong>
                                </p>
                            </header>
                            <input type="hidden" name="parentId" id="uploadParentId" value=""/>
                            <label for="uploadFile">File</label>
                            <input type="file" id="uploadFile" required="required"/>
                            <footer>
                                <button class="secondary">Cancel</button>
                                <button>Upload</button>
                            </footer>
                        </article>
                    </dialog>
                </main>
                <footer>
                    <strong>Raw XML</strong>
                    <progress />
                    <textarea class="code" data-theme="dark" rows="20" cols="80" readonly="readonly" spellcheck="false"><xsl:value-of select="$raw-xml"/></textarea>
                </footer>
            </body>
        </html>
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
                <xsl:text> </xsl:text><img src="assets/images/folder.svg" alt=""/><xsl:text> </xsl:text><span data-tooltip="Rename" data-placement="right" contenteditable="true" spellcheck="false" data-id="{@id}" data-type="folder"><xsl:value-of select="fragment[@name='name']"/></span>
            </td>
            <td>folder</td>
            <td>
                <xsl:value-of select="count(group[@type='folders']/item) + count(group[@type='files']/item)"/>
                <xsl:text> items</xsl:text>
            </td>
            <td>
                <time datetime="{fragment[@name='createdAt']}">
                    <xsl:value-of select="fragment[@name='createdAt']"/>
                </time>
            </td>
            <td class="actions">
                <button class="secondary" data-target="dialog-add-folder" data-parent-id="{@id}" title="New folder inside"><xsl:copy-of select="document('assets/images/folder-add.svg')/*"/></button>
                <xsl:text> </xsl:text>
                <button class="contrast" data-target="dialog-upload-file" data-parent-id="{@id}" title="Upload file into folder"><xsl:copy-of select="document('assets/images/document-add.svg')/*"/></button>
                <xsl:text> </xsl:text>
                <button class="delete" data-action="delete" data-id="{@id}" data-type="folder" title="Delete folder"><xsl:copy-of select="document('assets/images/trash.svg')/*"/></button>
            </td>
        </tr>

        <!--
            child-prefix: append '1' (show stem) when current is NOT last,
                          append '0' (show blank) when current IS last.
            substring('10', 1+boolean($is-last), 1):
              is-last=false → 1+0=1 → '1'
              is-last=true  → 1+1=2 → '0'
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
                <xsl:text> </xsl:text><img src="assets/images/document.svg" alt=""/><xsl:text> </xsl:text><span data-tooltip="Rename" data-placement="right" contenteditable="true" spellcheck="false" data-id="{@id}" data-type="file"><xsl:value-of select="fragment[@name='name']"/></span>
            </td>
            <td><xsl:value-of select="fragment[@name='mimeType']"/></td>
            <td>
                <xsl:value-of select="string-length(fragment[@name='content'])"/>
                <xsl:text> bytes</xsl:text>
            </td>
            <td>
                <time datetime="{fragment[@name='createdAt']}">
                    <xsl:value-of select="fragment[@name='createdAt']"/>
                </time>
            </td>
            <td class="actions">
                <button class="delete" data-action="delete" data-id="{@id}" data-type="file" title="Delete file"><xsl:copy-of select="document('assets/images/trash.svg')/*"/></button>
            </td>
        </tr>
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
