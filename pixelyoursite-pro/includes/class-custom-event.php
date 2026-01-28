<?php

namespace PixelYourSite;

use Behat\Transliterator\Transliterator;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * @property int    post_id
 * @property string title
 * @property bool   enabled
 * @property int    delay
 * @property array  triggers
 * @property array  url_filters
 * @property int    time_window
 * @property bool   enable_time_window
 * @property bool   facebook_enabled
 * @property string facebook_pixel_id
 * @property string facebook_event_type
 * @property string facebook_custom_event_type
 * @property bool   facebook_params_enabled
 * @property array  facebook_params
 * @property bool   facebook_track_single_woo_data
 * @property bool   facebook_track_cart_woo_data
 * @property array  facebook_custom_params
 * @property bool   pinterest_enabled
 * @property string pinterest_event_type
 * @property string pinterest_custom_event_type
 * @property bool   pinterest_params_enabled
 * @property bool   pinterest_track_single_woo_data
 * @property bool   pinterest_track_cart_woo_data
 * @property array  pinterest_custom_params
 * @property bool   ga_enabled
 * @property string ga_pixel_id
 * @property string ga_event_action
 * @property string ga_custom_event_action
 * @property array  ga_custom_params
 * @property array  ga_params
 * @property string ga_version
 * @property string ga_conversion_label
 * @property bool   ga_ads_enabled
 * @property string ga_ads_pixel_id
 * @property string ga_ads_event_action
 * @property string ga_ads_custom_event_action
 * @property array  ga_ads_custom_params
 * @property array  ga_ads_params
 * @property bool   ga_ads_track_single_woo_data
 * @property bool   ga_ads_track_cart_woo_data
 * @property string ga_ads_version
 * @property string ga_ads_conversion_label
 * @property string ga_ads_event_category
 * @property string ga_ads_event_label
 * @property bool   gtm_enabled
 * @property string gtm_pixel_id
 * @property string gtm_event_action
 * @property string gtm_custom_event_action
 * @property array  gtm_custom_params
 * @property array  gtm_params
 * @property bool   gtm_track_single_woo_data
 * @property bool   gtm_track_cart_woo_data
 * @property string gtm_version
 * @property string gtm_conversion_label
 * @property string gtm_event_category
 * @property string gtm_event_label
 * @property bool   gtm_automated_param
 * @property bool   gtm_remove_customTrigger
 * @property bool   gtm_use_custom_object_name
 * @property string gtm_custom_object_name
 * @property bool   google_ads_enabled
 * @property string google_ads_conversion_id
 * @property string google_ads_conversion_label
 * @property string google_ads_event_action
 * @property string google_ads_custom_event_action
 * @property string google_ads_event_category
 * @property string google_ads_event_label
 * @property string google_ads_event_value
 * @property array  google_ads_custom_params
 * @property bool   bing_enabled
 * @property string bing_event_action
 * @property string bing_event_category
 * @property string bing_event_label
 * @property string bing_event_value
 * @property bool   bing_track_single_woo_data
 * @property bool   bing_track_cart_woo_data
 * @property string bing_pixel_id
 * @property bool   tiktok_enabled
 * @property string tiktok_pixel_id
 * @property string tiktok_event_type
 * @property string tiktok_custom_event_type
 * @property bool   tiktok_params_enabled
 * @property array  tiktok_params
 * @property bool   tiktok_track_single_woo_data
 * @property bool   tiktok_track_cart_woo_data
 * @property array  tiktok_custom_params
 * @property bool    reddit_enabled
 * @property bool    reddit_track_single_woo_data
 * @property bool    reddit_track_cart_woo_data
 * @property string  reddit_pixel_id
 * @property string  reddit_event_type
 * @property bool    reddit_params_enabled
 */
class CustomEvent {

    private $post_id;

    private $title = 'Untitled';

    private $enabled = true;

    public static $currencies = array(
        'AUD' => 'Australian Dollar',
        'BRL' => 'Brazilian Real',
        'CAD' => 'Canadian Dollar',
        'CZK' => 'Czech Koruna',
        'DKK' => 'Danish Krone',
        'EUR' => 'Euro',
        'HKD' => 'Hong Kong Dollar',
        'HUF' => 'Hungarian Forint',
        'IDR' => 'Indonesian Rupiah',
        'ILS' => 'Israeli New Sheqel',
        'JPY' => 'Japanese Yen',
        'KRW' => 'Korean Won',
        'MYR' => 'Malaysian Ringgit',
        'MXN' => 'Mexican Peso',
        'NOK' => 'Norwegian Krone',
        'NZD' => 'New Zealand Dollar',
        'PHP' => 'Philippine Peso',
        'PLN' => 'Polish Zloty',
        'RON' => 'Romanian Leu',
        'GBP' => 'Pound Sterling',
        'SGD' => 'Singapore Dollar',
        'SEK' => 'Swedish Krona',
        'CHF' => 'Swiss Franc',
        'TWD' => 'Taiwan New Dollar',
        'THB' => 'Thai Baht',
        'TRY' => 'Turkish Lira',
        'USD' => 'U.S. Dollar',
        'ZAR' => 'South African Rands'
    );
    public static $tikTokEvents = [
        'AddPaymentInfo'        => [],
        'AddToCart'             => [
            ['type'=>'input','label'=>'content_type','name'=>'pys[event][tiktok_params][content_type]'],
            ['type'=>'input','label'=>'quantity','name'=>'pys[event][tiktok_params][quantity]'],
            ['type'=>'input','label'=>'content_id','name'=>'pys[event][tiktok_params][content_id]'],
            ['type'=>'input','label'=>'value','name'=>'pys[event][tiktok_params][value]'],
            ['type'=>'input','label'=>'currency','name'=>'pys[event][tiktok_params][currency]'],
        ],
        'AddToWishlist'         => [],
        'ApplicationApproval'   => [],
        'CompleteRegistration'  => [],
        'ClickButton'           => [],
        'CompletePayment'       => [
            ['type'=>'input','label'=>'content_type','name'=>'pys[event][tiktok_params][content_type]'],
            ['type'=>'input','label'=>'quantity','name'=>'pys[event][tiktok_params][quantity]'],
            ['type'=>'input','label'=>'content_id','name'=>'pys[event][tiktok_params][content_id]'],
            ['type'=>'input','label'=>'value','name'=>'pys[event][tiktok_params][value]'],
            ['type'=>'input','label'=>'currency','name'=>'pys[event][tiktok_params][currency]'],
        ],
        'Contact'               => [],
        'CustomizeProduct'      => [],
        'CustomEvent'           => [],
        'Download'              => [],
        'FindLocation'          => [],
        'InitiateCheckout'      => [],
        'Purchase'              => [
            ['type'=>'input','label'=>'content_type','name'=>'pys[event][tiktok_params][content_type]'],
            ['type'=>'input','label'=>'quantity','name'=>'pys[event][tiktok_params][quantity]'],
            ['type'=>'input','label'=>'content_id','name'=>'pys[event][tiktok_params][content_id]'],
            ['type'=>'input','label'=>'value','name'=>'pys[event][tiktok_params][value]'],
            ['type'=>'input','label'=>'currency','name'=>'pys[event][tiktok_params][currency]'],
        ],
        'PlaceAnOrder'          => [
            ['type'=>'input','label'=>'content_type','name'=>'pys[event][tiktok_params][content_type]'],
            ['type'=>'input','label'=>'quantity','name'=>'pys[event][tiktok_params][quantity]'],
            ['type'=>'input','label'=>'content_id','name'=>'pys[event][tiktok_params][content_id]'],
            ['type'=>'input','label'=>'value','name'=>'pys[event][tiktok_params][value]'],
            ['type'=>'input','label'=>'currency','name'=>'pys[event][tiktok_params][currency]'],
        ],
        'Schedule'              => [],
        'Search'                => [
            ['type'=>'input','label'=>'search_string','name'=>'pys[event][tiktok_params][search_string]']
        ],
        'StartTrial'            => [
            ['type'=>'input','label'=>'content_ids','name'=>'pys[event][tiktok_params][content_ids]'],
            ['type'=>'input','label'=>'value','name'=>'pys[event][tiktok_params][value]'],
            ['type'=>'input','label'=>'currency','name'=>'pys[event][tiktok_params][currency]'],
        ],
        'SubmitApplication'     => [],
        'SubmitForm'            => [],
        'Subscribe'             => [],
        'ViewContent'           => [
            ['type'=>'input','label'=>'content_type','name'=>'pys[event][tiktok_params][content_type]'],
            ['type'=>'input','label'=>'quantity','name'=>'pys[event][tiktok_params][quantity]'],
            ['type'=>'input','label'=>'content_id','name'=>'pys[event][tiktok_params][content_id]'],
            ['type'=>'input','label'=>'value','name'=>'pys[event][tiktok_params][value]'],
            ['type'=>'input','label'=>'currency','name'=>'pys[event][tiktok_params][currency]'],
        ],
    ];

