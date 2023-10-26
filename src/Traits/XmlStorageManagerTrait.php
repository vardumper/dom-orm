<?php

declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use Ramsey\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

trait XmlStorageManagerTrait
{
    use AttributeResolverTrait;
    protected const STORAGE_PATH = __DIR__ . '/../../storage/';
    protected const FILENAME = 'data.xml';

    protected \DOMDocument $dom;
    protected \DOMXPath $xpath;

    protected SerializerInterface $serializer;

    public function __construct()
    {
        $this->init();
        $xml = $this->getEmptyDOMDocument();
        $xml->load(self::STORAGE_PATH . self::FILENAME);
        $this->xpath = new \DOMXPath($xml);
        $this->dom = $xml;
        $this->serializer = $this->getSerializer();
    }

    public function init(): void
    {
        if (!is_dir(self::STORAGE_PATH)) {
            mkdir(self::STORAGE_PATH);
        }

        if (!file_exists(self::STORAGE_PATH . self::FILENAME)) {
            $xml = $this->getEmptyDOMDocument();
            $xml->loadXML('<data />');
            $xml->save(self::STORAGE_PATH . self::FILENAME);
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

        $array = $this->serializer->normalize($entity, null);
        $xml = $this->serializer->encode($array, SchemaEncoder::FORMAT);
        $tmp = $this->getEmptyDOMDocument();
        $tmp->loadXML($xml);
        $importedNode = $this->dom->importNode($tmp->documentElement, true); // @todo hanlde false on error
        if ($importedNode) {
            $parent->appendChild($importedNode);
        }

        $this->dom->save(self::STORAGE_PATH . self::FILENAME, LIBXML_NOXMLDECL);
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
    abstract public function findAll(): ?Collection;

    private function getEmptyDOMDocument(): \DOMDocument
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->validateOnParse = false;
        $dom->strictErrorChecking = false;
        $dom->formatOutput = true;

        return $dom;
    }

    private function getSerializer(): Serializer
    {
        $encoders = [new SchemaEncoder()];
        $normalizers = [new JsonSerializableNormalizer()];

        return new Serializer($normalizers, $encoders);
    }
}
