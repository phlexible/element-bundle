<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\ElementVersion;

use Phlexible\Bundle\ElementBundle\Entity\ElementVersion;
use Phlexible\Bundle\ElementBundle\Entity\ElementVersionMappedField;
use Phlexible\Bundle\ElementBundle\Model\ElementStructure;
use Phlexible\Bundle\ElementtypeBundle\ElementtypeService;

/**
 * Field mapper
 *
 * @author Stephan Wentz <swentz@brainbits.net>
 */
class FieldMapper
{
    /**
     * @var ElementtypeService
     */
    private $elementtypeService;

    /**
     * @var array
     */
    private $availableLanguages;

    /**
     * @var FieldMapperInterface[]
     */
    private $mappers = array();

    /**
     * @param ElementtypeService     $elementtypeService
     * @param string                 $availableLanguages
     * @param FieldMapperInterface[] $mappers
     */
    public function __construct(ElementtypeService $elementtypeService, $availableLanguages, array $mappers = array())
    {
        $this->elementtypeService = $elementtypeService;
        $this->availableLanguages = explode(',', $availableLanguages);
        $this->mappers = $mappers;
    }

    /**
     * @param FieldMapperInterface $mapper
     *
     * @return $this
     */
    public function addMapper(FieldMapperInterface $mapper)
    {
        $this->mappers[] = $mapper;

        return $this;
    }

    /**
     * @return array
     */
    public function getAvailableLanguages()
    {
        return $this->availableLanguages;
    }

    /**
     * @param ElementVersion   $elementVersion
     * @param ElementStructure $elementStructure
     * @param string           $language
     *
     * @return array
     */
    public function extract(ElementVersion $elementVersion, ElementStructure $elementStructure = null, $language)
    {
        $elementtype = $this->elementtypeService->findElementtype($elementVersion->getElement()->getElementtypeId());
        $elementtypeVersion = $this->elementtypeService->findElementtypeVersion(
            $elementtype,
            $elementVersion->getElementtypeVersion()
        );

        $titles = array();

        if ($elementStructure) {
            $mappings = $elementtypeVersion->getMappings();

            if ($mappings) {
                foreach ($mappings as $key => $mapping) {
                    if ($mapper = $this->findFieldMapper($key)) {
                        $title = $mapper->map($elementStructure, $language, $mapping);
                        if ($title) {
                            $titles[$key] = $title;
                        }
                    }
                }
            }
        }

        if (empty($titles['backend'])) {
            $titles['backend'] = '[' . $elementtype->getTitle() . ', ' . $language . ']';
        }

        return $titles;
    }

    /**
     * @param ElementVersion   $elementVersion
     * @param ElementStructure $elementStructure
     * @param array|null       $languages
     */
    public function apply(ElementVersion $elementVersion, ElementStructure $elementStructure = null, array $languages = null)
    {
        if (null === $languages) {
            $languages = $this->availableLanguages;
        }

        foreach ($languages as $language) {
            $mapping = $this->extract($elementVersion, $elementStructure, $language);

            $mappedFields = $elementVersion->getMappedFields();
            if (!$mappedFields->contains($language)) {
                $mappedField = new ElementVersionMappedField();
                $mappedField
                    ->setLanguage($language)
                    ->setElementVersion($elementVersion);
                $mappedFields->set($language, $mappedField);
            }

            $mappedField = $mappedFields->get($language);
            $mappedField->setMapping($mapping);

            $elementVersion->setMappedFields($mappedFields);
        }
    }

    /**
     * @param string $key
     *
     * @return FieldMapperInterface|null
     */
    private function findFieldMapper($key)
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->accept($key)) {
                return $mapper;
            }
        }

        return null;
    }
}