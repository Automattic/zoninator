<?php
/**
 * Thrown when a zone couldn't be saved or deleted.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Domain;

use RuntimeException;

/**
 * The storage refused to save or delete a zone, for example because another
 * zone has its name.
 *
 * @since 1.0.0
 */
final class ZoneNotSavedException extends RuntimeException implements ZoninatorExceptionInterface {

	/**
	 * The storage's own code for what went wrong.
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * Constructor.
	 *
	 * @param string $message    The storage's message.
	 * @param string $error_code The storage's own code for what went wrong.
	 */
	public function __construct( string $message, string $error_code ) {
		parent::__construct( $message );

		$this->error_code = $error_code;
	}

	/**
	 * Gets the storage's own code for what went wrong.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}
}
