<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Force PSR-4 autoloading of all test fixture classes so that
// AttributeResolverTrait::warmUpReflectionCache() can discover their
// #[ORM\Item] entityType attributes via get_declared_classes().
foreach (new \DirectoryIterator(__DIR__ . '/Fixtures') as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        class_exists('Tests\\Fixtures\\' . $file->getBasename('.php'));
    }
}
