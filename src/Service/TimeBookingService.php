<?php

namespace App\Service;

use App\Entity\TimeBooking;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeBookingRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for managing time bookings.
 */
class TimeBookingService
{
    public function __construct(
        private readonly TimeBookingRepository $timeBookings,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Return all time bookings sorted by ID ascending.
     *
     * @return array<int, array<string,mixed>>
     */
    public function list(): array
    {
        $items = $this->timeBookings->findBy([], ['id' => 'ASC']);
        return array_map(fn(TimeBooking $timeBooking) => $this->toArray($timeBooking), $items);
    }

    /** Get a time booking by ID, mapped for JSON. */
    public function get(int $id): ?array
    {
        $timeBooking = $this->timeBookings->find($id);
        if ($timeBooking) {
            return $this->toArray($timeBooking);
        }

        return null;
    }

    /**
     * Create a time booking from request data.
     *
     * @param array{
     *  projectId?:int,
     *  activityId?:int|null,
     *  startedAt?:string,
     *  endedAt?:string,
     *  ticketNumber?:string,
     *  durationMinutes?:int|null
     * } $data
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        $projectId = $data['projectId'] ?? null;
        $ticketNumber = trim((string)($data['ticketNumber'] ?? ''));
        $startedAtStr = (string)($data['startedAt'] ?? '');
        $endedAtStr = (string)($data['endedAt'] ?? '');
        if (!is_numeric($projectId) || $ticketNumber === '' || $startedAtStr === '' || $endedAtStr === '') {
            throw new \InvalidArgumentException('Fields "projectId", "ticketNumber", "startedAt", "endedAt" are required');
        }
        $project = $this->projects->find((int)$projectId);
        if (!$project) { throw new \RuntimeException('Project not found'); }
        $activity = null;
        if (array_key_exists('activityId', $data) && $data['activityId'] !== null) {
            if (!is_numeric($data['activityId'])) { throw new \InvalidArgumentException('activityId must be numeric or null'); }
            $activity = $this->activities->find((int)$data['activityId']);
            if (!$activity) { throw new \RuntimeException('Activity not found'); }
        }
        try {
            $started = new DateTimeImmutable($startedAtStr);
            $ended = new DateTimeImmutable($endedAtStr);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid date format for startedAt/endedAt');
        }
        if ($ended <= $started) { throw new \InvalidArgumentException('endedAt must be after startedAt'); }
        $minutes = (int)($data['durationMinutes'] ?? 0);
        if ($minutes <= 0) {
            $minutes = (int) round(($ended->getTimestamp() - $started->getTimestamp()) / 60);
            if ($minutes <= 0) { throw new \InvalidArgumentException('durationMinutes must be > 0'); }
        }
        $timeBooking = (new TimeBooking())
            ->setProject($project)
            ->setActivity($activity)
            ->setStartedAt($started)
            ->setEndedAt($ended)
            ->setTicketNumber($ticketNumber)
            ->setDurationMinutes($minutes);
        $this->timeBookings->save($timeBooking, true);
        return $this->toArray($timeBooking);
    }

    /**
     * Update a time booking with partial data.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $timeBooking = $this->timeBookings->find($id);
        if (!$timeBooking) return null;
        if (array_key_exists('projectId', $data)) {
            $projectId = $data['projectId'];
            if (!is_numeric($projectId)) { throw new \InvalidArgumentException('projectId must be numeric'); }
            $project = $this->projects->find((int)$projectId);
            if (!$project) { throw new \RuntimeException('Project not found'); }
            $timeBooking->setProject($project);
        }
        if (array_key_exists('activityId', $data)) {
            $activityId = $data['activityId'];
            if ($activityId === null) {
                $timeBooking->setActivity(null);
            } else {
                if (!is_numeric($activityId)) { throw new \InvalidArgumentException('activityId must be numeric or null'); }
                $activity = $this->activities->find((int)$activityId);
                if (!$activity) { throw new \RuntimeException('Activity not found'); }
                $timeBooking->setActivity($activity);
            }
        }
        if (array_key_exists('startedAt', $data)) {
            $timeBooking->setStartedAt(new DateTimeImmutable((string)$data['startedAt']));
        }
        if (array_key_exists('endedAt', $data)) {
            $timeBooking->setEndedAt(new DateTimeImmutable((string)$data['endedAt']));
        }
        if (array_key_exists('ticketNumber', $data)) {
            $ticket = trim((string)$data['ticketNumber']);
            if ($ticket === '') { throw new \InvalidArgumentException('ticketNumber must not be empty'); }
            $timeBooking->setTicketNumber($ticket);
        }
        if (array_key_exists('durationMinutes', $data)) {
            $minutes = (int)$data['durationMinutes'];
            if ($minutes <= 0) { throw new \InvalidArgumentException('durationMinutes must be > 0'); }
            $timeBooking->setDurationMinutes($minutes);
        }
        $this->em->flush();
        return $this->toArray($timeBooking);
    }

    /** Delete a time booking. */
    public function delete(int $id): bool
    {
        $timeBooking = $this->timeBookings->find($id);
        if (!$timeBooking) return false;
        $this->timeBookings->remove($timeBooking, true);
        return true;
    }

    /**
     * Map a TimeBooking entity to array for JSON output.
     *
     * @return array<string,mixed>
     */
    private function toArray(TimeBooking $timeBooking): array
    {
        return [
            'id' => $timeBooking->getId() ?? 0,
            'projectId' => $timeBooking->getProject()?->getId() ?? 0,
            'activityId' => $timeBooking->getActivity()?->getId(),
            'startedAt' => $timeBooking->getStartedAt()->format(DATE_ATOM),
            'endedAt' => $timeBooking->getEndedAt()->format(DATE_ATOM),
            'ticketNumber' => $timeBooking->getTicketNumber(),
            'durationMinutes' => $timeBooking->getDurationMinutes(),
        ];
    }
}
