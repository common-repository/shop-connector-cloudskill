<?php
if (!defined('ABSPATH')) //Leise das Script beenden, um unerwünschten Zugriff zu vermeiden
    exit;

class Elementor_customer_orders_cs_shop extends \Elementor\Widget_Base {

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);
        wp_register_style('style-handle', plugins_url('cs_shop_customer_orders_style.css', __FILE__));
    }

    public function get_style_depends() {
        return ['style-handle'];
    }

    public function get_name() {
        return 'cs_shop_customer_orders';
    }

    public function get_title() {
        return esc_html__('Benutzer Bestellungen', 'customer-orders-widget');
    }

    public function get_icon() {
        return 'eicon-cart';
    }

    public function get_custom_help_url() {
        return 'https://developers.elementor.com/docs/widgets/';
    }

    public function get_categories() {
        return ['cloudskiill'];
    }

    public function get_keywords() {
        return ['Cloudskill', 'Bestellungen', 'Benutzer'];
    }

    protected function register_controls() {
        
    }

    protected function render() {
        $api = new Cloudskill\Api();
        $user_orders = $api->get_user_orders_from_cs();

        if ($user_orders) {
            ?> 
            <div class="cs_shop_user_orders_widget" name="cs_shop_user_orders_widget">
                <table class="cs_shop_user_orders_widget_table">
                    <tr>
                        <th width="15%"> Belegnummer </th>
                        <th width="35%"> Datum </th>
                        <th width="25%"> Betrag Netto </th>
                        <th width="25%"> Betrag Brutto </th>
                    </tr>
                    <?php
                    foreach ($user_orders->orders as $order) {
                        ?> 
                        <tr>
                            <td><?php echo $order->nr ?></td>
                            <td><?php echo DateTime::createFromFormat('Ymd', $order->datum)->format('d.m.Y') . ' ' ?></td>
                            <td><?php echo number_format($order->betrag_netto,2,".",",") . ' €' ?></td>
                            <td><?php echo number_format($order->betrag_netto,2,".",",") . ' €' ?></td>
                        </tr>    
                        <?php
                    }
                    ?>
                </table>
            </div>    
            <?php
        } else {
            echo 'Fehler';
        }
    }

}
