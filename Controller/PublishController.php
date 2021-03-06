<?php

/*
 * This file is part of the phlexible package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\ElementBundle\Controller;

use Phlexible\Bundle\ElementBundle\Element\Publish\Selection;
use Phlexible\Bundle\ElementBundle\Exception\RuntimeException;
use Phlexible\Bundle\GuiBundle\Response\ResultResponse;
use Phlexible\Bundle\TreeBundle\Model\TreeNodeInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\LockHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Publish controller.
 *
 * @author Stephan Wentz <sw@brainbits.net>
 * @Route("/elements/publish")
 * @Security("is_granted('ROLE_ELEMENTS')")
 */
class PublishController extends Controller
{
    /**
     * @param Request $request
     *
     * @return ResultResponse
     * @Route("", name="elements_publish")
     */
    public function publishAction(Request $request)
    {
        $tid = $request->get('tid');
        $teaserId = $request->get('teaser_id', null);
        $version = $request->get('version', null);
        $language = $request->get('language');
        $comment = $request->get('comment', '');

        //$fileUsage = new Makeweb_Elements_Element_FileUsage(MWF_Registry::getContainer()->dbPool);

        if (!$teaserId) {
            $treeManager = $this->get('phlexible_tree.tree_manager');
            $tree = $treeManager->getByNodeId($tid);
            $node = $tree->get($tid);

            $tree->publish($node, $version, $language, $this->getUser()->getId(), $comment);

            #$eid = $node->getEid();
            //$fileUsage->update($node->getEid());

            $data = [];

            #$elementVersionManager = Makeweb_Elements_Element_Version_Manager::getInstance();
            #$elementVersion = $elementVersionManager->get($node->getEid(), $version);

            $data['status'] = $tree->isAsync($node, $language) ? 'async' : ($tree->isPublished($node, $language) ? 'online' : null);
            $data['instance'] = ($tree->isInstance($node) ? ($tree->isInstanceMaster($node) ? 'master' : 'slave') : false);
            #$data['icon'] = $elementVersion->getIconUrl($node->getIconParams($language));

            $response = new ResultResponse(true, 'TID "'.$tid.'" published.', $data);
        } else {
            $teaserManager = $this->get('phlexible_teaser.teaser_manager');
            $teaser = $teaserManager->find($teaserId);

            $eid = $teaserManager->publishTeaser($teaser, $version, $language, $this->getUser()->getId(), $comment);

            //$fileUsage->update($eid);

            $response = new ResultResponse(true, "Teaser ID $teaserId published.");
        }

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     * @Route("/preview", name="elements_publish_preview")
     */
    public function previewAction(Request $request)
    {
        $tid = $request->get('tid');
        $teaserId = $request->get('teaser_id', null);
        $language = $request->get('language');
        $languages = $request->get('languages');
        $version = $request->get('version', null);
        $includeElements = (bool) $request->get('include_elements', false);
        $includeElementInstances = (bool) $request->get('include_element_instances', false);
        $includeTeasers = (bool) $request->get('include_teasers', false);
        $includeTeaserInstances = (bool) $request->get('include_teaser_instances', false);
        $recursive = (bool) $request->get('recursive', false);
        $onlyOffline = (bool) $request->get('only_offline', false);
        $onlyAsync = (bool) $request->get('only_async', false);

        if ($languages) {
            $languages = explode(',', $languages);
        } else {
            $languages = [$language];
        }

        $selector = $this->get('phlexible_element.publish.selector');
        $iconResolver = $this->get('phlexible_element.icon_resolver');

        $selection = new Selection();
        foreach ($languages as $language) {
            $langSelection = $selector->select(
                $tid,
                $language,
                $version,
                $includeElements,
                $includeElementInstances,
                $includeTeasers,
                $includeTeaserInstances,
                $recursive,
                $onlyOffline,
                $onlyAsync
            );
            $selection->merge($langSelection);
        }

        $result = [];
        foreach ($selection->all() as $selectionItem) {
            if ($selectionItem->getTarget() instanceof TreeNodeInterface) {
                $id = $selectionItem->getTarget()->getId();
                $icon = $iconResolver->resolveTreeNode($selectionItem->getTarget(), $selectionItem->getLanguage());
            } else {
                $id = $selectionItem->getTarget()->getId();
                $icon = $iconResolver->resolveTeaser($selectionItem->getTarget(), $selectionItem->getLanguage());
            }

            $result[] = [
                'type' => $selectionItem->getTarget() instanceof TreeNodeInterface ? 'full_element' : 'part_element',
                'instance' => $selectionItem->isInstance(),
                'depth' => $selectionItem->getDepth(),
                'path' => $selectionItem->getPath(),
                'id' => $id,
                'eid' => $selectionItem->getTarget()->getTypeId(),
                'version' => $selectionItem->getVersion(),
                'language' => $selectionItem->getLanguage(),
                'title' => $selectionItem->getTitle(),
                'icon' => $icon,
                'action' => true,
            ];
        }

        return new JsonResponse(['preview' => $result]);
    }

    /**
     * @param Request $request
     *
     * @return ResultResponse
     *
     * @throws RuntimeException
     * @Route("/advanced", name="elements_publish_advanced")
     */
    public function advancedPublishAction(Request $request)
    {
        $tid = $request->get('tid');
        $version = $request->get('version');
        $language = $request->get('language');
        $comment = $request->get('comment');
        $data = $request->get('data');
        $data = json_decode($data, true);

        $lock = new LockHandler('elements_publish_lock', $this->container->getParameter('app.lock_dir'));
        if (!$lock->lock()) {
            throw new RuntimeException('Another advanced publish running.');
        }

        $treeManager = $this->get('phlexible_tree.tree_manager');
        $teaserManager = $this->get('phlexible_teaser.teaser_manager');
        $iconResolver = $this->get('phlexible_element.icon_resolver');

        foreach ($data as $row) {
            set_time_limit(15);
            if ($row['type'] === 'full_element') {
                $tree = $treeManager->getByNodeId($row['id']);
                $treeNode = $tree->get($row['id']);

                $tree->publish($treeNode, $row['version'], $row['language'], $this->getUser()->getId(), $comment);
            } elseif ($row['type'] === 'part_element') {
                $teaser = $teaserManager->find($row['id']);

                $teaserManager->publishTeaser($teaser, $row['version'], $row['language'], $this->getUser()->getId(), $comment);
            } else {
                continue;
            }

            // TODO: update usage
            /*
            $job = new Makeweb_Elements_Job_UpdateUsage();
            $job->setEid($eid);
            $queueService->addUniqueJob($job);
            */
        }

        $data = [];

        $tree = $treeManager->getByNodeId($tid);
        $treeNode = $tree->get($tid);

        $data = [
            'tid' => $tid,
            'language' => $language,
            'icon' => $iconResolver->resolveTreeNode($treeNode, $language),
        ];

        $lock->release();

        return new ResultResponse(true, 'Successfully published.', $data);
    }

    /**
     * @param Request $request
     *
     * @return ResultResponse
     * @Route("/setoffline", name="elements_publish_setoffline")
     */
    public function setOfflineAction(Request $request)
    {
        $tid = $request->get('tid');
        $teaserId = $request->get('teaser_id', null);
        $language = $request->get('language');
        $comment = $request->get('comment', null);

        //$fileUsage = new Makeweb_Elements_Element_FileUsage(MWF_Registry::getContainer()->dbPool);

        if (!$teaserId) {
            $treeManager = $this->get('phlexible_tree.tree_manager');
            $tree = $treeManager->getByNodeId($tid);
            $node = $tree->get($tid);

            $tree->setOffline($node, $language, $this->getUser()->getId(), $comment);

            //$eid = $node->getEid();
            //$fileUsage->update($node->getEid());

            $response = new ResultResponse(true, "TID $tid set offline.");
        } else {
            $teaserManager = $this->get('phlexible_teaser.teaser_manager');

            $teaser = $teaserManager->find($teaserId);
            $teaserManager->setTeaserOffline($teaser, $language, $this->getUser()->getId(), $comment);

            //$fileUsage->update($eid);

            /*
            Makeweb_Teasers_History::insert(
                Makeweb_Teasers_History::ACTION_SET_OFFLINE, $teaserId, $eid, null, $language, $comment
            );
            */

            $response = new ResultResponse(true, "Teaser ID $teaserId set offline.");
        }

        /*
        $queueManager = MWF_Core_Queue_Manager::getInstance();
        $job = new Makeweb_Elements_Job_UpdateUsage();
        $job->setEid($eid);
        $queueManager->addUniqueJob($job);
        */

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return ResultResponse
     * @Route("/setoffline/recursive", name="elements_publish_setoffline_recursive")
     */
    public function setofflinerecursiveAction(Request $request)
    {
        $tid = $request->get('tid');
        $teaserId = $request->get('teaser_id', null);
        $language = $request->get('language');
        $comment = $request->get('comment', '');

        if ($teaserId === null) {
            $manager = Makeweb_Elements_Tree_Manager::getInstance();
            $node = $manager->getNodeByNodeId($tid);
            $tree = $node->getTree();

            $tree->setNodeOffline($node, $language, true, $comment);
        } else {
        }

        return new ResultResponse(true, 'TID "'.$tid.'" set offline recursively.');
    }
}
