<?php

declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Command\Export;
use DOM\ORM\Encryption\EncryptionService;
use DOM\ORM\{Entity\EntityInterface, Serializer\Encoder\SchemaDecoder, Serializer\Encoder\SchemaEncoder, Serializer\Normalizer\SchemaDenormalizer, Serializer\Normalizer\SchemaNormalizer, Serializer\SchemaSerializer, Storage\StorageService};
use DOM\ORM\Storage\QueryCache;
use DOM\ORM\Vcs\VcsService;
use League\Flysystem\UnableToReadFile;

trait EntityManagerTrait
{
    use AttributeResolverTrait;

    protected StorageService $storage;

    protected \DOMDocument $data;

    protected \DOMXPath $xpath;

    protected SchemaSerializer $serializer;

    private static ?StorageService $sharedStorage = null;

    private static ?SchemaSerializer $sharedSerializer = null;

    private bool $initialized = false;

    private ?EncryptionService $encryption = null;

    public function init(): void
    {
        $this->initializeServices();
        $this->loadData();
    }

    /**
     * @param \DOMNode|\DOMNodeList<\DOMNode>|null $parent
     */
    /**
     * @param \DOMNode|\DOMNodeList<\DOMNode>|string|null $parent  A DOMNode, an XPath string, or null.
     *   - null + single allowedParentPath: group is auto-resolved and created when missing.
     *   - null + multiple allowedParentPaths: throws — caller must provide an explicit parent.
     *   - XPath string: resolved against the freshly-loaded DOM inside the lock cycle,
     *     so it always works correctly regardless of when the string was prepared.
     */
    public function persist(EntityInterface $entity, \DOMNode|\DOMNodeList|string|null $parent = null): void
    {
        $this->withWriteLock(function () use ($entity, $parent): void {
            $allowedParentPaths = $this->resolveAllowedParentPaths($entity);

            // Resolve an XPath string to a node inside the current (freshly loaded) DOM.
            if (\is_string($parent)) {
                $nodes = $this->xpath->query($parent);
                $resolved = ($nodes !== false) ? $nodes->item(0) : null;
                if ($resolved === null) {
                    throw new \InvalidArgumentException(\sprintf('The specified parent XPath "%s" did not match any node.', $parent));
                }
                $parent = $resolved;
            }

            // Single allowedParentPath: auto-resolve; auto-create the group when missing.
            if (\is_array($allowedParentPaths) && \count($allowedParentPaths) === 1) {
                $nodes = $this->xpath->query($allowedParentPaths[0]);
                $resolved = ($nodes === false) ? null : $nodes->item(0);
                if ($resolved === null) {
                    $resolved = $this->createGroupFromXPath($allowedParentPaths[0]);
                }
                if ($resolved === null) {
                    throw new \InvalidArgumentException(\sprintf('The parent node "%s" was not found and could not be auto-created.', $allowedParentPaths[0]));
                }
                $parent = $resolved;
            }

            // Multiple allowedParentPaths with no explicit parent: caller must decide.
            if (\is_array($allowedParentPaths) && \count($allowedParentPaths) > 1 && $parent === null) {
                throw new \InvalidArgumentException('This entity has several possible parent locations. Please provide a valid parent node or XPath string.');
            }

            // No allowedParentPaths and no explicit parent: fall back to document root.
            if ($parent === null) {
                $parent = $this->data->documentElement;
            }

            $array = $this->serializer->normalize($entity, SchemaNormalizer::FORMAT);
            $xml = $this->serializer->encode($array, SchemaEncoder::FORMAT);
            $tmp = $this->getEmptyDom();
            $tmp->loadXML($xml);
            $importedNode = $this->data->importNode($tmp->documentElement, true);

            // Upsert: replace the existing node if one with the same id already exists.
            $existing = $this->xpath->query(\sprintf('//*[@id="%s"]', $entity->getId()));
            if ($existing !== false && $existing->length > 0 && ($existingNode = $existing->item(0)) !== null) {
                $existingNode->parentNode?->replaceChild($importedNode, $existingNode);
            } else {
                $parent->appendChild($importedNode);
            }
        });
    }

