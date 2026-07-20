<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskState;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TaskFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function load(ObjectManager $manager): void
    {
        $users = $this->loadUsersByEmail($manager);

        /** @var list<array{
         *     projectRef: string,
         *     title: string,
         *     description: string|null,
         *     state: TaskState,
         *     priority: TaskPriority,
         *     assigneeEmail: string|null,
         *     dueAt: string|null,
         *     updatedAt: string,
         *     tagRefs: list<string>
         * }> $tasks
         */
        $tasks = [
            [
                'projectRef' => 'project_1',
                'title' => 'Maquettes page d\'accueil',
                'description' => 'Wireframes desktop et mobile',
                'state' => TaskState::OPEN,
                'priority' => TaskPriority::MEDIUM,
                'assigneeEmail' => 'alice.dupont@taskflow.fr',
                'dueAt' => '2026-06-01',
                'updatedAt' => '2026-04-08T10:00:00',
                'tagRefs' => ['tag_project_1_frontend'],
            ],
            [
                'projectRef' => 'project_1',
                'title' => 'Intégration header',
                'description' => null,
                'state' => TaskState::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assigneeEmail' => 'bob.leroy@taskflow.fr',
                'dueAt' => '2026-05-15',
                'updatedAt' => '2026-04-12T16:30:00',
                'tagRefs' => ['tag_project_1_frontend', 'tag_project_1_urgent'],
            ],
            [
                'projectRef' => 'project_1',
                'title' => 'Mise en production v1',
                'description' => 'Déploiement sur l\'environnement de prod',
                'state' => TaskState::CLOSED,
                'priority' => TaskPriority::MEDIUM,
                'assigneeEmail' => 'user@taskflow.fr',
                'dueAt' => '2026-04-01',
                'updatedAt' => '2026-04-01T18:00:00',
                'tagRefs' => ['tag_project_1_backend'],
            ],
            [
                'projectRef' => 'project_2',
                'title' => 'Initialisation projet Flutter',
                'description' => 'Structure, thème et navigation',
                'state' => TaskState::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assigneeEmail' => 'claire.bernard@taskflow.fr',
                'dueAt' => '2026-07-10',
                'updatedAt' => '2026-04-10T11:15:00',
                'tagRefs' => ['tag_project_2_mobile', 'tag_project_2_ios'],
            ],
            [
                'projectRef' => 'project_2',
                'title' => 'Écran de connexion',
                'description' => null,
                'state' => TaskState::OPEN,
                'priority' => TaskPriority::MEDIUM,
                'assigneeEmail' => 'claire.bernard@taskflow.fr',
                'dueAt' => null,
                'updatedAt' => '2026-03-25T09:45:00',
                'tagRefs' => ['tag_project_2_mobile'],
            ],
            [
                'projectRef' => 'project_5',
                'title' => 'Widget KPI ventes',
                'description' => 'Graphique mensuel pour le dashboard',
                'state' => TaskState::OPEN,
                'priority' => TaskPriority::HIGH,
                'assigneeEmail' => 'user@taskflow.fr',
                'dueAt' => '2026-06-20',
                'updatedAt' => '2026-04-05T14:20:00',
                'tagRefs' => ['tag_project_5_reporting', 'tag_project_5_kpi'],
            ],
            [
                'projectRef' => 'project_5',
                'title' => 'Export PDF des rapports',
                'description' => null,
                'state' => TaskState::OPEN,
                'priority' => TaskPriority::LOW,
                'assigneeEmail' => 'alice.dupont@taskflow.fr',
                'dueAt' => '2026-08-01',
                'updatedAt' => '2026-04-02T08:30:00',
                'tagRefs' => ['tag_project_5_reporting'],
            ],
            [
                'projectRef' => 'project_7',
                'title' => 'Revue des accès API',
                'description' => 'Audit des endpoints exposés',
                'state' => TaskState::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assigneeEmail' => 'david.petit@taskflow.fr',
                'dueAt' => '2026-05-30',
                'updatedAt' => '2026-04-14T15:00:00',
                'tagRefs' => ['tag_project_7_sécurité', 'tag_project_7_audit'],
            ],
            [
                'projectRef' => 'project_9',
                'title' => 'Pipeline staging',
                'description' => 'Build, tests et déploiement automatique',
                'state' => TaskState::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assigneeEmail' => 'david.petit@taskflow.fr',
                'dueAt' => '2026-06-05',
                'updatedAt' => '2026-04-11T13:40:00',
                'tagRefs' => ['tag_project_9_devops', 'tag_project_9_ci'],
            ],
            [
                'projectRef' => 'project_9',
                'title' => 'Notifications Slack CI',
                'description' => null,
                'state' => TaskState::OPEN,
                'priority' => TaskPriority::LOW,
                'assigneeEmail' => null,
                'dueAt' => '2026-07-01',
                'updatedAt' => '2026-04-09T10:10:00',
                'tagRefs' => ['tag_project_9_ci'],
            ],
            [
                'projectRef' => 'project_3',
                'title' => 'Export dump final',
                'description' => 'Sauvegarde avant bascule',
                'state' => TaskState::CLOSED,
                'priority' => TaskPriority::MEDIUM,
                'assigneeEmail' => 'bob.leroy@taskflow.fr',
                'dueAt' => '2025-12-10',
                'updatedAt' => '2025-12-10T17:00:00',
                'tagRefs' => ['tag_project_3_cloturé'],
            ],
        ];

        foreach ($tasks as $data) {
            $project = $this->getReference($data['projectRef'], Project::class);

            $task = new Task();
            $task->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setState($data['state'])
                ->setPriority($data['priority'])
                ->setDueAt(null !== $data['dueAt'] ? new \DateTimeImmutable($data['dueAt']) : null)
                ->setUpdatedAt(new \DateTimeImmutable($data['updatedAt']));

            if (null !== $data['assigneeEmail']) {
                $task->setAssignee($users[$data['assigneeEmail']]);
            }

            foreach ($data['tagRefs'] as $tagRef) {
                $tag = $this->getReference($tagRef, Tag::class);
                $task->addTag($tag);
            }

            $project->addTask($task);
            $manager->persist($task);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [TagFixtures::class, UserFixtures::class];
    }

    /**
     * @return array<string, User>
     */
    private function loadUsersByEmail(ObjectManager $manager): array
    {
        $repository = $manager->getRepository(User::class);
        $emails = [
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
}
