<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;
use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Storage\StorageService;
use DOM\ORM\Traits\EntityManagerTrait;

require __DIR__ . '/../vendor/autoload.php';

#[ORM\Item(entityType: 'virtual_note')]
final class VirtualFilesystemNote extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $title,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}

final class VirtualFilesystemNoteManager
{
    use EntityManagerTrait;

    public function add(string $title): void
    {
        $this->persist(new VirtualFilesystemNote($title));
    }
}

$storageDir = __DIR__ . '/storage';
if (!\is_dir($storageDir) && !\mkdir($storageDir, 0755, true) && !\is_dir($storageDir)) {
    throw new \RuntimeException('Unable to create storage directory: ' . $storageDir);
}

$dataFile = $storageDir . '/data.xml';
if (\is_file($dataFile)) {
    \unlink($dataFile);
}

$manager = new VirtualFilesystemNoteManager();
$manager->add('First virtual note');
$manager->add('Second virtual note');

$repository = new EntityRepository(VirtualFilesystemNote::class);
$notes = $repository->findAll();
$xml = StorageService::fromConfig()->read();

echo 'Leaf installed: ' . (InstalledVersions::isInstalled('leafs/leaf') ? 'yes' : 'no') . PHP_EOL;
echo 'Notes persisted: ' . \count($notes) . PHP_EOL;
echo 'XML file: ' . $dataFile . PHP_EOL;
echo PHP_EOL;
echo $xml . PHP_EOL;
