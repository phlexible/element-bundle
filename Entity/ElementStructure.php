<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Element structure
 *
 * @author Stephan Wentz <sw@brainbits.net>
 *
 * @ORM\Entity
 * @ORM\Table(name="element_structure", uniqueConstraints={@ORM\UniqueConstraint(columns={"data_id", "eid", "version"})})
 */
class ElementStructure
{
    /**
     * @var int
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="data_id", type="string", length=36, options={"fixed"=true})
     */
    private $dataId;

    /**
     * @var Element
     * @ORM\ManyToOne(targetEntity="Element")
     * @ORM\JoinColumn(name="eid", referencedColumnName="eid", onDelete="CASCADE")
     */
    private $element;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    private $version;

    /**
     * @var string
     * @ORM\Column(name="ds_id", type="string", length=36, options={"fixed"=true})
     */
    private $dsId;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $type;

    /**
     * @var string
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $name;

    /**
     * @var string
     * @ORM\Column(name="repeatable_id", type="string", length=255, nullable=true)
     */
    private $repeatableId;

    /**
     * @var string
     * @ORM\Column(name="repeatable_ds_id", type="string", length=36, nullable=true, options={"fixed"=true})
     */
    private $repeatableDsId;

    /**
     * @var int
     * @ORM\Column(type="integer", nullable=true)
     */
    private $sort;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getDataId()
    {
        return $this->dataId;
    }

    /**
     * @param string $dataId
     *
     * @return $this
     */
    public function setDataId($dataId)
    {
        $this->dataId = $dataId;

        return $this;
    }

    /**
     * @return Element
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @param Element $element
     *
     * @return $this
     */
    public function setElement($element)
    {
        $this->element = $element;

        return $this;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param int $version
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return string
     */
    public function getDsId()
    {
        return $this->dsId;
    }

    /**
     * @param string $dsId
     *
     * @return $this
     */
    public function setDsId($dsId)
    {
        $this->dsId = $dsId;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
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
     * @return string
     */
    public function getRepeatableId()
    {
        return $this->repeatableId;
    }

    /**
     * @param string $repeatableId
     *
     * @return $this
     */
    public function setRepeatableId($repeatableId)
    {
        $this->repeatableId = $repeatableId;

        return $this;
    }

    /**
     * @return string
     */
    public function getRepeatableDsId()
    {
        return $this->repeatableDsId;
    }

    /**
     * @param string $repeatableDsId
     *
     * @return $this
     */
    public function setRepeatableDsId($repeatableDsId)
    {
        $this->repeatableDsId = $repeatableDsId;

        return $this;
    }

    /**
     * @return int
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @param int $sort
     *
     * @return $this
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
    }
}