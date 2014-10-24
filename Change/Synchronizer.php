<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\Change;

use Doctrine\ORM\EntityManager;
use Phlexible\Bundle\ElementBundle\Entity\ElementSource;
use Phlexible\Bundle\ElementBundle\Model\ElementSourceManagerInterface;
use Phlexible\Bundle\ElementBundle\Model\ElementVersionManagerInterface;
use Phlexible\Bundle\ElementtypeBundle\File\Dumper\XmlDumper;
use Phlexible\Bundle\ElementtypeBundle\Model\Elementtype;

/**
 * Synchronizer
 *
 * @author Stephan Wentz <sw@brainbits.net>
 *
 * @TODO   : elementSourceManager
 */
class Synchronizer
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var ElementVersionManagerInterface
     */
    private $elementVersionManager;

    /**
     * @var ElementSourceManagerInterface
     */
    private $elementSourceManager;

    /**
     * @var XmlDumper
     */
    private $xmlDumper;

    /**
     * @param ElementVersionManagerInterface $elementVersionManager
     * @param ElementSourceManagerInterface  $elementSourceManager
     * @param XmlDumper                      $xmlDumper
     */
    public function __construct(
        ElementVersionManagerInterface $elementVersionManager,
        ElementSourceManagerInterface $elementSourceManager,
        XmlDumper $xmlDumper
    )
    {
        $this->elementSourceManager = $elementSourceManager;
        $this->elementVersionManager = $elementVersionManager;
        $this->xmlDumper = $xmlDumper;
    }

    /**
     * @param Change $change
     */
    public function synchronize(Change $change)
    {
        $elementSource = $this->importElementtype($change->getElementtype());

        $outdatedElementSources = $change->getOutdatedElementSources();
        foreach ($outdatedElementSources as $outdatedElementSource) {
            $elementVersions = $this->elementVersionManager->findByElementSource($elementSource);
            foreach ($elementVersions as $elementVersion) {
                $elementVersion->setElementSource($elementSource);
                $this->elementVersionManager->updateElementVersion($elementVersion, false);
            }
            $this->removeOutdatedElementSource($outdatedElementSource);
        }
    }

    /**
     * @param Elementtype $elementtype
     *
     * @return ElementSource
     */
    private function importElementtype(Elementtype $elementtype)
    {
        $elementSource = $this->elementSourceManager->findByElementtype($elementtype);
        if ($elementSource) {
            return $elementSource;
        }

        $elementSource = new ElementSource();
        $elementSource
            ->setElementtypeId($elementtype->getId())
            ->setElementtypeRevision($elementtype->getRevision())
            ->setXml($this->xmlDumper->dump($elementtype))
            ->setCreatedAt(new \DateTime());

        $this->elementSourceManager->updateElementSource($elementSource, false);

        return $elementSource;
    }

    private function removeOutdatedElementSource(ElementSource $elementSource)
    {
        $this->elementSourceManager->deleteElementSource($elementSource);
    }
}
