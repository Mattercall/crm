<?php

namespace PixelYourSite;

use URL;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function isEddRecurringActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'edd-recurring/edd-recurring.php' );
}
/**
 * Check if WPML plugin installed and activated.
 *
 * @return bool
 */
function isWPMLActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' );
}
/**
 * Check if Pixel Cost of goods plugin installed and activated.
 *
 * @return bool
 */
function isPixelCogActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'pixel-cost-of-goods/pixel-cost-of-goods.php' );

}

function isPinterestActive( $checkCompatibility = true ) {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active = is_plugin_active( 'pixelyoursite-pinterest/pixelyoursite-pinterest.php' );

    if ( $checkCompatibility ) {
        return $active && ! isPinterestVersionIncompatible()
            && function_exists( 'PixelYourSite\Pinterest' )
            && Pinterest() instanceof Plugin; // false for dummy
    } else {
        return $active;
    }

}

function isPinterestVersionIncompatible() {

    if ( ! function_exists( 'get_plugin_data' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-pinterest/pixelyoursite-pinterest.php', false, false );

    return ! version_compare( $data['Version'], PYS_PINTEREST_MIN_VERSION, '>=' );

}

function isSuperPackActive( $checkCompatibility = true  ) {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active = is_plugin_active( 'pixelyoursite-super-pack/pixelyoursite-super-pack.php' );

    if ( $checkCompatibility ) {
        return $active && function_exists( 'PixelYourSite\SuperPack' ) && ! isSuperPackVersionIncompatible();
    } else {
        return $active;
    }

}

function isSuperPackVersionIncompatible() {

    if ( ! function_exists( 'get_plugin_data' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-super-pack/pixelyoursite-super-pack.php', false, false );

    return ! version_compare( $data['Version'], PYS_SUPER_PACK_MIN_VERSION, '>=' );

}

function isBingActive( $checkCompatibility = true ) {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $active = is_plugin_active( 'pixelyoursite-bing/pixelyoursite-bing.php' );

    if ( $checkCompatibility ) {
        return $active && ! isBingVersionIncompatible()
            && function_exists( 'PixelYourSite\Bing' )
            && Bing() instanceof Plugin; // false for dummy
    } else {
        return $active;
    }

}

function isBingVersionIncompatible() {

    if ( ! function_exists( 'get_plugin_data' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    $data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-bing/pixelyoursite-bing.php', false, false );

    return ! version_compare( $data['Version'], PYS_BING_MIN_VERSION, '>=' );

}

/**
 * Check if Reddit plugin installed and activated.
 * @param bool $checkCompatibility
 * @return bool
 */
function isRedditActive( bool $checkCompatibility = true ): bool {

	if ( !function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	$active = is_plugin_active( 'pixelyoursite-reddit/pixelyoursite-reddit.php' );

	if ( $checkCompatibility ) {
		return $active && !isRedditVersionIncompatible()
		       && function_exists( 'PixelYourSite\Reddit' )
		       && Reddit() instanceof Plugin; // false for dummy
	} else {
		return $active;
	}
}

/**
 * Check if Reddit plugin version is compatible.
 * @return bool
 */
function isRedditVersionIncompatible(): bool {

	if ( !function_exists( 'get_plugin_data' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	$data = get_plugin_data( WP_PLUGIN_DIR . '/pixelyoursite-reddit/pixelyoursite-reddit.php', false, false );

	return !version_compare( $data[ 'Version' ], PYS_REDDIT_MIN_VERSION, '>=' );
}

/**
 * Check if WooCommerce plugin installed and activated.
 *
 * @return bool
 */
function isWooCommerceActive() {
    return class_exists( 'woocommerce' );
}

/**
 * Check if Easy Digital Downloads plugin installed and activated.
 *
 * @return bool
 */
function isEddActive() {
    return function_exists( 'EDD' );
}

/**
 * Check if Product Catalog Feed Pro plugin installed and activated.
 *
 * @return bool
 */
function isProductCatalogFeedProActive() {
    return class_exists( 'wpwoof_product_catalog' );
}

/**
 * Check if EDD Products Feed Pro plugin installed and activated.
 *
 * @return bool
 */
function isEddProductsFeedProActive() {
    return class_exists( 'Wpeddpcf_Product_Catalog' );
}

function isBoostActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'boost/boost.php' );

}

/**
 * Check if Smart OpenGraph plugin installed and activated.
 *
 * @return bool
 */
function isSmartOpenGraphActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'smart-opengraph/catalog-plugin.php' );

}

function isVisualComposerActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'js_composer/js_composer.php' );

}

function isMagicRowActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'magic-row/magic-row.php' );

}


function isContactForm7Active() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );

}
function isWPFormsActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'wpforms-lite/wpforms.php' );

}
function isForminatorActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'forminator/forminator.php' );

}
function isFluentFormActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'fluentform/fluentform.php' );

}
function isFormidableActive() {

    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }

    return is_plugin_active( 'formidable/formidable.php' );

}

function isWSFormsActive() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	}

	return is_plugin_active( 'ws-form-pro/ws-form.php' ) || is_plugin_active( 'ws-form/ws-form.php' );
}

function isPhotoCartActive() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    return is_plugin_active( 'sunshine-photo-cart/sunshine-photo-cart.php' );
}
/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 * Non-scalar values are ignored.
 *
 * @param string|array $var
 *
 * @return string|array
 */
function deepSanitizeTextField( $var ) {

    if ( is_array( $var ) ) {
        return array_map( 'deepSanitizeTextField', $var );
    } else {
        return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
    }

}

function getAvailableUserRoles() {

    $wp_roles   = new \WP_Roles();
    $user_roles = array();

    foreach ( $wp_roles->get_names() as $slug => $name ) {
        $user_roles[ $slug ] = $name;
    }

    return $user_roles;

}

/**
 * @param \WC_Product $product
 * @return array
 */
function getAvailableProductCog($product) {


    $cost_type = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_type', true );
    $product_cost = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_val', true );

    if(!$product_cost && $product->is_type("variation")) {
        $cost_type = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
    }


    if ($product_cost) {
        $cog = array(
            'type' => $cost_type,
            'val' => $product_cost
        );
    } else {
        $cog_term_val = get_product_cost_by_cat( $product->get_id() );
        if ($cog_term_val) {
            $cog = array(
                'type' => get_product_type_by_cat( $product->get_id() ),
                'val' => $cog_term_val
            );
        } else {
            $cog = array(
                'type' => get_option( '_pixel_cost_of_goods_cost_type'),
                'val' => get_option( '_pixel_cost_of_goods_cost_val')
            );
        }
    }

    return $cog;

}

