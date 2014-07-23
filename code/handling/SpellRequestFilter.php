<?php

class SpellRequestFilter implements RequestFilter {

	/**
	 * HtmlEditorConfig name to use
	 *
	 * @var string
	 * @config
	 */
	private static $editor = 'cms';

	public function preRequest(\SS_HTTPRequest $request, \Session $session, \DataModel $model) {
		// Check languages to set
		$languages = array();
		foreach(SpellController::config()->locales as $locale) {
			$languages[] = i18n::get_locale_name($locale).'='.$locale;
		}

		// Set settings
		$editor = Config::inst()->get(__CLASS__, 'editor');
		HtmlEditorConfig::get($editor)->enablePlugins('spellchecker');
		HtmlEditorConfig::get($editor)->addButtonsToLine(2, 'spellchecker');
		HtmlEditorConfig::get($editor)->setOption('spellchecker_rpc_url', 'spellcheck/');
		HtmlEditorConfig::get($editor)->setOption('browser_spellcheck', false);
		HtmlEditorConfig::get($editor)->setOption('spellchecker_languages', '+'.implode(', ', $languages));
		return true;
	}

	public function postRequest(\SS_HTTPRequest $request, \SS_HTTPResponse $response, \DataModel $model) {
		return true;
	}
}
