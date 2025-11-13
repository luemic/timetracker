<?php

namespace App\Controller;

use App\Service\TicketSystemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * REST endpoints for managing ticket systems.
 */
#[Route('/api/ticket-systems', name: 'api_ticket_systems_')]
#[IsGranted('ROLE_USER')]
class TicketSystemController extends AbstractController
{
    /** List all ticket systems. */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(TicketSystemService $svc): JsonResponse
    {
        return $this->json($svc->list());
    }

    /** Get one. */
    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getOne(int $id, TicketSystemService $svc): JsonResponse
    {
        $item = $svc->get($id);
        if (!$item) { return $this->json(['error' => 'Not found'], 404); }
        return $this->json($item);
    }

    /** Create. */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, TicketSystemService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->create($data);
            return $this->json($item, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** Update. */
    #[Route('/{id}', name: 'update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $request, TicketSystemService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->update($id, $data);
            if (!$item) { return $this->json(['error' => 'Not found'], 404); }
            return $this->json($item);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /** Delete. */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, TicketSystemService $svc): JsonResponse
    {
        $ok = $svc->delete($id);
        if (!$ok) { return $this->json(['error' => 'Not found'], 404); }
        return $this->json(null, 204);
    }
}
