<?php
/**
 * Affirm Payment Gateway
 *
 * Provides a form based Affirm Payment Gateway.
 *
 * @class 		WC_Gateway_Affirm
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		WooThemes
 */

class WC_Affirm_Express extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id = 'affirm_express';
        $this->icon = 'https://cdn-assets.affirm.com/images/blue_logo-transparent_bg.png';
		$this->has_fields = false;
		$this->method_title = __( 'Affirm', 'woocommerce-affirm-express' );
		$this->method_description = sprintf(
			/* translators: 1: html starting code 2: html end code */
			__( 'Works by sending the customer to %1$sAffirm%2$s to enter their payment information.', 'woocommerce-affirm-express' ),
			'<a href="http://affirm.com/">', '</a>'
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->description    = $this->get_option( 'description' );
		//$this->auth_only_mode = $this->get_option( 'transaction_mode' ) === self::TRANSACTION_MODE_AUTH_ONLY ? true : false;
        $this->product_page = $this->get_option( 'product_page');
        $this->cart_page = $this->get_option( 'cart_page');
        $this->promo = $this->get_option( 'promo');
        $this->width = $this->get_option( 'width');
        $this->height = $this->get_option( 'height');


        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	}



	/**
	 * Initialise Gateway Settings Form Fields
	 *
	 * @since 1.0.0
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-affirm-express' ),
				'label'       => __( 'Enable Affirm', 'woocommerce-affirm-express' ),
				'type'        => 'checkbox',
				'description' => __( 'This controls whether or not this customers can checkout with affirm on product/cart pages.', 'woocommerce-gateway-affirm' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),

//			'transaction_mode' => array(
//				'title'       => __( 'Transaction Mode', 'woocommerce-gateway-affirm' ),
//				'type'        => 'select',
//				'description' => __( 'Select how transactions should be processed.', 'woocommerce-gateway-affirm' ),
//				'default'     => self::TRANSACTION_MODE_AUTH_AND_CAPTURE,
//				'options'     => array(
//					self::TRANSACTION_MODE_AUTH_AND_CAPTURE => __( 'Authorize and Capture', 'woocommerce-gateway-affirm' ),
//					self::TRANSACTION_MODE_AUTH_ONLY        => __( 'Authorize Only', 'woocommerce-gateway-affirm' ),
//				),
//			),
            'product_page' => array(
                'title'       => __( 'Product Page', 'woocommerce-gateway-affirm' ),
                'label'       => __( 'Show Affirm Checkout button on Product Page.', 'woocommerce-gateway-affirm' ),
                'type'        => 'checkbox',
                'description' => __( 'Show Affirm Checkout button on Product Page.', 'woocommerce-gateway-affirm' ),
                'default'     => 'yes',
            ),
            'cart_page' => array(
                'title'       => __( 'Cart Page', 'woocommerce-gateway-affirm' ),
                'type'        => 'checkbox',
                'description' => __( 'Show Affirm Checkout button on Cart Page.', 'woocommerce-gateway-affirm.' ),
                'default'     => 'yes',
            ),
            'promo' => array(
                'title'       => __( 'Promo/Discount Code', 'woocommerce-gateway-affirm' ),
                'type'        => 'checkbox',
                'description' => __( 'Enable promo/discount codes to be entered.', 'woocommerce-gateway-affirm.' ),
                'default'     => 'yes',
            ),
            'width' => array(
                'title'     	  => __('Checkout Button height','woocommerce-gateway-affirm'),
                'type'     	  => 'text',
                'description'  => 'Set minimum amount for Affirm to appear at checkout.',
                'default'      => '50',
            ),
            'height' => array(
                'title'       => __('Checkout Button Width','woocommerce-gateway-affirm'),
                'type'     	  => 'text',
                'description' => 'Set maximum amount for Affirm to appear at checkout.',
                'default'     => '200'
            ),
		);
	}


}
