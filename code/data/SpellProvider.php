<?php

interface SpellProvider {

	/**
	 * Spellchecks an array of words.
	 *
	 * @param string $locale Locale code to check
	 * @param array $words List of words to spellcheck.
	 * @return array List of misspelled words.
	 */
	public function checkWords($locale, $words);

	/**
	 * Returns suggestions of for a specific word.
	 *
	 * @param string $locale Locale code to check
	 * @param string $word Specific word to get suggestions for.
	 * @return array List of suggestions for the specified word.
	 */
	public function getSuggestions($locale, $word);
}
