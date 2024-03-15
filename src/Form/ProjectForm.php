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
            'name' => 'lc_project_id',
            'type' => 'text',
            'options' => [
                'label' => 'Local Contexts Project ID', // @translate
                'info' => 'Optional. Add multiple IDs separated by "," to return multiple projects.', // @translate
            ],
            'attributes' => [
                'id' => 'lc-project-id',
            ],
        ]);
        
        // $this->add([
        //     'name' => 'sites',
        //     'type' => SiteSelect::class,
        //     'attributes' => [
        //         'class' => 'chosen-select',
        //         'data-placeholder' => 'Select site(s)', // @translate
        //         'multiple' => true,
        //         'id' => 'sites',
        //         'required' => true,
        //     ],
        //     'options' => [
        //         'label' => 'Sites', // @translate
        //         'info' => 'Select site(s) to apply Local Contexts Notices.', // @translate
        //         'empty_option' => '',
        //     ],
        // ]);
    }
}
