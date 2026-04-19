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

}
