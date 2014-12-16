<?php

namespace Themes\Blog\Controllers;

use Themes\DefaultTheme\DefaultThemeApp;
use Themes\Blog\BlogApp;
use RZ\Renzo\Core\Entities\Node;
use RZ\Renzo\Core\Entities\Translation;
use RZ\Renzo\Core\Utils\StringHandler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NewsController extends BlogApp
{

    public function indexAction(
        Request $request,
        $_locale = null,
        $name
    ) {
        $translation = $this->bindLocaleFromRoute($request, $_locale);
        $newsType = $this->getService('nodeTypeApi')->getOneBy(array("name" => "News"));
        $news = $this->getService('nodeApi')->getOneBy(
            array(
                "nodeType" => $newsType,
                'translation' => $translation,
                'nodeName' => $name
            )
        );

        $this->prepareThemeAssignation($news, $translation);

        $this->getService('stopwatch')->start('twigRender');
        //
        // $this->assignaton['nodeSource'] = $news;

        return new Response(
            $this->getTwig()->render('news.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html')
        );
    }

    /**
     * Default action for archive URL.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param string                                   $year
     * @param string                                   $month
     * @param string                                   $_locale
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function archiveListingAction(Request $request, $_locale = null, $year, $month)
    {
        $translation = $this->bindLocaleFromRoute($request, $_locale);
        $this->prepareThemeAssignation(null, $translation);

        $date = \DateTime::createFromFormat("YmdHis", $year.$month."01000000");
        $endDate = clone $date;

        $endDate->modify('last day of this month');

        $newsType = $this->getService('nodeTypeApi')->getOneBy(array("name" => "News"));
        $results = $this->getService('nodeSourceApi')->getBy(
            array(
                "node.nodeType" => $newsType,
                "translation" => $this->translation,
                "date" => array("BETWEEN", $date, $endDate)
            )
        );

        $this->assignation['news'] = $results;
        $this->assignation['obj'] = $date;
        $this->assignation['type'] = 'date';

        /*
         * First choice, render Homepage as any other nodes
         */
        return new Response(
            $this->getTwig()->render('newsTag.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html'));
    }


    /**
     * Default action for tag URL.
     *
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param string                                   $tagName
     * @param string                                   $_locale
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function tagListingAction(Request $request, $_locale = null, $tagName)
    {
        $translation = $this->bindLocaleFromRoute($request, $_locale);
        $this->prepareThemeAssignation(null, $translation);

        $tag = $this->getService('tagApi')->getOneBy(
          array(
            'translation' => $this->translation,
            'tagName' => $tagName
          )
        );

        $news = $this->getService('nodeSourceApi')->getBy(
          array(
            "tags" => $tag,
            "translation" => $this->translation
          )
        );

        $this->assignation['news'] = $news;
        $this->assignation['obj'] = $tag;
        $this->assignation['type'] = 'tag';

        /*
         * First choice, render Homepage as any other nodes
         */
        return new Response(
            $this->getTwig()->render('newsTag.html.twig', $this->assignation),
            Response::HTTP_OK,
            array('content-type' => 'text/html'));
    }
}
