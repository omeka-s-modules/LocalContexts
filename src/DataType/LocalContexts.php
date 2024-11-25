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

        if ($label['image_url']) {
            $content = sprintf(
                "<div class='label'><img class='column image' src='%s'><div class='column text'>
                <div class='name'>%s</div><div class='description'>%s</div></div></div>",
                $escape($label['image_url']),
                $escape($label['name']),
                $escape($label['text']),
            );
        } else {
            $content = sprintf(
                "<div class='column text'><div class='name'>%s</div><div class='description'>%s</div></div>",
                $escape($label['name']),
                $escape($label['text']),
            );
        }
        // Link to source Local Contexts project if available
        if (isset($label['project_url'])) {
            return $hyperlink->raw($content, $label['project_url'], ['target' => '_blank']);
        } else {
            return $content;
        }
    }
}
