<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'rel_enrollment')]
class RelEnrollment extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $studentId,
        #[ORM\Fragment]
        private string $courseId,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getStudentId(): string
    {
        return $this->studentId;
    }

    public function setStudentId(string $studentId): static
    {
        $this->studentId = $studentId;

        return $this;
    }

    public function getCourseId(): string
    {
        return $this->courseId;
    }

    public function setCourseId(string $courseId): static
    {
        $this->courseId = $courseId;

        return $this;
    }
}
