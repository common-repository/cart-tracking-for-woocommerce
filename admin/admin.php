<?php

defined( 'ABSPATH' ) || exit;
if ( !class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
add_action( 'admin_menu', 'wtrackt_admin_menu' );
add_action( 'admin_enqueue_scripts', 'wtrackt_adding_scripts' );
class Wtrackt_List_Table extends WP_List_Table
{
    public function __construct()
    {
        global  $status, $page ;
        //Set parent defaults
        parent::__construct( array(
            'singular' => 'cart',
            'plural'   => 'carts',
            'ajax'     => false,
        ) );
    }
    
    public function column_default( $item, $column_name )
    {
        switch ( $column_name ) {
            case 'update_time':
            case 'deleted_product':
            case 'customer_id':
            case 'cart_total':
            case 'order_created':
            case 'product_name':
                return $item[$column_name];
            default:
                return print_r( $item, true );
        }
    }
    
    public function column_title( $item )
    {
        //Build row actions
        $actions = array(
            'delete' => sprintf(
            '<a href="?page=%s&action=%s&cart=%s">Delete</a>',
            sanitize_text_field( $_REQUEST['page'] ),
            'delete',
            $item['id']
        ),
        );
        return sprintf(
            '%1$s <span>%2$s</span>%3$s',
            /*$1%s*/
            $item['title'],
            /*$2%s*/
            $item['id'],
            /*$3%s*/
            $this->row_actions( $actions )
        );
    }
    
    public function column_cb( $item )
    {
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/
            $this->_args['singular'],
            /*$2%s*/
            $item['id']
        );
    }
    
    public function get_columns()
    {
        $columns = array(
            'cb'              => '<input type="checkbox" />',
            'title'           => 'Cart',
            'update_time'     => 'Last update',
            'product_name'    => 'Products',
            'customer_id'     => 'Customer/IP',
            'deleted_product' => 'Products Removed',
            'cart_total'      => 'Cart value',
            'order_created'   => 'Order ID',
        );
        return $columns;
    }
    
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'title'       => array( 'title', false ),
            'update_time' => array( 'update_time', false ),
            'cart_total'  => array( 'cart_total', false ),
        );
        return $sortable_columns;
    }
    
    public function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete',
        );
        return $actions;
    }
    
    public function process_bulk_action()
    {
        global  $wpdb ;
        $table_cart_name = $wpdb->prefix . 'cart_tracking_wc_cart';
        $table_name = $wpdb->prefix . 'cart_tracking_wc';
        //Detect when a bulk action is being triggered...
        
        if ( 'delete' === $this->current_action() ) {
            $cart = filter_var_array( $_REQUEST['cart'], FILTER_SANITIZE_ENCODED );
            if ( !$cart ) {
                $cart = sanitize_text_field( $_REQUEST['cart'] );
            }
            $ids = ( isset( $cart ) ? $cart : array() );
            if ( is_array( $ids ) ) {
                $ids = implode( ',', $ids );
            }
            
            if ( !empty($ids) ) {
                $ids = sanitize_text_field( $ids );
                $wpdb->query( "DELETE FROM {$table_cart_name} WHERE id IN({$ids})" );
                $wpdb->query( "DELETE FROM {$table_name} WHERE cart_number IN({$ids})" );
            }
            
            //wp_die('Items deleted!');
            $add_message = "1";
            wp_redirect( add_query_arg( array(
                'page'    => 'wtrackt-main-menu',
                'message' => $add_message,
            ), admin_url( 'admin.php' ) ) );
        }
    
    }
    
    public function prepare_items()
    {
        global  $wpdb ;
        $per_page = 15;
        $search = ( isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : false );
        //  $do_search = ($search) ? " WHERE users.user_login LIKE '" . $search . "'" : '';
        $do_search = ( $search ? $wpdb->prepare(
            " WHERE users.user_login LIKE '%%%s%%' OR posts.post_title LIKE '%%%s%%'\r\n         OR carts.order_created LIKE '%%%s%%' OR carts.ip_address LIKE '%%%s%%'",
            $search,
            $search,
            $search,
            $search
        ) : '' );
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );
        $this->process_bulk_action();
        $current_page = $this->get_pagenum();
        $table_cart_name = $wpdb->prefix . "cart_tracking_wc_cart";
        $req_order_by = sanitize_text_field( $_REQUEST['orderby'] );
        $req_order = sanitize_text_field( $_REQUEST['order'] );
        $orderby = ( !empty($req_order_by) ? $req_order_by : 'carts.id' );
        //If no sort, default to title
        if ( $orderby === 'title' ) {
            $orderby = 'carts.id';
        }
        $order = ( !empty($req_order) ? $req_order : 'DESC' );
        // $sql = $wpdb->prepare("SELECT carts.id AS id, update_time, cart_total, GROUP_CONCAT(posts.post_title SEPARATOR ' ') AS products
        // FROM {$wpdb->prefix}cart_tracking_wc_cart AS carts JOIN {$wpdb->prefix}cart_tracking_wc AS products ON carts.id = products.cart_number
        // JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID GROUP BY carts.id ORDER BY carts.id DESC LIMIT %d OFFSET %d",
        //     $per_page, ($current_page - 1) * $per_page);
        $requete_sql = "SELECT carts.id AS id, update_time, IF(carts.order_created = 0, 'Not purchased',carts.order_created) as order_created, cart_total, IF(carts.customer_id = 0 ,carts.ip_address, users.user_login) as customer_id,\r\n        -- GROUP_CONCAT(IF(products.removed=0,CONCAT('<a href=\\'',posts.post_name,'\\'>',posts.post_title,'</a>',' (x',products.quantity,')' ),NULL) SEPARATOR '<br>') AS product_name,\r\n        -- GROUP_CONCAT(IF(products.removed=1,CONCAT('<a href=\\'',posts.post_name,'\\'>',posts.post_title,'</a>',' (x',products.quantity,')' ),NULL) SEPARATOR '<br>') AS deleted_product\r\n        GROUP_CONCAT(IF(products.removed=0,CONCAT('<a href=\\'','post.php?post=',posts.ID,'&action=edit','\\'>',posts.post_title,'</a>',' (x',products.quantity,')' ),NULL) SEPARATOR '<br>') AS product_name,\r\n        GROUP_CONCAT(IF(products.removed=1,CONCAT('<a href=\\'','post.php?post=',posts.ID,'&action=edit','\\'>',posts.post_title,'</a>',' (x',products.quantity,')' ),NULL) SEPARATOR '<br>') AS deleted_product\r\n        FROM {$wpdb->prefix}cart_tracking_wc_cart AS carts\r\n        LEFT JOIN {$wpdb->prefix}users AS users ON customer_id = users.ID\r\n        JOIN {$wpdb->prefix}cart_tracking_wc AS products ON carts.id = products.cart_number\r\n        JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n        {$do_search}\r\n        GROUP BY carts.id\r\n        ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $sql = $wpdb->prepare( $requete_sql, $per_page, ($current_page - 1) * $per_page );
        $results = $wpdb->get_results( $sql, ARRAY_A );
        foreach ( $results as $key => $result ) {
            $link = add_query_arg( array(
                'page' => 'wtrackt-history-page',
                'id'   => $result['id'],
            ), admin_url( 'admin.php' ) );
            $results[$key]['id'] = "<a href='" . $link . "'>" . $result['id'] . "</a>";
            
            if ( $result['order_created'] !== 'Not purchased' ) {
                $order = wc_get_order( $result['order_created'] );
                
                if ( $order ) {
                    $wc_st = $order->get_status();
                    $st_class = "wtrackt_" . $wc_st;
                    $order_status = '<span class="wtrackt_status ' . $st_class . '">' . strtolower( wc_get_order_status_name( $wc_st ) ) . '</span>';
                    $results[$key]['order_created'] = $result['order_created'] . ' ' . $order_status;
                }
            
            }
        
        }
        $total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_cart_name}" );
        $this->items = $results;
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }

}
function wtrackt_admin_menu()
{
    $menu_page = add_menu_page(
        'Cart Tracking Configuration Page',
        'Cart Tracking',
        'manage_options',
        'wtrackt-main-menu',
        'wtrackt_complex_main',
        'dashicons-cart'
    );
    add_submenu_page(
        "",
        'User Cart History',
        'User Cart History',
        'manage_options',
        'wtrackt-history-page',
        'wtrackt_cart_history_page'
    );
}

