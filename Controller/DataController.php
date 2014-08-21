<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\ElementBundle\Controller;

use Phlexible\Bundle\AccessControlBundle\ContentObject\ContentObjectInterface;
use Phlexible\Bundle\ElementBundle\ElementEvents;
use Phlexible\Bundle\ElementBundle\ElementStructure\Diff\Diff;
use Phlexible\Bundle\ElementBundle\ElementStructure\Serializer\ArraySerializer as ElementArraySerializer;
use Phlexible\Bundle\ElementBundle\Entity\ElementLock;
use Phlexible\Bundle\ElementBundle\Event\LoadDataEvent;
use Phlexible\Bundle\ElementtypeBundle\Entity\Elementtype;
use Phlexible\Bundle\ElementtypeBundle\ElementtypeStructure\Serializer\ArraySerializer as ElementtypeArraySerializer;
use Phlexible\Bundle\GuiBundle\Response\ResultResponse;
use Phlexible\Bundle\SecurityBundle\Acl\Acl;
use Phlexible\Bundle\TreeBundle\Doctrine\TreeFilter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Data controller
 *
 * @author Stephan Wentz <sw@brainbits.net>
 * @Route("/elements/data")
 * @Security("is_granted('elements')")
 */
class DataController extends Controller
{
    /**
     * Load element data
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @Route("/load", name="elements_data_load")
     */
    public function loadAction(Request $request)
    {
        $treeId = (int) $request->get('id');
        $teaserId = (int) $request->get('teaser_id');
        $language = $request->get('language');
        $version = $request->get('version');
        $unlockId = $request->get('unlock');
        $doLock = (bool) $request->get('lock', false);

        $teaserManager = $this->get('phlexible_teaser.teaser_manager');
        $treeManager = $this->get('phlexible_tree.tree_manager');
        $elementService = $this->get('phlexible_element.element_service');
        $iconResolver = $this->get('phlexible_element.icon_resolver');
        $stateManager = $this->get('phlexible_tree.state_manager');
        $elementHistoryManager = $this->get('phlexible_element.element_history_manager');
        $lockManager = $this->get('phlexible_element.element_lock_manager');

        try {
            $teaser = null;
            if ($teaserId) {
                $teaser = $teaserManager->findTeaser($teaserId);
                $eid = $teaser->getTypeId();
                $treeId = $teaser->getTreeId();
                $tree = $treeManager->getByNodeId($treeId);
                $node = $tree->get($treeId);
            } elseif ($treeId) {
                $tree = $treeManager->getByNodeId($treeId);
                $node = $tree->get($treeId);
                $eid = $node->getTypeId();
            } else {
                throw new \Exception('Unknown data requested.');
            }

            $element = $elementService->findElement($node->getTypeId());
            $elementMasterLanguage = $element->getMasterLanguage();

            if (!$language) {
                $language = $elementMasterLanguage;
            }

            if ($teaser) {
                $isPublished = $teaser->isPublished($language);
                $onlineVersion = null;
            } elseif ($treeId) {
                $isPublished = $stateManager->isPublished($node, $language);
                $onlineVersion = $stateManager->getPublishedVersion($node, $language);
            } else {
                throw new \Exception('Unknown data requested.');
            }

            if ($version) {
                $elementVersion = $elementService->findElementVersion($element, $version);
            } else {
                $elementVersion = $elementService->findLatestElementVersion($element);
                $version = $elementVersion->getVersion();
            }

            $elementtypeService = $elementService->getElementtypeService();

            $elementtype = $elementService->findElementtype($element);
            $elementtypeVersion = $elementService->findElementtypeVersion($elementVersion);
            $elementtypeStructure = $elementtypeService->findElementtypeStructure($elementtypeVersion);
            $type = $elementtype->getType();

            // versions

            if ($teaser) {
                $publishedVersions = $elementHistoryManager->findBy(
                    array(
                        'teaserId' => $teaser->getId(),
                        'action'   => 'publishTeaser'
                    )
                );
            } else {
                $publishedVersions = $elementHistoryManager->findBy(
                    array(
                        'treeId' => $node->getId(),
                        'action' => 'publishNode'
                    )
                );
            }

            $versions = array();
            foreach (array_reverse($elementService->getVersions($element)) as $version) {
                $versions[$version] = array(
                    'version'       => $version,
                    'format'        => 2,
                    'create_date'   => date('Y-m-d H:i:s'),
                    'is_published'  => false,
                    'was_published' => false,
                );
            }

            foreach ($publishedVersions as $publishedVersion) {
                $versions[$publishedVersion->getVersion()]['online'] = true;
                if ($publishedVersion->getVersion() === $onlineVersion) {
                    $versions[$publishedVersion->getVersion()]['is_published'] = true;
                } else {
                    $versions[$publishedVersion->getVersion()]['was_published'] = true;
                }
            }

            $versions = array_values($versions);

            // instances

            $instances = array();
            if ($teaser) {
                // TODO: implement $teaserManager->getInstances()
                foreach ($teaserManager->getInstances($teaser) as $instanceTeaser) {
                    $instance = array(
                        'id'              => $instanceTeaser->getId(),
                        'instance_master' => false,
                        'modify_time'     => $instanceTeaser->getCreatedAt()->format('Y-m-d H:i:s'),
                        'icon'            => $iconResolver->resolveTeaser($instanceTeaser, $language),
                        'type'            => 'teaser',
                        'link'            => array(),
                    );

                    $instances[] = $instance;
                }
            } else {
                foreach ($tree->getInstances($node) as $instanceNode) {
                    $instance = array(
                        'id'              => $instanceNode->getId(),
                        'instance_master' => false,
                        'modify_time'     => $instanceNode->getCreatedAt()->format('Y-m-d H:i:s'),
                        'icon'            => $iconResolver->resolveTreeNode($instanceNode, $language),
                        'type'            => 'treenode',
                        'link'            => array(),
                    );

                    if ($instanceNode->getTree()->getSiterootId() !== $tree->getSiterootId()) {
                        $instance['link'] = array(
                            'start_tid_path' => '/' . implode('/', $instanceNode->getTree()->getIdPath($instanceNode)),
                        );
                    }

                    $instances[] = $instance;
                }
            }

            // allowed child elements

            $allowedChildren = array();
            if (!$teaser) {
                foreach ($elementtypeService->findAllowedChildrenIds($elementtype) as $allowedChildId) {
                    $childElementtype = $elementtypeService->findElementtype($allowedChildId);

                    if ($childElementtype->getType() !== 'full') {
                        continue;
                    }

                    $allowedChildren[] = array(
                        $allowedChildId,
                        $childElementtype->getTitle(),
                        $iconResolver->resolveElementtype($childElementtype),
                    );
                }
            }

            // diff

            $diff = $request->get('diff');
            $diffVersionFrom = $request->get('diff_version_from');
            $diffVersionTo = $request->get('diff_version_to');
            $diffLanguage = $request->get('diff_language');

            if ($diff && $diffVersionTo) {
                $fromElementVersion = $elementService->findElementVersion($element, $diffVersionFrom);
                $fromElementStructure = $elementService->findElementStructure($fromElementVersion, $diffLanguage);
                $toElementVersion = $elementService->findElementVersion($element, $diffVersionTo);
                $toElementStructure = $elementService->findElementStructure($toElementVersion, $diffLanguage);
                $differ = new Diff();
                $elementStructure = $differ->diff($fromElementStructure, $toElementStructure);
            } else {
                $elementStructure = $elementService->findElementStructure($elementVersion, $language);
            }

            $diffInfo = null;
            if ($diff) {
                $diffInfo = array(
                    'enabled'      => $diff,
                    'version_from' => $diffVersionFrom,
                    'version_to'   => $diffVersionTo,
                    'language'     => $diffLanguage,
                );
            }

            // lock

            if ($unlockId !== null) {
                $unlockElement = $elementService->findElement($unlockId);
                if ($lockManager->isLockedByUser($unlockElement, $language, $this->getUser()->getId())) {
                    try {
                        $lockManager->unlock($unlockElement, $this->getUser()->getId());
                    } catch (\Exception $e) {
                        // unlock failed
                    }
                }
            }

            $securityContext = $this->get('security.context');
            if ($node instanceof ContentObjectInterface) {
                if (!$securityContext->isGranted(Acl::RESOURCE_SUPERADMIN) &&
                    !$securityContext->isGranted(array('right' => 'EDIT', 'language' => $language), $node)
                ) {
                    $doLock = false;
                }
            }

            $lock = null;
            if ($doLock && !$diff) {
                if (!$lockManager->isLockedByOtherUser($element, $language, $this->getUser()->getId())) {
                    $lock = $lockManager->lock(
                        $element,
                        $this->getUser()->getId(),
                        $language
                    );
                }
            }

            if (!$lock) {
                $lock = $lockManager->findMasterLock($element);
                if (!$lock) {
                    $lock = $lockManager->findSlaveLock($element, $language);
                }
            }

            $lockInfo = null;

            if ($lock && !$diff) {
                $lockUser = $this->get('phlexible_user.user_manager')->find($lock->getUserId());

                $lockInfo = array(
                    'status'   => 'locked',
                    'id'       => $lock->getEid(),
                    'username' => $lockUser->getDisplayName(),
                    'time'     => $lock->getLockedAt()->format('Y-m-d H:i:s'),
                    'age'      => time() - $lock->getLockedAt()->format('U'),
                    'type'     => $lock->getType(),
                );

                if ($lock->getUserId() === $this->getUser()->getId()) {
                    $lockInfo['status'] = 'edit';
                } elseif ($lock->getType() == ElementLock::TYPE_PERMANENTLY) {
                    $lockInfo['status'] = 'locked_permanently';
                }
            } elseif ($diff) {
                // Workaround for loading diffs without locking and view-mask
                // TODO: introduce new diff lock mode

                $lockInfo = array(
                    'status'   => 'edit',
                    'id'       => '',
                    'username' => '',
                    'time'     => '',
                    'age'      => 0,
                    'type'     => ElementLock::TYPE_TEMPORARY,
                );
            }

            // meta
            // TODO: repair element meta

            $metaSetArray = array();
            if (0) {
                $metaSetIdentifier = new Makeweb_Elements_Element_Version_MetaSet_Identifier($elementVersion, $language);
                $metaSetId = $elementtypeVersion->getMetaSetId();

                if ($metaSetId) {
                    $metaSet = $this->getContainer()->get('metasets.repository')->find($metaSetId);
                    $metaSetKeys = $metaSet->getKeys();
                    $metaSetItem = Media_MetaSets_Item_Peer::get($metaSetId, $metaSetIdentifier);
                    $metaSetArray = array_values($metaSetItem->toArray($language));

                    foreach ($metaSetArray as $metaKey => $metaRow) {
                        if ($metaRow['type'] == 'select') {
                            $metaSetArray[$metaKey]['options'] = Brainbits_Util_Array::keyToValue($metaRow['options']);
                        }

                        $metaSetArray[$metaKey]['value_' . $language] = $metaSetArray[$metaKey]['value'];
                        unset($metaSetArray[$metaKey]['value']);
                    }
                }
            }

            // redirects
            // TODO: auslagern

            $redirects = array();
            if (!$teaser && $this->container->has('redirectsManager')) {
                $redirectsManager = $this->get('redirectsManager');
                $redirects = $redirectsManager->getForTidAndLanguage($treeId, $language);
            }

            // preview / online url

            $urls = array(
                'preview' => '',
                'online'  => '',
            );

            $publishDate = null;
            $publishUser = null;
            $onlineVersion = null;
            $latestVersion = null;

            if (in_array($elementtype->getType(), array(Elementtype::TYPE_FULL, Elementtype::TYPE_STRUCTURE, Elementtype::TYPE_PART))) {
                if ($type == Elementtype::TYPE_FULL) {
                    $contentNode = $this->get('phlexible_tree.content.manager')->findByTreeId($node->getId())->get($node->getId());
                    $urls['preview'] = $this->get('phlexible_tree.router')->generate($contentNode);

                    if ($isPublished) {
                        $urls['online'] = $this->get('phlexible_tree.router')->generate($contentNode);
                    }
                }

                if ($isPublished) {
                    $publishInfo = $stateManager->getPublishInfo($node, $language);
                    $publishDate = $publishInfo['published_at'];
                    $publishUserId = $publishInfo['publish_user_id'];
                    $publishUser = $this->get('phlexible_user.user_manager')->find($publishUserId);
                    $onlineVersion = $publishInfo['version'];
                }

                $latestVersion = $element->getLatestVersion();
            }

            // attributes
            // TODO: implement $teaser->getAttributes()

            if ($teaser) {
                $attributes = $teaser->getAttributes();
            } else {
                $attributes = $node->getAttributes();
            }

            // context
            // TODO: repair element context

            $context = array();
            if (0) {
                $contextManager = $this->get('phlexible_element.context.manager');

                if ($contextManager->useContext()) {
                    $contextCountries = $contextManager->getAllCountries();

                    $activeContextCountries = $teaserId
                        ? $contextManager->getActiveCountriesByTeaserId($teaserId)
                        : $contextManager->getActiveCountriesByTid($node->getId());

                    foreach ($contextCountries as $contextKey => $contextValue) {
                        $context[] = array(
                            'id'      => $contextKey,
                            'country' => $contextValue,
                            'active'  => in_array($contextKey, $activeContextCountries) ? 1 : 0
                        );
                    }
                }
            }

            // pager

            $pager = array();
            if (!$teaser) {
                $parentNode = $tree->getParent($node);
                if ($parentNode) {
                    $parentElement = $elementService->findElement($parentNode->getTypeId());
                    $parentElementtype = $elementService->findElementtype($parentElement);
                    if ($parentElementtype->getHideChildren()) {
                        $filter = new TreeFilter(
                            $this->get('doctrine.dbal.default_connection'),
                            $request->getSession(),
                            $this->get('event_dispatcher'),
                            $parentNode->getId(),
                            $language
                        );
                        $pager = $filter->getPager($node->getId());
                    }
                }
            }

            // rights

            $userRights = array();
            if ($node instanceof ContentObjectInterface) {
                if (!$securityContext->isGranted(Acl::RESOURCE_SUPERADMIN)) {
                    //$contentRightsManager->calculateRights('internal', $rightsNode, $rightsIdentifiers);

                    if ($securityContext->isGranted(array('right' => 'VIEW', 'language' => $language), $node)) {
                        return null;
                    }

                    $userRights = array(); //$contentRightsManager->getRights($language);
                    $userRights = array_keys($userRights);
                } else {
                    $userRights = array_keys(
                        $this->get('phlexible_access_control.permissions')->getByContentClass('Phlexible\Bundle\TreeBundle\Model\TreeNode')
                    );
                }
            }

            $status = '';
            if ($stateManager->isPublished($node, $language)) {
                $status = $stateManager->isAsync($node, $language) ? 'async' : 'online';
            }

            $icon = $iconResolver->resolveTreeNode($node, $language);

            $createUser = $this->get('phlexible_user.user_manager')->find($elementVersion->getCreateUserId());

            // glue together

            $properties = array(
                'tid'              => $treeId,
                'eid'              => $eid,
                'siteroot_id'      => empty($teaserId) ? $node->getTree()->getSiterootId() : null,
                'teaser_id'        => $teaserId,
                'language'         => $language,
                'version'          => $elementVersion->getVersion(),
                'is_published'     => $isPublished,
                'master'           => $language == $element->getMasterLanguage() ? true : false,
                'status'           => $status,
                'backend_title'    => substr(
                    strip_tags($elementVersion->getBackendTitle($language, $elementMasterLanguage)),
                    0,
                    30
                ),
                'page_title'       => substr(
                    strip_tags($elementVersion->getPageTitle($language, $elementMasterLanguage)),
                    0,
                    30
                ),
                'navigation_title' => substr(
                    strip_tags($elementVersion->getNavigationTitle($language, $elementMasterLanguage)),
                    0,
                    30
                ),
                'unique_id'        => $element->getUniqueID(),
                'et_id'            => $elementtype->getId(),
                'et_title'         => $elementtype->getTitle(),
                'et_version'       => $elementVersion->getElementTypeVersion()
                    . ' [' . $elementtypeService->findLatestElementtypeVersion($elementtype)->getVersion() . ']',
                'et_unique_id'     => $elementtype->getUniqueId(),
                'et_type'          => $elementtype->getType(),
                'author'           => $createUser->getDisplayName(),
                'create_date'      => $elementVersion->getCreatedAt()->format('Y-m-d H:i:s'),
                'publish_date'     => $publishDate,
                'publisher'        => $publishUser ? $publishUser->getDisplayName() : null,
                'latest_version'   => (int) $latestVersion,
                'online_version'   => (int) $onlineVersion,
                'masterlanguage'   => $elementMasterLanguage,
                'sort_mode'        => $node->getSortMode(),
                'sort_dir'         => $node->getSortDir(),
                'icon'             => $icon,
                'navigation'       => $node->getInNavigation(),
            );

            $elementtypeSerializer = new ElementtypeArraySerializer();
            $serializedStructure = $elementtypeSerializer->serialize($elementtypeStructure);

            $elementSerializer = new ElementArraySerializer();
            $serializedValues = $elementSerializer->serialize($elementStructure);

            $data = array(
                'success'             => true,
                'properties'          => $properties,
                'attributes'          => $attributes,
                'comment'             => $elementVersion->getComment(),
                'meta'                => $metaSetArray,
                'redirects'           => $redirects,
                'default_tab'         => $elementtype->getDefaultTab(),
                'default_content_tab' => $elementtypeVersion->getDefaultContentTab(),
                'lockinfo'            => $lockInfo,
                'diff'                => $diffInfo,
                'urls'                => $urls,
                'context'             => $context,
                'pager'               => $pager,
                'rights'              => $userRights,
                'instances'           => $instances,
                'children'            => $allowedChildren,
                'versions'            => $versions,
                'valueStructure'      => $serializedValues,
                'structure'           => $serializedStructure,
            );

            $data = (object) $data;
            $event = new LoadDataEvent($node, $teaser, $language, $data);
            $this->get('event_dispatcher')->dispatch(ElementEvents::LOAD_DATA, $event);
            $data = (array) $data;
        } catch (\Exception $e) {
            $data = array(
                'success' => false,
                'msg'     => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            );
            if ($e->getPrevious()) {
                $data['previous'] = array(
                    'msg'   => $e->getPrevious()->getMessage(),
                    'trace' => $e->getPrevious()->getTraceAsString(),
                );
            }
        }

        return new JsonResponse($data);
    }

