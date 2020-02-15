<?php
/**
 * Plugin Name: Cleancoded Static Menus
 * Description: Cache navigation menus in static HTML for big performance gains.
 * Version: 1.0.0
 * Author: Cleancoded
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Setup the plugin constants
 */
define( 'CLEANCODED_STATIC_MENUS_VERSION', '1.0.0' );
define( 'CLEANCODED_STATIC_MENUS_SLUG', 'wp-static-menus' );
define( 'CLEANCODED_STATIC_MENUS_FILE', __FILE__ );
define( 'CLEANCODED_STATIC_MENUS_DIR', plugin_dir_path( CLEANCODED_STATIC_MENUS_FILE ) );
define( 'CLEANCODED_STATIC_MENUS_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( CLEANCODED_STATIC_MENUS_FILE ) ), basename( CLEANCODED_STATIC_MENUS_FILE ) ) ) );

/**
 * Require Plugin Files
 */
require_once 'includes/class-cleancoded-static-menus.php';

/**
 * Start the engines
 */
CLEANCODED_static_menus();

/**
 * Wrapper for getting global $CLEANCODED_static_menus and ensuring it is an instance of CLEANCODED_Static_Menus
 *
 * @return CLEANCODED_Static_Menus
 */
function CLEANCODED_static_menus() {
    global $CLEANCODED_static_menus;

    if( ! $CLEANCODED_static_menus instanceof CLEANCODED_Static_Menus ) {
        $CLEANCODED_static_menus = new CLEANCODED_Static_Menus;
    }

    return $CLEANCODED_static_menus;
}