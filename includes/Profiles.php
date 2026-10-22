<?php
/**
 * Profiles: named sets of groups to exclude (vegan, coeliac…). Stored as one option.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

final class Profiles {

	public const OPTION = 'prettylp_profiles';

	/** @return array<string, array> keyed by profile slug */
	public static function all(): array {
		$profiles = get_option( self::OPTION, null );
		if ( ! is_array( $profiles ) ) {
			$profiles = self::defaults();
			update_option( self::OPTION, $profiles, false );
		}
		return $profiles;
	}

	/** Shipped defaults, read from data/profiles.json (a list), re-keyed by slug. */
	public static function defaults(): array {
		$json = file_get_contents( PRETTYLP_RMB_DIR . 'data/profiles.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return [];
		}
		return array_column( $data, null, 'slug' );
	}
}
