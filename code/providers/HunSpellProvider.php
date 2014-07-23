<?php

/**
 * Implements spellcheck using the hunspell library
 */
class HunSpellProvider implements SpellProvider {

	/**
	 * See https://gist.github.com/sebskuse/1244667
	 *
	 * @var string
	 * @config
	 */
	private static $pattern = "/^(?P<type>&)\s(?P<original>\w+)\s(?P<count>\d+)\s(?P<offset>\d+):\s(?P<misses>.*+)$/u";


	/**
	 * Invoke hunspell library
	 *
	 * @param string $locale
	 * @param string $input Input text
	 * @param string $stdout output
	 * @param string $stderr error
	 * @return int Exit code
	 */
	protected function invoke($locale, $input, &$stdout, &$stderr) {
		// Prepare arguments
		$command = 'hunspell -d ' . escapeshellarg($locale);
		$descriptorSpecs = array(
			0 => array("pipe", "r"),
			1 => array("pipe", "w"),
			2 => array("pipe", "w")
		);
		$env = array(
			'LANG' => $locale . '.utf-8'
		);
		// Invoke command
		$proc = proc_open($command, $descriptorSpecs, $pipes, null, $env);
		if (!is_resource($proc)) return 255;

		// Send content as input
		fwrite($pipes[0], $input);
		fclose($pipes[0]);

		// Get output
		$stdout = stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		// Get result
		return proc_close($proc);
	}

	/**
	 * Get results from hunspell
	 *
	 * @param string $locale
	 * @param array $words
	 * @return array List of incorrect words, each with their list of suggestions
	 * @throws SpellException
	 */
	protected function getResults($locale, $words) {
		// Invoke HunSpell
		$input = implode(' ', $words);
		$return = $this->invoke($locale, $input, $stdout, $stderr);
		if($stderr) {
			throw new SpellException($stderr, 500);
		} elseif($return) {
			throw new SpellException("An unidentified error has occurred", 500);
		}

		// Parse results
		$pattern = Config::inst()->get(__CLASS__, 'pattern');
		$results = array();
		foreach(preg_split('/$\R?^/m', $stdout) as $line) {
			if(preg_match($pattern, $line, $matches)) {
				$results[$matches['original']] = explode(', ', $matches['misses']);
			}
		}
		return $results;
	}

	public function checkWords($locale, $words) {
		$results = $this->getResults($locale, $words);
		return array_keys($results);
	}

	public function getSuggestions($locale, $word) {
		$results = $this->getResults($locale, array($word));
		if(isset($results[$word])) return $results[$word];
	}
}
