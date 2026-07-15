<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @SuppressWarnings("PHPMD.TooManyMethods")
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const EMAIL_USER = 'user@taskflow.fr';
    protected const EMAIL_OWNER = 'owner@taskflow.fr';
    protected const EMAIL_MEMBER = 'member@taskflow.fr';
    protected const EMAIL_MANAGER = 'manager@taskflow.fr';
    protected const EMAIL_OTHER = 'other@taskflow.fr';
    protected const EMAIL_ASSIGNEE = 'assignee@taskflow.fr';

    protected const PASSWORD_USER = 'TaskFlowUser123';
    protected const PASSWORD_MANAGER = 'TaskFlowManager123';

    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->resetDatabase();
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string>     $extraServer
     */
    protected function requestJson(
        string $method,
        string $uri,
        ?array $payload = null,
        ?string $token = null,
        array $extraServer = [],
    ): void {
        $server = array_merge(
            null !== $payload ? ['CONTENT_TYPE' => 'application/json'] : [],
            null !== $token ? ['HTTP_AUTHORIZATION' => 'Bearer '.$token] : [],
            $extraServer,
        );

        $content = null !== $payload
            ? json_encode($payload, JSON_THROW_ON_ERROR)
            : null;

        $this->client->request($method, $uri, server: $server, content: $content);
    }

    protected function createManager(string $email = self::EMAIL_MANAGER, string $password = self::PASSWORD_MANAGER): User
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email)
            ->setFirstName('Manager')
            ->setLastName('Taskflow')
            ->setRoles([UserRole::Manager->value])
            ->setPassword($passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function loginAndGetToken(string $email, string $password): string
    {
        $this->client->request(
            'POST',
            '/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{token: string} $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $data['token'];
    }

    protected function loginAsManager(): string
    {
        $this->createManager();

        return $this->loginAndGetToken(self::EMAIL_MANAGER, self::PASSWORD_MANAGER);
    }

    protected function loginAsUser(string $email = self::EMAIL_USER): string
    {
        return $this->loginAndGetToken($email, self::PASSWORD_USER);
    }

    protected function getMe(?string $token = null): void
    {
        $this->requestJson('GET', '/me', null, $token);
    }

    protected function createInactiveUser(string $email = self::EMAIL_MANAGER): User
    {
        $user = $this->createManager($email, 'InactiveManager123');
        $user->setIsActive(false);

        $this->entityManager->flush();

        return $user;
    }

    protected function createUser(
        string $email = self::EMAIL_USER,
        string $password = self::PASSWORD_USER,
    ): User {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user
            ->setFirstName('User')
            ->setLastName('Taskflow')
            ->setEmail($email)
            ->setRoles([UserRole::User->value])
            ->setPassword($passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postUser(array $payload, ?string $token = null): void
    {
        $this->requestJson('POST', '/user', $payload, $token);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array<string, mixed> $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @return array{
     *     firstName: string,
     *     lastName: string,
     *     email: string,
     *     password: string
     * }
     */
    protected function getValidCreateUserPayload(): array
    {
        return [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@taskflow.fr',
            'password' => 'Rosebud123',
        ];
    }

    protected function getUsers(?string $token = null): void
    {
        $this->requestJson('GET', '/users', null, $token);
    }

    protected function getUserById(int $id, ?string $token = null): void
    {
        $this->requestJson('GET', '/user/'.$id, null, $token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function updateUserById(int $id, array $payload, ?string $token = null): void
    {
        $this->requestJson('PATCH', '/user/'.$id, $payload, $token);
    }

    protected function deleteUserById(int $id, ?string $token = null): void
    {
        $this->requestJson('DELETE', '/user/'.$id, null, $token);
    }

    /**
     * @return array{title: string, description: string}
     */
    protected function getValidCreateProjectPayload(): array
    {
        return [
            'title' => 'Création API de gestion de projets',
            'description' => 'Projet interne',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postProject(array $payload, ?string $token = null): void
    {
        $this->requestJson('POST', '/project', $payload, $token);
    }

    protected function getProjects(?string $token = null): void
    {
        $this->requestJson('GET', '/projects', null, $token);
    }

    protected function getProjectById(int $id, ?string $token = null): void
    {
        $this->requestJson('GET', '/project/'.$id, null, $token);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function extractIntId(array $data, string $key = 'id'): int
    {
        self::assertArrayHasKey($key, $data);
        self::assertIsInt($data[$key]);

        return $data[$key];
    }

    protected function addMemberToProjectByEmail(Project $project, string $email): void
    {
        $member = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($member);

        $project->addMember($member);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function updateProjectById(int $id, array $payload, ?string $token = null): void
    {
        $this->requestJson('PATCH', '/project/'.$id, $payload, $token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function addProjectMembersById(int $projectId, array $payload, ?string $token = null): void
    {
        $this->requestJson('POST', '/project/'.$projectId.'/members', $payload, $token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function removeProjectMembersById(int $projectId, array $payload, ?string $token = null): void
    {
        $this->requestJson('DELETE', '/project/'.$projectId.'/members', $payload, $token);
    }

    protected function getProjectTasks(int $projectId, ?string $token = null): void
    {
        $this->requestJson('GET', '/project/'.$projectId.'/tasks', null, $token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postProjectTask(int $projectId, array $payload, ?string $token = null): void
    {
        $this->requestJson('POST', '/project/'.$projectId.'/tasks', $payload, $token);
    }

    protected function getTaskById(int $id, ?string $token = null): void
    {
        $this->requestJson('GET', '/task/'.$id, null, $token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function updateTaskById(int $id, array $payload, ?string $token = null): void
    {
        $this->requestJson('PATCH', '/task/'.$id, $payload, $token);
    }

    protected function deleteTaskById(int $id, ?string $token = null): void
    {
        $this->requestJson('DELETE', '/task/'.$id, null, $token);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{projectId: int, token: string}
     */
    protected function createProjectAs(string $email = self::EMAIL_OWNER, ?array $payload = null): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $this->createUser($email);
        }

        $token = $this->loginAndGetToken($email, self::PASSWORD_USER);
        $payload ??= ['title' => 'Projet', 'description' => 'Test'];

        $this->postProject($payload, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return [
            'projectId' => $this->extractIntId($this->getJsonResponse()),
            'token' => $token,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function addTaskToProject(int $projectId, array $payload, string $token): int
    {
        $this->postProjectTask($projectId, $payload, $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->extractIntId($this->getJsonResponse());
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function addAssignedTaskToProject(
        int $projectId,
        string $ownerToken,
        string $assigneeEmail,
        array $payload,
    ): int {
        $assignee = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $assigneeEmail]);
        if (!$assignee instanceof User) {
            $this->createUser($assigneeEmail);
            $assignee = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $assigneeEmail]);
        }

        self::assertNotNull($assignee);

        $assigneeId = $assignee->getId();
        self::assertNotNull($assigneeId);

        $payload['assignee'] = $assigneeId;

        return $this->addTaskToProject($projectId, $payload, $ownerToken);
    }

    protected function addMemberToProject(int $projectId, string $email = self::EMAIL_MEMBER): string
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $this->createUser($email);
        }

        $project = $this->entityManager->getRepository(Project::class)->find($projectId);
        self::assertNotNull($project);

        $this->addMemberToProjectByEmail($project, $email);

        return $this->loginAndGetToken($email, self::PASSWORD_USER);
    }

    protected function archiveProject(int $projectId, string $token): void
    {
        $this->updateProjectById($projectId, ['status' => ProjectStatus::COMPLETED->value], $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertTrue($data['isArchived']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postProjectTag(int $projectId, array $payload, ?string $token = null): void
    {
        $this->requestJson('POST', '/project/'.$projectId.'/tags', $payload, $token);
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
