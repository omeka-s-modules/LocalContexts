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
            $notices = $view->setting('lc_notices');
			foreach ($notices as $notice) {
				$notice = json_decode($notice, true);
				$noticeName = $notice['name'];
				$noticeArray[$noticeName] = $noticeName;
			}
	
			$setLocalContexts = $block ? $block->dataValue('localContexts') : '';
	        $select = new Select('o:block[__blockIndex__][o:data][localContexts]');
	        $select->setValueOptions($noticeArray)->setValue($setLocalContexts);

	        $html = '<div class="field">';
	        $html .= '<div class="field-meta"><label>' . $view->translate('Local Contexts') . '</label></div>';
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
			$notices = $view->setting('lc_notices');
			foreach ($notices as $notice) {
				$notice = json_decode($notice, true);
				if ($notice['name'] == $localContextContent) {
					$noticeArray['name'] = $notice['name'];
	                $noticeArray['image_url'] = $notice['image_url'];
	                $noticeArray['text'] = $notice['text'];
					$contentArray[] = $noticeArray;
				}
			}

			return $view->partial('local-contexts/common/block-layout/lc-content-public', [
	            'lc_content' => $contentArray,
	        ]);
		}
	}
}
