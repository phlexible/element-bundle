<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\ElementStructure\LinkExtractor;

use Phlexible\Bundle\ElementBundle\Model\ElementStructureValue;
use Phlexible\Component\Elementtype\Field\FieldRegistry;

/**
 * Link extractor
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class LinkExtractor
{
    /**
     * @var FieldRegistry
     */
    private $fieldRegistry;

    /**
     * @var LinkExtractorInterface[]
     */
    private $extractors = [];

    /**
     * @param FieldRegistry            $fieldRegistry
     * @param LinkExtractorInterface[] $extractors
     */
    public function __construct(FieldRegistry $fieldRegistry, array $extractors = [])
    {
        $this->fieldRegistry = $fieldRegistry;

        foreach ($extractors as $extractor) {
            $this->addExtractor($extractor);
        }
    }

    /**
     * @param LinkExtractorInterface $extractor
     *
     * @return $this
     */
    public function addExtractor(LinkExtractorInterface $extractor)
    {
        $this->extractors[] = $extractor;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function extract(ElementStructureValue $value)
    {
        $field = $this->fieldRegistry->getField($value->getType());

        $links = [];
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($value, $field)) {
                foreach ($extractor->extract($value, $field) as $link) {
                    $links[] = $link;
                }
            }
        }

        return $links;
    }
}
