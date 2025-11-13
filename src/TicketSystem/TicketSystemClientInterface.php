<?php

namespace App\TicketSystem;

use DateTimeImmutable;

/**
 * Abstraction for external ticket/worklog operations.
 */
interface TicketSystemClientInterface
{
    /**
     * Create a worklog on the external system and return its external identifier.
     *
     * @param string $issueKey External ticket identifier (e.g., Jira issue key like PROJ-123)
     * @param DateTimeImmutable $startedAt When the work started
     * @param int $minutes Duration in minutes (>0)
     * @param string $comment Optional comment/description
     * @return string External worklog id
     */
    public function createWorklog(string $issueKey, DateTimeImmutable $startedAt, int $minutes, string $comment = ''): string;

    /**
     * Update an existing worklog in the external system.
     *
     * @param string $issueKey Ticket identifier
     * @param string $worklogId External worklog id
     * @param DateTimeImmutable $startedAt When the work started
     * @param int $minutes Duration in minutes (>0)
     * @param string $comment Optional comment
     */
    public function updateWorklog(string $issueKey, string $worklogId, DateTimeImmutable $startedAt, int $minutes, string $comment = ''): void;

    /**
     * Delete a worklog from the external system.
     *
     * @param string $issueKey Ticket identifier
     * @param string $worklogId External worklog id
     * @return bool True if the worklog was deleted (or did not exist), false otherwise
     */
    public function deleteWorklog(string $issueKey, string $worklogId): bool;

    /**
     * Delete a worklog by matching its signature when the external ID is unknown.
     * Implementations should locate a worklog on the given ticket that matches the
     * provided start timestamp and duration, and delete it if found.
     *
     * @return bool True if deleted or not found (treated as already deleted), false on failure
     */
    public function deleteWorklogBySignature(string $issueKey, DateTimeImmutable $startedAt, int $minutes): bool;
}
