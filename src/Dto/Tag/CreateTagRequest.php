<?php

declare(strict_types=1);

namespace App\Dto\Tag;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTagRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
        #[Assert\Length(max: 50)]
        public ?string $label = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: isset($data['label']) && \is_string($data['label']) ? $data['label'] : null,
        );
    }
}
