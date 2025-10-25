<?php

namespace App\Service;

use App\Entity\TimeBooking;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeBookingRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

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
        private readonly ?Security $security = null,
    ) {}

    /**
     * Return all time bookings sorted by ID ascending and scoped to current user if available.
     *
     * @return array<int, array<string,mixed>>
     */
    public function list(): array
    {
        $criteria = [];
        // Sort by start time ("von") descending: newest first; add id DESC for stable ordering
        $orderBy = ['startedAt' => 'DESC', 'id' => 'DESC'];
        $user = $this->security?->getUser();
        if ($user instanceof User) {
            $criteria['user'] = $user;
        }
        $items = $this->timeBookings->findBy($criteria, $orderBy);
        return array_map(fn(TimeBooking $timeBooking) => $this->toArray($timeBooking), $items);
    }

    /** Get a time booking by ID, mapped for JSON (scoped to current user if available). */
    public function get(int $id): ?array
    {
        $user = $this->security?->getUser();
        if ($user !== null && !$user instanceof User) {
            throw new \InvalidArgumentException('Benutzerkontext ungültig: Bitte melden Sie sich erneut an.');
        }
        $timeBooking = $user instanceof User
            ? $this->timeBookings->findOneBy(['id' => $id, 'user' => $user])
            : $this->timeBookings->find($id);
        if ($timeBooking) {
            return $this->toArray($timeBooking);
        }

        return null;
    }

    /**
     * Create a time booking from request data. Current authenticated user is assigned when available.
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
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Ungültiges Datumsformat für "startedAt". Erwartet ISO 8601, z. B. 2025-10-25T12:00:00Z oder 2025-10-25T12:00:00+02:00.');
        }
        try {
            $ended = new DateTimeImmutable($endedAtStr);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('Ungültiges Datumsformat für "endedAt". Erwartet ISO 8601, z. B. 2025-10-25T12:15:00Z oder 2025-10-25T12:15:00+02:00.');
        }
        if ($ended <= $started) { throw new \InvalidArgumentException('endedAt must be after startedAt'); }
        $minutes = (int)($data['durationMinutes'] ?? 0);
        if ($minutes <= 0) {
            $minutes = (int) round(($ended->getTimestamp() - $started->getTimestamp()) / 60);
            if ($minutes <= 0) { throw new \InvalidArgumentException('durationMinutes must be > 0'); }
        }

        // Prevent overlapping bookings for same user and project
        $user = $this->security?->getUser();
        if ($user !== null && !$user instanceof User) {
            throw new \InvalidArgumentException('Benutzerkontext ungültig: Bitte melden Sie sich erneut an.');
        }
        if ($user instanceof User) {
            if ($this->timeBookings->existsOverlap($user, $project, $started, $ended, null)) {
                throw new \InvalidArgumentException('Zeitüberschneidung: Der Zeitraum überlappt mit einer bestehenden Buchung (gleiches Projekt, gleicher Benutzer).');
            }
        }

        $timeBooking = (new TimeBooking())
            ->setProject($project)
            ->setActivity($activity)
            ->setStartedAt($started)
            ->setEndedAt($ended)
            ->setTicketNumber($ticketNumber)
            ->setDurationMinutes($minutes);
        if ($user instanceof User) {
            $timeBooking->setUser($user);
        }
        $this->timeBookings->save($timeBooking, true);
        return $this->toArray($timeBooking);
    }

    /**
     * Update a time booking with partial data (scoped to current user when available).
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $user = $this->security?->getUser();
        if ($user !== null && !$user instanceof User) {
            throw new \InvalidArgumentException('Benutzerkontext ungültig: Bitte melden Sie sich erneut an.');
        }
        $timeBooking = $user instanceof User
            ? $this->timeBookings->findOneBy(['id' => $id, 'user' => $user])
            : $this->timeBookings->find($id);
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
        // Parse and apply provided datetimes with validation
        if (array_key_exists('startedAt', $data)) {
            try {
                $timeBooking->setStartedAt(new DateTimeImmutable((string)$data['startedAt']));
            } catch (\Throwable $exception) {
                throw new \InvalidArgumentException('Ungültiges Datumsformat für "startedAt". Erwartet ISO 8601, z. B. 2025-10-25T12:00:00Z oder 2025-10-25T12:00:00+02:00.');
            }
        }
        if (array_key_exists('endedAt', $data)) {
            try {
                $timeBooking->setEndedAt(new DateTimeImmutable((string)$data['endedAt']));
            } catch (\Throwable $exception) {
                throw new \InvalidArgumentException('Ungültiges Datumsformat für "endedAt". Erwartet ISO 8601, z. B. 2025-10-25T12:15:00Z oder 2025-10-25T12:15:00+02:00.');
            }
        }
        // Validate time order: endedAt must be strictly after startedAt
        if ($timeBooking->getEndedAt() <= $timeBooking->getStartedAt()) {
            throw new \InvalidArgumentException('endedAt must be after startedAt');
        }
        // Prevent overlapping with other bookings of same user & project
        if ($user) {
            $projectForCheck = $timeBooking->getProject();
            $startedForCheck = $timeBooking->getStartedAt();
            $endedForCheck = $timeBooking->getEndedAt();
            if ($projectForCheck && $this->timeBookings->existsOverlap($user, $projectForCheck, $startedForCheck, $endedForCheck, $timeBooking->getId())) {
                throw new \InvalidArgumentException('Zeitüberschneidung: Der Zeitraum überlappt mit einer bestehenden Buchung (gleiches Projekt, gleicher Benutzer).');
            }
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

    /** Delete a time booking (scoped to current user when available). */
    public function delete(int $id): bool
    {
        $user = $this->security?->getUser();
        if ($user !== null && !$user instanceof User) {
            throw new \InvalidArgumentException('Benutzerkontext ungültig: Bitte melden Sie sich erneut an.');
        }
        $timeBooking = $user instanceof User
            ? $this->timeBookings->findOneBy(['id' => $id, 'user' => $user])
            : $this->timeBookings->find($id);
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
