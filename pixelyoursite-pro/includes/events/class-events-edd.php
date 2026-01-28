<?php

namespace PixelYourSite;

class EventsEdd extends EventsFactory {
    private $events = array(
        'edd_frequent_shopper',
        'edd_vip_client',
        'edd_big_whale',
	    'edd_purchase',
        'edd_view_content',
        'edd_view_category',
        'edd_add_to_cart_on_checkout_page',
        'edd_remove_from_cart',
        'edd_initiate_checkout',
        'edd_add_to_cart_on_button_click'
    );

    private $isNewCustomer = null;

    private $eddCustomerTotals = array();
    private static $_instance;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    private function __construct() {
        add_filter("pys_event_factory",[$this,"register"]);
    }

    function register($list) {
        $list[] = $this;
        return $list;
    }

    static function getSlug() {
        return "edd";
    }

    function getEvents() {
        return $this->events;
    }

    function getCount()
    {
        $size = 0;
        if(!$this->isEnabled()) {
            return 0;
        }
        foreach ($this->events as $event) {
            if($this->isActive($event)){
                $size++;
            }
        }
        return $size;
    }

    function isEnabled()
    {
        return isEddActive();
    }

    function getOptions()
    {
        if($this->isEnabled()) {
            return array(
                'enabled'                       => true,
                'enabled_save_data_to_orders'  => PYS()->getOption('edd_enabled_save_data_to_orders'),
                'addToCartOnButtonEnabled'      => isEventEnabled( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ),
                'addToCartOnButtonValueEnabled' => PYS()->getOption( 'edd_add_to_cart_value_enabled' ),
                'addToCartOnButtonValueOption'  => PYS()->getOption( 'edd_add_to_cart_value_option' ),
                'edd_purchase_on_transaction'   => PYS()->getOption( 'edd_purchase_on_transaction' )
            );
        } else {
            return array(
                'enabled'                       => false
            );
        }
    }

    function isReadyForFire($event)
    {
        switch ($event) {
            case 'edd_add_to_cart_on_button_click': {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' );
            }
            case 'edd_purchase': {
                return $this->checkEddPurchase();
            }
            case 'edd_initiate_checkout': {
                return  PYS()->getOption( 'edd_initiate_checkout_enabled' ) && edd_is_checkout();
            }
            case 'edd_remove_from_cart': {
                return PYS()->getOption( 'edd_remove_from_cart_enabled') && edd_is_checkout();
            }
            case 'edd_add_to_cart_on_checkout_page' : {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_checkout_page' )
                    && edd_is_checkout();
            }
            case 'edd_view_category': {
                return PYS()->getOption( 'edd_view_category_enabled' ) && is_tax( 'download_category' );
            }
            case 'edd_view_content' : {
                return PYS()->getOption( 'edd_view_content_enabled' ) && is_singular( 'download' );
            }
            case 'edd_vip_client': {
                $customerTotals = $this->getEddCustomerTotals();
                if(edd_is_success_page() && PYS()->getOption( 'edd_vip_client_enabled' )) {
                    $orders_count = (int) PYS()->getOption( 'edd_vip_client_transactions' );
                    $avg = (int) PYS()->getOption( 'edd_vip_client_average_value' );
                    return $customerTotals['orders_count'] >= $orders_count && $customerTotals['avg_order_value'] >= $avg;
                }
                return false;
            }
            case 'edd_big_whale': {
                $customerTotals = $this->getEddCustomerTotals();
                if(edd_is_success_page() && PYS()->getOption( 'edd_big_whale_enabled' )) {
                    $ltv = (int) PYS()->getOption( 'edd_big_whale_ltv' );
                    return $customerTotals['ltv'] >= $ltv;
                }
                return false;
            }
            case 'edd_frequent_shopper': {
                $customerTotals = $this->getEddCustomerTotals();
                if(edd_is_success_page() && PYS()->getOption( 'edd_frequent_shopper_enabled' )) {
                    $orders_count = (int) PYS()->getOption( 'edd_frequent_shopper_transactions' );
                    return $customerTotals['orders_count'] >= $orders_count;
                }
                return false;
            }

        }
        return false;
    }

