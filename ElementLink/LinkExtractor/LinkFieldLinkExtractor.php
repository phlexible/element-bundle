<?php

/*
 * This file is part of the phlexible package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\ElementBundle\ElementLink\LinkExtractor;

use Phlexible\Bundle\ElementBundle\Entity\ElementLink;
use Phlexible\Bundle\ElementBundle\Model\ElementStructureValue;
use Phlexible\Bundle\ElementtypeBundle\Field\AbstractField;

/**
 * Text link extractor.
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class LinkFieldLinkExtractor implements LinkExtractorInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(ElementStructureValue $value, AbstractField $field)
    {
        return $value->getType() === 'link';
    }

    /**
     * {@inheritdoc}
     */
    public function extract(ElementStructureValue $value, AbstractField $field)
    {
        if (!$value->getValue()) {
            return [];
        }

        $rawValue = $value->getValue();
        if (!is_array($rawValue)) {
            return [];
        }

        $link = new ElementLink();
        $link
            ->setLanguage($value->getLanguage())
            ->setField($value->getName());

        $type = $rawValue['type'];
        if (in_array($type, ['internal', 'intrasiteroot']) && !empty($rawValue['tid'])) {
            $link
                ->setType('link-internal')
                ->setTarget($rawValue['tid']);
        } elseif ($type === 'external' && !empty($rawValue['url'])) {
            $link
                ->setType('link-external')
                ->setTarget($rawValue['url']);
        } elseif ($type === 'mailto' && !empty($rawValue['recipient'])) {
            $link
                ->setType('link-mailto')
                ->setTarget($rawValue['recipient']);
        } else {
            return [];
        }

        return [$link];
    }
}
