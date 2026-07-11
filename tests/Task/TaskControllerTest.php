<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskState;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
class TaskControllerTest extends ApiTestCase
{
    public function testOwnerCanCreateTask(): void
    {
        $this->createUser('owner@taskflow.fr');
        $token = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject(['title' => 'Projet tâches', 'description' => 'Test'], $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, [
            'title' => 'Ma tâche',
            'priority' => TaskPriority::HIGH->value,
        ], $token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->getJsonResponse();
        self::assertSame('Ma tâche', $data['title']);
        self::assertSame(TaskState::OPEN->value, $data['state']);
        self::assertSame(TaskPriority::HIGH->value, $data['priority']);
        self::assertSame($projectId, $data['projectId']);
        self::assertNull($data['assignee']);
    }

    public function testMemberCannotCreateTask(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);

        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');
        $this->postProjectTask($projectId, ['title' => 'Interdit'], $memberToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testMemberCanListTasks(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);

        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $this->postProjectTask($projectId, ['title' => 'Tâche 1'], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->postProjectTask($projectId, ['title' => 'Tâche 2'], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');

        $this->getProjectTasks($projectId, $memberToken);
        self::assertResponseIsSuccessful();

        $tasks = $this->getJsonResponse();
        self::assertCount(2, $tasks);

        $titles = array_column($tasks, 'title');
        self::assertContains('Tâche 1', $titles);
        self::assertContains('Tâche 2', $titles);

        foreach ($tasks as $task) {
            self::assertIsArray($task);
            self::assertArrayHasKey('id', $task);
            self::assertArrayHasKey('title', $task);
            self::assertArrayHasKey('state', $task);
            self::assertArrayHasKey('priority', $task);
            self::assertArrayHasKey('projectId', $task);
            self::assertSame($projectId, $task['projectId']);
            self::assertSame(TaskState::OPEN->value, $task['state']);
        }
    }

    public function testNonMemberCannotListTasks(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('other@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'Tâche privée'], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $otherToken = $this->loginAndGetToken('other@taskflow.fr', 'TaskFlowUser123');
        $this->getProjectTasks($projectId, $otherToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAssigneeIsAutoAddedAsMember(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('assignee@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $assignee = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'assignee@taskflow.fr']);
        self::assertNotNull($assignee);

        $this->postProjectTask($projectId, [
            'title' => 'Tâche assignée',
            'assigneeId' => $assignee->getId(),
        ], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->getProjectById($projectId, $ownerToken);

        $projectData = $this->getJsonResponse();
        self::assertArrayHasKey('members', $projectData);
        self::assertIsArray($projectData['members']);

        $members = $projectData['members'];
        self::assertCount(1, $members);

        $firstMember = $members[0];
        self::assertIsArray($firstMember);
        self::assertArrayHasKey('email', $firstMember);
        self::assertSame('assignee@taskflow.fr', $firstMember['email']);
    }

    public function testMemberCanGetTaskDetail(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);

        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'Ma tâche'], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $taskId = $this->extractIntId($this->getJsonResponse());

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);

        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');
        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');

        $this->getTaskById($taskId, $memberToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Ma tâche', $data['title']);
        self::assertSame($projectId, $data['projectId']);
    }

    public function testNonMemberCannotGetTaskDetail(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('other@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);

        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'Privée'], $ownerToken);

        $taskId = $this->extractIntId($this->getJsonResponse());

        $otherToken = $this->loginAndGetToken('other@taskflow.fr', 'TaskFlowUser123');
        $this->getTaskById($taskId, $otherToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanUpdateTask(): void
    {
        $this->createUser('owner@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);

        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'Avant'], $ownerToken);

        $taskId = $this->extractIntId($this->getJsonResponse());

        $this->updateTaskById($taskId, [
            'title' => 'Après',
            'description' => 'Nouvelle description',
            'priority' => TaskPriority::HIGH->value,
        ], $ownerToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Après', $data['title']);
        self::assertSame('Nouvelle description', $data['description']);
        self::assertSame(TaskPriority::HIGH->value, $data['priority']);
    }

    public function testMemberCannotUpdateTask(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);

        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'Tâche'], $ownerToken);

        $taskId = $this->extractIntId($this->getJsonResponse());
        $project = $this->entityManager->getRepository(Project::class)->find($projectId);

        self::assertNotNull($project);
        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');
        $this->updateTaskById($taskId, ['title' => 'Hack'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanDeleteTask(): void
    {
        $this->createUser('owner@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'À supprimer'], $ownerToken);
        $taskId = $this->extractIntId($this->getJsonResponse());

        $this->deleteTaskById($taskId, $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->getTaskById($taskId, $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMemberCannotDeleteTask(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'Tâche'], $ownerToken);
        $taskId = $this->extractIntId($this->getJsonResponse());

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);
        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');
        $this->deleteTaskById($taskId, $memberToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testManagerCanDeleteTask(): void
    {
        $this->createUser('owner@taskflow.fr');
        $managerToken = $this->loginAsManager();

        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');
        $this->postProject(['title' => 'Projet', 'description' => 'Test'], $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->postProjectTask($projectId, ['title' => 'Tâche manager'], $ownerToken);
        $taskId = $this->extractIntId($this->getJsonResponse());

        $this->deleteTaskById($taskId, $managerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}