function getAvailableProductCogOrder($args) {

    $order_total = 0.0;
    $cost = 0;
    $notice = '';
    $custom_total = 0;
    $cat_isset = 0;
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating')  == 'no';

    foreach ($args['products'] as $productData) {
        $order_total += (float)$productData['total'];
        if(!$isWithoutTax) {
            $order_total += (float)$productData['total_tax'];
        }

        $product_id = $productData['product_id'];
        $productObject = wc_get_product($product_id);
        if(!$productObject) continue;

        $cost_type = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_val', true );

        if(!$product_cost && $productObject->is_type("variation")) {
            $cost_type = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        $args = array( 'qty'   => 1, 'price' => $productObject->get_price());
        $qlt = $productData['quantity'];

        if($isWithoutTax) {
            $price = wc_get_price_excluding_tax($productObject, $args);
        } else {
            $price = wc_get_price_including_tax($productObject,$args);
        }

        if ($product_cost) {
            $cost = ($cost_type == 'percent') ? $cost + ($price * ($product_cost / 100) * $qlt) : $cost + ($product_cost * $qlt);
            $custom_total = $custom_total + ($price * $qlt);
        } else {
            $product_cost = get_product_cost_by_cat( $product_id );
            $cost_type = get_product_type_by_cat( $product_id );
            if ($product_cost) {
                $cost = ($cost_type == 'percent') ? $cost + ($price * ($product_cost / 100) * $qlt) : $cost + ($product_cost * $qlt);
                $custom_total = $custom_total + ($price * $qlt);
                $notice = "Category Cost of Goods was used for some products.";
                $cat_isset = 1;
            } else {
                $product_cost = get_option( '_pixel_cost_of_goods_cost_val');
                $cost_type = get_option( '_pixel_cost_of_goods_cost_type' );
                if ($product_cost) {
                    $cost = ($cost_type == 'percent') ? (float) $cost + ((float) $price * ((float) $product_cost / 100) * $qlt) : (float) $cost + ((float) $product_cost * $qlt);
                    $custom_total = $custom_total + ($price * $qlt);
                    if ($cat_isset == 1) {
                        $notice = "Global and Category Cost of Goods was used for some products.";
                    } else {
                        $notice = "Global Cost of Goods was used for some products.";
                    }
                } else {
                    $notice = "Some products don't have Cost of Goods.";
                }
            }
        }
    }
    return $order_total - $cost;
}
function getProductCogOrder($args) {
    if(!isPixelCogActive()) {return ['cost'=> 0, 'profit'=>0];}
    $order_total = 0.0;
    $cost = 0;
    $custom_total = 0;
    $cat_isset = 0;
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating')  == 'no';

    foreach ($args['products'] as $productData) {
        $order_total += (float)$productData['total'];
        if(!$isWithoutTax) {
            $order_total += (float)$productData['total_tax'];
        }

        $product_id = $productData['product_id'];
        $productObject = wc_get_product($product_id);
        if(!$productObject) continue;

        $cost_type = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $productObject->get_id(), '_pixel_cost_of_goods_cost_val', true );

        if(!$product_cost && $productObject->is_type("variation")) {
            $cost_type = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $productObject->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        $args = array( 'qty'   => 1, 'price' => $productObject->get_price());
        $qlt = $productData['quantity'];

        if($isWithoutTax) {
            $price = wc_get_price_excluding_tax($productObject, $args);
        } else {
            $price = wc_get_price_including_tax($productObject,$args);
        }

        if ($product_cost) {
            $cost = ($cost_type == 'percent') ? $cost + ($price * ($product_cost / 100) * $qlt) : $cost + ($product_cost * $qlt);
            $custom_total = $custom_total + ($price * $qlt);
        } else {
            $product_cost = get_product_cost_by_cat( $product_id );
            $cost_type = get_product_type_by_cat( $product_id );
            if ($product_cost) {
                $cost = ($cost_type == 'percent') ? $cost + ($price * ($product_cost / 100) * $qlt) : $cost + ($product_cost * $qlt);
                $custom_total = $custom_total + ($price * $qlt);
                $cat_isset = 1;
            } else {
                $product_cost = get_option( '_pixel_cost_of_goods_cost_val');
                $cost_type = get_option( '_pixel_cost_of_goods_cost_type' );
                if ($product_cost) {
                    $cost = ($cost_type == 'percent') ? (float) $cost + ((float) $price * ((float) $product_cost / 100) * $qlt) : (float) $cost + ((float) $product_cost * $qlt);
                    $custom_total = $custom_total + ($price * $qlt);
                }
            }
        }
    }
    return ['cost'=> $cost, 'profit'=>$custom_total - $cost];
}

function getAvailableProductCogCart() {
    $cart_total = 0.0;
    $cost = 0;
    $notice = '';
    $custom_total = 0;
    $cat_isset = 0;
    $isWithoutTax = get_option( '_pixel_cog_tax_calculating')  == 'no';

    $shipping = WC()->cart->get_shipping_total();
    $cart_total = WC()->cart->get_total('edit') - $shipping;

    if($isWithoutTax) {
        $cart_total -=  WC()->cart->get_total_tax();
    } else {
        $cart_total -= WC()->cart->get_shipping_tax();
    }

    foreach ( WC()->cart->cart_contents as $cart_item_key => $item ) {
        $product_id = ( isset( $item['variation_id'] ) && 0 != $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );

        $product = wc_get_product($product_id);

        if(!$product) continue;

        $cost_type = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_type', true );
        $product_cost = get_post_meta( $product->get_id(), '_pixel_cost_of_goods_cost_val', true );

        if(!$product_cost && $product->is_type("variation")) {
            $cost_type = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_type', true );
            $product_cost = get_post_meta( $product->get_parent_id(), '_pixel_cost_of_goods_cost_val', true );
        }

        $args = array( 'qty'   => 1, 'price' => $product->get_price());
        if($isWithoutTax) {
            $price = wc_get_price_excluding_tax($product, $args);
        } else {
            $price = wc_get_price_including_tax($product,$args);
        }
        $qlt = $item['quantity'];


        if ($product_cost) {
            $cost = ($cost_type == 'percent') ? $cost + ($price * ($product_cost / 100) * $qlt) : $cost + ($product_cost * $qlt);
            $custom_total = $custom_total + ($price * $qlt);
        } else {
            $product_cost = get_product_cost_by_cat( $product_id );
            $cost_type = get_product_type_by_cat( $product_id );
            if ($product_cost) {
                $cost = ($cost_type == 'percent') ? $cost + ($price * ($product_cost / 100) * $qlt) : $cost + ($product_cost * $qlt);
                $custom_total = $custom_total + ($price * $qlt);
                $notice = "Category Cost of Goods was used for some products.";
                $cat_isset = 1;
            } else {
                $product_cost = get_option( '_pixel_cost_of_goods_cost_val');
                $cost_type = get_option( '_pixel_cost_of_goods_cost_type' );
                if ($product_cost) {
                    $cost = ($cost_type == 'percent') ? $cost + ((float) $price * ((float) $product_cost / 100) * $qlt) : (float) $cost + ((float) $product_cost * $qlt);
                    $custom_total = $custom_total + ($price * $qlt);
                    if ($cat_isset == 1) {
                        $notice = "Global and Category Cost of Goods was used for some products.";
                    } else {
                        $notice = "Global Cost of Goods was used for some products.";
                    }
                } else {
                    $notice = "Some products don't have Cost of Goods.";
                }
            }
        }
    }

    return $cart_total - $cost;

}

/**
 * get_product_type_by_cat.
 *
 * @version 1.0.0
 * @since   1.0.0
 */
function get_product_type_by_cat( $product_id ) {
    $term_list = wp_get_post_terms($product_id,'product_cat',array('fields'=>'ids'));
    $cost = array();
    foreach ($term_list as $term_id) {
        $cost[$term_id] = array(
            get_term_meta( $term_id, '_pixel_cost_of_goods_cost_val', true ),
            get_term_meta( $term_id, '_pixel_cost_of_goods_cost_type', true )
        );
    }
    if ( empty( $cost ) ) {
        return '';
    } else {
        asort( $cost );
        $max = end( $cost );
        return $max[1];
    }
}

/**
 * get_product_cost_by_cat.
 *
 * @version 1.0.0
 * @since   1.0.0
 */
function get_product_cost_by_cat( $product_id ) {
    $term_list = wp_get_post_terms($product_id,'product_cat',array('fields'=>'ids'));
    $cost = array();
    foreach ($term_list as $term_id) {
        $cost[$term_id] = get_term_meta( $term_id, '_pixel_cost_of_goods_cost_val', true );
    }
    if ( empty( $cost ) ) {
        return '';
    } else {
        asort( $cost );
        $max = end( $cost );
        return $max;
    }
}

function isDisabledForCurrentRole() {

    $user = wp_get_current_user();

    if(isDisabledForUserRole($user)) {
        add_action( 'wp_head', function() {
            echo "<script type='application/javascript' id='pys-config-warning-user-role'>console.warn('PixelYourSite is disabled for current user role.');</script>\r\n";
        } );

        return true;
    }

    return false;

}

/**
 * @param \WP_User $user
 * @return bool
 */

function isDisabledForUserRole($user) {
    if($user instanceof \WP_User) {
        $disabled_for = PYS()->getOption( 'do_not_track_user_roles' );

        foreach ( (array) $user->roles as $role ) {

            if ( in_array( $role, $disabled_for ) ) {
                return true;
            }
        }
    }

    return false;
}

function getPageTitle()
{
    global $post;

    if ($post instanceof \WP_Post) {
        return $post->post_title;
    }

    if (is_singular('post')) {
        return $post->post_title;
    }
    if(is_singular('page') || is_home()) {
        return is_home() == true ? get_bloginfo('name') : $post->post_title;
    }

    if (isWooCommerceActive() && is_shop()) {
        $page_id = (int)wc_get_page_id('shop');
        return get_the_title($page_id);
    }

    if (is_category()) {
        $cat = get_query_var('cat');
        $term = get_category($cat);
        if ($term) {
            return $term->name;
        }
    }

    if (is_tag()) {
        $slug = get_query_var('tag');
        $term = get_term_by('slug', $slug, 'post_tag');
        if ($term) {
            return $term->name;
        }
    }

    if (is_tax()) {
        $term = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
        if ($term) {
            return $term->name;
        }
    }
    $cpt = get_post_type();

    if ((isWooCommerceActive() && $cpt == 'product') ||
        (isEddActive() && $cpt == 'download')) {
        return $post->post_title;
    }

    return "";
}

function getPageId() {
    global $post;

    if ($post) {
        return $post->ID;
    }

    if (is_category()) {
        $cat = get_query_var('cat');
        $term = get_category($cat);
    }

    if (is_tag()) {
        $slug = get_query_var('tag');
        $term = get_term_by('slug', $slug, 'post_tag');
    }

    if (is_tax()) {
        $term = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
    }

    if ($term) {
        return $term->term_id;
    }
    return 0;
}


function getStandardParams() {
    global $post;
    $cpt = get_post_type();
    $params = array(
        'page_title' => "",
        'post_type' => $cpt,
        'post_id' => "",
        'plugin' => "PixelYourSite"
    );

    if(PYS()->getOption("enable_event_url_param")) {
        $url = getCurrentPageUrl(true);
        $params['event_url'] = $url;
    }
    if(PYS()->getOption("enable_user_role_param")) {
        $params['user_role'] = getUserRoles();
    }



    if(is_singular( 'post' )) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;


    } elseif( is_singular( 'page' ) || is_home()) {
        $params['post_type']    = 'page';
        $params['post_id']      = is_home() ? null : $post->ID;
        $params['page_title']   = is_home() == true ? get_bloginfo( 'name' ) : $post->post_title;

    } elseif (isWooCommerceActive() && is_shop()) {
        $page_id = (int) wc_get_page_id( 'shop' );
        $params['post_type'] = 'page';
        $params['post_id']   = $page_id;
        $params['page_title'] = get_the_title( $page_id );

    } elseif ( is_category() ) {
        $cat  = get_query_var( 'cat' );
        $term = get_category( $cat );
        $params['post_type']    = 'category';
        $params['post_id']      = $cat;
        $params['page_title'] = $term->name;

    } elseif ( is_tag() ) {
        $slug = get_query_var( 'tag' );
        $term = get_term_by( 'slug', $slug, 'post_tag' );
        $params['post_type']    = 'tag';
        if($term) {
            $params['post_id']      = $term->term_id;
            $params['page_title']   = $term->name;
        }


    } elseif (is_tax()) {
        $term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
        $params['post_type'] = get_query_var( 'taxonomy' );
        if ( $term ) {
            $params['post_id']      = $term->term_id;
            $params['page_title'] = $term->name;
        }

    }elseif(is_archive()){
	    $params['page_title'] = get_the_archive_title();
	    $params['post_type'] = 'archive';
	} elseif ((isWooCommerceActive() && $cpt == 'product') ||
        (isEddActive() && $cpt == 'download') ) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;

    } else if ($post instanceof \WP_Post) {
        $params['page_title'] = $post->post_title;
        $params['post_id']   = $post->ID;
    }


    if(!PYS()->getOption("enable_post_type_param")) {
        unset($params['post_type']);
    }
    if(!PYS()->getOption("enable_post_id_param")) {
        unset($params['post_id']);
    }

    if(isWcfStep()) {

        $step = getWcfCurrentStep();
        $flow_id = $step->get_flow_id();

        if(PYS()->getOption('wcf_global_cartflows_parameter_enabled'))
            $params["cartlows"] = "yes";

        if(PYS()->getOption('wcf_global_cartflows_flow_parameter_enabled'))
            $params["cartflows_flow"] = get_the_title($flow_id);

        if(PYS()->getOption('wcf_global_cartflows_step_parameter_enabled'))
            $params["cartflows_step"] = $step->get_step_type();
    }

    return $params;
}

