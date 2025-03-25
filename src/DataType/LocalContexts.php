<?php
namespace LocalContexts\DataType;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\Literal;
use Omeka\Entity\Value;
use Laminas\Form\Element\Select;
use Laminas\View\Renderer\PhpRenderer;

class LocalContexts extends Literal
{

    public function getName()
    {
        return 'lc_content';
    }

    public function getLabel()
    {
        return 'Local Contexts content';
    }

    public function render(PhpRenderer $view, ValueRepresentation $value)
    {
        $hyperlink = $view->plugin('hyperlink');
        $escape = $view->plugin('escapeHtml');
        $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));

        $label = json_decode($value->value(), 1);

        return $view->partial('local-contexts/common/linked-notice.phtml', ['notice' => $label]); 
    }
}
