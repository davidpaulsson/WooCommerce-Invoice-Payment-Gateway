<?php
/*
Plugin Name: WooCommerce Invoice Payment Gateway
Plugin URI: https://github.com/davidpaulsson/WooCommerce-Invoice-Payment-Gateway/
Description: Provides an Invoice Payment Gateway, mainly for B2B segment where instant payment via PayPal etc. is not a viable option.
Version: 2.0
Author: David Paulsson
Author URI: http://davidpaulsson.se/
License: GPLv2
*/

/** Check if WooCommerce is active **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


add_action('plugins_loaded', 'init_invoice_gateway', 0);
 
    function init_invoice_gateway() {
 

	/**
	 * Invoice Payment Gateway
	 *
	 */
	class WC_Gateway_Invoice extends WC_Payment_Gateway {
	
	    /**
	     * Constructor for the gateway.
	     *
	     */
		public function __construct() {
	        $this->id		= 'invoice';
	        $this->icon 		= apply_filters('woocommerce_invoice_icon', '');
	        $this->has_fields 	= false;
	        $this->method_title     = __( 'invoice', 'dp_wc_invoice' );
	
			// Load the form fields.
			$this->init_form_fields();
	
			// Load the settings.
			$this->init_settings();
	
			// Define user set variables
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
	
			// Actions
			add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
	    	add_action('woocommerce_thankyou_invoice', array(&$this, 'thankyou_page'));

	    	/** Detecting WC version **/
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
			  add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			} else {
			  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}
	
	    	// Customer Emails
	    	add_action('woocommerce_email_before_order_table', array(&$this, 'email_instructions'), 10, 2);
	    }

	    /**
	     * Initialise Gateway Settings Form Fields
	     *
	     */
	    function init_form_fields() {
	
	    	$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'dp_wc_invoice' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Invoice Payment', 'dp_wc_invoice' ),
					'default' => 'yes'
							),
				'title' => array(
					'title' => __( 'Title', 'dp_wc_invoice' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'dp_wc_invoice' ),
					'default' => __( 'Invoice Payment', 'dp_wc_invoice' )
							),
				'description' => array(
					'title' => __( 'Customer Message', 'dp_wc_invoice' ),
					'type' => 'textarea',
					'description' => __( 'Let the customer know the payee and that they\'ll soon receive an invoice with payment instruction and that their order won\'t be shipping until payment is received.', 'dp_wc_invoice' ),
					'default' => __( 'Thank you for your order. You\'ll be invoiced soon.', 'dp_wc_invoice' )
							)
				);
	
	    }
	
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 */
		public function admin_options() {
	
	    	?>
	    	<h3><?php _e('Invoice Payment', 'dp_wc_invoice'); ?></h3>
	    	<p><?php _e('Allows invoice payments. Sends an order email to the store admin who\'ll have to manually create and send an invoice to the customer.', 'dp_wc_invoice'); ?></p>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    	?>
			</table><!--/.form-table-->
	    	<?php
	    }
	
	    /**
	     * Output for the order received page.
	     *
	     */
		function thankyou_page() {
			if ( $description = $this->get_description() )
	        	echo wpautop( wptexturize( $description ) );
		}
	
	    /**
	     * Add content to the WC emails.
	     *
	     */
		function email_instructions( $order, $sent_to_admin ) {
	    	if ( $sent_to_admin ) return;
	
	    	if ( $order->status !== 'on-hold') return;
	
	    	if ( $order->payment_method !== 'invoice') return;
	
			if ( $description = $this->get_description() )
	        	echo wpautop( wptexturize( $description ) );
		}
	
	    /**
	     * Process the payment and return the result
	     *
	     */
		function process_payment( $order_id ) {
			global $woocommerce;
	
			$order = new WC_Order( $order_id );
	
			// Mark as on-hold (we're awaiting the invoice)
			$order->update_status('on-hold', __('Awaiting payment', 'dp_wc_invoice'));
	
			// Reduce stock levels
			$order->reduce_order_stock();
	
			// Remove cart
			$woocommerce->cart->empty_cart();
	
			// Empty awaiting payment session
			unset( $woocommerce->session->order_awaiting_payment );
	
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
	
		}
	
	}
	
	/**
	 * Add the gateway to WooCommerce
	 *
	 */
	function add_invoice_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Invoice';
		return $methods;
	}
	
	add_filter('woocommerce_payment_gateways', 'add_invoice_gateway' );
	
    }
}
