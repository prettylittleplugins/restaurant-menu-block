<?php
/**
 * Activation and version-to-version upgrades.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

final class Upgrade {

	public const DB_VERSION_OPTION = 'prettylp_db_version';
	public const DB_VERSION        = 1;

	/** Runs once when the plugin is activated. */
	public static function activate(): void {
		PostTypes::register();
		Taxonomies::register();
		self::maybe_upgrade();
		flush_rewrite_rules();
	}

	/** Runs on every load; does nothing unless the stored version is behind. */
	public static function maybe_upgrade(): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}

		if ( $installed < 1 ) {
			// First install: nothing to migrate yet. Shipped data is seeded on demand (see Cli / later Seeder).
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}
}
