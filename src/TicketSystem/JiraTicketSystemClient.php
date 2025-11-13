<?php

namespace App\TicketSystem;

use App\Entity\TicketSystem as TicketSystemEntity;
use DateTimeImmutable;
use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\Worklog as JiraWorklog;
use JiraRestApi\Issue\IssueService as JiraWorklogService;

class JiraTicketSystemClient implements TicketSystemClientInterface
{
    /**
     * Worklog service instance from lesstif/php-jira-rest-client.
     * @var JiraWorklogService
     */
    private $worklogService;

    public function __construct(private readonly TicketSystemEntity $configEntity)
    {
        // Fail fast with helpful message if Jira client library is missing
        if (!\class_exists(\JiraRestApi\Issue\IssueService::class) || !\class_exists(\JiraRestApi\Issue\Worklog::class) || !\class_exists(\JiraRestApi\Configuration\ArrayConfiguration::class)) {
            throw new \RuntimeException('Jira-Client Bibliothek nicht gefunden. Bitte stellen Sie sicher, dass lesstif/php-jira-rest-client korrekt installiert ist (composer install) und der Autoloader geladen wird.');
        }

        $host = trim((string)$configEntity->getUrl());
        $user = trim($configEntity->getUsername());
        $token = (string)$configEntity->getSecret();
        if ($host === '') {
            throw new \RuntimeException('Jira base URL (TicketSystem.url) ist nicht konfiguriert.');
        }
        if ($user === '' || $token === '') {
            throw new \RuntimeException('Jira Zugangsdaten (username/secret) sind nicht konfiguriert.');
        }
        $cfg = new ArrayConfiguration([
            'jiraHost' => $host,
            'jiraUser' => $user,
            'jiraPassword' => $token, // Jira Cloud: API Token
            'useV3RestApi' => true,
            'logEnabled' => false,
        ]);
        // Defer creation to runtime; convert any failure into a RuntimeException for controller logging
        try {
            $this->worklogService = new JiraWorklogService($cfg);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Jira WorklogService konnte nicht initialisiert werden: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createWorklog(string $issueKey, DateTimeImmutable $startedAt, int $minutes, string $comment = ''): string
    {
        try {
            $wl = new JiraWorklog();
            if ($comment !== '') {
                $wl->setComment($comment);
            }
            // Jira expects start as date string
            $wl->setStarted(\DateTime::createFromImmutable($startedAt));
            $wl->setTimeSpentSeconds(max(60, $minutes * 60));
            $created = $this->worklogService->addWorklog($issueKey, $wl);
            $id = (string)($created->id ?? '');
            if ($id === '') {
                throw new \RuntimeException('Jira hat keine Worklog-ID zurückgegeben.');
            }
            return $id;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Jira Worklog konnte nicht erstellt werden: ' . $e->getMessage(), 0, $e);
        }
    }

    public function updateWorklog(string $issueKey, string $worklogId, DateTimeImmutable $startedAt, int $minutes, string $comment = ''): void
    {
        try {
            $wl = new JiraWorklog();
            if ($comment !== '') {
                $wl->setComment($comment);
            }
            $wl->setStarted(\DateTime::createFromImmutable($startedAt));
            $wl->setTimeSpentSeconds(max(60, $minutes * 60));
            $this->worklogService->editWorklog($issueKey, $wl, $worklogId);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Jira Worklog konnte nicht aktualisiert werden: ' . $e->getMessage(), 0, $e);
        }
    }

    public function deleteWorklog(string $issueKey, string $worklogId): bool
    {
        try {
            $this->worklogService->deleteWorklog($issueKey, $worklogId);
            return true;
        } catch (\Throwable $e) {
            // consider 404 as already deleted – library throws generic exception; we accept only success
            return false;
        }
    }

    public function deleteWorklogBySignature(string $issueKey, DateTimeImmutable $startedAt, int $minutes): bool
    {
        try {
            $paginated = $this->worklogService->getWorklog($issueKey, []);
            // lesstif PaginatedWorklog exposes ->worklogs (array of Worklog)
            $worklogs = [];
            if (is_object($paginated)) {
                if (property_exists($paginated, 'worklogs') && is_array($paginated->worklogs)) {
                    $worklogs = $paginated->worklogs;
                } elseif (method_exists($paginated, 'getWorklogs')) {
                    $worklogs = $paginated->getWorklogs();
                }
            }
            $targetSeconds = max(60, $minutes * 60);
            $targetStart = $startedAt->getTimestamp();
            foreach ($worklogs as $wl) {
                $wlStart = null;
                if (is_object($wl)) {
                    $started = $wl->started ?? null;
                    if ($started instanceof \DateTimeInterface) {
                        $wlStart = $started->getTimestamp();
                    } elseif (is_string($started)) {
                        $ts = strtotime($started);
                        if ($ts !== false) { $wlStart = $ts; }
                    }
                    $sec = (int)($wl->timeSpentSeconds ?? 0);
                    if ($wlStart !== null && $wlStart === $targetStart && $sec === $targetSeconds) {
                        $id = (string)($wl->id ?? '');
                        if ($id !== '') {
                            return $this->deleteWorklog($issueKey, $id);
                        }
                    }
                }
            }
            // Not found → treat as already deleted
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
