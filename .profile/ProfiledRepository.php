<?php

declare(strict_types=1);

/**
 * Test double for profiling: exposes the protected services of the
 * EntityManagerTrait so the benchmark can measure the exact sub-phases
 * (file read, loadXML, DOMXPath construction, XPath query, decode,
 * denormalize) that findAll()/find() execute internally — without
 * modifying any library source file.
 */
class ProfiledRepository extends DOM\ORM\Repository\EntityRepository
{
    public function storage(): DOM\ORM\Storage\StorageService
    {
        return $this->storage;
    }

    public function serializer(): DOM\ORM\Serializer\SchemaSerializer
    {
        return $this->serializer;
    }

    public function dom(): \DOMDocument
    {
        return $this->data;
    }

    public function xpath(): \DOMXPath
    {
        return $this->xpath;
    }
}
