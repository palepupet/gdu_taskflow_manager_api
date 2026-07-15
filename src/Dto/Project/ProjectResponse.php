<?php

declare(strict_types=1);

namespace App\Dto\Project;

use App\Dto\Tag\TagResponse;
use App\Dto\Task\TaskResponse;
use App\Dto\User\UserSummaryResponse;
use App\Entity\Project;
use App\Entity\User;

class ProjectResponse
{
    /**
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     *
     * @param list<UserSummaryResponse> $members
     * @param list<TaskResponse>        $tasks
     * @param list<TagResponse>         $tags
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $status,
        public UserSummaryResponse $owner,
        public array $members,
        public array $tasks,
        public array $tags,
        public bool $isArchived,
        public ?string $createdAt,
        public ?string $startAt,
        public ?string $endAt,
        public ?string $updatedAt,
        public ?string $archivedAt,
    ) {
    }

    public static function fromProject(Project $project): self
    {
        $id = $project->getId();
        $owner = $project->getOwner();

        $tasks = [];
        foreach ($project->getTasks() as $task) {
            $tasks[] = TaskResponse::fromTask($task);
        }

        $tags = [];
        foreach ($project->getTags() as $tag) {
            $tags[] = TagResponse::fromTag($tag);
        }

        if (null === $id || !$owner instanceof User) {
            throw new \LogicException('Un projet persisté doit avoir un id et un owner.');
        }

        $members = [];
        foreach ($project->getMembers() as $member) {
            $members[] = UserSummaryResponse::fromUser($member);
        }

        return new self(
            id: $id,
            title: (string) $project->getTitle(),
            description: $project->getDescription(),
            status: $project->getStatus()->value,
            owner: UserSummaryResponse::fromUser($owner),
            members: $members,
            tasks: $tasks,
            tags: $tags,
            isArchived: (bool) $project->isArchived(),
            createdAt: $project->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            startAt: $project->getStartAt()?->format('Y-m-d'),
            endAt: $project->getEndAt()?->format('Y-m-d'),
            updatedAt: $project->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            archivedAt: $project->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $tasks = array_map(
            static fn (TaskResponse $task): array => $task->toArray(),
            $this->tasks,
        );

        $tags = array_map(
            static fn (TagResponse $tag): array => $tag->toArray(),
            $this->tags,
        );

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'owner' => $this->owner->toArray(),
            'members' => array_map(
                static fn (UserSummaryResponse $member): array => $member->toArray(),
                $this->members,
            ),
            'tasks' => $tasks,
            'tags' => $tags,
            'isArchived' => $this->isArchived,
            'createdAt' => $this->createdAt,
            'startAt' => $this->startAt,
            'endAt' => $this->endAt,
            'updatedAt' => $this->updatedAt,
            'archivedAt' => $this->archivedAt,
        ];
    }
}
