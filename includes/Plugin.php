<?php
/**
 * Plugin bootstrap.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's parts to WordPress hooks. Holds no logic of its own.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		register_activation_hook( PRETTYLP_RMB_FILE, [ Upgrade::class, 'activate' ] );

		add_action( 'init', [ PostTypes::class, 'register' ] );
		add_action( 'init', [ Taxonomies::class, 'register' ] );
		add_action( 'init', [ Blocks::class, 'register' ] );
		add_action( 'init', [ Upgrade::class, 'maybe_upgrade' ], 20 );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'prettylp menu', Cli::class );
		}
	}
}
