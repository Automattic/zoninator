<?php
/**
 * Zones kept as taxonomy terms.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Domain\Zone;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneSettingsInterface;
use WP_Term;

/**
 * Keeps each zone as a term in the zoninator_zones taxonomy, with its
 * details serialised into the term's description.
 *
 * @since 1.0.0
 * @internal Use Automattic\Zoninator\Application\ZoneManager instead.
 */
final class TermZoneRepository implements ZoneRepositoryInterface {

	/**
	 * Says which details every zone has.
	 *
	 * @var ZoneSettingsInterface
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param ZoneSettingsInterface $settings Says which details every zone has.
	 */
	public function __construct( ZoneSettingsInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Finds a zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @return Zone|null The zone, or null if there's none with that ID.
	 */
	public function find( int $zone_id ): ?Zone {
		$term = get_term_by( 'id', $zone_id, Taxonomy::NAME );

		return $term ? new Zone( $term->term_id, $term->slug, $term->name, $this->read_details( $term->description ) ) : null;
	}

	/**
	 * Saves a new zone.
	 *
	 * @param Zone $zone The zone, which has no ID yet.
	 * @return Zone The zone as it was saved, with its ID.
	 *
	 * @throws ZoneNotSavedException If the zone couldn't be saved.
	 */
	public function insert( Zone $zone ): Zone {
		$args = array(
			'slug'        => $zone->get_slug(),
			'description' => maybe_serialize( $zone->get_details() ),
		);

		// Filterize to allow other inputs
		$args = apply_filters( 'zoninator_insert_zone', $args );

		// wp_insert_term() expects slashed data, as it unslashes the name and description.
		return $this->saved( wp_insert_term( wp_slash( $zone->get_name() ), Taxonomy::NAME, wp_slash( $args ) ) );
	}

	/**
	 * Saves changes to a zone.
	 *
	 * @param Zone $zone The zone, with its ID.
	 * @return Zone The zone as it was saved.
	 *
	 * @throws ZoneNotSavedException If the zone couldn't be saved.
	 */
	public function update( Zone $zone ): Zone {
		$zone_id = (int) $zone->get_id();
		$args    = array(
			'name'        => $zone->get_name(),
			'slug'        => $zone->get_slug(),
			'description' => maybe_serialize( $zone->get_details() ),
		);

		// Filterize to allow other inputs
		$args = apply_filters( 'zoninator_update_zone', $args, $zone_id, $this->get_term( $zone_id ) );

		// wp_update_term() expects slashed data, as it unslashes the name and description.
		return $this->saved( wp_update_term( $zone_id, Taxonomy::NAME, wp_slash( $args ) ) );
	}

	/**
	 * Deletes a zone. Its posts are left alone.
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @throws ZoneNotSavedException If the zone couldn't be deleted.
	 */
	public function delete( int $zone_id ): void {
		$deleted = wp_delete_term( $zone_id, Taxonomy::NAME );

		if ( ! $deleted || is_wp_error( $deleted ) ) {
			throw new ZoneNotSavedException( 'The zone could not be deleted.', 'delete-zone' );
		}

		do_action( 'zoninator_delete_zone', $zone_id );
	}

	/**
	 * Gets a zone's term, with each of the zone's details as a property.
	 *
	 * @param int $zone_id Zone ID.
	 * @return WP_Term|false The term, or false if there's no such zone.
	 */
	public function get_term( int $zone_id ) {
		$term = get_term_by( 'id', $zone_id, Taxonomy::NAME );

		return $term ? $this->fill_details( $term ) : false;
	}

	/**
	 * Adds each of a zone's details to its term, as a property. The
	 * description property becomes the zone's description; the term stores
	 * every detail there, serialised.
	 *
	 * @param WP_Term|mixed $term The zone's term. get_terms() gives IDs or names when its fields arg is set, and those are returned as they are.
	 * @return WP_Term|mixed
	 */
	public function fill_details( $term ) {
		if ( ! is_object( $term ) || ! empty( $term->zoninator_parsed ) ) {
			return $term;
		}

		foreach ( $this->read_details( (string) $term->description ) as $detail_key => $detail_value ) {
			$term->$detail_key = $detail_value;
		}

		$term->zoninator_parsed = true;

		return $term;
	}

	/**
	 * Reads a zone's details from its term's description.
	 *
	 * @param string $description The term's description, as stored.
	 * @return array<string, mixed> The details, by name, with a default for any that's missing.
	 */
	private function read_details( string $description ): array {
		$details = array();

		if ( '' !== $description && is_serialized( $description ) ) {
			// Disallow object instantiation to prevent PHP object injection if a
			// crafted serialized payload reaches the term description via any path.
			// Silenced because unserialize() can still emit notices on truncated or
			// malformed payloads that pass is_serialized().
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$unserialized = @unserialize( trim( $description ), array( 'allowed_classes' => false ) );
			if ( false === $unserialized ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				$unserialized = @unserialize( $this->repair_serialized_lengths( trim( $description ) ), array( 'allowed_classes' => false ) );
			}
			if ( is_array( $unserialized ) ) {
				$details = $unserialized;
			}
		}

		return wp_parse_args( $details, $this->settings->get_detail_defaults() );
	}

	/**
	 * Recount the string lengths in a serialised value.
	 *
	 * Earlier versions stored a description with non-ASCII characters with its
	 * length counted before core decoded its HTML entities, so it no longer
	 * unserialised. The text is intact, so recounting recovers it.
	 *
	 * Limitation: this assumes no stored string contains `";`. The sanitisers
	 * encode `"`, so saved zone details don't; if one does, unserialize() still
	 * fails safely and the description reads as empty, as before.
	 *
	 * @param string $serialized Serialised value.
	 * @return string
	 */
	private function repair_serialized_lengths( $serialized ) {
		return preg_replace_callback(
			'/s:\d+:"(.*?)";/s',
			static function ( $matches ) {
				return 's:' . strlen( $matches[1] ) . ':"' . $matches[1] . '";';
			},
			$serialized
		);
	}

	/**
	 * @param array|\WP_Error $result What wp_insert_term() or wp_update_term() returned.
	 * @return Zone The zone as it was saved.
	 *
	 * @throws ZoneNotSavedException If WordPress refused to save the term.
	 */
	private function saved( $result ): Zone {
		if ( is_wp_error( $result ) ) {
			throw new ZoneNotSavedException( $result->get_error_message(), (string) $result->get_error_code() );
		}

		return $this->find( (int) $result['term_id'] );
	}
}
