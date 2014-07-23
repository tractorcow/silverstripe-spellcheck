<?php

/**
 * An error which should be presented to users if raised
 */
class SpellException extends Exception {

	/**
	 * @param string $message
	 * @param int $code HTTP error code
	 * @param Exception $previous
	 */
	public function __construct($message, $code, $previous) {
		parent::__construct($message, $code, $previous);
	}
}
