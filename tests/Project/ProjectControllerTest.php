<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\TaskPriority;
use App\Enum\TaskState;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
class ProjectControllerTest extends ApiTestCase
{
    public function testManagerCanListAllProjects(): void
    {
        $token = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $this->postProject(['title' => 'Projet interne 2', 'description' => 'Description du projet interne 2'], $token);

        $this->getProjects($token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(2, $data);

        foreach ($data as $project) {
            self::assertIsArray($project);
            self::assertArrayHasKey('id', $project);
            self::assertArrayHasKey('title', $project);
            self::assertArrayHasKey('owner', $project);
            self::assertArrayHasKey('members', $project);
        }
    }

    public function testUserCanOnlySeesTheirOwnProjects(): void
    {
        $this->createUser();
        $userToken = $this->loginAsUser();
        $managerToken = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $userToken);
        $this->postProject(['title' => 'Projet privé manager', 'description' => 'Invisible pour user'], $managerToken);

        $this->getProjects($userToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(1, $data);

        foreach ($data as $project) {
            self::assertIsArray($project);
            self::assertSame('Création API de gestion de projets', $project['title']);
        }
    }

    public function testAuthenticatedUserCanCreateProject(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->getJsonResponse();
        self::assertSame('Création API de gestion de projets', $data['title']);
        self::assertSame('Projet interne', $data['description']);
        self::assertSame(ProjectStatus::IN_PROGRESS->value, $data['status']);

        self::assertIsArray($data['owner']);
        self::assertSame(self::EMAIL_USER, $data['owner']['email']);
        self::assertSame('User', $data['owner']['firstName']);
        self::assertSame('Taskflow', $data['owner']['lastName']);
        self::assertArrayHasKey('id', $data['owner']);
        self::assertIsArray($data['members']);
        self::assertCount(0, $data['members']);

        self::assertFalse($data['isArchived']);
        self::assertArrayHasKey('createdAt', $data);

        self::assertNull($data['startAt']);
        self::assertNull($data['endAt']);
        self::assertNull($data['updatedAt']);
        self::assertNotNull($data['createdAt']);
    }

    public function testAuthenticatedUserCanCreateProjectWithDates(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();
        $payload = $this->getValidCreateProjectPayload();

        $payload['startAt'] = '2026-08-01';
        $payload['endAt'] = '2026-12-31';

        $this->postProject($payload, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->getJsonResponse();
        self::assertSame('2026-08-01', $data['startAt']);
        self::assertSame('2026-12-31', $data['endAt']);
        self::assertNull($data['updatedAt']);
    }

    public function testUnauthenticatedUserCannotCreateProject(): void
    {
        $this->postProject($this->getValidCreateProjectPayload());
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateProjectWithEndDateBeforeStartDateReturns400(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();
        $payload = $this->getValidCreateProjectPayload();

        $payload['startAt'] = '2026-12-01';
        $payload['endAt'] = '2026-08-01';

        $this->postProject($payload, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testManagerCanGetAnyProjectById(): void
    {
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_USER, $this->getValidCreateProjectPayload());
        $managerToken = $this->loginAsManager();

        $this->getProjectById($projectId, $managerToken);
        self::assertResponseIsSuccessful();
    }

    public function testOwnerCanGetItsProjectById(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );

        $this->getProjectById($projectId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame($projectId, $data['id']);
        self::assertSame('Création API de gestion de projets', $data['title']);
        self::assertIsArray($data['owner']);
        self::assertSame(self::EMAIL_USER, $data['owner']['email']);
    }

    public function testMemberCanGetItsProjectById(): void
    {
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_OWNER, $this->getValidCreateProjectPayload());
        $memberToken = $this->addMemberToProject($projectId);

        $this->getProjectById($projectId, $memberToken);
        self::assertResponseIsSuccessful();
    }

    public function testMemberCanSeeProjectTheyBelongTo(): void
    {
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_OWNER, $this->getValidCreateProjectPayload());
        $memberToken = $this->addMemberToProject($projectId);

        $this->getProjects($memberToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(1, $data);

        foreach ($data as $projectData) {
            self::assertIsArray($projectData);
            self::assertSame('Création API de gestion de projets', $projectData['title']);
            self::assertIsArray($projectData['owner']);
            self::assertSame(self::EMAIL_OWNER, $projectData['owner']['email']);
        }
    }

    public function testUserCannotGetProjectTheyDoNotBelongTo(): void
    {
        $this->createUser(self::EMAIL_OTHER);
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_USER, $this->getValidCreateProjectPayload());
        $otherToken = $this->loginAndGetToken(self::EMAIL_OTHER, self::PASSWORD_USER);

        $this->getProjectById($projectId, $otherToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanUpdateItsProject(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );

        $this->updateProjectById($projectId, ['title' => 'Titre modifié'], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Titre modifié', $data['title']);
        self::assertNotNull($data['updatedAt']);
    }

    public function testManagerCanUpdateAnyProject(): void
    {
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_USER, $this->getValidCreateProjectPayload());
        $managerToken = $this->loginAsManager();

        $this->updateProjectById($projectId, ['description' => 'MAJ manager'], $managerToken);
        self::assertResponseIsSuccessful();
    }

    public function testMemberCannotUpdateProject(): void
    {
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_OWNER, $this->getValidCreateProjectPayload());
        $memberToken = $this->addMemberToProject($projectId);

        $this->updateProjectById($projectId, ['title' => 'Hack'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUserCannotUpdateProjectTheyDoNotBelongTo(): void
    {
        $this->createUser(self::EMAIL_OTHER);
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_USER, $this->getValidCreateProjectPayload());
        $otherToken = $this->loginAndGetToken(self::EMAIL_OTHER, self::PASSWORD_USER);

        $this->updateProjectById($projectId, ['title' => 'Hack'], $otherToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanArchiveProjectBySettingStatusToCompleted(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(ProjectStatus::COMPLETED->value, $data['status']);
        self::assertTrue($data['isArchived']);
        self::assertIsString($data['archivedAt']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['archivedAt']));
    }

    public function testManagerCanCancelProject(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();
        $managerToken = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::CANCELLED->value], $managerToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(ProjectStatus::CANCELLED->value, $data['status']);
        self::assertTrue($data['isArchived']);
        self::assertIsString($data['archivedAt']);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['archivedAt']));
    }

    public function testArchivedProjectCannotBeUpdated(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );

        $this->archiveProject($projectId, $token);

        $this->updateProjectById($projectId, ['title' => 'Tentative'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanRestoreArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );

        $this->archiveProject($projectId, $token);

        $this->updateProjectById($projectId, ['status' => ProjectStatus::IN_PROGRESS->value], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(ProjectStatus::IN_PROGRESS->value, $data['status']);
        self::assertFalse($data['isArchived']);
        self::assertNull($data['archivedAt']);
    }

    public function testManagerCanRestoreArchivedProject(): void
    {
        $this->createUser();
        $userToken = $this->loginAsUser();
        $managerToken = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $userToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::CANCELLED->value], $managerToken);
        self::assertResponseIsSuccessful();

        $this->updateProjectById($projectId, ['status' => ProjectStatus::IN_PROGRESS->value], $managerToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame(ProjectStatus::IN_PROGRESS->value, $data['status']);
        self::assertFalse($data['isArchived']);
        self::assertNull($data['archivedAt']);
    }

    public function testMemberCannotRestoreArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs(
            self::EMAIL_OWNER,
            $this->getValidCreateProjectPayload(),
        );

        $this->archiveProject($projectId, $ownerToken);
        $memberToken = $this->addMemberToProject($projectId);

        $this->updateProjectById($projectId, ['status' => ProjectStatus::IN_PROGRESS->value], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRestoredProjectCanBeUpdatedAgain(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );

        $this->archiveProject($projectId, $token);
        $this->updateProjectById($projectId, ['status' => ProjectStatus::IN_PROGRESS->value], $token);
        self::assertResponseIsSuccessful();

        $this->updateProjectById($projectId, ['title' => 'Projet restauré'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('Projet restauré', $this->getJsonResponse()['title']);
    }

    public function testOwnerCanReadArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );

        $this->archiveProject($projectId, $token);

        $this->getProjectById($projectId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertTrue($data['isArchived']);
        self::assertSame(ProjectStatus::COMPLETED->value, $data['status']);
    }

    public function testMemberCanReadArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs(
            self::EMAIL_OWNER,
            $this->getValidCreateProjectPayload(),
        );
        $memberToken = $this->addMemberToProject($projectId);

        $this->archiveProject($projectId, $ownerToken);

        $this->getProjectById($projectId, $memberToken);
        self::assertResponseIsSuccessful();

        self::assertTrue($this->getJsonResponse()['isArchived']);
    }

    public function testManagerCannotUpdateArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $userToken] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );
        $managerToken = $this->loginAsManager();

