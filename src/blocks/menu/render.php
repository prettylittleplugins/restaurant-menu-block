<?php
/**
 * Front-end render for prettylp/menu.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner content (unused, dynamic block).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$wrapper = get_block_wrapper_attributes(
	[
		'data-wp-interactive' => 'prettylp/menu',
	]
);
?>
<div <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<p><?php esc_html_e( 'Restaurant Menu — dishes will appear here.', 'restaurant-menu-block' ); ?></p>
</div>
