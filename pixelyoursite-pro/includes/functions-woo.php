<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function isWooCommerceVersionGte( $version ) {

    if ( defined( 'WC_VERSION' ) && WC_VERSION ) {
        return version_compare( WC_VERSION, $version, '>=' );
    } else if ( defined( 'WOOCOMMERCE_VERSION' ) && WOOCOMMERCE_VERSION ) {
        return version_compare( WOOCOMMERCE_VERSION, $version, '>=' );
    } else {
        return false;
    }

}

/**
 * @param \WC_Product|\WP_Post $product
 *
 * @return bool
 */
function wooProductIsType( $product, $type ) {

    if ( isWooCommerceVersionGte( '2.7' ) ) {
        return $type == $product->is_type( $type );
    } else {
        return $product->product_type == $type;
    }

}

function getWooPayPalEventName() {

    $woo_paypal_custom_event_type = PYS()->getOption( 'woo_paypal_custom_event_type' );

    if ( PYS()->getOption( 'woo_paypal_event_type' ) == 'custom' && ! empty( $woo_paypal_custom_event_type ) ) {
        return sanitizeKey( PYS()->getOption( 'woo_paypal_custom_event_type' ) );
    } else {
        return PYS()->getOption( 'woo_paypal_event_type' );
    }
}

function getWooProductPrice( $product_id, $qty = 1,$customPrice = -1 ) {

    $product = wc_get_product( $product_id );

    if( false == $product ) {
        return 0;
    }

    if($product->get_type() == "variable") {
        $prices = $product->get_variation_prices( true );
        if(empty( $prices['price'] )) {
            $productPrice = $product->get_price();
        } else {
            $productPrice = current( $prices['price'] );
        }

    } else {
        $productPrice = $product->get_price();
    }

    if($customPrice > -1 && $customPrice != "") {
        $productPrice = $customPrice;
    }


    if(PYS()->getOption( 'woo_event_value' ) === 'custom') {
        $include_tax = PYS()->getOption( 'woo_tax_option' ) == 'included' ? true : false;
        if ( $product->is_taxable() && $include_tax ) {
            $value = wc_get_price_including_tax( $product, array(
                'price' => $productPrice,
                'qty' => $qty
            ) );
        } else {
            $value = wc_get_price_excluding_tax( $product, array(
                'price' => $productPrice,
                'qty' => $qty
            ) );
        }
        return pys_round($value);
    }

    if ( $product->is_taxable()) {
        $value = wc_get_price_including_tax( $product, array(
            'price' => $productPrice,
            'qty' => $qty
        ) );
    } else {
        $value = wc_get_price_excluding_tax( $product, array(
            'price' => $productPrice,
            'qty' => $qty
        ) );
    }

    return $value;

}

function getWooProductPriceToDisplay( $product_id, $qty = 1,$customPrice = -1 ) {

    if ( ! $product = wc_get_product( $product_id ) ) {
        return 0;
    }
    if($product->get_type() == "bundle") {
        $price = (float) getDefaultBundlePrice($product);//->get_bundle_price("min",true);
        return formatPriceTrimZeros($price);
    }
    if($product->get_type() == "variable") {
        $prices = $product->get_variation_prices( true );
        if(empty( $prices['price'] )) {
            $productPrice = $product->get_price();
        } else {
            $variation_id = key($prices['price']); // Getting the variation ID
            $variation = wc_get_product($variation_id); // Creating a Variation Instance

            if ($variation && is_a($variation, 'WC_Product')) { // Check if $variation is a valid product object
                $productPrice = current( $prices['price'] );
            } else {
                // Handle the case where no valid variation is found
                // For example, fallback to the parent product's price or set a default price
                $productPrice = $product->get_price(); // Fallback to the parent product's price
            }
        }

    } else {
        $productPrice = $product->get_price();
    }
    if($customPrice > -1 && $customPrice != "") {
        $productPrice = $customPrice;
    }



    return formatPriceTrimZeros(wc_get_price_to_display( $product, array( 'qty' => $qty,'price'=>$productPrice ) ) );
}

function getWooBundleProductCartPrice( $cart_item ) {

    $price = $cart_item['line_subtotal'];

    foreach ($cart_item['bundled_items'] as $has) {
        $bundled_cart_item = WC()->cart->get_cart_item($has);
        $price += $bundled_cart_item["line_subtotal"];
    }

    return $price;
}


/**
 * @param \WC_Product_Bundle $product
 */
