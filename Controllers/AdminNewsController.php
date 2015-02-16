<?php

namespace Themes\Blog\Controllers;

//use Themes\Rozier\RozierApp;
use Themes\Rozier\Controllers\NodesController;
use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\Translation;
use RZ\Roadiz\Core\ListManagers\EntityListManager;
use RZ\Roadiz\Utils\StringHandler;

use RZ\Roadiz\Core\Exceptions\EntityAlreadyExistsException;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Validator\Constraints\NotBlank;

class AdminNewsController extends NodesController
{

    public function listAction(
        Request $request
    ) {
        $newsType = $this->getService('nodeTypeApi')->getOneBy(array("name" => "News"));

        $listManager = new EntityListManager(
            $request,
            $this->getService("em"),
            "RZ\Roadiz\Core\Entities\Node",
            array(
              "nodeType" => $newsType
            ),
            array(
              "updatedAt" => "DESC"
            )
        );

        $listManager->handle();

        $this->assignation['filters'] = $listManager->getAssignation();
        $this->assignation['news'] = $listManager->getEntities();

        //$this->getService('stopwatch')->start('twigRender');

        return new Response(
            $this->getTwig()->render('admin/list.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
    }

    /**
     * Handle news creation pages.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $translationId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request, $translationId = null)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS');

        $translation = $this->getService('em')
            ->getRepository('RZ\Roadiz\Core\Entities\Translation')
            ->findDefault();

        if ($translationId !== null) {
            $translation = $this->getService('em')
                ->find('RZ\Roadiz\Core\Entities\Translation', (int) $translationId);
        }

        if ($translation !== null) {
            $form = $this->getService('formFactory')
                ->createBuilder()
                ->add(
                    'title',
                    'text',
                    array(
                        'label' => $this->getTranslator()->trans('title'),
                        'constraints' => array(
                            new NotBlank()
                        )
                    )
                )
                ->add(
                    'content',
                    new \RZ\Roadiz\CMS\Forms\MarkdownType(),
                    array(
                        'label' => $this->getTranslator()->trans("content"),
                        'constraints' => array(
                            new NotBlank()
                        )
                    )
                )
                ->add(
                    'excerpt',
                    'text',
                    array(
                        'label' => $this->getTranslator()->trans("excerpt"),
                        'required' => false
                    )
                )
                ->add(
                    'tags',
                    'text',
                    array(
                        'label' => $this->getTranslator()->trans("tags"),
                        'required' => false,
                        'attr' => array('class' => "rz-tag-autocomplete")
                    )
                )
                ->getForm();
            $form->handleRequest();

            if ($form->isValid()) {
                try {
                    $news = $this->createNews($form->getData(), $translation);

                    $msg = $this->getTranslator()->trans(
                        'news.%name%.created',
                        array('%name%'=>$news->getNodeName())
                    );
                    $this->publishConfirmMessage($request, $msg);

                    $response = new RedirectResponse(
                        $this->getService('urlGenerator')->generate(
                            'newsAdminListPage'
                        )
                    );
                    $response->prepare($request);

                    return $response->send();
                } catch (EntityAlreadyExistsException $e) {
                    $this->publishErrorMessage($request, $e->getMessage());

                    $response = new RedirectResponse(
                        $this->getService('urlGenerator')->generate(
                            'newsAdminAddPage',
                            array('translationId' => $translationId)
                        )
                    );
                    $response->prepare($request);

                    return $response->send();
                }
            }

            $this->assignation['translation'] = $translation;
            $this->assignation['form'] = $form->createView();

            return new Response(
                $this->getTwig()->render('admin/add.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }

    public function editAction(Request $request, $newsId, $translationId = null)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS');



        $translation = $this->getService('em')
            ->getRepository('RZ\Roadiz\Core\Entities\Translation')
            ->findDefault();

        if ($translationId !== null) {
            $translation = $this->getService('em')
                ->find('RZ\Roadiz\Core\Entities\Translation', (int) $translationId);
        }

        if ($newsId !== null) {
            $type = $this->getService('nodeTypeApi')->getOneBy(array("name" => "News"));
            $news = $this->getService("nodeSourceApi")->getOneBy(array("node" => $newsId, "translation" => $translation, "node.nodeType" => $type));
        }

        if ($news === null) {
            $tmp = $this->getService('em')
                ->getRepository('RZ\Roadiz\Core\Entities\Translation')
                ->findDefault();

            $tmpnews = $this->getService("nodeSourceApi")->getOneBy(array("node" => $newsId, "translation" => $tmp, "node.nodeType" => $type));

            $sourceClass = "GeneratedNodeSources\\".$type->getSourceEntityClassName();

            $news = new $sourceClass($tmpnews->getNode(), $translation);

            //exit();

            $news->setContent($tmpnews->getContent());
            $news->setExcerpt($tmpnews->getExcerpt());
            $news->setDate($tmpnews->getDate());
            $news->setTitle($tmpnews->getTitle());

            $this->getService('em')->persist($news);
            $this->getService('em')->flush();
        }
        // var_dump($news);
        // exit;
        if ($translation !== null) {
            $tags = array_map(function ($tag) {
                return $tag->getTagName();
            }, $news->getNode()->getTags()->toArray());

            $form = $this->getService('formFactory')
                ->createBuilder()
                ->add(
                    'title',
                    'text',
                    array(
                        'data' => $news->getTitle(),
                        'label' => $this->getTranslator()->trans('title'),
                        'constraints' => array(
                            new NotBlank()
                        )
                    )
                )
                ->add(
                    'content',
                    new \RZ\Roadiz\CMS\Forms\MarkdownType(),
                    array(
                        'data' => $news->getContent(),
                        'label' => $this->getTranslator()->trans("content"),
                        'constraints' => array(
                            new NotBlank()
                        )
                    )
                )
                ->add(
                    'excerpt',
                    'text',
                    array(
                        'data' => $news->getExcerpt(),
                        'label' => $this->getTranslator()->trans("excerpt"),
                        'required' => false
                    )
                )
                ->add(
                    'tags',
                    'text',
                    array(
                        'data' => implode(",", $tags),
                        'label' => $this->getTranslator()->trans("tags"),
                        'required' => false,
                        'attr' => array('class' => "rz-tag-autocomplete")
                    )
                )
                ->getForm();
            $form->handleRequest();

            if ($form->isValid()) {
                $data = $form->getData();

                $news->getNode()->getTags()->clear();

                $news->setTitle($data['title']);
                $news->setContent($data['content']);
                $news->setExcerpt($data['excerpt']);


                if (!empty($data['tags'])) {
                    $paths = explode(',', $data['tags']);
                    $paths = array_filter($paths);

                    foreach ($paths as $path) {
                        $tag = $this->getService('em')
                                    ->getRepository('RZ\Roadiz\Core\Entities\Tag')
                                    ->findOrCreateByPath($path);

                        $news->getNode()->addTag($tag);
                    }
                }

                $this->getService('em')->flush();

                $msg = $this->getTranslator()->trans(
                    'news.%name%.updated',
                    array('%name%'=>$news->getTitle())
                );

                $this->publishConfirmMessage($request, $msg);

                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate(
                        'newsAdminListPage'
                    )
                );
                $response->prepare($request);

                return $response->send();
            }

            $this->assignation['current'] = $translation;
            $this->assignation['form'] = $form->createView();
            $this->assignation['available_translations'] = $this->getService('em')
                ->getRepository('RZ\Roadiz\Core\Entities\Translation')->findAll();
            $this->assignation['news'] = $news->getNode();

            return new Response(
                $this->getTwig()->render('admin/edit.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }

    /**
     * @param array                              $data
     * @param RZ\Roadiz\Core\Entities\NodeType    $type
     * @param RZ\Roadiz\Core\Entities\Translation $translation
     *
     * @return RZ\Roadiz\Core\Entities\Node
     */
    private function createNews($data, Translation $translation)
    {
        if ($this->urlAliasExists(StringHandler::slugify($data['title']))) {
            $msg = $this->getTranslator()->trans(
                'news.%name%.no_creation.urlAlias.alreadyExists',
                array('%name%'=>$data['title'])
            );

            throw new EntityAlreadyExistsException($msg, 1);
        }
        if ($this->nodeNameExists(StringHandler::slugify($data['title']))) {
            $msg = $this->getTranslator()->trans(
                'news.%name%.no_creation.already_exists',
                array('%name%'=>$data['nodeName'])
            );

            throw new EntityAlreadyExistsException($msg, 1);
        }

        try {
            $type = $this->getService('nodeTypeApi')->getOneBy(array("name" => "News"));

            $parent = $this->getService('nodeApi')->getOneBy(array("nodeName" => "news", "parent" => null));

            $node = new Node($type);

            $node->setNodeName($data['title']);
            $node->setParent($parent);

            if (!empty($data['tags'])) {
                $paths = explode(',', $data['tags']);
                $paths = array_filter($paths);

                foreach ($paths as $path) {
                    $tag = $this->getService('em')
                                ->getRepository('RZ\Roadiz\Core\Entities\Tag')
                                ->findOrCreateByPath($path);

                    $node->addTag($tag);
                }
            }

            $this->getService('em')->persist($node);

            $sourceClass = "GeneratedNodeSources\\".$type->getSourceEntityClassName();

            $source = new $sourceClass($node, $translation);

            $source->setTitle($data['title']);
            $source->setContent($data['content']);
            $source->setExcerpt($data['excerpt']);
            $source->setDate(new \DateTime('now'));

            $this->getService('em')->persist($source);
            $this->getService('em')->flush();

            return $node;
        } catch (\Exception $e) {
            $msg = $this->getTranslator()->trans(
                'news.%name%.noCreation.alreadyExists',
                array('%name%'=>$node->getNodeName())
            );
            throw new EntityAlreadyExistsException($msg, 1);
        }
    }

    /**
     * Return an deletion form for requested node.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $nodeId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request, $nodeId)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS_DELETE');

        $node = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Node', (int) $nodeId);

        if (null !== $node &&
            !$node->isDeleted() &&
            !$node->isLocked()) {
            $this->assignation['node'] = $node;

            $form = $this->buildDeleteForm($node);
            $form->handleRequest();

            if ($form->isValid() &&
                $form->getData()['nodeId'] == $node->getId()) {
                $node->getHandler()->softRemoveWithChildren();
                $this->getService('em')->flush();

                // Update Solr Search engine if setup
                if (true === $this->getKernel()->pingSolrServer()) {
                    foreach ($node->getNodeSources() as $nodeSource) {
                        $solrSource = new \RZ\Roadiz\Core\SearchEngine\SolariumNodeSource(
                            $nodeSource,
                            $this->getService('solr')
                        );
                        $solrSource->getDocumentFromIndex();
                        $solrSource->updateAndCommit();
                    }
                }

                $msg = $this->getTranslator()->trans(
                    'news.%name%.deleted',
                    array('%name%'=>$node->getNodeName())
                );
                $this->publishConfirmMessage($request, $msg);
                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate('newsAdminListPage')
                );
                $response->prepare($request);

                return $response->send();
            }

            $this->assignation['form'] = $form->createView();

            return new Response(
                $this->getTwig()->render('nodes/delete.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }


    public function emptyTrashAction(Request $request)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS_DELETE');

        $form = $this->buildEmptyTrashForm();
        $form->handleRequest();

        if ($form->isValid()) {
            $nodes = $this->getService('em')
                          ->getRepository('RZ\Roadiz\Core\Entities\Node')
                          ->findBy(
                              array(
                                'status' => Node::DELETED,
                                'nodeType' => $this->getService("nodeTypeApi")->getOneBy(
                                    array("name" => "News")
                                )
                              )
                          );

            foreach ($nodes as $node) {
                $node->getHandler()->removeWithChildrenAndAssociations();
            }

            $msg = $this->getTranslator()->trans('news.trash.emptied');
            $this->publishConfirmMessage($request, $msg);

            $response = new RedirectResponse(
                $this->getService('urlGenerator')->generate('newsAdminListPage')
            );
            $response->prepare($request);

            return $response->send();
        }

        $this->assignation['form'] = $form->createView();

        return new Response(
            $this->getTwig()->render('nodes/emptyTrash.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
    }

    /**
     * Return an deletion form for requested node.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $nodeId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function undeleteAction(Request $request, $nodeId)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS_DELETE');

        $node = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Node', (int) $nodeId);

        if (null !== $node &&
            $node->isDeleted()) {
            $this->assignation['node'] = $node;

            $form = $this->buildDeleteForm($node);
            $form->handleRequest();

            if ($form->isValid() &&
                $form->getData()['nodeId'] == $node->getId()) {
                $node->getHandler()->softUnremoveWithChildren();
                $this->getService('em')->flush();

                // Update Solr Search engine if setup
                if (true === $this->getKernel()->pingSolrServer()) {
                    foreach ($node->getNodeSources() as $nodeSource) {
                        $solrSource = new \RZ\Roadiz\Core\SearchEngine\SolariumNodeSource(
                            $nodeSource,
                            $this->getService('solr')
                        );
                        $solrSource->getDocumentFromIndex();
                        $solrSource->updateAndCommit();
                    }
                }

                $msg = $this->getTranslator()->trans(
                    'news.%name%.undeleted',
                    array('%name%'=>$node->getNodeName())
                );
                $this->publishConfirmMessage($request, $msg);
                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate('newsAdminListPage')
                );
                $response->prepare($request);

                return $response->send();
            }

            $this->assignation['form'] = $form->createView();

            return new Response(
                $this->getTwig()->render('nodes/undelete.html.twig', $this->assignation),
                Response::HTTP_OK,
                array('content-type' => 'text/html')
            );
        } else {
            return $this->throw404();
        }
    }


    /**
     * Return an deletion form for requested node.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $nodeId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function publishAction(Request $request, $nodeId)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS_STATUS');

        $node = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Node', (int) $nodeId);

        if (null !== $node &&
            !$node->isPublished()) {
            $this->assignation['node'] = $node;

            $node->setPublished(true);
            $this->getService('em')->flush();

            // Update Solr Search engine if setup
            if (true === $this->getKernel()->pingSolrServer()) {
                foreach ($node->getNodeSources() as $nodeSource) {
                    $solrSource = new \RZ\Roadiz\Core\SearchEngine\SolariumNodeSource(
                        $nodeSource,
                        $this->getService('solr')
                    );
                    $solrSource->getDocumentFromIndex();
                    $solrSource->updateAndCommit();
                }
            }

            $msg = $this->getTranslator()->trans(
                'news.%name%.published',
                array('%name%'=>$node->getNodeName())
            );
            $this->publishConfirmMessage($request, $msg);
            /*
             * Force redirect to avoid resending form when refreshing page
             */
            $response = new RedirectResponse(
                $this->getService('urlGenerator')->generate('newsAdminListPage')
            );
            $response->prepare($request);

            return $response->send();

        } else {
            return $this->throw404();
        }
    }