function getWPMLProductId($product_id, $tag) {
    $tagOption = "woo_wpml_unified_id";
    $tagLanguageOption = "woo_wpml_language";

    if (isWPMLActive() && $tag->getOption($tagOption)) {
        $wpml_product_id = !empty($tag->getOption($tagLanguageOption))
            ? apply_filters('wpml_object_id', $product_id, 'product', false, $tag->getOption($tagLanguageOption))
            : apply_filters('wpml_original_element_id', NULL, $product_id);
        if ($wpml_product_id) {
            return $wpml_product_id;
        }
    }
    return $product_id;
}
function getObjectTerms($taxonomy, $post_id) {
    $terms = get_the_terms($post_id, $taxonomy);
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    // Build a map of term_id => term object for quick parent lookups
    $terms_by_id = [];
    foreach ($terms as $term) {
        $terms_by_id[$term->term_id] = $term;
    }

    // Helper function to calculate the "depth" of a term in the hierarchy
    // (how many parent levels it has)
    $get_depth = function($term) use ($terms_by_id) {
        $depth = 0;
        while ($term->parent && isset($terms_by_id[$term->parent])) {
            $term = $terms_by_id[$term->parent];
            $depth++;
        }
        return $depth;
    };

    // Sort terms by their depth so that parent categories come before child categories
    usort($terms, function($a, $b) use ($get_depth) {
        return $get_depth($a) <=> $get_depth($b);
    });

    // Return an array of decoded term names
    $results = [];
    foreach ($terms as $term) {
        $results[] = html_entity_decode($term->name);
    }

    return $results;
}

/**
 * @param string $taxonomy Taxonomy name
 *
 * @return array Array of object term names and id
 */
function getObjectTermsWithId( $taxonomy, $post_id ) {

    $terms   = get_the_terms( $post_id, $taxonomy );
    $results = array();

    if ( is_wp_error( $terms ) || empty ( $terms ) ) {
        return array();
    }

    // decode special chars
    foreach ( $terms as $term ) {
        $results[] = [
            'name' => html_entity_decode( $term->name ),
            'id'   => $term->term_id
        ];
    }

    return $results;

}

/**
 * @param array  $params
 * @param string $key
 *
 * @return mixed
 */
function safeGetArrayValue( $params, $key, $fallback = null ) {
    return isset( $params[ $key ] ) ? $params[ $key ] : $fallback;
}

/**
 * Sanitize event name. Only letters, numbers and underscores allowed.
 *
 * @param string $name
 *
 * @return string
 */
function sanitizeKey( $name ) {

    $name = str_replace( ' ', '_', $name );
    $name = preg_replace( '/[^0-9a-zA-z_]/', '', $name );

    return $name;

}

function removeProtocolFromUrl( $url ) {

    if ( extension_loaded( 'mbstring' ) ) {

        $un = new URL\Normalizer();
        $un->setUrl( $url );
        $url = $un->normalize();

    }

    // remove fragment component
    $url_parts = parse_url( $url );
    if( isset( $url_parts['fragment'] ) ) {
        $url = preg_replace( '/#'. $url_parts['fragment'] . '$/', '', $url );
    }

    // remove scheme and www and current host if any
    $url = str_replace( array( 'http://', 'https://', 'http://www.', 'https://www.', 'www.' ), '', $url );
    $url = trim( $url );
    $url = ltrim( $url, '/' );
    // $url = rtrim( $url, '/' );

    return $url;

}

function getCurrentPageUrl($removeQuery = false) {
	if(!isset($_SERVER['HTTP_HOST']) || !isset($_SERVER['REQUEST_URI'])) {
		return '';
	}
    if($removeQuery && isset($_SERVER['QUERY_STRING']) && isset($_SERVER['HTTP_HOST'])){
        return $_SERVER['HTTP_HOST'] . str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']);
    }
    return  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ;
}

