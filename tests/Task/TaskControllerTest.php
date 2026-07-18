<?php

declare(strict_types=1);

namespace App\Tests\Task;

use App\Enum\TaskPriority;
use App\Enum\TaskState;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
class TaskControllerTest extends ApiTestCase
{
    public function testOwnerCanCreateTask(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_OWNER,
            ['title' => 'Projet tâches', 'description' => 'Test'],
        );

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
        ['projectId' => $projectId] = $this->createProjectAs();
        $memberToken = $this->addMemberToProject($projectId);

        $this->postProjectTask($projectId, ['title' => 'Interdit'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testMemberCanListTasks(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $this->addTaskToProject($projectId, ['title' => 'Tâche 1'], $ownerToken);
        $this->addTaskToProject($projectId, ['title' => 'Tâche 2'], $ownerToken);

        $memberToken = $this->addMemberToProject($projectId);

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
        $this->createUser(self::EMAIL_OTHER);
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $this->addTaskToProject($projectId, ['title' => 'Tâche privée'], $ownerToken);

        $otherToken = $this->loginAndGetToken(self::EMAIL_OTHER, self::PASSWORD_USER);
        $this->getProjectTasks($projectId, $otherToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAssigneeIsAutoAddedAsMember(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $this->addAssignedTaskToProject($projectId, $ownerToken, self::EMAIL_ASSIGNEE, ['title' => 'Tâche assignée']);

        $this->getProjectById($projectId, $ownerToken);

        $projectData = $this->getJsonResponse();
        self::assertArrayHasKey('members', $projectData);
        self::assertIsArray($projectData['members']);

        $members = $projectData['members'];
        self::assertCount(1, $members);

        $firstMember = $members[0];
        self::assertIsArray($firstMember);
        self::assertArrayHasKey('email', $firstMember);
        self::assertSame(self::EMAIL_ASSIGNEE, $firstMember['email']);
    }

    public function testMemberCanGetTaskDetail(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Ma tâche'], $ownerToken);

        $memberToken = $this->addMemberToProject($projectId);

        $this->getTaskById($taskId, $memberToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Ma tâche', $data['title']);
        self::assertSame($projectId, $data['projectId']);
    }

    public function testNonMemberCannotGetTaskDetail(): void
    {
        $this->createUser(self::EMAIL_OTHER);
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Privée'], $ownerToken);

        $otherToken = $this->loginAndGetToken(self::EMAIL_OTHER, self::PASSWORD_USER);
        $this->getTaskById($taskId, $otherToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanUpdateTask(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Avant'], $ownerToken);

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
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $ownerToken);

        $memberToken = $this->addMemberToProject($projectId);
        $this->updateTaskById($taskId, ['title' => 'Hack'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanDeleteTask(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'À supprimer'], $ownerToken);

        $this->deleteTaskById($taskId, $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->getTaskById($taskId, $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMemberCannotDeleteTask(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $ownerToken);

        $memberToken = $this->addMemberToProject($projectId);
        $this->deleteTaskById($taskId, $memberToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testManagerCanDeleteTask(): void
    {
        $managerToken = $this->loginAsManager();
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche manager'], $ownerToken);

        $this->deleteTaskById($taskId, $managerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testOwnerCanChangeTaskStateToInProgress(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Ma tâche'], $ownerToken);

        $this->updateTaskById($taskId, ['state' => TaskState::IN_PROGRESS->value], $ownerToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(TaskState::IN_PROGRESS->value, $data['state']);
    }

    public function testAssigneeCanCloseTask(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addAssignedTaskToProject($projectId, $ownerToken, self::EMAIL_ASSIGNEE, ['title' => 'Tâche assignée']);

        $assigneeToken = $this->loginAndGetToken(self::EMAIL_ASSIGNEE, self::PASSWORD_USER);
        $this->updateTaskById($taskId, ['state' => TaskState::CLOSED->value], $assigneeToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(TaskState::CLOSED->value, $data['state']);
    }

    public function testAssigneeCanReopenClosedTask(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addAssignedTaskToProject($projectId, $ownerToken, self::EMAIL_ASSIGNEE, ['title' => 'Tâche assignée']);

        $this->updateTaskById($taskId, ['state' => TaskState::CLOSED->value], $ownerToken);
        self::assertResponseIsSuccessful();

        $assigneeToken = $this->loginAndGetToken(self::EMAIL_ASSIGNEE, self::PASSWORD_USER);
        $this->updateTaskById($taskId, ['state' => TaskState::OPEN->value], $assigneeToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(TaskState::OPEN->value, $data['state']);
    }

    public function testAssigneeCannotChangeTaskStateToInProgress(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addAssignedTaskToProject($projectId, $ownerToken, self::EMAIL_ASSIGNEE, ['title' => 'Tâche assignée']);

        $assigneeToken = $this->loginAndGetToken(self::EMAIL_ASSIGNEE, self::PASSWORD_USER);
        $this->updateTaskById($taskId, ['state' => TaskState::IN_PROGRESS->value], $assigneeToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAssigneeCannotUpdateTaskTitle(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addAssignedTaskToProject($projectId, $ownerToken, self::EMAIL_ASSIGNEE, ['title' => 'Tâche assignée']);

        $assigneeToken = $this->loginAndGetToken(self::EMAIL_ASSIGNEE, self::PASSWORD_USER);
        $this->updateTaskById($taskId, ['title' => 'Hack'], $assigneeToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotCreateTaskOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $this->addTaskToProject($projectId, ['title' => 'Tâche existante'], $ownerToken);

        $this->archiveProject($projectId, $ownerToken);

        $this->postProjectTask($projectId, ['title' => 'Nouvelle tâche'], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotUpdateTaskOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche existante'], $ownerToken);

        $this->archiveProject($projectId, $ownerToken);

        $this->updateTaskById($taskId, ['title' => 'Nouveau titre'], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotDeleteTaskOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche existante'], $ownerToken);

        $this->archiveProject($projectId, $ownerToken);

        $this->deleteTaskById($taskId, $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotChangeTaskStateOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche existante'], $ownerToken);

        $this->archiveProject($projectId, $ownerToken);

        $this->updateTaskById($taskId, ['state' => TaskState::CLOSED->value], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanListTasksOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $this->addTaskToProject($projectId, ['title' => 'Tâche existante'], $ownerToken);

        $this->archiveProject($projectId, $ownerToken);

        $this->getProjectTasks($projectId, $ownerToken);
        self::assertResponseIsSuccessful();

        $tasks = $this->getJsonResponse();
        self::assertCount(1, $tasks);

        $titles = array_column($tasks, 'title');
        self::assertContains('Tâche existante', $titles);
    }

    public function testOwnerCanGetTaskDetailOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche existante'], $ownerToken);

        $this->archiveProject($projectId, $ownerToken);

        $this->getTaskById($taskId, $ownerToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Tâche existante', $data['title']);
        self::assertSame($projectId, $data['projectId']);
    }

    public function testManagerCannotCreateTaskOnArchivedProject(): void
    {
        $managerToken = $this->loginAsManager();
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs();

        $this->archiveProject($projectId, $ownerToken);

        $this->postProjectTask($projectId, ['title' => 'Tentative manager'], $managerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanAddTagToTask(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->postProjectTag($projectId, ['label' => 'urgent'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());

        $this->addTagToTask($taskId, $tagId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('tags', $data);
        self::assertIsArray($data['tags']);
        self::assertCount(1, $data['tags']);

        $labels = array_column($data['tags'], 'label');
        self::assertSame(['urgent'], $labels);
    }

    public function testMemberCannotAddTagToTask(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->postProjectTag($projectId, ['label' => 'ops'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());

        $memberToken = $this->addMemberToProject($projectId);
        $this->addTagToTask($taskId, $tagId, $memberToken);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotAddTagFromOtherProject(): void
    {
        ['projectId' => $projectIdA, 'token' => $tokenA] = $this->createProjectAs(self::EMAIL_OWNER);
        $taskId = $this->addTaskToProject($projectIdA, ['title' => 'Tâche A'], $tokenA);

        ['projectId' => $projectIdB, 'token' => $tokenB] = $this->createProjectAs(self::EMAIL_OTHER);
        $this->postProjectTag($projectIdB, ['label' => 'autre-projet'], $tokenB);
        $tagIdFromB = $this->extractIntId($this->getJsonResponse());

        $this->addTagToTask($taskId, $tagIdFromB, $tokenA);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $data = $this->getJsonResponse();
        self::assertSame('TAG_NOT_IN_PROJECT', $data['code']);
    }

    public function testCannotAddSameTagTwice(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->postProjectTag($projectId, ['label' => 'backend'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());

        $this->addTagToTask($taskId, $tagId, $token);
        self::assertResponseIsSuccessful();

        $this->addTagToTask($taskId, $tagId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $data = $this->getJsonResponse();
        self::assertSame('TAG_ALREADY_LINKED', $data['code']);
    }

    public function testCannotAddTagOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->postProjectTag($projectId, ['label' => 'legacy'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());

        $this->archiveProject($projectId, $token);

        $this->addTagToTask($taskId, $tagId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotAddUnknownTagToTask(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->addTagToTask($taskId, 99999, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCannotAddTagToUnknownTask(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $this->postProjectTag($projectId, ['label' => 'urgent'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());

        $this->addTagToTask(99999, $tagId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testOwnerCanRemoveTagFromTask(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->postProjectTag($projectId, ['label' => 'urgent'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());

        $this->addTagToTask($taskId, $tagId, $token);
        self::assertResponseIsSuccessful();

        $this->removeTagFromTask($taskId, $tagId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('tags', $data);
        self::assertIsArray($data['tags']);
        self::assertCount(0, $data['tags']);

        $this->getProjectTags($projectId, $token);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->getJsonResponse());
    }

    public function testMemberCannotRemoveTagFromTask(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->postProjectTag($projectId, ['label' => 'ops'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());
        $this->addTagToTask($taskId, $tagId, $token);

        $memberToken = $this->addMemberToProject($projectId);
        $this->removeTagFromTask($taskId, $tagId, $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotRemoveTagOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $taskId = $this->addTaskToProject($projectId, ['title' => 'Tâche'], $token);

        $this->postProjectTag($projectId, ['label' => 'legacy'], $token);
        $tagId = $this->extractIntId($this->getJsonResponse());
        $this->addTagToTask($taskId, $tagId, $token);

        $this->archiveProject($projectId, $token);

        $this->removeTagFromTask($taskId, $tagId, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
