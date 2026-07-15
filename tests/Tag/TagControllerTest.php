<?php

declare(strict_types=1);

namespace App\Tests\Tag;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class TagControllerTest extends ApiTestCase
{
    public function testOwnerCanCreateTag(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();

        $this->postProjectTag($projectId, ['label' => 'urgent'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = $this->getJsonResponse();
        self::assertSame('urgent', $data['label']);
        self::assertSame($projectId, $data['projectId']);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('createdAt', $data);
    }

    public function testMemberCannotCreateTag(): void
    {
        ['projectId' => $projectId] = $this->createProjectAs();

        $memberToken = $this->addMemberToProject($projectId);

        $this->postProjectTag($projectId, ['label' => 'interdit'], $memberToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotCreateDuplicateTag(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();

        $this->postProjectTag($projectId, ['label' => 'backend'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->postProjectTag($projectId, ['label' => 'backend'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    }

    public function testCannotCreateTagOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();

        $this->archiveProject($projectId, $token);

        $this->postProjectTag($projectId, ['label' => 'trop tard'], $token);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
