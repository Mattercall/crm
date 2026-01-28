<?php

namespace PixelYourSite\TikTok\Helpers;

use PixelYourSite;
use function PixelYourSite\wooGetOrderIdFromRequest;
use function PixelYourSite\wooIsRequestContainOrderId;
use function PixelYourSite\isWPMLActive;
use function PixelYourSite\getWPMLProductId;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * @param string $product_id
 *
 * @return array
 */
function getTikTokWooProductContentId( $product_id ) {
    if(isWPMLActive()) {
        $product_id = getWPMLProductId($product_id, PixelYourSite\Tiktok());
    }
    if ( PixelYourSite\Tiktok()->getOption( 'woo_content_id' ) == 'product_sku' ) {
        $product = wc_get_product( $product_id );
        if ( $product && $product->is_type( 'variation' ) ) {
            $content_id = $product->get_sku();
            if ( empty( $content_id ) ) {
                $parent_id = $product->get_parent_id();
                $parent_product = wc_get_product( $parent_id );

                if ( $parent_product ) {
                    $content_id = $parent_product->get_sku();
                }

                if ( empty( $content_id ) ) {
                    $content_id = $product_id;
                }
            }
        } elseif($product) {
            $content_id = $product->get_sku();
            if ( empty( $content_id ) ) {
                $content_id = $product_id;
            }
        } else {
            $content_id = $product_id;
        }
    } else {
        $content_id = $product_id;
    }

    $prefix = PixelYourSite\Tiktok()->getOption( 'woo_content_id_prefix' );
    $suffix = PixelYourSite\Tiktok()->getOption( 'woo_content_id_suffix' );

    $value = '';
    if(!empty($content_id)){
        $value = trim( $prefix ) . $content_id . trim( $suffix );
    }

    return $value;

}

function getTikTokWooProductDataId( $item ) {

    if($item['type'] == 'variation') {
        if(PixelYourSite\Tiktok()->getOption( 'woo_variable_as_simple' ) ) {
            $product_id = $item['parent_id'];
        } else {
            $product_id = $item['product_id'];
        }
    } else {
        $product_id = $item['product_id'];
    }

    return $product_id;

}

function getTikTokWooCartProductId( $product ) {

    $product_id = $product['product_id'];

    if ( PixelYourSite\Tiktok()->getOption( 'woo_variable_as_simple' )
        && isset( $product['parent_id'] ) && $product['parent_id'] !== 0 ) {
        $product_id = $product['parent_id'];
    }

    return $product_id;
}

/**@deprecated use getFacebookWooCartProductId
 * @param $item
 * @return int|mixed
 */
function getTikTokWooCartItemId( $item ) {

    if ( ! PixelYourSite\Tiktok()->getOption( 'woo_variable_as_simple' ) && isset( $item['variation_id'] ) && $item['variation_id'] !== 0 ) {
        $product_id = $item['variation_id'];
    } else {
        $product_id = $item['product_id'];
    }


    return $product_id;
}

function get_tt_plugin_retailer_id( $woo_product ) {
    if(!$woo_product) return "";
    $woo_id = $woo_product->get_id();

    // Call $woo_product->get_id() instead of ->id to account for Variable
    // products, which have their own variant_ids.
    return $woo_product->get_sku() ? $woo_product->get_sku() . '_' .
        $woo_id : 'wc_post_id_'. $woo_id;
}



function getWooSingleAddToCartParams( $product_id, $qty = 1, $is_external = false ) {

	$params = array(
		'post_type'        => 'product',
		'product_id'       => getTikTokWooProductContentId($product_id),
		'quantity' => $qty,
	);

	//@todo: track "product_variant_id"

	// content_name, category_name, tags
	$params['tags'] = implode( ', ', PixelYourSite\getObjectTerms( 'product_tag', $product_id ) );
	$params = array_merge( $params, getWooCustomAudiencesOptimizationParams( $product_id ) );

	// set option names
	$value_enabled_option = $is_external ? 'woo_affiliate_value_enabled' : 'woo_add_to_cart_value_enabled';
	$value_option_option  = $is_external ? 'woo_affiliate_value_option' : 'woo_add_to_cart_value_option';
	$value_global_option  = $is_external ? 'woo_affiliate_value_global' : 'woo_add_to_cart_value_global';
	$value_percent_option = $is_external ? '' : 'woo_add_to_cart_value_percent';

	// currency, value
	if ( PixelYourSite\PYS()->getOption( $value_enabled_option ) ) {

		$value_option   = PixelYourSite\PYS()->getOption( $value_option_option );
		$global_value   = PixelYourSite\PYS()->getOption( $value_global_option, 0 );
		$percents_value = PixelYourSite\PYS()->getOption( $value_percent_option, 100 );

		$params['value']    = PixelYourSite\getWooEventValue( $value_option, $global_value, $percents_value,$product_id,$qty );
		$params['currency'] = get_woocommerce_currency();

	}

	$params['price'] = PixelYourSite\getWooProductPriceToDisplay( $product_id );

	if ( $is_external ) {
		$params['action'] = 'affiliate button click';
	}

	return $params;

}

function getWooCustomAudiencesOptimizationParams( $post_id ) {

	$post = get_post( $post_id );

	$params = array(
		'content_name'  => '',
		'category_name' => '',
	);

	if ( ! $post ) {
		return $params;
	}

	if ( $post->post_type == 'product_variation' ) {
		$post_id = $post->post_parent; // get terms from parent
	}

	$params['content_name']  = $post->post_title;
	$params['category_name'] = implode( ', ', PixelYourSite\getObjectTerms( 'product_cat', $post_id ) );

	return $params;

}

function getTikTokWooVariableToSimpleProductId ( $product ) {
	if ( PixelYourSite\Tiktok()->getOption( 'woo_variable_as_simple' ) && $product->get_type() == 'variation' ) {
		$product_id = $product->get_parent_id();
	} else {
		$product_id = $product->get_id();
	}

	return $product_id;
}