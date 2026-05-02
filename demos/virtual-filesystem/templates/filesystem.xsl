<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="html" encoding="UTF-8" omit-xml-declaration="yes" indent="yes"/>
    <xsl:strip-space elements="*"/>

    <xsl:param name="raw-xml"/>

    <xsl:include href="_table.xsl"/>

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
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/atom-one-dark.min.css"/>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>
                <script src="assets/js/modal.js"></script>
                <script src="assets/js/filesystem.js" defer="defer"></script>
                <style>
                    table td { padding-top: 0; padding-bottom: 0; vertical-align: middle; }
                    table td img.tree-node { width: 28px; height: 28px; vertical-align: middle; }
                    [contenteditable] { outline: 2px solid transparent; border-radius: 2px; padding: 1px 4px; }
                    [contenteditable]:focus { outline: 2px solid #D47DE4; }
                    html,body {height: 100vh;padding-block: 0;}
                    body>footer {position: sticky;top: 100vh;padding-block:0;}
                    footer {margin-bottom: 0; padding-bottom:0}
                    textarea.code { font-family: monospace; font-size: 0.8em; border-radius: 0; margin-bottom:0; }
                    progress {margin:0;padding:0;border-radius:0;width:100%;visibility:hidden;}
                    #raw-xml-pre { margin: 0; border-radius: 0; overflow-x: auto; }
                    #raw-xml-pre code.hljs { font-size: 0.8em; padding: 1rem; border-radius: 0; }
                    tr:hover td { background-color: #f0f0f0; }
                    .row-actions button { padding: 2px 4px; border: none; box-shadow: none; }
                    .row-actions button svg { width: 20px; height: 20px; vertical-align: middle; display: inline-block; }
                    .row-actions button.delete { background-color: var(--pico-color-red-650) !important }
                    .row-actions { visibility: hidden; white-space: nowrap; margin-left: 6px; }
                    tr:hover .row-actions { visibility: visible; }
                    #dialog-preview article { max-width: 860px; width: 90vw; }
                    #preview-content pre { margin: 0; border-radius: 4px; max-height: 60vh; overflow: auto; }
                    #preview-content pre code.hljs { font-size: 0.8em; border-radius: 4px; }
                    #preview-content img { max-width: 100%; max-height: 60vh; display: block; margin: 0 auto; border-radius: 4px; }
                    #preview-content .preview-unavailable { color: var(--pico-muted-color); text-align: center; padding: 2rem 0; }
                </style>
            </head>
            <body>
                <main class="container">
                    <section>
                        <hgroup>
                            <h1>Virtual Filesystem</h1>
                            <h2>Storing and hydrating PHP objects to and from XML.</h2>
                        </hgroup>
                        <p>
                            This demo uses simple file and folder PHP classes, that can be directly persisted and hydrated to and from XML. 
                            I used XSLT here to render the XML into HTML. If you're interested in other templating engines such as Twig, check out the Blog demo.
                            In this demo, you can rename files &amp; folders by clicking on their names.
                            You can preview, delete and add new files and folders. Every 10 minutes the XML is restored to its initial state. 
                        </p>
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
                    <section id="table-container">
                        <xsl:call-template name="render-table"/>
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
                            <p id="uploadTargetLabel" style="margin:0 0 0.5rem;font-size:0.875em;color:var(--pico-muted-color);">Uploading to: <em>Root</em></p>
                            <label for="uploadFile">File</label>
                            <input type="file" id="uploadFile" required="required"/>
                            <footer>
                                <button class="secondary">Cancel</button>
                                <button>Upload</button>
                            </footer>
                        </article>
                    </dialog>

                    <dialog id="dialog-preview">
                        <article>
                            <header>
                                <button aria-label="Close" rel="prev"></button>
                                <p><strong id="preview-filename">Preview</strong></p>
                            </header>
                            <div id="preview-content"></div>
                            <footer>
                                <button class="secondary">Close</button>
                            </footer>
                        </article>
                    </dialog>
                </main>
                <footer>
                    <strong>Raw XML</strong>
                    <progress />
                    <pre id="raw-xml-pre"><code class="language-xml" id="raw-xml-display"><xsl:value-of select="$raw-xml"/></code></pre>
                </footer>
            </body>
        </html>
    </xsl:template>

</xsl:stylesheet>
