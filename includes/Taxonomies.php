<?php
/**
 * Ingredient, Group and Section taxonomies.
 *
 * @package PrettyLittlePlugins\RestaurantMenuBlock
 */

namespace PrettyLittlePlugins\RestaurantMenuBlock;

defined( 'ABSPATH' ) || exit;

final class Taxonomies {

	public const INGREDIENT = 'prettylp_ingredient';
	public const GROUP      = 'prettylp_group';
	public const SECTION    = 'prettylp_section';

	public static function register(): void {
		$common = [
			'hierarchical' => false,
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'rewrite'      => false,
		];

		register_taxonomy(
			self::INGREDIENT,
			self::DISH_TYPES(),
			$common + [
				'labels' => [
					'name'          => __( 'Ingredients', 'restaurant-menu-block' ),
					'singular_name' => __( 'Ingredient', 'restaurant-menu-block' ),
				],
			]
		);

		// Groups are not attached to dishes directly; a dish's groups are derived from its ingredients.
		register_taxonomy(
			self::GROUP,
			[],
			$common + [
				'labels' => [
					'name'          => __( 'Allergen & Dietary Groups', 'restaurant-menu-block' ),
					'singular_name' => __( 'Group', 'restaurant-menu-block' ),
				],
			]
		);

		register_taxonomy(
			self::SECTION,
			self::DISH_TYPES(),
			$common + [
				'labels' => [
					'name'          => __( 'Menu Sections', 'restaurant-menu-block' ),
					'singular_name' => __( 'Section', 'restaurant-menu-block' ),
				],
			]
		);

		self::register_term_meta();

		// Groups aren't attached to a post type, so WordPress gives them no menu entry; add one under Menu.
		add_action( 'admin_menu', [ self::class, 'add_groups_menu' ] );
	}

	public static function add_groups_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostTypes::DISH,
			__( 'Allergen & Dietary Groups', 'restaurant-menu-block' ),
			__( 'Groups', 'restaurant-menu-block' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . self::GROUP
		);
	}

	private static function DISH_TYPES(): array {
		return [ PostTypes::DISH ];
	}

	/**
	 * Term meta, declared so it's typed and available over REST.
	 *
	 * Ingredient: groups (slugs) OR made_of (ingredient slugs), swap (ingredient slug), unclassified flag.
	 * Group: type (allergen|intolerance|choice), sort order.
	 */
	private static function register_term_meta(): void {
		$list = [
			'type'         => 'array',
			'single'       => true,
			'show_in_rest' => [ 'schema' => [ 'items' => [ 'type' => 'string' ] ] ],
			'default'      => [],
		];
		$text = [
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			'default'      => '',
		];

		register_term_meta( self::INGREDIENT, 'prettylp_groups', $list );
		register_term_meta( self::INGREDIENT, 'prettylp_made_of', $list );
		register_term_meta( self::INGREDIENT, 'prettylp_swap', $text );
		register_term_meta(
			self::INGREDIENT,
			'prettylp_unclassified',
			[
				'type'         => 'boolean',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => false,
			]
		);

		register_term_meta( self::GROUP, 'prettylp_type', $text );
		register_term_meta(
			self::GROUP,
			'prettylp_order',
			[
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => 0,
			]
		);
	}
}
