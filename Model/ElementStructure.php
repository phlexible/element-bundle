<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\Model;

use Phlexible\Bundle\ElementBundle\Entity\ElementVersion;

/**
 * Element structure
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElementStructure implements \IteratorAggregate
{
    /**
     * @var ElementVersion
     */
    private $elementVersion;

    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $repeatableId;

    /**
     * @var string
     */
    private $dsId;

    /**
     * @var string
     */
    private $repeatableDsId;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $parentName;

    /**
     * @var ElementStructure[]
     */
    private $structures = array();

    /**
     * @var ElementStructureValue[]
     */
    private $values = array();

    /**
     * @return ElementVersion
     */
    public function getElementVersion()
    {
        return $this->elementVersion;
    }

    /**
     * @param ElementVersion $elementVersion
     *
     * @return $this
     */
    public function setElementVersion(ElementVersion $elementVersion)
    {
        $this->elementVersion = $elementVersion;

        return $this;
    }

    /**
     * @return string
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
        $this->id = (int) $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getRepeatableId()
    {
        return $this->repeatableId;
    }

    /**
     * @param int $parentId
     *
     * @return $this
     */
    public function setRepeatableId($parentId)
    {
        $this->repeatableId = $parentId !== null ? (int) $parentId : null;

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
    public function getRepeatableDsId()
    {
        return $this->repeatableDsId;
    }

    /**
     * @param string $parentDsId
     *
     * @return $this
     */
    public function setRepeatableDsId($parentDsId)
    {
        $this->repeatableDsId = $parentDsId;

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
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * @param string $parentName
     *
     * @return $this
     */
    public function setParentName($parentName)
    {
        $this->parentName = $parentName;

        return $this;
    }

    /**
     * @param ElementStructure $elementStructure
     *
     * @return $this
     */
    public function addStructure(ElementStructure $elementStructure)
    {
        $elementStructure
            ->setRepeatableId($this->getId())
            ->setRepeatableDsId($this->getDsId());

        $this->structures[] = $elementStructure;

        return $this;
    }

    /**
     * @return ElementStructure[]
     */
    public function getStructures()
    {
        return $this->structures;
    }

    /**
     * @param ElementStructureValue $value
     *
     * @return $this;
     */
    public function setValue(ElementStructureValue $value)
    {
        $this->values[$value->getName()] = $value;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return ElementStructureValue
     */
    public function getValue($name)
    {
        if (!$this->hasValue($name)) {
            return null;
        }

        return $this->values[$name];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasValue($name)
    {
        return isset($this->values[$name]);
    }

    /**
     * @return ElementStructureValue[]
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * @param string $dsId
     *
     * @return bool
     */
    public function hasValueByDsId($dsId)
    {
        foreach ($this->values as $value) {
            if ($value->getDsId() === $dsId) {
                return true;
            }
        }

        return false;

    }

    /**
     * @param string $dsId
     *
     * @return ElementStructureValue|null
     */
    public function getValueByDsId($dsId)
    {
        foreach ($this->values as $value) {
            if ($value->getDsId() === $dsId) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return ElementStructureIterator
     */
    public function getIterator()
    {
        return new ElementStructureIterator($this->getStructures());
    }

    /**
     * @param string $name
     *
     * @return ElementStructure|ElementStructureValue|null
     */
    public function find($name)
    {
        if ($this->hasValue($name)) {
            return $this->getValue($name);
        }

        foreach ($this->getStructures() as $childStructure) {
            if ($result = $childStructure->find($name)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param string $name
     *
     * @return ElementStructure
     */
    public function children($name = null)
    {
        if (!$name) {
            return $this->getStructures();
        }

        $result = array();
        foreach ($this->getStructures() as $childStructure) {
            if ($childStructure->getParentName() === $name) {
                $result[] = $childStructure;
            } else {
                $localResult = $childStructure->children($name);
                if ($localResult) {
                    $result = array_merge($result, $localResult);
                }
            }
        }

        if (!count($result)) {
            return null;
        }

        return $result;
    }

    /**
     * @param int $indent
     *
     * @return string
     */
    public function dump($indent = 0)
    {
        $dump = str_repeat(' ', $indent) . 'S '.$this->getId().' '.$this->getDsId().' '.$this->getName().' '.$this->getParentName().' '.($this->getElementVersion() ? 'EV' : 'noEV').PHP_EOL;
        foreach ($this->structures as $structure) {
            $dump .= $structure->dump($indent + 1);
        }
        foreach ($this->values as $value) {
            $dump .= str_repeat(' ', $indent+1).'V '.$value->getId().' '.$value->getDsId().' '.$value->getName().' '.$value->getValue().PHP_EOL;
        }
        return $dump;
    }
}

