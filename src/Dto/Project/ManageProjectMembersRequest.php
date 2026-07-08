<?php

declare(strict_types=1);

namespace App\Dto\Project;

use Symfony\Component\Validator\Constraints as Assert;

class ManageProjectMembersRequest
{
    /**
     * @param list<int>|null $members
     */
    public function __construct(
        #[Assert\NotNull(message: 'La liste des membres est obligatoire.')]
        #[Assert\Count(min: 1, minMessage: 'Au moins un membre est requis.')]
        #[Assert\All([
            new Assert\Type('integer'),
            new Assert\Positive(),
        ])]
        public ?array $members = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $members = null;
        if (isset($data['members']) && \is_array($data['members'])) {
            $members = array_values(array_filter(
                array_map(static fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null, $data['members']),
                static fn (?int $id): bool => null !== $id,
            ));
        }

        return new self(members: $members);
    }
}
