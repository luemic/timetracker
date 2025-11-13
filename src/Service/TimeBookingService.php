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
        private readonly ?\App\TicketSystem\TicketSystemClientFactory $tsFactory = null,
    ) {}

    private function getClientForProject(\App\Entity\Project $project): ?\App\TicketSystem\TicketSystemClientInterface
    {
        return $this->tsFactory?->forProject($project);
    }

    private function recalcProjectRateIfFixedPrice(?\App\Entity\Project $project): void
    {
        if (!$project) { return; }
        if (!$project->isBudgetFixedPrice()) { return; }
        $budget = $project->getBudget();
        if ($budget === null || !is_numeric($budget)) { return; }
        $minutes = $this->timeBookings->sumMinutesByProject($project);
        if ($minutes > 0) {
            $hours = $minutes / 60.0;
            $rate = (float)$budget / $hours;
            $project->setHourlyRate(number_format($rate, 2, '.', ''));
        } else {
            $project->setHourlyRate(null);
        }
        // Flush in same transaction context if any
        try { $this->em->flush(); } catch (\Throwable) {}
    }

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
        // Duration is always derived from start/end on the server and rounded up to 15 minutes
        $diffSeconds = $ended->getTimestamp() - $started->getTimestamp();
        $minutes = (int) (ceil($diffSeconds / 900) * 15); // 900s = 15min
        if ($minutes <= 0) {
            throw new \InvalidArgumentException('endedAt must be after startedAt');
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

        // External ticket system integration: create worklog first, then DB persist in a transaction
        $client = $this->getClientForProject($project);
        if ($client) {
            // create external worklog
            $externalId = null;
            try {
                $externalId = $client->createWorklog($ticketNumber, $started, $minutes);
                $timeBooking->setWorklogId($externalId);
                // persist in DB transaction
                $this->em->wrapInTransaction(function() use ($timeBooking) {
                    $this->timeBookings->save($timeBooking, true);
                });
            } catch (\Throwable $e) {
                // compensation: if we have created an external worklog but DB failed afterwards, try to delete it
                if ($externalId) {
                    try { $client->deleteWorklog($ticketNumber, $externalId); } catch (\Throwable) {}
                }
                throw $e instanceof \InvalidArgumentException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
            }
        } else {
            // External ticket system configured but no usable client available → do not persist locally
            if ($project->getTicketSystem() !== null) {
                throw new \RuntimeException('Externes Ticket-System ist für dieses Projekt konfiguriert, aber der passende Client ist nicht verfügbar. Bitte installieren/konfigurieren Sie die Integration.');
            }
            // No ticket system linked → persist locally only
            $this->timeBookings->save($timeBooking, true);
        }

        // Recalculate derived hourly rate for fixed price projects
        $this->recalcProjectRateIfFixedPrice($project);

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

        // Derive new values (do not persist yet)
        $newProject = $timeBooking->getProject();
        if (array_key_exists('projectId', $data)) {
            $projectId = $data['projectId'];
            if (!is_numeric($projectId)) { throw new \InvalidArgumentException('projectId must be numeric'); }
            $p = $this->projects->find((int)$projectId);
            if (!$p) { throw new \RuntimeException('Project not found'); }
            $newProject = $p;
        }
        $newActivity = $timeBooking->getActivity();
        if (array_key_exists('activityId', $data)) {
            $activityId = $data['activityId'];
            if ($activityId === null) {
                $newActivity = null;
            } else {
                if (!is_numeric($activityId)) { throw new \InvalidArgumentException('activityId must be numeric or null'); }
                $activity = $this->activities->find((int)$activityId);
                if (!$activity) { throw new \RuntimeException('Activity not found'); }
                $newActivity = $activity;
            }
        }
        $newStarted = $timeBooking->getStartedAt();
        if (array_key_exists('startedAt', $data)) {
            try { $newStarted = new DateTimeImmutable((string)$data['startedAt']); }
            catch (\Throwable) { throw new \InvalidArgumentException('Ungültiges Datumsformat für "startedAt". Erwartet ISO 8601, z. B. 2025-10-25T12:00:00Z oder 2025-10-25T12:00:00+02:00.'); }
        }
        $newEnded = $timeBooking->getEndedAt();
        if (array_key_exists('endedAt', $data)) {
            try { $newEnded = new DateTimeImmutable((string)$data['endedAt']); }
            catch (\Throwable) { throw new \InvalidArgumentException('Ungültiges Datumsformat für "endedAt". Erwartet ISO 8601, z. B. 2025-10-25T12:15:00Z oder 2025-10-25T12:15:00+02:00.'); }
        }
        if ($newEnded <= $newStarted) { throw new \InvalidArgumentException('endedAt must be after startedAt'); }
        // Duration is always derived from start/end and rounded up to 15 minutes
        $diffSecondsUpd = $newEnded->getTimestamp() - $newStarted->getTimestamp();
        $newMinutes = (int) (ceil($diffSecondsUpd / 900) * 15);
        if ($newMinutes <= 0) {
            throw new \InvalidArgumentException('endedAt must be after startedAt');
        }
        $newTicket = $timeBooking->getTicketNumber();
        if (array_key_exists('ticketNumber', $data)) {
            $ticket = trim((string)$data['ticketNumber']);
            if ($ticket === '') { throw new \InvalidArgumentException('ticketNumber must not be empty'); }
            $newTicket = $ticket;
        }

        // Prevent overlapping with other bookings of same user & (new) project
        if ($user) {
            if ($newProject && $this->timeBookings->existsOverlap($user, $newProject, $newStarted, $newEnded, $timeBooking->getId())) {
                throw new \InvalidArgumentException('Zeitüberschneidung: Der Zeitraum überlappt mit einer bestehenden Buchung (gleiches Projekt, gleicher Benutzer).');
            }
        }

        // External first
        $createdExternalId = null;
        $oldProject = $timeBooking->getProject();
        $oldTicket = $timeBooking->getTicketNumber();
        $oldWid = $timeBooking->getWorklogId();
        $ticketOrProjectChanged = ($oldTicket !== $newTicket) || (($oldProject?->getId() ?? 0) !== ($newProject?->getId() ?? 0));

        $newClient = $newProject ? $this->getClientForProject($newProject) : null;
        if ($newProject && $newProject->getTicketSystem() !== null && !$newClient) {
            throw new \RuntimeException('Externes Ticket-System ist für dieses Projekt konfiguriert, aber der passende Client ist nicht verfügbar. Bitte installieren/konfigurieren Sie die Integration.');
        }

        // If the ticket key or project changed and there is an existing external worklog, we cannot move it – delete old then create new
        if ($ticketOrProjectChanged && $oldWid) {
            $oldClient = $oldProject ? $this->getClientForProject($oldProject) : null;
            if ($oldProject && $oldProject->getTicketSystem() !== null && !$oldClient) {
                throw new \RuntimeException('Externes Ticket-System ist für das ursprüngliche Projekt konfiguriert, aber der Client ist nicht verfügbar – Aktualisierung nicht möglich.');
            }
            if ($oldClient) {
                $deleted = false;
                try { $deleted = $oldClient->deleteWorklog($oldTicket, $oldWid); } catch (\Throwable $e) { $deleted = false; }
                if (!$deleted) {
                    throw new \RuntimeException('Externer Worklog konnte nicht vom ursprünglichen Ticket entfernt werden.');
                }
            }
            // After successful deletion, clear local wid so DB reflects new one after creation
            $timeBooking->setWorklogId(null);
        }

        if ($newClient) {
            try {
                $wid = $timeBooking->getWorklogId();
                if ($wid && !$ticketOrProjectChanged) {
                    // Same ticket/project → update existing external worklog
                    $newClient->updateWorklog($newTicket, $wid, $newStarted, $newMinutes);
                } else {
                    // No existing external worklog or target changed → create a new one on target issue
                    $createdExternalId = $newClient->createWorklog($newTicket, $newStarted, $newMinutes);
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException($e->getMessage(), 0, $e);
            }
        }

        // Now persist DB in a transaction
        try {
            $this->em->wrapInTransaction(function() use ($timeBooking, $newProject, $newActivity, $newStarted, $newEnded, $newTicket, $newMinutes, $createdExternalId) {
                $timeBooking->setProject($newProject);
                $timeBooking->setActivity($newActivity);
                $timeBooking->setStartedAt($newStarted);
                $timeBooking->setEndedAt($newEnded);
                $timeBooking->setTicketNumber($newTicket);
                $timeBooking->setDurationMinutes($newMinutes);
                if ($createdExternalId) {
                    $timeBooking->setWorklogId($createdExternalId);
                }
                $this->em->flush();
            });
            // After successful update, recalc rates for affected projects
            // Recalculate for both old and new project if they differ
            $this->recalcProjectRateIfFixedPrice($oldProject);
            if (($oldProject?->getId() ?? 0) !== ($newProject?->getId() ?? 0)) {
                $this->recalcProjectRateIfFixedPrice($newProject);
            }
        } catch (\Throwable $e) {
            // Compensation if we created a new external worklog for this update
            if ($newClient && $createdExternalId) {
                try { $newClient->deleteWorklog($newTicket, $createdExternalId); } catch (\Throwable) {}
            }
            throw $e instanceof \InvalidArgumentException ? $e : new \RuntimeException($e->getMessage(), 0, $e);
        }

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

        // If linked to an external ticket system, always delete externally first (even when worklogId is missing)
        $project = $timeBooking->getProject();
        $client = $project ? $this->getClientForProject($project) : null;
        $hasExternal = $project && $project->getTicketSystem() !== null;
        if ($hasExternal) {
            if (!$client) {
                throw new \RuntimeException('Externes Ticket-System ist für dieses Projekt konfiguriert, aber der passende Client ist nicht verfügbar. Bitte installieren/konfigurieren Sie die Integration.');
            }
            $ticket = $timeBooking->getTicketNumber();
            $wid = $timeBooking->getWorklogId();
            $ok = false;
            try {
                if ($wid) {
                    $ok = $client->deleteWorklog($ticket, (string)$wid);
                } else {
                    // Resolve by signature (startedAt + duration) if we don't have a stored external id
                    $ok = $client->deleteWorklogBySignature($ticket, $timeBooking->getStartedAt(), $timeBooking->getDurationMinutes());
                }
            } catch (\Throwable) {
                $ok = false;
            }
            if (!$ok) {
                throw new \RuntimeException('Externer Worklog konnte nicht gelöscht werden. Bitte versuchen Sie es später erneut.');
            }
        }

        // Delete locally (transactional)
        $this->em->wrapInTransaction(function() use ($timeBooking) {
            $this->timeBookings->remove($timeBooking, true);
        });
        // Recalc for the project the booking belonged to
        $this->recalcProjectRateIfFixedPrice($project);
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
            'worklogId' => $timeBooking->getWorklogId(),
        ];
    }
}
