<?php

namespace App\TicketSystem;

use App\Entity\Project;
use App\Entity\TicketSystem as TicketSystemEntity;

/**
 * Factory to build a concrete TicketSystemClientInterface from a Project or TicketSystem entity.
 */
class TicketSystemClientFactory
{
    public function forProject(Project $project): ?TicketSystemClientInterface
    {
        $ts = $project->getTicketSystem();
        return $ts ? $this->forTicketSystem($ts) : null;
    }

    public function forTicketSystem(TicketSystemEntity $ts): ?TicketSystemClientInterface
    {
        $type = strtolower($ts->getType());
        return match ($type) {
            'jira' => $this->buildJiraClientOrNull($ts),
            default => null,
        };
    }

    private function buildJiraClientOrNull(TicketSystemEntity $ts): ?TicketSystemClientInterface
    {
        // If the Jira library isn't available at runtime, skip external integration gracefully
        if (!\class_exists(\JiraRestApi\Issue\Worklog::class)
            || !\class_exists(\JiraRestApi\Configuration\ArrayConfiguration::class)) {
            return null;
        }
        // Library present → create concrete client
        return new JiraTicketSystemClient($ts);
    }
}
