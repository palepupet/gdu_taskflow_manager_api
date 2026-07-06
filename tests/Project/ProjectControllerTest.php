<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Enum\ProjectStatus;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProjectControllerTest extends ApiTestCase
{
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
}
