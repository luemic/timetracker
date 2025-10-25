<?php

namespace App\Controller;

use App\Service\ActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * REST endpoints for managing activities.
 */
#[Route('/api/activities', name: 'api_activities_')]
#[IsGranted('ROLE_USER')]
class ActivityController extends AbstractController
{
    /**
     * List all activities.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ActivityService $svc): JsonResponse
    {
        return $this->json($svc->list());
    }

    /**
     * Get a single activity by ID.
     */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getOne(int $id, ActivityService $svc): JsonResponse
    {
        $item = $svc->get($id);
        if (!$item) {
            return $this->json(['error' => 'Not found'], 404);
        }
        return $this->json($item);
    }

    /**
     * Create a new activity.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, ActivityService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->create($data);
            return $this->json($item, 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }
    }

    /**
     * Update an existing activity.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $request, ActivityService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->update($id, $data);
            if (!$item) { return $this->json(['error' => 'Not found'], 404); }
            return $this->json($item);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }
    }

    /**
     * Delete an activity by ID.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, ActivityService $svc): JsonResponse
    {
        $ok = $svc->delete($id);
        if (!$ok) {
            return $this->json(['error' => 'Not found'], 404);
        }
        return $this->json(null, 204);
    }
}
