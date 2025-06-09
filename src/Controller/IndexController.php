<?php
namespace LocalContexts\Controller;

use LocalContexts\Module;
use LocalContexts\Form\ProjectForm;
use Laminas\Form\Form;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use Laminas\Http\Request;
use Laminas\Http\Client;
use Omeka\Api\Exception as ApiException;
use Omeka\Settings\Settings;
use Omeka\Stdlib\Message;

class IndexController extends AbstractActionController
{
    /**
     * @var Settings
     */
    protected $settings;
    
    protected $client;

    public function __construct(Settings $settings, $client)
    {
        $this->settings = $settings;
        $this->client = $client;
    }
    
    public function indexAction()
    {
        $view = new ViewModel;
        $form = $this->getForm(ProjectForm::class);

        if ($this->settings->get('lc_project_id')) {        
            $form->setData(['lc_project_id' => $this->settings->get('lc_project_id')]);       
        }

        $view->setVariable('form', $form);
        return $view;
    }

    public function assignAction()
    {
        $view = new ViewModel;

        $form = $this->getForm(ProjectForm::class);
        $params = $this->params()->fromPost();
        $form->setData($params);
        if ($form->isValid()) {
            $formData = $form->getData();
            if (isset($formData['lc_project_id'])) {
                $this->settings->set('lc_project_id', $formData['lc_project_id']);
            }
        }
        
        $assignForm = new Form();

        // If LC remove content selected, remove from general settings
        if (isset($params['lc-remove'])) {
            foreach ($params['lc-remove'] as $remove) {
                $removeArray[] = json_decode($remove, true);
            }
            $this->removeLCcontent($removeArray);
        }

        // Get existing LC content from database
        $existingProjectArray = $this->settings->get('lc_notices') ?: [];

        // If LC assign content selected, add to general settings for site/item/page block access
        if (isset($params['lc-notice'])) {
            foreach ($params['lc-notice'] as $notice) {
                $noticeArray[] = json_decode($notice, true);
            }
            
            // Add notices to general settings for site/item/page block access
            if (isset($existingProjectArray)) {
                $existingProjectArray = array_unique(array_merge($existingProjectArray, $noticeArray), SORT_REGULAR);
            }
            $this->settings->set('lc_notices', $existingProjectArray);
        }

        $newProjectArray = [];
        // Retrieve project data from Local Contexts API. Only retrieve API content if given API key
        if (!empty($params['lc_api_key'])) {
            if (!empty($this->settings->get('lc_project_id'))) {
                $projects = explode(',', $this->settings->get('lc_project_id'));
                // Display 'Open to Collaborate' notice along with all given projects
                $newProjectArray[] = $this->fetchAPIdata($params['lc_api_key']);
                foreach ($projects as $projectID) {
                    $newProjectArray[] = $this->fetchAPIdata($params['lc_api_key'], trim($projectID));
                }
            } else {
                // Display 'Open to Collaborate' notice along with all user projects
                $newProjectArray[] = $this->fetchAPIdata($params['lc_api_key']);
                $projectsURL = 'https://sandbox.localcontextshub.org/api/v2/projects/';
                $request = $this->client->setUri($projectsURL);
                $request->getRequest()->getHeaders()->addHeaders(['x-api-key' => $params['lc_api_key']]);
                $response = $request->send();
                if ($response->isSuccess()) {
                    $projectsMetadata = json_decode($response->getBody(), true);
                    foreach ($projectsMetadata['results'] as $project) {
                        $newProjectArray[] = $this->fetchAPIdata($params['lc_api_key'], $project['unique_id']);
                    }
                }
            }
            $newProjectArray = array_filter($newProjectArray);
        }

        // Remove already assigned notices from retrieved notices
        $newProjectArray = array_diff(array_map('serialize',$newProjectArray), array_map('serialize', $existingProjectArray));
        $newProjectArray = array_map('unserialize', $newProjectArray);

        $contentArray = [];
        foreach ($newProjectArray as $project) {
            // Collapse many projects for ease of viewing
            $collapse = (count($newProjectArray) >= 3) ? true : false;
            $lcHtml = \LocalContexts\Module::renderLCNoticeHtml($project, $collapse);
            $lcArray['label'] = $lcHtml;
            $lcArray['value'] = json_encode($project);
            $contentArray[] = $lcArray;
        }

        $assignedArray = [];
        foreach ($existingProjectArray as $project) {
            // Collapse many projects for ease of viewing
            $collapse = (count($existingProjectArray) >= 3) ? true : false;
            $lcHtml = \LocalContexts\Module::renderLCNoticeHtml($project, $collapse);
            $lcArray['label'] = $lcHtml;
            $lcArray['value'] = json_encode($project);
            $assignedArray[] = $lcArray;
        }

        // Redirect to index page if no content to display
        if (empty($contentArray) && empty($assignedArray)) {
            return $this->redirect()->toRoute('admin/local-contexts');
        } else if (empty($contentArray) && !empty($assignedArray)) {
            $view->setVariable('lc_assigned', $assignedArray);
        } else if (!empty($contentArray) && empty($assignedArray)) {
            $view->setVariable('lc_content', $contentArray);
        } else {
            $view->setVariable('lc_content', $contentArray);
            $view->setVariable('lc_assigned', $assignedArray);
        }

        $view->setVariable('form', $assignForm);
        return $view;
    }

