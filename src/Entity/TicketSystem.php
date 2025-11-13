<?php

namespace App\Entity;

use App\Repository\TicketSystemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketSystemRepository::class)]
class TicketSystem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // e.g. 'jira' (extendable later)
    #[ORM\Column(type: 'string', length: 32)]
    private string $type = 'jira';

    // Display name of this ticket system configuration
    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $username = '';

    // API token or password; stored as-is for now
    #[ORM\Column(type: 'string', length: 4096)]
    private string $secret = '';

    // Base URL of the ticket system (e.g., Jira site URL)
    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    private ?string $url = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = strtolower($type);
        return $this;
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

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }
}
