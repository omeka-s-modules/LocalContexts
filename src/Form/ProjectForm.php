<?php
namespace LocalContexts\Form;

use Laminas\Form\Form;

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
                'info' => 'Optional. To retrieve project content from Local Contexts Hub, enter user API key. To edit/remove existing notices, leave blank.', // @translate
            ],
            'attributes' => [
                'id' => 'lc-api-key',
            ],
        ]);

        $this->add([
            'name' => 'lc_project_id',
            'type' => 'text',
            'options' => [
                'label' => 'Local Contexts Project ID', // @translate
                'info' => 'Optional. Input project IDs to retrieve from Local Contexts Hub. Add multiple IDs separated by "," to return multiple projects. If no ID given, return all projects associated with Local Contexts user.', // @translate
            ],
            'attributes' => [
                'id' => 'lc-project-id',
            ],
        ]);
    }
}
