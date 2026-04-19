<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use DOM\ORM\Storage\StorageService;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/VirtualFile.php';
require __DIR__ . '/VirtualFolder.php';
require __DIR__ . '/VirtualFilesystemManager.php';

\putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . __DIR__ . '/storage');
\putenv('DOM_ORM_FILENAME=data.xml');

$storageDir = __DIR__ . '/storage';
if (!\is_dir($storageDir) && !\mkdir($storageDir, 0755, true) && !\is_dir($storageDir)) {
    throw new \RuntimeException('Unable to create storage directory: ' . $storageDir);
}

$dataFile = $storageDir . '/data.xml';
if (\is_file($dataFile)) {
    \unlink($dataFile);
}

// Expected XML tree:
//
// <data>
//   <item type="file" id="...">           <!-- root-level file -->
//     <fragment name="name">readme.txt</fragment>
//     ...
//   </item>
//   <item type="folder" id="...">         <!-- /documents/ -->
//     <fragment name="name">documents</fragment>
//     <group type="files">
//       <item type="file" id="...">       <!-- documents/notes.txt -->
//         ...
//       </item>
//     </group>
//     <group type="folders">
//       <item type="folder" id="...">     <!-- documents/work/ -->
//         <fragment name="name">work</fragment>
//         <group type="files">
//           <item type="file" id="...">   <!-- documents/work/report.json -->
//             ...
//           </item>
//         </group>
//       </item>
//     </group>
//   </item>
// </data>

$manager = new VirtualFilesystemManager();

// Root-level file
$manager->addFile('readme.txt', 'text/plain', 'This is the virtual filesystem root.');

// /documents/ folder with one file and one sub-folder
$manager->addFolder('documents');
$manager->addFileToFolder('documents', 'notes.txt', 'text/plain', 'Meeting notes go here.');
$manager->addFolderToFolder('documents', 'work');
$manager->addFileToFolder('work', 'report.json', 'application/json', '{"status":"done","progress":100}');

$xml = StorageService::fromConfig()->read();

echo 'Leaf installed: ' . (InstalledVersions::isInstalled('leafs/leaf') ? 'yes' : 'no') . PHP_EOL;
echo 'XML file: ' . $dataFile . PHP_EOL;
echo PHP_EOL;
echo $xml . PHP_EOL;
