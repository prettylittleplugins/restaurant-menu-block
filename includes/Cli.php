<?php
/**
 * WP-CLI: wp prettylp menu <subcommand>
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

final class Cli {

	/**
	 * Reports plugin status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp prettylp menu status
	 */
	public function status(): void {
		\WP_CLI::log( 'Restaurant Menu Block ' . PRETTYLP_RMB_VERSION );
		\WP_CLI::log( 'DB version: ' . (int) get_option( Upgrade::DB_VERSION_OPTION, 0 ) );
		\WP_CLI::log( 'Shipped data: ' . ( Seeder::needs_seed() ? 'NOT seeded / out of date (run: wp prettylp menu seed)' : 'seeded, up to date' ) );
		\WP_CLI::log( 'Dishes: ' . (int) wp_count_posts( PostTypes::DISH )->publish );
		\WP_CLI::log( 'Ingredients: ' . (int) wp_count_terms( [ 'taxonomy' => Taxonomies::INGREDIENT, 'hide_empty' => false ] ) );
		\WP_CLI::log( 'Groups: ' . (int) wp_count_terms( [ 'taxonomy' => Taxonomies::GROUP, 'hide_empty' => false ] ) );
		\WP_CLI::success( 'OK' );
	}

	/**
	 * Seeds the shipped groups and ingredients from data/*.json.
	 *
	 * Idempotent. Only runs when the data files have changed since the last seed
	 * unless --force is given. Never touches terms the site owner added; never
	 * deletes terms that have been removed from the data files.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Seed even if the stored data version matches the shipped files.
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp prettylp menu seed
	 *     wp prettylp menu seed --dry-run
	 *     wp prettylp menu seed --force
	 *
	 * @param array $args       Positional args (none).
	 * @param array $assoc_args Flags.
	 */
	public function seed( array $args, array $assoc_args ): void {
		$force   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false );
		$dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

		if ( ! $force && ! $dry_run && ! Seeder::needs_seed() ) {
			\WP_CLI::success( 'Shipped data already up to date. Use --force to reseed anyway.' );
			return;
		}

		try {
			$result = Seeder::seed( (bool) $dry_run );
		} catch ( \RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		foreach ( $result['warnings'] as $warning ) {
			\WP_CLI::warning( $warning );
		}

		foreach ( [ 'groups', 'ingredients' ] as $what ) {
			$r = $result[ $what ];
			\WP_CLI::log(
				sprintf(
					'%s: %d created, %d updated, %d unchanged, %d skipped (not shipped), %d orphaned',
					ucfirst( $what ),
					count( $r['created'] ),
					count( $r['updated'] ),
					count( $r['unchanged'] ),
					count( $r['skipped'] ),
					count( $r['orphaned'] )
				)
			);
			foreach ( [ 'updated', 'skipped', 'orphaned' ] as $detail ) {
				if ( $r[ $detail ] ) {
					\WP_CLI::log( "  $detail: " . implode( ', ', $r[ $detail ] ) );
				}
			}
		}

		if ( $dry_run ) {
			\WP_CLI::success( 'Dry run — nothing written.' );
		} else {
			\WP_CLI::success( 'Seeded. Data version ' . substr( Seeder::data_version(), 0, 8 ) . '.' );
		}
	}

	/**
	 * Loads the shipped demo menu. Not implemented yet.
	 *
	 * ## EXAMPLES
	 *
	 *     wp prettylp menu demo
	 */
	public function demo(): void {
		\WP_CLI::warning( 'Demo import arrives with build step 2.' );
	}
}
