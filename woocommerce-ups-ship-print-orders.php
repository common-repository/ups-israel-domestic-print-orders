<?php
/**
* Plugin Name:UPS Israel Domestic Print Orders
* Plugin URI: https://pickuppoint.co.il/Print/Woo
* Description: Create Ship WB tracking number and Print labels
* Version:1.3.1
* Author: O.P.S.I (International Handling) Ltd
* Author URI: https://pickuppoint.co.il
* License:     GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: woocommerce-ups-ship-print-orders
* Domain Path: /languages
* {Plugin Name} is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 2 of the License, or
* any later version.
*
* UPS Israel Domestic Print Orders is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with UPS Israel Domestic Print Orders. If not, see  https://www.gnu.org/licenses/gpl-2.0.html.
*
* @author      O.P.S.I (International Handling) Ltd
* @category    Shipping
* @copyright   Copyright: (c) 2016-2018 O.P.S.I (International Handling) Ltd
* @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
*/


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if (!class_exists('WC_UPS_Domestic_Print')) {

	class WC_UPS_Domestic_Print  {

		// WC_UPS_Domestic_Print The single instance of the class

		protected static $_instance = null;

		//Default properties

		public static $plugin_url;
		public static $plugin_path;
		public static $plugin_version;
		public static $plugin_prefix;
		public static $plugin_basefile;
		public static $plugin_basefile_path;
		public static $plugin_text_domain;

		private function define_constants() {

			self::$plugin_version = '1.3.0';
			self::$plugin_prefix = 'wuspo_';
			self::$plugin_basefile_path = __FILE__;
			self::$plugin_basefile = plugin_basename( self::$plugin_basefile_path );
			self::$plugin_url = plugin_dir_url( self::$plugin_basefile );
			self::$plugin_path = trailingslashit( dirname( self::$plugin_basefile_path ) );
			self::$plugin_text_domain = trim( dirname( self::$plugin_basefile ) );

		}

		// WC_UPS_Domestic_Print - Main instance

		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		//WC_UPS_Domestic_Print constructor.

		public function __construct()
		{
			$this->define_constants();

			register_activation_hook(self::$plugin_basefile_path, array($this,'ups_print_on_activation'));
			//load admin scripts
			if( is_admin() ){
				add_action('admin_enqueue_scripts', array ($this,'ups_print_enqueueScript'));
				add_action('wp_ajax_ups_woocommerce_printwb',array ($this,'ups_woocommerce_printwb'));
				add_action('admin_init',array ($this,'ups_woocommerce_download'));
			}
			// Add 'WB' column in 'Orders' admin page
			add_filter('manage_edit-shop_order_columns', array ($this,'ups_print_WB_addColumn'));

			// Add printed status to combo box in order view
			add_filter('wc_order_statuses', array($this,'ups_print_add_awaiting_shipment_to_order_statuses'));

			// Add 'Printed' count label in 'Orders' admin page
			add_action('init', array($this,'ups_print_register_awaiting_shipment_order_status'));

			// Add 'Print Failed' status to combo box in order view
			add_filter('wc_order_statuses', array( $this,'ups_print_add_failed_order_statuses'));

			// Add 'Print Failed' count label in 'Orders' admin page
			add_filter('init', array($this,'ups_print_register_failed_order_statuses'));

			// Handle translation
			add_action('plugins_loaded', array($this,'ups_print_load_textdomain'));

			//Add button style
			add_filter( 'woocommerce_admin_order_actions_end', array ($this,'ups_print_add_button' ));

			add_action( 'admin_enqueue_scripts', array ($this,'ups_print_load_custom_wp_admin_style') );

			add_action ('init',array($this,'wuspo_update_tracking_in_DB'));



		}

		public function ups_print_load_custom_wp_admin_style ()
		{
			wp_register_style( 'ups_print_css', plugin_dir_url( __FILE__ ) . 'css/ups-print-style.css', false, '1.2.1' );
			wp_enqueue_style( 'ups_print_css' );
		}

		public function ups_print_on_activation()
		{   //Check Woocommerce
			if( !class_exists( 'woocommerce' ) )
			{
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( __( 'Sorry, you need to activate woocommerce first.', 'woocommerce-ups-ship-print-orders' ));
			}
			//Load localization files
			load_plugin_textdomain( self::$plugin_text_domain, false, dirname( self::$plugin_basefile ) . '/languages/' );
			//Add in MySQL column 'WB'
			global $wpdb;

			$woocom_table = $wpdb->prefix . 'woocommerce_order_items';

				$myCustomer = $wpdb->get_row("SELECT * FROM $woocom_table");
				if(!isset($myCustomer->wb)) {

					$wpdb->query("ALTER TABLE $woocom_table ADD wb varchar(500) NOT NULL DEFAULT 0");

					} return true;

		}
		// add button to orders page
		public function ups_print_add_button()
		{
			global $post;
			echo '<a class="button tips ups-print ups-print-icon" data-tip="Print Only Tnis Order: # '.$post->ID.' !" alt="WB-Print-Label" data-orderid="'.$post->ID.'">UPS</a>';
		}

		public function ups_print_load_textdomain()
		{
			// Load language files from the wp-content/languages/plugins folder
			$mo_file = WP_LANG_DIR . '/plugins/' . self::$plugin_text_domain . '-' . get_locale() . '.mo';
			if( is_readable( $mo_file ) ) {
				load_textdomain( self::$plugin_text_domain, $mo_file );
			}
			// Otherwise load them from the plugin folder
			load_plugin_textdomain( self::$plugin_text_domain, false, dirname( self::$plugin_basefile ) . '/languages/' );
		}


		public function ups_print_enqueueScript($hook) {
			if( 'edit.php' != $hook ) {
				return;
			}
			//Add JS
			wp_enqueue_script('woocommerce_ups_ship_print_orders_admin.js',
				self::$plugin_url . '/js/woocommerce_ups_ship_print_orders_admin.js?rev=1.32', array('jquery'), null, false);



			// Customer Id data
			global $wpdb;

			$table_name = $wpdb->prefix . 'woocommerce_order_items';

			$post_type = '';
			if(isset($_GET["post_type"]))  $post_type = $_GET['post_type'];

			$myCustomer = '';

			if ( $post_type == 'shop_order')
			{
				$myCustomer = $wpdb->get_results("SELECT * FROM $table_name ");
			}

			// Ajax url data with hook ups_woocommerce_printwb

				$ajax_urls = array(
                    'dest_url'  => admin_url( 'admin-ajax.php' ),

                );

			// Set data in array to be available in JS
			$data_array = array(
				'WB-Print-Label' =>__('button ups-print ups-print-icon'),
				'print_wb_labels' => __('Print WB Labels', 'woocommerce-ups-ship-print-orders'),
				'customer_data' => $myCustomer,
				'ajax_urls' => $ajax_urls,
				'nonce' => wp_create_nonce('myajax-nonce')
			);

			wp_localize_script('woocommerce_ups_ship_print_orders_admin.js', 'upsPrintData', $data_array);

		}

		// Add 'WB' column in 'Orders' admin page
		public function ups_print_WB_addColumn($columns)
		{
			$columns['shipping_wb'] = 'WB';

			return $columns;
		}

		// Add count label in 'Orders' admin page
		public function ups_print_register_awaiting_shipment_order_status()
		{
			register_post_status( 'wc-printed', array(

				'label'                     => __('Printed', 'woocommerce-ups-ship-print-orders'),

				'public'                    => true,

				'exclude_from_search'       => false,

				'show_in_admin_all_list'    => true,

				'show_in_admin_status_list' => true,

				'label_count'               => _n_noop( 'Printed <span class="count">(%s)</span>', 'Printed <span class="count">(%s)</span>', 'woocommerce-ups-ship-print-orders' )

			) );

		}

		// Add 'Printed' status to combo box in order view
		public function ups_print_add_awaiting_shipment_to_order_statuses( $order_statuses )
		{

			$order_statuses['wc-printed'] = __( 'Printed', 'woocommerce-ups-ship-print-orders' );

			return $order_statuses;

		}

		// Add 'Print Failed' status to combo box in order view
		public function ups_print_add_failed_order_statuses( $order_statuses )
		{

			$order_statuses['wc-printfailed'] = _x( 'Print Failed', 'WooCommerce Order status', 'woocommerce-ups-ship-print-orders' );

			return $order_statuses;

		}

		// Add 'Print Failed' count label in 'Orders' admin page
		public function ups_print_register_failed_order_statuses()
		{

			register_post_status( 'wc-printfailed', array(

				'label'                     => _x( 'Print Failed', 'WooCommerce Order status', 'woocommerce-ups-ship-print-orders' ),

				'public'                    => true,

				'exclude_from_search'       => false,

				'show_in_admin_all_list'    => true,

				'show_in_admin_status_list' => true,

				'label_count'               => _n_noop( 'Print Failed (%s)', 'print failed (%s)', 'woocommerce-ups-ship-print-orders' )

			) );
		}



		public function wuspo_update_tracking_in_DB()
		{

			if (!empty($_GET['orderid']) && $_GET['trackingid'] && $_GET['status'])
			{


                ob_clean();

                global $wpdb;
                global $woocommerce;

                $woocom_table = $wpdb->prefix . 'woocommerce_order_items';

                $orderid= intval($_GET['orderid']);
				$trackingid= sanitize_text_field($_GET['trackingid']);
                $status=sanitize_text_field($_GET['status']);

                $statuses = wc_get_order_statuses();
                $result = $wpdb->update($woocom_table,
                            array('wb' => $trackingid),
							array( 'order_id' => $orderid ),
                            array( '%s'),array( '%d' ) );

                $order = new WC_Order( $orderid );
                print_r($order->status);
                $order->update_status($status, 'Update status to ' . $statuses[$orderid] . ' for tracking number ' . $trackingid);
                $order->save();

                $update_res = array();
                $update_res['success'] = ($result === false) ? 'false' : 'true';
                $update_res['orderid'] = $orderid;
                $update_res['wb'] = $trackingid;
                $update_res['status'] = $statuses[$status];
                $update_res['last_error'] = ($result === false) ? $wpdb->last_error : '';

                $update_res_json = json_encode($update_res,JSON_PRETTY_PRINT);


                echo $update_res_json;
                echo json_encode($statuses,JSON_PRETTY_PRINT);


                http_response_code(201);
                exit;

				die();
			}
		}

        public function ups_woocommerce_printwb()
		{
            if (!isset($_POST['ids']) || $_POST['ids'] == '') {
                echo 'Sorry, something went wrong!';
                wp_die();
            }

            $ids = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array();
            $ids = array_map(null,$ids);

            $Orders = array();
			$i = 1;
            foreach ($ids as $id) {

                $total_item_amount = 0;
                $numberOfpackages = 1;

                $the_order = wc_get_order( $id );
                $jsondata = "";
                foreach ( $the_order->get_shipping_methods() as $shipping_item ) {

                    if ( 'woo-ups-pickups' == $shipping_item['method_id'] ) {
                        $jsondata = wc_get_order_item_meta( $id, 'pkps_json');
                    }
                }

                $the_order = new WC_Order($id);
                // Calculate weight
                if (sizeof($the_order->get_items()) > 0) {
                    $weight = 0;
                    foreach ($the_order->get_items() as $item) {
                        if ($item['product_id'] > 0) {
                            $_product = $the_order->get_product_from_item($item);
                            if (!$_product->is_virtual()) {
                                $weight += $_product->get_weight() * $item['qty'];
                            }
                        }
                    } // foreach
                } // if

                if (get_post_meta($id, '_shipping_first_name', true) == '') {
                    $billing_first_name = get_post_meta($id, '_billing_first_name', true);
                    $billing_last_name = get_post_meta($id, '_billing_last_name', true);
                    $billing_company = get_post_meta($id, '_billing_company', true);
                    $billing_address = get_post_meta($id, '_billing_address_1', true);
                    $billing_address2 = get_post_meta($id, '_billing_address_2', true);
                    $billing_city = get_post_meta($id, '_billing_city', true);
                    $billing_postcode = get_post_meta($id, '_billing_postcode', true);
                    $billing_country = get_post_meta($id, '_billing_country', true);
                    $billing_state = get_post_meta($id, '_billing_state', true);
                    $billing_email = get_post_meta($id, '_billing_email', true);
                    $billing_phone = get_post_meta($id, '_billing_phone', true);
                } else {
                    $billing_first_name = get_post_meta($id, '_shipping_first_name', true);
                    $billing_last_name = get_post_meta($id, '_shipping_last_name', true);
                    $billing_address = get_post_meta($id, '_shipping_address_1', true);
                    $billing_address2 = get_post_meta($id, '_shipping_address_2', true);
                    $billing_city = get_post_meta($id, '_shipping_city', true);
                    $billing_postcode = get_post_meta($id, '_shipping_postcode', true);
                    $billing_country = get_post_meta($id, '_shipping_country', true);
                    $billing_state = get_post_meta($id, '_shipping_state', true);
                    $billing_email = get_post_meta($id, '_shipping_email', true);
                    $billing_phone = get_post_meta($id, '_shipping_phone', true);

                    if ($billing_phone == '')
                        $billing_phone = get_post_meta($id, '_billing_phone', true);
                }
                $Orders['Orders'][$i]['ConsigneeAddress']['City'] = $billing_city;
                $Orders['Orders'][$i]['ConsigneeAddress']['ContactName'] = $billing_first_name . ' ' . $billing_last_name;
                $Orders['Orders'][$i]['ConsigneeAddress']['HouseNumber'] = $billing_address;
                $Orders['Orders'][$i]['ConsigneeAddress']['PhoneNumber'] = $billing_phone;
                $Orders['Orders'][$i]['ConsigneeAddress']['Street'] = $billing_address2;
                $Orders['Orders'][$i]['ConsigneeAddress']['Email'] = $billing_email;
                $Orders['Orders'][$i]['PKP'] = $jsondata;
                $Orders['Orders'][$i]['OrderID'] = $id;
                $Orders['Orders'][$i]['Weight'] = $weight;
                $Orders['Orders'][$i]['NumberOfPackages'] = $numberOfpackages;//$numberOfpackages; Allways one
                $i++;
            }

            $json = json_encode($Orders, JSON_UNESCAPED_UNICODE);
            print_r($json);
			$file = self::$plugin_path . 'orders.ship';

			// Delete any previous file
			if (file_exists($file)) {
				unlink($file);
			}
			//Write file with to server
			file_put_contents($file, $json);
        }

		public function ups_woocommerce_printwb_old()

		{   if (!isset($_POST['ids']) || $_POST['ids'] == '') {
			echo 'Sorry, something went wrong!';
			wp_die();
		}

			// check nonce
			$nonce = $_POST['nonce'];
			if (!wp_verify_nonce($nonce, 'myajax-nonce'))
				die('Nonce error!');
			if (!current_user_can('publish_posts'))
				die('You not authorizid!');
			global $woocommerce;
			global $wpdb;
			$posts_table = $wpdb->prefix . 'posts';


			$ids = isset( $_POST['ids'] ) ? (array) $_POST['ids'] : array();
			$ids = array_map(null,$ids);


			$fetch_orders = $wpdb->get_results("
			SELECT *
			FROM $posts_table
			WHERE `post_status`='wc-processing' || `post_status`= 'wc-completed' || `post_status`='wc-pending' || `post_status`='wc-on-hold' || `post_status`='wc-refunded' || `post_status`='wc-failed' || `post_status`='wc-cancelled' || `post_status`='wc-printed' || `post_status`='wc-printfailed'");

			$Orders = array();
			$i = 1;
			foreach ($fetch_orders as $values) {
				$id = $values->ID;

				if (in_array($id, $ids)) {

					$total_item_amount = 0;
					$numberOfpackages = 1;

					/*
					* Check is this PICKUP order
					*/
					$the_order = wc_get_order( $id );
					$jsondata = "";
					foreach ( $the_order->get_shipping_methods() as $shipping_item ) {

						if ( 'woo-ups-pickups' == $shipping_item['method_id'] ) {
							$jsondata = wc_get_order_item_meta( $id, 'pkps_json');
						}
					}

					$the_order = new WC_Order($id);
					// Calculate weight
					if (sizeof($the_order->get_items()) > 0) {
						$weight = 0;
						foreach ($the_order->get_items() as $item) {
							if ($item['product_id'] > 0) {
								$_product = $the_order->get_product_from_item($item);
								if (!$_product->is_virtual()) {
									$weight += $_product->get_weight() * $item['qty'];
								}
							}
						} // foreach
					} // if



					if (get_post_meta($id, '_shipping_first_name', true) == '') {
						$billing_first_name = get_post_meta($id, '_billing_first_name', true);
						$billing_last_name = get_post_meta($id, '_billing_last_name', true);
						$billing_company = get_post_meta($id, '_billing_company', true);
						$billing_address = get_post_meta($id, '_billing_address_1', true);
						$billing_address2 = get_post_meta($id, '_billing_address_2', true);
						$billing_city = get_post_meta($id, '_billing_city', true);
						$billing_postcode = get_post_meta($id, '_billing_postcode', true);
						$billing_country = get_post_meta($id, '_billing_country', true);
						$billing_state = get_post_meta($id, '_billing_state', true);
						$billing_email = get_post_meta($id, '_billing_email', true);
						$billing_phone = get_post_meta($id, '_billing_phone', true);
					} else {
						$billing_first_name = get_post_meta($id, '_shipping_first_name', true);
						$billing_last_name = get_post_meta($id, '_shipping_last_name', true);
						$billing_address = get_post_meta($id, '_shipping_address_1', true);
						$billing_address2 = get_post_meta($id, '_shipping_address_2', true);
						$billing_city = get_post_meta($id, '_shipping_city', true);
						$billing_postcode = get_post_meta($id, '_shipping_postcode', true);
						$billing_country = get_post_meta($id, '_shipping_country', true);
						$billing_state = get_post_meta($id, '_shipping_state', true);
						$billing_email = get_post_meta($id, '_shipping_email', true);
						$billing_phone = get_post_meta($id, '_shipping_phone', true);

						if ($billing_phone == '')
							$billing_phone = get_post_meta($id, '_billing_phone', true);
					}
					$Orders['Orders'][$i]['ConsigneeAddress']['City'] = $billing_city;
					$Orders['Orders'][$i]['ConsigneeAddress']['ContactName'] = $billing_first_name . ' ' . $billing_last_name;
					$Orders['Orders'][$i]['ConsigneeAddress']['HouseNumber'] = $billing_address;
					$Orders['Orders'][$i]['ConsigneeAddress']['PhoneNumber'] = $billing_phone;
					$Orders['Orders'][$i]['ConsigneeAddress']['Street'] = $billing_address2;
					$Orders['Orders'][$i]['ConsigneeAddress']['Email'] = $billing_email;
					$Orders['Orders'][$i]['PKP'] = $jsondata;
					$Orders['Orders'][$i]['OrderID'] = $id;
					$Orders['Orders'][$i]['Weight'] = $weight;
					$Orders['Orders'][$i]['NumberOfPackages'] = $numberOfpackages;//$numberOfpackages; Allways one
					$i++;
				} // if
			} //foreach

			$json = json_encode($Orders, JSON_UNESCAPED_UNICODE);

			$file = self::$plugin_path . 'orders.ship';

			// Delete any previous file
			if (file_exists($file)) {
				unlink($file);
			}
			//Write file with to server
			file_put_contents($file, $json);
		}//end very long ups_woocommerce_printwb


		public function ups_woocommerce_download()
		{
			if( !empty($_GET['filename']) )
			{

				$name_pre = 'orders';
				$name_end = 'ship';
				$name = (self::$plugin_path . $name_pre . '.' . $name_end);

				header('Content-Description: File Transfer');
				header('Content-Type: application/force-download');
				header("Content-Disposition: attachment; filename=\"" . $name_pre . filesize($name) . '_' . date('d_m_Y__H_i_s') . '.' . $name_end . "\";");
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');
				//header('Content-Length: ' . filesize($name));


				if (ob_get_contents()) ob_end_clean();

				flush();

				readfile($name); //showing the path to the server where the file is to be download

				unlink($name); //delete file from server

				exit;

				die();
			}
		}

	}



}

//Returns the main instance of to prevent the need to use globals.

function WUDP() {
	return WC_UPS_Domestic_Print::instance();
}

// Global for backwards compatibility

$GLOBALS['wuspo'] = WUDP();