    function getEvent($eventId, $isFilterEvent = false)
    {
        switch ($eventId) {

            case 'edd_view_category': {
                $event = new SingleEvent($eventId, EventTypes::$STATIC, self::getSlug());
                return $event;
            }
            case 'edd_view_content': {
                global  $post;
                $event = new SingleEvent($eventId, EventTypes::$STATIC, self::getSlug());
                $event->args = ['products' => [$this->getEddProductParams($post->ID)]] ;
                return $event;
        }

            case 'edd_remove_from_cart': {
                return $this->getRemoveFromCartEvents($eventId);
            }
            case 'edd_add_to_cart_on_button_click': {

                return new SingleEvent($eventId,EventTypes::$DYNAMIC,self::getSlug());
            }
            case 'edd_add_to_cart_on_checkout_page':
            case 'edd_initiate_checkout': {
                $event = new SingleEvent($eventId,EventTypes::$STATIC,self::getSlug());
                $event->args = ['products' => $this->getEddCartProducts()] ;
                return $event;
            }
            case 'edd_vip_client':
            case 'edd_big_whale':
            case 'edd_frequent_shopper': {
                $order_id =  $this->getEddOrderId();
                if(!$order_id) return null;
		        if ( PYS()->getOption( 'edd_purchase_on_transaction' ) &&
		             edd_get_payment_meta( $order_id, '_pys_purchase_event_fired', true )) {
			        return null; // skip woo_purchase if this transaction was fired
		        }
                $event = new SingleEvent($eventId,EventTypes::$STATIC,self::getSlug());
                $event->args = ['products' => $this->getEddCheckOutProducts($order_id)] ;
                return $event;
            }
            case 'edd_purchase': {
                $order_id = $this->getEddOrderId();

                $this->setNewCustomer($order_id);
                if ( PYS()->getOption( 'edd_purchase_on_transaction' ) &&
                    edd_get_payment_meta( $order_id, '_pys_purchase_event_fired', true )  && !$isFilterEvent  ) {
                    return null; // skip woo_purchase if this transaction was fired
                }
	            if ( !$isFilterEvent) {
		            edd_update_payment_meta( $order_id, '_pys_purchase_event_fired', true );
	            }
                return $this->getPurchaseEvent($eventId,$order_id);
            }
            case 'edd_refund': {
                $order_id = $this->getEddOrderId();
	            edd_update_payment_meta( $order_id, '_pys_purchase_event_fired', false );
                return $this->getRefundEvent($eventId,$order_id);
            }
        }
    }

    private function isActive($event)
    {
        switch ($event) {
            case 'edd_add_to_cart_on_button_click': {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' );
            }
            case 'edd_purchase': {
                return PYS()->getOption( 'edd_purchase_enabled' );
            }
            case 'edd_initiate_checkout': {
                return  PYS()->getOption( 'edd_initiate_checkout_enabled' ) ;
            }
            case 'edd_remove_from_cart': {
                return PYS()->getOption( 'edd_remove_from_cart_enabled');
            }
            case 'edd_add_to_cart_on_checkout_page' : {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_checkout_page' );
            }
            case 'edd_view_category': {
                return PYS()->getOption( 'edd_view_category_enabled' ) ;
            }
            case 'edd_view_content' : {
                return PYS()->getOption( 'edd_view_content_enabled' ) ;
            }
            case 'edd_vip_client': {
                return PYS()->getOption( 'edd_vip_client_enabled' );
            }
            case 'edd_big_whale': {
                return PYS()->getOption( 'edd_big_whale_enabled' );
            }
            case 'edd_frequent_shopper': {
                return PYS()->getOption( 'edd_frequent_shopper_enabled' );
            }
        }
        return false;
    }

