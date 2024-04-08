<?php
namespace LocalContexts;

use Omeka\Module\AbstractModule;
use Omeka\Entity\Item;
use Omeka\Entity\Value;
use Omeka\Entity\Property;
use Omeka\Form\Element\PropertySelect;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Form\Fieldset;
use Laminas\Form\Element\MultiCheckbox;
use Laminas\EventManager\Event;

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

        // Add LC content selection to item add, edit and batch edit forms
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

        // Add stylesheet for styling BatchUpdate
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            // 'Omeka\Form\ResourceBatchUpdateForm',
            'view.batch_edit.before',
            function (Event $event) {
                $view = $event->getTarget();
                $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
            }
        );

        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_elements',
            [$this, 'LCBatchUpdateResource']
        );

        // Add and authorize LC content within Batch Update/Edit
        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_input_filters',
            function (Event $event) {
                $inputFilter = $event->getParam('inputFilter');
                $inputFilter->add([
                    'name' => 'o:lc-content-property',
                    'required' => false,
                ]);
                $inputFilter->add([
                    'name' => 'o:lc-content',
                    'required' => false,
                ]);
            }
        );

        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.preprocess_batch_update',
            function (Event $event) {
                $data = $event->getParam('data');
                $rawData = $event->getParam('request')->getContent();
                if (isset($rawData['o:lc-content-property'])) {
                    $data['o:lc-content-property'] = $rawData['o:lc-content-property'];
                }
                if (isset($rawData['o:lc-content'])) {
                    $data['o:lc-content'] = $rawData['o:lc-content'];
                }
                $event->setParam('data', $data);
            }
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

    public function LCBatchUpdateResource(Event $event) {
        $form = $event->getTarget();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        if ($settings->get('lc_notices')) {
			$notices = $settings->get('lc_notices');

            $form->add([
                'name' => 'o:lc-content-property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Local Contexts field', // @translate
                    'info' => 'Apply Local Contexts Notices to chosen metadata field.', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select property', // @translate
                    'id' => 'o-property',
                    'required' => false,
                ],
            ]);

			foreach ($notices as $notice) {
				$notice = json_decode($notice, true);
				$noticeArray['label'] = '<img class="column image" src="' . $notice['image_url'] .
                                        '"><div class="column text"><div class="name">' . $notice['name'] .
                                        (isset($notice['language']) ? '<span class="language"> (' . $notice['language'] . ')</span>' : '') . '</div>' .
                                        '<div class="description">' . $notice['text'] . '</div></div>';
                $noticeArray['value'] = json_encode($notice);
				$optionArray[$notice['name']] = $noticeArray;
			}

            $form->add([
                'name' => 'o:lc-content',
                'type' => MultiCheckbox::class,
                'options' => [
                    'label' => 'Local Contexts value(s)', // @translate
                    'info' => 'Local Contexts notice(s) to apply to above field.', // @translate
                    'value_options' => $optionArray,
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                ],
                'attributes' => [
                    'class' => 'column check',
                    'required' => false,
                ],
            ]);
        }
    }

    public function LCHydrate(Event $event) {
        $item = $event->getParam('entity');
        $adapter = $event->getTarget();
        $data = $event->getParam('request')->getContent();
        $propertyAdapter = $adapter->getAdapter('properties');

        if ($data['o:lc-content-property']) {
            $lcContentProperty = $data['o:lc-content-property'];
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