function getDefaultBundlePrice($product) {
    $qty = 1;
    $price_prop = "price";
    $price_calc = 'display';
    $strict = false;
    $min_or_max = "min";
    if($product->contains( 'priced_individually' )) {

        $price_fn = 'get_' . $price_prop;
        $price    = wc_format_decimal( \WC_PB_Product_Prices::get_product_price( $product, array(
            'price' => $product->$price_fn(),
            'qty'   => $qty,
            'calc'  => $price_calc,
        ) ), wc_get_price_decimals() );

        $bundled_items = $product->get_bundled_items();

        if ( ! empty( $bundled_items ) ) {
            foreach ( $bundled_items as $bundled_item ) {

                if ( false === $bundled_item->is_purchasable() ) {
                    continue;
                }

                if ( false === $bundled_item->is_priced_individually() ) {
                    continue;
                }

                if($bundled_item->is_optional()) {
                    continue;
                }

                $bundled_item_qty = $qty * $bundled_item->get_quantity( "default", array( 'context' => 'price', 'check_optional' => $min_or_max === 'min' ) );

                if ( $bundled_item_qty ) {

                    $price += wc_format_decimal( $bundled_item->calculate_price( array(
                        'min_or_max' => $min_or_max,
                        'qty'        => $bundled_item_qty,
                        'strict'     => $strict,
                        'calc'       => $price_calc,
                        'prop'       => $price_prop
                    ) ), wc_get_price_decimals() );
                }
            }
        }

    } else {
        $price_fn = 'get_' . $price_prop;
        $price    = \WC_PB_Product_Prices::get_product_price( $product, array(
            'price' => $product->$price_fn(),
            'qty'   => $qty,
            'calc'  => $price_calc,
        ) );


    }

    return $price;
}

/**
 * @param SingleEvent $event
 */
function getWooEventCartSubtotal($event) {
    $subTotal = 0;
    $include_tax = get_option( 'woocommerce_tax_display_cart' ) == 'incl';
    if ( WC()->cart ) {
        $subTotal = WC()->cart->get_subtotal(); // Total cost of goods excluding taxes and discounts
        $subtotal_tax = WC()->cart->get_subtotal_tax(); // Tax on the amount of goods

        if($include_tax) {
            $subTotal += $subtotal_tax;
        }
    }
    return pys_round($subTotal);
}

/**
 * @param SingleEvent $event
 */
function getWooEventCartTotal($event) {

    return getWooEventCartSubtotal($event);
}
/**
 * @param SingleEvent $event
 */
function getWooEventOrderTotal( $event ) {

    if(!$event->args['order_id']) return 0;

    // Check if AWCDP order is passed in args (for partial payments)
    if (isset($event->args['awcdp_order']) && $event->args['awcdp_order'] instanceof \AWCDP_Order) {
        $order = $event->args['awcdp_order'];
    } else {
        $order = wc_get_order($event->args['order_id']);
    }

    if (!$order) {
        return 0;
    }

    if(PYS()->getOption( 'woo_event_value' ) != 'custom') {
        $total = $order->get_total();
        return pys_round($total);
    }

    $include_tax = PYS()->getOption( 'woo_tax_option' ) == 'included' ? true : false;
    $include_shipping = PYS()->getOption( 'woo_shipping_option' ) == 'included' ? true : false;
    $include_fees = PYS()->getOption( 'woo_fees_option' ) == 'included' ? true : false;


    $total = 0;
    foreach ($order->get_items() as $item_id => $item) {
        $line_total = $item->get_total(); // Total cost
        if ($include_tax) {
            $line_total += $item->get_total_tax(); // Add tax if included
        }
        $total += $line_total;
    }

    if ($include_shipping) {
        $total += $order->get_shipping_total(); // Shipping cost
        if ($include_tax) {
            $total += $order->get_shipping_tax(); // Shipping tax
        }
    }

    if ($include_fees) {
        foreach ($order->get_fees() as $fee) {
            $total += $fee->get_total();
        }
    }
    return pys_round($total );

}

function getWooCartSubtotal() {
    WC()->cart->calculate_totals();
    // subtotal is always same value on front-end and depends on PYS options
    $include_tax = get_option( 'woocommerce_tax_display_cart' ) == 'incl';

    if ( $include_tax ) {

        if ( isWooCommerceVersionGte( '3.2.0' ) ) {
            $subtotal = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
        } else {
            $subtotal = WC()->cart->subtotal;
        }

    } else {

        if ( isWooCommerceVersionGte( '3.2.0' ) ) {
            $subtotal = (float) WC()->cart->get_subtotal();
        } else {
            $subtotal = WC()->cart->subtotal_ex_tax;
        }

    }

    return $subtotal;
}

function getWooCartTotal() {

    $include_tax = PYS()->getOption( 'woo_tax_option' ) == 'included' ? true : false;

    if ( $include_tax ) {
        $total = WC()->cart->cart_contents_total + WC()->cart->tax_total;
    } else {
        $total = WC()->cart->cart_contents_total;
    }

    return $total;

}