function startsWith( $haystack, $needle ) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos( $haystack, $needle, -strlen( $haystack ) ) !== false;
}

/**
 * Compare single URL or array of URLs with base URL. If base URL is not set, current page URL will be used.
 *
 * @param string|array $url
 * @param string       $base
 * @param string       $rule
 *
 * @return bool
 */
function compareURLs( $url, $base = '', $rule = 'match' ) {

    // use current page url if not set
    if ( empty( $base ) ) {
        $base = getCurrentPageUrl();
    }

    $base = removeProtocolFromUrl( $base );

    if ( is_string( $url ) ) {
        if ( empty( $url ) || '*' === $url ) {
            return true;
        }

        $url = rtrim( $url, '*' );  // for backward capability
        $url = removeProtocolFromUrl( $url );
        if($rule == 'param_match')
        {
            $urlParamsWithQuestionMark = $url;
            parse_str($urlParamsWithQuestionMark, $params);
            foreach ($params as $key => $value)
            {
                if(urlParamsMatch($key, $value))
                {
                    return true;
                }
            }
            return false;
        }
        if($rule == 'param_contains')
        {
            return urlParamsContains($url);
        }
        if ( ! $rule || $rule == 'match' ) {
            return $base == $url;
        }

        if ( $rule == 'contains' ) {

            if ( $base == $url ) {
                return true;
            }

            if(empty($base) || empty($url)) {
                return false;
            }

            if ( strpos( $base, $url ) !== false ) {
                return true;
            }

            return false;

        }

        return false;

    } else {

        // recursively compare each url
        foreach ( $url as $single_url ) {
            if ( $single_url['rule'] === 'any' || compareURLs( $single_url['value'], $base, $single_url['rule'] )) {
                return true;
            }

        }

        return false;

    }

}
function urlParamsMatch($param, $value) {
    return isset($_GET[$param]) && $_GET[$param] === $value;
}

function urlParamsContains($search) {
    foreach ($_GET as $key => $value) {
        if ((is_string($key) && stripos($key, $search) !== false) || (is_string($value) && stripos($value, $search) !== false)) {
            return true;
        }
    }
    return false;
}
/**
 * Add attribute with value to a HTML tag.
 *
 * @param string $attr_name  Attribute name, eg. "class"
 * @param string $attr_value Attribute value
 * @param string $content    HTML content where attribute should be inserted
 * @param bool   $overwrite  Override existing value of attribute or append it
 * @param string $tag        Selector name, eg. "button". Default "a"
 *
 * @return string Modified HTML content
 */
function insertTagAttribute( $attr_name, $attr_value, $content, $overwrite = false, $tag = 'a' ) {

    // do not modify js attributes
    if ( $attr_name == 'on' ) {
        return $content;
    }

    $attr_value = trim( $attr_value );

    try {

        $doc = new \DOMDocument();

        // old libxml does not support options parameter
        if ( defined( 'LIBXML_DOTTED_VERSION' ) && version_compare( LIBXML_DOTTED_VERSION, '2.6.0', '>=' ) &&
            version_compare( phpversion(), '5.4.0', '>=' )
        ) {
            @$doc->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_NOEMPTYTAG );
        } else {
            @$doc->loadHTML( '<?xml encoding="UTF-8">' . $content );
        }

        // select top-level tag if it is not specified in args
        if ( $tag == 'any' ) {

            /** @var \DOMNodeList $node */
            $node = $doc->getElementsByTagName( 'body' );

            if ( $node->length == 0 ) {
                throw new \Exception( 'Empty or wrong tag passed to filter.' );
            }

            $node = $node->item( 0 )->childNodes->item( 0 );

        } else {
            $node = $doc->getElementsByTagName( $tag )->item( 0 );
        }

        if ( is_null( $node ) ) {
            return $content;
        }

        /** @noinspection PhpUndefinedMethodInspection */
        $attribute = $node->getAttribute( $attr_name );

        // add attribute or override old one
        if ( empty( $attribute ) || $overwrite ) {

            /** @noinspection PhpUndefinedMethodInspection */
            $node->setAttribute( $attr_name, $attr_value );

            return str_replace( array( '<?xml encoding="UTF-8">', '<html>', '</html>', '<body>', '</body>' ), null, $doc->saveHTML() );

        }

        // append value to exist attribute
        if ( $overwrite == false ) {
            if(strpos($attribute,$attr_value) !== false) {
                return $content;
            }
            $value = $attribute . ',' . $attr_value;
            /** @noinspection PhpUndefinedMethodInspection */
            $node->setAttribute( $attr_name, $value );

            return str_replace( array( '<?xml encoding="UTF-8">', '<html>', '</html>', '<body>', '</body>' ), null, $doc->saveHTML() );

        }

    } catch ( \Exception $e ) {
        error_log( $e );
    }

    return $content;

}
function getUserRoles() {
    $user = wp_get_current_user();

    if ( $user->ID !== 0 ) {
        $user_roles = implode( ',', $user->roles );
    } else {
        $user_roles = 'guest';
    }
    return $user_roles;
}

/**
 * Get UTM parameters
 *
 * @param bool $seed_undefined Whether to set undefined for missing values
 * @param bool $use_last If true, use last_pys_* cookies, otherwise use pys_* cookies
 * @return array
 */
function getUtms( $seed_undefined = false, $use_last = false ) {
    $utm = array();
    $utmTerms = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    $cookie_prefix = $use_last ? 'last_pys_' : 'pys_';

    foreach ( $utmTerms as $utmTerm ) {
        if ( isset( $_GET[ $utmTerm ] ) ) {
            $utm[ $utmTerm ] = filterEmails( $_GET[ $utmTerm ] );
        } elseif ( isset( $_COOKIE[ $cookie_prefix . $utmTerm ] ) ) {
            $utm[ $utmTerm ] = filterEmails( $_COOKIE[ $cookie_prefix . $utmTerm ] );
        } elseif ( isset( $_SESSION['TrafficUtms'] ) && isset( $_SESSION['TrafficUtms'][ $utmTerm ] ) ) {
            $utm[ $utmTerm ] = filterEmails( $_SESSION['TrafficUtms'][ $utmTerm ] );
        } elseif ( $seed_undefined ) {
            $utm[ $utmTerm ] = 'undefined';
        }
    }

    return $utm;
}

/**
 * Get UTM ID parameters
 *
 * @param bool $seed_undefined Whether to set undefined for missing values
 * @param bool $use_last If true, use last_pys_* cookies, otherwise use pys_* cookies
 * @return array
 */
function getUtmsId( $seed_undefined = false, $use_last = false ) {
    $utm = array();
    $utmTerms = ['fbadid', 'gadid', 'padid', 'bingid'];
    $cookie_prefix = $use_last ? 'last_pys_' : 'pys_';

    foreach ( $utmTerms as $utmTerm ) {
        if ( isset( $_GET[ $utmTerm ] ) ) {
            $utm[ $utmTerm ] = filterEmails( $_GET[ $utmTerm ] );
        } elseif ( isset( $_COOKIE[ $cookie_prefix . $utmTerm ] ) ) {
            $utm[ $utmTerm ] = filterEmails( $_COOKIE[ $cookie_prefix . $utmTerm ] );
        } elseif ( isset( $_SESSION['TrafficUtmsId'] ) && isset( $_SESSION['TrafficUtmsId'][ $utmTerm ] ) ) {
            $utm[ $utmTerm ] = filterEmails( $_SESSION['TrafficUtmsId'][ $utmTerm ] );
        } elseif ( $seed_undefined ) {
            $utm[ $utmTerm ] = 'undefined';
        }
    }

    return $utm;
}

/**
 * Parse UTM string in format "key:value|key:value" into array
 *
 * @param string $utmString UTM string to parse
 * @return array Parsed UTM parameters
 */
