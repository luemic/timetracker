<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    /** @var Collection<int, ProjectActivity> */
    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: ProjectActivity::class, cascade: ['persist', 'remove'])]
    private Collection $projectActivities;

    /** @var Collection<int, TimeBooking> */
    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: TimeBooking::class)]
    private Collection $timeBookings;

    public function __construct()
    {
        $this->projectActivities = new ArrayCollection();
        $this->timeBookings = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return Collection<int, ProjectActivity> */
    public function getProjectActivities(): Collection { return $this->projectActivities; }

    /** @return Collection<int, TimeBooking> */
    public function getTimeBookings(): Collection { return $this->timeBookings; }
}
