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
use Laminas\Form\Element;
use Laminas\Form\Element\Select;
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
        // Add and authorize LC content to Site Settings form
        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_elements',
            [$this, 'addLCSiteSettings']
        );

        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_input_filters',
            function (Event $event) {
                $inputFilter = $event->getParam('inputFilter');
                $inputFilter->add([
                    'name' => 'lc_content_sites',
                    'required' => false,
                ]);
                $inputFilter->add([
                    'name' => 'lc_language',
                    'required' => false,
                ]);
            }
        );

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

        $sharedEventManager->attach(
            'Omeka\Form\ResourceBatchUpdateForm',
            'form.add_elements',
            [$this, 'LCBatchUpdateResource']
        );

        // Add stylesheet for styling Site Settings form
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.edit.form.before',
            function (Event $event) {
                $view = $event->getTarget();
                $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
            }
        );

        // Add stylesheet for styling BatchUpdate
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.batch_edit.before',
            function (Event $event) {
                $view = $event->getTarget();
                $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
            }
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
                    'name' => 'o:lc-content-language',
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
                if (isset($rawData['o:lc-content-language'])) {
                    $data['o:lc-content-language'] = $rawData['o:lc-content-language'];
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

    public function addLCSiteSettings(Event $event)
    {
        $form = $event->getTarget();
        $services = $this->getServiceLocator();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $groups = $form->getOption('element_groups');
        $groups['local_contexts'] = 'Local Contexts'; // @translate
        $form->setOption('element_groups', $groups);

        if ($settings->get('lc_notices') || $siteSettings->get('lc_content_sites')) {
            // Combine available general settings projects with existing site settings projects
            $projects = $settings->get('lc_notices') ? $settings->get('lc_notices'): [];
            if ($siteSettings->get('lc_content_sites')) {
                foreach($siteSettings->get('lc_content_sites') as $siteProject) {
                    $projects[] = json_decode($siteProject, true);
                }
            }

			$lcArray = array();
            foreach (array_unique($projects, SORT_REGULAR) as $key => $project) {
                // Save each project's content as single select value
                $lcHtml = $this->renderLCNoticeHtml($project);
                $lcArray['label'] = $lcHtml;
                $lcArray['value'] = json_encode($project);
                $optionArray[] = $lcArray;
			}

            $form->add([
                'name' => 'lc_language',
                'type' => Element\Select::class,
                'options' => [
                    'element_group' => 'local_contexts',
                    'label' => 'Local Contexts Language', // @translate
                    'info' => 'Only display content in selected language (Note: must already be generated and retrieved from LC Hub).', // @translate
                    'value_options' => [
                        'All' => 'All available languages', // @translate
                        'English' => 'English', // @translate
                        'French' => 'French', // @translate
                        'Spanish' => 'Spanish', // @translate
                        'Māori' => 'Māori', // @translate
                    ],
                ],
                'attributes' => [
                    'value' => $siteSettings->get('lc_language') ?: 'English',
                    'required' => false,
                ],
            ]);

            $form->add([
                'name' => 'lc_content_sites',
                'type' => MultiCheckbox::class,
                'options' => [
                    'element_group' => 'local_contexts',
                    'label' => 'Local Contexts value(s)', // @translate
                    'info' => 'Local Contexts value(s) to apply to site footer.', // @translate
                    'value_options' => $optionArray,
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'local-contexts-multicheckbox',
                    ],
                ],
                'attributes' => [
                    'value' => $siteSettings->get('lc_content_sites'),
                    'class' => 'column check',
                    'required' => false,
                ],
            ]);
        }
    }

    public function addLCSite(Event $event)
    {
        $view = $event->getTarget();
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        if (isset($view->site) && $siteSettings->get('lc_content_sites')) {
            $projects = $siteSettings->get('lc_content_sites');
            $lcLanguage = $siteSettings->get('lc_language');
            $contentArray = array();
            foreach ($projects as $project) {
                $project = json_decode($project, true);
                $projectArray = array();

                foreach ($project as $key => $content) {
                    if (is_int($key)) {
                        // Only print content in selected language. If 'English' or 'All',
                        // print everything (since English doesn't have language element)
                        if ((isset($content['language']) && $content['language'] == $lcLanguage) 
                        || (!isset($content['language']) && $lcLanguage == 'English') 
                        || $lcLanguage == 'All') {
                            $projectArray[] = $content;
                        }
                    }
                }

                // Don't print project URL if element value array is empty
                if (isset($project['project_url']) && $projectArray) {
                    $projectArray['project_url'] = $project['project_url'];
                    $projectArray['project_title'] = $project['project_title'];
                }

                if ($projectArray) {
                    $contentArray[] = $projectArray;
                }
            }
            echo $view->partial('local-contexts/common/site-footer', [
                'lc_content' => $contentArray,
            ]);
            $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
        }
    }

    public function LCEditResource(Event $event) {
        $view = $event->getTarget();
        $formData = $event->getParam('data') ? $event->getParam('data')->getContent() : [];
        if ($view->setting('lc_notices')) {
			$projects = $view->setting('lc_notices');
			foreach ($projects as $project) {
                $contentArray[] = $project;
            }

            $languageData = [
                'All' => 'All available languages', // @translate
                'English' => 'English', // @translate
                'French' => 'French', // @translate
                'Spanish' => 'Spanish', // @translate
                'Māori' => 'Māori', // @translate
            ];
            $languageSelect = new Select('o:lc-content-language');
            $languageSelect->setValueOptions($languageData);

			echo $view->partial('local-contexts/common/lc-resource-edit', [
	            'data' => $formData,
                'language_select' => $languageSelect,
                'lc_content' => $contentArray,
	        ]);
            $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
        }
    }

    public function LCBatchUpdateResource(Event $event) {
        $form = $event->getTarget();
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        if ($settings->get('lc_notices')) {
			$projects = $settings->get('lc_notices');

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

            $form->add([
                'name' => 'o:lc-content-language',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Local Contexts language', // @translate
                    'info' => 'Only display content in selected language (Note: must already be generated and retrieved from LC Hub).', // @translate
                    'value_options' => [
                        'All' => 'All available languages', // @translate
                        'English' => 'English', // @translate
                        'French' => 'French', // @translate
                        'Spanish' => 'Spanish', // @translate
                        'Māori' => 'Māori', // @translate
                    ],
                ],
                'attributes' => [
                    'required' => false,
                ],
            ]);

			$lcArray = array();
            foreach ($projects as $project) {
                $lcHtml = $this->renderLCNoticeHtml($project);
                $lcArray['label'] = $lcHtml;
                $lcArray['value'] = json_encode($project);
                $optionArray[] = $lcArray;
			}

            $form->add([
                'name' => 'o:lc-content',
                'type' => MultiCheckbox::class,
                'options' => [
                    'label' => 'Local Contexts value(s)', // @translate
                    'info' => 'Local Contexts value(s) to apply to above field.', // @translate
                    'value_options' => $optionArray,
                    'label_options' => [
                        'disable_html_escape' => true,
                    ],
                    'label_attributes' => [
                        'class' => 'local-contexts-multicheckbox',
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
            $lcLanguage = $data['o:lc-content-language'];
            foreach ($data['o:lc-content'] as $lcContent) {
                $lcContent = json_decode($lcContent, true);
                $this->saveLCMetadata($lcContent, $property, $item, $lcLanguage);
            }
        }
    }

    public function renderLCNoticeHtml($project) {
        $lcHtml = '';
        // Save each project's content as single select value
        $lcHtml = '<div class="column content">';
        if (isset($project['project_url'])) {
            $lcHtml .= "<a class='project-name' target='_blank' href=" . $project['project_url'] . ">" . $project['project_title'] . "</a>";
        }
        foreach($project as $key => $content) {
            if (is_int($key)) {
                $lcHtml .= '<div class="local-contexts-notice"><img class="image" src="' . $content['image_url'] .
                    '"><div class="column text"><div class="notice-name">' . $content['name'] .
                    (isset($content['language']) ? '<span class="language"> (' . $content['language'] . ')</span>' : '') . '</div>' .
                    '<div class="notice-description">' . $content['text'] . '</div></div></div>';
            }
        }
        $lcHtml .= '</div>';
        return $lcHtml;
    }
  
    public function saveLCMetadata(array $lcContent, Property $property, Item $item, $lcLanguage)
    {
        $resourceValues = $item->getValues();
        $projectURL = isset($lcContent['project_url']) ? $lcContent['project_url'] : null;
        $langTagArray = array(
            'French' => 'fr',
            'Spanish' => 'es',
            'Māori' => 'mi'
        );

        // Save LC content as lc_content Datatype to better display LC graphic and format
        foreach($lcContent as $key => $content) {
            if (is_int($key)) {
                // Only print content in selected language. If 'English' or 'All',
                // print everything (since English doesn't have language element)
                if ((isset($content['language']) && $content['language'] == $lcLanguage) 
                || (!isset($content['language']) && $lcLanguage == 'English') 
                || $lcLanguage == 'All') {
                    $value = new Value;
                    $value->setResource($item);
                    $value->setType('lc_content');
                    $value->setIsPublic(true);
                    $value->setProperty($property);
                    if (!empty($projectURL)) {
                        $content['project_url'] = $projectURL;
                    }
                    $value->setValue(json_encode($content));
                    // Switch to localization language tags
                    if (isset($content['language'])) {
                        if (array_key_exists($content['language'], $langTagArray)) {
                            $langStr = $langTagArray[$content['language']];
                        } else {
                            $langStr = 'en-US';
                        }
                    } else {
                        // Set English by default
                        $langStr = 'en-US';
                    }
                    $value->setLang($langStr);
                    $resourceValues->add($value);
                }
            }
        }
    }
}
