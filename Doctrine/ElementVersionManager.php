<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Phlexible\Bundle\ElementBundle\ElementEvents;
use Phlexible\Bundle\ElementBundle\ElementsMessage;
use Phlexible\Bundle\ElementBundle\ElementVersion\ElementVersionRepository;
use Phlexible\Bundle\ElementBundle\Entity\Element;
use Phlexible\Bundle\ElementBundle\Entity\ElementVersion;
use Phlexible\Bundle\ElementBundle\Entity\ElementVersionEvent;
use Phlexible\Bundle\ElementBundle\Model\ElementVersionManagerInterface;
use Phlexible\Bundle\MessageBundle\Message\MessagePoster;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Element version manager
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElementVersionManager implements ElementVersionManagerInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var MessagePoster
     */
    private $messagePoster;

    /**
     * @var ElementVersionRepository
     */
    private $elementVersionRepository;

    /**
     * @param EntityManager            $entityManager
     * @param EventDispatcherInterface $dispatcher
     * @param MessagePoster            $messagePoster
     */
    public function __construct(EntityManager $entityManager, EventDispatcherInterface $dispatcher, MessagePoster $messagePoster)
    {
        $this->entityManager = $entityManager;
        $this->dispatcher = $dispatcher;
        $this->messagePoster = $messagePoster;
    }

    /**
     * @return ElementVersionRepository
     */
    private function getElementVersionRepository()
    {
        if (null === $this->elementVersionRepository) {
            $this->elementVersionRepository = $this->entityManager->getRepository('PhlexibleElementBundle:ElementVersion');
        }

        return $this->elementVersionRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function find(Element $element, $version)
    {
        return $this->getElementVersionRepository()->findOneBy(
            array(
                'element' => $element,
                'version' => $version,
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getVersions(Element $element)
    {
        return $this->getElementVersionRepository()->getVersions($element);
    }

    /**
     * {@inheritdoc}
     */
    public function updateElementVersion(ElementVersion $elementVersion)
    {
        $event = new ElementVersionEvent($elementVersion);
        $this->dispatcher->dispatch(ElementEvents::BEFORE_UPDATE_ELEMENT_VERSION, $event);
        if ($event->isPropagationStopped()) {
            throw new \Exception('Canceled by listener.');
        }

        $this->entityManager->persist($elementVersion);
        $this->entityManager->flush($elementVersion);

        $event = new ElementVersionEvent($elementVersion);
        $this->dispatcher->dispatch(ElementEvents::UPDATE_ELEMENT_VERSION, $event);

        // post message
        $message = ElementsMessage::create('Element version "' . $elementVersion->getEid() . ' updated.');
        $this->messagePoster->post($message);
    }
}