        $this->archiveProject($projectId, $userToken);

        $this->updateProjectById($projectId, ['title' => 'Tentative manager'], $managerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testMemberCannotUpdateArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs(
            self::EMAIL_OWNER,
            $this->getValidCreateProjectPayload(),
        );
        $memberToken = $this->addMemberToProject($projectId);

        $this->archiveProject($projectId, $ownerToken);

        $this->updateProjectById($projectId, ['title' => 'Tentative membre'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanAddMemberToProject(): void
    {
        $this->createUser(self::EMAIL_MEMBER);
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs(
            self::EMAIL_OWNER,
            $this->getValidCreateProjectPayload(),
        );

        $member = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::EMAIL_MEMBER]);
        self::assertNotNull($member);

        $memberId = $member->getId();
        self::assertIsInt($memberId);
        $this->addProjectMembersById($projectId, ['members' => [$memberId]], $ownerToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertIsArray($data['members']);
        self::assertCount(1, $data['members']);
        self::assertIsArray($data['members'][0]);
        self::assertSame(self::EMAIL_MEMBER, $data['members'][0]['email']);
    }

    public function testManagerCanAddMemberToProject(): void
    {
        $this->createUser(self::EMAIL_MEMBER);
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_USER, $this->getValidCreateProjectPayload());
        $managerToken = $this->loginAsManager();

        $member = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::EMAIL_MEMBER]);
        self::assertNotNull($member);

