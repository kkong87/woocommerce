<?php
/**
 * Plugin Name: WooCommerce Affirm Express
 * Plugin URI: https://woocommerce.com/products/woocommerce-gateway-affirm/
 * Description: Checkout via Affirm from product or cart pages
 * Author: WooCommerce
 * Author URI: https://woocommerce.com/
 * Version: 1.0.0
 * WC tested up to: 3.6
 * WC requires at least: 2.6
 * Woo: 1474706:b271ae89b8b86c34020f58af2f4cbc81
 *
 * Copyright (c) 2018 WooCommerce
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Required functions and classes
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
    require_once( 'woo-includes/woo-functions.php' );
}


/**
 * Constants
 */
define( 'WC_GATEWAY_EXPRESS_VERSION', '1.0.0' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'b271ae89b8b86c34020f58af2f4cbc81', '1474706' );


class WC_Affirm_Express_Loader {

    /**
     * The reference the *Singleton* instance of this class.
     *
     * @var WC_Affirm_Loader
     */
    private static $instance;

    /**
     * Returns the *Singleton* instance of this class.
     *
     * @return Singleton The *Singleton* instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone() {
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct() {

        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
        add_action( 'plugins_loaded', array( $this, 'init_gateway' ), 0 );

        // Add Express Checkout Button
        add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'checkout_button_product' ), 30 );
        add_action( 'woocommerce_proceed_to_checkout', array( $this, 'checkout_button_cart' ), 130 );

        add_action( 'rest_api_init', array( $this , 'register_express_route' ) );
        add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'getProductSku' ) );

    }


    /**
     * Initialize the gateway.
     *
     * @since 1.0.0
     */
    function init_gateway() {
        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        //require_once( dirname( __FILE__ ) . '/includes/class-wc-affirm-privacy.php' );
        require_once( plugin_basename( 'includes/class-wc-affirm-express.php' ) );
        load_plugin_textdomain( 'woocommerce-gateway-affirm', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
    }

    /**
     * Adds plugin action links.
     *
     * @since 1.0.0
     *
     * @param array $links Plugin action links.
     *
     * @return array Plugin action links.
     */
    public function plugin_action_links( $links ) {

        $settings_url = add_query_arg(
            array(
                'page' => 'wc-settings',
                'tab' => 'checkout',
                'section' => 'wc_affirm_express',
            ),
            admin_url( 'admin.php' )
        );

        $plugin_links = array(
            '<a href="' . $settings_url . '">' . __( 'Settings', 'woocommerce-affirm-express' ) . '</a>',
            '<a href="http://docs.woothemes.com/document/woocommerce-gateway-affirm/">' . __( 'Docs', 'woocommerce-gateway-affirm' ) . '</a>',
            '<a href="http://support.woothemes.com/">' . __( 'Support', 'woocommerce-gateway-affirm' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }


    /**
     * Add the gateway to WooCommerce
     * @since 1.0.0
     */
    public function add_gateway( $methods ) {
        $methods[] = 'WC_Affirm_Express';
        return $methods;
    }

    /**
     * Add Express Checkout Button to product page.
     *
     * @since 1.0.0
     * @version 1.1.0
     */
    public function checkout_button_product() {

        $product = wc_get_product();

        $itemArray = array(array(
            'sku' => $product->get_sku(),
            'display_name' => $product->get_name(),
            'item_url' => get_permalink( $product->get_id() ),
            'item_image_url' => get_the_post_thumbnail_url( $product->get_id() ),
            'unit_price' => $product->get_price()*100,
            'qty' => 10,


        ));

        $merchantDataArray = $this->merchantData();
        $metaDataArray = $this->metaData();
        $productSkuURL = $this->getSiteURL('getProductSku');


        wp_register_script( 'express_button', plugins_url( 'assets/js/affirm-express-button-product.js', __FILE__ ), array( 'jquery' ) );
        wp_localize_script( 'express_button', 'items', $itemArray );
        wp_localize_script( 'express_button', 'merchantData', json_encode($merchantDataArray)  );
        wp_localize_script( 'express_button', 'metaData', $metaDataArray  );
        wp_localize_script( 'express_button', 'productSkuURL', $productSkuURL  );
        wp_enqueue_script( 'express_button' );
        echo "<div id=\"checkout-now\"></div>";

    }

    public function checkout_button_cart (){
        $items = WC()->cart->get_cart();
        $itemArray = array();
        foreach ($items as $item){
            error_log(json_encode($item));
            $productId = $item['data']->get_id() ;
            $itemData = $this->getProductData( $productId , $item['quantity'] );
            $itemArray[] = $itemData;
        }

        $merchantDataArray = $this->merchantData();
        $metaDataArray = $this->metaData();
        wp_register_script( 'express_button', plugins_url( 'assets/js/affirm-express-button-cart.js', __FILE__ ), array( 'jquery' ) );
        wp_localize_script( 'express_button', 'items', $itemArray );
        wp_localize_script( 'express_button', 'merchantData', json_encode($merchantDataArray)  );
        wp_localize_script( 'express_button', 'metaData', $metaDataArray  );
        wp_enqueue_script( 'express_button' );
        echo "<div id=\"checkout-now\"></div>";
        error_log('adsfa');
        $packages = WC()->shipping()->get_packages();
        foreach ( $packages as $i => $package ) {
            $chosen_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';
            error_log( json_encode( $package['rates'] ));

            foreach ($package['rates'] as $method){
                error_log(json_encode($method->get_label()));
                error_log(json_encode($method->cost));
                //error_log(wc_cart_totals_shipping_method_label( $method ));
                //do_action( 'woocommerce_after_shipping_rate', $method, $i );
            }

        }


    }


    public function register_express_route() {
        register_rest_route( 'woocommerce-affirm-express', '/createOrder', array(
                'methods'  => 'POST',
                'callback' => array( $this , 'createOrder' )
            )
        );
        register_rest_route( 'woocommerce-affirm-express', '/getProductSku', array(
                'methods'  => 'GET',
                'callback' => array( $this , 'getProductSku' )
            )
        );
        register_rest_route( 'woocommerce-affirm-express', '/updateOrder', array(
                'methods'  => 'POST',
                'callback' => array( $this , 'updateOrder' )
            )
        );
        register_rest_route( 'woocommerce-affirm-express', '/discount', array(
                'methods'  => 'POST',
                'callback' => array( $this , 'discount' )
            )
        );
        register_rest_route( 'woocommerce-affirm-express', '/confirm', array(
                'methods'  => 'POST',
                'callback' => array( $this , 'confirm' )
            )
        );

        register_rest_route( 'woocommerce-affirm-express', '/test', array(
                'methods'  => 'GET',
                'callback' => array( $this , 'test' )
            )
        );

    }

    public function createOrder(WP_REST_Request $request){
        $this->getSession();

        $data = $request['data'];
        $orderData = $request['data']['order'];
        $user = $orderData['user'];
        error_log(json_encode($user));
//        wc_load_cart();

        $items = $this->setItems($orderData['items'] );
        $address = $this->getAddressArray($orderData['shipping'] , $user  );
        $order = $this->createOrderProgramatically($items, $address);

        return $this->response($order);
    }

    public function getAddressArray($address , $user ){

        $name = $this->splitFullname($address['full_name']);

        $address = array(
            'first_name' => $name['first_name'],
            'last_name'  => $name['last_name'],
            'email'      => $user['email'],
            'phone'      => $user['phone_number'],
            'address_1'  => $address['line1'],
            'address_2'  => $address['line2'],
            'city'       => $address['city'],
            'state'      => $address['state'],
            'postcode'   => $address['zipcode'],
            'country'    => 'US'//$address['country']
        );

        $this->setCustomer($address);

        return $address;
    }

    public function splitFullname($name) {
        $parts = array();
        while ( strlen( trim($name)) > 0 ) {
            $name = trim($name);
            $string = preg_replace('#.*\s([\w-]*)$#', '$1', $name);
            $parts[] = $string;
            $name = trim( preg_replace('#'.$string.'#', '', $name ) );
        }

        if (empty($parts)) {
            return false;
        }
        $parts = array_reverse($parts);
        $name = array(
            'first_name' => $parts[0],
            'middle_name' => (isset($parts[2])) ? $parts[1] : '',
            'last_name' => (isset($parts[2])) ? $parts[2] : ( isset($parts[1]) ? $parts[1] : '')
        );
        return $name;
    }

    public function createOrderProgramatically($items, $address){
        $this->createCart($items);
        $shippingOptions = $this->getShippingOptions();
        $total = WC()->cart->get_subtotal();

        $response =  array(
            'shipping_options' => $shippingOptions,
            'merchant_internal_order_id' => 'ads',
            'tax_amount' => WC()->cart->get_total_tax()*100,
            'total_amount' => $total*100,

        );

        return $response;

    }

    public function getProductSku(){
        $product = wc_get_product($_GET['variation_id']);

        $itemArray = array(array(
            'sku' => $product->get_sku(),
            'display_name' => $product->get_name(),
            'item_url' => get_permalink( $product->get_id() ),
            'item_image_url' => get_the_post_thumbnail_url( $product->get_id() ),
            'unit_price' => $product->get_price()*100,
            'qty' => 1,
        ));

        return rest_ensure_response( $itemArray );
    }

    private function merchantData(){
        $merchantDataArray = array(
            'discount_config_enabled' => false,
            'merchant_apply_discount_endpoint' => array(
                'action' => 'POST',
                'url' => $this->getSiteURL('discount')
            ),
            'merchant_create_order_endpoint' => array(
                'action' => 'POST',
                'url' => $this->getSiteURL('createOrder')
            ),
            'merchant_update_shipping_option_newsletter_endpoint' => array(
                'action' => 'POST',
                'url' => $this->getSiteURL('updateOrder')
            ),
            'newsletter_config_enabled' => false,
            'user_cancel_url' => array(
                'action' => 'POST',
                'url' => $this->getSiteURL('newsletter')
            ),
            'user_confirmation_url' => array(
                'action' => 'POST',
                'url' => $this->getSiteURL('confirm')
            )
        );

        return $merchantDataArray;
    }

    private function metaData(){
        $metaDataArray = array(
            'platform_affirm' => WC_GATEWAY_EXPRESS_VERSION,
            'platform_type' => 'WooCommerce_Express',
            'platform_version' => WOOCOMMERCE_VERSION

        );

        return $metaDataArray;
    }

    private function getProductData ($id , $qty){
        $product = wc_get_product($id);

        $itemArray = array(
            'sku' => $product->get_sku(),
            'display_name' => $product->get_name(),
            'item_url' => get_permalink( $product->get_id() ),
            'item_image_url' => get_the_post_thumbnail_url( $product->get_id() ),
            'unit_price' => $product->get_price()*100,
            'qty' => $qty,
        );

        return $itemArray;

    }

    private function getSiteURL($endPoint){
        return get_site_url(null, '/wp-json/woocommerce-affirm-express/'.$endPoint );
    }

    private function wc_get_product_id_by_variation_sku($sku) {
        $args = array(
            'post_type'  => 'product_variation',
            'meta_query' => array(
                array(
                    'key'   => '_sku',
                    'value' => $sku,
                )
            )
        );
        // Get the posts for the sku
        $posts = get_posts( $args);
        if ($posts) {
            return $posts[0]->post_parent;
        } else {
            return false;
        }
    }

    function getShippingOptions() {
        error_log('shipping');


        $customer = WC()->customer;
        $country = WC()->customer->get_shipping_country();
        $total = WC()->cart->get_subtotal();
        $active_methods   = array();
        $values = array (
            'country' => 'US',//$customer->get_billing_country(),
            'amount'  => $total,
            'state' => 'CA',
            'postcode' => '94108',
            'city' => $customer->get_billing_city(),
            'address' => $customer->get_billing_address_1(),
            'address_2' => $customer->get_billing_address_2(),
        );
        error_log(0);
        error_log(json_encode ($values));
        error_log(0);


        // Fake product number to get a filled card....
        WC()->cart->add_to_cart('1');

        WC()->shipping->calculate_shipping($this->get_shipping_packages($values));
        $shipping_methods = WC()->shipping->packages;
        error_log('***');
        //error_log(json_encode($shipping_methods));
        foreach ($shipping_methods[0]['rates'] as $id => $shipping_method) {
            error_log(json_encode($shipping_method->instance_id));
            $active_methods[] = array(
                'method_title'      => $shipping_method->method_id,
                'merchant_internal_method_code'  => $shipping_method->method_id.':'.$shipping_method->instance_id,
                'carrier_title'      => $shipping_method->label,
                'price'     => number_format($shipping_method->cost, 2, '.', '')*100  );
        }
        return $active_methods;
    }

    function get_shipping_packages($value) {
        $packages = array();
        $packages[0]['contents']                = WC()->cart->cart_contents;
        $packages[0]['contents_cost']           = $value['amount'];
        $packages[0]['applied_coupons']         = WC()->session->applied_coupon;
        $packages[0]['destination']['country']  = $value['country'];
        $packages[0]['destination']['state']    = $value['state'];
        $packages[0]['destination']['postcode'] = $value['postcode'];
        $packages[0]['destination']['city']     = $value['city'];
        $packages[0]['destination']['address']  = $value['address'];
        $packages[0]['destination']['address_2']= $value['address_2'];

        return apply_filters('woocommerce_cart_shipping_packages', $packages);
    }

    function updateOrder(WP_REST_Request $request){
        $data = $request['data'];
//        wc_load_cart();
        $this->getSession();
        WC()->session->set('shippinginfo' , $data );
        WC()->session->set('chosen_shipping_methods', array( $data['order']['chosen_shipping_method']['merchant_internal_method_code'] ) );
        $items = WC()->session->get('items');
        $this->createCart($items);
        $cart = WC()->cart;
        $cart->calculate_shipping();
        $tax = $cart->get_total_tax();
        $shipping_amount = $cart->get_shipping_total();
        $total_amount = $cart->get_subtotal() + $tax + $shipping_amount;

        $response = array(
            'tax_amount' => $tax * 100,
            'shipping_amount' => $shipping_amount * 100,
            'total_amount' => $total_amount * 100,
            'merchant_internal_order_id' => ''
        );
//        $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
//        $this->session = new $session_class();
//        $this->session->init();
//        $this->cart = new WC_Cart();
//
//        $this->session->get('sdf');
        return $this->response( $response );

    }

    function discount(WP_REST_Request $request){
        $data = $request['data'];
        $coupon_code = $data['order']['discount_code'];
        wc_load_cart();
        include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
        $items = WC()->session->get('items');
        $this->createCart($items);
        $cart = WC()->cart;
        $cart->remove_coupons();
        $old_coupon = WC()->session->get('coupon_code');
        error_log('old');
        error_log($old_coupon);
        $cart->calculate_shipping();
        $tax = $cart->get_total_tax();
        $shipping_amount = $cart->get_shipping_total();
        $total_amount = $cart->get_subtotal() + $tax + $shipping_amount;

        $apply_discount =  $cart->add_discount( $coupon_code );
        $new_discount = array();
        $valid_discount = array();
        if($apply_discount){
            $cart->calculate_shipping();
            $new_tax = $cart->get_total_tax();
            $new_shipping_amount = $cart->get_shipping_total();
            $new_total_amount = $cart->get_subtotal() + $tax + $shipping_amount;
            $new_discount = array(
            'discount_amount' => $cart->get_discount_total()*100,
            'discount_code' => $coupon_code,
            'valid' => true
            );
            $valid_discount = $new_discount;
            WC()->session->set('coupon_code' ,$coupon_code );
        } else {
            $new_discount = array(
                'discount_amount' => 0,
                'discount_code' => $coupon_code,
                'valid' => false
            );
            if (isset($old_coupon) && $old_coupon != ''){
                $apply_discount =  $cart->add_discount( $old_coupon );
                if($apply_discount) {
                    $cart->calculate_shipping();
                    $new_tax = $cart->get_total_tax();
                    $new_shipping_amount = $cart->get_shipping_total();
                    $new_total_amount = $cart->get_subtotal() + $tax + $shipping_amount;
                    $valid_discount = array(
                        'discount_amount' => $cart->get_discount_total()*100,
                        'discount_code' => $coupon_code,
                        'valid' => true
                    );
                    WC()->session->set('coupon_code', $coupon_code);
                }
            }

        }
        error_log('dfdfadsf');
        error_log($cart->get_discount_total());

        $cart->calculate_shipping();
        $tax = $cart->get_total_tax();
        $shipping_amount = $cart->get_shipping_total();
        $total_amount = $cart->get_subtotal() + $tax + $shipping_amount - $cart->get_discount_total();
        $shipping_options = $this->getShippingOptions();
        $response = array(
            'discount_data' => array(
                'most_recent_discount_code' => $new_discount,
                'valid_discount_codes' => array($valid_discount)
                ),
            'merchant_internal_order_id' =>'',
            'shipping_options' => $shipping_options,
            'tax_amount' => $tax*100,
            'total_amount' => $total_amount*100
        );

        return $this->response($response);
    }

    public function confirm(WP_REST_Request $request){
        $data = $request['data'];
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
        wc_load_cart();
        $shippinginfo = WC()->session->get('shippinginfo' );
        $cart = WC()->cart;
        $checkout = WC()->checkout();
        $order_id = $checkout->create_order();
        $order = wc_get_order( $order_id );
        $address = WC()->session->get('customer');
        $order->set_address( $address, 'billing' );
        $order->set_address( $address, 'shipping' );
        error_log(json_encode($address));
        $calculate_tax_for = array(
            'country' => $address['country'],
            'state' => $address['state'], // Can be set (optional)
            'postcode' => $address['postcode'], // Can be set (optional)
            'city' => $address['address_1'], // Can be set (optional)
        );

        $item = new WC_Order_Item_Shipping();
        $item->set_method_title( $shippinginfo['order']['chosen_shipping_method']['carrier_title'] );
        $item->set_method_id( $shippinginfo['order']['chosen_shipping_method']['merchant_internal_method_code'] );
        $item->set_total( $shippinginfo['order']['chosen_shipping_method']['price']/100 );
        $item->calculate_taxes($calculate_tax_for);
        $order->add_item( $item );
        $order->calculate_totals();
        error_log(json_encode($address));
        error_log($order_id);

    }

    public function setItems($items){
        $this->session->set('items',$items);
        return $items;
    }

    public function setCustomer($address ){
        $this->session->set('customer',$address);
        foreach ($address as $key=>$val){
            if($key != 'email' && $key != 'phone' ) {
                $method = 'set_shipping_' . $key;
                $this->customer->$method($val);
            }
            $method = 'set_billing_'.$key;
            $this->customer->$method($val);

        };
    }

    public function createCart($items){
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
//        wc_empty_cart();
        wc_empty_cart();
//        $this->cart = new WC()->cart;
        foreach ( $items as $item ){
            $productID = wc_get_product_id_by_sku( $item['sku'] );
            $this->cart->add_to_cart( $productID, 10 );
        }
        return;
    }

    public function response($message){
        $response = new WP_REST_Response( $message );
        $response->set_status( 200 );
       // $response->header( 'Access-Control-Allow-Origin', $origin );
        $response->header( 'Access-Control-Allow-Methods', 'POST, GET, OPTIONS, DELETE, PUT' );
        $response->header( 'Access-Control-Allow-Credentials', true );
        $response->header( 'Access-Control-Allow-Headers', 'content-type' );

        return $response;
    }

    

}

$GLOBALS['wc_affirm_express_loader'] = WC_Affirm_Express_Loader::get_instance();


