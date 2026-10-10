<?php
/**
 * Dish custom post type.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

final class PostTypes {

	public const DISH = 'prettylp_dish';

	public static function register(): void {
		register_post_type(
			self::DISH,
			[
				'labels'       => [
					'name'          => __( 'Dishes', 'restaurant-menu-block' ),
					'singular_name' => __( 'Dish', 'restaurant-menu-block' ),
					'add_new_item'  => __( 'Add New Dish', 'restaurant-menu-block' ),
					'edit_item'     => __( 'Edit Dish', 'restaurant-menu-block' ),
					'menu_name'     => __( 'Restaurant Menu', 'restaurant-menu-block' ),
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-food',
				'supports'     => [ 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ],
				'rewrite'      => false,
			]
		);

		// Price and availability are post meta, exposed to REST so the grid admin can edit them.
		register_post_meta(
			self::DISH,
			'prettylp_price',
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);
		register_post_meta(
			self::DISH,
			'prettylp_available',
			[
				'type'         => 'boolean',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => true,
			]
		);
	}
}
