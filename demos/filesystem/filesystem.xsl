<?xml version="1.0" encoding="UTF-8"?>
<!--
  DOM-ORM Virtual Filesystem — XSLT stylesheet
  ═══════════════════════════════════════════════════════════════════════════
  The XML storage IS the tree.

  Subfolders live inside  <group type="fs_folder">  inside their parent item.
  Files live inside       <group type="fs_file">    inside their parent item.

  No xsl:key, no parentId lookups, no JOIN-like indirection.
  XSLT just walks the natural <group>/<item> nesting that mirrors
  the filesystem hierarchy exactly.
-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:output method="html" encoding="UTF-8" indent="yes"/>

  <!-- ── Entry point ──────────────────────────────────────────────────────── -->
  <xsl:template match="/data">
    <ul class="tree">
      <xsl:apply-templates select="item[@type='fs_folder']"/>
    </ul>
  </xsl:template>

  <!-- ── Folder node ──────────────────────────────────────────────────────── -->
  <xsl:template match="item[@type='fs_folder']">
    <xsl:variable name="id"   select="@id"/>
    <xsl:variable name="name" select="fragment[@name='name']"/>
    <li class="folder" data-id="{$id}" data-type="folder">
      <span class="icon">&#x1F4C1;</span>
      <span class="label"><xsl:value-of select="$name"/></span>
      <span class="actions">
        <!-- Root folder is protected: rename/move/remove hidden -->
        <xsl:if test="not(@id = 'folder-root')">
          <button class="btn-rename"    data-id="{$id}" data-type="folder" title="Rename">&#x270F;&#xFE0F;</button>
          <button class="btn-move-up"   data-id="{$id}" title="Move up">&#x2B06;&#xFE0F;</button>
          <button class="btn-move-down" data-id="{$id}" title="Move down">&#x2B07;&#xFE0F;</button>
          <button class="btn-indent"    data-id="{$id}" title="Make child of previous sibling">&#x27A1;&#xFE0F;</button>
          <button class="btn-outdent"   data-id="{$id}" title="Move to parent level">&#x2B05;&#xFE0F;</button>
        </xsl:if>
        <button class="btn-add-folder" data-id="{$id}" title="Add subfolder">&#x1F4C1;+</button>
        <button class="btn-add-file"   data-id="{$id}" title="Add file">&#x1F4C4;+</button>
        <xsl:if test="not(@id = 'folder-root')">
          <button class="btn-remove" data-id="{$id}" data-type="folder" title="Remove folder (and all contents)">&#x1F5D1;&#xFE0F;</button>
        </xsl:if>
      </span>
      <ul>
        <!--
          Natural tree traversal — the XML structure IS the hierarchy.
          No xsl:key lookups, no parentId fragments required.
        -->
        <xsl:apply-templates select="group[@type='fs_folder']/item[@type='fs_folder']"/>
        <xsl:apply-templates select="group[@type='fs_file']/item[@type='fs_file']"/>
      </ul>
    </li>
  </xsl:template>

  <!-- ── File node ────────────────────────────────────────────────────────── -->
  <xsl:template match="item[@type='fs_file']">
    <xsl:variable name="id"   select="@id"/>
    <xsl:variable name="name" select="fragment[@name='name']"/>
    <li class="file" data-id="{$id}" data-type="file">
      <span class="icon">&#x1F4C4;</span>
      <span class="label"><xsl:value-of select="$name"/></span>
      <span class="actions">
        <button class="btn-rename"    data-id="{$id}" data-type="file" title="Rename">&#x270F;&#xFE0F;</button>
        <button class="btn-move-up"   data-id="{$id}" title="Move up">&#x2B06;&#xFE0F;</button>
        <button class="btn-move-down" data-id="{$id}" title="Move down">&#x2B07;&#xFE0F;</button>
        <button class="btn-indent"    data-id="{$id}" title="Make child of previous sibling folder">&#x27A1;&#xFE0F;</button>
        <button class="btn-outdent"   data-id="{$id}" title="Move to parent level">&#x2B05;&#xFE0F;</button>
        <button class="btn-remove"    data-id="{$id}" data-type="file" title="Remove file">&#x1F5D1;&#xFE0F;</button>
      </span>
    </li>
  </xsl:template>

</xsl:stylesheet>
