<?php

/**
 * Woocommerce - Cart & Checkout
 * Replace image with lumise design
 * Add design edit link to image
 */

remove_filter('woocommerce_cart_item_thumbnail', array($lumise_woo, 'woo_cart_design_thumbnails'), 10, 3);
add_filter('woocommerce_cart_item_thumbnail', 'lumise_cart_thumbnail', 10, 3);

function lumise_cart_thumbnail($product_image, $item, $cart_item_key) {
	global $lumise_printings, $lumise;
	
	$design_thumb = '';
	
	if ( isset($item['lumise_data']) ) {
		
		$cart_item_data = $lumise->lib->get_cart_data( $item['lumise_data'] );
		$color = $lumise->lib->get_color($cart_item_data['attributes']);

		if(is_cart()) {
			
			$tool_url = site_url('/design-editor/');
			$is_query = explode('?', $tool_url);
			$cart_id = $item['lumise_data']['cart_id'];
			$url = $tool_url.((isset($is_query[1]) && !empty($is_query[1]))? '&' : '?').'product_base='.$cart_item_data['product_id'].'&product_cms='.$cart_item_data['product_cms'].'&cart='.$cart_id;

		}
		
		if(isset($cart_item_data['screenshots']) && is_array($cart_item_data['screenshots']) ){

			foreach ($cart_item_data['screenshots'] as $screenshot) {
				if (is_cart()) $design_thumb .= '<a id="'.$cart_id.'" href="'.$url.'" title="'.__('Edit Design', 'lumise').'">';
				$design_thumb .= '<img style="background:'.$color.';padding: 0px;" class="lumise-cart-thumbnail" src="'.$lumise->cfg->upload_url.$screenshot.'" />';
				if (is_cart()) $design_thumb .= '</a>';
			}
			
		}

	}
	
	if (intval($lumise->cfg->settings['show_only_design']) == 1 && isset($item['lumise_data']) ) {
		$product_image = '';
	}

	return $product_image.$design_thumb;
}

add_filter('woocommerce_order_item_thumbnail', 'lumise_order_item_thumbnail', 10, 2);

function lumise_order_item_thumbnail($product_image, $item){
	global $lumise, $post;
		
	$item_data = $item->get_data();

	$data = array(
		"product_cms" => $item_data['product_id'],
		"cart_id" => '',
		"product_base" => '',
		"template" => '',
		"order_id" => $item_data['order_id'],
		"item_id" => $item_data['id']
	);
		
	$lumise_image = lumise_get_image($data);

	if ( $lumise_image ) $product_image = '';

	return $lumise_image . $product_image;
}

/**
 * Woocommerce - Admin Order
 * Replace image with lumise design
 */

add_filter( 'woocommerce_admin_order_item_thumbnail', 'lumise_woocommerce_admin_order_item_thumbnail', 10, 3 ); 

function lumise_woocommerce_admin_order_item_thumbnail($product_image,  $item_id,  $item ) {

	global $lumise, $post;
		
	$item_data = $item->get_data();

	$data = array(
		"product_cms" => $item_data['product_id'],
		"cart_id" => '',
		"product_base" => '',
		"template" => '',
		"order_id" => $item_data['order_id'],
		"item_id" => $item_data['id']
	);
		
	$lumise_image = lumise_get_image($data);

	if ( $lumise_image ) $product_image = '';

	return $lumise_image . $product_image;

}

/**
 * WooCommerce - Lumise image function
 */

