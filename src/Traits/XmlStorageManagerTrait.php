<?php

declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use DOM\ORM\Serializer\SchemaSerializer;
use Ramsey\Collection\Collection;

trait XmlStorageManagerTrait
{
    use AttributeResolverTrait;

    protected const STORAGE_PATH = __DIR__ . '/../../storage/';
    protected const FILENAME = 'data.xml';

    protected \DOMDocument $data;
    protected \DOMXPath $xpath;

    public function loadData($storage)
    {
        $xml = $this->getEmptyDom();
        $xml->load($storage);
        $this->data = $xml;
        // $this->xpath = new \DOMXPath($xml);
        $this->serializer = $this->getSerializer();
    }

    public function init(string $storage): void
    {
        if (!is_dir(dirname($storage))) {
            mkdir(dirname($storage), 0755, true);
        }

        if (!is_writable(dirname($storage))) {
            chmod(dirname($storage), 0755);
        }

        if (!file_exists($storage)) {
            $xml = $this->getEmptyDom();
            $xml->loadXML('<data />');
            $xml->save($storage);
        }
    }

    /**
     * @param \DOMNode|\DOMNodeList $parent if a Nodelist is provided, the item will be duplicated to multiple locations
     */
    public function persist(EntityInterface $entity, \DOMNode|\DOMNodeList $parent = null): void
    {
        $allowedParentPaths = $this->resolveAllowedParentPaths($entity);
        // $entityType = $this->resolveEntityType($entity);

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
        die('hello');
        $xml = $this->serializer->encode($array, SchemaEncoder::FORMAT);
        $tmp = $this->getEmptyDom();
        $tmp->loadXML($xml);
        $importedNode = $this->data->importNode($tmp->documentElement, true); // @todo hanlde false on error
        if ($importedNode) {
            $parent->appendChild($importedNode);
        }

        $this->data->save(getcwd() . '/storage/data.xml', LIBXML_NOXMLDECL);
    }

    public function findAll(): ?Collection
    {
        return null;
    }

    public function find($id, $lockMode = null, $lockVersion = null): ?EntityInterface
    {
        return null;
    }

    public function findOneBy(array $criteria, array $orderBy = null): ?EntityInterface
    {
        return null;
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): ?Collection
    {
        return new Collection();
    }

    /**
     * find all needs to return every <item> stored in the xml, that doesn't make uch sense, so it's declared abstract
     * @tutorial what does make sense are concrete implementations. eg: UserRepository::findAll() to return all users
     * or a CategoryRepository::findAll() to get a list of folders or a NavigationRepository::findAll() to get a list of navs
     */
    // abstract public function findAll(): ?Collection;
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
        return new SchemaSerializer(new SchemaNormalizer(), new SchemaEncoder());
    }
}