function wtrackt_complex_main()
{
    //Create an instance of our package class...
    $wtracktListTable = new Wtrackt_List_Table();
    //Fetch, prepare, sort, and filter our data...
    $wtracktListTable->prepare_items();
    ?>
    <div id="wtrackt-general" class="wrap">
    <?php 
    if ( isset( $_GET['message'] ) && $_GET['message'] == '1' ) {
        ?>
 <div id='message' class='updated fade'><p><strong> Item Successfully Deleted</strong></p></div>
<?php 
    }
    ?>
    <h2>Cart Tracking</h2>
    <?php 
    
    if ( isset( $_GET['tab'] ) ) {
        wtrackt_admin_tabs( sanitize_text_field( $_GET['tab'] ) );
    } else {
        wtrackt_admin_tabs( 'carts' );
    }
    
    ?>
    <?php 
    global  $wpdb ;
    
    if ( isset( $_GET['tab'] ) ) {
        $tab = sanitize_text_field( $_GET['tab'] );
    } else {
        $tab = 'carts';
    }
    
    switch ( $tab ) {
        case 'carts':
            ?>
            <form id="carts-filter" method="get">

            <input type="hidden" name="page" value="<?php 
            echo  esc_html( $_REQUEST['page'] ) ;
            ?>" />

            <?php 
            $wtracktListTable->search_box( 'Search', 'search' );
            $wtracktListTable->display();
            ?>
        </form>
        <?php 
            break;
        case 'added_products':
            ?>
<div>
<h2>Most added to cart</h2>
<h3>Today</h3>
<?php 
            $list_length = 20;
            $requete_sql = "SELECT product_id, posts.post_title,count(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE date(products.time)=CURDATE()\r\nGROUP BY products.product_id ORDER BY carts DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_added_table( $results );
            ?>
<h3>Last 7 days</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title,count(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE products.time>= NOW()- INTERVAL 7 DAY\r\n            GROUP BY products.product_id ORDER BY carts DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_added_table( $results );
            ?>
<h3>All time</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title,count(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            GROUP BY products.product_id ORDER BY carts DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_added_table( $results );
            ?>
</div>
<?php 
            break;
        case 'removed_products':
            $list_length = 20;
            ?>
            <div>
<h2>Actively removed from cart by customer %</h2>
<p>Products ordered by share of products actively removed from the cart on the products added to the cart.</p>
<h3>Today</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title, ROUND(count(*)*100/(SELECT count(*) FROM {$wpdb->prefix}cart_tracking_wc WHERE product_id=products.product_id)) as share,\r\n            count(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE products.removed = 1 AND date(products.time)=CURDATE()\r\n            GROUP BY products.product_id ORDER BY share DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_removed_table( $results, true );
            ?>

<h3>Last 7 days</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title, ROUND(count(*)*100/(SELECT count(*) FROM {$wpdb->prefix}cart_tracking_wc WHERE product_id=products.product_id)) as share,\r\ncount(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE products.removed = 1 AND products.time>= NOW()- INTERVAL 7 DAY\r\n            GROUP BY products.product_id ORDER BY share DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_removed_table( $results, true );
            ?>

<h3>All time</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title, ROUND(count(*)*100/(SELECT count(*) FROM {$wpdb->prefix}cart_tracking_wc WHERE product_id=products.product_id)) as share,\r\ncount(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE products.removed = 1\r\n            GROUP BY products.product_id ORDER BY share DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_removed_table( $results, true );
            ?>

</div>
<div>
<h2>Actively removed from cart by customer</h2>
<p>Products ordered by total removed.</p>
<h3>Today</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title,count(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE products.removed = 1 AND date(products.time)=CURDATE()\r\n            GROUP BY products.product_id ORDER BY carts DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_removed_table( $results );
            ?>

<h3>Last 7 days</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title,count(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE products.removed = 1 AND products.time>= NOW()- INTERVAL 7 DAY\r\n            GROUP BY products.product_id ORDER BY carts DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_removed_table( $results );
            ?>

<h3>All time</h3>
<?php 
            $requete_sql = "SELECT product_id, posts.post_title,count(*) as carts FROM {$wpdb->prefix}cart_tracking_wc AS products\r\n            JOIN {$wpdb->prefix}posts AS posts ON products.product_id = posts.ID\r\n            WHERE products.removed = 1\r\n            GROUP BY products.product_id ORDER BY carts DESC LIMIT {$list_length}";
            $prep_sql = $wpdb->prepare( $requete_sql, $list_length );
            $results = $wpdb->get_results( $prep_sql, ARRAY_A );
            wtrackt_display_removed_table( $results );
            ?>

</div>
        <?php 
            break;
    }
    ?>
</div>
    <?php 
}

function wtrackt_admin_tabs( $current = 'carts' )
{
    $tabs = array(
        'carts'            => __( 'Carts', "cart-tracking-woocommerce" ),
        'added_products'   => __( 'Added Products', "cart-tracking-woocommerce" ),
        'removed_products' => __( 'Removed Products', "cart-tracking-woocommerce" ),
    );
    $allowed_html = array(
        'div' => array(),
        'h2'  => array(),
        'br'  => array(),
        'a'   => array(
        'href'  => array(),
        'class' => array(),
    ),
    );
    echo  '<div id="icon-themes" class="icon32"><br></div>' ;
    echo  '<h2 class="nav-tab-wrapper">' ;
    foreach ( $tabs as $tab => $name ) {
        $class = ( $tab == $current ? 'nav-tab nav-tab-active' : "nav-tab" );
        echo  wp_kses( "<a class='{$class}' href='?page=wtrackt-main-menu&tab={$tab}'>{$name}</a>", $allowed_html ) ;
    }
    echo  '</h2>' ;
}

function wtrackt_display_added_table( $results )
{
    ?>
    <table class="wtrackt_table">        <!-- Display table headers -->
    <tr>
    <th>
    <strong>Product ID</strong></th>
    <th style="width: 400px"><strong>Product</strong></th>
    <th><strong>Total Carts</strong></th>
    </tr>
            <?php 
    
    if ( $results ) {
        $allowed_html = array(
            'tr' => array(),
            'td' => array(),
            'a'  => array(),
        );
        foreach ( $results as $product ) {
            echo  '<tr>' ;
            echo  wp_kses( '<td>' . $product['product_id'] . '</td>', $allowed_html ) ;
            echo  '<td><a href="' ;
            echo  esc_url( get_permalink( $product['product_id'] ) ) ;
            echo  '">' . htmlspecialchars( $product['post_title'] ) . '</a></td>' ;
            echo  wp_kses( '<td>' . $product['carts'] . '</td></tr>', $allowed_html ) ;
        }
    } else {
        echo  '<tr style="background: #FFF">' ;
        echo  '<td colspan=3>No Product Found.</td></tr>' ;
    }
    
    ?>
        </table>
        <?php 
}

function wtrackt_display_removed_table( $results, $share = false )
{
    ?>
    <table class="wtrackt_table">        <!-- Display table headers -->
    <tr>
    <th>
    <strong>Product ID</strong></th>
    <th style="width: 400px"><strong>Product</strong></th>
    <?php 
    if ( $share ) {
        ?>
    <th><strong>Share %</strong></th>
    <?php 
    }
    ?>
    <th><strong>Total Carts</strong></th>
    </tr>

            <?php 
    
    if ( $results ) {
        $allowed_html = array(
            'tr' => array(),
            'td' => array(),
        );
        foreach ( $results as $product ) {
            echo  '<tr>' ;
            echo  wp_kses( '<td>' . $product['product_id'] . '</td>', $allowed_html ) ;
            echo  '<td><a href="' ;
            echo  esc_url( get_permalink( $product['product_id'] ) ) ;
            echo  '">' . htmlspecialchars( $product['post_title'] ) . '</a></td>' ;
            if ( $share ) {
                echo  wp_kses( '<td>' . $product['share'] . '</td>', $allowed_html ) ;
            }
            echo  wp_kses( '<td>' . $product['carts'] . '</td></tr>', $allowed_html ) ;
        }
    } else {
        echo  '<tr style="background: #FFF">' ;
        $colspan = ( $share ? 4 : 3 );
        echo  '<td colspan="' . htmlspecialchars( $colspan ) . '">No Product Found.</td></tr>' ;
    }
    
    ?>
        </table>
        <?php 
}

function wtrackt_adding_scripts()
{
    $custom_css_ver = date( "ymd-Gis", filemtime( plugin_dir_path( __FILE__ ) . 'stylesheet.css' ) );
    wp_enqueue_style(
        'admin_style',
        plugin_dir_url( __FILE__ ) . 'stylesheet.css',
        array(),
        $custom_css_ver
    );
}
