<?php
/**
 * Plugin Name: CJ Affiliate S2S Tracking
 * Plugin URI: https://82marketing.com
 * Description: This plugin implements S2S tracking for CJ Affiliate in WooCommerce.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://82marketing.com
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * WC requires at least: 3.0.0
 * WC tested up to: 6.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Create the settings page
function cj_affiliate_s2s_tracking_menu() {
    add_options_page(
        'CJ Affiliate S2S Tracking Settings',
        'CJ Affiliate S2S Tracking',
        'manage_options',
        'cj-affiliate-s2s-tracking',
        'cj_affiliate_s2s_tracking_settings_page'
    );
}
add_action('admin_menu', 'cj_affiliate_s2s_tracking_menu');

// Display the settings page
function cj_affiliate_s2s_tracking_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error('cj_affiliate_s2s_messages', 'cj_affiliate_s2s_message', 'Settings Saved', 'updated');
    }

    settings_errors('cj_affiliate_s2s_messages');

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('cj_affiliate_s2s_tracking');
            do_settings_sections('cj_affiliate_s2s_tracking');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

// Register the settings
function cj_affiliate_s2s_tracking_settings_init() {
    register_setting('cj_affiliate_s2s_tracking', 'cj_affiliate_s2s_options');

    add_settings_section(
        'cj_affiliate_s2s_tracking_section',
        'CJ Affiliate S2S Tracking Settings',
        'cj_affiliate_s2s_tracking_section_cb',
        'cj_affiliate_s2s_tracking'
    );

    add_settings_field(
        'cid',
        'CID',
        'cj_affiliate_s2s_cid_cb',
        'cj_affiliate_s2s_tracking',
        'cj_affiliate_s2s_tracking_section'
    );

    add_settings_field(
        'type',
        'TYPE',
        'cj_affiliate_s2s_type_cb',
        'cj_affiliate_s2s_tracking',
        'cj_affiliate_s2s_tracking_section'
    );

    add_settings_field(
        'signature',
        'SIGNATURE',
        'cj_affiliate_s2s_signature_cb',
        'cj_affiliate_s2s_tracking',
        'cj_affiliate_s2s_tracking_section'
    );
}
add_action('admin_init', 'cj_affiliate_s2s_tracking_settings_init');

// Section callback
function cj_affiliate_s2s_tracking_section_cb() {
    echo 'Enter your CJ Affiliate S2S Tracking settings below:';
}

// Field callbacks
function cj_affiliate_s2s_cid_cb() {
    $options = get_option('cj_affiliate_s2s_options');
    ?>
    <input type="text" name="cj_affiliate_s2s_options[cid]" value="<?php echo esc_attr($options['cid']); ?>">
    <?php
}

function cj_affiliate_s2s_type_cb() {
    $options = get_option('cj_affiliate_s2s_options');
    ?>
    <input type="text" name="cj_affiliate_s2s_options[type]" value="<?php echo esc_attr($options['type']); ?>">
    <?php
}

function cj_affiliate_s2s_signature_cb() {
    $options = get_option('cj_affiliate_s2s_options');
    ?>
    <input type="text" name="cj_affiliate_s2s_options[signature]" value="<?php echo esc_attr($options['signature']); ?>">
    <?php
}


// Set the 'CJEVENT' cookie if it's present in the URL
function set_cjevent_cookie() {
    if (isset($_GET['CJEVENT']) || isset($_GET['cjevent'])) {
        $cjevent_value = isset($_GET['CJEVENT']) ? $_GET['CJEVENT'] : $_GET['cjevent'];
        setcookie('CJEVENT', $cjevent_value, time() + 13 * 7 * 24 * 60 * 60, '/');
    }
}
add_action('init', 'set_cjevent_cookie');

// Add action to trigger after a successful order
add_action('woocommerce_thankyou', 'custom_cj_affiliate_s2s_tracking', 10, 1);

function custom_cj_affiliate_s2s_tracking($order_id) {
    if (!$order_id) {
        return;
    }

    $order = wc_get_order($order_id);

    // Get the CJEVENT cookie value
    if (isset($_COOKIE['CJEVENT'])) {
        $cjevent_cookie = $_COOKIE['CJEVENT'];
    } else {
        return;
    }

    // Get the current time in ISO 8601 format (UTC)
    $event_time = gmdate('c');

    $item_params = '';
    $index = 1;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $item_id = $product->get_id();
        $item_price = $order->get_item_total($item, false, false);
        $item_qty = $item->get_quantity();
        $item_discount = $order->get_total_discount() / $order->get_item_count();

        $item_params .= '&ITEM' . $index . '=' . urlencode($item_id);
        $item_params .= '&AMT' . $index . '=' . urlencode($item_price);
        $item_params .= '&QTY' . $index . '=' . urlencode($item_qty);
        $item_params .= '&DCNT' . $index . '=' . urlencode($item_discount);
        $index++;
    }

	$applied_coupons = $order->get_coupon_codes();
	$coupon_codes = implode(',', $applied_coupons);

	$options = get_option('cj_affiliate_s2s_options');
	$cid = urlencode($options['cid']);
	$type = urlencode($options['type']);
	$signature = urlencode($options['signature']);

	$tracking_url = 'https://www.emjcd.com/u?CID=' . $cid . '&TYPE=' . $type . '&METHOD=S2S&SIGNATURE=' . $signature;
    $tracking_url .= '&CJEVENT=' . urlencode($cjevent_cookie);
    $tracking_url .= '&eventTime=' . urlencode($event_time);
    $tracking_url .= '&OID=' . urlencode($order->get_order_number());
    $tracking_url .= '&currency=' . urlencode($order->get_currency());
    $tracking_url .= '&coupon=' . urlencode($coupon_codes);
    $tracking_url .= $item_params;
    $tracking_url .= '&discount=' . urlencode($order->get_total_discount());

    // Send the tracking information to CJ Affiliate
    $response = wp_remote_get($tracking_url, [
        'timeout' => 30,
        'sslverify' => false,
    ]);



}

