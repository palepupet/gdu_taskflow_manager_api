<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TaskPriority;
use App\Enum\TaskState;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(enumType: TaskPriority::class)]
    private TaskPriority $priority = TaskPriority::MEDIUM;

    #[ORM\Column(enumType: TaskState::class)]
    private TaskState $state = TaskState::OPEN;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $assignee = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_tag')]
    #[ORM\OrderBy(['label' => 'ASC'])]
    private Collection $tags;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(TaskPriority $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getState(): TaskState
    {
        return $this->state;
    }

    public function setState(TaskState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): static
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isAccessibleBy(User $user): bool
    {
        $project = $this->project;

        return $project instanceof Project && $project->isAccessibleBy($user);
    }

    public function canBeModifiedBy(User $user): bool
    {
        $project = $this->project;

        return $project instanceof Project && $project->canBeModifiedBy($user);
    }

    public function canChangeStateBy(User $user, TaskState $targetState): bool
    {
        $project = $this->project;
        if (!$project instanceof Project || !$project->isEditable()) {
            return false;
        }

        if ($user->isManager()) {
            return true;
        }

        if ($project->isOwner($user)) {
            return true;
        }

        $assignee = $this->assignee;
        if ($assignee instanceof User && $assignee->getId() === $user->getId()) {
            return TaskState::IN_PROGRESS !== $targetState;
        }

        return false;
    }

    public function canTransitionTo(TaskState $targetState): bool
    {
        return $this->state !== $targetState;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function hasTag(Tag $tag): bool
    {
        return $this->tags->contains($tag);
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
