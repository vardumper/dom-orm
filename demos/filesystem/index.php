<?php
declare(strict_types=1);

// ─── Point DOM-ORM at this demo's storage folder ────────────────────────────
putenv('DOM_ORM_STORAGE_PATH=' . __DIR__ . '/storage');

require __DIR__ . '/../vendor/autoload.php';

// ─── Auto-create live data.xml from seed if missing ─────────────────────────
$storagePath = __DIR__ . '/storage';
$dataFile    = $storagePath . '/data.xml';
$initialFile = $storagePath . '/data.xml.initial';
if (!file_exists($dataFile) && file_exists($initialFile)) {
    copy($initialFile, $dataFile);
}

// ════════════════════════════════════════════════════════════════════════════
// ENTITIES
// ════════════════════════════════════════════════════════════════════════════
//
// No parentId fragment — the XML tree IS the hierarchy.
// Child folders live inside  <group type="fs_folder">  of their parent item.
// Child files live inside    <group type="fs_file">    of their parent item.
//
// ════════════════════════════════════════════════════════════════════════════

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;
use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Storage\StorageService;
use DOM\ORM\Traits\EntityManagerTrait;

#[ORM\Item(entityType: 'fs_folder')]
class FsFolder extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment] private string $name,
        #[ORM\Group(entity: FsFolder::class, groupType: 'fs_folder')] private array $folders = [],
        #[ORM\Group(entity: FsFile::class, groupType: 'fs_file')]   private array $files = [],
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }

    /** @return FsFolder[] */
    public function getFolders(): array { return $this->folders; }
    /** @param FsFolder[] $v */
    public function setFolders(array $v): static { $this->folders = $v; return $this; }

    /** @return FsFile[] */
    public function getFiles(): array { return $this->files; }
    /** @param FsFile[] $v */
    public function setFiles(array $v): static { $this->files = $v; return $this; }
}

#[ORM\Item(entityType: 'fs_file')]
class FsFile extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment] private string $name,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
}

// ════════════════════════════════════════════════════════════════════════════
// HELPERS
// ════════════════════════════════════════════════════════════════════════════

class FsManager
{
    use EntityManagerTrait;
}
$mgr = new FsManager();

/** Return raw XML string from storage */
function readXml(): string
{
    return StorageService::fromConfig()->read();
}

/** Load DOMDocument from storage */
function loadDom(): DOMDocument
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput     = true;
    $dom->preserveWhiteSpace = false;
    $dom->loadXML(readXml());
    return $dom;
}

/**
 * Save DOMDocument back to storage.
 * Matches dom-orm's internal format: documentElement only, no XML declaration.
 */
function saveDom(DOMDocument $dom): void
{
    StorageService::fromConfig()->write(
        $dom->saveXML($dom->documentElement, LIBXML_NOXMLDECL)
    );
}

/** Find a DOMElement anywhere in the tree by its @id attribute */
function findNode(DOMDocument $dom, string $id): ?DOMElement
{
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query(sprintf('//*[@id="%s"]', addslashes($id)));
    return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
}

/**
 * Find or create the <group type="$groupType"> child of a folder item.
 * If the group is missing it is appended automatically.
 */
function ensureGroupNode(DOMDocument $dom, DOMElement $folderItem, string $groupType): DOMElement
{
    $xpath  = new DOMXPath($dom);
    $groups = $xpath->query(sprintf('group[@type="%s"]', $groupType), $folderItem);
    if ($groups && $groups->length > 0) {
        return $groups->item(0);
    }
    $group = $dom->createElement('group');
    $group->setAttribute('type', $groupType);
    $folderItem->appendChild($group);
    return $group;
}

/** Replace the CDATA content of a <fragment name="name"> inside $node */
function updateNameFragment(DOMDocument $dom, DOMElement $node, string $newName): void
{
    $xpath = new DOMXPath($dom);
    $frag  = $xpath->query('fragment[@name="name"]', $node)->item(0);
    if ($frag instanceof DOMElement) {
        while ($frag->firstChild !== null) {
            $frag->removeChild($frag->firstChild);
        }
        $frag->appendChild($dom->createCDATASection($newName));
    }
}

