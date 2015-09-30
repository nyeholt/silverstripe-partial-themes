<?php

/**
 * An extension that helps out with the application of a "Partial Theme"
 * 
 * A Partial Theme is one in which only certain templates are defined, that
 * are considered to 'override' those defined in the main theme currently 
 * applied to the site. This allows for a subsection to define several templates
 * differently from the 'main' theme but without needing to copy large chunks of
 * code (eg the master Page template) 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class PartialThemesExtension extends DataExtension {
	
	private static $db = array(
		'PartialTheme'		=> 'Varchar',
		'AppliedTheme'			=> 'Varchar',
	);
	
	public function updateSettingsFields(FieldList $fields) {
		
		$systemThemes = SiteConfig::current_site_config()->getAvailableThemes();
		
		$partials = $themes = array('' => '','none' => '(none)');
		foreach ($systemThemes as $key => $themeName) {
			if (file_exists(Director::baseFolder().'/themes/' . $themeName.'/templates/Page.ss')) {
				$themes[$key] = $themeName;
			} else {
				$partials[$key] = $themeName;
			}
		}

		$themeDropdownField = new DropdownField("AppliedTheme", 'Applied Theme', $themes);
		$fields->addFieldToTab('Root.Theme', $themeDropdownField);
		$current = $this->appliedTheme();
		if ($current) {
			$themeDropdownField->setRightTitle('Current effective theme: ' . $current);
		} else {
			$themeDropdownField->setRightTitle('Using default site theme');
		}
		
		$themeDropdownField = new DropdownField("PartialTheme", 'Partial Theme', $partials);
		$fields->addFieldToTab('Root.Theme', $themeDropdownField);
		
		$current = $this->appliedPartialTheme();
		if ($current) {
			$themeDropdownField->setRightTitle('Current effective partial theme: ' . $current);
		} else {
			$themeDropdownField->setRightTitle('Please only use a specific applied theme OR a partial theme, not both!');
		}
	}
	
	public function appliedTheme() {
		if ($this->owner->AppliedTheme) {
			return $this->owner->AppliedTheme;
		}

		// if we're in multisites, the owner Site object has a Theme set itself
		if ($this->owner->Theme) {
			return $this->owner->Theme;
		}

		if ($this->owner->ParentID) {
			return $this->owner->Parent()->appliedTheme();
		}
	}

	public function appliedPartialTheme() {
		if ($this->owner->PartialTheme == "none") {
			return;
		}
		if ($this->owner->PartialTheme) {
			return $this->owner->PartialTheme;
		}
		if ($this->owner->ParentID) {
			return $this->owner->Parent()->appliedPartialTheme();
		}
	}

	public function overridePartialTemplates($viewer, $templates = array(), $action = null) {
		// find the theme from the current page
		$current = Controller::curr();
		$partial = null;
		if ($current->hasMethod('appliedPartialTheme')) {
			$partial = $current->appliedPartialTheme();
		}
		
		// check for alternative 'sub' templates
		if (!$partial) {
			return $viewer;
		}

		$alts = $this->findAlternate($partial, $templates, $action);
		if ($alts) {
			if (isset($alts['Layout'])) {
				$viewer->setTemplateFile('Layout', $alts['Layout']);
			}
			if (isset($alts['main'])) {
				$viewer->setTemplateFile('main', $alts['main']);
			}
			if (isset($alts['Includes'])) {
				$viewer->setTemplateFile('main', $alts['Includes']);
			}
		}

		return $viewer;
	}
	
	public function partialRenderWith($template, $arguments = null) {
		if(!is_object($template)) {
			$templates = is_array($template) ? $template : array($template); 
			// we silence errors here because we know that the template might not exist in the applied
			// theme but only in the overridden theme. 
			$viewer = @new SSViewer($templates);
		}

		$viewer = $this->overridePartialTemplates($viewer, $template);
		return $this->owner->renderWith($viewer, $arguments);
	}

	public function partialInclude($template, $data = null) {
		$extraParams = array();
		if (is_string($data)) {
			parse_str($data, $extraParams);
		}
		$data = null;
		if ($this->owner->customisedObject) {
			$data = $this->owner->customise($this->owner->customisedObject);
		} else {
			$data = $this->owner;
		}
		
		foreach ($extraParams as $key => $val) {
			$data->$key = $val;
		}

		$arguments = array();

		$v = new SSViewer("dummy.ss");
		$v->setTemplateFile("main", null);
		$v = $this->overridePartialTemplates($v, array($template));
		
		// check that there was an override
		$templates = $v->templates();
		if (!strlen($templates['main'])) {
			return '';
		}

		return $v->process($data, $arguments);
	}
	
	
	/**
	 * Find an alternative template for the current page in a separate theme
	 * 
	 * @param type $inTheme 
	 */
	protected function findAlternate($inTheme, $templates = array(), $action = null) {

		if (is_array($templates)) {
			if (count($templates) == 0) {
				// duplicated from Controller::getViewer
				$parentClass = $this->owner->class;
				if ($action && $action != 'index') {
					$parentClass = $this->owner->class;
					while ($parentClass != "Controller" && $parentClass != 'DataObject') {
						$templates[] = strtok($parentClass, '_') . '_' . $action;
						$parentClass = get_parent_class($parentClass);
					}
				}
				// Add controller templates for inheritance chain
				$parentClass = $this->owner->class;

				// This is slightly different from Controller::getViewer to prevent
				// picking up the cms/controller template in the findTemplates call
				while ($parentClass != "ContentController" && $parentClass != 'DataObject') {
					$templates[] = strtok($parentClass, '_');
					$parentClass = get_parent_class($parentClass);
				}
			}

			// remove duplicates
			$templates = array_unique($templates);
		}

		$other = SS_TemplateLoader::instance()->findTemplates($templates, $inTheme);
		
		// check that the theme's path is actually in the returned paths
		foreach ($other as $key => $path) {
			if (strpos($path, 'themes/' . $inTheme) === false) {
				unset($other[$key]);
			}
		}

		if (count($other)) {
			return $other;
		}
	}
	
	/**
	 *
	 * @var ThemeHelper
	 */
	protected $themeHelpers;
	
	protected function getThemeHelper($themeName) {
		if (!isset($this->themeHelpers[$themeName])) {
			$theme = false;
                        
                        $themeName = preg_replace('/-/', "", $themeName);
			// see if there's a theme specific controller
			$helperClass = ucfirst($themeName . 'Helper');                        
			if (class_exists($helperClass)) {
				$theme = $helperClass::create();
			}
			$this->themeHelpers[$themeName] = $theme;
		}
		
		return $this->themeHelpers[$themeName];
	}

	public function onBeforeInit() {
		$applied = $partial = '';
		
		if ($this->owner->hasMethod('appliedTheme')) {
			$applied = $this->owner->appliedTheme();
		}
		if ($this->owner->hasMethod('appliedPartialTheme')) {
			$partial = $this->owner->appliedPartialTheme();
		}
		
		if (strlen($applied)) {
			SSViewer::set_theme($applied);
			if ($helper = $this->getThemeHelper($applied)) {
				$helper->beforeInit();
			}
		}
		if (strlen($partial)) {
			if ($helper = $this->getThemeHelper($partial)) {
				$helper->beforeInit();
			}
		}
	}
	
	public function onAfterInit() {
		$applied = $partial = '';
		if ($this->owner->hasMethod('appliedTheme')) {
			$applied = $this->owner->appliedTheme();
		}
		if ($this->owner->hasMethod('appliedPartialTheme')) {
			$partial = $this->owner->appliedPartialTheme();
		}
		
		if (strlen($applied)) {
			if ($helper = $this->getThemeHelper($applied)) {
				$helper->afterInit();
			}
		}
		if (strlen($partial)) {
			if ($helper = $this->getThemeHelper($partial)) {
				$helper->afterInit();
			}
		}
	}

	/**
	 * Return the applied themes as a string of classes
	 *
	 * @return string
	 */
	public function getThemeHierarchyClasses(){
		$classes = '';

		if ($this->owner->hasMethod('appliedTheme') && $this->owner->appliedTheme()) {
			$classes .= $this->owner->appliedTheme() . '-theme';
		}
		if ($this->owner->hasMethod('appliedPartialTheme') && $this->owner->appliedPartialTheme()) {
			$classes .= ' '. $this->owner->appliedPartialTheme() . '-theme';
		}

		return $classes;
	}
}
