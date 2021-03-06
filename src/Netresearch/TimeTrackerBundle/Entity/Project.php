<?php
namespace Netresearch\TimeTrackerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Netresearch\TimeTrackerBundle\Model\Base as Base;

/**
 *
 * @ORM\Entity
 * @ORM\Table(name="projects")
 * @ORM\Entity(repositoryClass="Netresearch\TimeTrackerBundle\Entity\ProjectRepository")
 */
class Project extends Base
{

    const BILLING_NONE      = 0;
    const BILLING_TM        = 1;
    const BILLING_FP        = 2;
    const BILLING_MIXED     = 3;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $active;

    /**
     * @ORM\ManyToOne(targetEntity="Customer", inversedBy="projects")
     * @ORM\JoinColumn(name="customer_id", referencedColumnName="id")
     */
    protected $customer;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $global;


    /**
     * @ORM\Column(type="string", name="jira_id")
     */
    protected $jiraId;

    /**
     * @ORM\ManyToOne(targetEntity="TicketSystem", inversedBy="projects")
     * @ORM\JoinColumn(name="ticket_system", referencedColumnName="id")
     */
    protected $ticketSystem;

    /**
     * @ORM\OneToMany(targetEntity="Entry", mappedBy="project")
     */
    protected $entries;


    /**
     * Estimated project duration in minutes
     * @ORM\Column(type="integer", name="estimation")
     */
    protected $estimation;

    /**
     * Offer number
     * @ORM\Column(name="offer", length=31)
     */
    protected $offer;

    /**
     * Used billing method
     * @ORM\Column(type="integer", name="billing")
     */
    protected $billing;

    /**
     * cost center (number or name)
     * @ORM\Column(name="cost_center", length=31, nullable=true)
     */
    protected $costCenter;

    /**
     * internal reference number
     * @ORM\Column(name="internal_ref", length=31, nullable=true)
     */
    protected $internalReference;

    /**
     * external (clients) reference number
     * @ORM\Column(name="external_ref", length=31, nullable=true)
     */
    protected $externalReference;

    /**
     * project manager user
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="project_lead_id", referencedColumnName="id", nullable=true)
     */
    protected $projectLead;

    /**
     * lead developer
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="technical_lead_id", referencedColumnName="id", nullable=true)
     */
    protected $technicalLead;

    /**
     * invoice number, reserved for future use
     * * @ORM\Column(name="invoice", length=31, nullable=true)
     */
    protected $invoice;

    /**
     * @ORM\Column(name="additional_information_from_external", type="boolean")
     */
    protected $additionalInformationFromExternal;

    /**
     * @param boolean $additionalInformationFromExternal
     */
    public function setAdditionalInformationFromExternal($additionalInformationFromExternal)
    {
        $this->additionalInformationFromExternal = $additionalInformationFromExternal;
    }

