<?php

defined('ABSPATH') || exit;

function wtrackt_install()
{
    global $wtrackt_db_version;
    $installed_ver = get_option("wtrackt_db_version");

    if ($installed_ver != $wtrackt_db_version) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cart_tracking_wc';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		product_id bigint(20) NOT NULL,
		quantity double NOT NULL DEFAULT 0,
		cart_number bigint(20) NOT NULL,
        removed boolean NOT NULL DEFAULT false,
		PRIMARY KEY  (id)
	) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $table_name = $wpdb->prefix . 'cart_tracking_wc_cart';
        $sql_cart = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            update_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            cart_total double NOT NULL DEFAULT 0,
            order_created bigint(20) NOT NULL DEFAULT 0,
            customer_id bigint(20) NOT NULL DEFAULT 0,
            ip_address varchar(100),
            PRIMARY KEY  (id)
        ) $charset_collate;";

        //require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_cart);

        $table_name = $wpdb->prefix . 'cart_tracking_wc_logs';
        $sql_cart = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            op_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            op_type bigint(20) NOT NULL DEFAULT 0,
            customer_id bigint(20) NOT NULL DEFAULT 0,
            product_id bigint(20) NOT NULL,
		quantity double NOT NULL DEFAULT 0,
		cart_number bigint(20) NOT NULL,
        op_value varchar(100) DEFAULT '',
            PRIMARY KEY  (id)
        ) $charset_collate;";
// op type 1 for add new product
        // 2 for removed product
        // 3 for order created
        // 4 for order status update
        dbDelta($sql_cart);

        update_option('wtrackt_db_version', $wtrackt_db_version);
    }
}
function wtrackt_update_db_check()
{
    global $wtrackt_db_version;
    if (get_site_option('wtrackt_db_version') != $wtrackt_db_version) {
        wtrackt_install();
    }
}