/** Previous <item> element sibling within the same <group> */
function prevItemSibling(DOMElement $node): ?DOMElement
{
    $sib = $node->previousSibling;
    while ($sib !== null) {
        if ($sib instanceof DOMElement && $sib->nodeName === 'item') {
            return $sib;
        }
        $sib = $sib->previousSibling;
    }
    return null;
}

/** Next <item> element sibling within the same <group> */
function nextItemSibling(DOMElement $node): ?DOMElement
{
    $sib = $node->nextSibling;
    while ($sib !== null) {
        if ($sib instanceof DOMElement && $sib->nodeName === 'item') {
            return $sib;
        }
        $sib = $sib->nextSibling;
    }
    return null;
}

/** JSON response helper — outputs and exits */
function jsonResponse(array $data): never
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// TREE RENDERER  (XSLT stylesheet lives in filesystem.xsl next to this file)
// ════════════════════════════════════════════════════════════════════════════

/**
 * Transform the current data.xml into an HTML tree fragment via XSLT.
 * The stylesheet uses natural XPath traversal — no xsl:key required.
 */
function renderTree(): string
{
    $dom = new DOMDocument();
    $dom->loadXML(readXml());

    $xsl = new DOMDocument();
    $xsl->load(__DIR__ . '/filesystem.xsl');

    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    return (string) $proc->transformToXML($dom);
}

// ════════════════════════════════════════════════════════════════════════════
// ROUTES
// ════════════════════════════════════════════════════════════════════════════

app()->get('/', function () {
    $tree = renderTree();
    $xml  = htmlspecialchars(readXml(), ENT_QUOTES, 'UTF-8');
    echo renderPage($tree, $xml);
});

