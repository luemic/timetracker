<?php

namespace Netresearch\TimeTrackerBundle\Controller;

use Netresearch\TimeTrackerBundle\Entity\Project;
use Netresearch\TimeTrackerBundle\Entity\Entry as Entry;
use Netresearch\TimeTrackerBundle\Response\Error;
use Netresearch\TimeTrackerBundle\Helper\JiraApiException;
use Netresearch\TimeTrackerBundle\Helper\JiraOAuthApi;
use Netresearch\TimeTrackerBundle\Helper\TicketHelper;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class CrudController extends BaseController
{
    const LOG_FILE = 'trackingsave.log';

    public function deleteAction(Request $request)
    {
        if (!$this->checkLogin($request)) {
            return $this->getFailedLoginResponse();
        }

        $alert = null;

        if (0 != $request->request->get('id')) {
            $doctrine = $this->getDoctrine();
            $entry = $doctrine->getRepository('NetresearchTimeTrackerBundle:Entry')
                ->find($request->request->get('id'));

            try {
                $this->deleteJiraWorklog($entry);
            } catch (JiraApiException $e) {
                if ($e->getRedirectUrl()) {
                    // Invalid JIRA token
                    return new Error($e->getMessage(), 403, $e->getRedirectUrl());
                }
                $alert = $e->getMessage() . '<br />' .
                    $this->get('translator')->trans("Dataset was modified in Timetracker anyway");
            }

            // remember the day to calculate classes afterwards
            $day = $entry->getDay()->format("Y-m-d");

            $manager = $doctrine->getManager();
            $manager->remove($entry);
            $manager->flush();

            // We have to update classes after deletion as well
            $this->calculateClasses($this->_getUserId($request), $day);
        }

        return new Response(json_encode(array('success' => true, 'alert' => $alert)));
    }

    /**
     * Deletes a work log entry in a remote JIRA installation.
     * JIRA instance is defined by ticket system in project.
     *
     * @param \Netresearch\TimeTrackerBundle\Entity\Entry
     *
     * @return void
     */
    private function deleteJiraWorklog(\Netresearch\TimeTrackerBundle\Entity\Entry $entry)
    {
        $project = $entry->getProject();
        if (! $project instanceof \Netresearch\TimeTrackerBundle\Entity\Project) {
            return;
        }

        $ticketSystem = $project->getTicketSystem();
        if (! $ticketSystem instanceof \Netresearch\TimeTrackerBundle\Entity\TicketSystem) {
            return;
        }

        if (! $ticketSystem->getBookTime() || $ticketSystem->getType() != 'JIRA') {
            return;
        }

        $jiraOAuthApi = new JiraOAuthApi($entry->getUser(), $ticketSystem, $this->getDoctrine(), $this->container->get('router'));
        $jiraOAuthApi->deleteEntryJiraWorkLog($entry);
    }



    /**
     * Set rendering classes for pause, overlap and daybreak.
     *
     * @param integer $userId
     * @param string  $day
     * @return void
     */
    private function calculateClasses($userId, $day)
    {
        if (! (int) $userId) {
            return;
        }

        $doctrine = $this->getDoctrine();
        $manager = $doctrine->getManager();
        /* @var $entries \Netresearch\TimeTrackerBundle\Entity\Entry[] */
        $entries = $doctrine->getRepository('NetresearchTimeTrackerBundle:Entry')
            ->findByDay((int) $userId, $day);

        if (!count($entries)) {
            return;
        }

        if (! is_object($entries[0])) {
            return;
        }

        $entry = $entries[0];
        if ($entry->getClass() != Entry::CLASS_DAYBREAK) {
            $entry->setClass(Entry::CLASS_DAYBREAK);
            $manager->persist($entry);
            $manager->flush();
        }

        for ($c = 1; $c < count($entries); $c++) {
            $entry = $entries[$c];
            $previous = $entries[$c-1];

            if ($entry->getStart()->format("H:i") > $previous->getEnd()->format("H:i")) {
                if ($entry->getClass() != Entry::CLASS_PAUSE) {
                    $entry->setClass(Entry::CLASS_PAUSE);
                    $manager->persist($entry);
                    $manager->flush();
                }
                continue;
            }

            if ($entry->getStart()->format("H:i") < $previous->getEnd()->format("H:i")) {
                if ($entry->getClass() != Entry::CLASS_OVERLAP) {
                    $entry->setClass(Entry::CLASS_OVERLAP);
                    $manager->persist($entry);
                    $manager->flush();
                }
                continue;
            }

            if ($entry->getClass() != Entry::CLASS_PLAIN) {
                $entry->setClass(Entry::CLASS_PLAIN);
                $manager->persist($entry);
                $manager->flush();
            }
        }
    }



    /**
     * Save action handler.
     *
     * @param Request $request
     * @return Error|Response
     */
    public function saveAction(Request $request)
    {
        if (!$this->checkLogin($request)) {
            return $this->getFailedLoginResponse();
        }

        try {
            $alert = null;
            $this->logDataToFile($_POST, TRUE);

            $doctrine = $this->getDoctrine();

            if($request->get('id') != 0) {
                $entry = $doctrine->getRepository('NetresearchTimeTrackerBundle:Entry')
                    ->find($request->get('id'));
            } else {
                $entry = new Entry();
            }

            // We make a copy to determine if we have to update JIRA
            $oldEntry = clone $entry;

            if ($project = $doctrine->getRepository('NetresearchTimeTrackerBundle:Project')->find($request->get('project'))) {
                if (! $project->getActive()) {
                    $message = $this->get('translator')->trans("This project is inactive and cannot be used for booking.");
                    throw new \Exception($message);
                }
                $entry->setProject($project);
            }

            if ($customer = $doctrine->getRepository('NetresearchTimeTrackerBundle:Customer')->find($request->get('customer'))) {
                if (! $customer->getActive()) {
                    $message = $this->get('translator')->trans("This customer is inactive and cannot be used for booking.");
                    throw new \Exception($message);
                }
                $entry->setCustomer($customer);
            }

            /* @var $user \Netresearch\TimeTrackerBundle\Entity\User */
            $user = $doctrine->getRepository('NetresearchTimeTrackerBundle:User')
                ->find($this->_getUserId($request));
            $entry->setUser($user);

            if ($activity = $doctrine->getRepository('NetresearchTimeTrackerBundle:Activity')->find($request->get('activity'))) {
                $entry->setActivity($activity);
            }

            $entry->setTicket(strtoupper(trim($request->get('ticket') ? $request->get('ticket') : '')))
                ->setDescription($request->get('description') ? $request->get('description') : '')
                ->setDay($request->get('date') ? $request->get('date') : null)
                ->setStart($request->get('start') ? $request->get('start') : null)
                ->setEnd($request->get('end') ? $request->get('end') : null)
                // ->calcDuration(is_object($activity) ? $activity->getFactor() : 1);
                ->calcDuration()
                ->setSyncedToTicketsystem(FALSE);

            // write log
            $this->logDataToFile($entry->toArray());

            // Check if the activity needs a ticket
            if (($user->getType() == 'DEV') && is_object($activity) && $activity->getNeedsTicket()) {
                if (strlen($entry->getTicket()) < 1) {
                    $message = $this->get('translator')
                        ->trans(
                            "For the activity '%activity%' you must specify a ticket.",
                            array(
                                '%activity%' => $activity->getName(),
                            )
                        );
                    throw new \Exception($message);
                }
            }

            // check if ticket matches the project's ticket pattern
            $this->requireValidTicketFormat($entry->getTicket());

            // check if ticket matches the project's ticket pattern
            $this->requireValidTicketPrefix($entry->getProject(), $entry->getTicket());

            $em = $doctrine->getManager();
            $em->persist($entry);
            $em->flush();

            // we may have to update the classes of the entry's day
            if (is_object($entry->getDay())) {
                $this->calculateClasses(
                    $user->getId(), $entry->getDay()->format("Y-m-d")
                );
                // and the previous day, if the entry was moved
                if (is_object($oldEntry->getDay())) {
                    if ($entry->getDay()->format("Y-m-d") != $oldEntry->getDay()->format("Y-m-d"))
                        $this->calculateClasses(
                            $user->getId(), $oldEntry->getDay()->format("Y-m-d")
                        );
                }
            }

            // update JIRA, if necessary
            try {
                $this->updateJiraWorklog($entry, $oldEntry);
                // Save potential worklog ID
                $em->persist($entry);
                $em->flush();
            } catch (JiraApiException $e) {
                if ($e->getRedirectUrl()) {
                    // Invalid JIRA token
                    return new Error($e->getMessage(), 403, $e->getRedirectUrl());
                }
                $alert = $e->getMessage() . '<br />' .
                    $this->get('translator')->trans("Dataset was modified in Timetracker anyway");
            }

            $response = array(
                'result' => $entry->toArray(),
                'alert'  => $alert
            );

            return new Response(json_encode($response));
        } catch (\Exception $e) {
            return new Error(
                $this->get('translator')->trans($e->getMessage()), 406
            );
        }
    }


    /**
     * Inserts a series of same entries by preset
     *
     * @param Request $request
     *
     * @return Response
     */
    public function bulkentryAction(Request $request)
    {
        if (!$this->checkLogin($request)) {
            return $this->getFailedLoginResponse();
        }

        try {
            $alert = null;
            $this->logDataToFile($_POST, TRUE);

            $doctrine = $this->getDoctrine();

            $preset = $doctrine->getRepository('NetresearchTimeTrackerBundle:Preset')->find((int) $request->get('preset'));
            if (! is_object($preset))
                throw new \Exception('Preset not found');

            // Retrieve needed objects
            $user     = $doctrine->getRepository('NetresearchTimeTrackerBundle:User')
                ->find($this->_getUserId($request));
            $customer = $doctrine->getRepository('NetresearchTimeTrackerBundle:Customer')
                ->find($preset->getCustomerId());
            $project  = $doctrine->getRepository('NetresearchTimeTrackerBundle:Project')
                ->find($preset->getProjectId());
            $activity = $doctrine->getRepository('NetresearchTimeTrackerBundle:Activity')
                ->find($preset->getActivityId());
            $em = $doctrine->getManager();

            $date = new \DateTime($request->get('startdate'));
            $endDate = new \DateTime($request->get('enddate'));

            $c = 0;

            // define weekends
            $weekend = array('0','6','7');

            // define regular holidays
            $regular_holidays = array(
                "01-01",
                "05-01",
                "10-03",
                "10-31",
                "12-25",
                "12-26"
            );

            // define irregular holidays
            $irregular_holidays = array(
                "2012-04-06",
                "2012-04-09",
                "2012-05-17",
                "2012-05-28",
                "2012-11-21",

                "2013-03-29",
                "2013-04-01",
                "2013-05-09",
                "2013-05-20",
                "2013-11-20",

                "2014-04-18",
                "2014-04-21",
                "2014-05-29",
                "2014-06-09",
                "2014-11-19",

                "2015-04-03",
                "2015-04-04",
                "2015-05-14",
                "2015-05-25",
                "2015-11-18",
            );

            do {
                // some loop security
                $c++;
                if ($c > 100) break;

                // skip weekends
                if (($request->get('skipweekend'))
                    && (in_array($date->format('w'), $weekend))
                ) {
                    $date->add(new \DateInterval('P1D'));
                    continue;
                }

                // skip holidays
                if (($request->get('skipholidays'))) {
                    // skip regular holidays
                    if (in_array($date->format("m-d"), $regular_holidays)) {
                        $date->add(new \DateInterval('P1D'));
                        continue;
                    }

                    // skip irregular holidays
                    if (in_array($date->format("Y-m-d"), $irregular_holidays)) {
                        $date->add(new \DateInterval('P1D'));
                        continue;
                    }
                }

                $entry = new Entry();
                $entry->setUser($user)
                    ->setTicket('')
                    ->setDescription($preset->getDescription())
                    ->setDay($date)
                    ->setStart($request->get('starttime') ? $request->get('starttime') : null)
                    ->setEnd($request->get('endtime') ? $request->get('endtime') : null)
                    //->calcDuration(is_object($activity) ? $activity->getFactor() : 1);
                    ->calcDuration();

                if ($project) {
                    $entry->setProject($project);
                }
                if ($activity) {
                    $entry->setActivity($activity);
                }
                if ($customer) {
                    $entry->setCustomer($customer);
                }

                // write log
                $this->logDataToFile($entry->toArray());

                $em->persist($entry);
                $em->flush();

                // calculate color lines for the changed days
                $this->calculateClasses($user->getId(), $entry->getDay()->format("Y-m-d"));

                // print $date->format('d.m.Y') . " was saved.<br/>";
                $date->add(new \DateInterval('P1D'));
            } while ($date <= $endDate);

            $response = new Response($this->get('translator')->trans('All entries have been saved.'));
            $response->setStatusCode(200);
            return $response;

        } catch (\Exception $e) {
            $response = new Response($this->get('translator')->trans($e->getMessage()));
            $response->setStatusCode(406);
            return $response;
        }
    }



    /**
     * Ensures valid ticket number format.
     *
     * @param $ticket
     * @return void
     * @throws \Exception
     */
    private function requireValidTicketFormat($ticket)
    {
        // do not check empty tickets
        if (strlen($ticket) < 1) {
            return;
        }

        if (! TicketHelper::checkFormat($ticket)) {
            $message = $this->get('translator')->trans("The ticket's format is not recognized.");
            throw new \Exception($message);
        }

        return;
    }



    /**
     * TTT-199: check if ticket prefix matches project's JIRA id.
     *
     * @param Project $project
     * @param string $ticket
     * @throws \Exception
     * @return void
     */
    private function requireValidTicketPrefix(Project $project, $ticket)
    {
        // do not check empty tickets
        if (strlen($ticket) < 1) {
            return;
        }

        // do not check empty jira-projects
        if (strlen($project->getJiraId()) < 1) {
            return;
        }

        if (! TicketHelper::checkFormat($ticket)) {
            $message = $this->get('translator')->trans("The ticket's format is not recognized.");
            throw new \Exception($message);
        }

        $jiraId = TicketHelper::getPrefix($ticket);
        $projectIds = explode(",", $project->getJiraId());

        foreach ($projectIds as $pId) {
            if (trim($pId) == $jiraId) {
                return;
            }
        }

        $message = $this->get('translator')->trans(
            "The ticket's JIRA ID '%ticket_jira_id%' does not match the project's JIRA ID '%project_jira_id%'.",
            array('%ticket_jira_id%' => $jiraId, '%project_jira_id%' => $project->getJiraId())
        );

        throw new \Exception($message);
    }



    /**
     * Write log entry to log file.
     *
     * @param array $data
     * @param bool  $raw
     * @throws \Exception
     */
    private function logDataToFile(array $data, $raw = FALSE)
    {
        $file = $this->get('kernel')->getRootDir() . '/logs/' . self::LOG_FILE;
        if (!file_exists($file) && !touch($file)) {
            throw new \Exception(
                $this->get('translator')->trans(
                    'Could not create log file: %log_file%',
                    array('%log_file%' => $file)
                )
            );
        }

        if (!is_writable($file)) {
            throw new \Exception(
                $this->get('translator')->trans(
                    'Cannot write to log file: %log_file%',
                    array('%log_file%' => $file)
                )
            );
        }

        $log = sprintf(
            '[%s][%s]: %s %s',
            date('d.m.Y H:i:s'),
            ($raw ? 'raw' : 'obj'),
            json_encode($data),
            PHP_EOL
        );

        file_put_contents($file, $log, FILE_APPEND);
    }



    /**
     * Updates a JIRA work log entry.
     *
     * @param Entry $entry
     * @param Entry $oldEntry
     *
     * @return void
     */
    private function updateJiraWorklog(Entry $entry, Entry $oldEntry)
    {
        $project = $entry->getProject();
        if (! $project instanceof \Netresearch\TimeTrackerBundle\Entity\Project) {
            return;
        }

        $ticketSystem = $project->getTicketSystem();
        if (! $ticketSystem instanceof \Netresearch\TimeTrackerBundle\Entity\TicketSystem) {
            return;
        }

        if (! $ticketSystem->getBookTime() || $ticketSystem->getType() != 'JIRA') {
            return;
        }

        if ($oldEntry->getTicket() != $entry->getTicket()) {
            // ticket number changed
            // delete old worklog - new one will be created later
            $this->deleteJiraWorklog($oldEntry);
            $entry->setWorklogId(NULL);
        }

        $jiraOAuthApi = new JiraOAuthApi($entry->getUser(), $ticketSystem, $this->getDoctrine(), $this->container->get('router'));
        $jiraOAuthApi->updateEntryJiraWorkLog($entry);
    }
}
