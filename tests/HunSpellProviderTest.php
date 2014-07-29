<?php

/**
 * Tests the {@see HunSpellProvider} class
 */
class HunSpellProviderTest extends SapphireTest {

	/**
	 * Assert that all needles are in the haystack
	 *
	 * @param array $needles
	 * @param array $haystack
	 */
	protected function assertArrayContains($needles, $haystack) {
		$overlap = array_intersect($needles, $haystack);
		$this->assertEquals($overlap, $needles, "Assert that array contains all values specified");
	}

	public function testCheckWords() {
		$provider = new HunSpellProvider();
		$result = $provider->checkWords('en_US', array('collor', 'one', 'twoo', 'three'));
		$this->assertArrayContains(
			array('collor', 'twoo'),
			$result
		);
		$result = $provider->checkWords('en_US', array('basketball'));
		$this->assertEmpty($result);
	}

	public function testGetSuggestions() {
		$provider = new HunSpellProvider();
		$result = $provider->getSuggestions('en_US', 'collor');
		$this->assertArrayContains(
			array('color', 'collar', 'coll or', 'coll-or', 'collator'),
			$result
		);
		$result = $provider->getSuggestions('en_US', 'basketball');
		$this->assertEmpty($result);
	}
}