/**
 * @param \WC_Order $order
 *
 * @return string
 */
function getWooOrderTotal( $order ) {

    $include_tax = PYS()->getOption( 'woo_tax_option' ) == 'included' ? true : false;
    $include_shipping = PYS()->getOption( 'woo_shipping_option' ) == 'included' ? true : false;
    $include_fees = PYS()->getOption( 'woo_fees_option' ) == 'included' ? true : false;

    if ($include_shipping && $include_tax && $include_fees) {

        $total = $order->get_total();   // full order price

    } elseif ( ! $include_shipping && ! $include_tax ) {

        $cart_subtotal  = $order->get_subtotal();

        if ( isWooCommerceVersionGte( '2.7' ) ) {
            $discount_total = (float) $order->get_discount_total( 'edit' );
        } else {
            $discount_total = $order->get_total_discount();
        }

        $total = $cart_subtotal - $discount_total;

    } elseif ( ! $include_shipping && $include_tax ) {

        if ( isWooCommerceVersionGte( '2.7' ) ) {
            $cart_total     = (float) $order->get_total( 'edit' );
            $shipping_total = (float) $order->get_shipping_total( 'edit' );
            $shipping_tax   = (float) $order->get_shipping_tax( 'edit' );
        } else {
            $cart_total     = $order->get_total();
            $shipping_total = $order->get_total_shipping();
            $shipping_tax   = $order->get_shipping_tax();
        }

        $total = $cart_total - $shipping_total - $shipping_tax;

    } else {
        // $include_shipping && !$include_tax

        $cart_subtotal  = $order->get_subtotal();

        if ( isWooCommerceVersionGte( '2.7' ) ) {
            $discount_total = (float) $order->get_discount_total( 'edit' );
            $shipping_total = (float) $order->get_shipping_total( 'edit' );
        } else {
            $discount_total = $order->get_total_discount();
            $shipping_total = $order->get_total_shipping();
        }

        $total = $cart_subtotal - $discount_total + $shipping_total;

    }

    if(!$include_fees){
        $fees = $order->get_fees();
        $fee_amount = 0;

        foreach ($fees as $fee) {
            $fee_amount += $fee->get_total();
        }
        if($fee_amount > 0){
            $total = $total - $fee_amount;
        }
    }
    //wc_get_price_thousand_separator is ignored
    return number_format( $total, wc_get_price_decimals(), '.', '' );

}

/**
 * @deprecated use getWooProductValue
 * @param String $valueOption
 * @param float $global
 * @param float $percent
 * @param int $product_id
 * @param int $qty
 * @return false|float|int
 */

function getWooEventValue( $valueOption, $global, $percent, $product_id,$qty ) {
    return getWooProductValue([
        'valueOption' => $valueOption,
        'global' => $global,
        'percent' => $percent,
        'product_id' => $product_id,
        'qty' => $qty
    ]);
}

function getWooProductValue($args) {
    $valueOption = $args['valueOption'];
    $global = $args['global'];
    $percent = $args['percent'];
    $product_id = $args['product_id'];
    $qty = $args['qty'];


    $product = wc_get_product($product_id);
    if(!$product) return 0;

    $productPrice = "";

    if(!empty($args['price'])) {
        $productPrice = $args['price'];
    }
    // for cartflow product sale
    $salePrice = getWfcProductSalePrice($product,$args);
    if($salePrice > -1 ) {
        $productPrice = $salePrice;
    }



    if($valueOption == 'cog' && isPixelCogActive()) {
        return pys_woo_get_cog_product_value($product,$qty,$productPrice);
    }

    if ( PYS()->getOption( 'woo_event_value' ) == 'custom' ) {
        $amount = getWooProductPrice( $product_id, $qty,$productPrice );
    } else {
        $amount = getWooProductPriceToDisplay( $product_id, $qty,$productPrice );
    }

    switch ( $valueOption ) {
        case 'global': $value = $global; break;
        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', null, $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;
        default:$value = (float)$amount;
    }
    return formatPriceTrimZeros($value);
}

