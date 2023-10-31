<?php

declare(strict_types=1);

namespace DOM\ORM\Entity;

use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Traits\AttributeResolverTrait;
use Ramsey\Collection\Collection;
use Ramsey\Uuid\Uuid;

abstract class AbstractEntity implements EntityInterface
{
    use AttributeResolverTrait;

    #[Fragment(storageStrategy: 'inline')]
    private ?string $id = null;

    private ?array $allowedParentPaths = null;

    #[Fragment]
    private ?\DateTimeInterface $createdAt = null;

    #[Fragment]
    private ?\DateTimeInterface $updatedAt = null;

    #[Fragment]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex()->toString();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function hasAllowedParentPaths(): bool
    {
        return $this->allowedParentPaths !== null;
    }

    public function jsonSerialize(): array
    {
        $entityType = $this->resolveEntityType($this);
        $data = [
            'item' => [
                '@type' => $entityType,
                '@id' => $this->getId(),
            ],
        ];

        $fragments = $this->resolveFragments($this);
        foreach ($fragments as [$storageStrategy, $fragmentName, $propertyName]) {
            $name = ($storageStrategy === 'inline') ? '@' . $fragmentName : $fragmentName;

            try {
                // we expect private properties to be inaccessible here
                $value = $this->{$propertyName};
            } catch (\Throwable $th) {
                // so we'll try to get the value via the getter
                $methodName = 'get' . ucfirst($propertyName);
                $value = $this->{$methodName}();
            }

            // basic sanitization
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            $data['item'][$name] = $value;
        }

        $groups = $this->resolveGroups($this);

        // nothing more to do here
        if ($groups === null) {
            return $data;
        }

        foreach ($groups as [$entity, $groupType, $propertyName]) {
            $name = $groupType ?? $propertyName;
            $value = null;

            try {
                // we expect private properties to be inaccessible here
                $value = $this->{$propertyName};
            } catch (\Throwable $th) {
                // so we'll try to get the value via the getter
                $methodName = 'get' . ucfirst($propertyName);
                $value = $this->{$methodName}();
            }

            // skip empty groups
            if ($value === null) {
                continue;
            }

            // basic validation
            if (!is_array($value) && !$value instanceof Collection) {
                throw new \Exception('wtf');
            }
            if (!is_iterable($value) && !$value instanceof Collection) {
                throw new \InvalidArgumentException(sprintf('Groups must be of type Ramsey\Collection or an array of EntityInterface objects. %s given', gettype($value)));
            }

            // recursion
            foreach ($value as $item) {
                if (get_class($item) !== $entity) {
                    throw new \InvalidArgumentException(sprintf('Wrong EntityInterface type given. Expected type was %s', $entity));
                }
                $data['item'][$name][] = $item->jsonSerialize();
            }
        }

        return $data;
    }
}