    /**
     * @return boolean
     */
    public function getAdditionalInformationFromExternal()
    {
        return $this->additionalInformationFromExternal;
    }


    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }


    /**
     * Get id
     *
     * @return integer $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set id
     * @param integer $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
    }



    /**
     * Set active
     *
     * @param boolean $active
     *
     * @return $this
     */
    public function setActive($active)
    {
        $this->active = $active;
        return $this;
    }

    /**
     * Get active
     *
     * @return boolean $active
     */
    public function getActive()
    {
        return $this->active;
    }



    /**
     * Set customer
     *
     * @param \Netresearch\TimeTrackerBundle\Entity\Customer $customer
     *
     * @return $this
     */
    public function setCustomer(\Netresearch\TimeTrackerBundle\Entity\Customer $customer)
    {
        $this->customer = $customer;
        return $this;
    }

    /**
     * Get customer
     *
     * @return \Netresearch\TimeTrackerBundle\Entity\Customer $customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }




    /**
     * Set global
     *
     * @param boolean $global
     *
     * @return $this
     */
    public function setGlobal($global)
    {
        $this->global = $global;
        return $this;
    }

    /**
     * Get global
     *
     * @return boolean $global
     */
    public function getGlobal()
    {
        return $this->global;
    }



    /**
     * Add entries
     *
     * @param \Netresearch\TimeTrackerBundle\Entity\Entry $entries
     *
     * @return $this
     */
    public function addEntries(\Netresearch\TimeTrackerBundle\Entity\Entry $entries)
    {
        $this->entries[] = $entries;
        return $this;
    }

    /**
     * Get entries
     *
     * @return \Doctrine\Common\Collections\Collection $entries
     */
    public function getEntries()
    {
        return $this->entries;
    }

    public function getJiraId()
    {
        return $this->jiraId;
    }

    public function setJiraId($jiraId)
    {
        $this->jiraId = $jiraId;
        return $this;
    }

    /**
     * @return integer $ticketSystem
     */
    public function getTicketSystem()
    {
        return $this->ticketSystem;
    }

    /**
     * Set the id of the ticket system that is associated with this project
     * @param TicketSystem $ticketSystem
     *
     * @return $this
     */
    public function setTicketSystem($ticketSystem)
    {
        $this->ticketSystem = $ticketSystem;
        return $this;
    }


    public function getEstimation()
    {
        return $this->estimation;
    }

    public function setEstimation($estimation)
    {
        $this->estimation = $estimation;
        return $this;
    }

    public function getOffer()
    {
        return $this->offer;
    }

    public function setOffer($offer)
    {
        $this->offer = $offer;
        return $this;
    }

    public function getBilling()
    {
        return $this->billing;
    }

    public function setBilling($billing)
    {
        $this->billing = $billing;
        return $this;
    }

    public function getCostCenter()
    {
        return $this->costCenter;
    }

    public function setCostCenter($costCenter)
    {
        $this->costCenter = $costCenter;
        return $this;
    }

    public function getInvoice()
    {
        return $this->invoice;
    }

    public function setInvoice($invoice)
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function setProjectLead($projectLead)
    {
        $this->projectLead = $projectLead;
        return $this;
    }

    public function getProjectLead()
    {
        return $this->projectLead;
    }

    public function setTechnicalLead($technicalLead)
    {
        $this->technicalLead = $technicalLead;
        return $this;
    }

    public function getTechnicalLead()
    {
        return $this->technicalLead;
    }


    /**
     * Set internalReference
     *
     * @param string $internalReference
     * @return Project
     */
    public function setInternalReference($internalReference)
    {
        $this->internalReference = $internalReference;

        return $this;
    }

    /**
     * Get internalReference
     *
     * @return string
     */
    public function getInternalReference()
    {
        return $this->internalReference;
    }

    /**
     * Set externalReference
     *
     * @param string $externalReference
     * @return Project
     */
    public function setExternalReference($externalReference)
    {
        $this->externalReference = $externalReference;
        return $this;
    }

    /**
     * Get externalReference
     *
     * @return string
     */
    public function getExternalReference()
    {
        return $this->externalReference;
    }

    /**
     * Add entries
     *
     * @param \Netresearch\TimeTrackerBundle\Entity\Entry $entries
     * @return Project
     */
    public function addEntry(\Netresearch\TimeTrackerBundle\Entity\Entry $entries)
    {
        $this->entries[] = $entries;
        return $this;
    }

    /**
     * Remove entries
     *
     * @param \Netresearch\TimeTrackerBundle\Entity\Entry $entries
     */
    public function removeEntrie(\Netresearch\TimeTrackerBundle\Entity\Entry $entries)
    {
        $this->entries->removeElement($entries);
    }

    /**
     * Add entries
     *
     * @param \Netresearch\TimeTrackerBundle\Entity\Entry $entries
     * @return Project
     */
    public function addEntrie(\Netresearch\TimeTrackerBundle\Entity\Entry $entries)
    {
        $this->entries[] = $entries;

        return $this;
    }
}
