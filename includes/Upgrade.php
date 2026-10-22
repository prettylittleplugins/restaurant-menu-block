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

	/**
	 * Runs on every load (init, priority 20) and on activation.
	 *
	 * Two independent checks: the shipped data (reseeded whenever data/*.json
	 * changes, e.g. after a plugin update) and the schema version (migrations).
	 */
	public static function maybe_upgrade(): void {
		self::maybe_seed();

		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );
		if ( $installed >= self::DB_VERSION ) {
			return;
		}

		if ( $installed < 1 ) {
			// First install: nothing to migrate.
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Seeds shipped data if it's missing or out of date. A seeding failure is a
	 * packaging bug, not a site bug: log it rather than take the site down.
	 */
	private static function maybe_seed(): void {
		try {
			Seeder::maybe_seed();
		} catch ( \RuntimeException $e ) {
			error_log( 'Restaurant Menu Block: seeding failed — ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
