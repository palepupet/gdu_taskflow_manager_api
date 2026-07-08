<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Entity\Project;
use App\Enum\ProjectStatus;
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
        self::assertSame('user@taskflow.fr', $data['owner']['email']);
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
        $this->createUser();
        $userToken = $this->loginAsUser();
        $managerToken = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $userToken);
        $created = $this->getJsonResponse();
        $projectId = $this->extractIntId($created);

        $this->getProjectById($projectId, $managerToken);
        self::assertResponseIsSuccessful();
    }

    public function testOwnerCanGetItsProjectById(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $created = $this->getJsonResponse();
        $projectId = $this->extractIntId($created);

        $this->getProjectById($projectId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame($projectId, $data['id']);
        self::assertSame('Création API de gestion de projets', $data['title']);
        self::assertIsArray($data['owner']);
        self::assertSame('user@taskflow.fr', $data['owner']['email']);
    }

    public function testMemberCanGetItsProjectById(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        $created = $this->getJsonResponse();
        $projectId = $this->extractIntId($created);

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);

        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');

        $this->getProjectById($projectId, $memberToken);
        self::assertResponseIsSuccessful();
    }

    public function testMemberCanSeeProjectTheyBelongTo(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $created = $this->getJsonResponse();
        $projectId = $this->extractIntId($created);

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);

        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');

        $this->getProjects($memberToken);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(1, $data);

        foreach ($data as $projectData) {
            self::assertIsArray($projectData);
            self::assertSame('Création API de gestion de projets', $projectData['title']);
            self::assertIsArray($projectData['owner']);
            self::assertSame('owner@taskflow.fr', $projectData['owner']['email']);
        }
    }

    public function testUserCannotGetProjectTheyDoNotBelongTo(): void
    {
        $this->createUser();
        $this->createUser('other@taskflow.fr');
        $ownerToken = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        $created = $this->getJsonResponse();
        $projectId = $this->extractIntId($created);
        $otherToken = $this->loginAndGetToken('other@taskflow.fr', 'TaskFlowUser123');

        $this->getProjectById($projectId, $otherToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanUpdateItsProject(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['title' => 'Titre modifié'], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertSame('Titre modifié', $data['title']);
        self::assertNotNull($data['updatedAt']);
    }

    public function testManagerCanUpdateAnyProject(): void
    {
        $this->createUser();
        $userToken = $this->loginAsUser();
        $managerToken = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $userToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['description' => 'MAJ manager'], $managerToken);
        self::assertResponseIsSuccessful();
    }

    public function testMemberCannotUpdateProject(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);

        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');
        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');

        $this->updateProjectById($projectId, ['title' => 'Hack'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testUserCannotUpdateProjectTheyDoNotBelongTo(): void
    {
        $this->createUser();
        $this->createUser('other@taskflow.fr');
        $ownerToken = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());
        $otherToken = $this->loginAndGetToken('other@taskflow.fr', 'TaskFlowUser123');

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
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);
        self::assertResponseIsSuccessful();

        $this->updateProjectById($projectId, ['title' => 'Tentative'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testOwnerCanRestoreArchivedProject(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);
        self::assertResponseIsSuccessful();

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
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $ownerToken);
        self::assertResponseIsSuccessful();

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);
        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');
        $this->updateProjectById($projectId, ['status' => ProjectStatus::IN_PROGRESS->value], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRestoredProjectCanBeUpdatedAgain(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);
        $this->updateProjectById($projectId, ['status' => ProjectStatus::IN_PROGRESS->value], $token);
        self::assertResponseIsSuccessful();

        $this->updateProjectById($projectId, ['title' => 'Projet restauré'], $token);
        self::assertResponseIsSuccessful();
        self::assertSame('Projet restauré', $this->getJsonResponse()['title']);
    }

    public function testOwnerCanReadArchivedProject(): void
    {
        $this->createUser();
        $token = $this->loginAsUser();

        $this->postProject($this->getValidCreateProjectPayload(), $token);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);
        self::assertResponseIsSuccessful();

        $this->getProjectById($projectId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertTrue($data['isArchived']);
        self::assertSame(ProjectStatus::COMPLETED->value, $data['status']);
    }

    public function testMemberCanReadArchivedProject(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);
        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $ownerToken);
        self::assertResponseIsSuccessful();

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');
        $this->getProjectById($projectId, $memberToken);
        self::assertResponseIsSuccessful();

        self::assertTrue($this->getJsonResponse()['isArchived']);
    }

    public function testManagerCannotUpdateArchivedProject(): void
    {
        $this->createUser();
        $userToken = $this->loginAsUser();
        $managerToken = $this->loginAsManager();

        $this->postProject($this->getValidCreateProjectPayload(), $userToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $userToken);
        self::assertResponseIsSuccessful();

        $this->updateProjectById($projectId, ['title' => 'Tentative manager'], $managerToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testMemberCannotUpdateArchivedProject(): void
    {
        $this->createUser('owner@taskflow.fr');
        $this->createUser('member@taskflow.fr');
        $ownerToken = $this->loginAndGetToken('owner@taskflow.fr', 'TaskFlowUser123');

        $this->postProject($this->getValidCreateProjectPayload(), $ownerToken);
        $projectId = $this->extractIntId($this->getJsonResponse());

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);
        $this->addMemberToProjectByEmail($project, 'member@taskflow.fr');

        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $ownerToken);
        self::assertResponseIsSuccessful();

        $memberToken = $this->loginAndGetToken('member@taskflow.fr', 'TaskFlowUser123');
        $this->updateProjectById($projectId, ['title' => 'Tentative membre'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
