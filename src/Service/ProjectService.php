<?php

namespace App\Service;

use App\Entity\Project;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\TicketSystemRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for managing projects.
 */
class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly CustomerRepository $customers,
        private readonly TicketSystemRepository $ticketSystems,
        private readonly EntityManagerInterface $em,
        private readonly ?\App\Repository\TimeBookingRepository $timeBookings = null,
    ) {}

    /**
     * Return all projects sorted by ID ascending.
     *
     * @return array<int, array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string,ticketSystemId:?int}>
     */
    public function list(): array
    {
        $items = $this->projects->findBy([], ['id' => 'ASC']);
        return array_map(fn(Project $project) => $this->toArray($project), $items);
    }

    /**
     * Get a project by ID, mapped for JSON.
     */
    public function get(int $id): ?array
    {
        $project = $this->projects->find($id);
        return $project ? $this->toArray($project) : null;
    }

    /**
     * Create a project from request data.
     *
     * @param array{
     *   name?:string,
     *   customerId?:int,
     *   externalTicketUrl?:?string,
     *   externalTicketLogin?:?string,
     *   externalTicketCredentials?:?string,
     *   ticketSystemId?:?int,
     *   budgetType?:?string,
     *   budget?:?string|?float|?int,
     *   hourlyRate?:?string|?float|?int
     * } $data
     * @return array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string,ticketSystemId:?int,budgetType:string,budget:?string,hourlyRate:?string}
     */
    public function create(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $customerId = $data['customerId'] ?? null;
        if ($name === '' || !is_numeric($customerId)) {
            throw new \InvalidArgumentException('Fields "name" and numeric "customerId" are required');
        }
        $customer = $this->customers->find((int)$customerId);
        if (!$customer) {
            throw new \RuntimeException('Customer not found');
        }
        $project = (new Project())
            ->setName($name)
            ->setCustomer($customer)
            ->setExternalTicketUrl($data['externalTicketUrl'] ?? null)
            ->setExternalTicketLogin($data['externalTicketLogin'] ?? null)
            ->setExternalTicketCredentials($data['externalTicketCredentials'] ?? null);
        // Optional TicketSystem relation
        if (array_key_exists('ticketSystemId', $data) && $data['ticketSystemId'] !== null && $data['ticketSystemId'] !== '') {
            $tsId = (int)$data['ticketSystemId'];
            if ($tsId > 0) {
                $ts = $this->ticketSystems->find($tsId);
                if (!$ts) { throw new \RuntimeException('Ticket system not found'); }
                $project->setTicketSystem($ts);
            }
        }
        // Budget fields
        $this->applyBudgetFields($project, $data);

        $this->projects->save($project, true);
        return $this->toArray($project);
    }

    /**
     * Update a project with partial data.
     *
     * @param array<string,mixed> $data
     * @return array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string,ticketSystemId:?int,budgetType:string,budget:?string,hourlyRate:?string}|null
     */
    public function update(int $id, array $data): ?array
    {
        $project = $this->projects->find($id);
        if (!$project) {
            return null;
        }
        if (array_key_exists('name', $data)) {
            $name = trim((string)($data['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('Field "name" must not be empty');
            }
            $project->setName($name);
        }
        if (array_key_exists('customerId', $data)) {
            $customerId = $data['customerId'];
            if (!is_numeric($customerId)) {
                throw new \InvalidArgumentException('Field "customerId" must be numeric');
            }
            $customer = $this->customers->find((int)$customerId);
            if (!$customer) {
                throw new \RuntimeException('Customer not found');
            }
            $project->setCustomer($customer);
        }
        if (array_key_exists('externalTicketUrl', $data)) {
            $project->setExternalTicketUrl($data['externalTicketUrl']);
        }
        if (array_key_exists('externalTicketLogin', $data)) {
            $project->setExternalTicketLogin($data['externalTicketLogin']);
        }
        if (array_key_exists('externalTicketCredentials', $data)) {
            $project->setExternalTicketCredentials($data['externalTicketCredentials']);
        }
        if (array_key_exists('ticketSystemId', $data)) {
            $tsId = $data['ticketSystemId'];
            if ($tsId === null || $tsId === '' || (int)$tsId <= 0) {
                $project->setTicketSystem(null);
            } else {
                $ts = $this->ticketSystems->find((int)$tsId);
                if (!$ts) { throw new \RuntimeException('Ticket system not found'); }
                $project->setTicketSystem($ts);
            }
        }
        // Budget fields
        $this->applyBudgetFields($project, $data);
        $this->em->flush();

        return $this->toArray($project);
    }

    /**
     * Delete a project.
     */
    public function delete(int $id): bool
    {
        $project = $this->projects->find($id);
        if (!$project) return false;
        $this->projects->remove($project, true);
        return true;
    }

    /**
     * Map a Project entity to array for JSON output.
     *
     * @return array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string,ticketSystemId:?int,budgetType:string,budget:?string,hourlyRate:?string}
     */
    private function toArray(Project $project): array
    {
        return [
            'id' => $project->getId() ?? 0,
            'name' => $project->getName(),
            'customerId' => $project->getCustomer()?->getId() ?? 0,
            'externalTicketUrl' => $project->getExternalTicketUrl(),
            'externalTicketLogin' => $project->getExternalTicketLogin(),
            'externalTicketCredentials' => $project->getExternalTicketCredentials(),
            'ticketSystemId' => $project->getTicketSystem()?->getId(),
            'budgetType' => $project->getBudgetType(),
            'budget' => $project->getBudget(),
            'hourlyRate' => $project->getHourlyRate(),
        ];
    }

    /**
     * Apply budget related fields and enforce business rules.
     *
     * Rules:
     * - budgetType none: budget=null, hourlyRate=null
     * - budgetType fixed_price: hourlyRate is derived from budget / bookedHours, ignore provided hourlyRate
     * - budgetType tm: budget=null, hourlyRate must be provided
     *
     * @param array<string,mixed> $data
     */
    private function applyBudgetFields(Project $project, array $data): void
    {
        if (array_key_exists('budgetType', $data) && is_string($data['budgetType'])) {
            $project->setBudgetType($data['budgetType']);
        }
        $type = $project->getBudgetType();
        if ($type === 'none') {
            $project->setBudget(null);
            $project->setHourlyRate(null);
            return;
        }
        if ($type === 'tm') {
            // budget not applicable
            $project->setBudget(null);
            $rate = null;
            if (array_key_exists('hourlyRate', $data)) {
                $rateVal = $data['hourlyRate'];
                if ($rateVal === null || $rateVal === '') {
                    throw new \InvalidArgumentException('hourlyRate required for Time & Material');
                }
                if (!is_numeric($rateVal)) {
                    throw new \InvalidArgumentException('hourlyRate must be numeric');
                }
                $rate = number_format((float)$rateVal, 2, '.', '');
            }
            if ($rate === null && $project->getHourlyRate() === null) {
                throw new \InvalidArgumentException('hourlyRate required for Time & Material');
            }
            if ($rate !== null) {
                $project->setHourlyRate($rate);
            }
            return;
        }
        if ($type === 'fixed_price') {
            // Budget required
            $budgetStr = null;
            if (array_key_exists('budget', $data)) {
                $val = $data['budget'];
                if ($val === null || $val === '') {
                    throw new \InvalidArgumentException('budget required for fixed price');
                }
                if (!is_numeric($val)) { throw new \InvalidArgumentException('budget must be numeric'); }
                $budgetStr = number_format((float)$val, 2, '.', '');
                $project->setBudget($budgetStr);
            }
            if ($project->getBudget() === null) {
                throw new \InvalidArgumentException('budget required for fixed price');
            }
            // Derive hourly rate from current booked hours when available
            $minutes = $this->timeBookings?->sumMinutesByProject($project) ?? 0;
            $hours = $minutes > 0 ? ($minutes / 60.0) : null;
            if ($hours && $hours > 0) {
                $rate = (float)$project->getBudget() / $hours;
                $project->setHourlyRate(number_format($rate, 2, '.', ''));
            } else {
                // No bookings yet → hourlyRate not defined (or keep as null)
                $project->setHourlyRate(null);
            }
            return;
        }
        // Fallback
        $project->setBudgetType('none');
        $project->setBudget(null);
        $project->setHourlyRate(null);
    }
}