        $memberId = $member->getId();
        self::assertIsInt($memberId);
        $this->addProjectMembersById($projectId, ['members' => [$memberId]], $managerToken);
        self::assertResponseIsSuccessful();
    }

    public function testMemberCannotAddMemberToProject(): void
    {
        $this->createUser(self::EMAIL_OTHER);
        ['projectId' => $projectId] = $this->createProjectAs(self::EMAIL_OWNER, $this->getValidCreateProjectPayload());
        $memberToken = $this->addMemberToProject($projectId);

        $other = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::EMAIL_OTHER]);
        self::assertNotNull($other);

        $otherId = $other->getId();
        self::assertIsInt($otherId);
        $this->addProjectMembersById($projectId, ['members' => [$otherId]], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotAddOwnerAsMember(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs(
            self::EMAIL_OWNER,
            $this->getValidCreateProjectPayload(),
        );

        $owner = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::EMAIL_OWNER]);
        self::assertNotNull($owner);

        $ownerId = $owner->getId();
        self::assertIsInt($ownerId);
        $this->addProjectMembersById($projectId, ['members' => [$ownerId]], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testOwnerCanRemoveMemberFromProject(): void
    {
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs(
            self::EMAIL_OWNER,
            $this->getValidCreateProjectPayload(),
        );
        $this->addMemberToProject($projectId);

        $member = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::EMAIL_MEMBER]);
        self::assertNotNull($member);

        $memberId = $member->getId();
        self::assertIsInt($memberId);

        $this->removeProjectMembersById($projectId, ['members' => [$memberId]], $ownerToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertIsArray($data['members']);
        self::assertCount(0, $data['members']);
    }

    public function testCannotManageMembersOnArchivedProject(): void
    {
        $this->createUser(self::EMAIL_MEMBER);
        ['projectId' => $projectId, 'token' => $ownerToken] = $this->createProjectAs(
            self::EMAIL_OWNER,
            $this->getValidCreateProjectPayload(),
        );

        $this->archiveProject($projectId, $ownerToken);

        $member = $this->entityManager->getRepository(User::class)->findOneBy(['email' => self::EMAIL_MEMBER]);
        self::assertNotNull($member);

        $memberId = $member->getId();
        self::assertIsInt($memberId);
        $this->addProjectMembersById($projectId, ['members' => [$memberId]], $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function testOwnerCanGetProjectWithItsTasks(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs(
            self::EMAIL_USER,
            $this->getValidCreateProjectPayload(),
        );

        $this->addTaskToProject($projectId, [
            'title' => 'Tâche 1',
            'priority' => TaskPriority::HIGH->value,
        ], $token);
        $this->addTaskToProject($projectId, ['title' => 'Tâche 2'], $token);

        $this->getProjectById($projectId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame($projectId, $data['id']);
        self::assertArrayHasKey('tasks', $data);
        self::assertIsArray($data['tasks']);
        self::assertCount(2, $data['tasks']);

        $titles = array_column($data['tasks'], 'title');
        self::assertContains('Tâche 1', $titles);
        self::assertContains('Tâche 2', $titles);

        foreach ($data['tasks'] as $task) {
            self::assertIsArray($task);
            self::assertArrayHasKey('id', $task);
            self::assertArrayHasKey('title', $task);
            self::assertArrayHasKey('description', $task);
            self::assertArrayHasKey('dueAt', $task);
            self::assertArrayHasKey('priority', $task);
            self::assertArrayHasKey('state', $task);
            self::assertArrayHasKey('projectId', $task);
            self::assertArrayHasKey('assignee', $task);
            self::assertArrayHasKey('createdAt', $task);
            self::assertArrayHasKey('updatedAt', $task);
            self::assertSame($projectId, $task['projectId']);
            self::assertSame(TaskState::OPEN->value, $task['state']);
        }

        $task1 = $data['tasks'][array_search('Tâche 1', $titles, true)];
        self::assertIsArray($task1);
        self::assertSame(TaskPriority::HIGH->value, $task1['priority']);

        $task2 = $data['tasks'][array_search('Tâche 2', $titles, true)];
        self::assertIsArray($task2);
        self::assertSame(TaskPriority::MEDIUM->value, $task2['priority']);
    }

    public function testOwnerCanSearchProjects(): void
    {
        ['token' => $token] = $this->createProjectAs();

        $this->searchProjects(['filters' => []], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(1, $data);
    }

    public function testOwnerCanFilterProjectsByStatus(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);

        $this->searchProjects([
            'filters' => ['status' => [ProjectStatus::COMPLETED->value]],
        ], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(1, $data);

        $statuses = array_column($data, 'status');
        self::assertSame([ProjectStatus::COMPLETED->value], $statuses);
    }

    public function testOwnerCanFilterProjectsByArchived(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);

        $this->searchProjects(['filters' => ['archived' => true]], $token);
        self::assertCount(1, $this->getJsonResponse());

        $this->searchProjects(['filters' => ['archived' => false]], $token);
        self::assertCount(0, $this->getJsonResponse());
    }

    public function testUserCanOnlySearchAccessibleProjects(): void
    {
        $this->createUser();
        $userToken = $this->loginAsUser();
        $managerToken = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $userToken);
        $this->postProject(['title' => 'Projet privé manager', 'description' => 'description'], $managerToken);

        $this->searchProjects(['filters' => []], $userToken);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->getJsonResponse());
    }
}
