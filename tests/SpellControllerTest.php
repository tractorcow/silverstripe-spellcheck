<?php

if (class_exists('Phockito')) Phockito::include_hamcrest();

/**
 * Tests the {@see SpellController} class
 */
class SpellControllerTest extends FunctionalTest {

	protected $securityWasEnabled = false;

	public function setUp() {
		parent::setUp();
		Config::nest();
		Injector::nest();
		$this->securityWasEnabled = SecurityToken::is_enabled();

		// Check dependencies
		if (!class_exists('Phockito')) {
			$this->skipTest = true;
			return $this->markTestSkipped("These tests need the Phockito module installed to run");
		}

		// Reset config
		Config::inst()->update('SpellController', 'required_permission', 'CMS_ACCESS_CMSMain');
		Config::inst()->remove('SpellController', 'locales');
		Config::inst()->update('SpellController', 'locales', array('en_US', 'en_NZ', 'fr_FR'));
		Config::inst()->update('SpellController', 'enable_security_token', true);
		SecurityToken::enable();

		// Setup mock for testing provider
		$spellChecker = Phockito::mock('SpellProvider');
		Phockito::when($spellChecker)
			->checkWords('en_NZ', array('collor', 'colour', 'color', 'onee', 'correct'))
			->return(array('collor', 'color', 'onee'));
		Phockito::when($spellChecker)
			->checkWords('en_US', array('collor', 'colour', 'color', 'onee', 'correct'))
			->return(array('collor', 'colour', 'onee'));
		Phockito::when($spellChecker)
			->getSuggestions('en_NZ', 'collor')
			->return(array('collar', 'colour'));
		Phockito::when($spellChecker)
			->getSuggestions('en_US', 'collor')
			->return(array('collar', 'color'));
		Injector::inst()->registerService($spellChecker, 'SpellProvider');
	}

	public function tearDown() {
		if($this->securityWasEnabled) SecurityToken::enable();
		else SecurityToken::disable();
		Injector::unnest();
		Config::unnest();
		parent::tearDown();
	}

	/**
	 * Tests security ID check
	 */
	public function testSecurityID() {
		// Mock token
		$securityToken = SecurityToken::inst();
		$generator = new RandomGenerator();
		$token = $generator->randomToken('sha1');
		$session = array(
			$securityToken->getName() => $token
		);
		$tokenError = _t(
			'SpellController.SecurityMissing',
			'Your session has expired. Please refresh your browser to continue.'
		);

		// Test request sans token
		$response = $this->get('spellcheck', Injector::inst()->create('Session', $session));
		$this->assertEquals(400, $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals($tokenError, $jsonBody->error->errstr);

		// Test request with correct token (will fail with an unrelated error)
		$response = $this->get(
			'spellcheck/?SecurityID='.urlencode($token),
			Injector::inst()->create('Session', $session)
		);
		$jsonBody = json_decode($response->getBody());
		$this->assertNotEquals($tokenError, $jsonBody->error->errstr);

		// Test request with check disabled
		Config::inst()->update('SpellController', 'enable_security_token', false);
		$response = $this->get('spellcheck', Injector::inst()->create('Session', $session));
		$jsonBody = json_decode($response->getBody());
		$this->assertNotEquals($tokenError, $jsonBody->error->errstr);
	}

	/**
	 * Tests permission check
	 */
	public function testPermissions() {
		// Disable security ID for this test
		Config::inst()->update('SpellController', 'enable_security_token', false);
		$securityError = _t('SpellController.SecurityDenied', 'Permission Denied');

		// Test admin permissions
		Config::inst()->update('SpellController', 'required_permission', 'ADMIN');
		$this->logInWithPermission('ADMIN');
		$response = $this->get('spellcheck');
		$jsonBody = json_decode($response->getBody());
		$this->assertNotEquals($securityError, $jsonBody->error->errstr);

		// Test insufficient permissions
		$this->logInWithPermission('CMS_ACCESS_CMSMain');
		$response = $this->get('spellcheck');
		$this->assertEquals(403, $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals($securityError, $jsonBody->error->errstr);

		// Test disabled permissions
		Config::inst()->update('SpellController', 'required_permission', false);
		$response = $this->get('spellcheck');
		$jsonBody = json_decode($response->getBody());
		$this->assertNotEquals($securityError, $jsonBody->error->errstr);
	}

	/**
	 * Ensure that invalid input is correctly rejected
	 */
	public function testInputRejection() {
		// Disable security ID and permissions for this test
		Config::inst()->update('SpellController', 'enable_security_token', false);
		Config::inst()->update('SpellController', 'required_permission', false);
		$invalidRequest = _t('SpellController.InvalidRequest', 'Invalid request');

		// Test checkWords acceptance
		$dataCheckWords = array(
			'id' => 'c0',
			'method' => 'checkWords',
			'params' => array(
				'en_NZ',
				array('collor', 'colour', 'color', 'onee', 'correct')
			)
		);
		$response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataCheckWords)));
		$this->assertEquals(200,  $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals(array("collor", "color", "onee"), $jsonBody->result);

		// Test getSuggestions acceptance
		$dataGetSuggestions = array(
			'id' => 'c1',
			'method' => 'getSuggestions',
			'params' => array(
				'en_NZ',
				'collor'

			)
		);
		$response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataGetSuggestions)));
		$this->assertEquals(200,  $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals(array('collar', 'colour'), $jsonBody->result);

		// Test non-ajax rejection
		$response = $this->post('spellcheck', array('json_data' => json_encode($dataCheckWords)));
		$this->assertEquals(400,  $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals($invalidRequest, $jsonBody->error->errstr);

		// Test incorrect method
		$dataInvalidMethod = $dataCheckWords;
		$dataInvalidMethod['method'] = 'validate';
		$response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataInvalidMethod)));
		$this->assertEquals(400,  $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals(
			_t('SpellController.UnsupportedMethod', "Unsupported method '{method}'", array('method' => 'validate')),
			$jsonBody->error->errstr
		);

		// Test missing method
		$dataNoMethod = $dataCheckWords;
		unset($dataNoMethod['method']);
		$response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataNoMethod)));
		$this->assertEquals(400,  $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals($invalidRequest, $jsonBody->error->errstr);

		// Test unsupported locale
		$dataWrongLocale = $dataCheckWords;
		$dataWrongLocale['params'] = array(
			'de_DE',
			array('collor', 'colour', 'color', 'onee', 'correct')
		);
		$response = $this->post('spellcheck', array('ajax' => 1, 'json_data' => json_encode($dataWrongLocale)));
		$this->assertEquals(400,  $response->getStatusCode());
		$jsonBody = json_decode($response->getBody());
		$this->assertEquals(_t('SpellController.InvalidLocale', 'Not supported locale'), $jsonBody->error->errstr);
	}
}
