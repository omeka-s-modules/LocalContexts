<?php
namespace LocalContexts\Form;

use Laminas\Form\Form;
// use Laminas\Authentication\AuthenticationService;
// use Omeka\Settings\Settings;
// use Omeka\Form\Element\SiteSelect;

class ProjectForm extends Form
{

    public function init()
    {
        $this->setAttribute('action', 'local-contexts/assign');

        $this->add([
            'name' => 'lc_api_key',
            'type' => 'password',
            'options' => [
                'label' => 'API Key', // @translate
                'info' => 'Optional. To retrieve project notices from Local Contexts Hub, enter user API key. To edit/remove existing notices, leave blank.', // @translate
            ],
            'attributes' => [
                'id' => 'lc-api-key',
            ],
        ]);

        $this->add([
            'name' => 'lc_institution_id',
            'type' => 'text',
            'options' => [
                'label' => 'Local Contexts Institution ID', // @translate
                'info' => 'Optional. Input Institution ID to retrieve all associated projects from Local Contexts Hub (this takes precedence over individual project IDs below).', // @translate
            ],
            'attributes' => [
                'id' => 'lc-institution-id',
            ],
        ]);

        $this->add([
            'name' => 'lc_project_id',
            'type' => 'text',
            'options' => [
                'label' => 'Local Contexts Project ID', // @translate
                'info' => 'Optional. Input Project IDs to retrieve from Local Contexts Hub. Add multiple IDs separated by "," to return multiple projects.', // @translate
            ],
            'attributes' => [
                'id' => 'lc-project-id',
            ],
        ]);
    }
}