    private function getRemoveFromCartEvents($eventId) {
        $events = [];


        foreach (edd_get_cart_contents() as $cart_item_key => $cart_item) {
            $event = new SingleEvent($eventId,EventTypes::$DYNAMIC,self::getSlug());
            $event->args = ['key'=>$cart_item_key,'item'=>$cart_item];
            $events[]=$event;
        }
        return $events;
    }

    public function getEddCustomerTotals($order_id = null) {
        // setup and cache params

        if ( empty( $this->eddCustomerTotals ) ) {
            $this->eddCustomerTotals = getEddCustomerTotals(0,$order_id);
        }
        return $this->eddCustomerTotals;
    }

    private function checkEddPurchase() {
        if(PYS()->getOption( 'edd_purchase_enabled' ) && edd_is_success_page()) {
            /**
             * When a payment gateway used, user lands to Payment Confirmation page first, which does automatic
             * redirect to Purchase Confirmation page. We filter Payment Confirmation to avoid double Purchase event.
             */
            if ( isset( $_GET['payment-confirmation'] ) ) {
                //@fixme: some users will not reach success page and event will not be fired
                //return;
            }
            $order_id = $this->getEddOrderId();
            $status = edd_get_payment_status( $order_id );

            // pending payment status used because we can't fire event on IPN
            if ( strtolower( $status ) != 'publish' && strtolower( $status ) != 'pending' &&  strtolower( $status ) != 'complete' ) {
                return false;
            }

            return true;
        }
        return false;
    }

    function getEddOrderId() {
        $payment_key = getEddPaymentKey();
        $order_id = (int) edd_get_purchase_id_by_key( $payment_key );
        return (int)apply_filters("pys_edd_checkout_order_id",$order_id);
    }

    function getEddProductParams($productId, $quantity = 1) {
        $post = get_post(  $productId );
        $tags = getObjectTerms( 'download_tag', $productId );
        $categories = getObjectTermsWithId( 'download_category', $productId );
        $data = [
            'product_id'    => $productId,
            'name'          => $post->post_title,
            'tags'          => $tags,
            'categories'    => $categories,
            'quantity'      => $quantity,
            'price_index'   => null
        ];

        return $data;
    }

    function getEddCartProducts() {
        $products = [];
        foreach (edd_get_cart_contents() as $cart_item_key => $cart_item) {
            $productId = (int) $cart_item['id'];
            $post = get_post(  $productId );
            $tags = getObjectTerms( 'download_tag', $productId );
            $categories = getObjectTermsWithId( 'download_category', $productId );

            if ( ! empty( $cart_item['options'] ) &&  !empty($cart_item['options']['price_id']) ) {
                $price_index = $cart_item['options']['price_id'];
            } else {
                $price_index = null;
            }

            $products[] = [
                'cart_item_key' => $cart_item_key,
                'product_id'    => $productId,
                'name'          => $post->post_title,
                'tags'          => $tags,
                'categories'    => $categories,
                'quantity'      => $cart_item['quantity'],
                'price_index'   => $price_index
            ];
        }
        return $products;
    }

