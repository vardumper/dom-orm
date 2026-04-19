<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;

final class VirtualFilesystemManager
{
    use EntityManagerTrait;

    public function addFile(string $name, string $mimeType, string $content): void
    {
        $this->persist(new VirtualFile($name, $mimeType, $content));
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

        $file = new VirtualFile($fileName, $mimeType, $content);
        $folder->addFile($file);
        $this->persist($folder);
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
        $parentFolder->addFolder($childFolder);
        $this->persist($parentFolder);
    }

    public function addFolderById(?string $parentId, string $name): string
    {
        $folder = new VirtualFolder($name);

        if (empty($parentId)) {
            $this->persist($folder);
        } else {
            $repository = new EntityRepository(VirtualFolder::class);
            $parent = $repository->find($parentId);
            if (!$parent instanceof VirtualFolder) {
                throw new \RuntimeException('Parent folder not found: ' . $parentId);
            }
            $parent->addFolder($folder);
            $this->persist($parent);
        }

        return $folder->getId();
    }

    public function addFileById(string $parentId, string $name, string $mimeType, string $content): string
    {
        $repository = new EntityRepository(VirtualFolder::class);
        $parent = $repository->find($parentId);
        if (!$parent instanceof VirtualFolder) {
            throw new \RuntimeException('Parent folder not found: ' . $parentId);
        }

        $file = new VirtualFile($name, $mimeType, $content);
        $parent->addFile($file);
        $this->persist($parent);

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

}
