<?php
declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Serializer\Encoder\SchemaDecoder;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaDenormalizer;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use DOM\ORM\Serializer\SchemaSerializer;
use DOM\ORM\Storage\StorageService;

trait EntityManagerTrait
{
    use AttributeResolverTrait;

    protected StorageService $storage;

    protected \DOMDocument $data;

    protected \DOMXPath $xpath;

    protected SchemaSerializer $serializer;

    public function init(): void
    {
        $this->storage = new StorageService();
        $xml = $this->getEmptyDom();
        $xml->loadXML($this->storage->read());
        $this->data = $xml;
        $this->xpath = new \DOMXPath($xml);
        $this->serializer = $this->getSerializer();
    }

    /**
     * @param \DOMNode|\DOMNodeList $parent if a Nodelist is provided, the item will be duplicated to multiple locations
     */
    public function persist(EntityInterface $entity, \DOMNode|\DOMNodeList $parent = null): void
    {
        $this->init();

        if ($parent === null) {
            $parent = $this->data->documentElement;
        }

        $allowedParentPaths = $this->resolveAllowedParentPaths($entity);

        if ($allowedParentPaths === null && $parent === null) {
            throw new \InvalidArgumentException('To store an entity a parent node is required.');
        }

        /** we will ignore a parent node given via parameter if the entity dictates one specific parent */
        if (count($allowedParentPaths) === 1) {
            $parent = $this->xpath->query($allowedParentPaths[0])?->item(0);
            if ($parent === null) {
                throw new \InvalidArgumentException(sprintf('The parent node %s wasn\'t found.', $allowedParentPaths[0]));
            }
        }

        /** if more than one are given, the parent parameter is required, and we can validate its path against the ones defined in the entity */
        if (count($allowedParentPaths) > 1 && $parent === null) {
            throw new \InvalidArgumentException('This entity has several possible parent locations. To store it please provide a valid parent Node.');
        }

        /** if the parent is still null, we give feedback */
        if ($parent === null) {
            throw new \InvalidArgumentException('Invalid parent node given. Allowed parents are: ' . implode(', ', $allowedParentPaths));
        }

        $array = $this->serializer->normalize($entity, SchemaNormalizer::FORMAT);
        $xml = $this->serializer->encode($array, SchemaEncoder::FORMAT);
        $tmp = $this->getEmptyDom();
        $tmp->loadXML($xml);
        $importedNode = $this->data->importNode($tmp->documentElement, true); // @todo hanlde false on error
        if ($importedNode) {
            $parent->appendChild($importedNode);
        }

        $this->save();
    }

    public function save(): void
    {
        $contents = $this->data->saveXML($this->data->documentElement, LIBXML_NOXMLDECL);
        $this->storage->write($contents);
    }

    public function removeById(string $id): void
    {
        $this->init();

        $node = $this->xpath->query("//*[@id='{$id}']")?->item(0);
        $node->parentNode->removeChild($node);

        $this->save();
    }

    public function getEmptyDom(): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = false;
        $dom->strictErrorChecking = false;
        $dom->formatOutput = true;

        return $dom;
    }

    private function getSerializer(): SchemaSerializer
    {
        return new SchemaSerializer(new SchemaNormalizer(), new SchemaDenormalizer(), new SchemaEncoder(), new SchemaDecoder());
    }
}
