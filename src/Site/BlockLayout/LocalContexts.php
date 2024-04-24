<?php
namespace LocalContexts\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Form\Element\Select;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Settings\Settings;

class LocalContexts extends AbstractBlockLayout
{
	/**
     * @var Settings
     */
    protected $settings;

	public function getLabel() {
		return 'Local Contexts'; // @translate
	}

	public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    ) {
        if ($view->setting('lc_notices')) {        
            $projects = $view->setting('lc_notices');
            foreach ($projects as $project) {
                $project = json_decode($project, true);
                if (isset($project['project_title'])) {
                    $projectName = $project['project_title'];
                } elseif (isset($project['project_url'])) {
                    $projectName = $project['project_url'];
                } elseif ($project[0]['name'] == 'Open to Collaborate Notice') {
                    $projectName = 'Open to Collaborate notice';
                } else {
                    $projectName = '[no project title]';
                }
				$projectArray[$projectName] = $projectName;
			}
	
			$setLocalContexts = $block ? $block->dataValue('localContexts') : '';
	        $select = new Select('o:block[__blockIndex__][o:data][localContexts]');
	        $select->setValueOptions($projectArray)->setValue($setLocalContexts);

	        $html = '<div class="field">';
	        $html .= '<div class="field-meta"><label>' . $view->translate('Local Contexts project name') . '</label></div>';
	        $html .= '<div class="inputs">' . $view->formSelect($select) . '</div>';
	        $html .= '</div>'; 
        } else {
			$html = '<div>No Local Contexts content set.</div>';
		}

        return $html;
    }

	public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
	{	
        $localContextContent = $block->dataValue('localContexts');
		if (!$localContextContent) {
            return '';
        }
		
		if ($view->setting('lc_notices')) {
			$projects = $view->setting('lc_notices');
			foreach ($projects as $project) {
				$project = json_decode($project, true);
				if ((isset($project['project_title']) && $project['project_title'] == $localContextContent) || $project[0]['name'] == 'Open to Collaborate Notice') {
                    $contentArray = $project;
                } else {
                    $contentArray = [];
                }
			}
		} else {
            return '';
        }
        
        $view->headLink()->appendStylesheet($view->assetUrl('css/local-contexts.css', 'LocalContexts'));
        return $view->partial('local-contexts/common/block-layout/lc-content-public', [
            'lc_content' => $contentArray,
        ]);
	}
}
