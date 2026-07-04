<?php

declare(strict_types=1);

namespace App\Tests;

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
     * @param array<string, mixed>  $payload
     * @param array<string, string> $extraHeaders
     */
    protected function postUser(
        array $payload,
        ?string $token = null,
        array $extraHeaders = [],
    ): void {
        $server = array_merge(
            ['CONTENT_TYPE' => 'application/json'],
            null !== $token ? ['HTTP_AUTHORIZATION' => 'Bearer '.$token] : [],
            $extraHeaders,
        );

        $this->client->request(
            'POST',
            '/user',
            server: $server,
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
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

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
