<?php
namespace LocalContexts\Controller;

use LocalContexts\Form\ProjectForm;
use LocalContexts\Form\AssignForm;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
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
        
        $form = $this->getForm(AssignForm::class);

        // Coming from assign page, assign notices & labels
        if (isset($params['lc-notice'])) {
            foreach ($params['lc-notice'] as $notice) {
                $noticeArray[] = $notice;
            }
            
            // Assign notices to general settings for page block access
            $this->settings->set('lc_notices', $noticeArray);

            // Assign notices to site(s)
            $siteSettings = $this->siteSettings();
            if (isset($params['lc-sites'])) {
                foreach ($params['lc-sites'] as $site) {
                    $siteSettings->set('local_contexts_notices', $noticeArray, $site);
                }
            }
        }
        
        $contentArray = [];
        if (!empty($this->settings->get('lc_project_id'))) {
            $projects = explode(',', $this->settings->get('lc_project_id'));
            foreach ($projects as $projectID) {
                $contentArray = array_merge($contentArray, $this->fetchAPIdata($projectID));
                // Remove duplicates
                $contentArray = array_unique($contentArray, SORT_REGULAR);
            }
        } else {
            $contentArray = $this->fetchAPIdata();
        }
        
        $view->setVariable('lc_content', $contentArray);
        $view->setVariable('form', $form);
        return $view;
    }

    /**
     * retrieve and display content from Local Contexts API
     *
     * @param string $projectID
     */
    protected function fetchAPIdata($projectID = null)
    {
        // Retrieve generic 'Open to Collaborate' even if no project ID given
        $collaborateURL = 'https://localcontextshub.org/api/v1/notices/open_to_collaborate';
        $this->client->setUri($collaborateURL);
        $response = $this->client->send();
        $collaborateMetadata = json_decode($response->getBody(), true);
        $noticeArray['name'] = $collaborateMetadata['name'];
        $noticeArray['image_url'] = $collaborateMetadata['img_url'];
        $noticeArray['text'] = $collaborateMetadata['default_text'];
        $assignArray[] = $noticeArray;
        
        // If project ID(s) given, retrieve specific project notices
        if (!empty($projectID)) {
            $APIProjectURL = 'https://localcontextshub.org/api/v1/projects/' . $projectID;
        } else {
            return $assignArray;
        }
        $this->client->setUri($APIProjectURL);
        $response = $this->client->send();
        $projectMetadata = json_decode($response->getBody(), true);

        foreach ($projectMetadata['notice'] as $notice) {
            $noticeArray = array();
            $noticeArray['name'] = $notice['name'];
            $noticeArray['image_url'] = $notice['img_url'];
            $noticeArray['text'] = $notice['default_text'];
            $assignArray[] = $noticeArray;
            if ($notice['translations']) {
                $noticeArray['name'] = $notice['translations'][0]['translated_name'];
                $noticeArray['image_url'] = $notice['img_url'];
                $noticeArray['text'] = $notice['translations'][0]['translated_text'];
                $noticeArray['language'] = $notice['translations'][0]['language'];
                $assignArray[] = $noticeArray;
                $noticeArray = array();
            }
        }
        return $assignArray;
    }
}