    /**
     * Save element data
     *
     * @param Request $request
     *
     * @throws \Exception
     * @return ResultResponse
     * @Route("/save", name="elements_data_save")
     */
    public function saveAction(Request $request)
    {
        $tid = $request->get('tid');
        $teaserId = $request->get('teaser_id');
        $eid = $request->get('eid');
        $language = $request->get('language');
        $data = $request->get('data');
        $oldVersion = $request->get('version');
        $comment = $request->get('comment');
        $isPublish = $request->get('publish');
        $notifications = $request->get('notifications');
        $values = $request->request->all();

        unset($values['eid'],
        $values['language'],
        $values['version'],
        $values['data'],
        $values['comment'],
        $values['publish']);

        if ($data) {
            $data = json_decode($data, true);
        }

        $dispatcher = $this->get('event_dispatcher');
        $treeManager = $this->get('phlexible_tree.tree_manager');
        $teaserManager = $this->get('phlexible_teaser.teaser_manager');
        $elementService = $this->get('phlexible_element.element_service');
        $stateManager = $this->get('phlexible_tree.state_manager');

        $tree = $treeManager->getByNodeId($tid);
        $node = $tree->get($tid);
        $element = $elementService->findElement($eid);
        $oldElementVersion = $elementService->findLatestElementVersion($element);
        $oldLatestVersion = $oldElementVersion->getVersion();
        $isMaster = $element->getMasterLanguage() == $language;

        $teaser = null;
        if ($teaserId) {
            $teaser = $teaserManager->findTeaser($teaserId);
        }

        $event = new BeforeSaveElement($element, $language, $oldVersion);
        $dispatcher->dispatch($event);

        $comment = null;
        if (!empty($data['comment'])) {
            $comment = $data['comment'];
        }

        $publishSlaveLanguages = array();
        $publishSlaves = array();

        if ($isPublish) {
            if ($element->getMasterLanguage() == $language) {
                foreach ($this->container->getParameter('phlexible_cms.languages.available') as $slaveLanguage) {
                    if ($language == $slaveLanguage) {
                        continue;
                    }

                    if ($teaser) {
                        if ($teaser->isPublished($slaveLanguage)) {
                            if (!$teaser->isAsync($slaveLanguage)) {
                                $publishSlaveLanguages[] = $slaveLanguage;
                            }
                        } else {
                            if ($this->container->getParameter(
                                'phlexible_element.publish.cross_language_publish_offline'
                            )
                            ) {
                                $publishSlaves[] = array($teaser->getId(), $slaveLanguage, 0, '', 0);
                            }
                        }
                    } else {
                        if ($stateManager->isPublished($node, $slaveLanguage)) {
                            if (!$stateManager->isAsync($node, $slaveLanguage)) {
                                $publishSlaveLanguages[] = $slaveLanguage;
                            } else {
                                $publishSlaves[] = array($node->getId(), $slaveLanguage, 0, 'async', 1);
                            }
                        } else {
                            if ($this->container->getParameter('phlexible_element.publish.cross_language_publish_offline')) {
                                $publishSlaves[] = array($node->getId(), $slaveLanguage, 0, '', 0);
                            }
                        }
                    }
                }
            }
        }

        $minor = 0;
        $elementVersion = $element->createVersion(null, $comment, $minor, $language);
        $newVersion = $elementVersion->getVersion();
        $elementTypeVersionObj = $elementVersion->getElementTypeVersionObj();

        foreach ($publishSlaves as $publishSlaveKey => $publishSlaveRow) {
            $publishSlaves[$publishSlaveKey][2] = $newVersion;
        }

        /*
        Makeweb_Elements_Element_History::insert(Makeweb_Elements_Element_History::ACTION_CREATE_VERSION,
                                                 $elementVersion->getEid(),
                                                 $elementVersion->getVersion(),
                                                 $language,
                                                 $comment);
        */

        // Copy tree item page values from old version to new version in all instances

        if ($teaser) {

        } else {
            foreach ($tree->getInstances($node) as $instanceNode) {
                $instanceNode->getTree()->copyPage($treeId, $newVersion, $oldElementVersion->getVersion());
            }
        }

        // Copy meta values from old version to new version

        $setId = $elementTypeVersionObj->getMetaSetId();
        if ($setId) {
            $select = $db
                ->select()
                ->from($db->prefix . 'element_version_metaset_items')
                ->where('set_id = ?', $setId)
                ->where('eid = ?', $eid)
                ->where('version = ?', $oldLatestVersion);

            foreach ($db->fetchAll($select) as $insertData) {
                unset($insertData['id']);
                $insertData['version'] = $newVersion;

                $db->insert($db->prefix . 'element_version_metaset_items', $insertData);
            }
        }

        $elementData = $elementVersion->getData($language);

        if ($isMaster) {
            $elementData->saveData($elementVersion, $values, $oldLatestVersion);
        } else {
            $elementData->saveData($elementVersion, $values, $oldLatestVersion, $element->getMasterLanguage());
        }

        if (!$teaser) {
            $elementVersion->getBackendTitle($language);

            $select = $db
                ->select()
                ->distinct()
                ->from($db->prefix . 'element_tree', 'parent_id')
                ->where('eid = ?', $eid);

            $updateTids = $db->fetchCol($select);

            $parentNode = $node->getParentNode();
            if ($parentNode && $parentNode->getSortMode() != Makeweb_Elements_Tree::SORT_MODE_FREE) {
                foreach ($updateTids as $updateTid) {
                    if (!$updateTid) {
                        continue;
                    }

                    $sorter = $container->get('elementsTreeSorter');
                    $sorter->sortNode($parentNode);
                }
            }
        }

        // accordion stuff

        if (empty($teaserId)) {
            $event = new BeforeUpdateNode($node);
            $dispatcher->dispatch($event);

            // save config

            if (!empty($data['config'])) {
                $page = $node->getPage($oldVersion);

                $page['navigation'] = !empty($data['page']['navigation']) ? 1 : 0;
                $page['restricted'] = !empty($data['page']['restricted']) ? 1 : 0;
                if (array_key_exists('code', $data['page'])) {
                    $page['disable_cache'] = !empty($data['page']['disable_cache']) ? 1 : 0;
                    $page['cache_lifetime'] = (int) Brainbits_Util_Array::get($data['page'], 'cache_lifetime', 0);
                    $page['https'] = !empty($data['page']['https']) ? 1 : 0;
                    $page['code'] = !empty($data['page']['code']) ? $data['page']['code'] : 200;
                }

                $tree = $node->getTree();

                $tree->setPage(
                    $node->getId(),
                    $newVersion,
                    !empty($page['navigation']) ? 1 : 0,
                    !empty($page['restricted']) ? 1 : 0,
                    !empty($page['disable_cache']) ? 1 : 0,
                    !empty($page['code']) ? $page['code'] : 200,
                    !empty($page['https']) ? 1 : 0,
                    (int) Brainbits_Util_Array::get($page, 'cache_lifetime', 0)
                );

                //$tree->getNodeOnlineVersions($node->getId(), false);
                //$identifier = new Makeweb_Elements_Tree_Identifier($node->getTree()->getSiteRootId());
                //MWF_Core_Cache_Manager::remove($identifier);
                //$treeManager->getBySiteRootId($node->getTree()->getSiteRootId(), true);
            }

            // save meta

            if (!empty($data['meta'])) {
                $metaSetId = $elementVersion->getElementTypeVersionObj()->getMetaSetId();
                /* @var $metaSet Media_MetaSets_Set */
                $metaSet = $container->get('metasets.repository')->find($metaSetId);

                $identifier = new Makeweb_Elements_Element_Version_MetaSet_Identifier($elementVersion, $language);
                /* @var $metaSetItem Media_MetaSets_Item */
                $metaSetItem = Media_MetaSets_Item_Peer::get($metaSetId, $identifier);
                $metaSetArray = $metaSetItem->toArray($language);

                foreach ($data['meta'] as $key => $value) {
                    if (!$isMaster && $metaSetArray[$key]['synchronized']) {
                        if ($metaSetItem->$key === null) {
                            $metaSetItem->$key = '';
                        }

                        continue;
                    }

                    if ('suggest' === $metaSetItem->getType($key)) {
                        $dataSourceId = $metaSetItem->getOptions($key);
                        $dataSourcesRepository = $container->get('datasources.repository');
                        $dataSource = $dataSourcesRepository->getDataSourceById($dataSourceId, $language);
                        $dataSourceKeys = $dataSource->getKeys();
                        $dataSourceModified = false;
                        foreach (explode(',', $value) as $singleValue) {
                            if (!in_array($singleValue, $dataSourceKeys)) {
                                $dataSource->addKey($singleValue, true);
                                $dataSourceModified = true;
                            }
                        }
                        if ($dataSourceModified) {
                            $dataSourcesRepository->save($dataSource, $this->getUser()->getId());
                        }
                    }

                    $metaSetItem->$key = $value ? $value : '';
                }

                $metaSetItem->save();

                if ($isMaster) {
                    $slaveLanguages = $container->getParameter('frontend.languages.available');
                    unset($slaveLanguages[array_search($language, $slaveLanguages)]);

                    foreach ($slaveLanguages as $slaveLanguage) {
                        $identifier = new Makeweb_Elements_Element_Version_MetaSet_Identifier($elementVersion, $slaveLanguage);
                        /* @var $metaSetItem Media_MetaSets_Item */
                        $metaSetItem = Media_MetaSets_Item_Peer::get($metaSetId, $identifier);

                        foreach ($data['meta'] as $key => $value) {
                            if (!$metaSetArray[$key]['synchronized'] && $metaSetItem->$key) {
                                continue;
                            } elseif (!$metaSetArray[$key]['synchronized'] && !$metaSetItem->$key) {
                                $metaSetItem->$key = '';
                            } else {
                                $metaSetItem->$key = $value ? $value : '';
                            }
                        }

                        $metaSetItem->save();
                    }
                }
            }

            // save context

            if (isset($data['context'])) {
                $db->delete($db->prefix . 'element_tree_context', array('tid = ?' => $tid));

                $insertData = array(
                    'tid' => $tid
                );

                foreach ($data['context'] as $country) {
                    $insertData['context'] = $country;

                    $db->insert($db->prefix . 'element_tree_context', $insertData);
                }
            }

            $event = new SaveNodeData($node, $language, $data);
            $dispatcher->dispatch($event);

            $event = new UpdateNode($node);
            $dispatcher->dispatch($event);
        } else {
            $beforeUpdateTeaserEvent = new BeforeUpdateTeaser($teaser, $language, $data);
            $dispatcher->dispatch($beforeUpdateTeaserEvent);

            // save teaser

            if (!empty($data['teaser'])) {
                $updateData = array(
                    'disable_cache'  => empty($data['teaser']['disable_cache']) ? 0 : 1,
                    'cache_lifetime' => (int) Brainbits_Util_Array::get($data['teaser'], 'cache_lifetime', 0),
                );

                if ($updateData['disable_cache']) {
                    $updateData['cache_lifetime'] = 0;
                }

                $db->update($db->prefix . 'element_tree_teasers', $updateData, 'id = ' . $db->quote($teaserId));
            }

            // save context

            if (isset($data['context'])) {
                $db->delete($db->prefix . 'element_tree_teasers_context', array('teaser_id = ?' => $teaserId));

                $insertData = array(
                    'teaser_id' => $teaserId
                );

                foreach ($data['context'] as $country) {
                    $insertData['context'] = $country;

                    $db->insert($db->prefix . 'element_tree_teasers_context', $insertData);
                }
            }

            $event = new UpdateTeaser($teaser, $language, $data);
            $dispatcher->dispatch($event);
        }

        $event = new SaveElement($elementVersion, $language, $oldVersion);
        $dispatcher->dispatch($event);

        $msg = 'Element "' . $eid . '" master language "' . $language . '" saved as new version ' . $newVersion;

        $publishOther = array();
        if ($isPublish) {
            $msg .= ' and published.';

            if (!$teaser) {
                $node = $treeManager->getNodeByNodeId($tid);
                $tree = $node->getTree();

                // notification data
                $notificationManager = $container->get('elementsNotifications');
                $checkNotify = $notificationManager->getNotificationByTid($tid, $language);

                // check if there is a notification already
                if (count($checkNotify) && $notifications) {
                    $notificationId = $checkNotify[0]['id'];
                    $notificationManager->update($notificationId, $language);
                } else {
                    if ($notifications) {
                        $notificationManager->save($tid, $language);
                    }
                }

                // publish node
                $tree->publishNode(
                    $node,
                    $language,
                    $newVersion,
                    false,
                    $comment
                );

                if (count($publishSlaveLanguages)) {
                    foreach ($publishSlaveLanguages as $slaveLanguage) {
                        // publish slave node
                        $tree->publishNode(
                            $node,
                            $slaveLanguage,
                            $newVersion,
                            false,
                            $comment
                        );

                        // workaround to fix missing catch results for non master language elements
                        Makeweb_Elements_Element_History::insert(
                            Makeweb_Elements_Element_History::ACTION_SAVE,
                            $eid,
                            $newVersion,
                            $slaveLanguage
                        );
                    }
                }
            } else {
                $tree = $node->getTree();

                $eid = $teasersManager->publish(
                    $teaserId,
                    $newVersion,
                    $language,
                    $comment,
                    $tid
                );

                if (count($publishSlaveLanguages)) {
                    foreach ($publishSlaveLanguages as $slaveLanguage) {
                        // publish slave node
                        $teasersManager->publish(
                            $teaserId,
                            $newVersion,
                            $slaveLanguage,
                            $comment,
                            $tid
                        );
                    }
                }
            }
        } else {
            $msg .= '.';
        }

        $lockService = $container->get('phlexible_element.lock.service');
        $lockService->unlockElement($element, $language);

        $queueService = $container->get('queue.service');

        $updateUsageJob = new Makeweb_Elements_Job_UpdateUsage();
        $updateUsageJob->setEid($eid);
        $queueService->addUniqueJob($updateUsageJob);


        //            $updateCatchHelperJob = new Makeweb_Teasers_Job_UpdateCatchHelper();
        //            $updateUsageJob->setEid($eid);
        //            $queueManager->addJob($updateCatchHelperJob);

        //$fileUsage = new Makeweb_Elements_Element_FileUsage(MWF_Registry::getContainer()->dbPool);
        //$fileUsage->update($eid);

        $data = array();

        $status = '';
        if ($node->isPublished($language)) {
            $status = $node->isAsync($language) ? 'async' : 'online';
        }

        $data = array(
            'title'         => $elementVersion->getBackendTitle($language),
            'status'        => $status,
            'navigation'    => $teaserId ? '' : $node->inNavigation($newVersion),
            'restricted'    => $teaserId ? '' : $node->isRestricted($newVersion),
            'publish_other' => $publishSlaves,
        );

        return new ResultResponse(true, $msg, $data);
    }
}