    function getPurchaseEvent($eventId,$order_id) {

        if(!$order_id) return null;

        $payment = new \EDD_Payment($order_id);

        if(!$payment) return null;

        $event = new SingleEvent($eventId,EventTypes::$STATIC,self::getSlug());
        $event->addPayload(['edd_order'=>$order_id]);
        $args = [
            'products' => $this->getEddPurchaseProducts($payment),
            'order_id'=>$order_id,
        ];
        $allFee = $payment->get_fees();
        $feeAmount = 0;
        foreach ( $allFee as $fee) {
            $feeAmount += $fee['amount'];
        }
        $payment->decrease_tax();

        $args['fee'] = $feeAmount;
        $args['fee_tax'] =  round( edd_calculate_tax($feeAmount), edd_currency_decimal_filter() );

        $user = edd_get_payment_meta_user_info( $order_id );
        // coupons
        $coupons = isset( $user['discount'] ) && $user['discount'] != 'none' ? $user['discount'] : null;

        if ( ! empty( $coupons ) ) {
            $coupons = explode( ', ', $coupons );
            $args['coupon'] = $coupons[0];
        } else {
            $args['coupon'] = '';
        }
        if(!is_null($this->isNewCustomer)) {
            $args['new_customer'] = $this->isNewCustomer;
        }

        if (PYS()->getOption('enable_edd_payment_method')){
            $gateway = edd_get_payment_gateway($payment->_ID);
            $args['payment_method'] = $gateway ? edd_get_gateway_admin_label($gateway) : '';
        }
        $event->args = $args;
        return $event;
    }
    function getRefundEvent($eventId,$order_id) {

        if(!$order_id) return null;
        $payment = new \EDD_Payment($order_id);

        if(!$payment) return null;

        $event = new SingleEvent($eventId,EventTypes::$STATIC,self::getSlug());
        $event->addPayload(['edd_order'=>$order_id]);
        $args = [
            'products' => $this->getEddPurchaseProducts($payment),
            'order_id'=>$order_id,
        ];
        $allFee = $payment->get_fees();
        $feeAmount = 0;
        foreach ( $allFee as $fee) {
            $feeAmount += $fee['amount'];
        }
        $payment->decrease_tax();

        $args['fee'] = $feeAmount;
        $args['fee_tax'] =  round( edd_calculate_tax($feeAmount), edd_currency_decimal_filter() );

        $user = edd_get_payment_meta_user_info( $order_id );

        $event->args = $args;

        return $event;
    }
    /**
     * @param EDD_Payment $payment
     * @return array
     */
    function getEddPurchaseProducts($payment) {
        $products = [];


        $cart_details = $payment->cart_details;

        foreach ($cart_details as $cart_item_key => $cart_item) {
            $productId = (int) $cart_item['id'];
            $post = get_post(  $productId );
            $tags = getObjectTerms( 'download_tag', $productId );
            $categories = getObjectTermsWithId( 'download_category', $productId );

            $options = $cart_item['item_number']['options'];
            if ( ! empty( $options ) && $options !== 0 ) {
                $price_index = $options['price_id'];
            } else {
                $price_index = null;
            }

            $products[] = [
                'cart_item_key' => $cart_item_key,
                'product_id' => $productId,
                'name'  => $post->post_title,
                'tags'          => $tags,
                'categories'    => $categories,
                'quantity'  => $cart_item['quantity'],
                'subtotal'  =>  $cart_item['subtotal'],
                'tax'  => $cart_item['tax'] ,
                'discount'  => $cart_item['discount'],
                'price'  => $cart_item['price'],
                'price_index'=>$price_index
            ];
        }
        return $products;
    }

    function getEddCheckOutProducts($orderId) {
        $products = [];
        $cart = edd_get_payment_meta_cart_details($orderId, true );
        foreach ($cart as $cart_item_key => $cart_item) {
            $productId = (int) $cart_item['id'];
            $post = get_post(  $productId );
            $tags = getObjectTerms( 'download_tag', $productId );
            $categories = getObjectTermsWithId( 'download_category', $productId );

            $options = $cart_item['item_number']['options'];
            if ( ! empty( $options ) && $options !== 0 ) {
                $price_index = $options['price_id'];
            } else {
                $price_index = null;
            }

            $products[] = [
                'cart_item_key' => $cart_item_key,
                'product_id' => $productId,
                'name'  => $post->post_title,
                'tags'          => $tags,
                'categories'    => $categories,
                'quantity'  => $cart_item['quantity'],
                'subtotal'  =>  $cart_item['subtotal'],
                'tax'  => $cart_item['tax'] ,
                'discount'  => $cart_item['discount'],
                'price'  => $cart_item['price'],
                'price_index'=>$price_index
            ];
        }
        return $products;
    }