function parseUtmString( $utmString ) {
    if ( empty( $utmString ) ) {
        return array();
    }

    $result = array();
    $utmPairs = explode( '|', $utmString );

    foreach ( $utmPairs as $pair ) {
        $parts = explode( ':', $pair, 2 );
        $key = $parts[0];
        $value = isset( $parts[1] ) && $parts[1] !== 'undefined' ? $parts[1] : '';

        if ( ! empty( $key ) && ! empty( $value ) ) {
            $result[ $key ] = $value;
        }
    }

    return $result;
}

function getBrowserTime(){
    $dateTime = array();
    $date = new \DateTime();

    $days = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $months = array('January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December');
    $hours = array('00-01', '01-02', '02-03', '03-04', '04-05', '05-06', '06-07', '07-08',
        '08-09', '09-10', '10-11', '11-12', '12-13', '13-14', '14-15', '15-16', '16-17',
        '17-18', '18-19', '19-20', '20-21', '21-22', '22-23', '23-24');

    $dateTime[] = $hours[$date->format('G')];
    $dateTime[] = $days[$date->format('w')];
    $dateTime[] = $months[$date->format('n') - 1];

    $dateTimeString = implode("|", $dateTime);
    return $dateTimeString;
}

/**
 * Parse browser time string into array with event_time, event_day, event_month
 *
 * @param string $browserTimeString Browser time string in format "hour|day|month" (e.g., "08-09|Monday|January")
 * @return array Parsed time data with keys: event_time, event_day, event_month
 */
function getUserTime( $browserTimeString ) {
    if ( empty( $browserTimeString ) ) {
        return array();
    }

    $parts = explode( '|', $browserTimeString );
    $result = array();

    if ( isset( $parts[0] ) && ! empty( $parts[0] ) && PYS()->getOption( 'enable_event_time_param' ) ) {
        $result['event_hour'] = $parts[0];
    }
    if ( isset( $parts[1] ) && ! empty( $parts[1] ) && PYS()->getOption( 'enable_event_day_param' ) ) {
        $result['event_day'] = $parts[1];
    }
    if ( isset( $parts[2] ) && ! empty( $parts[2] ) && PYS()->getOption( 'enable_event_month_param' ) ) {
        $result['event_month'] = $parts[2];
    }

    return $result;
}

function filterEmails($value) {
    return validateEmail($value) ? "undefined" : $value;
}

function validateEmail($email){
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function getTrafficSource ($use_last = false) {
    $referrer = "";
    $source = "";
    $cookie_prefix = $use_last ? 'last_pys' : 'pys';
    try {
        if ( function_exists( 'wc_get_raw_referer' ) ) {
            $referrer = wc_get_raw_referer();
        } else if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = $_SERVER['HTTP_REFERER'];
        }

        $direct = empty( $referrer ) ? false : true;
        $internal = $direct ? false : (substr($referrer, 0, strlen(site_url())) === site_url());
        $external = !( $direct || $internal );
        $cookie = !isset($_COOKIE[$cookie_prefix .'TrafficSource']) || $_COOKIE[$cookie_prefix .'TrafficSource'] === 'direct'  ? false : $_COOKIE[$cookie_prefix .'TrafficSource'];
        $session = !isset($_SESSION['TrafficSource']) || $_SESSION['TrafficSource'] === 'direct' ? false : $_SESSION['TrafficSource'];
        if (!$external) {
            $source = $cookie || $session ? $session ?? $cookie: 'direct';

        } else {
            $source = ( $cookie && $cookie === $referrer ) || ( $session && $session === $referrer ) ? $session ?? $cookie : $referrer;
        }

        if ($source !== 'direct') {

            $parse = parse_url($source);
            if(isset($parse['host'])) {
                return $parse['host'];// leave only domain (Issue #70)
            } elseif ($source == $cookie || $source == $session){
                return $source;
            } else {
                return defined( 'REST_REQUEST' ) && REST_REQUEST ? 'REST API' : 'direct';
            }
        } else {
            return defined( 'REST_REQUEST' ) && REST_REQUEST ? 'REST API' : $source;
        }
    } catch (\Exception $e) {
        return "direct";
    }
}

function sanitizeParams( $params ) {

    $sanitized = array();

    foreach ( $params as $key => $value ) {

        // skip empty (but not zero)
        if ( ! isset( $value )  ||
            (is_string($value) && $value == "") ||
            (is_array($value) && count($value) == 0)
        ) {
            continue;
        }

        $key = sanitizeKey( $key );

        if ( is_array( $value ) ) {
            $sanitized[ $key ] = sanitizeParams( $value );
        } elseif ( is_bool( $value ) ) {
            $sanitized[ $key ] = (bool) $value;
        } elseif (is_numeric($value)) {
            $sanitized[ $key ] = $value;
        } else {
            $sanitized[ $key ] = stripslashes(html_entity_decode( $value ));
        }



    }

    return $sanitized;

}

function formatPriceTrimZeros($number, $decimals = 2) {
    $formatted = number_format($number, $decimals, '.', '');
    // Remove extra zeros on the right and a period if necessary
    return rtrim(rtrim($formatted, '0'), '.');
}

/**
 * Checks if specified event enabled at least for one configured pixel
 *
 * @param string $eventName
 *
 * @return bool
 */
function isEventEnabled( $eventName ) {

    foreach ( PYS()->getRegisteredPixels() as $pixel ) {
        /** @var Pixel|Settings $pixel */

        if ( $pixel->configured() && $pixel->getOption( $eventName ) ) {
            return true;
        }

    }

    return false;

}

function pys_round( $val, $precision = 2, $mode = PHP_ROUND_HALF_UP )  {
    if ( ! is_numeric( $val ) ) {
        $val = floatval( $val );
    }
    return round( $val, $precision, $mode );
}

/**
 * Currency symbols
 *
 * @return array
 * */

