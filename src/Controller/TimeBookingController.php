<?php

namespace App\Controller;

use App\Service\TimeBookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/time-bookings', name: 'api_time_bookings_')]
#[IsGranted('ROLE_USER')]
class TimeBookingController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(TimeBookingService $svc): JsonResponse
    {
        return $this->json($svc->list());
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    public function getOne(int $id, TimeBookingService $svc): JsonResponse
    {
        $item = $svc->get($id);
        if (!$item) { return $this->json(['error' => 'Not found'], 404); }
        return $this->json($item);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request, TimeBookingService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->create($data);
            return $this->json($item, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT','PATCH'])]
    public function update(int $id, Request $request, TimeBookingService $svc): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        try {
            $item = $svc->update($id, $data);
            if (!$item) { return $this->json(['error' => 'Not found'], 404); }
            return $this->json($item);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id, TimeBookingService $svc): JsonResponse
    {
        $ok = $svc->delete($id);
        if (!$ok) { return $this->json(['error' => 'Not found'], 404); }
        return $this->json(null, 204);
    }
}
