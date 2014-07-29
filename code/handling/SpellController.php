<?php

/**
 * Controller to handle requests for spellchecking
 */
class SpellController extends Controller {

	/**
	 * Locales to spellcheck
	 *
	 * @var array
	 * @config
	 */
	private static $locales = array();

	/**
	 * Necessary permission required to spellcheck. Set to empty or null to disable restrictions.
	 *
	 * @var string
	 * @config
	 */
	private static $required_permission = 'CMS_ACCESS_CMSMain';

	/**
	 * Enable security token for spellchecking
	 *
	 * @var bool
	 * @config
	 */
	private static $enable_security_token = true;

	/**
	 * Dependencies required by this controller
	 *
	 * @var array
	 * @config
	 */
	private static $dependencies = array(
		'Provider' => '%$SpellProvider'
	);

	/**
	 * Spellcheck provider
	 *
	 * @var SpellProvider
	 */
	protected $provider = null;

	/**
	 * Parsed request data
	 *
	 * @var array|null Null if not set or an array if parsed
	 */
	protected $data = null;

	/**
	 * Get the current provider
	 *
	 * @return SpellProvider
	 */
	public function getProvider() {
		return $this->provider;
	}

	/**
	 * Gets locales to spellcheck for
	 *
	 * @return array
	 */
	public static function get_locales() {
		// Default to current locale if none configured
		return self::config()->locales ?: array(i18n::get_locale());
	}

	/**
	 * Set the provider to use
	 *
	 * @param SpellProvider $provider
	 * @return $this
	 */
	public function setProvider(SpellProvider $provider) {
		$this->provider = $provider;
		return $this;
	}

	/**
	 * Parse the output response
	 *
	 * @param string $id Request ID
	 * @param array|null $result Result data
	 * @param array|null $error Error data
	 * @param int $code HTTP Response code
	 */
	protected function result($id, $result, $error = null, $code = 200) {
		$this->response->setStatusCode($code);
		$this->response->setBody(json_encode(array(
			'id' => $id ? preg_replace('/\W/', '', $id) : null, // Cleanup id
			'result' => $result,
			'error' => $error
		)));
		return $this->response;
	}

	protected function success($result) {
		$data = $this->getRequestData();
		return $this->result($data['id'], $result);
	}

	/**
	 * Set the error
	 *
	 * @param string $message
	 * @param int $code HTTP error code
	 */
	protected function error($message, $code) {
		$error = array(
			'errstr' => $message,
			'errfile' => '',
			'errline' => null,
			'errcontext' => '',
			'level' => 'FATAL'
		);
		return $this->result(null, null, $error, $code);

	}

	public function index() {
		$this->setHeaders();

		// Check security token
		if(self::config()->enable_security_token && !SecurityToken::inst()->checkRequest($this->request)) {
			return $this->error(
				_t(__CLASS__.'.SecurityMissing', 'Your session has expired. Please refresh your browser to continue.'),
				400
			);
		}

		// Check permission
		$permission = self::config()->required_permission;
		if($permission && !Permission::check($permission)) {
			return $this->error(_t(__CLASS__.'.SecurityDenied', 'Permission Denied'), 403);
		}

		// Check data
		$data = $this->getRequestData();
		if(empty($data)) {
			return $this->error(_t(__CLASS__.'.MissingData', "Could not get raw post data"), 400);
		}

		// Check params and request type
		if(!Director::is_ajax() || empty($data['method']) || empty($data['params']) || count($data['params']) < 2) {
			return $this->error(_t(__CLASS__.'.InvalidRequest', 'Invalid request'), 400);
		}

		// Check locale
		$params = $data['params'];
		$locale = $params[0];
		if(!in_array($locale, self::get_locales())) {
			return $this->error(_t(__CLASS__.'.InvalidLocale', 'Not supported locale'), 400);
		}

		// Check provider
		$provider = $this->getProvider();
		if(empty($provider)) {
			return $this->error(_t(__CLASS__.'.MissingProviders', "No spellcheck module installed"), 500);
		}

		// Perform action
		try {
			$method = $data['method'];
			$words = $params[1];
			switch($method) {
				case 'checkWords':
					return $this->success($provider->checkWords($locale, $words));
				case 'getSuggestions':
					return $this->success($provider->getSuggestions($locale, $words));
				default:
					return $this->error(
						_t(
							__CLASS__.'.UnsupportedMethod',
							"Unsupported method '{method}'",
							array('method' => $method)
						),
						400
					);
			}
		} catch(SpellException $ex) {
			return $this->error($ex->getMessage(), $ex->getCode());
		}
	}

	/**
	 * Ensures the response has the correct headers
	 */
	protected function setHeaders() {
		// Set headers
		HTTP::set_cache_age(0);
		HTTP::add_cache_headers($this->response);
		$this->response
			->addHeader('Content-Type', 'application/json')
			->addHeader('Content-Encoding', 'UTF-8')
			->addHeader('X-Content-Type-Options', 'nosniff');
	}

	/**
	 * Get request data
	 *
	 * @return array Parsed data with an id, method, and params key
	 */
	protected function getRequestData() {
		// Check if data needs to be parsed
		if($this->data === null) {
			// Parse data from input
			$result = $this->request->requestVar('json_data')
				?: file_get_contents("php://input");
			$this->data = $result ? json_decode($result, true) : array();
		}
		return $this->data;
	}

}
