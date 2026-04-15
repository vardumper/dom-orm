<?php
declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Entity\EntityInterface;

class EntityRepository extends AbstractEntityRepository
{
    public function __construct(EntityInterface|string $class)
    {
        $type = $this->resolveEntityType($class);
        if ($type === null) {
            throw new \Exception(\sprintf('Entity type %s not found.', (string)$class));
        }
        parent::__construct($type);
    }
}
