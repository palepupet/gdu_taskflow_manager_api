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

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }
}