function getWcfEventValueOrder( $valueOption, $wcf_offer_step_id, $global, $percent ,$quantity) {

    $wcf_product = getWcfOfferProduct($wcf_offer_step_id);
    $product = wc_get_product($wcf_product['product']);
    $price = getWfcProductSalePrice($product,$wcf_product);
    $offer_shipping_fee = wcf_get_offer_shipping($wcf_offer_step_id);

    if($price < 0) {
        $price = $product->get_price();
    }


    if ( PYS()->getOption( 'woo_event_value' ) == 'custom' ) {
        $include_tax = PYS()->getOption( 'woo_tax_option' ) == 'included' ? true : false;
        $include_shipping = PYS()->getOption( 'woo_shipping_option' ) == 'included' ? true : false;

        if($include_shipping) {
            $amount = $price * $quantity + $offer_shipping_fee;
        } else {
            $amount = $price * $quantity;
        }
    } else {
        $amount = $price * $quantity;
    }

    switch ( $valueOption ) {
        case 'global':
            $value = (float) $global;
            break;

        case 'cog':
            $cog_value = pys_woo_get_cog_product_value($product,$quantity,$price);
            ($cog_value !== '') ? $value = (float) round($cog_value, 2) : $value = (float) $amount;
            if ( !isPixelCogActive() ) $value = (float) $amount;
            break;

        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', null, $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;

        default:    // "price" option
            $value = (float) $amount;
    }

    return $value;
}

/**
 * @param string $valueOption  // 'global' or 'cog' or 'percent' other is default
 * @param float $global // global value
 * @param string $percent // percent from value from 0 to 100%
 * @param $args // other args
 */
function getWooEventValueProducts( $valueOption, $global, $percent, $total, $args ) {

    if($valueOption == 'global') return $global;

    if($valueOption == 'percent') {
        $percents = (float) $percent;
        $percents = str_replace( '%', null, $percents );
        $percents = (float) $percents / 100;
        return (float) $total * $percents;
    }

    if($valueOption == 'cog' && isPixelCogActive()) {
        $cog_value = getAvailableProductCogOrder($args);
        if($cog_value !== '') {
            return (float) round($cog_value, 2);
        }
    }

    return formatPriceTrimZeros($total);
}
/**
 * @deprecated
 * @param  $valueOption
 * @param \WC_Order $order
 * @param $global
 * @param $order_id
 * @param $content_ids
 * @param int $percent
 * @return float|int
 */
function getWooEventValueOrder( $valueOption, $order, $global, $percent = 100 ) {

    if ( PYS()->getOption( 'woo_event_value' ) == 'custom' ) {
        $amount = getWooOrderTotal( $order );
    } else {
        $amount = $order->get_total();
    }
    switch ( $valueOption ) {
        case 'global':
            $value = (float) $global;
            break;

        case 'cog':
            $cog_value = getAvailableProductCogOrder($order->get_id());
            ($cog_value !== '') ? $value = (float) round($cog_value, 2) : $value = (float) $amount;
            if ( !isPixelCogActive() ) $value = (float) $amount;
            break;

        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', null, $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;

        default:    // "price" option
            $value = (float) $amount;
    }

    return formatPriceTrimZeros($value);

}


/**
 * @deprecated
 * @param $valueOption
 * @param $global
 * @param int $percent
 * @return bool|float|int|mixed|string|\WC_Tax
 */
function getWooEventValueCart( $valueOption, $global, $percent = 100 ) {


    if($valueOption == 'cog' && isPixelCogActive()) {
        $cog_value = getAvailableProductCogCart();
        if($cog_value !== '')
            return (float) round($cog_value, 2) ;

        if ( get_option( '_pixel_cog_tax_calculating')  == 'no' ) {
            return WC()->cart->cart_contents_total;
        }

        return WC()->cart->cart_contents_total + WC()->cart->tax_total;
    }


    if ( PYS()->getOption( 'woo_event_value' ) == 'custom' ) {
        $amount = getWooCartTotal();
    } else {
        $amount = $params['value'] = WC()->cart->subtotal;
    }

    switch ( $valueOption ) {
        case 'global':
            $value = (float) $global;
            break;

        case 'percent':
            $percents = (float) $percent;
            $percents = str_replace( '%', null, $percents );
            $percents = (float) $percents / 100;
            $value    = (float) $amount * $percents;
            break;

        default:    // "price" option
            $value = (float) $amount;
    }

    return formatPriceTrimZeros($value);
}

function getWooUserStat($orderId = 0) {
    global $wpdb;

    $customerId = getCustomerIdFromOrder($orderId);
    $customerEmail = getCustomerEmailFromOrder($orderId);

    return getUserPurchaseData($customerId, $customerEmail);
}

