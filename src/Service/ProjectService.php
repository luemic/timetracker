<?php

namespace App\Service;

use App\Entity\Project;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for managing projects.
 */
class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly CustomerRepository $customers,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Return all projects sorted by ID ascending.
     *
     * @return array<int, array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string}>
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
     *   externalTicketCredentials?:?string
     * } $data
     * @return array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string}
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
        $this->projects->save($project, true);
        return $this->toArray($project);
    }

    /**
     * Update a project with partial data.
     *
     * @param array<string,mixed> $data
     * @return array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string}|null
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
     * @return array{id:int,name:string,customerId:int,externalTicketUrl:?string,externalTicketLogin:?string,externalTicketCredentials:?string}
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
        ];
    }
}
