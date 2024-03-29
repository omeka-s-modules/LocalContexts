<?php
namespace LocalContexts;

use Omeka\Module\AbstractModule;
use Omeka\Entity\Item;
use Omeka\Entity\Value;
use Omeka\Entity\Property;
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

        // Add LC content selection to item add and edit forms
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.edit.form.advanced',
            [$this, 'LCEditResource']
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.form.advanced',
            [$this, 'LCEditResource']
        );

        // Save selected LC content as item metadata when hydrating
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.hydrate.post',
            [$this, 'LCHydrate']
        );
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
            echo $view->partial('local-contexts/common/site-footer', [
                'contentArray' => $contentArray,
            ]);
            $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
        }
    }

    public function LCEditResource(Event $event) {
        $view = $event->getTarget();

        $formData = $event->getParam('data') ? $event->getParam('data')->getContent() : [];
        if ($view->setting('lc_notices')) {
			$notices = $view->setting('lc_notices');
			foreach ($notices as $notice) {
				$notice = json_decode($notice, true);
				$noticeArray['name'] = $notice['name'];
                $noticeArray['image_url'] = $notice['image_url'];
                $noticeArray['text'] = $notice['text'];
                $noticeArray['language'] = isset($notice['language']) ? $notice['language'] : null;
				$contentArray[] = $noticeArray;
			}

			echo $view->partial('local-contexts/common/lc-resource-edit', [
	            'data' => $formData,
                'lc_content' => $contentArray,
	        ]);
            $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
        }
    }

    public function LCHydrate(Event $event) {
        $item = $event->getParam('entity');
        $adapter = $event->getTarget();
        $data = $event->getParam('request')->getContent();
        $propertyAdapter = $adapter->getAdapter('properties');

        if ($data['o:lc-content-property']['o:id']) {
            $lcContentProperty = $data['o:lc-content-property']['o:id'];
        } else {
            // If no metadata value given, do nothing
            return;
        }

        $property = $propertyAdapter->findEntity($lcContentProperty);
        if ($data['o:lc-content']) {
            foreach ($data['o:lc-content'] as $lcContent) {
                $lcContent = json_decode($lcContent, true);
                $this->saveLCMetadata($lcContent, $property, $item);
            }
        }
    }

    public function saveLCMetadata(array $lcContent, Property $property, Item $item)
    {
        $resourceValues = $item->getValues();
        $value = new Value;
        $value->setResource($item);
        $value->setType('literal');
        $value->setIsPublic(true);
        $value->setProperty($property);

        $textValue .= $lcContent['name'];
        $textValue .= isset($lcContent['language']) ? ' (' . $lcContent['language'] . ')' : '';
        $textValue .= ': ' . $lcContent['text'];
        $value->setValue($textValue);

        $resourceValues->add($value);
    }
}
