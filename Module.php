<?php
namespace LocalContexts;

use Omeka\Module\AbstractModule;
use Omeka\Entity\Item;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Fieldset;
use Laminas\Mvc\MvcEvent;
use Laminas\EventManager\Event;
use LocalContexts\Form\ProjectForm;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {    
        // Display Local Context Notice/Label in footer of public site pages
        $resources = [
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\Media',
            'Omeka\Controller\Site\Index',
            'Omeka\Controller\Site\Page',
            'Omeka\Controller\Site\ItemSet',
        ];
        foreach ($resources as $resource) {
            $sharedEventManager->attach(
                $resource,
                'view.show.after',
                [$this, 'addLCSite']
            );
            $sharedEventManager->attach(
                $resource,
                'view.browse.after',
                [$this, 'addLCSite']
            );
        }
    }

    public function addLCSite(Event $event)
    {
        $view = $event->getTarget();
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        if (isset($view->site) && $siteSettings->get('local_contexts_notices')) {
            $notices = $siteSettings->get('local_contexts_notices');
            foreach ($notices as $notice) {
                $notice = json_decode($notice, true);
                $noticeArray['name'] = $notice['name'];
                $noticeArray['image_url'] = $notice['image_url'];
                $noticeArray['text'] = $notice['text'];
                $contentArray[] = $noticeArray;
            }
            echo $view->partial('site-footer', [
                'contentArray' => $contentArray,
            ]);
            $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
        }
    }
}