function getCustomerEmailFromOrder($orderId)
{
    global $wpdb;
    $orderTable = isWooUseHPStorage() ? $wpdb->prefix . "wc_orders" : $wpdb->postmeta;
    $selectClause = isWooUseHPStorage() ? "SELECT billing_email FROM $orderTable WHERE id = %d" : "SELECT meta_value FROM $orderTable WHERE post_id = %d AND meta_key = '_billing_email'";

    return $wpdb->get_var($wpdb->prepare($selectClause, $orderId));
}
function getCustomerIdFromOrder($orderId) {
    global $wpdb;
    $orderTable = isWooUseHPStorage() ? $wpdb->prefix . "wc_orders" : $wpdb->postmeta;
    $selectClause = isWooUseHPStorage() ? "SELECT customer_id FROM $orderTable WHERE id = %d" : "SELECT meta_value FROM $orderTable WHERE post_id = %d AND meta_key = '_customer_user'";

    return $wpdb->get_var($wpdb->prepare($selectClause, $orderId));
}
function getUserPurchaseData($userId = null, $userEmail = null) {
    global $wpdb;
    if(!$userId && !$userEmail) return false;
    if (isWooUseHPStorage()) {
        $orderTable = $wpdb->prefix . "wc_orders";
        $query = $wpdb->prepare("SELECT SUM(total_amount) AS ltv, AVG(total_amount) AS avg_order_value, COUNT(total_amount) AS orders_count FROM $orderTable WHERE (customer_id != 0 AND customer_id = %d) OR billing_email = %s AND status IN ('wc-processing', 'wc-completed')", $userId, $userEmail);
        $result = $wpdb->get_row($query);
        if (!$result) return ['orders_count' => 0, 'avg_order_value' => 0, 'ltv' => 0];
        return ['orders_count' => (int) $result->orders_count, 'avg_order_value' => round((float) $result->avg_order_value, 2), 'ltv' => round((float) $result->ltv, 2)];
    } else {
        return getLegacyUserPurchaseData($userId, $userEmail);
    }

    return false;
}
function getLegacyUserPurchaseData($userId, $userEmail) {
    global $wpdb;
    if (!$userId && !$userEmail) return false;
    $orderTable = $wpdb->prefix . "posts";
    $metaTable  = $wpdb->prefix . "postmeta";

    $query = $wpdb->prepare("
        SELECT DISTINCT o.ID
        FROM $orderTable o
        JOIN $metaTable pm ON pm.post_id = o.ID
        WHERE 
            (
                (pm.meta_key = '_customer_user' AND pm.meta_value = %d AND pm.meta_value != 0)
                OR 
                (pm.meta_key = '_billing_email' AND pm.meta_value = %s AND pm.meta_value != '')
            )
            AND o.post_type = 'shop_order'
            AND o.post_status IN ('wc-processing', 'wc-completed')
    ", $userId, $userEmail);

    $order_ids = $wpdb->get_col($query);
    if (!$order_ids) {
        return false;
    }

    // Fetch orders in bulk using wc_get_orders (to avoid querying SQL for each order)
    $orders = wc_get_orders([
        'post__in' => $order_ids,                 // instead of include
        'orderby'  => 'post__in',                 // sort by the list
        'posts_per_page' => count($order_ids),    // instead of limit
        'status'   => ['wc-processing', 'wc-completed'],
        'return'   => 'objects',
    ]);
    $total_sum   = 0.0;
    $order_count = count($orders);

    // Check if there are any orders to avoid division by zero
    if ($order_count === 0) {
        return false;
    }

    foreach ($orders as $order) {
        $total_sum += (float) $order->get_total();
    }

    return [
        'orders_count'    => $order_count,
        'avg_order_value' => round($total_sum / $order_count, 2),
        'ltv'             => round($total_sum, 2),
    ];
}
function getWooCustomerTotals($user_id = 0, $order_id = null) {
    global $wpdb;

    $customerEmail = $order_id !== null ? getCustomerEmailFromOrder($order_id) : null;
    $user_id = $order_id !== null ? getCustomerIdFromOrder($order_id) : ($user_id == 0 && is_user_logged_in() ? get_current_user_id() : $user_id);

    if (empty($order_id) && empty($user_id)) {
        return false;
    }

    return getUserPurchaseData($user_id, $customerEmail);
}
function wooMapOrderId($orderId) {
    return PYS()->getOption("woo_order_id_prefix").$orderId;
}


function getWooCustomAudiencesFromOldTable($order_statues) {
    global $wpdb;
    $csv_data = [];
    $order_statues_placeholders = implode( ', ', array_fill( 0, count( $order_statues ), '%s' ) );
    // collect all unique customers by email
    $query = $wpdb->prepare( "
        SELECT  postmeta.meta_value AS email, postmeta.post_id
        FROM    $wpdb->postmeta AS postmeta
        WHERE   postmeta.meta_key = '_billing_email'
                AND postmeta.post_id IN (
                    SELECT ID FROM $wpdb->posts 
                    WHERE post_type = 'shop_order'
                    AND post_status IN ({$order_statues_placeholders})
                )
    ", $order_statues );

    $results = $wpdb->get_results( $query );

    $customers = array();

    // format data as email => [ order_ids ]
    foreach ( $results as $row ) {

        $order_ids   = isset( $customers[ $row->email ] ) ? $customers[ $row->email ] : array();
        $order_ids[] = (int) $row->post_id;

        $customers[ $row->email ] = $order_ids;

    }

    @ini_set( 'max_execution_time', 180 );

    // collect data per each customer
    foreach ( $customers as $email => $order_ids ) {

        $order_ids_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

        // calculate customer LTV
        $query = $wpdb->prepare( "
            SELECT  SUM( meta_value )
            FROM    $wpdb->postmeta
            WHERE   post_id IN ( {$order_ids_placeholders} )
                    AND meta_key = '_order_total'
        ", $order_ids );

        $customer_ltv = $wpdb->get_col( $query );

        // query customer data from last order
        $query = $wpdb->prepare( "
            SELECT  meta_key, meta_value
            FROM    $wpdb->postmeta
            WHERE   post_id = %d
                    AND meta_key IN ( '_billing_first_name', '_billing_last_name', '_billing_city', '_billing_state',
                    '_billing_postcode', '_billing_country', '_billing_phone' )
        ", end( $order_ids ) );

        $results = $wpdb->get_results( $query );

        $customer_meta          = wp_list_pluck( $results, 'meta_value', 'meta_key' );
        $customer_meta['ltv']   = (float) $customer_ltv[0];
        $customer_meta['email'] = $email;

        $csv_data[] = $customer_meta;

    }

    return $csv_data;

}

function getWooCustomAudiencesFromHP($order_statues) {
    global $wpdb;
    $csv_data = [];
    $orderTable = $wpdb->prefix."wc_orders";
    $addressTable = $wpdb->prefix."wc_order_addresses";
    $order_statues_placeholders = implode( ', ', array_fill( 0, count( $order_statues ), '%s' ) );
    // collect all unique customers by email
    $query = $wpdb->prepare( "
        SELECT  t1.billing_email as email,
                SUM(t1.total_amount) as ltv, 
                t3.first_name as _billing_first_name,
                t3.last_name as _billing_last_name, 
                t3.city as _billing_city,
                t3.state as _billing_state,
                t3.postcode as _billing_postcode,
                t3.country as _billing_country,
                t3.phone as _billing_phone 
        FROM $orderTable as t1 
            LEFT JOIN (SELECT billing_email, MAX(id) last_id FROM $orderTable GROUP BY billing_email) t2 
                ON t1.billing_email = t2.billing_email 
            LEFT JOIN $addressTable as t3 
                ON t3.order_id = t2.last_id AND t3.address_type = 'billing' 
        WHERE   status IN ({$order_statues_placeholders})
        GROUP BY t1.billing_email
        
    ", $order_statues );

    return $wpdb->get_results( $query,ARRAY_A  );
}

function wooExportCustomAudiences() {
    global $wpdb;

    ob_clean();

    $order_statues = PYS()->getOption( 'woo_ltv_order_statuses', array() );

    if ( empty( $order_statues ) ) {
        $order_statues = array_keys( wc_get_order_statuses() );
    }
    if(isWooUseHPStorage()) {
        $csv_data = getWooCustomAudiencesFromHP($order_statues);
    } else {
        $csv_data = getWooCustomAudiencesFromOldTable($order_statues);
    }




    // generate file name
    $site_name = site_url();
    $site_name = str_replace( array( 'http://', 'https://' ), '', $site_name );
    $site_name = strtolower( preg_replace( "/[^A-Za-z]/", '_', $site_name ) );
    $file_name = strftime( '%Y%m%d' ) . '_' . $site_name . '_woo_customers.csv';

    // output CSV
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $file_name );

    $output = fopen( 'php://output', 'w' );

    // headings
    fputcsv( $output, array( 'email', 'phone', 'fn', 'ln', 'ct', 'st', 'country', 'zip', 'value' ) );

    // rows
    foreach ( $csv_data as $row ) {

        fputcsv( $output, array(
            $row['email'],
            isset( $row['_billing_phone'] ) ? $row['_billing_phone'] : '',
            isset( $row['_billing_first_name'] ) ? $row['_billing_first_name'] : '',
            isset( $row['_billing_last_name'] ) ? $row['_billing_last_name'] : '',
            isset( $row['_billing_city'] ) ? $row['_billing_city'] : '',
            isset( $row['_billing_state'] ) ? $row['_billing_state'] : '',
            isset( $row['_billing_country'] ) ? $row['_billing_country'] : '',
            isset( $row['_billing_postcode'] ) ? $row['_billing_postcode'] : '',
            $row['ltv']
        ) );

    }

    exit;

}

function wooGetOrderIdFromRequest() {
    global $wp;
    if(isset( $_REQUEST['key'] )  && $_REQUEST['key'] != "") {
        $order_key = sanitize_key($_REQUEST['key']);

        $cache_key = 'order_id_' . $order_key;
        $order_id = get_transient( $cache_key );
        if (PYS()->woo_is_order_received_page() && empty($order_id) && !empty($wp->query_vars['order-received'])) {
            $order_id = absint( $wp->query_vars['order-received'] );
            if ($order_id) {
                set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
            }
        }
        if ( empty($order_id) ) {
            $order_id = (int) wc_get_order_id_by_order_key( $order_key );
            set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
        }
        return $order_id;
    }
    if(PYS()->woo_is_order_received_page() && (isset( $_REQUEST['orderId'] ) || isset( $_REQUEST['order'] ))){
        $order_id = $_REQUEST['orderId'] ?? $_REQUEST['order'];
        if ( empty($order_id) ) {
            $order_id = absint( $wp->query_vars['order-received'] );
        }
        return $order_id;
    }
    if(PYS()->woo_is_order_received_page() && !empty($wp->query_vars['order-received'])){
        $cache_key = 'order_id_' . $wp->query_vars['order-received'];
        $order_id = get_transient( $cache_key );
        if (empty($order_id)) {
            $order_id = absint( $wp->query_vars['order-received'] );
            if ($order_id) {
                set_transient( $cache_key, $order_id, HOUR_IN_SECONDS );
            }
        }
        return $order_id;
    }
    if(isset( $_REQUEST['referenceCode'] )  && $_REQUEST['referenceCode'] != "") {
        return (int)$_REQUEST['referenceCode'];
    }
    if(isset( $_REQUEST['ref_venta'] )  && $_REQUEST['ref_venta'] != "") {
        return (int)$_REQUEST['ref_venta'];
    }
    if(!empty($_REQUEST['wcf-order'])) {
        return (int)$_REQUEST['wcf-order'];
    }
    return -1;
}
function wooIsRequestContainOrderId() {
    global $wp;
    return  isset( $_REQUEST['key'] )  && $_REQUEST['key'] != ""
        || !empty($wp->query_vars['order-received'])
        || (PYS()->woo_is_order_received_page() && (isset( $_REQUEST['orderId'] ) || isset( $_REQUEST['order'] )))
        || isset( $_REQUEST['referenceCode'] )  && $_REQUEST['referenceCode'] != ""
        || isset( $_REQUEST['ref_venta'] )  && $_REQUEST['ref_venta'] != ""
        || !empty( $_REQUEST['wcf-order'] );
}

/**
 * @param \WC_Product $product
 * @return array
 */
function pys_woo_get_product_data($product,$args) {

    if ( $product->get_type() == 'variation' ) {
        $parent_id = $product->get_parent_id(); // get terms from parent
        $tags = getObjectTerms( 'product_tag', $parent_id );
        $categories = getObjectTermsWithId( 'product_cat', $parent_id );
    } else {
        $tags = getObjectTerms( 'product_tag', $product->get_id() );
        $categories = getObjectTermsWithId( 'product_cat', $product->get_id() );
    }



    $product_price = $product->get_price();
    // for cartflow product sale
    $sale_price = getWfcProductSalePrice($product,$args);
    if($sale_price > -1) {
        $product_price = $sale_price;
    }

    $product_type = $product->get_type();

    $isGrouped = $product_type == "grouped";
    $child = [];
    if($isGrouped) {
        $product_ids = $product->get_children();
        foreach ($product_ids as $id) {
            $child = wc_get_product($id);
            if(!$child) continue;
            if($child->get_type() == "variable") { // skip  variable products in grouped product
                continue;
            }
            $child[] = [
                'id' => $id,
                'quantity' => 1
            ];
        }
    }
    $variation_attr = null;
    if($product_type == 'variation') {
        $variation_attr = $product->get_variation_attributes();
    }

    return [
        'id'            => $product->get_id(),
        'name'          => $product->get_name(),
        'tags'          => $tags,
        'categories'    => $categories,
        'price'         => $product_price,
        'type'          => $product_type,
        'child'         => $child,
        'is_external'   => false,
        'quantity'      => $args['quantity'],
        'variation_attr'=> $variation_attr
    ];
}

function pys_woo_get_cog_product_value($product,$quantity,$price) {
    $args = array( 'qty'   => $quantity, 'price' => $price);
    if(get_option( '_pixel_cog_tax_calculating')  == 'no') {
        $amount = wc_get_price_excluding_tax($product, $args);
    } else {
        $amount = wc_get_price_including_tax($product,$args);
    }

    $cog = getAvailableProductCog($product);

    if ($cog['val']) {
        if ($cog['type'] == 'fix') {
            $value = (float)$amount - (float)$cog['val'];
        } else {
            $value = (float)$amount - ((float)$amount * (float)$cog['val'] / 100);
        }
    } else {
        $value = (float)$amount;
    }
    return formatPriceTrimZeros($value);
}

/**
 * Check is Woo Supporting High-Performance Order Storage
 * @return bool
 */
function isWooUseHPStorage() {
    if(class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )) {
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
    return false;

}
function getBrandForWooItem($item_id) {
    if (!PYS()->getOption('enable_woo_brand')) {
        return false;
    }

    $brand_names = [];

    // Check plugins
    $plugin_taxonomies = [
	    'PYS_BRAND_PYS_PCF' => 'wpfoof-brand',
	    'PYS_BRAND_PBFW'    => 'product_brand',
	    'PYS_BRAND_WB'      => 'product_brand',
	    'PYS_BRAND_YWBA'    => '_yoast_wpseo_primary_yith_product_brand',
	    'PYS_BRAND_PEWB'    => 'pwb-brand',
	    'PYS_BRAND_PRWB'    => 'product_brand'
    ];

    foreach ($plugin_taxonomies as $plugin_const_name => $taxonomy) {
        if (defined($plugin_const_name) &&
            is_plugin_active(constant($plugin_const_name)) &&
            in_array( $plugin_const_name, PYS()->getOption( 'woo_brand_taxonomy_plugin' ), true ) ) {
            if (in_array($taxonomy, ['wpfoof-brand', '_yoast_wpseo_primary_yith_product_brand'])) {
                $result = get_post_meta($item_id, $taxonomy, true);
                if (!empty($result)) {
                    $brand_names[] = $result;
                }
            } else {
                $terms = wp_get_object_terms($item_id, $taxonomy);
                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if (is_object($term) && property_exists($term, 'name')) {
                            $brand_names[] = $term->name;
                        }
                    }
                }
            }
            if (!empty($brand_names)) {
                return implode('/', $brand_names);
            }
        }
    }

    // Check custom taxonomy
    if (!empty(PYS()->getOption('woo_brand_taxonomy'))) {
        $terms = get_the_terms($item_id, PYS()->getOption('woo_brand_taxonomy'));
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (is_object($term) && property_exists($term, 'name')) {
                    $brand_names[] = $term->name;
                }
            }
        }
        if (!empty($brand_names)) {
            return implode('/', $brand_names);
        }
    }

    // Autodetect brand taxonomy
    /*if (in_array('autodetect', PYS()->getOption('woo_brand_taxonomy_plugin'))) {
        $registered_taxonomies = get_taxonomies();
        foreach ($registered_taxonomies as $taxonomy) {
            $taxonomy_object = get_taxonomy($taxonomy);
            if (strpos($taxonomy_object->name, 'brand') !== false) {
                $terms = get_the_terms($item_id, $taxonomy);
                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if (is_object($term) && property_exists($term, 'name')) {
                            $brand_names[] = $term->name;
                        }
                    }
                }
                if (!empty($brand_names)) {
                    return implode('/', $brand_names);
                }
            }
        }
    }*/

    return false;
}