function getPysCurrencySymbols() {
    return array(
        'AED' => '&#x62f;.&#x625;',
        'AFN' => '&#x60b;',
        'ALL' => 'L',
        'AMD' => 'AMD',
        'ANG' => '&fnof;',
        'AOA' => 'Kz',
        'ARS' => '&#36;',
        'AUD' => '&#36;',
        'AWG' => 'Afl.',
        'AZN' => 'AZN',
        'BAM' => 'KM',
        'BBD' => '&#36;',
        'BDT' => '&#2547;&nbsp;',
        'BGN' => '&#1083;&#1074;.',
        'BHD' => '.&#x62f;.&#x628;',
        'BIF' => 'Fr',
        'BMD' => '&#36;',
        'BND' => '&#36;',
        'BOB' => 'Bs.',
        'BRL' => '&#82;&#36;',
        'BSD' => '&#36;',
        'BTC' => '&#3647;',
        'BTN' => 'Nu.',
        'BWP' => 'P',
        'BYR' => 'Br',
        'BYN' => 'Br',
        'BZD' => '&#36;',
        'CAD' => '&#36;',
        'CDF' => 'Fr',
        'CHF' => '&#67;&#72;&#70;',
        'CLP' => '&#36;',
        'CNY' => '&yen;',
        'COP' => '&#36;',
        'CRC' => '&#x20a1;',
        'CUC' => '&#36;',
        'CUP' => '&#36;',
        'CVE' => '&#36;',
        'CZK' => '&#75;&#269;',
        'DJF' => 'Fr',
        'DKK' => 'DKK',
        'DOP' => 'RD&#36;',
        'DZD' => '&#x62f;.&#x62c;',
        'EGP' => 'EGP',
        'ERN' => 'Nfk',
        'ETB' => 'Br',
        'EUR' => '&euro;',
        'FJD' => '&#36;',
        'FKP' => '&pound;',
        'GBP' => '&pound;',
        'GEL' => '&#x20be;',
        'GGP' => '&pound;',
        'GHS' => '&#x20b5;',
        'GIP' => '&pound;',
        'GMD' => 'D',
        'GNF' => 'Fr',
        'GTQ' => 'Q',
        'GYD' => '&#36;',
        'HKD' => '&#36;',
        'HNL' => 'L',
        'HRK' => 'kn',
        'HTG' => 'G',
        'HUF' => '&#70;&#116;',
        'IDR' => 'Rp',
        'ILS' => '&#8362;',
        'IMP' => '&pound;',
        'INR' => '&#8377;',
        'IQD' => '&#x639;.&#x62f;',
        'IRR' => '&#xfdfc;',
        'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
        'ISK' => 'kr.',
        'JEP' => '&pound;',
        'JMD' => '&#36;',
        'JOD' => '&#x62f;.&#x627;',
        'JPY' => '&yen;',
        'KES' => 'KSh',
        'KGS' => '&#x441;&#x43e;&#x43c;',
        'KHR' => '&#x17db;',
        'KMF' => 'Fr',
        'KPW' => '&#x20a9;',
        'KRW' => '&#8361;',
        'KWD' => '&#x62f;.&#x643;',
        'KYD' => '&#36;',
        'KZT' => 'KZT',
        'LAK' => '&#8365;',
        'LBP' => '&#x644;.&#x644;',
        'LKR' => '&#xdbb;&#xdd4;',
        'LRD' => '&#36;',
        'LSL' => 'L',
        'LYD' => '&#x644;.&#x62f;',
        'MAD' => '&#x62f;.&#x645;.',
        'MDL' => 'MDL',
        'MGA' => 'Ar',
        'MKD' => '&#x434;&#x435;&#x43d;',
        'MMK' => 'Ks',
        'MNT' => '&#x20ae;',
        'MOP' => 'P',
        'MRU' => 'UM',
        'MUR' => '&#x20a8;',
        'MVR' => '.&#x783;',
        'MWK' => 'MK',
        'MXN' => '&#36;',
        'MYR' => '&#82;&#77;',
        'MZN' => 'MT',
        'NAD' => 'N&#36;',
        'NGN' => '&#8358;',
        'NIO' => 'C&#36;',
        'NOK' => '&#107;&#114;',
        'NPR' => '&#8360;',
        'NZD' => '&#36;',
        'OMR' => '&#x631;.&#x639;.',
        'PAB' => 'B/.',
        'PEN' => 'S/',
        'PGK' => 'K',
        'PHP' => '&#8369;',
        'PKR' => '&#8360;',
        'PLN' => '&#122;&#322;',
        'PRB' => '&#x440;.',
        'PYG' => '&#8370;',
        'QAR' => '&#x631;.&#x642;',
        'RMB' => '&yen;',
        'RON' => 'lei',
        'RSD' => '&#x434;&#x438;&#x43d;.',
        'RUB' => '&#8381;',
        'RWF' => 'Fr',
        'SAR' => '&#x631;.&#x633;',
        'SBD' => '&#36;',
        'SCR' => '&#x20a8;',
        'SDG' => '&#x62c;.&#x633;.',
        'SEK' => '&#107;&#114;',
        'SGD' => '&#36;',
        'SHP' => '&pound;',
        'SLL' => 'Le',
        'SOS' => 'Sh',
        'SRD' => '&#36;',
        'SSP' => '&pound;',
        'STN' => 'Db',
        'SYP' => '&#x644;.&#x633;',
        'SZL' => 'L',
        'THB' => '&#3647;',
        'TJS' => '&#x405;&#x41c;',
        'TMT' => 'm',
        'TND' => '&#x62f;.&#x62a;',
        'TOP' => 'T&#36;',
        'TRY' => '&#8378;',
        'TTD' => '&#36;',
        'TWD' => '&#78;&#84;&#36;',
        'TZS' => 'Sh',
        'UAH' => '&#8372;',
        'UGX' => 'UGX',
        'USD' => '&#36;',
        'UYU' => '&#36;',
        'UZS' => 'UZS',
        'VEF' => 'Bs F',
        'VES' => 'Bs.S',
        'VND' => '&#8363;',
        'VUV' => 'Vt',
        'WST' => 'T',
        'XAF' => 'CFA',
        'XCD' => '&#36;',
        'XOF' => 'CFA',
        'XPF' => 'Fr',
        'YER' => '&#xfdfc;',
        'ZAR' => '&#82;',
        'ZMW' => 'ZK',
    );
}

function pys_generate_token( $length = 36 ) {
	$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
	$charactersLength = strlen( $characters );
	$randomString = '';
	for ( $i = 0; $i < $length; $i++ ) {
		$randomString .= $characters[ random_int( 0, $charactersLength - 1 ) ];
	}
	return $randomString;
}

/**
 * Remove url params [url_*]
 * @param $params
 * @return mixed
 */
function remove_url_params( $params ) {
	if ( !empty( $params ) ) {
		foreach ( $params as &$param ) {
			if ( preg_match( '/^\[?url_([^\]]*)\]?$/i', $param, $matches ) ) {
				$param = $matches[ 1 ];
			}
		}
	}

	return $params;
}

/**
 * Get persistence user data
 * @param $em
 * @param $fn
 * @param $ln
 * @param $tel
 * @return array
 */
function get_persistence_user_data( $em, $fn, $ln, $tel ) {
	if ( !apply_filters( 'pys_disable_advanced_form_data_cookie', false ) && !apply_filters( 'pys_disable_advance_data_cookie', false ) && (!is_admin() || wp_doing_ajax())) {
        if ( isset( $_COOKIE[ "pys_advanced_form_data" ] ) ) {
			$userData = json_decode( stripslashes( $_COOKIE[ "pys_advanced_form_data" ] ), true );
			$data_persistence = PYS()->getOption( 'data_persistency' );

			if ( isset( $userData[ "email" ] ) && $userData[ "email" ] != "" && ( $data_persistence == 'keep_data' || empty( $em ) ) ) {
				$em = $userData[ "email" ];
			}
			if ( isset( $userData[ "phone" ] ) && $userData[ "phone" ] != "" && ( $data_persistence == 'keep_data' || empty( $tel ) ) ) {
				$tel = $userData[ "phone" ];
			}
			if ( isset( $userData[ "first_name" ] ) && $userData[ "first_name" ] != "" && ( $data_persistence == 'keep_data' || empty( $fn ) ) ) {
				$fn = $userData[ "first_name" ];
			}
			if ( isset( $userData[ "last_name" ] ) && $userData[ "last_name" ] != "" && ( $data_persistence == 'keep_data' || empty( $ln ) ) ) {
				$ln = $userData[ "last_name" ];
			}
		}
	}

	return array(
		'em'  => $em,
		'fn'  => $fn,
		'ln'  => $ln,
		'tel' => $tel
	);
}

