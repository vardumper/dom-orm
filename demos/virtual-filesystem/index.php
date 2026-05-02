<?php

declare(strict_types=1);

use DOM\ORM\Storage\StorageService;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/models/VirtualFile.php';
require __DIR__ . '/models/VirtualFolder.php';
require __DIR__ . '/service/VirtualFilesystemManager.php';

\putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . __DIR__ . '/storage');
\putenv('DOM_ORM_FILENAME=data.xml');

$storageDir = __DIR__ . '/storage';
if (!\is_dir($storageDir) && !\mkdir($storageDir, 0755, true) && !\is_dir($storageDir)) {
    throw new \RuntimeException('Unable to create storage directory: ' . $storageDir);
}

// Seed only when no data file exists yet.
if (!\is_file($storageDir . '/data.xml')) {
    $seed = new VirtualFilesystemManager();
    $seed->addFile('readme.txt', 'text/plain', 'This is the virtual filesystem root.');
    $seed->addFolder('documents');
    $seed->addFileToFolder('documents', 'notes.txt', 'text/plain', 'Meeting notes go here.');
    $seed->addFolderToFolder('documents', 'work');
    $seed->addFileToFolder('work', 'report.json', 'application/json', '{"status":"done","progress":100}');
}

function renderHtml(): string
{
    $xml = StorageService::fromConfig()->read();
    $doc = new DOMDocument();
    $doc->loadXML($xml);

    $xsl = new DOMDocument();
    $xsl->load(__DIR__ . '/templates/filesystem.xsl');

    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $proc->setParameter('', 'raw-xml', $xml);

    return (string)$proc->transformToXML($doc);
}

function jsonOk(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $message,
    ]);
    exit;
}

// ── Routes ───────────────────────────────────────────────────────────────────

app()->get('/', function () {
    echo renderHtml();
});

// Folder endpoints ────────────────────────────────────────────────────────────

app()->post('/api/folder/add', function () {
    $name = trim((string)(request()->get('name') ?? ''));
    $parentId = trim((string)(request()->get('parentId') ?? ''));

    if ($name === '') {
        jsonError('name is required');
    }

    try {
        $manager = new VirtualFilesystemManager();
        $id = $manager->addFolderById($parentId !== '' ? $parentId : null, $name);
        jsonOk([
            'success' => true,
            'id' => $id,
        ], 201);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->post('/api/folder/rename', function () {
    $id = trim((string)(request()->get('id') ?? ''));
    $name = trim((string)(request()->get('name') ?? ''));

    if ($id === '' || $name === '') {
        jsonError('id and name are required');
    }

    try {
        $manager = new VirtualFilesystemManager();
        $manager->renameFolder($id, $name);
        jsonOk([
            'success' => true,
        ]);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->post('/api/folder/remove', function () {
    $id = trim((string)(request()->get('id') ?? ''));

    if ($id === '') {
        jsonError('id is required');
    }

    try {
        $manager = new VirtualFilesystemManager();
        $manager->removeById($id);
        jsonOk([
            'success' => true,
        ]);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

// File endpoints ──────────────────────────────────────────────────────────────

app()->post('/api/file/add', function () {
    $parentId = trim((string)(request()->get('parentId') ?? ''));
    $name = trim((string)(request()->get('name') ?? ''));
    $mimeType = trim((string)(request()->get('mimeType') ?? 'application/octet-stream'));
    $content = (string)(request()->get('content') ?? '');
    $size = trim((string)(request()->get('size') ?? '0'));

    if ($name === '') {
        jsonError('name is required');
    }

    $sizeInt = (int)$size;
    if ($sizeInt > 500 * 1024) {
        jsonError('File exceeds the maximum allowed size of 500 KB.', 413);
    }

    try {
        $manager = new VirtualFilesystemManager();
        if ($parentId !== '') {
            $id = $manager->addFileById($parentId, $name, $mimeType, $content, $sizeInt);
        } else {
            $id = $manager->addEncodedFileToRoot($name, $mimeType, $content, $sizeInt);
        }
        jsonOk([
            'success' => true,
            'id' => $id,
        ], 201);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->get('/api/file/content', function () {
    $id = trim((string)(request()->get('id') ?? ''));

    if ($id === '') {
        jsonError('id is required');
    }

    try {
        $repository = new \DOM\ORM\Repository\EntityRepository(VirtualFile::class);
        $file = $repository->find($id);
        if (!$file instanceof VirtualFile) {
            jsonError('File not found', 404);
        }
        jsonOk([
            'name' => $file->getName(),
            'mimeType' => $file->getMimeType(),
            'content' => $file->getContent(), // base64-encoded
            'size' => $file->getSize(),
        ]);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->post('/api/file/rename', function () {
    $id = trim((string)(request()->get('id') ?? ''));
    $name = trim((string)(request()->get('name') ?? ''));

    if ($id === '' || $name === '') {
        jsonError('id and name are required');
    }

    try {
        $manager = new VirtualFilesystemManager();
        $manager->renameFile($id, $name);
        jsonOk([
            'success' => true,
        ]);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->get('/api/xml', function () {
    header('Content-Type: application/xml');
    echo StorageService::fromConfig()->read();
});

app()->get('/api/table', function () {
    $xml = StorageService::fromConfig()->read();
    $doc = new DOMDocument();
    $doc->loadXML($xml);

    $xsl = new DOMDocument();
    $xsl->load(__DIR__ . '/templates/table.xsl');

    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);

    header('Content-Type: text/html; charset=UTF-8');
    echo (string)$proc->transformToXML($doc);
});

app()->post('/api/file/remove', function () {
    $id = trim((string)(request()->get('id') ?? ''));

    if ($id === '') {
        jsonError('id is required');
    }

    try {
        $manager = new VirtualFilesystemManager();
        $manager->removeById($id);
        jsonOk([
            'success' => true,
        ]);
    } catch (\Throwable $e) {
        jsonError($e->getMessage());
    }
});

app()->run();
