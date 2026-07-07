<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class ApiTestCase extends WebTestCase
{
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

    protected function createManager(string $email = 'manager@taskflow.fr', string $password = 'TaskFlowManager123'): User
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

        return $this->loginAndGetToken('manager@taskflow.fr', 'TaskFlowManager123');
    }

    protected function loginAsUser(string $email = 'user@taskflow.fr'): string
    {
        return $this->loginAndGetToken($email, 'TaskFlowUser123');
    }

    protected function getMe(?string $token = null): void
    {
        $this->requestJson('GET', '/me', null, $token);
    }

    protected function createInactiveUser(string $email = 'manager@taskflow.fr'): User
    {
        $user = $this->createManager($email, 'InactiveManager123');
        $user->setIsActive(false);

        $this->entityManager->flush();

        return $user;
    }

    protected function createUser(
        string $email = 'user@taskflow.fr',
        string $password = 'TaskFlowUser123',
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

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
