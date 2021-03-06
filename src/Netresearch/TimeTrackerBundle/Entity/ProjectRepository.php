<?php

namespace Netresearch\TimeTrackerBundle\Entity;

use Netresearch\TimeTrackerBundle\Helper\TimeHelper;
use Doctrine\ORM\EntityRepository;

class ProjectRepository extends EntityRepository
{
    public function sortProjectsByName($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    }

    public function getGlobalProjects()
    {
        return $this->findBy(array('global' => 1));
    }



    /**
     * Returns an array structure with keys of customer IDs
     * The values are arrays of projects.
     *
     * There is a special key "all", where all projects are in.
     * @param int $userId
     * @param array $customers
     * @return array
     */
    public function getProjectStructure($userId, array $customers)
    {
        /* @var $globalProjects Project[] */
        $globalProjects = $this->getGlobalProjects();
        $userProjects   = $this->getProjectsByUser($userId, null);

        $projects = array();
        foreach ($customers as $customer) {

            // Restructure customer-specific projects
            foreach ($userProjects as $project) {
                if ($customer['customer']['id'] == $project['project']['customer']) {
                    $projects[$customer['customer']['id']][] = array(
                        'id' => $project['project']['id'],
                        'name' => $project['project']['name'],
                        'jiraId' => $project['project']['jiraId'],
                        'active' => $project['project']['active']
                    );
                }
            }

            // Add global projects to each customer
            foreach ($globalProjects as $global) {
                $projects[$customer['customer']['id']][] = array(
                    'id' => $global->getId(),
                    'name' => $global->getName(),
                    'jiraId' => $global->getJiraId(),
                    'active' => $global->getActive()
                );
            }
        }

        // Add each customer-specific project to the all-projects-list
        foreach ($userProjects as $project) {
            $projects['all'][] = $project['project'];
        }

        // Add each global project to the all-projects-list
        foreach ($globalProjects as $global) {
            $projects['all'][] = array(
                    'id' => $global->getId(),
                    'name' => $global->getName(),
//                    'customer_id' => 0,
                    'jiraId' => $global->getJiraId()
                );
        }

        // Sort projects by name for each customer
        foreach($projects AS &$customerProjects) {
            usort($customerProjects, array($this, 'sortProjectsByName'));
        }

        return $projects;
    }


    public function getProjectsByUser($userId, $customerId = null)
    {
        $connection = $this->getEntityManager()->getConnection();

        /* May god help us... */
        $sql = array();
        $sql['select'] = "SELECT DISTINCT p.*";
        $sql['from'] = "FROM projects p";
        $sql['join_c'] = "LEFT JOIN customers c ON p.customer_id = c.id";
        $sql['join_tc'] = "LEFT JOIN teams_customers tc ON tc.customer_id = c.id";
        $sql['join_tu'] = "LEFT JOIN teams_users tu ON tc.team_id = tu.team_id";
        $sql['where_user'] = "WHERE (c.global=1 OR tu.user_id = %d)";
        if ((int) $customerId > 0) {
            $sql['where_customer'] = "AND (p.customer_id = %d OR p.global = 1)";
        }
        $sql['order'] = "ORDER BY p.name ASC";

        $stmt = $connection->query(sprintf(implode(" ", $sql), $userId, $customerId));

        return $this->findByQuery($stmt);
    }

    public function findAll($customerId = 0)
    {
        $connection = $this->getEntityManager()->getConnection();

        $sql = array();
        $sql['select'] = "SELECT DISTINCT *";
        $sql['from'] = "FROM projects p";

        if ((int) $customerId > 0) {
            $sql['where'] = 'WHERE p.customer_id = ' . (int) $customerId
                            . ' OR p.global=1';
        }

        $sql['order'] = "ORDER BY p.name ASC";

        $stmt = $connection->query(implode(" ", $sql));

        return $this->findByQuery($stmt);
    }

    protected function findByQuery($stmt)
    {
        $projects = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $data = array();
        foreach ($projects as $project) {
            $data[] = array('project' => array(
                'id'            => $project['id'],
                'name'          => $project['name'],
                'jiraId'        => $project['jira_id'],
                'ticket_system' => $project['ticket_system'],
                'customer'      => $project['customer_id'],
                'active'        => $project['active'],
                'global'        => $project['global'],
                'estimation'    => $project['estimation'],
                'estimationText'=> TimeHelper::minutes2readable($project['estimation'], false),
                'billing'       => $project['billing'],
                'cost_center'   => $project['cost_center'],
                'offer'         => $project['offer'],
                'project_lead'  => $project['project_lead_id'],
                'technical_lead'=> $project['technical_lead_id'],
                'additionalInformationFromExternal' => $project['additional_information_from_external'],
            ));
        }

        return $data;
    }

    public function isValidJiraPrefix($jiraId)
    {
        return preg_match('/^([A-Z]+[A-Z0-9]*[, ]*)*$/', $jiraId);
    }
}