function update_advance_matching_user_data( $first_name, $last_name, $email, $phone ) {
	if ( !apply_filters( 'pys_disable_advanced_form_data_cookie', false ) && !apply_filters( 'pys_disable_advance_data_cookie', false ) ) {
		$user_persistence_data = get_persistence_user_data( $email, $first_name, $last_name, $phone );
		$userData = array(
			'first_name' => $user_persistence_data[ 'fn' ],
			'last_name'  => $user_persistence_data[ 'ln' ],
			'email'      => $user_persistence_data[ 'em' ],
			'phone'      => $user_persistence_data[ 'tel' ]
		);

		if ( isset( $_COOKIE[ "pys_advanced_form_data" ] ) ) {
			$userData_cookie = json_decode( stripslashes( $_COOKIE[ "pys_advanced_form_data" ] ), true );
			$data_persistence = PYS()->getOption( 'data_persistency' );

			if ( isset( $userData_cookie[ 'emails' ] ) && is_array( $userData_cookie[ 'emails' ] ) ) {
				if ( $data_persistence == 'keep_data' ) {
					$userData[ 'emails' ] = array_merge( $userData_cookie[ 'emails' ], array( $email ) );
				} else {
					$userData[ 'emails' ] = array_merge( array( $email ), $userData_cookie[ 'emails' ] );
				}
				$userData[ 'emails' ] = array_unique( $userData[ 'emails' ] );
				$userData[ 'emails' ] = array_slice( $userData[ 'emails' ], 0, 3 );
			} else {
				$userData[ 'emails' ] = array( $email );
			}

			if ( isset( $userData_cookie[ 'fns' ] ) && is_array( $userData_cookie[ 'fns' ] ) ) {
				if ( $data_persistence == 'keep_data' ) {
					$userData[ 'fns' ] = array_merge( $userData_cookie[ 'fns' ], array( $first_name ) );
				} else {
					$userData[ 'fns' ] = array_merge( array( $first_name ), $userData_cookie[ 'fns' ] );
				}
				$userData[ 'fns' ] = array_unique( $userData[ 'fns' ] );
				$userData[ 'fns' ] = array_slice( $userData[ 'fns' ], 0, 2 );
			} else {
				$userData[ 'fns' ] = array( $first_name );
			}

			if ( isset( $userData_cookie[ 'lns' ] ) && is_array( $userData_cookie[ 'lns' ] ) ) {
				if ( $data_persistence == 'keep_data' ) {
					$userData[ 'lns' ] = array_merge( $userData_cookie[ 'lns' ], array( $last_name ) );
				} else {
					$userData[ 'lns' ] = array_merge( array( $last_name ), $userData_cookie[ 'lns' ] );
				}
				$userData[ 'lns' ] = array_unique( $userData[ 'lns' ] );
				$userData[ 'lns' ] = array_slice( $userData[ 'lns' ], 0, 2 );
			} else {
				$userData[ 'lns' ] = array( $last_name );
			}

			if ( !empty( $phone ) ) {
				if ( isset( $userData_cookie[ 'phones' ] ) && is_array( $userData_cookie[ 'phones' ] ) ) {
					if ( $data_persistence == 'keep_data' ) {
						$userData[ 'phones' ] = array_merge( $userData_cookie[ 'phones' ], array( $phone ) );
					} else {
						$userData[ 'phones' ] = array_merge( array( $phone ), $userData_cookie[ 'phones' ] );
					}
					$userData[ 'phones' ] = array_unique( $userData[ 'phones' ] );
					$userData[ 'phones' ] = array_slice( $userData[ 'phones' ], 0, 2 );
				} else {
					$userData[ 'phones' ] = array( $phone );
				}
			} else {
				$userData[ 'phones' ] = $userData_cookie[ 'phones' ] ?? array();
			}

		} else {
			$userData[ 'emails' ] = array( $email );
			$userData[ 'fns' ] = array( $first_name );
			$userData[ 'lns' ] = array( $last_name );
			$userData[ 'phones' ] = array( $phone );
		}

		setcookie( 'pys_advanced_form_data', json_encode( $userData ), 2147483647, '/', PYS()->general_domain );
	}
}

function getUserData() {
    $userData = array();
    $emails = $addresses = $phones = array();

    $user = wp_get_current_user();
    if ( $user != null && $user->ID != 0 ) {
        $user_meta = get_user_meta( $user->ID, '', true );

        $emails[] = $user->user_email;
        if ( !empty( $user_meta[ 'billing_phone' ] ) && !empty( $user_meta[ 'billing_phone' ][ 0 ] ) ) $phones[] = $user_meta[ 'billing_phone' ][ 0 ];

        $first_name = $user->first_name;
        $last_name = $user->last_name;
        $city = !empty( $user_meta[ 'billing_city' ] ) ? $user_meta[ 'billing_city' ][ 0 ] : '';
        $country = !empty( $user_meta[ 'billing_country' ] ) ? $user_meta[ 'billing_country' ][ 0 ] : '';

        if ( $first_name && $last_name && $country && $city ) {

            $address = array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'city'       => $city,
                'country'    => $country
            );

            $street = !empty( $user_meta[ 'billing_address_1' ] ) ? $user_meta[ 'billing_address_1' ][ 0 ] : '';
            $region = !empty( $user_meta[ 'billing_state' ] ) ? $user_meta[ 'billing_state' ][ 0 ] : '';
            $zip = !empty( $user_meta[ 'billing_postcode' ] ) ? $user_meta[ 'billing_postcode' ][ 0 ] : '';

            if ( $zip ) $address[ 'postal_code' ] = $zip; // additional
            if ( $street ) $address[ 'street' ] = $street; // additional
            if ( $region ) $address[ 'region' ] = $region; // additional

            $addresses[] = $address;
        }
    }
    if ( isWooCommerceActive() ) {
        $wooOrder = EventsWoo()->getOrder();
        if ( $wooOrder ) {

            $emails[] = $wooOrder->get_billing_email();
            $phones[] = $wooOrder->get_billing_phone();

            $city = $wooOrder->get_billing_city();
            $country = $wooOrder->get_billing_country();
            $first_name = $wooOrder->get_billing_first_name();
            $last_name = $wooOrder->get_billing_last_name();

            if ( $first_name && $last_name && $country && $city ) {
                $address = array(
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'city'       => $city,
                    'country'    => $country
                );

                $zip = $wooOrder->get_billing_postcode();
                $street = $wooOrder->get_billing_address_1();

                if ( $zip ) $address[ 'postal_code' ] = $zip; // additional
                if ( $street ) $address[ 'street' ] = $street; // additional

                $addresses[] = $address;
            }
        }
    }
    if ( isEddActive() ) {
        $eddOrderId = EventsEdd()->getEddOrderId();
        if ( $eddOrderId ) {
            $payment = new \EDD_Payment( $eddOrderId );
            if ( $payment ) {
                $meta = $payment->get_meta();
                if ( isset( $meta[ 'user_info' ] ) ) {

                    $first_name = $last_name = '';

                    if ( isset( $meta[ 'user_info' ][ 'email' ] ) ) {
                        $emails[] = $meta[ 'user_info' ][ 'email' ];
                    }
                    if ( isset( $meta[ 'user_info' ][ 'first_name' ] ) ) {
                        $first_name = $meta[ 'user_info' ][ 'first_name' ];
                    }
                    if ( isset( $meta[ 'user_info' ][ 'last_name' ] ) ) {
                        $last_name = $meta[ 'user_info' ][ 'last_name' ];
                    }
                    if ( isset( $meta[ 'user_info' ][ 'address' ] ) ) {
                        $addresses[] = $meta[ 'user_info' ][ 'address' ];
                    }

                    if ( $first_name && $last_name && !empty( $addresses ) ) {
                        $address = array(
                            'first_name' => $first_name,
                            'last_name'  => $last_name,
                            'city'       => $addresses[ 0 ][ 'city' ],
                            'country'    => $addresses[ 0 ][ 'country' ]
                        );
                        $addresses[] = $address;
                    }
                }
            }
        }
    }

    if ( GA()->getOption( 'use_multiple_provided_data' ) || GTM()->getOption( 'use_multiple_provided_data' )) {
        $userData[ 'emails' ] = array_slice( $emails, 0, 3 );
        $userData[ 'phones' ] = array_slice( $phones, 0, 3 );
        $userData[ 'addresses' ] = array_slice( $addresses, 0, 2 );
    } else {
        $userData[ 'emails' ] = array_slice( $emails, 0, 1 );
        $userData[ 'phones' ] = array_slice( $phones, 0, 1 );
        $userData[ 'addresses' ] = array_slice( $addresses, 0, 1 );
    }

    return $userData;
}

/**
 * Check if Elementor plugin installed and activated.
 * @return bool
 */
function isElementorActive() {

	if ( !function_exists( 'is_plugin_active' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	}

	return is_plugin_active( 'elementor/elementor.php' ) || is_plugin_active( 'elementor-pro/elementor-pro.php' );
}

function getAllMetaEventParamName(){
    $metaEventParamName = array(
        'event_url'=>'Event URL',
        'landing_page'=>'Landing Page URL',
        'post_id'=>'Post ID',
        'post_title'=>'Post Title',
        'post_type'=>'Post Type',
        'page_title' => 'Page Title',
        'content_name'=>'Content Name',
        'content_type'=>'Content Type',
        'categories'=>'Categories',
        'category_name'=>'Category Name',
        'tags'=>'Tags',
        'user_role'=>'User Role',
        'plugin'=>'Plugin',
    );

    return $metaEventParamName;
}

function get_aw_feed_country_codes() {
    $country = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CV' => 'Cape Verde',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo - Brazzaville',
        'CD' => 'Congo - Kinshasa',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'ET' => 'Ethiopia',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GR' => 'Greece',
        'GD' => 'Grenada',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar (Burma)',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'KP' => 'North Korea',
        'MK' => 'North Macedonia',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'KR' => 'South Korea',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TW' => 'Taiwan',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe'
    ];

    $result = array_map(
        function($name, $code) {
            return $name . " ($code)";
        },
        $country,
        array_keys($country)
    );
    return array_combine(array_keys($country), $result);
}