function lumise_get_image($data) {
	global $lumise;

	$product = wc_get_product($data['product_cms']);

	if (count($item_data['meta_data']) > 0) {
		foreach ($item_data['meta_data'] as $meta_data) {
			if ($meta_data->key == 'lumise_data') {
				$data['cart_id'] = $meta_data->value['cart_id'];
				break;
			}
		}
	}
	
	$data['product_base'] = get_post_meta($data['product_cms'], 'lumise_product_base', true );
	
	if (empty($data['cart_id'])) {
		$data['template'] = get_post_meta($data['product_cms'], 'lumise_design_template', true );	
	}

	$id_parent = 0;
	$is_variation = false;

	if($product->get_parent_id() != null && intval($product->get_parent_id()) != 0) {

		$id_parent = $product->get_parent_id();
		$product_parent = wc_get_product( $id_parent );
		$is_variation = $product_parent->is_type( 'variable' );

	}

	if ( empty($data['cart_id']) && $id_parent != 0 && $is_variation == true ) {

		$data['template'] = get_post_meta($data['product_cms'], '_variation_lumise', true );
		$data['product_base'] = 'variable:'.$product->get_id();	

	}

	if (count($item_data['meta_data']) > 0) {

		foreach ($item_data['meta_data'] as $meta_data) {
			if ($meta_data->key == 'lumise_data' && $data['product_base'] == '' && isset($meta_data->value['id']) && strpos($meta_data->value['id'], 'variable') !== false ) {
				$data['product_base'] = $meta_data->value['id'];
				break;
			}
		}

	}

	$scrs = array();
	$data['design'] = '';
	$prtable = false;
	$pdfid = '';

	// Customized designs

	if(empty($data['cart_id'])){

		$session_name = 'product_'.$data['order_id'].'_'.$data['product_cms'].'_'.$data['product_base'].'_length';
		$itemCheckAgain = $lumise->db->rawQuery( sprintf("SELECT * FROM `%s` WHERE `order_id`='%s' AND `product_id`='%s' AND `product_base`='%s' ", $lumise->db->prefix.'order_products', $data['order_id'], $data['product_cms'], $data['product_base'] ) );

		if(count($itemCheckAgain) > 0) {

			// create new temp
			if( !isset($_SESSION[$session_name])|| ( isset($_SESSION[$session_name]) && $_SESSION[$session_name]['order_id'] != $data['order_id'] ) ) {
				$_SESSION[$session_name] = array(
					'order_id' => $data['order_id'],
					'product_cms' => $data['product_cms'],
					'product_base' => $data['product_base'],
					'index' => 0,
					'maxIndex' => count($itemCheckAgain)
				);
			}

			$item = $lumise->db->rawQuery( sprintf( "SELECT * FROM `%s` WHERE `order_id`='%s' AND `product_id`='%s' AND `product_base`='%s' ORDER BY id ASC LIMIT ".intval($_SESSION[$session_name]['index']).",1", $lumise->db->prefix.'order_products', $data['order_id'], $data['product_cms'], $data['product_base'] ) );
	
			if (count($item) > 0) {

				$_SESSION[$session_name]['index'] = intval($_SESSION[$session_name]['index'])+1;

				$sc = @json_decode($item[0]['screenshots']);

				$prtable = true; 
				$pdfid = $item['cart_id'];

				$data['design'] = $item[0]['design'];
	
				foreach ($sc as $i => $s) {

					array_push($scrs, array(
						"url" => is_array($prt) && isset($prt[$i]) ? $lumise->cfg->upload_url.'orders/'.$prt[$i] : '#',
						"screenshot" => $lumise->cfg->upload_url.'orders/'.$s,
						"download" => true
					));

				}

				if( intval($_SESSION[$session_name]['index']) == intval($_SESSION[$session_name]['maxIndex']) ){
					unset($_SESSION[$session_name]);
				}

			}

		}

		if (!empty($data['cart_id'])) {
			
			$item = $lumise->db->rawQuery( sprintf( "SELECT * FROM `%s` WHERE `cart_id`='%s'", $lumise->db->prefix.'order_products', $data['cart_id'] ) );
			
			if (count($item) > 0) {
				
				$sc = @json_decode($item[0]['screenshots']);
				
				$data['design'] = $item[0]['design'];
				
				foreach ($sc as $i => $s) {
					array_push($scrs, array(
						"url" => is_array($prt) && isset($prt[$i]) ? $lumise->cfg->upload_url.'orders/'.$prt[$i] : '#',
						"screenshot" => $lumise->cfg->upload_url.'orders/'.$s,
						"download" => true
					));
				}
				
			}
			
		} else if (!empty($data['template'])) {
			
			$temps = json_decode(urldecode($data['template']));
			if(isset($temps->stages)){
				$tempsData = json_decode(urldecode(base64_decode($temps->stages)));
				$temps = new stdClass();

				foreach ($tempsData as $key => $detail) {
					if(isset($detail->template) && isset($detail->template->id)){
						$detailtemplate = $lumise->lib->get_template($detail->template->id);
						if($detailtemplate != null){
							$tempsData->$key->template->screenshot = $detailtemplate['screenshot'];
						}
						$temps->$key = $detail->template;
					}
				}
			}
			
			foreach ($temps as $n => $d) {
				
				$dsg = $lumise->db->rawQuery( sprintf( "SELECT * FROM `%s` WHERE `id`=%d", $lumise->db->prefix.'templates', $d->id ) );
				
				if (count($dsg) > 0 && strpos($dsg[0]['upload'], '.lumi') === false) {
					$pdfid .= $d->id.',';
					array_push($scrs, array(
						"url" => $lumise->cfg->upload_url.$dsg[0]['upload'],
						"screenshot" => $d->screenshot,
						"download" => true
					));
				} else {
					array_push($scrs, array(
						"url" => '',
						"screenshot" => $d->screenshot
					));
				}
				
			}
			
		}
		
		if (count($scrs) > 0) {

			global $lumise;

			$key = $lumise->get_option('purchase_key');
			$key_valid = ($key === null || empty($key) || strlen($key) != 36 || count(explode('-', $key)) != 5) ? false : true;
			
			$is_query = explode('?', $lumise->cfg->tool_url);
			
			$product = wc_get_product($data['product_cms']);
			
			if ($product && $product->get_type() == 'variation') {
				$data['product_base'] = 'variable:'.$data['product_cms'];
				$data['product_cms'] = $product->get_parent_id();
			}

			if($key_valid){

				foreach ($scrs as $i => $scr) {
					$images .= '<img src="'.$scr['screenshot'].'" />';
				}

			}

			return $images;
			
		}

	}

	return;


}
