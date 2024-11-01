<?php

if (!defined('ABSPATH')) //Leise das Script beenden, um unerwünschten Zugriff zu vermeiden
    exit;

require_once(__DIR__ . "/cs_bestellungen.php");

/**
 * Erstellt das Hauptmenü "Cloudskill" (sofern es nicht schon von einem anderen Plugin erstellt wurde)
 * und das Submenu "Cloudskill Shop". 
 * @global array $menu = Alle exestierenden Hauptmenüs
 */
function cloudskill_setup_menu() {
    global $menu;
    $menuexist = FALSE;
    foreach ($menu as $men) {
        if (strtolower($men[0]) == strtolower('cloudskill')) {
            $menuexist = TRUE;
        }
    }
    if (!$menuexist) {
        add_menu_page('cloudskill', 'Cloudskill', 'manage_options', 'cloudskill', 'cloudskill_orders_menu_page', 'dashicons-cloud', 0);
    }
    add_submenu_page('cloudskill', 'cs_shop', 'Cloudskill Shop', 'manage_options', 'cs-shop-orders', 'cloudskill_orders_menu_page', 1);

    /* erstelle option für Speicherung, falls noch nicht existiert*/
    if (!get_option('cs_g1t89rf4_shop_orders_numbers', "")) {
        $orders_cs_number = [];
        add_option('cs_g1t89rf4_shop_orders_numbers', $orders_cs_number);
    }
}
add_action('admin_menu', 'cloudskill_setup_menu');

/**
 * bindet style und script ein
 */
function cloudskill_style_script() {
    wp_enqueue_style("cloudskill-style", plugins_url("cs_shop_style.css", __FILE__));
    wp_enqueue_script("cloudskill-script", plugins_url("cs_shop_script.js", __FILE__));
}

add_action('admin_enqueue_scripts', 'cloudskill_style_script');

/**
 * kreiert die CS-Shop Page
 */
function cloudskill_orders_menu_page() {
    cloudskill_create_order_page();
}

/**
 * API Funktion triggern wenn eine Order in WooCommerce eingeht.
 * @param type $order_id : ID von Bestellung
 */
function cloudskill_send_order($order_id) {
    $cs_api = new \Cloudskill\Api();
    $cs_api->trigger_order($order_id);
}

add_action('woocommerce_thankyou', 'cloudskill_send_order');

/**
 * Callback Funktion von WooCommerce API Zugang abfangen
 */
function cloudskill_authquery() {
    if (filter_has_var(INPUT_GET, 'callback') && filter_input(INPUT_GET, 'callback', FILTER_SANITIZE_STRING) == 'call') {

        $cs_api = new \Cloudskill\Api();
        $cs_api->woo_callback_action();
    }
}
add_action('wp_footer', 'cloudskill_authquery');

/**
 * Registriert das Kunden-Bestellungen Widget.
 * @param \Elementor\Widgets_Manager $widget_manager : Elementor Widget Manager
 */
function cloudskill_register_customer_orders_widget($widget_manager) {
    require_once(__DIR__ . '/cs_shop_widgets/cs_shop_customer_orders/customer_orders_widget.php');
    $widget_manager->register(new \Elementor_customer_orders_cs_shop());
}
add_action('elementor/widgets/register', 'cloudskill_register_customer_orders_widget');
