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
                if (isset($project['project_title'])) {
                    $projectName = $project['project_title'];
                } elseif (isset($project['project_url'])) {
                    $projectName = $project['project_url'];
                } elseif ($project[0]['name'] == 'Open to Collaborate Notice') {
                    $projectName = 'Open to Collaborate Notice';
                } else {
                    $projectName = '[no project title]';
                }
				$projectArray[$projectName] = $projectName;
			}
	
			$setLC = $block ? $block->dataValue('localContexts') : '';
	        $selectLC = new Select('o:block[__blockIndex__][o:data][localContexts]');
	        $selectLC->setValueOptions($projectArray)->setValue($setLC);
            $setLanguage = $block ? $block->dataValue('language') : 'en-US';
            $selectLanguage = new Select('o:block[__blockIndex__][o:data][language]');
            $languageArray = array(
                'All' => 'All available languages',
                'English' => 'English',
                'French' => 'French',
                'Spanish' => 'Spanish',
                'Māori' => 'Māori'
            );
	        $selectLanguage->setValueOptions($languageArray)->setValue($setLanguage);

	        $html = '<div class="field">';
	        $html .= '<div class="field-meta"><label>' . $view->translate('LC project name') . '</label></div>';
	        $html .= '<div class="inputs">' . $view->formSelect($selectLC) . '</div>';
	        $html .= '</div><div class="field">';
	        $html .= '<div class="field-meta"><label>' . $view->translate('LC Language') . '</label></div>';
	        $html .= '<div class="inputs">' . $view->formSelect($selectLanguage) . '</div>';
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
        $lcLanguage = $block->dataValue('language');
		
		if ($view->setting('lc_notices')) {
			$projects = $view->setting('lc_notices');
			foreach ($projects as $key => $project) {
				if ((isset($project['project_title']) && $project['project_title'] == $localContextContent) || $project[0]['name'] == $localContextContent) {
                    $projectArray = array();
                    foreach ($project as $key => $content) {
                        if (is_int($key)) {
                            // Only print content in selected language. If 'All', print everything
                            if ((isset($content['language']) && $content['language'] == $lcLanguage) 
                            || (!isset($content['language']) && $lcLanguage == 'English') 
                            || $lcLanguage == 'All') {
                                $projectArray[] = $content;
                            }
                        }
                    }

                    // Don't print project URL if element value array is empty
                    if (isset($project['project_url']) && $projectArray) {
                        $projectArray['project_url'] = $project['project_url'];
                        $projectArray['project_title'] = $project['project_title'];
                    }

                    $lcArray = array();
                    if ($projectArray) {
                        $lcHtml = \LocalContexts\Module::renderLCNoticeHtml($projectArray, $key);
                        $lcArray['label'] = $lcHtml;
                        $lcArray['value'] = json_encode($project);
                        $contentArray[] = $lcArray;
                    }

                    break;
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