    /**
     * @param SingleEvent $event
     * @param $filter
     */
    static function filterEventProductsBy($event,$filters,$pixel) {
        $products = [];

        foreach ($event->args['products'] as $productData) {
            $includeProduct = ($pixel->logicConditionalTrack === 'track'); // Initially include for 'track', exclude for 'dont_track'
            foreach ($filters as $filter) {
                if ($filter == 'in_download_category') {
                    $ids = array_column($productData['categories'], 'id');
                    if ($pixel->logicConditionalTrack == 'track') {
                        if (in_array($filter['sub_id'], $ids)) {
                            $includeProduct = true; // Product matches
                            break; // Stop checking
                        } else {
                            $includeProduct = false; // Does not match the filter
                        }
                    } elseif ($pixel->logicConditionalTrack == 'dont_track') {
                        if (in_array($filter['sub_id'], $ids)) {
                            $includeProduct = false; // The product should be excluded
                            break; // Stop checking
                        } else {
                            $includeProduct = true; // The product remains on the list
                        }
                    }
                } elseif ($filter == 'in_download_tag') {
                    if ($pixel->logicConditionalTrack == 'track') {
                        if (isset($productData['tags'][$filter['sub_id']])) {
                            $includeProduct = true;
                            break;
                        } else {
                            $includeProduct = false;
                        }
                    } elseif ($pixel->logicConditionalTrack == 'dont_track') {
                        if (isset($productData['tags'][$filter['sub_id']])) {
                            $includeProduct = false;
                            break;
                        } else {
                            $includeProduct = true;
                        }
                    }
                } else {
                    if ($pixel->logicConditionalTrack == 'track') {
                        if ($productData['product_id'] == $filter['sub_id']) {
                            $includeProduct = true;
                            break;
                        } else {
                            $includeProduct = false;
                        }
                    } elseif ($pixel->logicConditionalTrack == 'dont_track') {
                        if ($productData['product_id'] == $filter['sub_id']) {
                            $includeProduct = false;
                            break;
                        } else {
                            $includeProduct = true;
                        }
                    }
                }
            }
            if ($includeProduct) {
                $products[] = $productData; // We add a product only if it passes all filters
            }
        }
        return $products;
    }
    public function setNewCustomer($order_id)
    {
        if(!is_null($this->isNewCustomer)) return;
            $payment_id = $order_id;
            $payment = edd_get_payment($payment_id);

            $exclude_payment_id = $payment_id;
            $start_date = strtotime('540 days ago');
            $end_date = time();

            if (!empty($payment)) {
                $customer_id = $payment->customer_id;
                if ($customer_id && $customer_id != 0) {
                    // Retrieve the customer's orders (payments) from the last 540 days, excluding the current order
                    $args = array(
                        'output'        => 'payments', // Specify output as 'payments' for retrieving EDD payment objects
                        'customer'   => $customer_id, // Filter payments by customer ID
                        'post__not_in'  => array($exclude_payment_id), // Exclude the current payment ID
                        'start_date'        => date('Y-m-d H:i:s', $start_date), // Start date for the range (540 days ago)
                        'end_date'          => date('Y-m-d H:i:s', $end_date), // End date for the range (today)
                        'number'        => 1, // Retrieve only 1 payment to determine if there is at least one previous order
                    );

                    // Get the payments for the customer based on the specified criteria
                    $payments = edd_get_payments($args);

                    // Check if there are no payments found; if none, this is a new customer
                    $this->isNewCustomer = empty($payments);
                }
            }

    }

    public function getNewCustomer()
    {
        return $this->isNewCustomer;
    }
}

/**
 * @return EventsEdd
 */
function EventsEdd() {
    return EventsEdd::instance();
}

EventsEdd();