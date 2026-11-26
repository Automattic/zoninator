<?php
/**
 * The error codes the zoninator/v1 REST API returns.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

/**
 * The codes in the zoninator/v1 REST API's error responses. Clients match on
 * these, so they don't change.
 *
 * @since 1.0.0
 */
final class ErrorCodes {

	/**
	 * A post isn't one the user may put in a zone.
	 */
	public const INVALID_POST_ID = 'invalid-post-id';

	/**
	 * The zone ID was 0.
	 */
	public const ZONE_ID_REQUIRED = 'zone-id-required';

	/**
	 * The zone's posts couldn't be read.
	 */
	public const ZONE_FEED_ERROR = 'zone-feed-error';

	/**
	 * The zones couldn't be read.
	 */
	public const ZONES_READ_ERROR = 'zones-read-error';

	/**
	 * The user may not do this.
	 */
	public const PERMISSION_DENIED = 'permission-denied';

	/**
	 * There's no such zone.
	 */
	public const ZONE_NOT_FOUND = 'zone-not-found';

	/**
	 * A new zone was given no slug.
	 */
	public const ZONE_EMPTY_SLUG = 'zone-empty-slug';

	/**
	 * The zone can't hold more posts.
	 */
	public const ZONE_FULL = 'zone-full';

	/**
	 * Someone else holds the zone's lock.
	 */
	public const ZONE_LOCKED = 'zone-locked';
}
