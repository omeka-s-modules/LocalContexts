<?php
namespace LocalContexts\Controller;

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
        $assignedArray = $this->settings->get('lc_notices') ?: [];

        // If LC assign content selected, add to general settings for site/item/page block access
        if (isset($params['lc-notice'])) {
            foreach ($params['lc-notice'] as $notice) {
                $noticeArray[] = json_decode($notice, true);
            }
            
            // Add notices to general settings for site/item/page block access
            if (isset($assignedArray)) {
                $assignedArray = array_unique(array_merge($assignedArray, $noticeArray), SORT_REGULAR);
            }
            $this->settings->set('lc_notices', $assignedArray);
        }
        
        // Retrieve project data from Local Contexts API
        $contentArray = [];
        // Only retrieve API content if given API key
        if (isset($params['lc_api_key'])) {
            if (!empty($this->settings->get('lc_project_id'))) {
                $projects = explode(',', $this->settings->get('lc_project_id'));
                // Display 'Open to Collaborate' notice along with all projects
                $contentArray[] = $this->fetchAPIdata($params['lc_api_key']);
                foreach ($projects as $projectID) {
                    $contentArray[] = $this->fetchAPIdata($params['lc_api_key'], trim($projectID));
                }
            } else {
                $contentArray[] = $this->fetchAPIdata($params['lc_api_key']);
            }
        }

        $view->setVariable('lc_content', $contentArray);
        $view->setVariable('lc_assigned', $assignedArray);
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
        
        // If project ID(s) given, retrieve specific project notices
        if (!empty($projectID)) {
            $APIProjectURL = 'https://sandbox.localcontextshub.org/api/v2/projects/multi' . $projectID;
        } else {
            // If not, retrieve generic 'Open to Collaborate' notice
            $collaborateURL = 'https://sandbox.localcontextshub.org/api/v2/notices/open_to_collaborate';
            // $request = $this->client->setUri($collaborateURL);
            // $request = $this->client
            //     ->setUri($collaborateURL)
            //     ->setMethod('GET');
            // $request->getRequest()->getHeaders()->addHeaders(['x-api-key' => $apiKey]);
            // $this->client->getRequest()->getHeaders()->addHeaderLine('x-api-key: ' . $apiKey);
            // $this->client->setHeaders(['x-api-key' => $apiKey]);
            $writer = new \Laminas\Log\Writer\Stream('logs/application.log');
            $logger = new \Laminas\Log\Logger();
            $logger->addWriter($writer);
            // $logger->info($this->client->getRequest()->getHeaders()->toString());
            // $headers = $request->getHeaders();
            // $headers->addHeaderLine('x-api-key', $apiKey);
            $request = new Request;
            $httpClient = new Client;
            $request->setUri($collaborateURL);
            // $request->getRequest()->getHeaders()->addHeaderLine('x-api-key', $apiKey);
            // $request->getHeaders()->addHeaders(['x-api-key' => $apiKey]);
            $request->getHeaders()->addHeaderLine('x-api-key', $apiKey);
            $logger->info($request->getHeaders()->toString());
            // $response = $this->client->setUri($collaborateURL)->send();
            $response = $httpClient->send($request);
            // $response = $request->send();
            $logger->info($request->getHeaders()->toString());
            $logger->info($response->getBody());
            $projectMetadata = json_decode($response->getBody(), true);
            $collaborateMetadata = json_decode($response->getBody(), true);
            $noticeArray['name'] = $collaborateMetadata['name'];
            $noticeArray['image_url'] = $collaborateMetadata['img_url'];
            $noticeArray['text'] = $collaborateMetadata['default_text'];
            $assignArray[] = $noticeArray;
            return $assignArray;
        }
        $request = $this->client->setUri($APIProjectURL);
        $request->getRequest()->getHeaders()->addHeaders(['x-api-key' => $apiKey]);
        // $this->client->setHeaders(['x-api-key' => $apiKey]);
        $response = $request->send();

        $assignArray['project_url'] = $projectMetadata['project_page'] ?: null;
        $assignArray['project_title'] = $projectMetadata['title'] ?: null;
        if (isset($projectMetadata['notice'])) {
            foreach ($projectMetadata['notice'] as $notice) {
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