    /**
     * Return an deletion form for requested node.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $nodeId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function unpublishAction(Request $request, $nodeId)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS_STATUS');

        $node = $this->getService('em')
            ->find('RZ\Roadiz\Core\Entities\Node', (int) $nodeId);

        if (null !== $node &&
            $node->isPublished()) {
            $this->assignation['node'] = $node;

            $node->setPublished(false);
            $this->getService('em')->flush();

            // Update Solr Search engine if setup
            if (true === $this->getKernel()->pingSolrServer()) {
                foreach ($node->getNodeSources() as $nodeSource) {
                    $solrSource = new \RZ\Roadiz\Core\SearchEngine\SolariumNodeSource(
                        $nodeSource,
                        $this->getService('solr')
                    );
                    $solrSource->getDocumentFromIndex();
                    $solrSource->updateAndCommit();
                }
            }

            $msg = $this->getTranslator()->trans(
                'news.%name%.unpublished',
                array('%name%'=>$node->getNodeName())
            );
            $this->publishConfirmMessage($request, $msg);
            /*
             * Force redirect to avoid resending form when refreshing page
             */
            $response = new RedirectResponse(
                $this->getService('urlGenerator')->generate('newsAdminListPage')
            );
            $response->prepare($request);

            return $response->send();

        } else {
            return $this->throw404();
        }
    }

    public function listDeletedAction(Request $request)
    {
        $this->validateAccessForRole('ROLE_ACCESS_NEWS_DELETE');

        $type = $this->getService('nodeTypeApi')->getOneBy(array("name" => "News"));
        $news = $this->getService("nodeApi")->getBy(array("status" => NODE::DELETED, "nodeType" => $type));

        $this->assignation["news"] = $news;

        return new Response(
            $this->getTwig()->render('admin/listDeleted.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );

    }

    /**
     * @param RZ\Roadiz\Core\Entities\Node $node
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildDeleteForm(Node $node)
    {
        $builder = $this->getService('formFactory')
            ->createBuilder('form')
            ->add('nodeId', 'hidden', array(
                'data' => $node->getId(),
                'constraints' => array(
                    new NotBlank()
                )
            ));

        return $builder->getForm();
    }

    /**
     * @return \Symfony\Component\Form\Form
     */
    protected function buildEmptyTrashForm()
    {
        $builder = $this->getService('formFactory')
            ->createBuilder('form');

        return $builder->getForm();
    }
}
