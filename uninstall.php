<?php
/**
 * Runs when the plugin is deleted from the Plugins screen. Removes plugin options only.
 * Dishes, ingredients and groups are the restaurant's data and are left in place.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'prettylp_profiles' );
delete_option( 'prettylp_db_version' );
delete_option( 'prettylp_taxonomy_version' );
