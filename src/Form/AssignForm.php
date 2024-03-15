<?php
namespace LocalContexts\Form;

use Omeka\Form\Element\ResourceSelect;
use Omeka\Form\Element\SiteSelect;
use Omeka\Settings\UserSettings;
use Omeka\Api\Manager as ApiManager;
use Laminas\Form\Form;

class AssignForm extends Form
{
    public function init()
    {
        $this->setAttribute('action', 'assign');
        $this->add([
            'name' => 'lc-sites',
            'type' => SiteSelect::class,
            'attributes' => [
                'class' => 'chosen-select',
                'data-placeholder' => 'Select site(s)', // @translate
                'multiple' => true,
                'id' => 'lc-sites',
            ],
            'options' => [
                'label' => 'Sites', // @translate
                'info' => 'Select site(s) to apply Local Contexts Notices.', // @translate
                'empty_option' => '',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'lc-sites',
            'required' => false,
        ]);
    }
}
