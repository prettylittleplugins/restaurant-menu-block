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
	 * Reports plugin status. Placeholder until the engine lands.
	 *
	 * ## EXAMPLES
	 *
	 *     wp prettylp menu status
	 */
	public function status(): void {
		\WP_CLI::log( 'Restaurant Menu Block ' . PRETTYLP_RMB_VERSION );
		\WP_CLI::log( 'DB version: ' . (int) get_option( Upgrade::DB_VERSION_OPTION, 0 ) );
		\WP_CLI::log( 'Dishes: ' . (int) wp_count_posts( PostTypes::DISH )->publish );
		\WP_CLI::log( 'Ingredients: ' . (int) wp_count_terms( [ 'taxonomy' => Taxonomies::INGREDIENT, 'hide_empty' => false ] ) );
		\WP_CLI::log( 'Groups: ' . (int) wp_count_terms( [ 'taxonomy' => Taxonomies::GROUP, 'hide_empty' => false ] ) );
		\WP_CLI::success( 'OK' );
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
