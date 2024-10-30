<?php

/**
 * Plugin Name: Cart tracking for WooCommerce
 * Plugin URI: https://wpsimpleplugins.wordpress.com/
 * Description: Keep track of what people are adding or removing from their cart. See top added/removed products.
 * Version: 1.0.14
 * Author: Simple Plugins
 * Author URI: https://wpsimpleplugins.wordpress.com/
 * License: GPLv2
 * WC tested up to: 7.8.2
 **/
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if ( function_exists( 'ctfw_fs' ) ) {
    ctfw_fs()->set_basename( false, __FILE__ );
} else {
    
    if ( !function_exists( 'ctfw_fs' ) ) {
        // Create a helper function for easy SDK access.
        function ctfw_fs()
        {
            global  $ctfw_fs ;
            
            if ( !isset( $ctfw_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $ctfw_fs = fs_dynamic_init( array(
                    'id'             => '8352',
                    'slug'           => 'cart-tracking-for-woocommerce',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_36322effa7a7f8a5c323183c9d501',
                    'is_premium'     => false,
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => array(
                    'slug'    => 'wtrackt-main-menu',
                    'support' => false,
                ),
                    'anonymous_mode' => true,
                    'is_live'        => true,
                ) );
            }
            
            return $ctfw_fs;
        }
        
        // Init Freemius.
        ctfw_fs();
        // Signal that SDK was initiated.
        do_action( 'ctfw_fs_loaded' );
    }
    
    require plugin_dir_path( __FILE__ ) . 'includes/logger.php';
    wp_mkdir_p( WTRACKT_LOG_DIR );
    global  $wtrackt_db_version ;
    $wtrackt_db_version = '1.0.6';
    require plugin_dir_path( __FILE__ ) . 'includes/activation.php';
    register_activation_hook( __FILE__, 'wtrackt_install' );
    add_action( 'plugins_loaded', 'wtrackt_update_db_check' );
    require plugin_dir_path( __FILE__ ) . 'includes/woocommerce_cart.php';
    require plugin_dir_path( __FILE__ ) . 'includes/history_log.php';
    
    if ( is_admin() ) {
        require plugin_dir_path( __FILE__ ) . 'admin/admin.php';
        require plugin_dir_path( __FILE__ ) . 'admin/cart_history.php';
    }
    
    add_action( 'before_woocommerce_init', function () {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    } );
    function wtrackt_custom_is_submenu_visible( $is_visible, $menu_id )
    {
        if ( 'contact' != $menu_id ) {
            return $is_visible;
        }
        return ctfw_fs()->can_use_premium_code();
    }
    
    ctfw_fs()->add_filter(
        'is_submenu_visible',
        'wtrackt_custom_is_submenu_visible',
        10,
        2
    );
    ctfw_fs()->add_action( 'after_uninstall', 'ctfw_fs_uninstall_cleanup' );
    function ctfw_fs_uninstall_cleanup()
    {
        wtrackt_delete_plugin();
    }
    
    function wtrackt_delete_plugin()
    {
        delete_option( 'wtrackt_db_version' );
        global  $wpdb ;
        if ( is_multisite() ) {
            
            if ( !empty($_GET['networkwide']) ) {
                // Get blog list and cycle through all blogs
                $start_blog = $wpdb->blogid;
                $blog_list = $wpdb->get_col( 'SELECT blog_id FROM ' . $wpdb->blogs );
                foreach ( $blog_list as $blog ) {
                    switch_to_blog( $blog );
                    // Call function to delete bug table with prefix
                    wtrackt_drop_table( $wpdb->get_blog_prefix() );
                }
                switch_to_blog( $start_blog );
                return;
            }
        
        }
        wtrackt_drop_table( $wpdb->prefix );
    }
    
    function wtrackt_drop_table( $prefix )
    {
        global  $wpdb ;
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $prefix . 'cart_tracking_wc' );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $prefix . 'cart_tracking_wc_cart' );
        $wpdb->query( 'DROP TABLE IF EXISTS ' . $prefix . 'cart_tracking_wc_logs' );
    }

}
