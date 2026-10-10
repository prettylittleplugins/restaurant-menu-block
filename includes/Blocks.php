<?php
/**
 * Registers the compiled blocks from build/.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	public static function register(): void {
		$build = PRETTYLP_RMB_DIR . 'build/';

		if ( ! is_dir( $build ) ) {
			// npm run build hasn't been run; blocks simply don't register.
			return;
		}

		// WP 6.7+ reads one manifest for all blocks; older versions register each block dir.
		$manifest = $build . 'blocks-manifest.php';
		if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) && is_readable( $manifest ) ) {
			wp_register_block_types_from_metadata_collection( $build . 'blocks', $manifest );
			return;
		}

		foreach ( [ 'menu', 'menu-filter' ] as $block ) {
			if ( is_readable( $build . 'blocks/' . $block . '/block.json' ) ) {
				register_block_type( $build . 'blocks/' . $block );
			}
		}
	}
}
