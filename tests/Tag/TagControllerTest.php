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

    public function testOwnerCanListTags(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();

        $this->postProjectTag($projectId, ['label' => 'urgent'], $token);
        $this->postProjectTag($projectId, ['label' => 'backend'], $token);

        $this->getProjectTags($projectId, $token);
        self::assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        self::assertCount(2, $data);
        $labels = array_column($data, 'label');
        self::assertSame(['backend', 'urgent'], $labels);
    }

    public function testMemberCanListTags(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $this->postProjectTag($projectId, ['label' => 'ops'], $token);

        $memberToken = $this->addMemberToProject($projectId);
        $this->getProjectTags($projectId, $memberToken);
        self::assertResponseIsSuccessful();
    }

    public function testRandomUserCannotListTags(): void
    {
        ['projectId' => $projectId] = $this->createProjectAs();
        $this->createUser(self::EMAIL_OTHER);
        $otherToken = $this->loginAsUser(self::EMAIL_OTHER);

        $this->getProjectTags($projectId, $otherToken);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCanListTagsOnArchivedProject(): void
    {
        ['projectId' => $projectId, 'token' => $token] = $this->createProjectAs();
        $this->postProjectTag($projectId, ['label' => 'legacy'], $token);
        $this->archiveProject($projectId, $token);

        $this->getProjectTags($projectId, $token);
        self::assertResponseIsSuccessful();
    }
}
