<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    // External ticket system info (legacy fields, kept for backward compatibility)
    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    private ?string $externalTicketUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalTicketLogin = null;

    #[ORM\Column(type: 'string', length: 4096, nullable: true)]
    private ?string $externalTicketCredentials = null;

    // Optional 1:1 link to TicketSystem
    #[ORM\OneToOne(cascade: ['persist'], orphanRemoval: false)]
    #[ORM\JoinColumn(name: 'ticket_system_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TicketSystem $ticketSystem = null;

    /** @var Collection<int, ProjectActivity> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectActivity::class, cascade: ['persist', 'remove'])]
    private Collection $projectActivities;

    /** @var Collection<int, TimeBooking> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: TimeBooking::class, cascade: ['persist', 'remove'])]
    private Collection $timeBookings;

    // Budget handling
    // Type: none | fixed_price | tm (time & material)
    #[ORM\Column(type: 'string', length: 32, options: ['default' => 'none'])]
    private string $budgetType = 'none';

    // Total budget amount (e.g., EUR) for fixed price projects
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $budget = null; // store as string per Doctrine decimal best practice

    // Hourly rate (e.g., EUR/hour). For fixed price, derived from budget / booked hours
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $hourlyRate = null; // store as string per Doctrine decimal best practice

    public function __construct()
    {
        $this->projectActivities = new ArrayCollection();
        $this->timeBookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getExternalTicketUrl(): ?string
    {
        return $this->externalTicketUrl;
    }

    public function setExternalTicketUrl(?string $url): self
    {
        $this->externalTicketUrl = $url;

        return $this;
    }

    public function getExternalTicketLogin(): ?string
    {
        return $this->externalTicketLogin;
    }

    public function setExternalTicketLogin(?string $login): self
    {
        $this->externalTicketLogin = $login;

        return $this;
    }

    public function getExternalTicketCredentials(): ?string
    {
        return $this->externalTicketCredentials;
    }

    public function setExternalTicketCredentials(?string $credentials): self
    {
        $this->externalTicketCredentials = $credentials;

        return $this;
    }

    public function getTicketSystem(): ?TicketSystem
    {
        return $this->ticketSystem;
    }

    public function setTicketSystem(?TicketSystem $ts): self
    {
        $this->ticketSystem = $ts;
        return $this;
    }

    /** @return Collection<int, ProjectActivity> */
    public function getProjectActivities(): Collection
    {
        return $this->projectActivities;
    }

    public function addProjectActivity(ProjectActivity $pa): self
    {
        if (!$this->projectActivities->contains($pa)) {
            $this->projectActivities->add($pa);
            $pa->setProject($this);
        }

        return $this;
    }

    public function removeProjectActivity(ProjectActivity $pa): self
    {
        if ($this->projectActivities->removeElement($pa)) {
            if ($pa->getProject() === $this) {
                $pa->setProject(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, TimeBooking> */
    public function getTimeBookings(): Collection
    {
        return $this->timeBookings;
    }

    public function addTimeBooking(TimeBooking $tb): self
    {
        if (!$this->timeBookings->contains($tb)) {
            $this->timeBookings->add($tb);
            $tb->setProject($this);
        }

        return $this;
    }

    public function removeTimeBooking(TimeBooking $tb): self
    {
        if ($this->timeBookings->removeElement($tb)) {
            if ($tb->getProject() === $this) {
                $tb->setProject(null);
            }
        }

        return $this;
    }

    public function getBudgetType(): string
    {
        return $this->budgetType;
    }

    public function setBudgetType(string $budgetType): self
    {
        $budgetType = in_array($budgetType, ['none','fixed_price','tm'], true) ? $budgetType : 'none';
        $this->budgetType = $budgetType;
        return $this;
    }

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(?string $budget): self
    {
        $this->budget = $budget;
        return $this;
    }

    public function getHourlyRate(): ?string
    {
        return $this->hourlyRate;
    }

    public function setHourlyRate(?string $hourlyRate): self
    {
        $this->hourlyRate = $hourlyRate;
        return $this;
    }

    public function isBudgetNone(): bool { return $this->budgetType === 'none'; }
    public function isBudgetFixedPrice(): bool { return $this->budgetType === 'fixed_price'; }
    public function isBudgetTimeAndMaterial(): bool { return $this->budgetType === 'tm'; }
}