    /**
     * Persist multiple entities in a single write: appends all nodes to the DOM,
     * then calls save() exactly once. Far more efficient than calling persist()
     * in a loop when inserting large numbers of entities.
     *
     * @param iterable<EntityInterface> $entities
     * @param \DOMNode|\DOMNodeList<\DOMNode>|null $parent
     */
    public function persistBatch(iterable $entities, \DOMNode|\DOMNodeList|null $parent = null): void
    {
        $this->withWriteLock(function () use ($entities, $parent): void {
            $resolvedParent = $parent ?? $this->data->documentElement;

            foreach ($entities as $entity) {
                $allowedParentPaths = $this->resolveAllowedParentPaths($entity);

                $nodeParent = $resolvedParent;
                if (\is_array($allowedParentPaths) && \count($allowedParentPaths) === 1) {
                    $nodes = $this->xpath->query($allowedParentPaths[0]);
                    $nodeParent = ($nodes === false) ? null : $nodes->item(0);
                    if ($nodeParent === null) {
                        throw new \InvalidArgumentException(\sprintf('The parent node %s wasn\'t found.', $allowedParentPaths[0]));
                    }
                }

                if ($nodeParent === null) {
                    throw new \InvalidArgumentException('Invalid parent node. Allowed parents: ' . \implode(', ', $allowedParentPaths ?? []));
                }

                $array = $this->serializer->normalize($entity, SchemaNormalizer::FORMAT);
                $xml = $this->serializer->encode($array, SchemaEncoder::FORMAT);
                $tmp = $this->getEmptyDom();
                $tmp->loadXML($xml);
                $nodeParent->appendChild($this->data->importNode($tmp->documentElement, true));
            }
        });
    }

    public function save(): void
    {
        $this->initializeServices();

        $lockedHere = false;
        if (!$this->storage->isLocked()) {
            $this->storage->lock();
            $lockedHere = true;

            if (!isset($this->data)) {
                $this->loadData();
            }
        }

        try {
            $this->writeCurrentState();
        } finally {
            if ($lockedHere) {
                $this->storage->unlock();
            }
        }
    }

    public function removeById(string $id): void
    {
        $this->withWriteLock(function () use ($id): void {
            $nodes = $this->xpath->query("//*[@id='{$id}']");
            $node = ($nodes === false) ? null : $nodes->item(0);
            if ($node === null || $node->parentNode === null) {
                return;
            }
            $node->parentNode->removeChild($node);
        });
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

    protected function getEncryptionService(): ?EncryptionService
    {
        return $this->encryption;
    }

    private function initializeServices(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->storage = self::$sharedStorage ??= StorageService::fromConfig();
        self::warmUpReflectionCache();

        try {
            $this->encryption = EncryptionService::fromConfig();
        } catch (\RuntimeException) {
            // No encryption_key configured — encryption silently disabled
        }

        $this->serializer = self::$sharedSerializer ??= $this->getSerializer();
        $this->initialized = true;
    }

    private function loadData(): void
    {
        $xml = $this->getEmptyDom();

        try {
            $xml->loadXML($this->storage->read());
        } catch (UnableToReadFile) {
            $this->storage->write('<data />');
            $xml->loadXML('<data />');
        }

        $this->data = $xml;
        $this->xpath = new \DOMXPath($xml);
    }

    private function withWriteLock(callable $callback): void
    {
        $this->initializeServices();

        $lockedHere = false;
        if (!$this->storage->isLocked()) {
            $this->storage->lock();
            $lockedHere = true;
        }

        try {
            $this->loadData();
            $callback();
            $this->writeCurrentState();
        } finally {
            if ($lockedHere) {
                $this->storage->unlock();
            }
        }
    }

    private function writeCurrentState(): void
    {
        $contents = $this->data->saveXML($this->data->documentElement, LIBXML_NOXMLDECL);
        $this->storage->write($contents);

        if (QueryCache::isEnabled() && QueryCache::getStrategy() === 'on_persist') {
            QueryCache::buildFromDom($this->data);
            Export::runConfigured($this->data, $contents);
        }

        if (VcsService::isEnabled()) {
            $repoPath = (string)\DOM\ORM\getConfig()->get('dom-orm.flysystem.config.location');
            VcsService::commit($repoPath);
        }
    }

    private function getSerializer(): SchemaSerializer
    {
        return new SchemaSerializer(
            new SchemaNormalizer($this->encryption),
            new SchemaDenormalizer($this->encryption),
            new SchemaEncoder(),
            new SchemaDecoder(),
        );
    }

    /**
     * Attempts to create a parent group node from a simple XPath pattern.
     * Supports the common form: //group[@type="VALUE"] or //group[@type='VALUE']
     */
    private function createGroupFromXPath(string $xpath): ?\DOMElement
    {
        if (\preg_match('#^//group\[@type=["\']([^"\']+)["\']\]$#', $xpath, $m)) {
            $group = $this->data->createElement('group');
            $group->setAttribute('type', $m[1]);
            $this->data->documentElement->appendChild($group);

            return $group;
        }

        return null;
    }
}