function getVariableIdByAttributes($product) {
    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        $attributes = $_GET;
        unset($attributes['product_id']);
        // Normalize keys by removing 'attribute_' prefix
        $normalized_attributes = [];
        foreach ($attributes as $key => $value) {
            if (strpos($key, 'attribute_') === 0) {
                $normalized_key = str_replace('attribute_', '', $key);
                $normalized_attributes[$normalized_key] = $value;
            }
        }

        // If no attributes are provided, use the default attributes
        if (empty($normalized_attributes)) {
            $normalized_attributes = $product->get_default_attributes();
        }

        if(empty($normalized_attributes)) {
            return null;
        }
        $matched_variation_id = null;

        foreach ($variations as $variation) {
            $matched = true;

            foreach ($variation['attributes'] as $key => $value) {
                $attribute_key = str_replace('attribute_', '', $key);

                // Check if the attribute is set to "Any" (empty value) or matches the normalized attribute
                if ($value === '' || (isset($normalized_attributes[$attribute_key]) && strtolower($value) === strtolower($normalized_attributes[$attribute_key]))) {
                    continue;
                } else {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                $matched_variation_id = $variation['variation_id'];
                break;
            }
        }

        if (!is_null($matched_variation_id)) {
            return $matched_variation_id;
        }
    }
    return null;
}