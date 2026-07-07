<?php

declare(strict_types=1);

namespace App\Dto\Project;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre ne doit pas être vide.', allowNull: true)]
        #[Assert\Length(max: 100)]
        public ?string $title = null,
        public ?string $description = null,
        #[Assert\Date(message: 'La date de début n\'est pas valide.')]
        public ?string $startAt = null,
        #[Assert\Date(message: 'La date de fin n\'est pas valide.')]
        public ?string $endAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: isset($data['title']) && \is_string($data['title']) ? $data['title'] : null,
            description: array_key_exists('description', $data)
                ? (\is_string($data['description']) ? $data['description'] : null)
                : null,
            startAt: array_key_exists('startAt', $data)
                ? (\is_string($data['startAt']) ? $data['startAt'] : null)
                : null,
            endAt: array_key_exists('endAt', $data)
                ? (\is_string($data['endAt']) ? $data['endAt'] : null)
                : null,
        );
    }

    public function getStartAtAsDateTime(): ?\DateTimeImmutable
    {
        return null !== $this->startAt
            ? new \DateTimeImmutable($this->startAt)
            : null;
    }

    public function getEndAtAsDateTime(): ?\DateTimeImmutable
    {
        return null !== $this->endAt
            ? new \DateTimeImmutable($this->endAt)
            : null;
    }
}
