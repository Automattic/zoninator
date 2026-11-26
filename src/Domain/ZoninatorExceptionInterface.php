<?php
/**
 * What every exception Zoninator throws has in common.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

use Throwable;

/**
 * Marks an exception as one of Zoninator's own, so code that changes zones
 * can catch them all at once: catch ( ZoninatorExceptionInterface $exception ).
 *
 * Every exception in this namespace implements it.
 *
 * @since 1.0.0
 */
interface ZoninatorExceptionInterface extends Throwable {
}