    /**
     * retrieve and display content from Local Contexts API
     *
     * @param string $projectID
     */
    protected function fetchAPIdata($apiKey, $projectID = null)
    {
        if (!empty($projectID)) {
            // If project ID(s) given, retrieve specific project notices
            $APIProjectURL = 'https://sandbox.localcontextshub.org/api/v2/projects/' . $projectID . '/';
        } else {
            // If not, retrieve generic 'Open to Collaborate' notice
            $collaborateURL = 'https://sandbox.localcontextshub.org/api/v2/notices/open_to_collaborate/';

            $request = $this->client->setUri($collaborateURL);
            $request->getRequest()->getHeaders()->addHeaders(['x-api-key' => $apiKey]);
            $response = $request->send();
            if (!$response->isSuccess()) {
                return;
            }

            $collaborateMetadata = json_decode($response->getBody(), true);
            // Set institution/researcher name and profile page to display as linked 'project' metadata
            if (isset($collaborateMetadata['institution'])) {
                $assignArray['project_url'] = $collaborateMetadata['institution']['profile_url'];
                $assignArray['project_title'] = $collaborateMetadata['institution']['name'] . ' (institution)';
            } else if (isset($collaborateMetadata['researcher'])) {
                $assignArray['project_url'] = $collaborateMetadata['researcher']['profile_url'];
                $assignArray['project_title'] = $collaborateMetadata['researcher']['name'] . ' (researcher)';
            } else if (isset($collaborateMetadata['integration_partner'])) {
                $assignArray['project_url'] = $collaborateMetadata['integration_partner']['profile_url'];
                $assignArray['project_title'] = $collaborateMetadata['integration_partner']['name'] . ' (integration partner)';
            } else {
                $assignArray['project_url'] = null;
                $assignArray['project_title'] = null;
            }
            $noticeArray['name'] = isset($collaborateMetadata['notice']['name']) ? $collaborateMetadata['notice']['name'] : null;
            $noticeArray['image_url'] = isset($collaborateMetadata['notice']['img_url']) ? $collaborateMetadata['notice']['img_url'] : null;
            $noticeArray['text'] = isset($collaborateMetadata['notice']['default_text']) ? $collaborateMetadata['notice']['default_text'] : null;
            $assignArray[] = $noticeArray;
            return $assignArray;
        }

        $request = $this->client->setUri($APIProjectURL);
        $request->getRequest()->getHeaders()->addHeaders(['x-api-key' => $apiKey]);
        $response = $request->send();
        if (!$response->isSuccess()) {
            return;
        }
        $projectMetadata = json_decode($response->getBody(), true);
        $assignArray['project_url'] = isset($projectMetadata['project_page']) ? $projectMetadata['project_page'] : null;
        $assignArray['project_title'] = isset($projectMetadata['title']) ? $projectMetadata['title'] . ' (project)' : null;

        if (isset($projectMetadata['notice'])) {
            $assignArray = $this->buildLCProjectComponent($projectMetadata['notice'], $assignArray, true);
        }
        if (isset($projectMetadata['bc_labels'])) {
            $assignArray = $this->buildLCProjectComponent($projectMetadata['bc_labels'], $assignArray, false);
        }
        if (isset($projectMetadata['tk_labels'])) {
            $assignArray = $this->buildLCProjectComponent($projectMetadata['tk_labels'], $assignArray, false);
        }

        return $assignArray;
    }

    /**
     * Retrieve metadata from Notices and Labels
     *
     * @param array $projectMetadataArray
     * @param bool $isNotice
     */
    protected function buildLCProjectComponent($projectMetadataArray, $assignArray, $isNotice = false)
    {
        foreach ($projectMetadataArray as $component) {
            $componentArray['name'] = $component['name'];
            $componentArray['image_url'] = $component['img_url'];
            $componentArray['text'] = $isNotice ? $component['default_text'] : $component['label_text'];
            $assignArray[] = $componentArray;
            if ($component['translations']) {
                foreach ($component['translations'] as $translation) {
                    $componentArray['name'] = $translation['translated_name'];
                    $componentArray['image_url'] = $component['img_url'];
                    $componentArray['text'] = $translation['translated_text'];
                    $componentArray['language'] = $translation['language'];
                    $assignArray[] = $componentArray;
                    $componentArray = array();
                }
            }
        }
        return $assignArray;
    }

    /**
     * remove Local Contexts content from settings and any sites/items/pages (if possible)
     *
     * @param array $removeArray
     */
    protected function removeLCcontent($removeArray)
    {
        // Get existing LC content from settings
        $currentLCcontent = $this->settings->get('lc_notices') ?: [];

        // Build new array without removeArray content and save to settings
        $diff = array_diff(array_map('json_encode', $currentLCcontent), array_map('json_encode', $removeArray));
        $newLCcontent = array_map(function ($json) { return json_decode($json, true); }, $diff);
        $this->settings->set('lc_notices', $newLCcontent);
    }
}