	//TODO check event names
	public static $redditEvents = [
		'ViewContent'   => [],
		'Search'        => [],
		'AddToCart'     => [
			[ 'type' => 'input', 'label' => 'itemCount', 'name' => 'pys[event][reddit_params][itemCount]' ],
			[ 'type' => 'input', 'label' => 'value', 'name' => 'pys[event][reddit_params][value]' ],
			[ 'type' => 'input', 'label' => 'currency', 'name' => 'pys[event][reddit_params][currency]' ],
		],
		'AddToWishlist' => [
			[ 'type' => 'input', 'label' => 'itemCount', 'name' => 'pys[event][reddit_params][itemCount]' ],
			[ 'type' => 'input', 'label' => 'value', 'name' => 'pys[event][reddit_params][value]' ],
			[ 'type' => 'input', 'label' => 'currency', 'name' => 'pys[event][reddit_params][currency]' ],
		],
		'Purchase'      => [
			[ 'type' => 'input', 'label' => 'itemCount', 'name' => 'pys[event][reddit_params][itemCount]' ],
			[ 'type' => 'input', 'label' => 'value', 'name' => 'pys[event][reddit_params][value]' ],
			[ 'type' => 'input', 'label' => 'currency', 'name' => 'pys[event][reddit_params][currency]' ],
		],
		'Lead'          => [
			[ 'type' => 'input', 'label' => 'value', 'name' => 'pys[event][reddit_params][value]' ],
			[ 'type' => 'input', 'label' => 'currency', 'name' => 'pys[event][reddit_params][currency]' ],
		],
		'SignUp'        => [
			[ 'type' => 'input', 'label' => 'value', 'name' => 'pys[event][reddit_params][value]' ],
			[ 'type' => 'input', 'label' => 'currency', 'name' => 'pys[event][reddit_params][currency]' ],
		],
		'Custom'        => [
			[ 'type' => 'input', 'label' => 'itemCount', 'name' => 'pys[event][reddit_params][itemCount]' ],
			[ 'type' => 'input', 'label' => 'value', 'name' => 'pys[event][reddit_params][value]' ],
			[ 'type' => 'input', 'label' => 'currency', 'name' => 'pys[event][reddit_params][currency]' ],
		],
	];

