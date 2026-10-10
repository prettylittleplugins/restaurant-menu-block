<?php
/**
 * Front-end render for prettylp/menu-filter.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$wrapper = get_block_wrapper_attributes(
	[
		'data-wp-interactive' => 'prettylp/menu',
	]
);
?>
<form <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<p><?php esc_html_e( 'Menu Filter — customer controls will appear here.', 'restaurant-menu-block' ); ?></p>
</form>
