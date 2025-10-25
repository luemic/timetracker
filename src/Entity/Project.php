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

    // External ticket system info
    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    private ?string $externalTicketUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $externalTicketLogin = null;

    #[ORM\Column(type: 'string', length: 4096, nullable: true)]
    private ?string $externalTicketCredentials = null;

    /** @var Collection<int, ProjectActivity> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: ProjectActivity::class, cascade: ['persist', 'remove'])]
    private Collection $projectActivities;

    /** @var Collection<int, TimeBooking> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: TimeBooking::class, cascade: ['persist', 'remove'])]
    private Collection $timeBookings;

    public function __construct()
    {
        $this->projectActivities = new ArrayCollection();
        $this->timeBookings = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): self { $this->customer = $customer; return $this; }

    public function getExternalTicketUrl(): ?string { return $this->externalTicketUrl; }
    public function setExternalTicketUrl(?string $url): self { $this->externalTicketUrl = $url; return $this; }

    public function getExternalTicketLogin(): ?string { return $this->externalTicketLogin; }
    public function setExternalTicketLogin(?string $login): self { $this->externalTicketLogin = $login; return $this; }

    public function getExternalTicketCredentials(): ?string { return $this->externalTicketCredentials; }
    public function setExternalTicketCredentials(?string $credentials): self { $this->externalTicketCredentials = $credentials; return $this; }

    /** @return Collection<int, ProjectActivity> */
    public function getProjectActivities(): Collection { return $this->projectActivities; }

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
    public function getTimeBookings(): Collection { return $this->timeBookings; }

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
            if ($tb->getProject() === $this) { $tb->setProject(null); }
        }
        return $this;
    }
}
