<?php
/**
 * Zones kept as taxonomy terms.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

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
 * It stores a zone's name and details with &, < and > encoded, and gives
 * them back decoded, so a Zone always holds plain text.
 *
 * WordPress filters a term's description when it saves it: it strips tags,
 * encodes a bare ampersand, and decodes every entity but those three. Each
 * of those changes the length of a string inside the serialised details,
 * which then no longer unserialise. Text with only those three characters
 * encoded, and its quotes left alone, is the one form the filters pass as it
 * is. Encoding it here, not where a request comes in, covers every way a zone
 * is saved. The filters also take out control characters, and carriage
 * returns by WordPress 7.3 though not in 7.1, so the details go through them
 * before they're counted.
 *
 * @since 1.0.0
 * @internal Use Automattic\Zoninator\Application\ZoneManager instead.
 */
final class TermZoneRepository implements ZoneRepositoryInterface {

	/**
	 * How text is encoded for storage: &, < and > only. WordPress decodes an
	 * encoded quote when it saves a term, so quotes are stored as they are.
	 */
	private const ENCODING = ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401;

	/**
	 * Constructor.
	 *
	 * @param ZoneSettingsInterface $settings Says which details every zone has.
	 */
	public function __construct( private readonly ZoneSettingsInterface $settings ) {
	}

	/**
	 * Finds a zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @return Zone|null The zone, or null if there's none with that ID.
	 */
	public function find( int $zone_id ): ?Zone {
		$term = get_term_by( 'id', $zone_id, Taxonomy::NAME );

		return $term ? new Zone( (int) $term->term_id, (string) $term->slug, $this->decode( (string) $term->name ), $this->decode( $this->read_details( (string) $term->description ) ) ) : null;
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
			'description' => maybe_serialize( $this->encode_details( $zone->get_details() ) ),
		);

		/**
		 * Filters the wp_insert_term() arguments for a new zone.
		 *
		 * @since 0.4
		 *
		 * @param array $args The zone's slug, and its details, serialized, as description.
		 */
		$args = apply_filters( 'zoninator_insert_zone', $args );

		// wp_insert_term() expects slashed data, as it unslashes the name and description.
		return $this->saved( wp_insert_term( wp_slash( $this->encode( $zone->get_name() ) ), Taxonomy::NAME, wp_slash( $args ) ) );
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
			'name'        => $this->encode( $zone->get_name() ),
			'slug'        => $zone->get_slug(),
			'description' => maybe_serialize( $this->encode_details( $zone->get_details() ) ),
		);

		/**
		 * Filters the wp_update_term() arguments for a zone being changed.
		 *
		 * @since 0.4
		 *
		 * @param array   $args    The zone's name, slug, and details, serialized, as description.
		 * @param int     $zone_id Zone ID.
		 * @param WP_Term $zone    The zone as it is.
		 */
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
			throw new ZoneNotSavedException( __( "Sorry, we couldn't delete the zone.", 'zoninator' ), 'delete-zone' );
		}

		/**
		 * Fires after a zone is deleted.
		 *
		 * @since 0.6
		 *
		 * @param int $zone_id Zone ID.
		 */
		do_action( 'zoninator_delete_zone', $zone_id );
	}

	/**
	 * Gets a zone's term, with each of the zone's details as a property.
	 *
	 * @param int $zone_id Zone ID.
	 * @return WP_Term|false The term, or false if there's no such zone.
	 */
	public function get_term( int $zone_id ): WP_Term|false {
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
	public function fill_details( $term ): mixed {
		if ( ! is_object( $term ) || ! empty( $term->zoninator_parsed ) ) {
			return $term;
		}

		foreach ( $this->read_details( (string) $term->description ) as $detail_key => $detail_value ) {
			$detail_key = (string) $detail_key;

			// A detail is an extra property, and only the description replaces one the term already
			// has, so what's stored can't stand in for the term's ID or name. PHP refuses a property
			// with no name, or one that starts with a null byte.
			if ( 'description' === $detail_key || ( '' !== $detail_key && "\0" !== $detail_key[0] && ! property_exists( $term, $detail_key ) ) ) {
				$term->$detail_key = $detail_value;
			}
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
	 * Encodes &, < and > in text, for storage.
	 *
	 * @param mixed $value A string, or an array of details. Anything else is returned as it is.
	 * @return mixed
	 */
	private function encode( $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'encode' ), $value );
		}

		return is_string( $value ) ? htmlspecialchars( $value, self::ENCODING, 'UTF-8' ) : $value;
	}

	/**
	 * Encodes a zone's details for storage, and leaves WordPress nothing to change in them.
	 *
	 * When WordPress saves a description it also takes out control characters,
	 * and carriage returns by WordPress 7.3, which encode() leaves in. The
	 * details are counted when they're serialised, so each string goes through
	 * WordPress's own filters first, whatever they take out in this version:
	 * the second pass, on the whole description, then changes nothing, and the
	 * lengths stay right.
	 *
	 * @param mixed $value A string, or an array of details. Anything else is returned as it is.
	 * @return mixed
	 */
	private function encode_details( $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'encode_details' ), $value );
		}

		// The filters expect slashed data, as wp_insert_term() gives them.
		return is_string( $value ) ? wp_unslash( sanitize_term_field( 'description', wp_slash( $this->encode( $value ) ), 0, Taxonomy::NAME, 'db' ) ) : $value;
	}

	/**
	 * Decodes stored text. It decodes every entity, not only the three
	 * encode() writes: a zone saved before 1.0.0 can hold others, such as
	 * &quot; or &eacute;, which WordPress would decode, and miscount, the
	 * next time the zone was saved.
	 *
	 * @param mixed $value A string, or an array of details. Anything else is returned as it is.
	 * @return mixed
	 */
	private function decode( $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'decode' ), $value );
		}

		return is_string( $value ) ? html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $value;
	}

	/**
	 * Recount the string lengths in a serialised value.
	 *
	 * Earlier versions stored a description with non-ASCII characters with its
	 * length counted before core decoded its HTML entities, so it no longer
	 * unserialised. The text is intact, so recounting recovers it.
	 *
	 * Limitation: this assumes no stored string contains `";`. Only a zone
	 * saved before 1.0.0 needs repairing; if one holds that, unserialize() still
	 * fails safely and the description reads as empty, as before.
	 *
	 * @param string $serialized Serialised value.
	 * @return string
	 */
	private function repair_serialized_lengths( $serialized ): string {
		return (string) preg_replace_callback(
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
