<?php

declare(strict_types=1);

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use DOM\ORM\Traits\EntityManagerTrait;

final class VirtualFilesystemManager
{
    use EntityManagerTrait;

    public function addFile(string $name, string $mimeType, string $content): void
    {
        $encoded = base64_encode($content);
        $this->persist(new VirtualFile($name, $mimeType, $encoded, strlen($content)));
    }

    public function addFolder(string $name): void
    {
        $this->persist(new VirtualFolder($name));
    }

    public function addFileToFolder(string $folderName, string $fileName, string $mimeType, string $content): void
    {
        $repository = new EntityRepository(VirtualFolder::class);
        $folder = $repository->findOneBy([
            'name' => $folderName,
        ]);
        if (!$folder instanceof VirtualFolder) {
            throw new \RuntimeException('Folder not found: ' . $folderName);
        }

        $encoded = base64_encode($content);
        $file = new VirtualFile($fileName, $mimeType, $encoded, strlen($content));
        $this->appendEntityToFolderGroup($folder->getId(), 'files', $file);
    }

    public function addFolderToFolder(string $parentFolderName, string $childFolderName): void
    {
        $repository = new EntityRepository(VirtualFolder::class);
        $parentFolder = $repository->findOneBy([
            'name' => $parentFolderName,
        ]);
        if (!$parentFolder instanceof VirtualFolder) {
            throw new \RuntimeException('Parent folder not found: ' . $parentFolderName);
        }

        $childFolder = new VirtualFolder($childFolderName);
        $this->appendEntityToFolderGroup($parentFolder->getId(), 'folders', $childFolder);
    }

    public function addFolderById(?string $parentId, string $name): string
    {
        $folder = new VirtualFolder($name);

        if (empty($parentId)) {
            $this->persist($folder);
        } else {
            $this->appendEntityToFolderGroup($parentId, 'folders', $folder);
        }

        return $folder->getId();
    }

    /**
     * Add a pre-base64-encoded file at the root level (for API uploads).
     */
    public function addEncodedFileToRoot(string $name, string $mimeType, string $encodedContent, int $size): string
    {
        $file = new VirtualFile($name, $mimeType, $encodedContent, $size);
        $this->persist($file);

        return $file->getId();
    }

    public function addFileById(string $parentId, string $name, string $mimeType, string $content, int $size = 0): string
    {
        $file = new VirtualFile($name, $mimeType, $content, $size);
        $this->appendEntityToFolderGroup($parentId, 'files', $file);

        return $file->getId();
    }

    public function renameFolder(string $id, string $name): void
    {
        $repository = new EntityRepository(VirtualFolder::class);
        $folder = $repository->find($id);
        if (!$folder instanceof VirtualFolder) {
            throw new \RuntimeException('Folder not found: ' . $id);
        }
        $folder->setName($name);
        $this->persist($folder);
    }

    public function renameFile(string $id, string $name): void
    {
        $repository = new EntityRepository(VirtualFile::class);
        $file = $repository->find($id);
        if (!$file instanceof VirtualFile) {
            throw new \RuntimeException('File not found: ' . $id);
        }
        $file->setName($name);
        $this->persist($file);
    }

    private function appendEntityToFolderGroup(string $folderId, string $groupType, EntityInterface $entity): void
    {
        $this->withWriteLock(function () use ($folderId, $groupType, $entity): void {
            $folderQuery = sprintf('//item[@type="folder" and @id="%s"]', $folderId);
            $folderNodes = $this->xpath->query($folderQuery);
            $folderNode = ($folderNodes === false) ? null : $folderNodes->item(0);
            if (!$folderNode instanceof \DOMElement) {
                throw new \RuntimeException('Parent folder not found: ' . $folderId);
            }

            $groupQuery = sprintf('./group[@type="%s"]', $groupType);
            $groupNodes = $this->xpath->query($groupQuery, $folderNode);
            $groupNode = ($groupNodes === false) ? null : $groupNodes->item(0);
            if (!$groupNode instanceof \DOMElement) {
                $groupNode = $this->data->createElement('group');
                $groupNode->setAttribute('type', $groupType);
                $folderNode->appendChild($groupNode);
            }

            $array = $this->serializer->normalize($entity, SchemaNormalizer::FORMAT);
            $xml = $this->serializer->encode($array, SchemaEncoder::FORMAT);
            $tmp = $this->getEmptyDom();
            $tmp->loadXML($xml);
            $importedNode = $this->data->importNode($tmp->documentElement, true);
            $groupNode->appendChild($importedNode);
        });
    }

}
