<?php
/**
 * Plugin Name: WC XML Export Modify
 * Plugin URI: https://rayflores.com/plugins/custom-xml-export-modify/
 * Description: modify XML export to allow Datapak needs
 * Author: Ray Flores
 * Author URI: https://rayflores.com
 * Version: 1.0.0
*/

add_filter( 'wc_customer_order_xml_export_suite_xml_root_element', 'custom_root_element', 10, 1 );
function custom_root_element( $root_element  ){
 return 'DatapakServices method="submit_order"';
}
add_filter( 'wc_customer_order_xml_export_suite_orders_footer', 'custom_xml_footer', 10, 1 );
function custom_xml_footer( $footer )
{
   $footer = '</DatapakServices>';
    return $footer;
}
add_filter( 'wc_customer_order_xml_export_suite_orders_xml_data', 'custom_format_xml', 10, 2 );
function custom_format_xml( $xml_array, $orders ){

    $xml_array = array(
            'Source' => array(
                'ID' => 'IMP',
                'Username' => 'johndoe',
                'Password' => 'x423423423'
            ),
            'Order' => array(
                '@attributes' => array('method' => 'order'),
                $orders),
    );

    return $xml_array;
}
/**
 * Adjust the individual order format
 *
 * @since 1.0
 * @param array $order_format existing order array format
 * @param object $order \WC_Order instance
 * @return array
 */
function custom_xml_order_list_format( $order_format, $order) {
	$all_notes = get_formatted_order_notes( $order );
	$notes_string = '';
	foreach ($all_notes->OrderNote as $note){
		$notes_string .= $note;
	}
	
	$payment_info = array();
	if ( $order->payment_method === 'authorize'){
		$payment_info = array( // Credit Card
			'PaymentType' 		=> $order->payment_method_title,
			'CardNumber'  		=> '', // ?
			'ExpirationMonth' 	=> '', // ?
			'ExpirationYear'	=> '', // ?
			'CVV' 				=> '', // ?
			'TransactionID'		=> get_transactionid_from_notes( $order ),
			'AuthCode'			=> '', // ?
		);
	} else { // eCheck
		$payment_info = array(
			'RoutingNumber' 	 => '',
			'AccountNumber'		 => '',
			'CheckNumber'		 => '',
			'NumberOfPayments'	 => '',
			'Payment number="1"' => '',
			'Payment number="2"' => '',
			'Payment number="3"' => '',
			'MerchandiseTotal'	 => $order->get_total(),
			'ShippingCharge'	 => $order->get_total_shipping(),
			'RushCharge'		 => '', // fee?
			'PriorityHandling'	 => '', // shipping fee?
			'SalesTax'			 => wc_format_decimal( $order->get_total_tax(), 2 ),
			'OrderTotal'		 => $order->get_total(),
		);
	}
	
	
	return array(
			'CompanyNumber' => '122',
			'ProjectNumber' => $order->id,
			'OrderNumber' 	=> $order->get_order_number(),
			'SourceCode' 	=> '104',
			'TrackingCode'	=> '100',
			'MediaCode'		=> '33',
			'OrderDate'		=> date('m-d-Y', $order->order_date),
			'OrderTime'		=> date('H:m:i', $order->order_date),
			'ShippingMethod'=> '01', // ?
			'BillingInfo' 	=> array(
								'FirstName' => $order->billing_first_name,
								'LastName' 	=> $order->billing_last_name,
								'Address1' 	=> $order->billing_address_1,
								'Address2' 	=> $order->billing_address_2,
								'City'		=> $order->billing_city,
								'State'		=> $order->billing_state,
								'Zip'		=> $order->billing_postcode,
								'Country'	=> $order->billing_country,
								'Phone'		=> $order->billing_phone,
								'Email'		=> $order->billing_email,
							),
			'ShippingInfo'	=> array(
								'FirstName' => $order->shipping_first_name,
								'LastName' 	=> $order->shipping_last_name,
								'Address1' 	=> $order->shipping_address_1,
								'Address2' 	=> $order->shipping_address_2,
								'City'		=> $order->shipping_city,
								'State'		=> $order->shipping_state,
								'Zip'		=> $order->shipping_postcode,
								'Country'	=> $order->shipping_country,
								'Phone'		=> $order->billing_phone,
								'Email'		=> $order->billing_email,
							),
			'PaymentInfo'	=> $payment_info,
			'Item'			=> 	custom_xml_get_line_items( $order ),		
			);
}	
add_filter( 'wc_customer_order_xml_export_suite_order_data', 'custom_xml_order_list_format', 10, 2 );
/**
 * Adjust the individual line item format
 *
 * @since 1.0
 * @param object $order \WC_Order instance
 * @return array
 */
function custom_xml_get_line_items( $order ) {
	$i = 1;
	foreach( $order->get_items() as $item_id => $item_data ) {
 
		$product = $order->get_product_from_item( $item_data );
		$items[]	= array(
			'ItemCode' => $order->id,
			'Sequence' => $i++,  
			'Quantity'  => $item_data['qty'],
			'Price'  => $product->get_price(),
			'Upsell'  => '',// ?
			'GiftWrap'  => '', // 
			'GiftWrapCharge'  => '', // ?
			'GiftMessage'  => '', // custom meta? 
			'GiftWrapFrom'  => '', // custom meta? 

		);
	}
 
	return $items;
}

function get_transactionid_from_notes ( $order ){
	$all_notes = get_formatted_order_notes( $order );
	$notes_all = '';
	foreach ($all_notes['OrderNote'] as $note){
		$notes_all .= $note['Content'].'<--';
	}
	$notes_string = get_string_between($notes_all, 'Transaction ID: ', '<--');
	return $notes_string;
}
function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function get_formatted_order_notes( $order ) {
		$order_notes = get_order_notes( $order );
		$order_note = array();
		if ( ! empty( $order_notes ) ) {
			foreach ( $order_notes as $note ) {
				$note_content = $note->comment_content;

				/**
				 * Filters the format of order notes in the order XML export
				 *
				 * @since 1.7.0
				 * @param array - the data included for each order note
				 * @param \WC_Order $order
				 * @param object $note the order note comment object
				 */
				$order_note['OrderNote'][] = array(
					'Content' => $note_content,
				);
			}
		}
		return ! empty( $order_note ) ? $order_note : null;
	}
function get_order_notes( $order ) {
		$callback = array( 'WC_Comments', 'exclude_order_comments' );
		$args = array(
			'post_id' => $order->id,
			'approve' => 'approve',
			'type'    => 'order_note',
		);
		remove_filter( 'comments_clauses', $callback );
		$order_notes = get_comments( $args );
		add_filter( 'comments_clauses', $callback );
		return $order_notes;
	}
