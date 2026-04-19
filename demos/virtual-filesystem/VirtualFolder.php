<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'folder')]
final class VirtualFolder extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $name,
        #[ORM\Group(entity: VirtualFile::class, groupType: 'files')]
        private array $files = [],
        #[ORM\Group(entity: self::class, groupType: 'folders')]
        private array $folders = [],
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getFolders(): array
    {
        return $this->folders;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function addFile(VirtualFile $file): void
    {
        $this->files[] = $file;
    }

    public function addFolder(self $folder): void
    {
        $this->folders[] = $folder;
    }
}