function get_aw_feed_language_codes() {
    $language = [
        'AB' => 'Abkhaz',
        'AA' => 'Afar',
        'AF' => 'Afrikaans',
        'AK' => 'Akan',
        'SQ' => 'Albanian',
        'AM' => 'Amharic',
        'AR' => 'Arabic',
        'AN' => 'Aragonese',
        'HY' => 'Armenian',
        'AS' => 'Assamese',
        'AV' => 'Avaric',
        'AE' => 'Avestan',
        'AY' => 'Aymara',
        'AZ' => 'Azerbaijani',
        'BM' => 'Bambara',
        'BA' => 'Bashkir',
        'EU' => 'Basque',
        'BE' => 'Belarusian',
        'BN' => 'Bengali',
        'BH' => 'Bihari',
        'BI' => 'Bislama',
        'BS' => 'Bosnian',
        'BR' => 'Breton',
        'BG' => 'Bulgarian',
        'MY' => 'Burmese',
        'CA' => 'Catalan',
        'CH' => 'Chamorro',
        'CE' => 'Chechen',
        'NY' => 'Chichewa',
        'ZH' => 'Chinese',
        'CV' => 'Chuvash',
        'KW' => 'Cornish',
        'CO' => 'Corsican',
        'CR' => 'Cree',
        'HR' => 'Croatian',
        'CS' => 'Czech',
        'DA' => 'Danish',
        'DV' => 'Divehi',
        'NL' => 'Dutch',
        'DZ' => 'Dzongkha',
        'EN' => 'English',
        'EO' => 'Esperanto',
        'ET' => 'Estonian',
        'EE' => 'Ewe',
        'FO' => 'Faroese',
        'FJ' => 'Fijian',
        'FI' => 'Finnish',
        'FR' => 'French',
        'FF' => 'Fula',
        'GL' => 'Galician',
        'KA' => 'Georgian',
        'DE' => 'German',
        'EL' => 'Greek',
        'GN' => 'Guarani',
        'GU' => 'Gujarati',
        'HT' => 'Haitian',
        'HA' => 'Hausa',
        'HE' => 'Hebrew',
        'HZ' => 'Herero',
        'HI' => 'Hindi',
        'HO' => 'Hiri Motu',
        'HU' => 'Hungarian',
        'IA' => 'Interlingua',
        'ID' => 'Indonesian',
        'IE' => 'Interlingue',
        'GA' => 'Irish',
        'IG' => 'Igbo',
        'IK' => 'Inupiaq',
        'IO' => 'Ido',
        'IS' => 'Icelandic',
        'IT' => 'Italian',
        'IU' => 'Inuktitut',
        'JA' => 'Japanese',
        'JV' => 'Javanese',
        'KL' => 'Kalaallisut',
        'KN' => 'Kannada',
        'KR' => 'Kanuri',
        'KS' => 'Kashmiri',
        'KK' => 'Kazakh',
        'KM' => 'Khmer',
        'KI' => 'Kikuyu',
        'RW' => 'Kinyarwanda',
        'KY' => 'Kyrgyz',
        'KV' => 'Komi',
        'KG' => 'Kongo',
        'KO' => 'Korean',
        'KU' => 'Kurdish',
        'KJ' => 'Kwanyama',
        'LA' => 'Latin',
        'LB' => 'Luxembourgish',
        'LG' => 'Luganda',
        'LI' => 'Limburgish',
        'LN' => 'Lingala',
        'LO' => 'Lao',
        'LT' => 'Lithuanian',
        'LU' => 'Luba-Katanga',
        'LV' => 'Latvian',
        'GV' => 'Manx',
        'MK' => 'Macedonian',
        'MG' => 'Malagasy',
        'MS' => 'Malay',
        'ML' => 'Malayalam',
        'MT' => 'Maltese',
        'MI' => 'Maori',
        'MR' => 'Marathi',
        'MH' => 'Marshallese',
        'MN' => 'Mongolian',
        'NA' => 'Nauru',
        'NV' => 'Navajo',
        'ND' => 'North Ndebele',
        'NE' => 'Nepali',
        'NG' => 'Ndonga',
        'NB' => 'Norwegian Bokmål',
        'NN' => 'Norwegian Nynorsk',
        'NO' => 'Norwegian',
        'II' => 'Nuosu',
        'NR' => 'South Ndebele',
        'OC' => 'Occitan',
        'OJ' => 'Ojibwe',
        'CU' => 'Old Church Slavonic',
        'OM' => 'Oromo',
        'OR' => 'Oriya',
        'OS' => 'Ossetian',
        'PA' => 'Punjabi',
        'PI' => 'Pali',
        'FA' => 'Persian',
        'PL' => 'Polish',
        'PS' => 'Pashto',
        'PT' => 'Portuguese',
        'QU' => 'Quechua',
        'RM' => 'Romansh',
        'RN' => 'Kirundi',
        'RO' => 'Romanian',
        'RU' => 'Russian',
        'SA' => 'Sanskrit',
        'SC' => 'Sardinian',
        'SD' => 'Sindhi',
        'SE' => 'Northern Sami',
        'SM' => 'Samoan',
        'SG' => 'Sango',
        'SR' => 'Serbian',
        'GD' => 'Scottish Gaelic',
        'SN' => 'Shona',
        'SI' => 'Sinhala',
        'SK' => 'Slovak',
        'SL' => 'Slovenian',
        'SO' => 'Somali',
        'ST' => 'Southern Sotho',
        'ES' => 'Spanish',
        'SU' => 'Sundanese',
        'SW' => 'Swahili',
        'SS' => 'Swati',
        'SV' => 'Swedish',
        'TA' => 'Tamil',
        'TE' => 'Telugu',
        'TG' => 'Tajik',
        'TH' => 'Thai',
        'TI' => 'Tigrinya',
        'BO' => 'Tibetan',
        'TK' => 'Turkmen',
        'TL' => 'Tagalog',
        'TN' => 'Tswana',
        'TO' => 'Tongan',
        'TR' => 'Turkish',
        'TS' => 'Tsonga',
        'TT' => 'Tatar',
        'TW' => 'Twi',
        'TY' => 'Tahitian',
        'UG' => 'Uyghur',
        'UK' => 'Ukrainian',
        'UR' => 'Urdu',
        'UZ' => 'Uzbek',
        'VE' => 'Venda',
        'VI' => 'Vietnamese',
        'VO' => 'Volapük',
        'WA' => 'Walloon',
        'CY' => 'Welsh',
        'WO' => 'Wolof',
        'XH' => 'Xhosa',
        'YI' => 'Yiddish',
        'YO' => 'Yoruba',
        'ZA' => 'Zhuang',
        'ZU' => 'Zulu'
    ];

    $result = array_map(
        function($name, $code) {
            return $name . " ($code)";
        },
        $language,
        array_keys($language)
    );
    return array_combine(array_keys($language), $result);
}

function pys_get_option( $option, $default = false ) {
    global $wpdb;

    $table = $wpdb->prefix . 'pys_options';

    // We are trying to get from our table
    $value = $wpdb->get_var( $wpdb->prepare(
        "SELECT option_value FROM {$table} WHERE option_name = %s LIMIT 1",
        $option
    ) );

    if ( $value !== null ) {
        return maybe_unserialize( $value );
    }

    // If it is not in your table, try wp_options
    $value = get_option( $option, $default );

    return $value;
}
function pys_update_option( $option, $value ) {
    global $wpdb;

    $table = $wpdb->prefix . 'pys_options';

    $data = [
        'option_name'  => $option,
        'option_value' => maybe_serialize( $value ),
    ];
    $formats = [ '%s', '%s' ];

    // Update or insert
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE option_name = %s",
        $option
    ) );

    if ( $existing ) {
        $wpdb->update( $table, $data, [ 'option_name' => $option ], $formats, [ '%s' ] );
    } else {
        $wpdb->insert( $table, $data, $formats );
    }

    return true;
}