    public $GAEvents = array(
        'All Properties' => array(
            "conversion"             => array(),
            "earn_virtual_currency"  => array("virtual_currency_name", "value"),
            "join_group"             => array("group_id"),
            "login"                  => array("method"),
            "purchase"               => array('affiliation', 'coupon', 'currency', 'items', 'transaction_id', 'shipping', 'tax', 'value', 'google_business_vertical'),
            "refund"                 => array("transaction_id", "value", "currency", "tax", "shipping", "items", "affiliation", "coupon"),
            "search"                 => array("search_term"),
            "select_content"         => array("content_type", "item_id"),
            "share"                  => array("content_type", "item_id"),
            "sign_up"                => array("method"),
            "spend_virtual_currency" => array("item_name", "virtual_currency_name", "value"),
            "tutorial_begin"         => array(),
            "tutorial_complete"      => array(),
        ),
        'Custom Event' => array( 'CustomEvent' => array() ),
        "Games" => array(
            'earn_virtual_currency'  => array('virtual_currency_name', 'value'),
            'join_group'             => array('group_id'),
            'level_end'              => array('level_name', 'success'),
            'level_start'            => array('level_name'),
            'level_up'               => array('character', 'level'),
            'post_score'             => array('level', 'character', 'score'),
            'select_content'         => array('content_type', 'item_id'),
            'spend_virtual_currency' => array('item_name', 'virtual_currency_name', 'value'),
            'tutorial_begin'         => array(),
            'tutorial_complete'      => array(),
            'unlock_achievement'     => array('achievement_id'),
        ),
        'Jobs, Education, Local Deals, Real Estate' => array(
            'add_payment_info'    => array('coupon', 'currency', 'items', 'payment_type', 'value'),
            'add_shipping_info'   => array('coupon', 'currency', 'items', 'shipping_tier', 'value'),
            'add_to_cart'         => array('currency', 'items', 'value', 'google_business_vertical'),
            'add_to_wishlist'     => array('currency', 'items', 'value'),
            'begin_checkout'      => array('coupon', 'currency', 'items', 'value'),
            'purchase'            => array('affiliation', 'coupon', 'currency', 'items', 'transaction_id', 'shipping', 'tax', 'value', 'google_business_vertical'),
            'refund'              => array('affiliation', 'coupon', 'currency', 'items', 'transaction_id', 'shipping', 'tax', 'value'),
            'remove_from_cart'    => array('currency', 'items', 'value'),
            'select_item'         => array('items', 'item_list_name', 'item_list_id'),
            'select_promotion'    => array('items', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot', 'location_id'),
            'view_cart'           => array('currency', 'items', 'value'),
            'view_item'           => array('currency', 'items', 'value', 'google_business_vertical'),
            'view_item_list'      => array('items', 'item_list_name', 'item_list_id', 'google_business_vertical'),
            'view_promotion'      => array('items', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot', 'location_id'),
        ),
        'Retail/Ecommerce' => array(
            'add_payment_info'    => array('coupon', 'currency', 'items', 'payment_type', 'value'),
            'add_shipping_info'   => array('coupon', 'currency', 'items', 'shipping_tier', 'value'),
            'add_to_cart'         => array('currency', 'items', 'value', 'google_business_vertical'),
            'add_to_wishlist'     => array('currency', 'items', 'value'),
            'begin_checkout'      => array('coupon', 'currency', 'items', 'value'),
            'generate_lead'       => array('value', 'currency', 'google_business_vertical'),
            'purchase'            => array('affiliation', 'coupon', 'currency', 'items', 'transaction_id', 'shipping', 'tax', 'value', 'google_business_vertical'),
            'refund'              => array('affiliation', 'coupon', 'currency', 'items', 'transaction_id', 'shipping', 'tax', 'value'),
            'remove_from_cart'    => array('currency', 'items', 'value'),
            'select_item'         => array('items', 'item_list_name', 'item_list_id'),
            'select_promotion'    => array('items', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot', 'location_id'),
            'view_cart'           => array('currency', 'items', 'value'),
            'view_item'           => array('currency', 'items', 'value', 'google_business_vertical'),
            'view_item_list'      => array('items', 'item_list_name', 'item_list_id', 'google_business_vertical'),
            'view_promotion'      => array('items', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot', 'location_id'),
        ),
        'Travel (Hotel/Air)' => array(
            'add_payment_info'    => array('coupon', 'currency', 'items', 'payment_type', 'value'),
            'add_shipping_info'   => array('coupon', 'currency', 'items', 'shipping_tier', 'value'),
            'add_to_cart'         => array('currency', 'items', 'value', 'google_business_vertical'),
            'add_to_wishlist'     => array('currency', 'items', 'value'),
            'begin_checkout'      => array('coupon', 'currency', 'items', 'value'),
            'generate_lead'       => array('value', 'currency', 'google_business_vertical'),
            'purchase'            => array('affiliation', 'coupon', 'currency', 'items', 'transaction_id', 'shipping', 'tax', 'value', 'google_business_vertical'),
            'refund'              => array('affiliation', 'coupon', 'currency', 'items', 'transaction_id', 'shipping', 'tax', 'value'),
            'remove_from_cart'    => array('currency', 'items', 'value'),
            'select_item'         => array('items', 'item_list_name', 'item_list_id'),
            'select_promotion'    => array('items', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot', 'location_id'),
            'view_cart'           => array('currency', 'items', 'value'),
            'view_item'           => array('currency', 'items', 'value', 'google_business_vertical'),
            'view_item_list'      => array('items', 'item_list_name', 'item_list_id', 'google_business_vertical'),
            'view_promotion'      => array('items', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot', 'location_id'),
        ),
    );

    private $ecommerceParamArray = array(
        'currency',
        'value',
        'items',
        'tax',
        'shipping',
        'coupon',
        'affiliation',
        'transaction_id',
        'total_value',
        'ecomm_prodid',
        'ecomm_pagetype',
        'ecomm_totalvalue'
    );

    private $ecommerceEventNames = array(
        'add_payment_info',
        'add_shipping_info',
        'add_to_cart',
        'add_to_wishlist',
        'begin_checkout',
        'generate_lead',
        'purchase',
        'refund',
        'remove_from_cart',
        'select_item',
        'select_promotion',
        'view_cart',
        'view_item',
        'view_item_list',
        'view_promotion'
    );

    private $triggers = array();
    private $conditions = array();
    private $triggerEventTypes = array();

    private $data = array(

        'enable_time_window' => false,
        'time_window' => 24,

        'facebook_enabled'                  => false,
        'facebook_event_type'               => 'ViewContent',
        'facebook_custom_event_type'        => null,
        'facebook_params_enabled'           => false,
        'facebook_params'                   => array(),
        'facebook_track_single_woo_data'    => false,
        'facebook_track_cart_woo_data'      => false,
        'facebook_custom_params'            => array(),
        'facebook_pixel_id'                 => array('all'),


        'tiktok_enabled'           => false,
        'tiktok_event_type'        => 'Search',
        'tiktok_custom_event_type' => null,
        'tiktok_params_enabled'    => false,
        'tiktok_params'            => array(),
        'tiktok_track_single_woo_data'    => false,
        'tiktok_track_cart_woo_data'      => false,
        'tiktok_custom_params'     => array(),
        'tiktok_pixel_id'          => 'all',

        'bing_pixel_id'              => 'all',
        'pinterest_enabled'           => false,
        'pinterest_event_type'        => 'ViewContent',
        'pinterest_custom_event_type' => null,
        'pinterest_params_enabled'    => false,
        'pinterest_custom_params'     => array(),
        'pinterest_track_single_woo_data'    => false,
        'pinterest_track_cart_woo_data'      => false,

        'ga_enabled'             => false,
        'ga_pixel_id'            => array('all'),
        'ga_event_action'        => '_custom',
        'ga_custom_event_action' => null,
        //ver 4
        'ga_params'             => array(),
        'ga_custom_params'      => array(),
        'ga_custom_params_enabled'    => false,
        'ga_conversion_label'    => null,

        'ga_ads_enabled'             => false,
        'ga_ads_pixel_id'            => array('all'),
        'ga_ads_event_action'        => '_custom',
        'ga_ads_custom_event_action' => null,
        //ver 4
        'ga_ads_params'             => array(),
        'ga_ads_track_single_woo_data'     => false,
        'ga_ads_track_cart_woo_data'      => false,
        'ga_ads_custom_params'      => array(),
        'ga_ads_custom_params_enabled'    => false,
        'ga_ads_conversion_label'    => null,

        'gtm_enabled'             => false,
        'gtm_pixel_id'            => array(),
        'gtm_event_action'        => '_custom',
        'gtm_custom_event_action' => null,
        //ver 4
        'gtm_params'             => array(),
        'gtm_track_single_woo_data'     => false,
        'gtm_track_cart_woo_data'      => false,
        'gtm_custom_params'      => array(),
        'gtm_custom_params_enabled'    => false,
        'gtm_conversion_label'    => null,
        'gtm_automated_param'   => true,
        'gtm_remove_customTrigger' => false,
        'gtm_use_custom_object_name' => false,
        'gtm_custom_object_name' => null,

        'google_ads_enabled'             => false,
        'google_ads_conversion_id'       => array('all'),
        'google_ads_conversion_label'    => null,
        'google_ads_event_action'        => 'conversion',
        'google_ads_custom_event_action' => null,
        'google_ads_event_category'      => null,
        'google_ads_event_label'         => null,
        'google_ads_event_value'         => null,
        'google_ads_custom_params'       => array(),

        'bing_enabled' => false,
        'bing_event_action' => null,
        'bing_event_category' => null,
        'bing_event_label' => null,
        'bing_event_value' => null,
        'bing_track_single_woo_data' => false,
        'bing_track_cart_woo_data'      => false,

        'reddit_pixel_id'              => 'all',
        'reddit_event_type'            => 'ViewContent',
        'reddit_enabled'               => false,
        'reddit_track_single_woo_data' => false,
        'reddit_track_cart_woo_data'   => false,

        'conditions_enabled' => false,
        'conditions_logic' => 'OR'
    );

    public function __construct( $post_id = null ) {
        $this->initialize( $post_id );
    }

    function getAllData() {
        return $this->data;
    }

    public function __get( $key ) {

        if ( isset( $this->$key) ) {
            return $this->$key;
        }

        if ( isset( $this->data[ $key ] ) ) {
            return $this->data[ $key ];
        } else {
            return null;
        }

    }

    public function __set( $key, $value ) {
        if ( $key == 'triggerEventTypes' ) {
            $this->triggerEventTypes = $value;
        }
    }

    private function initialize( $post_id ) {

        if ( $post_id ) {

            $this->post_id = $post_id;
            $this->title   = get_the_title( $post_id );

            $data = get_post_meta( $post_id, '_pys_event_data', true );
            $triggers = get_post_meta( $post_id, '_pys_event_triggers', true );
            $conditions = get_post_meta( $post_id, '_pys_event_conditions', true );

            if ( $conditions !== '' ) {
                $this->conditions = !empty( $conditions ) ? unserialize( $conditions ) : array();
            }
            //Compatibility with old data
            if ( $triggers !== '' && is_string( $triggers ) ) {
                $this->triggers = !empty( $triggers ) ? unserialize( $triggers ) : array();
            } elseif ( !empty( $data ) && isset( $data[ 'trigger_type' ] )) {
                $trigger_type = $data[ 'trigger_type' ];
                $trigger_event = new TriggerEvent( $trigger_type, 0 );
                if ( in_array( $trigger_type, TriggerEvent::$allowedTriggers ) ) {
                    $trigger_event->migrateTriggerData( $trigger_type, $data );
                    $this->triggers = array( $trigger_event );
                } else {
                    $this->triggers = array();
                }
            }elseif ( !empty( $triggers ) ) {
                foreach ( $triggers as $trigger ) {
                    if ($trigger instanceof TriggerEvent) {
                        $this->triggers[] = $trigger;
                    }
                }
            }

            // add loaded data to default or use default
            $this->data = is_array( $data ) ? $data+$this->data : $this->data;

            $state = get_post_meta( $post_id, '_pys_event_state', true );
            $this->enabled = $state == 'active' ? true : false;

        }
        else{
            if(empty($this->data['gtm_pixel_id'])) {
                $all = GTM()->getPixelIDs();
                if(count($all) > 0) {
                    $this->data['gtm_pixel_id'] = $all[0];
                }
            }
        }
    }

    public function setData($newData) {

        //set title
        wp_update_post( array(
            'ID'         => $this->post_id,
            'post_title' => $newData['title']
        ) );

        // set state
        $state =  $newData['enabled'] ? 'active' : 'paused';
        $this->enabled = $newData['enabled'];
        update_post_meta( $this->post_id, '_pys_event_state', $state );


        // set other
        $this->data = $newData;

        //save
        update_post_meta( $this->post_id, '_pys_event_data', $this->data );
    }

    public function update( $args = null ) {

        if ( !is_array( $args ) ) {
            $args = $this->data;
        }

        /**
         * GENERAL
         */

        // title
        wp_update_post( array(
            'ID'         => $this->post_id,
            'post_title' => empty( $args[ 'title' ] ) ? $this->title : sanitize_text_field( $args[ 'title' ] )
        ) );

        // state
        $state = isset( $args[ 'enabled' ] ) && $args[ 'enabled' ] ? 'active' : 'paused';
        $this->enabled = $state == 'active' ? true : false;
        update_post_meta( $this->post_id, '_pys_event_state', $state );

        $trigger_types = array(
            'page_visit',
            'home_page',
            'add_to_cart',
            'purchase',
            'number_page_visit',
            'url_click',
            'css_click',
            'css_mouseover',
            'scroll_pos',
            'post_type',
            'video_view',
            'email_link',
            'form_field',
        );
        $eventsFormFactory = apply_filters( "pys_form_event_factory", [] );
        foreach ( $eventsFormFactory as $activeFormPlugin ) :
            $trigger_types[] = $activeFormPlugin->getSlug();
        endforeach;

        $this->data[ 'enable_time_window' ] = isset( $args[ 'enable_time_window' ] ) ? (bool) $args[ 'enable_time_window' ] : false;
        $this->data[ 'time_window' ] = isset( $args[ 'time_window' ] ) ? (int) $args[ 'time_window' ] : 24;

        $this->data[ 'conditions_enabled' ] = isset( $args[ 'conditions_enabled' ] ) ? (bool) $args[ 'conditions_enabled' ] : false;
        $this->data[ 'conditions_logic' ] = isset( $args[ 'conditions_logic' ] ) ? $args[ 'conditions_logic' ] : 'OR';

        // reset old triggers
        $this->triggers = array();
        $index = 0;

        $this->conditions = array();
        $condition_index = 0;
        //reset old data
        $old_data = array(
            'conditional_number_visit',
            'number_visit',
            'triggers',
            'post_type_value',
            'video_view_data',
            'video_view_urls',
            'video_view_triggers',
            'video_view_play_trigger',
            'video_view_disable_watch_video',
            'disabled_form_action',
            'forms',
            'delay',
            'url_filters'
        );
        foreach ( $old_data as $datum ) {
            if ( isset( $this->data[ $datum ] ) ) {
                unset( $this->data[ $datum ] );
            }
        }

        if ( !empty( $args[ 'triggers' ] ) ) {
            foreach ( $args[ 'triggers' ] as $data_trigger ) {

                if ( isset( $data_trigger[ 'cloned_event' ] ) ) {
                    continue;
                }

                $saving_trigger = false;

                // trigger type
                $trigger_type = isset( $data_trigger[ 'trigger_type' ] ) && in_array( $data_trigger[ 'trigger_type' ], $trigger_types ) ? sanitize_text_field( $data_trigger[ 'trigger_type' ] ) : 'page_visit';

                $trigger = new TriggerEvent( $trigger_type );

                // delay
                $delay = ( $trigger_type == 'page_visit' || $trigger_type == 'post_type' || $trigger_type == 'home_page' ) && isset( $data_trigger[ 'delay' ] ) && $data_trigger[ 'delay' ] ? (int) sanitize_text_field( $data_trigger[ 'delay' ] ) : null;
                $trigger->updateParam( 'delay', $delay );

                $number_visit = ( $trigger_type == 'number_page_visit' ) && isset( $data_trigger[ 'number_visit' ] ) && $data_trigger[ 'number_visit' ] ? (int) sanitize_text_field( $data_trigger[ 'number_visit' ] ) : null;
                $trigger->updateParam( 'number_visit', $number_visit );

                $conditional_number_visit = ( $trigger_type == 'number_page_visit' ) && isset( $data_trigger[ 'conditional_number_visit' ] ) && $data_trigger[ 'conditional_number_visit' ] ? (string) sanitize_text_field( $data_trigger[ 'conditional_number_visit' ] ) : null;
                $trigger->updateParam( 'conditional_number_visit', $conditional_number_visit );

                // post_type_value
                $post_type_value = $trigger_type == 'post_type' && isset( $data_trigger[ 'post_type_value' ] ) && $data_trigger[ 'post_type_value' ] ? sanitize_text_field( $data_trigger[ 'post_type_value' ] ) : null;
                $trigger->updateParam( 'post_type_value', $post_type_value );

                if ( $trigger_type === 'post_type' || $trigger_type === 'home_page' || $trigger_type === 'add_to_cart' || $trigger_type === 'purchase' ) {
                    $saving_trigger = true;
                }

                /**
                 * TRIGGERS
                 */
                $event_triggers = array();

                // page visit triggers
                if ( $trigger_type == 'page_visit' && isset( $data_trigger[ 'page_visit_triggers' ] ) && is_array( $data_trigger[ 'page_visit_triggers' ] ) ) {

                    foreach ( $data_trigger[ 'page_visit_triggers' ] as $page_visit_trigger ) {
                        if ( !empty( $page_visit_trigger[ 'value' ] ) ) {
                            $event_triggers[] = array(
                                'rule'  => sanitize_text_field( $page_visit_trigger[ 'rule' ] ),
                                'value' => sanitize_text_field( $page_visit_trigger[ 'value' ] ),
                            );
                        }
                    }
                }

                if ( $trigger_type == 'number_page_visit' && isset( $data_trigger[ 'number_page_visit_triggers' ] ) && is_array( $data_trigger[ 'number_page_visit_triggers' ] ) ) {

                    foreach ( $data_trigger[ 'number_page_visit_triggers' ] as $number_page_trigger ) {
                        if ( $number_page_trigger[ 'rule' ] === 'any' ) {
                            $event_triggers[] = array(
                                'rule' => sanitize_text_field( $number_page_trigger[ 'rule' ] ),
                            );
                            continue;
                        }
                        if ( !empty( $number_page_trigger[ 'value' ] ) ) {
                            $event_triggers[] = array(
                                'rule'  => sanitize_text_field( $number_page_trigger[ 'rule' ] ),
                                'value' => sanitize_text_field( $number_page_trigger[ 'value' ] ),
                            );
                        }
                    }
                }

                // url click triggers
                if ( $trigger_type == 'url_click' && isset( $data_trigger[ 'url_click_triggers' ] ) && is_array( $data_trigger[ 'url_click_triggers' ] ) ) {

                    foreach ( $data_trigger[ 'url_click_triggers' ] as $url_click_trigger ) {
                        if ( !empty( $url_click_trigger[ 'value' ] ) ) {
                            $event_triggers[] = array(
                                'rule'  => sanitize_text_field( $url_click_trigger[ 'rule' ] ),
                                'value' => sanitize_text_field( $url_click_trigger[ 'value' ] ),
                            );
                        }
                    }
                }

                // css click triggers
                if ( $trigger_type == 'css_click' && isset( $data_trigger[ 'css_click_triggers' ] ) && is_array( $data_trigger[ 'css_click_triggers' ] ) ) {

                    foreach ( $data_trigger[ 'css_click_triggers' ] as $css_click_trigger ) {
                        if ( !empty( $css_click_trigger[ 'value' ] ) ) {
                            $event_triggers[] = array(
                                'rule'  => null,
                                'value' => sanitize_text_field( $css_click_trigger[ 'value' ] ),
                            );
                        }
                    }
                }

                // css mouseover triggers
                if ( $trigger_type == 'css_mouseover' && isset( $data_trigger[ 'css_mouseover_triggers' ] ) && is_array( $data_trigger[ 'css_mouseover_triggers' ] ) ) {

                    foreach ( $data_trigger[ 'css_mouseover_triggers' ] as $css_mouseover_trigger ) {
                        if ( !empty( $css_mouseover_trigger[ 'value' ] ) ) {
                            $event_triggers[] = array(
                                'rule'  => null,
                                'value' => sanitize_text_field( $css_mouseover_trigger[ 'value' ] ),
                            );
                        }
                    }
                }

                // scroll pos triggers
                if ( $trigger_type == 'scroll_pos' && isset( $data_trigger[ 'scroll_pos_triggers' ] ) && is_array( $data_trigger[ 'scroll_pos_triggers' ] ) ) {

                    foreach ( $data_trigger[ 'scroll_pos_triggers' ] as $scroll_pos_trigger ) {
                        if ( !empty( $scroll_pos_trigger[ 'value' ] ) ) {
                            $event_triggers[] = array(
                                'rule'  => null,
                                'value' => (int) sanitize_text_field( $scroll_pos_trigger[ 'value' ] ),
                            );
                        }
                    }
                }


                // embedded video triggers
                if ( $trigger_type == 'video_view' ) {
                    $video_view_data = ( isset( $data_trigger[ 'video_view_urls' ] ) && is_array( $data_trigger[ 'video_view_urls' ] ) && isset( $data_trigger[ 'video_view_data' ] ) ) ? json_decode( sanitize_text_field( stripslashes( $data_trigger[ 'video_view_data' ] ) ), true ) : array();
                    $trigger->updateParam( 'video_view_data', $video_view_data );

                    if ( !empty( $video_view_data ) && isset( $data_trigger[ 'video_view_urls' ] ) && is_array( $data_trigger[ 'video_view_urls' ] ) ) {

                        $video_view_urls = array();
                        foreach ( $data_trigger[ 'video_view_urls' ] as $url ) {
                            $video_view_urls[] = sanitize_url( $url );
                        }
                        $trigger->updateParam( 'video_view_urls', $video_view_urls );
                        $saving_trigger = true;
                    }

                    if ( !empty( $data_trigger[ 'video_view_urls' ] ) ) {
                        if ( isset( $data_trigger[ 'video_view_play_trigger' ] ) ) {
                            $trigger->updateParam( 'video_view_play_trigger', sanitize_text_field( $data_trigger[ 'video_view_play_trigger' ] ) );
                        }

                        if ( isset( $data_trigger[ 'video_view_disable_watch_video' ] ) ) {
                            $trigger->updateParam( 'video_view_disable_watch_video', (bool) sanitize_text_field( $data_trigger[ 'video_view_disable_watch_video' ] ) );
                        }
                    }

                    if ( !empty( $data_trigger[ 'video_view_urls' ] ) && isset( $data_trigger[ 'video_view_triggers' ] ) && is_array( $data_trigger[ 'video_view_triggers' ] ) ) {
                        $video_view_triggers = array();
                        foreach ( $data_trigger[ 'video_view_triggers' ] as $video_view_trigger ) {
                            $video_view_trigger = sanitize_text_field( $video_view_trigger );
                            $video_view_triggers[] = $video_view_trigger;
                            $i = array_search( $video_view_trigger, array_column( $video_view_data, 'id' ) );
                            if ( $i !== false ) {
                                $event_triggers[] = array(
                                    'rule'  => $video_view_trigger,
                                    'value' => $data_trigger[ 'video_view_play_trigger' ],
                                    'type'  => $video_view_data[ $i ][ 'type' ],
                                );
                            }
                        }

                        $trigger->updateParam( 'video_view_triggers', $video_view_triggers );
                    }
                }

                // elementor form triggers
                if ( $trigger_type == 'elementor_form' ) {

                    $elementor_form_data = ( isset( $data_trigger[ 'elementor_form_urls' ] ) && is_array( $data_trigger[ 'elementor_form_urls' ] ) && isset( $data_trigger[ 'elementor_form_data' ] ) ) ? json_decode( sanitize_text_field( stripslashes( $data_trigger[ 'elementor_form_data' ] ) ), true ) : array();
                    $trigger->updateParam( 'elementor_form_data', $elementor_form_data );

                    if ( !empty( $elementor_form_data ) && isset( $data_trigger[ 'elementor_form_urls' ] ) && is_array( $data_trigger[ 'elementor_form_urls' ] ) ) {
                        $elementor_form_urls = array();
                        foreach ( $data_trigger[ 'elementor_form_urls' ] as $url ) {
                            $elementor_form_urls[] = sanitize_url( $url );
                        }
                        $trigger->updateParam( 'elementor_form_urls', $elementor_form_urls );
                        $saving_trigger = true;
                    }

                    if ( !empty( $data_trigger[ 'elementor_form_urls' ] ) && isset( $data_trigger[ 'elementor_form' ] ) && is_array( $data_trigger[ 'elementor_form' ] ) && isset($data_trigger[ 'elementor_form' ]['forms']) ) {
                        $elementor_form_triggers = array();
                        foreach ( $data_trigger[ 'elementor_form' ]['forms'] as $elementor_form ) {
                            $elementor_form_triggers[] = sanitize_text_field( $elementor_form );
                        }
                        $trigger->updateParam( 'forms', $elementor_form_triggers );
                    }
                    
                    if ( isset( $data_trigger[ 'elementor_form' ][ 'form_submit_mode' ] ) ) {
                        $form_submit_mode = sanitize_text_field( $data_trigger[ 'elementor_form' ][ 'form_submit_mode' ] );
                        if ( !in_array( $form_submit_mode, array( 'both', 'reload_only', 'ajax_only' ) ) ) {
                            $form_submit_mode = 'both';
                        }
                        $trigger->updateParam( 'form_submit_mode', $form_submit_mode );
                    }
                }

                //Email link
                if ( $trigger_type == 'email_link' && isset( $data_trigger[ 'email_link_triggers' ] ) && is_array( $data_trigger[ 'email_link_triggers' ] ) ) {
                    foreach ( $data_trigger[ 'email_link_triggers' ] as $email_link_trigger ) {

                        if ( $email_link_trigger[ 'rule' ] === 'any' ) {
                            $event_triggers[] = array(
                                'rule'  => sanitize_text_field( $email_link_trigger[ 'rule' ] ),
                                'value' => ''
                            );
                            continue;
                        }

                        if ( !empty( $email_link_trigger[ 'value' ] ) ) {
                            $event_triggers[] = array(
                                'rule'  => sanitize_text_field( $email_link_trigger[ 'rule' ] ),
                                'value' => sanitize_text_field( $email_link_trigger[ 'value' ] ),
                            );
                        }
                    }

                    if ( isset( $data_trigger[ 'email_link_disable_email_event' ] ) ) {
                        $trigger->updateParam( 'email_link_disable_email_event', (bool) sanitize_text_field( $data_trigger[ 'email_link_disable_email_event' ] ) );
                    }
                }

                //Purchase
                if ( $trigger_type == 'purchase') {
                    if ( isset( $data_trigger[ 'purchase_transaction_only' ] ) ) {
                        $trigger->updateParam( 'purchase_transaction_only', (bool) sanitize_text_field( $data_trigger[ 'purchase_transaction_only' ] ) );
                    }
                    if(isset($data_trigger['track_transaction_ID'])){
                        $trigger->updateParam( 'track_transaction_ID', (bool) sanitize_text_field( $data_trigger[ 'track_transaction_ID' ] ) );
                    }
                    if(isset($data_trigger['track_value_and_currency'])){
                        $trigger->updateParam( 'track_value_and_currency', (bool) sanitize_text_field( $data_trigger[ 'track_value_and_currency' ] ) );
                    }
                }

                //AddToCart
                if($trigger_type == 'add_to_cart'){
                    if(isset($data_trigger['track_value_and_currency'])){
                        $trigger->updateParam( 'track_value_and_currency', (bool) sanitize_text_field( $data_trigger[ 'track_value_and_currency' ] ) );
                    }
                }

                if ( $trigger->isFormTriggerType( $trigger_type ) && $trigger_type != 'elementor_form' && isset( $data_trigger[ $trigger_type ][ 'forms' ] ) ) {
                    $forms = array();
                    foreach ( $data_trigger[ $trigger_type ][ 'forms' ] as $form ) {
                        if ( !empty( $form ) ) {
                            $forms[] = $form;
                        }
                    }
                    $trigger->updateParam( 'forms', $forms );
                    $saving_trigger = true;

                }

                if ( $trigger->isFormTriggerType( $trigger_type ) && isset( $data_trigger[ $trigger_type ][ 'disabled_form_action' ] ) ) {
                    $disabled_form_action = (bool) $data_trigger[ $trigger_type ][ 'disabled_form_action' ];
                    $trigger->updateParam( 'disabled_form_action', $disabled_form_action );
                }
                
                if ( $trigger->isFormTriggerType( $trigger_type ) && isset( $data_trigger[ $trigger_type ][ 'form_submit_mode' ] ) ) {
                    $form_submit_mode = sanitize_text_field( $data_trigger[ $trigger_type ][ 'form_submit_mode' ] );
                    if ( !in_array( $form_submit_mode, array( 'both', 'reload_only', 'ajax_only' ) ) ) {
                        $form_submit_mode = 'both';
                    }
                    $trigger->updateParam( 'form_submit_mode', $form_submit_mode );
                }


                if ( $trigger_type == 'form_field' ) {

                    $form_field_data = ( isset( $data_trigger[ 'form_field' ] ) && is_array( $data_trigger[ 'form_field_urls' ] ) && isset( $data_trigger[ 'form_field_data' ] ) ) ? json_decode( sanitize_text_field( stripslashes( $data_trigger[ 'form_field_data' ] ) ), true ) : array();
                    $trigger->updateParam( 'form_field_data', $form_field_data );

                    if ( !empty( $form_field_data ) && isset( $data_trigger[ 'form_field_urls' ] ) && is_array( $data_trigger[ 'form_field_urls' ] ) ) {
                        $form_field_urls = array();
                        foreach ( $data_trigger[ 'form_field_urls' ] as $url ) {
                            $form_field_urls[] = sanitize_url( $url );
                        }
                        $trigger->updateParam( 'form_field_urls', $form_field_urls );
                        $saving_trigger = true;
                    }

                    if ( !empty( $data_trigger[ 'form_field_urls' ] ) && isset( $data_trigger[ 'form_field' ] ) && is_array( $data_trigger[ 'form_field' ] ) && isset($data_trigger[ 'form_field' ]['form']) ) {
                        $trigger->updateParam( 'form_field_forms', $data_trigger[ 'form_field' ]['form'] );
                    }

                    if ( !empty( $data_trigger[ 'form_field_urls' ] ) && isset( $data_trigger[ 'form_field' ] ) && is_array( $data_trigger[ 'form_field' ] ) && isset($data_trigger[ 'form_field' ]['field']) ) {
                        $trigger->updateParam( 'form_field_fields', $data_trigger[ 'form_field' ]['field'] );
                    }
                }

                if ( !empty( $event_triggers ) || $saving_trigger ) {
                    $trigger->updateParam( 'triggers', $event_triggers );
                    $trigger->updateParam( 'index', $index );

                    $this->triggers[] = $trigger;
                    $index++;
                }
            }
        }
        if ( !empty( $args[ 'conditions' ] ) ) {
            foreach ($args['conditions'] as $data_condition) {
                if (isset($data_condition['cloned_event'])) {
                    continue;
                }

                $condition_type = isset( $data_condition[ 'condition_type' ] ) ? sanitize_text_field( $data_condition[ 'condition_type' ] ) : 'url_filters';
                $condition = new ConditionalEvent( $condition_type );
                switch ($condition_type){
                    case 'url_filters' :
                    case 'url_parameters':
                    case 'landing_page' :
                    case 'source' :
                        if (empty($data_condition[$condition_type][ 'condition_value' ])) {
                            continue 2;
                        }
                        $condition->updateParam('condition_rule', $data_condition[$condition_type][ 'condition_rule' ]);
                        $condition->updateParam('condition_value', $data_condition[$condition_type][ 'condition_value' ]);
                        break;
                    case 'device' :
                        $condition->updateParam('device', $data_condition[ 'device' ]);
                        break;
                    case 'user_role':
                        $condition->updateParam('user_role', $data_condition[ 'user_role' ]);
                        break;
                }

                $condition->updateParam( 'index', $condition_index );
                $this->conditions[] = $condition;
                $condition_index++;
            }
        }

        /**
         * TIKTOK
         */
        $this->updateTikTok( $args );

	    /**
         * REDDIT
         */
		$this->updateRedditParams( $args );

        /**
         * FACEBOOK
         */
        $facebook_event_types = array(
            'ViewContent',
            'AddToCart',
            'AddToWishlist',
            'InitiateCheckout',
            'AddPaymentInfo',
            'Purchase',
            'Lead',
            'CompleteRegistration',

            'Subscribe',
            'CustomizeProduct',
            'FindLocation',
            'StartTrial',
            'SubmitApplication',
            'Schedule',
            'Contact',
            'Donate',

            'CustomEvent'
        );

        // enabled
        $this->data[ 'facebook_enabled' ] = isset( $args[ 'facebook_enabled' ] ) && $args[ 'facebook_enabled' ] ? true : false;
        $allFBpixels = Facebook()->getAllPixels( false );
        if ( !empty( $args[ 'facebook_pixel_id' ] ) ) {
            $this->data[ 'facebook_pixel_id' ] = array_map( function ( $pixelId ) use ( $allFBpixels ) {
                if ( in_array( $pixelId, $allFBpixels ) || $pixelId == 'all' ) {
                    return $pixelId;
                }
            }, $args[ 'facebook_pixel_id' ] );
        } elseif ( count( $allFBpixels ) > 0 ) {
            $this->data[ 'facebook_pixel_id' ] = $allFBpixels[ 0 ];
        } else {
            $this->data[ 'facebook_pixel_id' ] = [];
        }

        // event type
        $this->data[ 'facebook_event_type' ] = isset( $args[ 'facebook_event_type' ] ) && in_array( $args[ 'facebook_event_type' ], $facebook_event_types ) ? sanitize_text_field( $args[ 'facebook_event_type' ] ) : 'ViewContent';

        // custom event type
        $this->data[ 'facebook_custom_event_type' ] = $this->facebook_event_type == 'CustomEvent' && !empty( $args[ 'facebook_custom_event_type' ] ) ? sanitizeKey( $args[ 'facebook_custom_event_type' ] ) : null;

        // params enabled
        $this->data[ 'facebook_params_enabled' ] = isset( $args[ 'facebook_params_enabled' ] ) && $args[ 'facebook_params_enabled' ] ? true : false;

        // params
        if ( $this->facebook_params_enabled && isset( $args[ 'facebook_params' ] ) && $this->facebook_event_type !== 'CustomEvent' ) {

            $this->data[ 'facebook_params' ] = array(
                'value'            => !empty( $args[ 'facebook_params' ][ 'value' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'value' ] ) : null,
                'currency'         => !empty( $args[ 'facebook_params' ][ 'currency' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'currency' ] ) : null,
                'content_name'     => !empty( $args[ 'facebook_params' ][ 'content_name' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'content_name' ] ) : null,
                'content_ids'      => !empty( $args[ 'facebook_params' ][ 'content_ids' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'content_ids' ] ) : null,
                'content_type'     => !empty( $args[ 'facebook_params' ][ 'content_type' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'content_type' ] ) : null,
                'content_category' => !empty( $args[ 'facebook_params' ][ 'content_category' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'content_category' ] ) : null,
                'num_items'        => !empty( $args[ 'facebook_params' ][ 'num_items' ] ) ? (int) $args[ 'facebook_params' ][ 'num_items' ] : null,
                'order_id'         => !empty( $args[ 'facebook_params' ][ 'order_id' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'order_id' ] ) : null,
                'search_string'    => !empty( $args[ 'facebook_params' ][ 'search_string' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'search_string' ] ) : null,
                'status'           => !empty( $args[ 'facebook_params' ][ 'status' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'status' ] ) : null,
                'predicted_ltv'    => !empty( $args[ 'facebook_params' ][ 'predicted_ltv' ] ) ? sanitize_text_field( $args[ 'facebook_params' ][ 'predicted_ltv' ] ) : null,
            );

            // custom currency
            if ( $this->data[ 'facebook_params' ][ 'currency' ] == 'custom' && !empty( $args[ 'facebook_params' ][ 'custom_currency' ] ) ) {
                $this->data[ 'facebook_params' ][ 'custom_currency' ] = sanitize_text_field( $args[ 'facebook_params' ][ 'custom_currency' ] );
            } else {
                $this->data[ 'facebook_params' ][ 'custom_currency' ] = null;
            }

        } else {

            $this->data[ 'facebook_params' ] = array(
                'value'            => null,
                'currency'         => null,
                'custom_currency'  => null,
                'content_name'     => null,
                'content_ids'      => null,
                'content_type'     => null,
                'content_category' => null,
                'num_items'        => null,
                'order_id'         => null,
                'search_string'    => null,
                'status'           => null,
                'predicted_ltv'    => null,
            );

        }

        $this->data['facebook_track_single_woo_data'] = isset( $args[ 'facebook_track_single_woo_data' ] ) && $args[ 'facebook_track_single_woo_data' ] ? true : false;
        $this->data['facebook_track_cart_woo_data'] = isset( $args[ 'facebook_track_cart_woo_data' ] ) && $args[ 'facebook_track_cart_woo_data' ] ? true : false;
        // reset old custom params
        $this->data[ 'facebook_custom_params' ] = array();

        // custom params
        if ( $this->facebook_params_enabled && isset( $args[ 'facebook_custom_params' ] ) ) {

            foreach ( $args[ 'facebook_custom_params' ] as $custom_param ) {

                if ( !empty( $custom_param[ 'name' ] ) && !empty( $custom_param[ 'value' ] ) ) {

                    $this->data[ 'facebook_custom_params' ][] = array(
                        'name'  => sanitize_text_field( $custom_param[ 'name' ] ),
                        'value' => sanitize_text_field( $custom_param[ 'value' ] ),
                    );

                }

            }

        }

        /**
         * PINTEREST
         */

        $pinterest_event_types = array(
            'pagevisit',
            'viewcategory',
            'search',
            'addtocart',
            'checkout',
            'watchvideo',
            'signup',
            'lead',
            'custom',
            'partner_defined',
        );

        // enabled
        $this->data[ 'pinterest_enabled' ] = isset( $args[ 'pinterest_enabled' ] ) && $args[ 'pinterest_enabled' ] ? true : false;

        // event type
        $this->data[ 'pinterest_event_type' ] = isset( $args[ 'pinterest_event_type' ] ) && in_array( $args[ 'pinterest_event_type' ], $pinterest_event_types ) ? sanitize_text_field( $args[ 'pinterest_event_type' ] ) : 'pagevisit';

        // custom event type
        $this->data[ 'pinterest_custom_event_type' ] = $this->pinterest_event_type == 'partner_defined' && !empty( $args[ 'pinterest_custom_event_type' ] ) ? sanitizeKey( $args[ 'pinterest_custom_event_type' ] ) : null;

        // params enabled
        $this->data[ 'pinterest_params_enabled' ] = isset( $args[ 'pinterest_params_enabled' ] ) && $args[ 'pinterest_params_enabled' ] ? true : false;

        $this->data['pinterest_track_single_woo_data'] = isset( $args[ 'pinterest_track_single_woo_data' ] ) && $args[ 'pinterest_track_single_woo_data' ] ? true : false;
        $this->data['pinterest_track_cart_woo_data'] = isset( $args[ 'pinterest_track_cart_woo_data' ] ) && $args[ 'pinterest_track_cart_woo_data' ] ? true : false;

        // reset old custom params
        $this->data[ 'pinterest_custom_params' ] = array();

        // custom params
        if ( $this->pinterest_params_enabled && isset( $args[ 'pinterest_custom_params' ] ) ) {

            foreach ( $args[ 'pinterest_custom_params' ] as $custom_param ) {

                if ( !empty( $custom_param[ 'name' ] ) && !empty( $custom_param[ 'value' ] ) ) {

                    $this->data[ 'pinterest_custom_params' ][] = array(
                        'name'  => sanitize_text_field( $custom_param[ 'name' ] ),
                        'value' => sanitize_text_field( $custom_param[ 'value' ] ),
                    );

                }

            }

        }

        $this->updateUnifyGA( $args );

        $this->updateGTM($args);


        /**
         * BING
         */

        $this->data[ 'bing_enabled' ] = isset( $args[ 'bing_enabled' ] ) && $args[ 'bing_enabled' ] ? true : false;
        $this->data[ 'bing_event_action' ] = !empty( $args[ 'bing_event_action' ] ) ? sanitize_text_field( $args[ 'bing_event_action' ] ) : null;
        $this->data[ 'bing_event_category' ] = !empty( $args[ 'bing_event_category' ] ) ? sanitize_text_field( $args[ 'bing_event_category' ] ) : null;
        $this->data[ 'bing_event_label' ] = !empty( $args[ 'bing_event_label' ] ) ? sanitize_text_field( $args[ 'bing_event_label' ] ) : null;
        $this->data[ 'bing_event_value' ] = !empty( $args[ 'bing_event_value' ] ) ? sanitize_text_field( $args[ 'bing_event_value' ] ) : null;
        $this->data['bing_track_single_woo_data'] = isset( $args[ 'bing_track_single_woo_data' ] ) && $args[ 'bing_track_single_woo_data' ] ? true : false;
        $this->data['bing_track_cart_woo_data'] = isset( $args[ 'bing_track_cart_woo_data' ] ) && $args[ 'bing_track_cart_woo_data' ] ? true : false;

        $this->data[ 'bing_pixel_id' ] = !empty( $args[ 'bing_pixel_id' ] ) && in_array( $args[ 'bing_pixel_id' ], Bing()->getAllPixels() ) ? $args[ 'bing_pixel_id' ] : 'all';


        update_post_meta( $this->post_id, '_pys_event_data', $this->data );
        update_post_meta( $this->post_id, '_pys_event_conditions', addslashes( serialize( $this->conditions ) ) );
        update_post_meta( $this->post_id, '_pys_event_triggers', addslashes( serialize( $this->triggers ) ) );
    }

    public function enable() {

        $this->enabled = true;
        update_post_meta( $this->post_id, '_pys_event_state', 'active' );

    }

    public function disable() {

        $this->enabled = false;
        update_post_meta( $this->post_id, '_pys_event_state', 'paused' );

    }

    /**
     * @return int
     */
    public function getPostId() {
        return $this->post_id;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return $this->title;
    }

    public function transformTitle($title = null) {

        if(!is_null($title)){
            $title_pre_transform = $title;
        }
        else{
            $title_pre_transform = $this->title;
        }
        $textLat = Transliterator::transliterate($title_pre_transform);
        $cleaned = preg_replace('/[^A-Za-z0-9]+/', ' ', $textLat);
        $result = ucwords(trim($cleaned));
        return str_replace(' ', '', $result);
    }

    public function getManualCustomObjectName()
    {
        return $this->gtm_use_custom_object_name && $this->gtm_custom_object_name ? $this->gtm_custom_object_name : 'manual_'.$this->transformTitle();
    }

    public function isEnabled() {
        return $this->enabled;
    }

    public function getTriggers() {
        return $this->triggers;
    }

    public function getConditions(){
        return $this->conditions;
    }

    public function hasTriggerAddToCart() {
        $flag = false;
        foreach ($this->triggers as $trigger) {
            if ($trigger->getTriggerType() === 'add_to_cart') {
                $flag = true;
                break;
            }
        }
        return $flag;
    }

    public function hasTimeWindow() {
        return $this->enable_time_window;
    }

    public function getTimeWindow() {
        return $this->time_window;
    }

    public function isFacebookEnabled() {
        return (bool) $this->facebook_enabled;
    }

    public function getFacebookEventType() {
        return $this->facebook_event_type == 'CustomEvent' ? $this->facebook_custom_event_type : $this->facebook_event_type;
    }

    public function isFacebookParamsEnabled() {
        return (bool) $this->facebook_params_enabled;
    }

    public function getFacebookParam( $key ) {
        return isset( $this->facebook_params[ $key ] ) ? $this->facebook_params[ $key ] : null;
    }

    public function getFacebookParams() {
        return $this->facebook_params_enabled ? $this->facebook_params : array();
    }

    public function getFacebookCustomParams() {
        return $this->facebook_params_enabled ? $this->facebook_custom_params : array();
    }

    public function isPinterestEnabled() {
        return (bool) $this->pinterest_enabled;
    }

	public function isRedditEnabled() {
		return (bool) $this->reddit_enabled;
	}

    public function getPinterestEventType() {
        return $this->pinterest_event_type == 'partner_defined'
            ? $this->pinterest_custom_event_type
            : $this->pinterest_event_type;
    }

    public function isPinterestParamsEnabled() {
        return (bool) $this->pinterest_params_enabled;
    }

    public function getPinterestCustomParams() {
        return $this->pinterest_params_enabled ? $this->pinterest_custom_params : array();
    }
    public function isGTMEnabled(){
        return (bool) $this->gtm_enabled;
    }

    public function hasAutomatedParam(){
        return (bool) $this->gtm_automated_param;
    }

    public function removeGTMCustomTrigger(){
        return $this->gtm_remove_customTrigger;
    }

    public function useCustomNameObject(){
        return (bool) $this->gtm_use_custom_object_name;
    }
    public function isGTMPresent(){
        $allValues = GTM()->getAllPixels();
        $selectedValues = (array) $this->gtm_pixel_id;
        $hasAWElement = !empty($selectedValues) && (
                ( in_array( 'all', $selectedValues ) &&
                    (bool) array_filter( $allValues, function ( $value ) {
                        return strpos( $value, 'GTM' ) === 0;
                    } ) ) ||
                (bool) array_filter($selectedValues, function($value) {
                    return strpos($value, 'GTM') === 0;
                })
            );

        return $hasAWElement;
    }
    public function isGoogleAnalyticsEnabled() {
        return (bool) $this->ga_enabled;
    }

    public function isGoogleAnalyticsPresent() {
        $allValues = array_merge(GA()->getAllPixels(), Ads()->getAllPixels());
        $selectedValues = (array) $this->ga_ads_pixel_id;

        $hasAWElement = !empty($selectedValues) && (
                (
                    in_array('all', $selectedValues) &&
                    (bool) array_filter($allValues, function($value) {
                        return is_string($value) && strpos($value, 'G') === 0;
                    })
                ) ||
                (bool) array_filter($selectedValues, function($value) {
                    return is_string($value) && strpos($value, 'G') === 0;
                })
            );

        return $hasAWElement;
    }

    public function getGAMergedCustomParams() {
        if(is_array($this->ga_ads_custom_params)) {
            return $this->ga_ads_custom_params;
        }
        return [];
    }

    public function getGACustomParams() {
        if(is_array($this->ga_custom_params)) {
            return $this->ga_custom_params;
        }
        return [];
    }


    public function getGaParams() {
        if($this->isGaV4()) {
            if(is_array($this->ga_params)) {
                return $this->ga_params;
            } else {
                return [];
            }

        }

        $list = array();
        foreach ($this->GAEvents as $group) {
            foreach ($group as $name => $fields) {
                if($name == $this->data['ga_event_action']) {
                    foreach ($fields as $field) {
                        $list[$field] = "";
                    }
                }
            }
        }

        return $list;
    }
    public function getGTMParams() {
        if(is_array($this->gtm_params)) {
            return apply_filters( 'pys_superpack_dynamic_params', $this->gtm_params, 'gtm' );
        } else {
            return [];
        }
    }

    public function getAllGTMParams(){
        $params = [];
        if(is_array($this->getGTMParams())){
            if(in_array($this->getGTMAction(), $this->ecommerceEventNames)){
                foreach ($this->getGTMParams() as $key => $param){
                    if ( in_array( $key, $this->ecommerceParamArray ) ) {
                        $params['ecommerce'][ $key ] = $param;
                    } else {
                        $params[ $this->getManualCustomObjectName() ][ $key ] = $param;
                    }
                }
            }
            else{
                foreach ($this->getGTMParams() as $key => $param){
                    $params[ $this->getManualCustomObjectName() ][ $key ] = $param;
                }
            }
        }

        if(is_array($this->getGTMCustomParams())){
            foreach ($this->getGTMCustomParams() as $param){
                $params[ $this->getManualCustomObjectName() ][ $param['name'] ] = $param['value'];
            }
        }

        return $params;
    }
    public function getMergedGaParams() {
        if(is_array($this->ga_ads_params)) {
            return $this->ga_ads_params;
        } else {
            return [];
        }
    }


    public function getGoogleAnalyticsAction() {
        return $this->ga_event_action == '_custom' || $this->ga_event_action ==  'CustomEvent' ? $this->ga_custom_event_action : $this->ga_event_action;
    }

    public function getMergedAction(){
        return $this->ga_ads_event_action == '_custom' || $this->ga_ads_event_action ==  'CustomEvent' ? $this->ga_ads_custom_event_action : $this->ga_ads_event_action;
    }

    public function getGTMAction(){
        return $this->gtm_event_action == '_custom' || $this->gtm_event_action ==  'CustomEvent' ? $this->gtm_custom_event_action : $this->gtm_event_action;
    }

    public function isGoogleAdsEnabled() {
        return (bool) $this->google_ads_enabled;
    }
    public function isGoogleAdsPresent() {
        $allValues = array_merge(GA()->getAllPixels(), Ads()->getAllPixels());
        $selectedValues = (array) $this->ga_ads_pixel_id;

        $hasAWElement = !empty($selectedValues) && (
                (
                    in_array('all', $selectedValues) &&
                    (bool) array_filter($allValues, function($value) {
                        return is_string($value) && strpos($value, 'AW') === 0;
                    })
                ) ||
                (bool) array_filter($selectedValues, function($value) {
                    return is_string($value) && strpos($value, 'AW') === 0;
                })
            );

        return $hasAWElement;
    }
    public function getGoogleAdsAction() {
        return $this->google_ads_event_action == '_custom' ? $this->google_ads_custom_event_action : $this->google_ads_event_action;
    }
    public function getGoogleAdsEventCategory() {
        return $this->google_ads_event_category;
    }
    public function getGoogleAdsEventLabel() {
        return $this->google_ads_event_label;
    }
    public function getGoogleAdsCustomParams() {
        return  (array)$this->google_ads_custom_params;
    }

    public function getGTMCustomParamsAdmin() {
        return  $this->gtm_custom_params;
    }
    public function getGTMCustomParams() {
        $params = [];
        foreach ($this->gtm_custom_params as $param){
            $params[] = apply_filters( 'pys_superpack_dynamic_params', $param, 'gtm' );
        }
        return  $params;
    }

    public function isUnifyAnalyticsEnabled(){
        return (bool) $this->ga_ads_enabled;
    }

    public function isBingEnabled() {
        return (bool) $this->bing_enabled;
    }

    public function isGaV4() {
        return true;
        /*$tag = $this->data['ga_pixel_id'];
        if (is_array($tag)) {
            foreach ($tag as $t) {
                if (!is_string($t)) {
                    return false;
                }
                if (strpos($t, 'G') === 0 || $t == 'all') {
                    return true;
                }
            }
            return false;
        } else {
            return strpos($tag, 'G') === 0 || $tag == 'all';
        }*/
    }

    private function clearGa() {
        $this->data['ga_params'] = array();
        $this->data['ga_custom_params'] = array();
        $this->data['ga_event_action'] = 'CustomEvent';
        $this->data['ga_custom_event_action']=null;
    }

    private function clearGTM() {
        $this->data['gtm_params'] = array();
        $this->data['gtm_custom_params'] = array();
        $this->data['gtm_event_action'] = 'CustomEvent';
        $this->data['gtm_custom_event_action']=null;
    }

    function migrateUnifyGA() {

        $all = array_merge(GA()->getAllPixels(false), Ads()->getAllPixels(false));
        $this->data['ga_ads_enabled'] = $this->isGoogleAnalyticsEnabled() || $this->isGoogleAdsEnabled() ? true : false;
        if(!empty($this->data['ga_ads_pixel_id'])){ return; }
        if(!empty($this->data['ga_pixel_id']) && !empty($this->data['google_ads_conversion_id'])){
            $pixel_ids = array_unique(array_merge((array)$this->data['ga_pixel_id'], (array)$this->data['google_ads_conversion_id']));
        }
        elseif (!empty($this->data['ga_pixel_id'])){
            $pixel_ids = (array)$this->data['ga_pixel_id'];
        }
        elseif (!empty($this->data['google_ads_conversion_id'])){
            $pixel_ids = (array)$this->data['google_ads_conversion_id'];
        }
        else{
            return;
        }

        $this->data['ga_ads_pixel_id']  = array_map(function($pixelId) use ($all) {
            if (in_array($pixelId, $all) || $pixelId == 'all') {
                return $pixelId;
            } else {
                return '';
            }
        }, $pixel_ids);

        $this->data['ga_ads_pixel_id'] = array_filter($this->data['ga_ads_pixel_id']);
        if($this->isGoogleAdsEnabled() && $this->data['google_ads_conversion_label']) {
            $this->data['ga_ads_conversion_label'] = $this->data['google_ads_conversion_label'];
        }

        if($this->isGoogleAnalyticsEnabled()){
            $this->data['ga_ads_event_action'] = $this->ga_event_action;
            $this->data['ga_ads_custom_event_action'] = $this->ga_event_action == '_custom' || $this->ga_event_action ==  'CustomEvent' ? $this->ga_custom_event_action : '';
            $this->data['ga_ads_params'] = $this->getGaParams();
            $this->data['ga_ads_custom_params'] = $this->getGACustomParams();
        }elseif ($this->isGoogleAdsEnabled()){
            $this->data['ga_ads_event_action'] = $this->google_ads_event_action;
            $this->data['ga_ads_custom_event_action'] = $this->google_ads_event_action == '_custom' || $this->google_ads_event_action ==  'CustomEvent' ? $this->google_ads_custom_event_action : '';
            $this->data['ga_ads_params'] = array();
            $this->data['ga_ads_custom_params'] = $this->getGoogleAdsCustomParams();
        }
        if($this->isGoogleAdsEnabled()){
            $this->data['ga_ads_custom_params'][] = array('name'=> 'event_category', 'value' => $this->getGoogleAdsEventCategory());
            $this->data['ga_ads_custom_params'][] = array('name'=> 'event_label', 'value' => $this->getGoogleAdsEventLabel());
        }
        $outputArray = [];

        foreach ($this->data['ga_ads_custom_params'] as $item) {
            $key = $item["name"];
            if (!isset($outputArray[$key])) {
                $outputArray[$key] = $item;
            }
        }
        $this->data['ga_ads_custom_params'] = array_values($outputArray);
        update_post_meta( $this->post_id, '_pys_event_data', $this->data );
    }

    private function updateUnifyGA($args){
        $all = array_merge(GA()->getAllPixels(false), Ads()->getAllPixels(false));

        if(!empty( $args['ga_ads_pixel_id'] )) {
            $this->data['ga_ads_pixel_id'] = array_map(function($pixelId) use ($all) {
                if (in_array( $pixelId,$all) || $pixelId == 'all') {
                    return $pixelId;
                }
            }, $args['ga_ads_pixel_id']);
        } elseif (count($all) > 0) {
            $this->data['ga_ads_pixel_id'] = (array) $all[0];
        } else {
            $this->data['ga_ads_pixel_id'] = [];
        }

        $this->data['ga_ads_enabled'] = isset( $args['ga_ads_enabled']  )
            && $args['ga_ads_enabled'];

        $this->data['ga_ads_event_action'] = isset( $args['ga_ads_event_action'] )
            ? sanitize_text_field( $args['ga_ads_event_action'] )
            : 'view_item';
        $this->data['ga_ads_custom_event_action'] = (isset( $args['ga_ads_event_action'] ) && ($args['ga_ads_event_action'] == '_custom' || $args['ga_ads_event_action'] == 'CustomEvent')) && !empty($args['ga_ads_custom_event_action'])
            ? sanitizeKey( $args['ga_ads_custom_event_action'] )
            : null;
        $this->data['ga_ads_params'] = array();

        foreach ($this->GAEvents as $group) {
            foreach ($group as $name => $fields) {
                if($name == $this->data['ga_ads_event_action']) {
                    foreach ($fields as $field) {
                        $this->data['ga_ads_params'][$field] = isset($args['ga_ads_params'][$field]) ? $args['ga_ads_params'][$field] : "";
                    }
                    break;
                }
            }
        }

        $this->data['ga_ads_event_action_group'] = $args['ga_ads_event_action_group'] ?? '';

        if ( isset( $args['ga_ads_params'] ) ) {
            foreach ($args['ga_ads_params'] as $key => $val) {
                $this->data['ga_ads_params'][$key] = sanitize_text_field( $val );
            }
        }

        $this->data['ga_ads_track_single_woo_data'] = isset( $args['ga_ads_track_single_woo_data'] ) && $args['ga_ads_track_single_woo_data'] ? true : false;
        $this->data['ga_ads_track_cart_woo_data'] = isset( $args['ga_ads_track_cart_woo_data'] ) && $args['ga_ads_track_cart_woo_data'] ? true : false;

        // reset old custom params
        $this->data['ga_ads_custom_params'] = array();

        // custom params
        if ( isset( $args['ga_ads_custom_params'] ) ) {

            foreach ( $args['ga_ads_custom_params'] as $custom_param ) {

                if ( ! empty( $custom_param['name'] ) && ! empty( $custom_param['value'] ) ) {

                    $this->data['ga_ads_custom_params'][] = array(
                        'name'  => sanitize_text_field( $custom_param['name'] ),
                        'value' => sanitize_text_field( $custom_param['value'] ),
                    );

                }

            }

        }
        $sanitizeGoogleAdsConversionLabel = function ($label) {
            return wp_kses_post( trim( stripslashes( $label ) ) );
        };
        $this->data['ga_ads_conversion_label'] = ! empty( $args['ga_ads_conversion_label'] )
            ? $sanitizeGoogleAdsConversionLabel( $args['ga_ads_conversion_label'] )
            : null;
    }
    private function updateGTM($args)
    {
        $all = GTM()->getAllPixels();
        if(!empty( $args['gtm_pixel_id'] )) {
            $this->data['gtm_pixel_id'] = array_map(function($pixelId) use ($all) {
                if (in_array( $pixelId,$all)) {
                    return $pixelId;
                }
            }, $args['gtm_pixel_id']);
        } elseif (count($all) > 0) {
            $this->data['gtm_pixel_id'] = (array) $all[0];
        } else {
            $this->data['gtm_pixel_id'] = [];
        }

        $this->data['gtm_enabled'] = isset( $args['gtm_enabled']  )
            && $args['gtm_enabled'];

        $this->data['gtm_automated_param'] = isset( $args['gtm_automated_param']  )
            && $args['gtm_automated_param'];

        $this->data['gtm_remove_customTrigger'] = isset( $args['gtm_remove_customTrigger']  )
            && $args['gtm_remove_customTrigger'];

        $this->data['gtm_use_custom_object_name'] = isset( $args['gtm_use_custom_object_name']  )
            && $args['gtm_use_custom_object_name'];

        $this->data['gtm_custom_object_name'] = !empty($args['gtm_custom_object_name']) ? sanitize_text_field( $args['gtm_custom_object_name'] ) : 'manual_'.$this->transformTitle();

        $this->data['gtm_event_action'] = isset( $args['gtm_event_action'] )
            ? sanitize_text_field( $args['gtm_event_action'] )
            : 'view_item';
        $this->data['gtm_custom_event_action'] = (isset( $args['gtm_event_action'] ) && ($args['gtm_event_action'] == '_custom' || $args['gtm_event_action'] == 'CustomEvent')) && !empty($args['gtm_custom_event_action'])
            ? sanitizeKey( $args['gtm_custom_event_action'] )
            : null;
        $this->data['gtm_params'] = array();

        foreach ($this->GAEvents as $group) {
            foreach ($group as $name => $fields) {
                if($name == $this->data['gtm_event_action']) {
                    foreach ($fields as $field) {
                        $this->data['gtm_params'][$field] = isset($args['gtm_params'][$field]) ? $args['gtm_params'][$field] : "";
                    }
                    break;
                }
            }
        }

        $this->data['gtm_event_action_group'] = $args['gtm_event_action_group'] ?? '';

        if ( isset( $args['gtm_params'] ) ) {
            foreach ($args['gtm_params'] as $key => $val) {
                $this->data['gtm_params'][$key] = sanitize_text_field( $val );
            }
        }

        $this->data['gtm_track_single_woo_data'] = isset( $args['gtm_track_single_woo_data'] ) && $args['gtm_track_single_woo_data'] ? true : false;
        $this->data['gtm_track_cart_woo_data'] = isset( $args['gtm_track_cart_woo_data'] ) && $args['gtm_track_cart_woo_data'] ? true : false;

        // reset old custom params
        $this->data['gtm_custom_params'] = array();

        // custom params
        if ( isset( $args['gtm_custom_params'] ) ) {

            foreach ( $args['gtm_custom_params'] as $custom_param ) {

                if ( ! empty( $custom_param['name'] ) && ! empty( $custom_param['value'] ) ) {

                    $this->data['gtm_custom_params'][] = array(
                        'name'  => sanitize_text_field( $custom_param['name'] ),
                        'value' => sanitize_text_field( $custom_param['value'] ),
                    );

                }

            }

        }
    }
    private function updateTikTok($args) {
        $tiktok_event_types = ['CustomEvent','ViewContent','ClickButton','Search',
            'AddToWishlist','AddToCart','InitiateCheckout','AddPaymentInfo',
            'CompletePayment','PlaceAnOrder','Contact','Download','SubmitForm',
            'CompleteRegistration','Subscribe'];
        $standard_params = [
            'content_id',
            'content_type',
            'content_category',
            'content_name',
            'currency',
            'value',
            'quantity',
            'price',
            'query',
        ];
        // enabled
        $this->data['tiktok_enabled'] = isset( $args['tiktok_enabled'] ) && $args['tiktok_enabled'] ? true : false;

        //pixel id
        $this->data['tiktok_pixel_id'] = !empty( $args['tiktok_pixel_id'] )
        && in_array( $args['tiktok_pixel_id'], Tiktok()->getAllPixels() )
            ? $args['tiktok_pixel_id'] : 'all';

        // event type
        $this->data['tiktok_event_type'] = isset( $args['tiktok_event_type'] ) && in_array( $args['tiktok_event_type'], $tiktok_event_types )
            ? sanitize_text_field( $args['tiktok_event_type'] )
            : 'ViewContent';

        // custom event type
        $this->data['tiktok_custom_event_type'] = $this->tiktok_event_type == 'CustomEvent' && ! empty( $args['tiktok_custom_event_type'] )
            ? sanitizeKey( $args['tiktok_custom_event_type'] )
            : null;

        // params enabled
        $this->data['tiktok_params_enabled'] = isset( $args['tiktok_params_enabled'] ) && $args['tiktok_params_enabled'] ? true : false;

        $this->data['tiktok_track_single_woo_data'] = isset( $args['tiktok_track_single_woo_data'] ) && $args['tiktok_track_single_woo_data'] ? true : false;
        $this->data['tiktok_track_cart_woo_data'] = isset( $args['tiktok_track_cart_woo_data'] ) && $args['tiktok_track_cart_woo_data'] ? true : false;

        // params
        if ( $this->tiktok_params_enabled && isset( $args['tiktok_params'] ) && $this->tiktok_event_type !== 'CustomEvent' ) {

            $params = [];
            foreach ($standard_params as $standard) {
                $params[$standard] = ! empty( $args['tiktok_params'][$standard] ) ? sanitize_text_field( $args['tiktok_params'][$standard] ) : null;
            }
        } else {
            // clear all
            $params = [];
            foreach ($standard_params as $standard) {
                $params[$standard] =  null;
            }
        }
        $this->data['tiktok_params'] = $params;

    }

	private function updateRedditParams( $args ) {
		$reddit_event_types = array_keys( self::$redditEvents );

		//TODO check event names
		$standard_params = [
			'itemCount',
			'currency',
			'value',
		];
		// enabled
		$this->data[ 'reddit_enabled' ] = isset( $args[ 'reddit_enabled' ] ) && $args[ 'reddit_enabled' ];

		//pixel id
		$this->data[ 'reddit_pixel_id' ] = !empty( $args[ 'reddit_pixel_id' ] )
		                                   && in_array( $args[ 'reddit_pixel_id' ], Reddit()->getAllPixels() ) ? $args[ 'reddit_pixel_id' ] : 'all';

		// event type
		$this->data[ 'reddit_event_type' ] = isset( $args[ 'reddit_event_type' ] )
		                                     && in_array( $args[ 'reddit_event_type' ], $reddit_event_types ) ? sanitize_text_field( $args[ 'reddit_event_type' ] ) : 'ViewContent';

		// custom event type
		$this->data[ 'reddit_custom_event_type' ] = $this->reddit_event_type == 'Custom'
		                                            && !empty( $args[ 'reddit_custom_event_type' ] ) ? sanitizeKey( $args[ 'reddit_custom_event_type' ] ) : null;

		// params enabled
		$this->data[ 'reddit_params_enabled' ] = isset( $args[ 'reddit_params_enabled' ] )
		                                         && $args[ 'reddit_params_enabled' ];

		$this->data[ 'reddit_track_single_woo_data' ] = isset( $args[ 'reddit_track_single_woo_data' ] )
		                                                && $args[ 'reddit_track_single_woo_data' ];
		$this->data[ 'reddit_track_cart_woo_data' ]   = isset( $args[ 'reddit_track_cart_woo_data' ] )
		                                                && $args[ 'reddit_track_cart_woo_data' ];

		// params
		$params = [];
		if ( $this->reddit_params_enabled && isset( $args[ 'reddit_params' ] ) ) {

			foreach ( $standard_params as $standard ) {
				$params[ $standard ] = !empty( $args[ 'reddit_params' ][ $standard ] ) ? sanitize_text_field( $args[ 'reddit_params' ][ $standard ] ) : null;
			}
		} else {
			// clear all
			foreach ( $standard_params as $standard ) {
				$params[ $standard ] = null;
			}
		}
		$this->data[ 'reddit_params' ] = $params;
	}

    /**
     * @return bool
     */
    public function isTikTokEnabled() {
        return (bool) $this->tiktok_enabled;
    }

    public function getTikTokEventType() {
        return $this->tiktok_event_type == 'CustomEvent' ? $this->tiktok_custom_event_type : $this->tiktok_event_type;
    }


    /**
     * Get delay for event
     * @return mixed|null
     */
    public function getDelay () {
        $delay = null;
        if (!empty($this->triggers)) {
            $delays = array();
            foreach ( $this->triggers as $trigger ) {
                $delays[] = $trigger->getParam('delay');
            }
            $delay = max($delays);
        }

        return $delay;
    }
    public function checkConditions()
    {
        $conditions_enabled = $this->__get('conditions_enabled');
        $conditions_logic = $this->__get('conditions_logic');
        $conditions = $this->getConditions();
        $check = true;

        if($conditions_enabled && !empty($conditions)){
            $conditions_results = [];
            foreach ($conditions as $condition) {
                $condition_result = $condition->check();
                $conditions_results[] = $condition_result;
            }


            if($conditions_logic === 'AND'){
                $check = !in_array(false, $conditions_results);
            }
            else{
                $check =  in_array(true, $conditions_results);
            }
        }
        return $check;
    }
}