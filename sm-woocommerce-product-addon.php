<?php
/*
Plugin Name: sm-wcpa
Plugin URI: https://www.samanik.com
Description: custom plugin to add extra products to the variables products
Version: 1.0.0
Author: Moortak
Author URI: https://www.samanik.com/
Text Domain: sm-wpca
Domain Path: /languages
*/

add_action('plugins_loaded', 'sm_wcpa_textdomain');
function sm_wcpa_textdomain()
{
	load_plugin_textdomain('sm-wcpa', false, dirname(plugin_basename(__FILE__)));
}

if (!function_exists('sm_wcpa_get_products_as_options')) {
	function sm_wcpa_get_products_as_options()
	{
//		$products_array = [null => __('please select products that you want to be shown as offer for user after adding primary product to the cart', 'sm')];
		$products = wc_get_products(['status' => 'publish', 'type' => 'simple', 'limit' => '-1']);
		foreach ($products as $i => $product) {
			$products_array[$product->id] = $product->name;
		}
		return $products_array;
	}
}

if (!function_exists('sm_wccpd_custom_product_tabs')) {
	/**
	 * Add a custom product tab.
	 */
	function sm_wccpd_custom_product_tabs($tabs)
	{
		$tabs['sm_wcpa_extra_products'] = array(
			'label'  => __('Samanik Extra Products', 'sm-wcpa'),
			'target' => 'sm_wc_extra_products_options',
			'class'  => array('show_if_variable'),
		);
		return $tabs;
	}
	
	add_filter('woocommerce_product_data_tabs', 'sm_wccpd_custom_product_tabs');
}

if (!function_exists('extra_products_options_product_tab_content')) {
	/**
	 * Contents of the gift card options product tab.
	 */
	
	function extra_products_options_product_tab_content()
	{
		global $post;
		$product = wc_get_product($post->ID);
		$child_products = $product->get_meta('_sm_wcpa_child_product');
		// Note the 'id' attribute needs to match the 'target' parameter set above
		?>
        <div id='sm_wc_extra_products_options' class='panel woocommerce_options_panel'><?php
		?>
        <div class='options_group'><?php
			woocommerce_wp_checkbox(
				[
					'id'    => '_has_extra_products',
					'label' => __('has extra products', 'sm-wcpa'),
				]);
			woocommerce_wp_select(
				[
					'class'             => 'multiselect attribute_values wc-enhanced-select',
					'custom_attributes' => ['multiple' => 'multiple', 'style' => 'width:100% !important;'],
					'id'                => '_sm_wcpa_child_product[]',
					'label'             => __('Extra Products to be add here', 'sm-wcpa'),
					'value'             => $child_products,
					'options'           => sm_wcpa_get_products_as_options(),
				]);
			
			?></div>

        </div><?php
	}

//	add_filter('woocommerce_product_data_tabs', 'extra_products_options_product_tab_content'); // WC 2.5 and below
	add_filter('woocommerce_product_data_panels', 'extra_products_options_product_tab_content'); // WC 2.6 and up
}

if (!function_exists('save_extra_products_option_fields')) {
	
	/**
	 * Save the custom fields.
	 */
	function save_extra_products_option_fields($post_id)
	{
		
		$allow_personal_message = isset($_POST['_has_extra_products']) ? 'yes' : 'no';
		update_post_meta($post_id, '_has_extra_products', $allow_personal_message);
//		var_dump($_POST);exit();
		if (isset($_POST['_sm_wcpa_child_product'])) :
			update_post_meta($post_id, '_sm_wcpa_child_product', $_POST['_sm_wcpa_child_product']);
		endif;
		
	}

//	add_action('woocommerce_process_product_meta_simple', 'save_extra_products_option_fields');
	add_action('woocommerce_process_product_meta_variable', 'save_extra_products_option_fields');
	
}

if (!function_exists('sm_wcpa_get_child_product_price')) {
	function sm_wcpa_get_child_product_price($product_id = null)
	{
		$product = wc_get_product($product_id);
		return $product->get_price();
	}
}

