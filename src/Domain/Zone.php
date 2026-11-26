<?php
/**
 * A zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * A zone: a named, ordered list of posts that editors curate by hand.
 *
 * A zone has a slug, a name and details. The details always include a
 * description, and a site can add its own. This class holds the rules for
 * making a zone and changing one; it never changes itself, so each change
 * gives a new Zone.
 *
 * @since 1.0.0
 */
final class Zone {

	/**
	 * Constructor. To make a new zone with the rules applied, use create().
	 *
	 * @param int|null             $id      The zone's ID, or null if it hasn't been saved yet.
	 * @param string               $slug    The zone's slug.
	 * @param string               $name    The zone's name.
	 * @param array<string, mixed> $details The zone's details, by name.
	 */
	public function __construct(
		private readonly ?int $id,
		private readonly string $slug,
		private readonly string $name,
		private readonly array $details,
	) {
	}

	/**
	 * Makes a new zone. Its name is its slug unless it's given one, and it has
	 * every default detail the caller didn't set.
	 *
	 * @param string               $slug            The zone's slug.
	 * @param string               $name            The zone's name, or '' to use the slug.
	 * @param array<string, mixed> $details         Details to set, by name.
	 * @param array<string, mixed> $default_details Every zone's details, with their default values.
	 * @return self
	 *
	 * @throws InvalidZoneException If the slug is empty.
	 */
	public static function create( string $slug, string $name, array $details, array $default_details ): self {
		if ( '' === $slug ) {
			throw new InvalidZoneException( 'A zone needs a slug.' );
		}

		return new self( null, $slug, '' === $name ? $slug : $name, array_merge( $default_details, $details ) );
	}

	/**
	 * Gets this zone with some of it changed.
	 *
	 * An empty name or slug leaves the current one. A detail the caller
	 * doesn't send keeps its current value if it's one of the default
	 * details, and is dropped if it isn't.
	 *
	 * @param array{name?: string, slug?: string, details?: array<string, mixed>} $changes         What to change.
	 * @param array<string, mixed>                                                $default_details Every zone's details, with their default values.
	 * @return self
	 */
	public function with_changes( array $changes, array $default_details ): self {
		$kept = array_intersect_key( $this->details, $default_details );

		return new self(
			$this->id,
			empty( $changes['slug'] ) ? $this->slug : (string) $changes['slug'],
			empty( $changes['name'] ) ? $this->name : (string) $changes['name'],
			array_merge( $default_details, $kept, empty( $changes['details'] ) ? array() : (array) $changes['details'] )
		);
	}

	/**
	 * Gets the zone's ID.
	 *
	 * @return int|null The ID, or null if the zone hasn't been saved yet.
	 */
	public function get_id(): ?int {
		return $this->id;
	}

	/**
	 * Gets the zone's slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Gets the zone's name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Gets the zone's details.
	 *
	 * @return array<string, mixed> The details, by name.
	 */
	public function get_details(): array {
		return $this->details;
	}

	/**
	 * Gets the zone's description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return (string) ( $this->details['description'] ?? '' );
	}
}