// ── Reset ──────────────────────────────────────────────────────────────────
app()->post('/api/reset', function () {
    $storageDir = __DIR__ . '/storage';
    copy($storageDir . '/data.xml.initial', $storageDir . '/data.xml');
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Add folder ────────────────────────────────────────────────────────────
app()->post('/api/folder/add', function () use ($mgr) {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $name     = trim($body['name'] ?? 'New Folder');
    $parentId = trim($body['parentId'] ?? 'folder-root');

    // Validate parent exists via the ORM repository
    if (!(new EntityRepository(FsFolder::class))->find($parentId)) {
        jsonResponse(['error' => 'parent folder not found']);
    }

    // Ensure the <group type="fs_folder"> container exists inside the parent item.
    // Groups are schema-level containers; ensureGroupNode only writes when missing.
    $dom    = loadDom();
    $parent = findNode($dom, $parentId);
    ensureGroupNode($dom, $parent, 'fs_folder');
    saveDom($dom);

    // Persist via ORM — the entity is serialized through the full ORM pipeline
    $mgr->persist(
        new FsFolder($name),
        sprintf("//item[@id='%s']/group[@type='fs_folder']", addslashes($parentId))
    );
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Rename folder ─────────────────────────────────────────────────────────
app()->post('/api/folder/rename', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    $name = trim($body['name'] ?? '');
    if ($id === '' || $name === '') { jsonResponse(['error' => 'id and name required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if (!$node) { jsonResponse(['error' => 'not found']); }
    updateNameFragment($dom, $node, $name);
    saveDom($dom);
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Remove folder (nested children removed automatically) ─────────────────
app()->post('/api/folder/remove', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if ($id === '') { jsonResponse(['error' => 'id required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if ($node && $node->parentNode) {
        $node->parentNode->removeChild($node);
        saveDom($dom);
    }
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Add file ──────────────────────────────────────────────────────────────
app()->post('/api/file/add', function () use ($mgr) {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $name     = trim($body['name'] ?? 'new-file.txt');
    $parentId = trim($body['parentId'] ?? 'folder-root');

    // Validate parent folder exists via the ORM repository
    if (!(new EntityRepository(FsFolder::class))->find($parentId)) {
        jsonResponse(['error' => 'parent folder not found']);
    }

    // Ensure the <group type="fs_file"> container exists inside the parent item
    $dom    = loadDom();
    $parent = findNode($dom, $parentId);
    ensureGroupNode($dom, $parent, 'fs_file');
    saveDom($dom);

    // Persist via ORM
    $mgr->persist(
        new FsFile($name),
        sprintf("//item[@id='%s']/group[@type='fs_file']", addslashes($parentId))
    );
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Rename file ───────────────────────────────────────────────────────────
app()->post('/api/file/rename', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    $name = trim($body['name'] ?? '');
    if ($id === '' || $name === '') { jsonResponse(['error' => 'id and name required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if (!$node) { jsonResponse(['error' => 'not found']); }
    updateNameFragment($dom, $node, $name);
    saveDom($dom);
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Remove file ───────────────────────────────────────────────────────────
app()->post('/api/file/remove', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if ($id === '') { jsonResponse(['error' => 'id required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if ($node && $node->parentNode) {
        $node->parentNode->removeChild($node);
        saveDom($dom);
    }
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Move up (swap with previous <item> sibling within same <group>) ────────
app()->post('/api/move/up', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if ($id === '') { jsonResponse(['error' => 'id required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if (!$node) { jsonResponse(['error' => 'not found']); }

    $prev = prevItemSibling($node);
    if ($prev) {
        $node->parentNode->insertBefore($node, $prev);
        saveDom($dom);
    }
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Move down (swap with next <item> sibling within same <group>) ──────────
app()->post('/api/move/down', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if ($id === '') { jsonResponse(['error' => 'id required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if (!$node) { jsonResponse(['error' => 'not found']); }

    $next = nextItemSibling($node);
    if ($next) {
        $nextNext = nextItemSibling($next);
        if ($nextNext) {
            $node->parentNode->insertBefore($node, $nextNext);
        } else {
            $node->parentNode->appendChild($node);
        }
        saveDom($dom);
    }
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Indent (make child of previous sibling folder) ────────────────────────
app()->post('/api/indent', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if ($id === '') { jsonResponse(['error' => 'id required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if (!$node) { jsonResponse(['error' => 'not found']); }

    $prev = prevItemSibling($node);
    if (!$prev || $prev->getAttribute('type') !== 'fs_folder') {
        jsonResponse(['error' => 'no previous folder sibling to indent into']);
    }

    // Node type determines target group (folder→fs_folder group, file→fs_file group)
    $targetGroup = ensureGroupNode($dom, $prev, $node->getAttribute('type'));
    $targetGroup->appendChild($node);
    saveDom($dom);
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ── Outdent (move to containing folder's parent) ──────────────────────────
app()->post('/api/outdent', function () {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if ($id === '') { jsonResponse(['error' => 'id required']); }

    $dom  = loadDom();
    $node = findNode($dom, $id);
    if (!$node) { jsonResponse(['error' => 'not found']); }

    // DOM path: node → <group type="X"> → <item fs_folder> → <group type="fs_folder"> or <data>
    $currentGroup     = $node->parentNode;
    $containingFolder = $currentGroup ? $currentGroup->parentNode : null;
    $outerGroup       = $containingFolder ? $containingFolder->parentNode : null;

    if (!$outerGroup || $outerGroup->nodeName === 'data') {
        jsonResponse(['error' => 'already at the outermost level']);
    }

    // $outerGroup is <group type="fs_folder"> whose parent is the grandparent folder
    $grandparentFolder = $outerGroup->parentNode;
    if (!($grandparentFolder instanceof DOMElement)) {
        jsonResponse(['error' => 'cannot outdent further']);
    }

    // Move node into the grandparent folder's appropriate group
    $targetGroup = ensureGroupNode($dom, $grandparentFolder, $node->getAttribute('type'));
    $targetGroup->appendChild($node);
    saveDom($dom);
    jsonResponse(['tree' => renderTree(), 'xml' => readXml()]);
});

// ════════════════════════════════════════════════════════════════════════════
// PAGE TEMPLATE
// ════════════════════════════════════════════════════════════════════════════

function renderPage(string $tree, string $xmlEncoded): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DOM-ORM Demo — Virtual Filesystem</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #f4f6f8; color: #222; display: flex; flex-direction: column; min-height: 100vh; }
    header { background: #1a1a2e; color: #eee; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; }
    header h1 { font-size: 1.2rem; }
    header a { color: #90caf9; font-size: .85rem; text-decoration: none; }
    main { flex: 1; padding: 1.5rem; padding-bottom: 220px; }
    h2 { margin-bottom: 1rem; font-size: 1rem; color: #555; text-transform: uppercase; letter-spacing: .05em; }
    .toolbar { display: flex; gap: .5rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
    .toolbar button { padding: .4rem .8rem; border: 1px solid #aaa; border-radius: 4px; background: #fff; cursor: pointer; font-size: .85rem; }
    .toolbar button:hover { background: #e8f4fd; }
    .toolbar .btn-reset { border-color: #e57373; color: #c62828; }
    .toolbar .btn-reset:hover { background: #ffebee; }
    ul.tree, ul.tree ul { list-style: none; padding-left: 1.4rem; }
    ul.tree > li { padding-left: 0; }
    li.folder, li.file { padding: .3rem .2rem; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; flex-wrap: wrap; gap: .3rem; }
    li.folder > ul { width: 100%; flex-basis: 100%; margin-top: .2rem; }
    .icon { font-size: 1rem; }
    .label { font-weight: 500; cursor: default; }
    .label.editing { outline: 2px solid #1976d2; border-radius: 3px; padding: 0 4px; background: #fff; }
    .actions { display: flex; gap: .2rem; margin-left: auto; }
    .actions button { background: none; border: none; cursor: pointer; font-size: .9rem; padding: 2px 4px; border-radius: 3px; }
    .actions button:hover { background: #e3e3e3; }
    /* Sticky debug toolbar */
    #debug-footer { position: fixed; bottom: 0; left: 0; right: 0; height: 190px; background: #1e1e2e; border-top: 2px solid #333; display: flex; flex-direction: column; z-index: 1000; }
    #debug-footer .footer-header { background: #2a2a3e; color: #ccc; font-size: .75rem; padding: .3rem .8rem; display: flex; align-items: center; gap: 1rem; user-select: none; }
    #debug-footer .footer-header span { font-weight: bold; color: #90caf9; }
    #debug-footer textarea { flex: 1; resize: none; background: #1e1e2e; color: #a8d8a8; font-family: monospace; font-size: .72rem; border: none; padding: .5rem .8rem; outline: none; }
    .flash { position: fixed; top: 1rem; right: 1rem; background: #43a047; color: #fff; padding: .5rem 1rem; border-radius: 4px; font-size: .85rem; opacity: 0; transition: opacity .3s; pointer-events: none; z-index: 9999; }
    .flash.show { opacity: 1; }
  </style>
</head>
<body>
  <header>
    <h1>📁 DOM-ORM — Virtual Filesystem Demo</h1>
    <a href="https://vardumper.github.io/dom-orm/" target="_blank">docs ↗</a>
  </header>

  <main>
    <div class="toolbar">
      <button id="btn-add-root-folder">📁+ Add Root Folder</button>
      <button id="btn-add-root-file">📄+ Add Root File</button>
      <button class="btn-reset" id="btn-reset">↺ Reset to Initial State</button>
    </div>
    <h2>File Tree</h2>
    <div id="tree-container">
      {$tree}
    </div>
  </main>

  <div id="debug-footer">
    <div class="footer-header">
      <span>DOM-ORM</span> storage/data.xml — live view
    </div>
    <textarea id="xml-view" readonly>{$xmlEncoded}</textarea>
  </div>

  <div class="flash" id="flash"></div>

  <script>
    const flash = (msg) => {
      const el = document.getElementById('flash');
      el.textContent = msg;
      el.classList.add('show');
      setTimeout(() => el.classList.remove('show'), 1800);
    };

    const api = async (url, body = {}) => {
      const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await r.json();
      if (data.error) { flash('Error: ' + data.error); return; }
      document.getElementById('tree-container').innerHTML = data.tree;
      document.getElementById('xml-view').value = data.xml;
      bindEvents();
    };

    const prompt2 = (msg, def = '') => {
      const v = window.prompt(msg, def);
      return v !== null ? v.trim() : null;
    };

    const bindEvents = () => {
      // Rename
      document.querySelectorAll('.btn-rename').forEach(btn => {
        btn.addEventListener('click', async e => {
          const id   = btn.dataset.id;
          const type = btn.dataset.type;
          const label = btn.closest('li').querySelector('.label');
          const name = prompt2('New name:', label.textContent.trim());
          if (!name) return;
          await api(`/api/\${type}/rename`, { id, name });
          flash('Renamed');
        });
      });

      // Remove
      document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', async e => {
          const id   = btn.dataset.id;
          const type = btn.dataset.type;
          if (!confirm(`Remove this \${type}?`)) return;
          await api(`/api/\${type}/remove`, { id });
          flash('Removed');
        });
      });

      // Add subfolder
      document.querySelectorAll('.btn-add-folder').forEach(btn => {
        btn.addEventListener('click', async e => {
          const parentId = btn.dataset.id;
          const name = prompt2('Folder name:', 'New Folder');
          if (!name) return;
          await api('/api/folder/add', { name, parentId });
          flash('Folder added');
        });
      });

      // Add file inside folder
      document.querySelectorAll('.btn-add-file').forEach(btn => {
        btn.addEventListener('click', async e => {
          const parentId = btn.dataset.id;
          const name = prompt2('File name:', 'file.txt');
          if (!name) return;
          await api('/api/file/add', { name, parentId });
          flash('File added');
        });
      });

      // Move up
      document.querySelectorAll('.btn-move-up').forEach(btn => {
        btn.addEventListener('click', async () => {
          await api('/api/move/up', { id: btn.dataset.id });
          flash('Moved up');
        });
      });

      // Move down
      document.querySelectorAll('.btn-move-down').forEach(btn => {
        btn.addEventListener('click', async () => {
          await api('/api/move/down', { id: btn.dataset.id });
          flash('Moved down');
        });
      });

      // Indent
      document.querySelectorAll('.btn-indent').forEach(btn => {
        btn.addEventListener('click', async () => {
          await api('/api/indent', { id: btn.dataset.id });
          flash('Indented');
        });
      });

      // Outdent
      document.querySelectorAll('.btn-outdent').forEach(btn => {
        btn.addEventListener('click', async () => {
          await api('/api/outdent', { id: btn.dataset.id });
          flash('Outdented');
        });
      });
    };

    // Toolbar buttons
    document.getElementById('btn-add-root-folder').addEventListener('click', async () => {
      const name = prompt2('Folder name:', 'New Folder');
      if (!name) return;
      await api('/api/folder/add', { name, parentId: 'folder-root' });
      flash('Folder added');
    });

    document.getElementById('btn-add-root-file').addEventListener('click', async () => {
      const name = prompt2('File name:', 'file.txt');
      if (!name) return;
      await api('/api/file/add', { name, parentId: 'folder-root' });
      flash('File added');
    });

    document.getElementById('btn-reset').addEventListener('click', async () => {
      if (!confirm('Reset to initial state?')) return;
      await api('/api/reset');
      flash('Reset done');
    });

    bindEvents();
  </script>
</body>
</html>
HTML;
}

// ════════════════════════════════════════════════════════════════════════════
// RUN
// ════════════════════════════════════════════════════════════════════════════

app()->run();