if (!function_exists('sm_wcpa_add_child_product_price_to_parent')) {
	function sm_wcpa_add_child_product_price_to_parent()
	{
		global $product;
		if (!$product->get_type('variable') || !$product->get_meta('_has_extra_products')) return;
//		add_action('woocommerce_before_add_to_cart_button', 'product_option_custom_field', 30 );
		$parent_price = (float)$product->get_price();
		$child_products = $product->get_meta('_sm_wcpa_child_product');
//		var_dump($child_products);exit();
		$child_products = (array)array_values($child_products);
		echo '<ul class="nav nav-tabs value">';
		foreach ($child_products as $child_product) {
			$child_price = (float)sm_wcpa_get_child_product_price($child_product);
			$get_product = wc_get_product($child_product);
			//here i placed custom html code refer to my custom template. this will be updated to wc_get_template() function asap.
//			$child_price_html = strip_tags(wc_price(wc_get_price_to_display($product, array('price' => $child_price))));
			echo '<li class="nav-item">' .
				'<input type="checkbox" class="form-check-input" name="child_products[' . $child_product . ']" id="child_product-' . $child_product . '" value="1">' .
				'<label class="form-check-label bg-transparent form-check mb-0 nav-link w-100" for="child_product-' . $child_product . '">' .
				'<div class="media_prod">' .
				'<span>' . get_the_post_thumbnail($child_product, [80, 80]) . '</span>' .
				'<div class="des_prod">' .
				'<h6><strong>' . $get_product->get_title() . '</strong></h6>' .
				'<strong>' . $get_product->get_price_html() . '</strong>' .
				'</div>' .
				'</div>' .
				'<input type="hidden" name="child_price[' . $child_product . ']" value="' . $child_price . '">' .
				'<input type="hidden" name="parent_price" value="' . $parent_price . '"></li>';
			
		}
		echo '</ul>';
//		add_action('woocommerce_single_product_summary','add_repair_price_option_to_single_product', 2 );
	}
	
	add_action('sm_wcpa_extra_products', 'sm_wcpa_add_child_product_price_to_parent', 30);
	
}

add_filter('woocommerce_add_cart_item_data', 'add_custom_product_data', 10, 3);
function add_custom_product_data($cart_item_data, $product_id, $variation_id)
{
//	echo '<pre data-myself="true">';
//    var_dump($cart_item_data);
//	var_dump($_POST);
	if (isset($_POST['child_products']) && !empty($_POST['child_products'])) {
		$child_prices = 0;
		foreach ($_POST['child_price'] as $child_price) {
			$child_prices = $child_prices + $child_price;
		}
		foreach ($_POST['child_products'] as $index => $child_product) {
			$cart_item_data['new_price'] = (float)($_POST['parent_price'] + $child_prices);
			$cart_item_data['child_price'][$index] = (float)$_POST['child_price'][$index];
			$cart_item_data['parent_price'] = (float)$_POST['parent_price'];
			$cart_item_data['unique_key'][$index] = md5(microtime() . rand());
		}
	}
	
	return apply_filters('wc_epo_add_cart_item_data', $cart_item_data);
}

add_action('woocommerce_before_calculate_totals', 'extra_price_add_custom_price', 20, 1);

function extra_price_add_custom_price($cart)
{
	if (is_admin() && !defined('DOING_AJAX'))
		return;
	
	if (did_action('woocommerce_before_calculate_totals') >= 2)
		return;
	
	foreach ($cart->get_cart() as $cart_item) {
		if (isset($cart_item['new_price']))
			$cart_item['data']->set_price((float)$cart_item['new_price']);
	}
}

add_filter('woocommerce_get_item_data', 'display_custom_item_data', 10, 2);

function display_custom_item_data($cart_item_data, $cart_item)
{
	if (isset($cart_item['repair_price'])) {
		$cart_item_data[] = array(
			'name'  => __("Repair option", "woocommerce"),
			'value' => strip_tags('+ ' . wc_price(wc_get_price_to_display($cart_item['data'], array('price' => $cart_item['child_price']))))
		);
	}
	
	return $cart_item_data;
}
