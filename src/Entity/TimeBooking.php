<?php

namespace App\Entity;

use App\Repository\TimeBookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimeBookingRepository::class)]
class TimeBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'timeBookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'timeBookings')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endedAt;

    #[ORM\Column(type: 'string', length: 255)]
    private string $ticketNumber = '';

    #[ORM\Column(type: 'integer')]
    private int $durationMinutes = 0;

    public function getId(): ?int { return $this->id; }

    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $project): self { $this->project = $project; return $this; }

    public function getActivity(): ?Activity { return $this->activity; }
    public function setActivity(?Activity $activity): self { $this->activity = $activity; return $this; }

    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function setStartedAt(\DateTimeImmutable $startedAt): self { $this->startedAt = $startedAt; return $this; }

    public function getEndedAt(): \DateTimeImmutable { return $this->endedAt; }
    public function setEndedAt(\DateTimeImmutable $endedAt): self { $this->endedAt = $endedAt; return $this; }

    public function getTicketNumber(): string { return $this->ticketNumber; }
    public function setTicketNumber(string $ticketNumber): self { $this->ticketNumber = $ticketNumber; return $this; }

    public function getDurationMinutes(): int { return $this->durationMinutes; }
    public function setDurationMinutes(int $minutes): self { $this->durationMinutes = $minutes; return $this; }
}
