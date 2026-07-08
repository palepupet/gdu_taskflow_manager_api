<?php

declare(strict_types=1);

namespace App\Dto\Project;

use Symfony\Component\Validator\Constraints as Assert;

class AddProjectMemberRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'L\'utilisateur est obligatoire.')]
        public ?int $userId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $userId = null;
        if (isset($data['userId']) && \is_numeric($data['userId'])) {
            $userId = (int) $data['userId'];
        }

        return new self(
            userId: $userId,
        );
    }
}
