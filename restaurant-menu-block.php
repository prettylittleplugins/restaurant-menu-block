<?php
/**
 * Plugin Name:       Restaurant Menu Block – with Allergen & Dietary Filters
 * Plugin URI:        https://prettylittleplugins.com/restaurant-menu-block/
 * Description:       A menu block for restaurants, with ingredient-derived allergen information and customer-facing dietary filters.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Pretty Little Plugins
 * Author URI:        https://prettylittleplugins.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       restaurant-menu-block
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

define( 'PRETTYLP_RMB_VERSION', '0.1.0' );
define( 'PRETTYLP_RMB_FILE', __FILE__ );
define( 'PRETTYLP_RMB_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRETTYLP_RMB_URL', plugin_dir_url( __FILE__ ) );

/**
 * Minimal PSR-4 autoloader for the plugin's own classes.
 *
 * Maps PrettyLittlePlugins\RestaurantMenuBlock\Foo\Bar to includes/Foo/Bar.php.
 * Composer's autoloader does the same job during development and testing,
 * but we don't ship vendor/, so production needs this.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = PRETTYLP_RMB_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

Plugin::instance()->boot();
