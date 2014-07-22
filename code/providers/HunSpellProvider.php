<?php

/**
 * Implements spellcheck using the hunspell library
 */
class HunSpellProvider implements SpellProvider {

	public function checkWords($locale, $words) {
		return array();
	}

	public function getSuggestions($locale, $word) {
		return array();
	}

}
