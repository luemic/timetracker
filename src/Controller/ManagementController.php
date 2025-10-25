<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\ProjectActivity;
use App\Entity\TimeBooking;
use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeBookingRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api', name: 'api_')]
class ManagementController extends AbstractController
{
    #[Route('/customers', name: 'create_customer', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createCustomer(Request $request, CustomerRepository $customers): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return $this->json(['error' => 'Field "name" is required'], 400);
        }

        $customer = (new Customer())->setName($name);
        $customers->save($customer, true);

        return $this->json(['id' => $customer->getId(), 'name' => $customer->getName()], 201);
    }

    #[Route('/activities', name: 'create_activity', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createActivity(Request $request, ActivityRepository $activities): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            return $this->json(['error' => 'Field "name" is required'], 400);
        }
        $activity = (new Activity())->setName($name);
        $activities->save($activity, true);
        return $this->json(['id' => $activity->getId(), 'name' => $activity->getName()], 201);
    }

    #[Route('/projects', name: 'create_project', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createProject(Request $request, ProjectRepository $projects, CustomerRepository $customers): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        $name = trim((string)($data['name'] ?? ''));
        $customerId = $data['customerId'] ?? null;
        if ($name === '' || !is_numeric($customerId)) {
            return $this->json(['error' => 'Fields "name" and numeric "customerId" are required'], 400);
        }
        $customer = $customers->find((int)$customerId);
        if (!$customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }

        $project = (new Project())
            ->setName($name)
            ->setCustomer($customer)
            ->setExternalTicketUrl($data['externalTicketUrl'] ?? null)
            ->setExternalTicketLogin($data['externalTicketLogin'] ?? null)
            ->setExternalTicketCredentials($data['externalTicketCredentials'] ?? null);

        $projects->save($project, true);

        return $this->json([
            'id' => $project->getId(),
            'name' => $project->getName(),
            'customerId' => $customer->getId(),
            'externalTicketUrl' => $project->getExternalTicketUrl(),
            'externalTicketLogin' => $project->getExternalTicketLogin(),
            'externalTicketCredentials' => $project->getExternalTicketCredentials(),
        ], 201);
    }

    #[Route('/projects/{projectId}/activities', name: 'attach_activity_to_project', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function attachActivityToProject(
        int $projectId,
        Request $request,
        ProjectRepository $projects,
        ActivityRepository $activities,
        ProjectActivityRepository $projectActivities
    ): JsonResponse {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];
        $activityId = $data['activityId'] ?? null;
        $factor = isset($data['factor']) ? (float)$data['factor'] : 1.0;

        if (!is_numeric($activityId)) {
            return $this->json(['error' => 'Field "activityId" is required'], 400);
        }

        $project = $projects->find($projectId);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }
        $activity = $activities->find((int)$activityId);
        if (!$activity) {
            return $this->json(['error' => 'Activity not found'], 404);
        }

        // Check existing link
        $existing = $projectActivities->findOneBy(['project' => $project, 'activity' => $activity]);
        if ($existing) {
            $existing->setFactor($factor);
            $projectActivities->save($existing, true);
            return $this->json([
                'id' => $existing->getId(),
                'projectId' => $project->getId(),
                'activityId' => $activity->getId(),
                'factor' => $existing->getFactor(),
                'updated' => true,
            ], 200);
        }

        $pa = (new ProjectActivity())
            ->setProject($project)
            ->setActivity($activity)
            ->setFactor($factor);
        $projectActivities->save($pa, true);

        return $this->json([
            'id' => $pa->getId(),
            'projectId' => $project->getId(),
            'activityId' => $activity->getId(),
            'factor' => $pa->getFactor(),
        ], 201);
    }

    #[Route('/time-bookings', name: 'create_time_booking', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createTimeBooking(
        Request $request,
        ProjectRepository $projects,
        ActivityRepository $activities,
        TimeBookingRepository $timeBookings
    ): JsonResponse {
        $data = json_decode($request->getContent() ?: '[]', true) ?: [];

        $projectId = $data['projectId'] ?? null;
        $activityId = $data['activityId'] ?? null; // optional
        $ticketNumber = trim((string)($data['ticketNumber'] ?? ''));
        $startedAtStr = $data['startedAt'] ?? null;
        $endedAtStr = $data['endedAt'] ?? null;
        $durationMinutes = $data['durationMinutes'] ?? null;

        if (!is_numeric($projectId) || $ticketNumber === '' || !$startedAtStr || !$endedAtStr) {
            return $this->json(['error' => 'Fields required: projectId, ticketNumber, startedAt, endedAt'], 400);
        }

        try {
            $startedAt = new DateTimeImmutable((string)$startedAtStr);
            $endedAt = new DateTimeImmutable((string)$endedAtStr);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format for startedAt or endedAt'], 400);
        }
        if ($endedAt <= $startedAt) {
            return $this->json(['error' => 'endedAt must be after startedAt'], 400);
        }

        $project = $projects->find((int)$projectId);
        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        $activity = null;
        if ($activityId !== null) {
            if (!is_numeric($activityId)) {
                return $this->json(['error' => 'activityId must be numeric when provided'], 400);
            }
            $activity = $activities->find((int)$activityId);
            if (!$activity) {
                return $this->json(['error' => 'Activity not found'], 404);
            }
        }

        // Compute duration if not provided
        if ($durationMinutes === null) {
            $diff = $endedAt->getTimestamp() - $startedAt->getTimestamp();
            $durationMinutes = (int) round($diff / 60);
        } else {
            $durationMinutes = (int)$durationMinutes;
        }
        if ($durationMinutes <= 0) {
            return $this->json(['error' => 'durationMinutes must be > 0'], 400);
        }

        $tb = (new TimeBooking())
            ->setProject($project)
            ->setActivity($activity)
            ->setTicketNumber($ticketNumber)
            ->setStartedAt($startedAt)
            ->setEndedAt($endedAt)
            ->setDurationMinutes($durationMinutes);

        $timeBookings->save($tb, true);

        return $this->json([
            'id' => $tb->getId(),
            'projectId' => $project->getId(),
            'activityId' => $activity?->getId(),
            'ticketNumber' => $tb->getTicketNumber(),
            'startedAt' => $tb->getStartedAt()->format(DATE_ATOM),
            'endedAt' => $tb->getEndedAt()->format(DATE_ATOM),
            'durationMinutes' => $tb->getDurationMinutes(),
        ], 201);
    }
}
