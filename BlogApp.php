<?php

namespace Themes\Blog;

use Themes\DefaultTheme\DefaultThemeApp;
use RZ\Roadiz\CMS\Controllers\FrontendController;
use RZ\Roadiz\Core\Entities\Node;
use RZ\Roadiz\Core\Entities\Translation;
use Symfony\Bridge\Twig\Extension\TranslationExtension;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pimple\Container;

/**
* DefaultThemeApp class
*/
class BlogApp extends DefaultThemeApp
{
    protected static $themeName =      'Blog static theme';
    protected static $themeAuthor =    'Maxime Constantinian';
    protected static $themeCopyright = 'Maxime Constantinian';
    protected static $themeDir =       'Blog';
    protected static $backendTheme =    false;

    protected static $specificNodesControllers = array(
      'home', 'tagListing', "news"
        // Put here your nodes which need a specific controller
        // instead of a node-type controller
    );

    /**
     * Return every paths to search for twig templates.
     *
     * Extend this method in your custom theme if you need to
     * search additionnal templates.
     *
     * @return $this
     */
    public function getTwigLoader()
    {
        $this->getService()->extend(
            'twig.loaderFileSystem',
            function (\Twig_Loader_Filesystem $loader, $c) {

                $loader->prependPath('themes/' . parent::$themeDir . '/Resources/views');
                $loader->prependPath(static::getViewsFolder());
                return $loader;
            }
        );
        $this->getService('twig.environment')->addExtension(new TranslationExtension($this->getService('translator')));
        $this->getService('twig.environment')->addExtension(new \Twig_Extensions_Extension_Intl());
        return $this;
    }

    /**
     * @return string
     */
    public function getStaticResourcesUrl()
    {
        return $this->kernel->getRequest()->getBaseUrl().
            '/themes/'.parent::$themeDir.'/static/';
    }

   /*
    * Default action for default URL (homepage).
    *
    * @param Symfony\Component\HttpFoundation\Request $request
    * @param string                                   $_locale
    *
    * @return Symfony\Component\HttpFoundation\Response
    */
    public function homeAction(Request $request, $_locale = null)
    {
        $translation = $this->bindLocaleFromRoute($request, $_locale);
        parent::prepareThemeAssignation(null, $translation);

        $tagParent = $this->getService('tagApi')->getOneBy(array('tagName' => 'newstypes'));
        $allTag = $this->getService('tagApi')->getBy(
            array(
                'translation' => $this->translation,
                'parent' => $tagParent
            )
        );
        $newsType = $this->getService('nodeTypeApi')->getOneBy(array("name" => "News"));
        $news = $this->getService('nodeSourceApi')->getBy(
            array(
                "node.nodeType" => $newsType,
                'translation' => $translation
            ),
            array(
                "date" => "DESC"
            )
        );

        $dateFound = array();

        foreach ($news as $key => $result) {
            $dateFound[$result->getDate()->format("Y F")] = $result->getDate();
        }

        $this->assignation['archives'] = $dateFound;
        $this->assignation['tags'] = $allTag;
        $this->assignation['news'] = $news;

        /*
         * First choice, render Homepage as any other nodes
         */
        return new Response(
            $this->getTwig()->render('home.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html'));
    }

    /**
     * Append objects to global container.
     *
     * @param Pimple\Container $container
     */
    public static function setupDependencyInjection(Container $container)
    {
        parent::setupDependencyInjection($container);

        $container->extend('twig.loaderFileSystem', function (\Twig_Loader_Filesystem $loader, $c) {
            $loader->addPath(static::getViewsFolder());
            return $loader;
        });

        $container->extend('backoffice.entries', function (array $entries, $c) {

            /*
             * Add a test entry in your Backoffice
             */
            $entries['news'] = array(
                'name' => 'Manage News',
                'path' => null,
                'icon' => 'uk-icon-file-text-o',
                'roles' => array('ROLE_ACCESS_NEWS', 'ROLE_ACCESS_NEWS_DELETE'),
                'subentries' => array(
                    'newsList' => array(
                        'name' => 'List News',
                        'path' => $c['urlGenerator']->generate('newsAdminListPage'),
                        'icon' => 'uk-icon-file-text-o',
                        'roles' => array('ROLE_ACCESS_NEWS')
                    ),
                    'trashedNews' => array(
                        'name' => 'Trashed News',
                        'path' => $c['urlGenerator']->generate('newsAdminTrashPage'),
                        'icon' => 'uk-icon-trash-o',
                        'roles' => array('ROLE_ACCESS_NEWS_DELETE')
                    )
                )
            );

            return $entries;
        });
    }
}
