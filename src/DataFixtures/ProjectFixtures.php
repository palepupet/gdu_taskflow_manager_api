<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function load(ObjectManager $manager): void
    {
        $users = $this->loadUsersByEmail($manager);

        $projects = [
            [
                'title' => 'Refonte site web',
                'description' => 'Modernisation du site vitrine',
                'status' => ProjectStatus::IN_PROGRESS,
                'ownerEmail' => 'user@taskflow.fr',
                'memberEmails' => ['alice.dupont@taskflow.fr', 'bob.leroy@taskflow.fr'],
                'startAt' => '2025-09-01',
                'endAt' => '2026-06-30',
                'updatedAt' => '2026-04-10T14:30:00',
                'archivedAt' => null,
                'isArchived' => false,
            ],
            [
                'title' => 'Application mobile TaskFlow',
                'description' => 'MVP iOS / Android',
                'status' => ProjectStatus::IN_PROGRESS,
                'ownerEmail' => 'alice.dupont@taskflow.fr',
                'memberEmails' => ['claire.bernard@taskflow.fr'],
                'startAt' => '2025-11-15',
                'endAt' => null,
                'updatedAt' => '2026-03-20T09:15:00',
                'archivedAt' => null,
                'isArchived' => false,
            ],
            [
                'title' => 'Migration base de données',
                'description' => 'MySQL 8 → nouvelle infra',
                'status' => ProjectStatus::COMPLETED,
                'ownerEmail' => 'bob.leroy@taskflow.fr',
                'memberEmails' => ['david.petit@taskflow.fr', 'user@taskflow.fr'],
                'startAt' => '2025-06-01',
                'endAt' => '2025-12-15',
                'updatedAt' => '2025-12-15T16:00:00',
                'archivedAt' => '2025-12-15T16:00:00',
                'isArchived' => true,
            ],
            [
                'title' => 'Intégration SSO',
                'description' => 'Projet annulé faute de budget',
                'status' => ProjectStatus::CANCELLED,
                'ownerEmail' => 'claire.bernard@taskflow.fr',
                'memberEmails' => ['sophie.martin@taskflow.fr'],
                'startAt' => '2025-10-01',
                'endAt' => '2026-01-31',
                'updatedAt' => '2026-01-31T11:45:00',
                'archivedAt' => '2026-01-31T11:45:00',
                'isArchived' => true,
            ],
            [
                'title' => 'Dashboard manager',
                'description' => 'KPIs et reporting',
                'status' => ProjectStatus::IN_PROGRESS,
                'ownerEmail' => 'manager@taskflow.fr',
                'memberEmails' => [
                    'user@taskflow.fr',
                    'alice.dupont@taskflow.fr',
                    'bob.leroy@taskflow.fr',
                ],
                'startAt' => '2026-01-10',
                'endAt' => null,
                'updatedAt' => '2026-04-05T10:00:00',
                'archivedAt' => null,
                'isArchived' => false,
            ],
            [
                'title' => 'API partenaires',
                'description' => 'Exposition REST externe',
                'status' => ProjectStatus::COMPLETED,
                'ownerEmail' => 'david.petit@taskflow.fr',
                'memberEmails' => ['claire.bernard@taskflow.fr'],
                'startAt' => '2025-08-01',
                'endAt' => '2025-11-30',
                'updatedAt' => '2025-11-30T17:30:00',
                'archivedAt' => '2025-11-30T17:30:00',
                'isArchived' => true,
            ],
            [
                'title' => 'Audit sécurité',
                'description' => 'Pentest et corrections',
                'status' => ProjectStatus::IN_PROGRESS,
                'ownerEmail' => 'sophie.martin@taskflow.fr',
                'memberEmails' => ['manager@taskflow.fr', 'david.petit@taskflow.fr'],
                'startAt' => '2026-03-01',
                'endAt' => null,
                'updatedAt' => '2026-04-15T08:20:00',
                'archivedAt' => null,
                'isArchived' => false,
            ],
            [
                'title' => 'Refonte UX back-office',
                'description' => 'Maquettes et tests utilisateurs',
                'status' => ProjectStatus::CANCELLED,
                'ownerEmail' => 'user@taskflow.fr',
                'memberEmails' => ['bob.leroy@taskflow.fr'],
                'startAt' => '2025-12-01',
                'endAt' => '2026-04-30',
                'updatedAt' => '2026-04-30T15:10:00',
                'archivedAt' => '2026-04-30T15:10:00',
                'isArchived' => true,
            ],
            [
                'title' => 'Automatisation CI/CD',
                'description' => 'Pipelines GitHub Actions',
                'status' => ProjectStatus::IN_PROGRESS,
                'ownerEmail' => 'alice.dupont@taskflow.fr',
                'memberEmails' => [
                    'user@taskflow.fr',
                    'claire.bernard@taskflow.fr',
                    'david.petit@taskflow.fr',
                ],
                'startAt' => '2026-02-01',
                'endAt' => null,
                'updatedAt' => '2026-04-01T13:00:00',
                'archivedAt' => null,
                'isArchived' => false,
            ],
            [
                'title' => 'Documentation technique',
                'description' => 'Swagger + guides internes',
                'status' => ProjectStatus::COMPLETED,
                'ownerEmail' => 'bob.leroy@taskflow.fr',
                'memberEmails' => ['alice.dupont@taskflow.fr', 'user@taskflow.fr'],
                'startAt' => '2025-10-01',
                'endAt' => '2026-01-15',
                'updatedAt' => '2026-01-15T12:00:00',
                'archivedAt' => '2026-01-15T12:00:00',
                'isArchived' => true,
            ],
        ];

        foreach ($projects as $index => $data) {
            $owner = $users[$data['ownerEmail']];

            $project = $this->createProject(
                title: $data['title'],
                description: $data['description'],
                status: $data['status'],
                owner: $owner,
                memberEmails: $data['memberEmails'],
                users: $users,
                startAt: $data['startAt'],
                endAt: $data['endAt'],
                updatedAt: $data['updatedAt'],
                archivedAt: $data['archivedAt'],
                isArchived: $data['isArchived'],
            );

            $manager->persist($project);
            $this->addReference('project_'.($index + 1), $project);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    /**
     * @return array<string, User>
     */
    private function loadUsersByEmail(ObjectManager $manager): array
    {
        $repository = $manager->getRepository(User::class);
        $emails = [
            'manager@taskflow.fr',
            'sophie.martin@taskflow.fr',
            'user@taskflow.fr',
            'alice.dupont@taskflow.fr',
            'bob.leroy@taskflow.fr',
            'claire.bernard@taskflow.fr',
            'david.petit@taskflow.fr',
        ];

        $users = [];
        foreach ($emails as $email) {
            $user = $repository->findOneBy(['email' => $email]);
            if (!$user instanceof User) {
                throw new \RuntimeException(sprintf('User fixture manquant pour l\'email %s.', $email));
            }

            $users[$email] = $user;
        }

        return $users;
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     *
     * @param array<string, User> $users
     * @param list<string>        $memberEmails
     */
    private function createProject(
        string $title,
        ?string $description,
        ProjectStatus $status,
        User $owner,
        array $memberEmails,
        array $users,
        string $startAt,
        ?string $endAt,
        ?string $updatedAt,
        ?string $archivedAt,
        bool $isArchived,
    ): Project {
        $project = new Project();
        $project
            ->setTitle($title)
            ->setDescription($description)
            ->setOwner($owner)
            ->setStartAt(new \DateTimeImmutable($startAt))
            ->setEndAt(null !== $endAt ? new \DateTimeImmutable($endAt) : null);

        if (null !== $updatedAt) {
            $project->setUpdatedAt(new \DateTimeImmutable($updatedAt));
        }

        $project->changeStatus($status);

        if (null !== $archivedAt) {
            $project->setArchivedAt(new \DateTimeImmutable($archivedAt));
        }

        if (ProjectStatus::COMPLETED === $status && null === $endAt) {
            throw new \InvalidArgumentException(sprintf('Le projet terminé "%s" doit avoir une date de fin.', $title));
        }

        if ($isArchived && null === $archivedAt) {
            throw new \InvalidArgumentException(sprintf('Le projet archivé "%s" doit avoir une date d\'archivage.', $title));
        }

        if ($project->isArchived() !== $isArchived) {
            throw new \InvalidArgumentException(sprintf('Incohérence fixture "%s" : status=%s mais isArchived=%s', $title, $status->value, $isArchived ? 'true' : 'false'));
        }

        foreach ($memberEmails as $email) {
            if (!isset($users[$email])) {
                continue;
            }

            $member = $users[$email];

            if ($member->getId() === $owner->getId()) {
                continue;
            }

            $project->addMember($member);
        }

        return $project;
    }
}
