<?php
declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\{Entity\EntityInterface, Serializer\Encoder\SchemaDecoder, Serializer\Encoder\SchemaEncoder, Serializer\Normalizer\SchemaDenormalizer, Serializer\Normalizer\SchemaNormalizer, Serializer\SchemaSerializer, Storage\StorageService};
use League\Flysystem\UnableToReadFile;

trait EntityManagerTrait
{
    use AttributeResolverTrait;

    protected StorageService $storage;

    protected \DOMDocument $data;

    protected \DOMXPath $xpath;

    protected SchemaSerializer $serializer;

    public function init(): void
    {
        $this->storage = StorageService::fromConfig();
        $xml = $this->getEmptyDom();

        try {
            $xml->loadXML($this->storage->read());
        } catch (UnableToReadFile $e) {
            $this->storage->write('<data />');
            $xml->loadXML($this->storage->read());
        }
        $this->data = $xml;
        $this->xpath = new \DOMXPath($xml);
        $this->serializer = $this->getSerializer();
    }

    /**
     * @param \DOMNode|\DOMNodeList<\DOMNode>|null $parent
     */
    public function persist(EntityInterface $entity, \DOMNode|\DOMNodeList|null $parent = null): void
    {
        $this->init();

        if ($parent === null) {
            $parent = $this->data->documentElement;
        }

        $allowedParentPaths = $this->resolveAllowedParentPaths($entity);

        if ($allowedParentPaths === null && $parent === null) {
            throw new \InvalidArgumentException('To store an entity a parent node is required.');
        }

        if (\is_array($allowedParentPaths) && \count($allowedParentPaths) === 1) {
            $nodes = $this->xpath->query($allowedParentPaths[0]);
            $parent = ($nodes === false) ? null : $nodes->item(0);
            if ($parent === null) {
                throw new \InvalidArgumentException(\sprintf('The parent node %s wasn\'t found.', $allowedParentPaths[0]));
            }
        }

        if (\is_array($allowedParentPaths) && \count($allowedParentPaths) > 1 && $parent === null) {
            throw new \InvalidArgumentException('This entity has several possible parent locations. To store it please provide a valid parent Node.');
        }

        if ($parent === null) {
            throw new \InvalidArgumentException('Invalid parent node given. Allowed parents are: ' . \implode(', ', $allowedParentPaths ?? []));
        }

        $array = $this->serializer->normalize($entity, SchemaNormalizer::FORMAT);
        $xml = $this->serializer->encode($array, SchemaEncoder::FORMAT);
        $tmp = $this->getEmptyDom();
        $tmp->loadXML($xml);
        $importedNode = $this->data->importNode($tmp->documentElement, true);
        $parent->appendChild($importedNode);

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

        $nodes = $this->xpath->query("//*[@id='{$id}']");
        $node = ($nodes === false) ? null : $nodes->item(0);
        if ($node === null || $node->parentNode === null) {
            return;
        }
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
