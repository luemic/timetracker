<?php

namespace App\Service;

use App\Entity\TimeBooking;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeBookingRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class TimeBookingService
{
    public function __construct(
        private readonly TimeBookingRepository $timeBookings,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array<int, array<string,mixed>> */
    public function list(): array
    {
        $items = $this->timeBookings->findBy([], ['id' => 'ASC']);
        return array_map(fn(TimeBooking $tb) => $this->toArray($tb), $items);
    }

    public function get(int $id): ?array
    {
        $tb = $this->timeBookings->find($id);
        return $tb ? $this->toArray($tb) : null;
    }

    /**
     * @param array{
     *  projectId?:int,
     *  activityId?:int|null,
     *  startedAt?:string,
     *  endedAt?:string,
     *  ticketNumber?:string,
     *  durationMinutes?:int|null
     * } $data
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
        $tb = (new TimeBooking())
            ->setProject($project)
            ->setActivity($activity)
            ->setStartedAt($started)
            ->setEndedAt($ended)
            ->setTicketNumber($ticketNumber)
            ->setDurationMinutes($minutes);
        $this->timeBookings->save($tb, true);
        return $this->toArray($tb);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): ?array
    {
        $tb = $this->timeBookings->find($id);
        if (!$tb) return null;
        if (array_key_exists('projectId', $data)) {
            $pid = $data['projectId'];
            if (!is_numeric($pid)) { throw new \InvalidArgumentException('projectId must be numeric'); }
            $project = $this->projects->find((int)$pid);
            if (!$project) { throw new \RuntimeException('Project not found'); }
            $tb->setProject($project);
        }
        if (array_key_exists('activityId', $data)) {
            $aid = $data['activityId'];
            if ($aid === null) {
                $tb->setActivity(null);
            } else {
                if (!is_numeric($aid)) { throw new \InvalidArgumentException('activityId must be numeric or null'); }
                $activity = $this->activities->find((int)$aid);
                if (!$activity) { throw new \RuntimeException('Activity not found'); }
                $tb->setActivity($activity);
            }
        }
        if (array_key_exists('startedAt', $data)) {
            $tb->setStartedAt(new DateTimeImmutable((string)$data['startedAt']));
        }
        if (array_key_exists('endedAt', $data)) {
            $tb->setEndedAt(new DateTimeImmutable((string)$data['endedAt']));
        }
        if (array_key_exists('ticketNumber', $data)) {
            $ticket = trim((string)$data['ticketNumber']);
            if ($ticket === '') { throw new \InvalidArgumentException('ticketNumber must not be empty'); }
            $tb->setTicketNumber($ticket);
        }
        if (array_key_exists('durationMinutes', $data)) {
            $minutes = (int)$data['durationMinutes'];
            if ($minutes <= 0) { throw new \InvalidArgumentException('durationMinutes must be > 0'); }
            $tb->setDurationMinutes($minutes);
        }
        $this->em->flush();
        return $this->toArray($tb);
    }

    public function delete(int $id): bool
    {
        $tb = $this->timeBookings->find($id);
        if (!$tb) return false;
        $this->timeBookings->remove($tb, true);
        return true;
    }

    private function toArray(TimeBooking $tb): array
    {
        return [
            'id' => $tb->getId() ?? 0,
            'projectId' => $tb->getProject()?->getId() ?? 0,
            'activityId' => $tb->getActivity()?->getId(),
            'startedAt' => $tb->getStartedAt()->format(DATE_ATOM),
            'endedAt' => $tb->getEndedAt()->format(DATE_ATOM),
            'ticketNumber' => $tb->getTicketNumber(),
            'durationMinutes' => $tb->getDurationMinutes(),
        ];
    }
}
