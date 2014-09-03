<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\ElementVersion;

use Phlexible\Bundle\ElementBundle\Model\ElementStructure;
use Phlexible\Bundle\ElementBundle\Model\ElementStructureValue;

/**
 * Title field mapper
 *
 * @author Stephan Wentz <swentz@brainbits.net>
 */
class TitleFieldMapper implements FieldMapperInterface
{
    /**
     * {@inheritdoc}
     */
    public function accept($key)
    {
        return in_array($key, array('backend', 'page', 'navigation'));
    }

    /**
     * {@inheritdoc}
     */
    public function map(ElementStructure $elementStructure, array $mapping)
    {
        $pattern = $mapping['pattern'];
        $replace = array();
        foreach ($mapping['fields'] as $field) {
            $dsId = $field['ds_id'];
            $replace['$' . $field['index']] = $this->findValue($elementStructure, $dsId);
        }
        $title = str_replace(array_keys($replace), array_values($replace), $pattern);

        if (!$title) {
            return null;
        }

        return $title;
    }

    /**
     * @param ElementStructure $elementStructure
     * @param string           $dsId
     *
     * @return null|ElementStructureValue
     */
    private function findValue(ElementStructure $elementStructure, $dsId)
    {
        if ($elementStructure->hasValueByDsId($dsId)) {
            return $elementStructure->getValueByDsId($dsId);
        }

        foreach ($elementStructure->getStructures() as $childStructure) {
            $value = $this->findValue($childStructure, $dsId);
            if ($value) {
                return $value;
            }
        }

        return null;
    }
}