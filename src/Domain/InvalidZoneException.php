<?php
/**
 * Thrown when a zone breaks a rule.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Domain;

use InvalidArgumentException;

/**
 * A zone can't be made as asked, for example without a slug.
 *
 * @since 1.0.0
 */
final class InvalidZoneException extends InvalidArgumentException implements ZoninatorExceptionInterface {
}
