<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/google_gtm/function-helpers.php';

use PixelYourSite\GTM\Helpers;
use WC_Product;

class GTM extends Settings implements Pixel {

    private static $_instance;
    private $isEnabled;
    private $configured;

    private $googleBusinessVertical;
    private $checkout_step = 2;
    /** @var array $wooOrderParams Cached WooCommerce Purchase and AM events params */
    private $wooOrderParams = array();

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    public function __construct() {

        parent::__construct( 'gtm' );

        $this->locateOptions(
            PYS_PATH . '/modules/google_gtm/options_fields.json',
            PYS_PATH . '/modules/google_gtm/options_defaults.json'
        );
        add_action( 'pys_register_pixels', function( $core ) {
            /** @var PYS $core */
            $core->registerPixel( $this );
        } );

        add_action( 'init', array($this, 'init'), 1 );
    }

    public function init()
    {
        $this->isEnabled = $this->enabled();
        if($this->isEnabled) {
            add_action('wp_head', array($this, 'pys_wp_header_top'), 1, 0);
        }

        $this->googleBusinessVertical = PYS()->getOption( 'google_retargeting_logic' ) === 'ecomm' ? 'retail' : 'custom';
    }

    public function enabled() {
        $enabled = $this->getOption( 'main_pixel_enabled' ) && ($this->getOption( 'gtm_id' ) || $this->getOption( 'gtm_just_data_layer' ));
        return $enabled;
    }

    public function configured() {

        $license_status = PYS()->getOption( 'license_status' );
		$main_pixel_enabled = $this->getOption( 'main_pixel_enabled' );

        $disabledPixel =  apply_filters( 'pys_pixel_disabled', false, $this->getSlug() );
        $this->configured = $this->enabled()
            && ! empty( $license_status ) // license was activated before
            && $disabledPixel != '1' && $disabledPixel != 'all'
			&& $main_pixel_enabled;


        return $this->configured;

    }
    public function pys_wp_header_top( $echo = true ) {

        $has_html5_support    = current_theme_supports( 'html5' );
        $gtm_dataLayer_name = $this->getOption( 'gtm_dataLayer_name' ) ?? 'dataLayer';
        // the data layer initialization has to use 'var' instead of 'let' since 'let' can break related browser extension and 3rd party script.
        $_gtm_top_content = '
<!-- Google Tag Manager by PYS -->
<script data-cfasync="false" data-pagespeed-no-defer' . ( $has_html5_support ? ' type="text/javascript"' : '' ) . '>
	var pys_datalayer_name = "' . esc_js( $gtm_dataLayer_name ) . '";
	window.' . esc_js( $gtm_dataLayer_name ) . ' = window.' . esc_js( $gtm_dataLayer_name ) . ' || [];';

        if($this->getOption( 'check_list') != 'disabled' && !empty($this->getOption( 'check_list_contain'))){
            $elementName = 'gtm.'.$this->getOption( 'check_list');
            $element = [];
            $element[$elementName] = array_values($this->getOption( 'check_list_contain'));
            $_gtm_top_content .= esc_js( $gtm_dataLayer_name ).'.push('.json_encode($element).');';
        }
        $_gtm_top_content .= '</script> 
<!-- End Google Tag Manager by PYS -->';

        if ( $echo ) {
            echo wp_kses(
                $_gtm_top_content,
                array(
                    'script' => array(
                        'data-cfasync'            => array(),
                        'data-pagespeed-no-defer' => array(),
                        'data-cookieconsent'      => array(),
                    ),
                )
            );
        } else {
            return $_gtm_top_content;
        }
    }
    public function getPixelIDs() {

        $ids = (array) $this->getOption( 'gtm_id' );

        if(count($ids) == 0|| empty($ids[0])) {
            return apply_filters("pys_gtm_ids",[]);
        } else {
			$id = array_shift($ids);
			return apply_filters("pys_gtm_ids", array($id)); // return first id only
        }
    }


    public function getPixelOptions() {
        $options = array(
            'trackingIds'                   => $this->getAllPixels(),
            'gtm_dataLayer_name'            => $this->getOption( 'gtm_dataLayer_name' ),
            'gtm_container_domain'          => $this->getOption( 'gtm_container_domain' ),
            'gtm_container_identifier'      => $this->getOption( 'gtm_container_identifier' ),
            'gtm_auth'                      => $this->getOption( 'gtm_auth' ),
            'gtm_preview'                   => $this->getOption( 'gtm_preview' ),
            'gtm_just_data_layer'           => $this->getOption( 'gtm_just_data_layer' ),
            'check_list'                    => $this->getOption( 'check_list' ),
            'check_list_contain'            => $this->getOption( 'check_list_contain' ),
            'wooVariableAsSimple'           => GTM()->getOption( 'woo_variable_as_simple' ),
        );

        return $options;
    }

    public function updateOptions( $values = null ) {
        if(!isset($_POST['pys'][ $this->getSlug() ]['select_all_blacklist'])) {
            $_POST['pys'][ $this->getSlug() ]['select_all_blacklist'] = array();
        }
        parent::updateOptions($values);
    }
    /**
     * Create pixel event and fill it
     * @param SingleEvent $event
     */
    public function generateEvents($event) {
        $track_event = true;
        if ( ! $this->configured() ) {
            return [];
        }
        $pixelEvents = [];
        $disabledPixel =  apply_filters( 'pys_pixel_disabled', array(), $this->getSlug() );


        if($disabledPixel == '1' || $disabledPixel == 'all') return [];
        if(is_array($disabledPixel) && (in_array('1', $disabledPixel) || in_array('all', $disabledPixel))) return [];
        $hide_pixels = apply_filters('hide_pixels', array());
        $disabledPixel = array_merge($disabledPixel, $hide_pixels);


        if($event->getId() == 'woo_remove_from_cart') {
            $product_id = $event->args['item']['product_id'];
            add_filter('pys_conditional_post_id', function($id) use ($product_id) { return $product_id; });
        }

        $pixelIds = $this->getAllPixelsForEvent($event);

        if($event->getId() == 'woo_remove_from_cart') {
            remove_all_filters('pys_conditional_post_id');
        }

        if($event->getId() == 'custom_event'){

			$all_pixels = $this->getAllPixels(false);
            $preselectedPixel = array_filter((array) $event->args->gtm_pixel_id, static function ($element) use ($all_pixels) {
		            return in_array( $element, $all_pixels, true ) || $element == 'all';
            });


            if(is_array($preselectedPixel)){
                if(in_array('all', $preselectedPixel)){
                        $pixelIds = $all_pixels;
                }
                else{
                    $pixelIds = array_filter($preselectedPixel, static function ($element) use ($disabledPixel) {
                        return !in_array($element, $disabledPixel);
                    });
                    $pixelIds = array_values($pixelIds);
                }

            }else{
                if($preselectedPixel == 'all') {
                    if(count($pixelIds) > 0) {
                        $preselectedPixel = $pixelIds[0];
                    }
                }

                if(!in_array($preselectedPixel,$pixelIds) || $preselectedPixel == $disabledPixel) {
                    return []; // not fire event if pixel id was disabled or deleted
                }

                $pixelIds = [$preselectedPixel];
            }
            $event->payload['trackingIds'] = $pixelIds;

        } else {
            // filter disabled pixels
            if(!empty($disabledPixel)) {
                if(is_array($disabledPixel))
                {
                    $pixelIds = array_filter($pixelIds, static function ($element) use ($disabledPixel) {
                        return !in_array($element, $disabledPixel);
                    });
                    $pixelIds = array_values($pixelIds);
                }
                else
                {
                    foreach ($pixelIds as $key => $value) {
                        if($value == $disabledPixel) {
                            array_splice($pixelIds,$key,1);
                        }
                    }
                }
            }
        }

        $listOfEddEventWithProducts = ['edd_add_to_cart_on_checkout_page','edd_initiate_checkout','edd_purchase','edd_frequent_shopper','edd_vip_client','edd_big_whale','edd_view_category'];
        $listOfWooEventWithProducts = [
            'woo_view_cart',
            'woo_initiate_checkout_progress_o',
            'woo_initiate_checkout_progress_e',
            'woo_initiate_checkout_progress_l',
            'woo_initiate_checkout_progress_f',
            'woo_purchase',
            'woo_initiate_checkout',
            'woo_paypal',
            'woo_add_to_cart_on_checkout_page',
            'woo_add_to_cart_on_cart_page',
            'woo_view_category',
            'woo_view_item_list',
            'woo_view_item_list_single',
            'woo_view_item_list_search',
            'woo_view_item_list_shop',
            'woo_view_item_list_tag'
        ];
        $isWooEventWithProducts = in_array($event->getId(),$listOfWooEventWithProducts);
        $isEddEventWithProducts = in_array($event->getId(),$listOfEddEventWithProducts);

        if(isSuperPackActive('3.0.0') && SuperPack()->getOption( 'enabled' ) )
        {

            $pixelEvent = clone $event;
            $main_pixel = $this->getPixelIDs();
            $pixels_for_filter = array();
            if (!empty($main_pixel) || $this->getOption( 'gtm_just_data_layer' )) {
                $main_pixel_options = $this->getOption('main_pixel');
                if (!empty($main_pixel_options) && isset($main_pixel_options[0])) {
                    $main_pixel_options = $this->normalizeSPOptions($main_pixel[0] ?? null, $main_pixel_options[0]);
                } else {
                    $main_pixel_options = $this->normalizeSPOptions($main_pixel[0] ?? null, '');
                }
                $pixels_for_filter[] = SuperPack\SPPixelId::fromArray( $main_pixel_options );
            }

            foreach ($pixels_for_filter as $_pixel) {
                $filter = null;
                if(!$_pixel->isValidForEvent($event, null, $this->getOption( 'gtm_just_data_layer' )) || in_array($_pixel->pixel, $disabledPixel)) {continue;}
                if($isWooEventWithProducts) {
                    $filter = $_pixel->getWooFilter();
                }
                if($isEddEventWithProducts) {
                    $filter = $_pixel->getEddFilter();
                }
                if($filter != null) {
                    $products = [];
                    $containsAllFilter = array_filter($filter, function($item) {
                        return $item['filter'] == "all";
                    });
                    if($containsAllFilter) {
                        $products = $pixelEvent->args['products'];
                    } else {
                        if($isWooEventWithProducts) {
                            $products = EventsWoo()->filterEventProductsBy($event,$filter,$_pixel);
                            $pixelEvent->args['products'] = $products;
                            $pixelEvent->args['filter_product'] = true;
                        }
                        if($isEddEventWithProducts) {
                            $products = EventsEdd()->filterEventProductsBy($event,$filter,$_pixel);
                            $pixelEvent->args['products'] = $products;
                            $pixelEvent->args['filter_product'] = true;
                        }
                    }
                    if(!empty($products)){
                        $pixelEvent->payload['trackingIds'] = [$_pixel->pixel];
                        if ($this->addParamsToEvent( $pixelEvent ) ) {
                            $pixelEvents[] = $pixelEvent;
                        }
                    }
                } else {
                    if(!$_pixel->isConditionalValidForEvent( $event )){continue;}
                    $pixelEvent->payload['trackingIds'] = [$_pixel->pixel];
                    if ( $this->addParamsToEvent( $pixelEvent ) ) {
                        $pixelEvents[] = $pixelEvent;
                    }
                }
            }
        }
        else
        {
                $pixelEvent = clone $event;
                $pixelEvent->payload['trackingIds'] = $pixelIds;
                if($this->addParamsToEvent($pixelEvent)) {
                    $pixelEvents[] = $pixelEvent;
                }
        }

        return $pixelEvents;
    }

    /**
     * @param SingleEvent $event
     * @return boolean
     */
    private function addParamsToEvent(&$event) {
        if ( ! $this->configured() ) {
            return false;
        }
        $isActive = false;
	    $triggerType = Helpers\getTriggerType($event->getId());
	    if ($triggerType) {
		    $event->addParams(['triggerType' => ['type' => $triggerType]]);
	    }
        switch ($event->getId()) {
            case 'init_event':{
                $eventData = $this->getOption('track_page_view') ? $this->getPageViewEventParams() : false;
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            //Automatic events
            case "automatic_event_video":{
                $event->addPayload(
                    array('youtube_disabled'=>$this->getOption("automatic_event_video_youtube_disabled"))
                );
                $isActive = $this->getOption($event->getId().'_enabled');
            }break;
            case 'automatic_event_signup' : {
                $event->addPayload(["name" => "sign_up"]);
                $isActive = $this->getOption($event->getId().'_enabled');
            } break;
            case 'automatic_event_login' :{
                $event->addPayload(["name" => "login"]);
                $isActive = $this->getOption($event->getId().'_enabled');
            } break;
	        case 'automatic_event_404' :{
		        $event->addPayload(["name" => "404"]);
		        $isActive = $this->getOption($event->getId().'_enabled');
	        } break;
            case 'automatic_event_search' :{
                $event->addPayload(["name" => "search"]);
                $event->addParams([
                    "search_term" =>  empty( $_GET['s'] ) ? null : $_GET['s'],

                ]);
                $isActive = $this->getOption($event->getId().'_enabled');
            } break;
            case 'automatic_event_tel_link' :
            case 'automatic_event_email_link':
            case 'automatic_event_form' :
            case 'automatic_event_download' :
            case 'automatic_event_comment' :
            case 'automatic_event_adsense' :
            case 'automatic_event_scroll' :
            case 'automatic_event_time_on_page' :
            case "automatic_event_outbound_link":
            case "automatic_event_internal_link": {
                $isActive = $this->getOption($event->getId().'_enabled');
            }break;

            case 'woo_frequent_shopper':
            case 'woo_vip_client':
            case 'woo_big_whale':
            case 'woo_FirstTimeBuyer':
            case 'woo_ReturningCustomer':{
                $eventData =  $this->getWooAdvancedMarketingEventParams( $event->getId() );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'woo_view_content': {
                if(!$this->getOption('woo_view_content_enabled')) return;
                $eventData =  $this->getWooViewContentEventParams($event->args, $event);
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'woo_view_cart': {
                $isActive =  $this->getWooViewCartEventParams($event);
            }break;
            case 'woo_view_item_list':
                {
                    if(!$this->getOption('woo_enable_list_category')) return;
                    $eventData = $this->getWooViewCategoryEventParams($event);
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;
            case 'woo_view_item_list_single':
                {
                    if(!$this->getOption('woo_enable_list_related')) return;
                    $eventData = $this->getWooViewItemListSingleParams();
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;
            case "woo_view_item_list_search":{
                if(!$this->getOption('woo_enable_list_shop')) return;
                $eventData =  $this->getWooViewItemListSearch();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case "woo_view_item_list_shop":{
                if(!$this->getOption('woo_enable_list_shop')) return;
                $eventData =  $this->getWooViewItemListShop();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case "woo_view_item_list_tag":{
                if(!$this->getOption('woo_enable_list_tags')) return;
                $eventData =  $this->getWooViewItemListTag();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'woo_add_to_cart_on_cart_page':
            case 'woo_add_to_cart_on_checkout_page':{
                $isActive =  $this->setWooAddToCartOnCartEventParams($event);
            }break;
            case 'woo_initiate_checkout':{
                $isActive =  $this->setWooInitiateCheckoutEventParams($event);

            }break;
            case 'woo_purchase':{
                $isActive =  $this->getWooPurchaseEventParams($event);

            }break;
            case 'woo_initiate_set_checkout_option':{
                $eventData =  $this->getWooSetСheckoutOptionEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_initiate_checkout_progress_f':
            case 'woo_initiate_checkout_progress_l':
            case 'woo_initiate_checkout_progress_e':
            case 'woo_initiate_checkout_progress_o':{
                $isActive =  $this->setWooCheckoutProgressEventParams($event);
            }break;
            case 'woo_remove_from_cart':{
                $eventData =  $this->getWooRemoveFromCartParams( $event->args['item'] );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);

                }
            }break;
            case 'woo_paypal':{
                $isActive =  $this->setWooPayPalEventParams($event);

            }break;
            case "woo_select_content_category":
                $isActive = $this->getOption('woo_enable_list_category') ? $this->getWooSelectContent("category",$event) : false;break;
            case "woo_select_content_single":
                $isActive = $this->getOption('woo_enable_list_related') || $this->getOption('woo_enable_list_shortcodes') ? $this->getWooSelectContent("single",$event) : false;break;
            case "woo_select_content_search":
                $isActive = $this->getOption('woo_enable_list_shop') ? $this->getWooSelectContent("search",$event) : false;break;
            case "woo_select_content_shop":
                $isActive = $this->getOption('woo_enable_list_shop') ? $this->getWooSelectContent("shop",$event) : false;break;
            case "woo_select_content_tag":
                $isActive = $this->getOption('woo_enable_list_tag') ? $this->getWooSelectContent("tag",$event) : false;break;
            //Edd
            case 'edd_view_content': {
                $eventData = $this->getEddViewContentEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'edd_add_to_cart_on_checkout_page':  {
                $isActive = $this->setEddCartEventParams($event);

            }break;

            case 'edd_remove_from_cart': {
                $eventData =  $this->getEddRemoveFromCartParams( $event->args['item'] );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'edd_view_category': {
                $eventData = $this->getEddViewCategoryEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'edd_initiate_checkout': {
                $isActive = $this->setEddCartEventParams($event);

            }break;

            case 'edd_purchase': {
                $isActive = $this->setEddCartEventParams($event);

            }break;
            case 'edd_refund': {
                $isActive = $this->setEddCartEventParams($event);

            }break;
            case 'edd_frequent_shopper':
            case 'edd_vip_client':
            case 'edd_big_whale': {
                $isActive = $this->setEddCartEventParams($event);
            }break;


            case 'custom_event': {
                $eventData = $this->getCustomEventData($event);
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'woo_add_to_cart_on_button_click': {

                if (  $this->getOption( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_button_click' ) ) {
					$isActive = true;
                    if(isset($event->args['productId'])) {
                        $eventData =  $this->getWooAddToCartOnButtonClickEventParams(  $event->args );

                        if($eventData) {
                            $event->addParams($eventData["params"]);
                            unset($eventData["params"]);
                            $event->addPayload($eventData);
                        }
                    }


                    $event->addPayload(array(
                        'name'=>"add_to_cart"
                    ));
                }
            }break;

            case 'woo_affiliate': {
                if (  $this->getOption( 'woo_affiliate_enabled' ) ) {
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $productId = $event->args['productId'];
                        $quantity = $event->args['quantity'];
                        $eventData =  $this->getWooAffiliateEventParams( $productId,$quantity );
                        if($eventData) {
                            $event->addParams($eventData["params"]);
                            unset($eventData["params"]);
                            $event->addPayload($eventData);
                        }
                    }

                }
            }break;

            case 'edd_add_to_cart_on_button_click': {
                if (  $this->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
                    $isActive = true;
                    if($event->args != null) {
                        $eventData =  $this->getEddAddToCartOnButtonClickEventParams( $event->args );
                        $event->addParams($eventData);
                    }
                    $event->addPayload(array(
                        'name'=>"add_to_cart"
                    ));
                }
            }break;

            case 'wcf_view_content': {
                $isActive =  $this->getWcfViewContentEventParams($event);
            }break;
            case 'wcf_add_to_cart_on_bump_click':
            case 'wcf_add_to_cart_on_next_step_click': {
                $isActive = $this->prepare_wcf_add_to_cart($event);
            }break;

            case 'wcf_remove_from_cart_on_bump_click': {
                $isActive = $this->prepare_wcf_remove_from_cart($event);
            } break;

            case 'wcf_bump': {
                $isActive = $this->getOption('wcf_bump_event_enabled');
            }break;

            case 'wcf_page': {
                $isActive = $this->getOption('wcf_cart_flows_event_enabled');
            }break;

            case 'wcf_step_page': {
                $isActive = $this->getOption('wcf_step_event_enabled');
            }break;

            case 'wcf_lead': {
                $isActive = PYS()->getOption('wcf_lead_enabled');
            }break;

        }

        if($isActive) {
            if( !isset($event->payload['trackingIds'])) {
                $event->payload['trackingIds'] = $this->getAllPixelsForEvent($event);
            }
        }
        return $isActive;
    }

    private function addDataToEvent($eventData,&$event) {
        $params = $eventData["data"];
        unset($eventData["data"]);
        //unset($eventData["name"]);
        $event->addPayload($eventData);
        $event->addParams($params);

    }

    public function getEventData( $eventType, $args = null ) {
        return false;
    }

    public function outputNoScriptEvents() {

        if ( ! $this->configured() || $this->getOption('disable_noscript') || $this->getOption('gtm_just_data_layer') || empty($this->getPixelIDs())) {
            return;
        }

        $eventsManager = PYS()->getEventsManager();

        foreach ( $eventsManager->getStaticEvents( 'gtm' ) as $eventName => $events ) {
            foreach ( $events as $event ) {
                foreach ( $this->getAllPixels() as $pixelID ) {
                    $args = array(
                        'v'    => 2,
                        'tid'  => $pixelID,
                        'cid'  => isset($_COOKIE['_ga']) ? preg_replace('/GA\d+\.\d+\.(\d+\.\d+)/', '$1', $_COOKIE['_ga']) : time() . '.' . rand(100000, 999999), // Generate a random Client ID
                        'en'   => $event['name'], // The name of the event (eg view_item)
                        'ep.eventID'  => $event['eventID'],
                    );

                    $args['dt'] = isset($event['params']['page_title']) ? urlencode($event['params']['page_title']) : '';
                    $args['dl'] = isset($event['params']['event_url']) ? urlencode($event['params']['event_url']) : '';
                    // DYNAMICALLY LOOPING THROUGH ALL PARAMETERS EXCEPT "items"
                    foreach ($event['params'] as $key => $value) {
                        if ($key === 'items' || $key === 'page_title' || $key === 'event_url') {
                            continue;
                        }
                        $args["ep.$key"] = is_array($value) ? json_encode($value) : $value;
                    }

                    // Adding products
                    if (!empty($event['params']['items'])) {
                        foreach ($event['params']['items'] as $key => $item) {
                            $args["pr" . ($key + 1) . "id"] = urlencode($item['item_id']);
                            $args["pr" . ($key + 1) . "nm"] = urlencode($item['item_name']);
                            $args["pr" . ($key + 1) . "pr"] = (float)$item['price'];
                            $args["pr" . ($key + 1) . "qt"] = (int)$item['quantity'];
                            $args["pr" . ($key + 1) . "ca"] = urlencode($item['item_category']);
                        }
                    }

                    $src = add_query_arg( $args, 'https://www.google-analytics.com/collect' );
                    $src = str_replace("[","%5B",$src);
                    $src = str_replace("]","%5D",$src);

                    // ALT tag used to pass ADA compliance
                    printf( '<noscript><img height="1" width="1" style="display: none;" src="%s" alt="google_gtm"></noscript>',
                        $src );

                    echo "\r\n";

                }
            }
        }

    }


    private function getPageViewEventParams() {

	    global $post;


	    $cpt = get_post_type();
	    $params = array();

	    if(!$cpt) return false;

	    if(isWooCommerceActive() && $cpt == 'product') {
		    $params['categories'] = implode( ', ', getObjectTerms( 'product_cat', $post->ID ) );
		    $params['tags']       = implode( ', ', getObjectTerms( 'product_tag', $post->ID ) );
	    } elseif (isEddActive() && $cpt == 'download') {
		    $params['categories'] = implode( ', ', getObjectTerms( 'download_category', $post->ID ) );
		    $params['tags']       = implode( ', ', getObjectTerms( 'download_tag', $post->ID ) );
	    } elseif ($post instanceof \WP_Post) {
		    $params['tags'] = implode( ', ', getObjectTerms( 'post_tag', $post->ID ) );
		    $taxonomies = get_object_taxonomies($post->post_type);
			if ( ! empty( $taxonomies ) && $terms = getObjectTerms( $taxonomies[0], $post->ID ) ) {
			    $params['categories'] = implode( ', ', $terms );
		    }
	    }

	    return array(
		    'name'  => 'page_view',
		    'data'  => $params
	    );

    }


    /**
     * @param PYSEvent $event
     *
     * @return array|bool
     */
    private function getCustomEventData( $event ) {
        global $post;
        /**
         * @var CustomEvent $customEvent
         */
        $customEvent = $event->args;
        $gtm_action = $customEvent->getGTMAction();


        if ( ! $customEvent->isGTMEnabled() || empty( $gtm_action ) ) {
            return false;
        }

        $params = $customEvent->getAllGTMParams();

        $trigger_types = array();
        foreach ($customEvent->getTriggers() as $trigger) {
            $trigger_types[] = $trigger->getTriggerType();
        }
        if ( in_array( 'purchase', $trigger_types ) && ($trigger->getTrackValueAndCurrency() || $trigger->getTrackTransactionID()) ) {
            $order = EventsWoo()->getOrder();
            if ($order) {
                $added_params = array();
                if($trigger->getTrackValueAndCurrency()) {
                    $added_params['value'] = $order->get_total();
                    $added_params['currency'] = $order->get_currency();
                }
                if($trigger->getTrackTransactionID()) {
                    $added_params['transaction_id'] = $order->get_id();
                }
                if(!isset($params['ecommerce']) || !is_array($params['ecommerce'])) {
                    $params['ecommerce'] = [];
                }
                $params['ecommerce'] = array_merge($params['ecommerce'], $added_params);
            }
        }

        if ( in_array( 'add_to_cart', $trigger_types ) && $trigger->getTrackValueAndCurrency() && $event->getPayloadValue('productId')) {
            $product_id = $event->getPayloadValue('productId');
            $quantity = $event->getPayloadValue('quantity') ?? 1;

            $product = wc_get_product($product_id);
            if( !$product ) {
                return false; // only simple products
            }
            $value = $product->get_price();
            if ( $product->is_taxable()) {
                $value = wc_get_price_including_tax( $product, array(
                    'price' => $value,
                    'qty' => $quantity
                ) );
            } else {
                $value = wc_get_price_excluding_tax( $product, array(
                    'price' => $value,
                    'qty' => $quantity
                ) );
            }
            if(!isset($params['ecommerce']) || !is_array($params['ecommerce'])) {
                $params['ecommerce'] = [];
            }
            $params['ecommerce'] = array_merge($params['ecommerce'], [
                'currency' => get_woocommerce_currency(),
                'value' => $value,
                'quantity' => $quantity,
            ]);
        }

        $params['manualName'] = $customEvent->getManualCustomObjectName();

        if($customEvent->removeGTMCustomTrigger()){
            $event->removeParam('triggerType');
        }
        if(isWooCommerceActive()) {
            if ($customEvent->gtm_track_single_woo_data && is_product() && ($post->post_type === 'product' || $event->getPayloadValue('productId'))) {
                $product_id = $event->getPayloadValue('productId') ?? $post->ID;
                $quantity = $event->getPayloadValue('quantity') ?? 1;

                $eventData = $this->getWooViewContentEventParams(array('id' => $product_id, 'quantity' => $quantity), $event);
                if (!isset($params['ecommerce']) || !is_array($params['ecommerce'])) {
                    $params['ecommerce'] = [];
                }
                $params['ecommerce'] = isset($eventData['data']) ? array_merge($params['ecommerce'], $eventData['data']) : $params;

            } elseif ($customEvent->gtm_track_cart_woo_data) {
                $order = EventsWoo()->getOrder();
                if (!isset($params['ecommerce']) || !is_array($params['ecommerce'])) {
                    $params['ecommerce'] = [];
                }
                if ($order) {
                    $purchaseEvent = EventsWoo()->getEvent('woo_purchase');
                    if ($purchaseEvent) {
                        $params['ecommerce'] = array_merge($params['ecommerce'], $this->getWooPurchaseParams($purchaseEvent, $order));
                    }
                } else {
                    $params['ecommerce'] = array_merge($params['ecommerce'], $this->getWooEventCartParams($event));
                }
            }
        }
        $event->addPayload(array(
            'hasAutoParam' => $customEvent->hasAutomatedParam(),
        ));
        return array(
            'name'  => $customEvent->getGTMAction(),
            'data'  => $params,
            'delay' => $customEvent->getDelay(),

        );



    }

    private function getWooViewItemListTag() {
        global $posts, $wp_query;

        if ( ! $this->getOption( 'woo_view_item_list_enabled' ) ) {
            return false;
        }
        $product_tag = '';
        $product_tag_slug = '';
        $tag_obj = $wp_query->get_queried_object();
        if ( $tag_obj ) {
            $product_tag = single_tag_title( '', false );
            $product_tag_slug = $tag_obj->slug;
        }

        $list_name =  !empty($product_tag) && $this->getOption('woo_view_item_list_track_name') ? 'Tag - '.$product_tag : 'Tag';
        $list_id =  !empty($product_tag_slug) && $this->getOption('woo_view_item_list_track_name') ? 'tag_'.$product_tag_slug : 'tag';

        $items = array();

        for ( $i = 0; $i < count( $posts )&& $i < 10; $i ++ ) {

            if ( $posts[ $i ]->post_type !== 'product' ) {
                continue;
            }

            $item = array(
                'item_id'            => Helpers\getWooProductContentId($posts[ $i ]->ID),
                'item_name'          => $posts[ $i ]->post_title,
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $posts[ $i ]->ID ),
                'item_list_name'=> GTM()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GTM()->getOption('woo_track_item_list_id') ? $list_id : '',
                'affiliation' => PYS_SHOP_NAME,
            );
            $category = $this->getCategoryArrayWoo($posts[ $i ]->ID);
            if(!empty($category))
            {
                $item = array_merge($item, $category);
            }
            $brand = getBrandForWooItem($posts[ $i ]->ID);
            if($brand)
            {
                $item['item_brand'] = $brand;
            }
            $items[] = $item;

        }

        $params = array(
            'event_category'  => 'ecommerce',
            'event_label'     => $list_name,
            'currency'        => get_woocommerce_currency(),
            'items'           => $items,
        );

        return array(
            'name'  => 'view_item_list',
            'data'  => $params
        );
    }

    private function getWooViewItemListShop() {
        /**
         * @var \WC_Product $product
         * @var $related_products \WC_Product[]
         */

        global $posts;

        if ( ! $this->getOption( 'woo_view_item_list_enabled' ) ) {
            return false;
        }


        $list_name = 'Shop page';
        $list_id = 'shop_page';
        $items = array();

        foreach ( $posts as $i=>$post) {
            if( $post->post_type != 'product') continue;
            $item = array(
                'item_id'            => Helpers\getWooProductContentId($post->ID),
                'item_name'          => $post->post_title ,
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $post->ID ),
                'item_list_name'=> GTM()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GTM()->getOption('woo_track_item_list_id') ? $list_id : ''
            );
            $category = $this->getCategoryArrayWoo($post->ID);
            if(!empty($category))
            {
                $item = array_merge($item, $category);
            }
            $brand = getBrandForWooItem($post->ID);
            if($brand)
            {
                $item['item_brand'] = $brand;
            }
            $items[] = $item;
        }


        $params = array(
            'event_category'  => 'ecommerce',
            'event_label'     => $list_name,
            'currency'        => get_woocommerce_currency(),
            'items'           => $items,
        );


        return array(
            'name'  => 'view_item_list',
            'data'  => $params
        );
    }

    private function getWooViewItemListSearch() {
        /**
         * @var \WC_Product $product
         * @var $related_products \WC_Product[]
         */

        global $posts;

        if ( ! $this->getOption( 'woo_view_item_list_enabled' ) ) {
            return false;
        }



        $list_name = "Search Results";
        $list_id = 'search_results';
        $items = array();
        $i = 0;

        foreach ( $posts as $post) {
            if( $post->post_type != 'product') continue;
            $item = array(
                'item_id'            => Helpers\getWooProductContentId($post->ID),
                'item_name'          => $post->post_title ,
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $post->ID ),
                'item_list_name'=> GTM()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GTM()->getOption('woo_track_item_list_id') ? $list_id : '',
                'affiliation' => PYS_SHOP_NAME
            );
            $category = $this->getCategoryArrayWoo($post->ID);
            if(!empty($category))
            {
                $item = array_merge($item, $category);
            }
            $brand = getBrandForWooItem($post->ID);
            if($brand)
            {
                $item['item_brand'] = $brand;
            }
            $items[] = $item;
        }

        $params = array(
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
            'event_label'     => $list_name,
            'items'           => $items,
        );


        return array(
            'name'  => 'view_item_list',
            'data'  => $params,
        );
    }

    /**
     * @param string $type
     * @param SingleEvent $event
     * @return bool
     */
    private function getWooSelectContent($type,&$event) {

        if(!$this->getOption('woo_select_content_enabled')) {
            return false;
        }


        $event->addParams( array(
            'event_category'  => 'ecommerce',
            'content_type'     => "product",
        ));
        $event->addPayload( array(
            'name'=>"select_item"
        ));

        return true;
    }


    private function getWooViewItemListSingleParams() {
        global $wp_query;
        /**
         * @var \WC_Product $product
         * @var $related_products \WC_Product[]
         */
        $product = wc_get_product( get_the_ID() );

        if ( !$product || ! $this->getOption( 'woo_view_item_list_enabled' ) ) {
            return false;
        }

        $related_products = array();

        $args = array(
            'posts_per_page' => 4,
            'columns'        => 4,
        );
        $args = apply_filters( 'woocommerce_output_related_products_args', $args );

        $ids =  Helpers\custom_wc_get_related_products( get_the_ID(), $args['posts_per_page'] );
        $ids = array_slice($ids, 0, 10);
        foreach ( $ids as $id) {
            $rel = wc_get_product($id);
            if($rel) {
                $related_products[] = $rel;
            }
        }

        $product_name = '';
        $product_slug = '';
        $prod_obj = $wp_query->get_queried_object();
        if ( $prod_obj ) {
            $product_name = $prod_obj->post_title;
            $product_slug = $prod_obj->post_name;
        }

        $list_name =  !empty($product_name) && $this->getOption('woo_view_item_list_track_name') ? 'Related Products - '.$product_name : 'Related Products';
        $list_id =  !empty($product_slug) && $this->getOption('woo_view_item_list_track_name') ? 'related_products_'.$product_slug : 'related_products';


        $items = array();
        $i = 0;
        if(!$related_products) return;
        foreach ( $related_products as $relate) {

            $item = array(
                'item_id'            => Helpers\getWooProductContentId($relate->get_id()),
                'item_name'          => $relate->get_title(),
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $relate->get_id() ),
                'item_list_name'=> GTM()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GTM()->getOption('woo_track_item_list_id') ? $list_id : '',
                'affiliation' => PYS_SHOP_NAME
            );
            $category = $this->getCategoryArrayWoo($relate->get_id());
            if(!empty($category))
            {
                $item = array_merge($item, $category);
            }
            $brand = getBrandForWooItem($relate->get_id());
            if($brand)
            {
                $item['item_brand'] = $brand;
            }
            $items[] = $item;
        }

        $params = array(
            'event_category'  => 'ecommerce',
            'event_label'     => $list_name,
            'currency'        => get_woocommerce_currency(),
            'items'           => $items,
        );


        return array(
            'name'  => 'view_item_list',
            'data'  => $params,
        );
    }

    private function getWooViewCategoryEventParams($event) {
        global $posts;

        if ( ! $this->getOption( 'woo_view_item_list_enabled' ) ) {
            return false;
        }

        $product_category = "";
        $product_category_slug = "";
        $term = get_term_by( 'slug', get_query_var( 'term' ), 'product_cat' );

        if ( $term ) {
            $product_category = $term->name;
            $product_category_slug = $term->slug;
        }

        $list_name =  !empty($product_category) && $this->getOption('woo_view_item_list_track_name') ? 'Category - '.$product_category : 'Category';
        $list_id =  !empty($product_category_slug) && $this->getOption('woo_view_item_list_track_name') ? 'category_'.$product_category_slug : 'category';
        $items = array();

        for ( $i = 0; $i < count( $posts ) && $i < 10; $i ++ ) {
            $event_has_product = true;

            if ((isset($event->args['filter_product']) && $event->args['filter_product'] == true) && (isset($event->args['products']) && is_array($event->args['products']))) {
                // Defining the filtering condition
                $filtered_products = array_filter($event->args['products'], function ($product) use ($posts, $i) {

                    return $product['product_id'] == $posts[ $i ]->ID;
                });
                $event_has_product = !empty($filtered_products);
            }

            if(!$event_has_product) continue;
            if ( $posts[ $i ]->post_type !== 'product' ) {
                continue;
            }
            $item = array(
                'item_id'            => Helpers\getWooProductContentId($posts[ $i ]->ID),
                'item_name'          => $posts[ $i ]->post_title,
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $posts[ $i ]->ID ),
                'item_list_name'          => GTM()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id' => GTM()->getOption('woo_track_item_list_id') ? $list_id : '',
                'affiliation' => PYS_SHOP_NAME
            );
            $category = $this->getCategoryArrayWoo($posts[ $i ]->ID);
            if(!empty($category))
            {
                $item = array_merge($item, $category);
            }
            $brand = getBrandForWooItem($posts[ $i ]->ID);
            if($brand)
            {
                $item['item_brand'] = $brand;
            }

            $items[] = $item;

        }

        $params = array(
            'event_category'  => 'ecommerce',
            'event_label'     => $list_name,
            'currency'        => get_woocommerce_currency(),
            'items'           => $items,
        );

        return array(
            'name'  => 'view_item_list',
            'data'  => $params,
        );

    }
    /**
     * @param SingleEvent $event
     * @return false
     */
    function prepare_wcf_remove_from_cart(&$event) {
        if (  !$this->getOption( 'woo_remove_from_cart_enabled' )
            || empty($event->args['products'])
        ) {
            return false;
        }
        $product_data = $event->args['products'][0];
        $product_id = $product_data['id'];
        $content_id = Helpers\getWooProductContentId( $product_id );
        $price = getWooProductPriceToDisplay($product_id, $product_data['quantity'],$product_data['price']);
        $variation_name = empty($product_data['variation_attr'])
            ? null
            : implode( '/', $product_data['variation_attr'] );
        $params = [
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
            'items'           => [
                [
                    'item_id'       => $content_id,
                    'item_name'     => $product_data['name'],
                    'quantity' => $product_data['quantity'],
                    'price'    => $price,
                    'variant'  => $variation_name,
                    'affiliation' => PYS_SHOP_NAME
                ]
            ]
        ];
        $category = $this->getCategoryArrayWoo($content_id);
        if(!empty($category))
        {
            $params['items'][0] = array_merge($params['items'][0], $category);
        }
        $brand = getBrandForWooItem($content_id);
        if($brand)
        {
            $params['items'][0]['item_brand'] = $brand;
        }
        $event->addParams($params);
        $event->addPayload([
            'name' => "remove_from_cart",
        ]);
        return true;
    }
    /**
     * @param SingleEvent $event
     * @return false
     */
    private function prepare_wcf_add_to_cart(&$event) {
        if (  !$this->getOption( 'woo_add_to_cart_enabled' )
            || empty($event->args['products'])
        ) {
            return false;
        }
        $content_ids        = array();
        $items              = array();
        $value = 0;
        foreach ($event->args['products'] as $product_data) {
            $product_id = $product_data['id'];
            $content_id = Helpers\getWooProductContentId( $product_id );
            $price = getWooProductPriceToDisplay( $product_id,$product_data['quantity'],$product_data['price'] );

            $item = array(
                'item_id'       => $content_id,
                'item_name'     => $product_data['name'],
                'quantity' => $product_data['quantity'],
                'price'    => $price,
                'variant'  => empty($product_data['variation_attr']) ? null : implode("/", $product_data['variation_attr']),
            );
            $category = $this->getCategoryArrayWoo($content_id);
            if(!empty($category))
            {
                $item = array_merge($item, $category);
            }
            $brand = getBrandForWooItem($content_id);
            if($brand)
            {
                $item['item_brand'] = $brand;
            }
            $items[] = $item;
            $content_ids[] = $content_id;
            $value += $price;
        }

        $params = array(
            'event_category'  => 'ecommerce',
            'items' => $items
        );

	    $params['value'] = $value;

        $dyn_remarketing = array(
            'product_id'  => $content_ids,
            'page_type'   => 'cart',
            'total_value' => $value,
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }


        $event->addParams($params);

        $event->addPayload([
            'name'=>"add_to_cart"
        ]);
        return true;

    }
    /**
     * @param SingleEvent $event
     * @return false
     */
    private function getWcfViewContentEventParams(&$event)  {
        if ( ! $this->getOption( 'woo_view_content_enabled' )
            || empty($event->args['products'])
        ) {
            return false;
        }
        $product_data = $event->args['products'][0];
        $content_id = Helpers\getWooProductContentId($product_data['id']);
        $category = implode( ', ', array_column($product_data['categories'],"name") );
        $price = getWooProductPriceToDisplay( $product_data['id'],$product_data['quantity'],$product_data['price']);

        $params = array(
            'event_category'  => 'ecommerce',
            'currency' => get_woocommerce_currency(),
            'items'           => array(
                array(
                    'item_id'       => $content_id,
                    'item_name'     => $product_data['name'],
                    'quantity' => $product_data['quantity'],
                    'price'    => $price,
                    'affiliation' => PYS_SHOP_NAME
                ),
            ),
        );
        if (isset($_COOKIE['select_prod_list'])) {
            $productlist = json_decode(stripslashes($_COOKIE['select_prod_list']), true);
            if (isset($productlist['list_name']) && $this->getOption('woo_track_item_list_name')) {
                $params['items'][0]['item_list_name'] = sanitize_text_field($productlist['list_name']);
            }

            if (isset($productlist['list_id']) && $this->getOption('woo_track_item_list_id')) {
                $params['items'][0] = sanitize_text_field($productlist['list_id']);
            }
            setcookie('select_prod_list', '', time() - 3600);
        }
        $category = $this->getCategoryArrayWoo($content_id);
        if(!empty($category))
        {
            $params['items'][0] = array_merge($params['items'][0], $category);
        }
        $brand = getBrandForWooItem($content_id);
        if($brand)
        {
            $params['items'][0]['item_brand'] = $brand;
        }

        if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
            $value_option   = PYS()->getOption( 'woo_view_content_value_option' );
            $global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
            $percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $product_data['id'],
                'qty' => $product_data['quantity']
            ];

            $params[ 'value' ] = getWooProductValue($valueArgs);
        }

        $dyn_remarketing = array(
            'product_id'  => $content_id,
            'page_type'   => 'product',
            'total_value' => $price,
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }

        $event->addParams($params);

        $event->addPayload([
            'name'  => 'view_item',
            'delay' => (int) PYS()->getOption( 'woo_view_content_delay' ),
        ]);

        return true;
    }
    private function getWooViewCartEventParams(&$event){
        if ( ! $this->getOption( 'woo_view_cart_enabled' ) ) {
            return false;
        }
        $data = ['name'  => 'view_cart'];
        $payload = $event->payload;
        $params = $this->getWooEventViewCartParams( $event );
        $event->addParams($params);
        $event->addPayload($data);
        return true;
    }

    private function getWooViewContentEventParams($eventArgs = null, $event = null)
    {

        $variable_id = null;
        $quantity = 1;
        $customProductPrice = -1;
        if ($eventArgs && isset($eventArgs['id'])) {
            $product = wc_get_product($eventArgs['id']);
            $quantity = $eventArgs['quantity'];
        } else {
            global $post;
            $product = wc_get_product($post->ID);
        }
        if (!$product) return false;
        if (GTM()->getOption('woo_variable_data_select_product') && !GTM()->getOption('woo_variable_as_simple')) {
            $variable_id = getVariableIdByAttributes($product);
        }
        $product_get_id = $variable_id ?? $product->get_id();
        $productId = Helpers\getWooProductContentId($product_get_id);
	    $category = $this->getCategoryArrayWoo($product_get_id, $product->is_type('variable'));
	    $brand = getBrandForWooItem($product_get_id);

        if (isset($_COOKIE['select_prod_list'])) {
            $productlist = json_decode(stripslashes($_COOKIE['select_prod_list']), true);
            if (isset($productlist['list_name']) && $this->getOption('woo_track_item_list_name')) {
                $item_list_name = sanitize_text_field($productlist['list_name']);
            }

            if (isset($productlist['list_id']) && $this->getOption('woo_track_item_list_id')) {
                $item_list_id = sanitize_text_field($productlist['list_id']);
            }
            $current_url = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] ?? parse_url(get_site_url(), PHP_URL_HOST) . $_SERVER['REQUEST_URI'];

// Update the select_prod_list cookie with the address of the current page
            $productlist['url'] = $current_url;
	        $_COOKIE['select_prod_list'] = json_encode($productlist);
        }

        $items = array();

// Add general product
        if (empty($variable_id)) {

            $general_item = array(
                'item_id' => $productId,
                'item_name' => $product->get_name(),
                'quantity' => $quantity,
                'price' => getWooProductPriceToDisplay($product->get_id(), $quantity, $customProductPrice),
                'affiliation' => PYS_SHOP_NAME
            );

            if (!empty($item_list_name)) {
                $general_item['item_list_name'] = $item_list_name;
            }
            if (!empty($item_list_id)) {
                $general_item['item_list_id'] = $item_list_id;
            }

            if (!empty($category)) {
                $general_item = array_merge($general_item, $category);
            }

            if ($brand) {
                $general_item['item_brand'] = $brand;
            }
            $items[] = $general_item;
        }
// Check if the product has variations
        if ($product->is_type('variable') && !GTM()->getOption( 'woo_variable_as_simple' )) {
            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {

                    $variationProduct = wc_get_product($variation['variation_id']);
                    if(!$variationProduct) continue;
                    $variationProductId = Helpers\getWooProductContentId($variation['variation_id']);

                    $item = array(
                        'item_id'       => $variationProductId,
                        'item_name'     => GTM()->getOption('woo_variations_use_parent_name') ? $variationProduct->get_title() : $variationProduct->get_name(),
                        'quantity' => $quantity,
                        'price'    => getWooProductPriceToDisplay($variationProduct->get_id(), $quantity, $customProductPrice),
                        'affiliation' => PYS_SHOP_NAME,
                        'variant' => implode("/", $variationProduct->get_variation_attributes())
                    );
                    if(!empty($item_list_name))
                    {
                        $item['item_list_name'] = $item_list_name;
                    }
                    if(!empty($item_list_id))
                    {
                        $item['item_list_id'] = $item_list_id;
                    }
                    if($brand)
                    {
                        $item['item_brand'] = $brand;
                    }
                if (empty($variable_id) || $variation['variation_id'] == $variable_id) {
                    $items[] = array_merge($item, $category);
                }
            }
        }
        $params['items'] = $items;
	    $params['currency'] = get_woocommerce_currency();
        $value =  getWooProductPriceToDisplay( $product_get_id ,$quantity,$customProductPrice );
        if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {
            $value_option   = PYS()->getOption( 'woo_view_content_value_option' );
            $global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
            $percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $product_get_id,
                'qty' => $quantity
            ];

            $params[ 'value' ] = getWooProductValue($valueArgs);
        }

        $dyn_remarketing = array(
            'product_id'  => $productId,
            'page_type'   => 'product',
            'total_value' => $value,
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
		if(!empty($dyn_remarketing)){
			$params = array_merge( $params, $dyn_remarketing );
		}

        return array(
            'name'  => 'view_item',
            'data'  => $params,
            'delay' => (int) PYS()->getOption( 'woo_view_content_delay' ),
        );

    }

    private function getWooAddToCartOnButtonClickEventParams($args) {
        $product_id = $args['productId'];
        $quantity = $args['quantity'];
        $contentId = Helpers\getWooProductContentId($product_id);
        $product = wc_get_product( $product_id );
        if(!$product) return false;


        $customProductPrice = getWfcProductSalePrice($product,$args);
        $params = array(
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
        );

        $product_ids = array();
        $items = array();
	    if(isset($_COOKIE['productlist']) && PYS()->getOption('woo_add_to_cart_catch_method') == "add_cart_hook")
	    {
		    $productlist = json_decode(stripslashes($_COOKIE['productlist']), true);
		    setcookie('productlist', '', time() - 3600);
	    }
        $isGrouped = $product->get_type() == "grouped";
        if($isGrouped) {
            $product_ids = $product->get_children();
        } else {
            $product_ids[] = $product_id;
        }
        foreach ($product_ids as $product_key => $child_id) {
            $childProduct = wc_get_product($child_id);
            if(!$childProduct || ($childProduct->get_type() == "variable" && $isGrouped)) {
                continue;
            }
            $childContentId = Helpers\getWooProductContentId( $child_id );
            $price = getWooProductPriceToDisplay( $child_id, $quantity,$customProductPrice );

            if ( $childProduct->get_type() == 'variation' ) {
                $parentId = $childProduct->get_parent_id();
                $name = GTM()->getOption('woo_variations_use_parent_name') ? $childProduct->get_title() : $childProduct->get_name();
                $category_prod_id = $parentId;
                $variation_name = implode("/", $childProduct->get_variation_attributes());
            } else {
                $name = $childProduct->get_name();
                $category_prod_id = $child_id;
                $variation_name = null;
            }

            $items[$product_key] =  array(
                'item_id'       => $childContentId,
                'item_name'     => $name,
                'quantity' => $quantity,
                'price'    => $price,
                'variant'  => $variation_name,
                'affiliation' => PYS_SHOP_NAME
            );
	        if(isset($productlist) && is_array($productlist)){
		        $items[$product_key]['list_name'] = GTM()->getOption('woo_track_item_list_name') ? sanitize_text_field($productlist['pys_list_name_productlist_name']) : '';
		        $items[$product_key]['item_list_id'] = GTM()->getOption('woo_track_item_list_id') ? sanitize_text_field($productlist['pys_list_name_productlist_id']) : '';

	        }
            $category = $this->getCategoryArrayWoo($category_prod_id);
            if(!empty($category))
            {
                $items[$product_key] = array_merge($items[$product_key], $category);
            }
            $brand = getBrandForWooItem($childContentId) ? getBrandForWooItem($childContentId) : getBrandForWooItem($childProduct->get_parent_id());
            if($brand)
            {
                $items[$product_key]['item_brand'] = $brand;
            }
        }
        $params['items'] = $items;

	    $value =  getWooProductPriceToDisplay( $product_id, $quantity ,$customProductPrice);

        $value_enabled_option = 'woo_add_to_cart_value_enabled';

        // currency, value
        if ( PYS()->getOption( $value_enabled_option ) ) {

            $value_option_option  = 'woo_add_to_cart_value_option';
            $value_global_option  = 'woo_add_to_cart_value_global';
            $value_percent_option = 'woo_add_to_cart_value_percent';
            $value_option   = PYS()->getOption( $value_option_option );
            $global_value   = PYS()->getOption( $value_global_option, 0 );
            $percents_value = PYS()->getOption( $value_percent_option, 100 );

            $valueArgs = [
                'valueOption' => $value_option,
                'global' => $global_value,
                'percent' => $percents_value,
                'product_id' => $product_id,
                'qty' => $quantity
            ];

            $params['value']    = getWooProductValue($valueArgs);

        }

        $dyn_remarketing = array(
            'product_id'  => $contentId,
            'page_type'   => 'cart',
            'total_value' => $value,
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }

        $data = array(
            'params'  => $params,
        );

        if($product->get_type() == 'grouped') {
            $grouped = array();
            foreach ($product->get_children() as $childId) {
                $grouped[$childId] = array(
                    'content_id' => Helpers\getWooProductContentId( $childId ),
                    'price' => getWooProductPriceToDisplay( $childId )
                );
            }
            $data['grouped'] = $grouped;
        }

        return $data;

    }

    /**
     * @param SingleEvent $event
     * @return boolean
     */
    private function setWooAddToCartOnCartEventParams(&$event) {

        if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) ) {
            return false;
        }

        $params = $this->getWooEventCartParams($event);
        $event->addParams($params);
        $event->addPayload(['name' => 'add_to_cart']);

        return true;
    }

    private function getWooRemoveFromCartParams( $cart_item ) {

        if ( ! $this->getOption( 'woo_remove_from_cart_enabled' ) ) {
            return false;
        }


        $product_id = Helpers\getWooCartItemId( $cart_item );
        $content_id = Helpers\getWooProductContentId( $product_id );

        $_product = wc_get_product($product_id);

        if(!$_product) return false;

        if($_product->get_type() == "bundle") {
            $price = getWooBundleProductCartPrice($cart_item);
        } else {
            $price = getWooProductPriceToDisplay($product_id, $cart_item['quantity']);
        }

        $product = get_post( $product_id );

        if ( ! empty( $cart_item['variation_id'] ) ) {
            $variation = wc_get_product( (int) $cart_item['variation_id'] );
            if($variation && is_a($variation, 'WC_Product_Variation')) {
                $parentId = $variation->get_parent_id();
                $name = GTM()->getOption('woo_variations_use_parent_name') ? $variation->get_title() : $variation->get_name();
                $categories = implode( '/', getObjectTerms( 'product_cat', $parentId ) );
                $variation_name = implode("/", $variation->get_variation_attributes());
            } else {
                $name = $product->post_title;
                $variation_name = null;
                $categories = implode( '/', getObjectTerms( 'product_cat', $product_id ) );
            }
        } else {
            $name = $product->post_title;
            $variation_name = null;
            $categories = implode( '/', getObjectTerms( 'product_cat', $product_id ) );
        }
        $params = array(
            'name' => "remove_from_cart",
            'data' => array(
                'event_category'  => 'ecommerce',
                'currency'        => get_woocommerce_currency(),
                'items'           => array(
                    array(
                        'item_id'       => $content_id,
                        'item_name'     => $name,
                        'quantity' => $cart_item['quantity'],
                        'price'    => $price,
                        'affiliation' => PYS_SHOP_NAME
                    ),
                ),
            ),
        );
        if ($_product->is_type('variable') && !GTM()->getOption( 'woo_variable_as_simple' )) {
            $params['data']['items'][0]['variant'] = $variation_name;
        }
        $category = $this->getCategoryArrayWoo($content_id);
        if(!empty($category))
        {
            $params['data']['items'][0] = array_merge($params['data']['items'][0], $category);
        }
        $brand = getBrandForWooItem($content_id);
        if($brand)
        {
            $params['data']['items'][0]['item_brand'] = $brand;
        }
        return $params;

    }


    /**
     * @param SingleEvent $event
     * @return boolean
     */
    private function setWooInitiateCheckoutEventParams(&$event) {

        if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
            return false;
        }
        $data = ['name'  => 'begin_checkout',];
        $params = $this->getWooEventCartParams( $event );
        $event->addParams($params);
        $event->addPayload($data);
        return true;

    }

    private function getWooSetСheckoutOptionEventParams() {

        if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) || !$this->getOption( 'woo_initiate_set_checkout_option_enabled' )) {
            return false;
        }
        $user = wp_get_current_user();
        if ( $user->ID !== 0 ) {
            $user_roles = implode( ',', $user->roles );
        } else {
            $user_roles = 'guest';
        }

        $params = array (
            'event_category'=> 'ecommerce',
            'event_label'     => $user_roles,
            'checkout_step'   => '1',
            'checkout_option' => $user_roles,
        );
        return array(
            'name'  => 'set_checkout_option',
            'data'  => $params
        );


    }

    /**
     * @param SingleEvent $event
     * @return bool
     */
    private function setWooCheckoutProgressEventParams($event) {

        if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) || ! $this->getOption( $event->getId()."_enabled" ) ) {
            return false;
        }

        $params = [];
        $params['checkout_step'] = $this->checkout_step;
        $this->checkout_step++;
        $params['event_category'] = "ecommerce";
        $cartParams = $this->getWooEventCartParams( $event );
        if(isset($cartParams['items'])){
            $params['items'] = $cartParams['items'];
        }

        switch ($event->getId()) {
            case 'woo_initiate_checkout_progress_f': {
                $params['event_label'] = $params['checkout_option'] = "Add First Name";
                break;
            }
            case 'woo_initiate_checkout_progress_l': {
                $params['event_label'] = $params['checkout_option'] = "Add Last Name";
                break;
            }
            case 'woo_initiate_checkout_progress_e': {
                $params['event_label'] = $params['checkout_option'] = "Add Email";
                break;
            }
            case 'woo_initiate_checkout_progress_o': {
                $params['event_label'] = "Click Place Order";
                if(isset($cartParams['coupon'])){
                    $params['coupon'] = $cartParams['coupon'];
                }
                if(isset($cartParams['shipping']) && !empty($cartParams['shipping']) )
                    $params['checkout_option'] = $cartParams['shipping'];
                break;
            }
        }
        $event->addPayload(['name'=> 'checkout_progress']);
        $event->addParams($params);

        return true;
    }


    private function getWooAffiliateEventParams( $product_id,$quantity ) {

        if ( ! $this->getOption( 'woo_affiliate_enabled' ) ) {
            return false;
        }

        $product = get_post( $product_id );

        $params = array(
            'event_category'  => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
            'items'           => array(
                array(
                    'item_id'       => $product_id,
                    'item_name'     => $product->post_title,
                    'quantity' => $quantity,
                    'price'    => getWooProductPriceToDisplay( $product_id, $quantity ),
                    'affiliation' => PYS_SHOP_NAME
                ),
            ),
        );
        $category = $this->getCategoryArrayWoo($product_id);
        if(!empty($category))
        {
            $params['items'][0] = array_merge($params['items'][0], $category);
        }
        $brand = getBrandForWooItem($product_id);
        if($brand)
        {
            $params['items'][0]['item_brand'] = $brand;
        }
        return array(
            'params'  => $params,
        );

    }

    /**
     * @param SingleEvent $event
     * @return boolean
     */
    private function setWooPayPalEventParams($event) {

        if ( ! $this->getOption( 'woo_paypal_enabled' ) ) {
            return false;
        }

        $params = $this->getWooEventCartParams( $event );
        unset( $params['coupon'] );

        $event->addPayload(['name' => getWooPayPalEventName(),]);
        $event->addParams($params);

        return true;
    }

    private function getWooPurchaseEventParams(&$event) {
        if ( ! $this->getOption( 'woo_purchase_enabled' ) || empty($event->args['order_id']) ) {
            return false;
        }

        $product_ids = array();

        if(isset($event->args['order_id'])){
            $order = wc_get_order($event->args['order_id']);
        } else { return false; }

        $params = $this->getWooPurchaseParams($event, $order, $product_ids);

		$google_consent_mode = PYS()->getOption('google_consent_mode');

		$analytics_storage = $order->get_meta( "_cm_analytics_storage" );
		if ( $analytics_storage ) {
			$params[ 'analytics_storage' ] = $analytics_storage;
		} elseif ( $google_consent_mode ) {
			$params[ 'analytics_storage' ] = 'granted';
		}

		$ad_storage = $order->get_meta( "_cm_ad_storage" );
		if ( $ad_storage ) {
			$params[ 'ad_storage' ] = $ad_storage;
		} elseif ( $google_consent_mode ) {
			$params[ 'ad_storage' ] = 'granted';
		}

		$ad_user_data = $order->get_meta( "_cm_ad_user_data" );
		if ( $ad_user_data ) {
			$params[ 'ad_user_data' ] = $ad_user_data;
		} elseif ( $google_consent_mode ) {
			$params[ 'ad_user_data' ] = 'granted';
		}

		$ad_personalization = $order->get_meta( "_cm_ad_personalization" );
		if ( $ad_personalization ) {
			$params[ 'ad_personalization' ] = $ad_personalization;
		} elseif ( $google_consent_mode ) {
			$params[ 'ad_personalization' ] = 'granted';
		}

        if($this->getOption('woo_purchase_new_customer') && ($order->get_customer_id() || PYS()->getOption('woo_purchase_new_customer_guest') === 'yes')){
            $params['new_customer'] = $event->args['new_customer'];
        }

        $event->addParams($params);
        $event->addPayload([
            'name' => 'purchase',
        ]);
        return true;
    }

    private function getWooPurchaseParams($event, $order, &$product_ids = []){

        $items = array();
        $tax = 0;
        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );
        $withTax = 'incl' === get_option( 'woocommerce_tax_display_cart' );

        // Handle AWCDP (Deposits & Partial Payments) plugin integration
        $awcdp_order = null;
        if (isset($event->args['awcdp_order']) && $event->args['awcdp_order'] instanceof \AWCDP_Order) {
            $awcdp_order = $event->args['awcdp_order'];
        } elseif ($order instanceof \AWCDP_Order) {
            $awcdp_order = $order;
        }

        // If we have an AWCDP order, get parent order for products
        if ($awcdp_order) {
            $parent_order_id = $awcdp_order->get_parent_id();
            if ($parent_order_id) {
                $parent_order = wc_get_order($parent_order_id);
                if ($parent_order) {
                    $order = $parent_order;
                }
            }
        }

        $order_Items = $order->get_items();
        foreach ( $order_Items as $order_Item ) {
            $product = $order_Item->get_product();
            if ($this->getOption('woo_variable_as_simple') && $product->is_type('variation')) {
                $product = wc_get_product($product->get_parent_id());
            }
            if(!$product) continue;
            $event_has_product = true;
            if (isset($event->args['products']) && is_array($event->args['products'])) {
                $event_has_product = false;
                // Defining the filtering condition
                $filtered_products = array_filter($event->args['products'], function ($filter_product) use ($product) {
                    return $filter_product['product_id'] == $product->get_id() || (isset($filter_product['parent_id']) && $filter_product['parent_id'] == $product->get_id());
                });
                $event_has_product = !empty($filtered_products);
            }
            if (!$event_has_product) {
                continue;
            }
            $product_data = $product->get_data();
            $product_array = (array) $product_data;
            $product_array['type'] = $product->get_type();
            $product_id  = Helpers\getWooProductDataId( $product_array );
            $content_id  = Helpers\getWooProductContentId( $product_id );

            /**
             * Discounted(total) price used instead of price as is on Purchase event only to avoid wrong numbers in
             * Analytic's Product Performance report.
             */
            $price = getWooProductPrice($product_id);


            $quantity = $order_Item->get_quantity();
            $tax += $order_Item->get_total_tax();
            $item = array(
                'item_id'       => $content_id,
                'item_name'     => GTM()->getOption('woo_variations_use_parent_name') && $product->is_type('variation') ? $product->get_title() : $product->get_name(),
                'quantity' => $quantity,
                'price'    => $price,
                'affiliation' => PYS_SHOP_NAME
            );
            if ($product && $product->is_type('variation')) {
                foreach ($event->args['products'] as $event_product){
                    if($event_product['product_id'] == $product_id && !empty($event_product['variation_name']))
                    {
                        $item['variant'] = $event_product['variation_name'];
                    }

                }
            }
            $list_name = $order_Item->get_meta('item_list_name');
            if(!empty($list_name) && GTM()->getOption('woo_track_item_list_name'))
            {
                $item['item_list_name'] = $list_name;
            }
            $item_list_id = $order_Item->get_meta('item_list_id');
            if(!empty($item_list_id) && GTM()->getOption('woo_track_item_list_id'))
            {
                $item['item_list_id'] = $item_list_id;
            }

            $category = $this->getCategoryArrayWoo($content_id, $product->is_type('variation'));
            if(!empty($category))
            {
                $item = array_merge($item, $category);
            }
            if (wp_get_post_parent_id($product_id)) {
                $brand = getBrandForWooItem($product_id) ? getBrandForWooItem($product_id) : getBrandForWooItem(wp_get_post_parent_id($product_id));
            } else {
                $brand = getBrandForWooItem($product_id);
            }
            if($brand)
            {
                $item['item_brand'] = $brand;
            }
            $items[] = $item;
            $product_ids[] = $item['item_id'];
        }

        if(empty($items)) return false; // order is empty

        $tax += (float) $event->args['shipping_tax'];
        $shipping_cost = $event->args['shipping_cost'];
        if($withTax) {
            $shipping_cost += $event->args['shipping_tax'];
        }
        $total_value = getWooEventOrderTotal($event);
        $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total_value,$event->args);
        $params = array(
            'event_category' => 'ecommerce',
            'transaction_id' => wooMapOrderId( $event->args[ 'order_id' ] ),
            'value'          => $value,
            'currency'       => $event->args[ 'currency' ],
            'items'          => $items,
            'tax'            => pys_round( $tax ),
            'shipping'       => pys_round( $shipping_cost, 2 ),
            'coupon'         => $event->args[ 'coupon_name' ],
        );

        if(PYS()->getOption('enable_CwCD')) {
            if(!empty(PYS()->getOption('aw_merchant_id'))){
                $params['aw_merchant_id'] = PYS()->getOption('aw_merchant_id');
            }
            if(!empty(PYS()->getOption('aw_feed_label'))){
                $params['aw_feed_label'] = PYS()->getOption('aw_feed_label');
            }
            if(!empty(PYS()->getOption('aw_feed_country'))){
                $params['aw_feed_country'] = PYS()->getOption('aw_feed_country');
            }
            if(!empty(PYS()->getOption('aw_feed_language'))){
                $params['aw_feed_language'] = PYS()->getOption('aw_feed_language');
            }
        }

        if(isset($event->args['fees'])){
            $params['fees'] = (float) $event->args['fees'];
        }

        $dyn_remarketing = array(
            'product_id'  => $product_ids,
            'page_type'   => 'purchase',
            'total_value' => $total_value,
        );
        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        if(!empty($dyn_remarketing)){
            $params = array_merge( $params, $dyn_remarketing );
        }

        return $params;
    }
    private function getWooAdvancedMarketingEventParams( $eventType ) {

        if ( ! $this->getOption( $eventType . '_enabled' ) ) {
            return false;
        }

        $params = array(
            //  "plugin" => "PixelYourSite",
        );


        switch ( $eventType ) {
            case 'woo_frequent_shopper':
                $eventName = 'FrequentShopper';
                break;

            case 'woo_vip_client':
                $eventName = 'VipClient';
                break;
            case 'woo_FirstTimeBuyer':
                $eventName = 'FirstTimeBuyer';
                break;
            case 'woo_ReturningCustomer':
                $eventName = 'ReturningCustomer';
                break;
            default:
                $eventName = 'BigWhale';
        }

        return array(
            'name'  => $eventName,
            'data'  => $params,
        );

    }

    private function getWooEventViewCartParams($event){
        $params = [
            'event_category' => 'ecommerce',
        ];
        $params['currency'] = get_woocommerce_currency();
        $items = array();
        $product_ids = array();
        $withTax = 'incl' === get_option( 'woocommerce_tax_display_cart' );
        if(WC()->cart->get_cart())
        {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $event_has_product = true;
                if ((isset($event->args['filter_product']) && $event->args['filter_product'] == true) && (isset($event->args['products']) && is_array($event->args['products']))) {
                    $event_has_product = false;
                    // Defining the filtering condition
                    $filtered_products = array_filter($event->args['products'], function ($product) use ($cart_item) {
                        if (!empty($cart_item['variation_id']) && !GATags()->getOption('woo_variable_as_simple')) {
                            return $product['product_id'] == $cart_item['variation_id'];
                        } else {
                            return $product['product_id'] == $cart_item['product_id'];
                        }
                    });
                    $event_has_product = !empty($filtered_products);
                }

                if(!$event_has_product) continue;
                $product = wc_get_product(!empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id']);

                if ($product) {

                    $product_data = $product->get_data();
                    $product_id = Helpers\getWooCartItemId( $cart_item );
                    $content_id = Helpers\getWooProductContentId($product_id);
                    $price = $cart_item['line_subtotal'];

                    $withTax = 'incl' === get_option('woocommerce_tax_display_cart');

                    if ($withTax) {
                        $price += $cart_item['line_subtotal_tax'];
                    }
                    $item = array(
                        'item_id'       => $content_id,
                        'item_name'     => GTM()->getOption('woo_variations_use_parent_name') && $product->is_type('variation') ? $product->get_title() : $product->get_name(),
                        'quantity' => $cart_item['quantity'],
                        'price'    => $cart_item['quantity'] > 0 ? pys_round($price / $cart_item['quantity']) : $price,
                        'affiliation' => PYS_SHOP_NAME
                    );

                    if ($product && $product->is_type('variation')) {
                        foreach ($event->args['products'] as $event_product){
                            if($event_product['product_id'] == $product_id && !empty($event_product['variation_name']))
                            {
                                $item['variant'] = $event_product['variation_name'];
                            }

                        }
                    }
                    if (isset($cart_item['item_list_name']) && GTM()->getOption('woo_track_item_list_name')) {
                        $item['item_list_name'] = $cart_item['item_list_name'];
                    }

                    if (isset($cart_item['item_list_id']) && GTM()->getOption('woo_track_item_list_id')) {
                        $item['item_list_id'] = $cart_item['item_list_id'];
                    }

                    $category = $this->getCategoryArrayWoo($product_id, $product->is_type('variation'));
                    if (!empty($category)) {
                        $item = array_merge($item, $category);
                    }

                    if (wp_get_post_parent_id($product_id)) {
                        $brand = getBrandForWooItem($product_id) ? getBrandForWooItem($product_id) : getBrandForWooItem(wp_get_post_parent_id($product_id));
                    } else {
                        $brand = getBrandForWooItem($product_id);
                    }

                    if ($brand) {
                        $item['item_brand'] = $brand;
                    }

                    $items[] = $item;
                    $product_ids[] = $item['item_id'];
                }
            }
        }
        $params['value'] = getWooEventCartTotal($event);
        $params['items'] = $items;
        $params['coupon'] = isset($event->args['coupon']) ? $event->args['coupon'] : '';

	    $dyn_remarketing = array(
		    'product_id'  => $product_ids,
		    'page_type'   => 'cart',
		    'total_value' => getWooEventCartTotal($event),
	    );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }

        return $params;
    }

    private function getWooEventCartParams( $event ){

        $items = array();
        $product_ids = array();
        $withTax = 'incl' === get_option( 'woocommerce_tax_display_cart' );
        if(WC()->cart->get_cart())
        {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $event_has_product = true;
                if (is_array($event->args) && (isset($event->args['filter_product']) && $event->args['filter_product'] == true) && (isset($event->args['products']) && is_array($event->args['products']))) {
                    $event_has_product = false;
                    // Defining the filtering condition
                    $filtered_products = array_filter($event->args['products'], function ($product) use ($cart_item) {
                        if (!empty($cart_item['variation_id']) && !$this->getOption('woo_variable_as_simple')) {
                            return $product['product_id'] == $cart_item['variation_id'];
                        } else {
                            return $product['product_id'] == $cart_item['product_id'];
                        }
                    });
                    $event_has_product = !empty($filtered_products);
                }

                if(!$event_has_product) continue;
                $product = wc_get_product(!empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id']);

                if ($product) {

                    $product_data = $product->get_data();
                    $product_id = Helpers\getWooCartItemId( $cart_item );
                    $content_id = Helpers\getWooProductContentId($product_id);
                    $price = $cart_item['line_subtotal'];

                    $withTax = 'incl' === get_option('woocommerce_tax_display_cart');

                    if ($withTax) {
                        $price += $cart_item['line_subtotal_tax'];
                    }
                    $item = array(
                        'item_id'       => $content_id,
                        'item_name'     => GTM()->getOption('woo_variations_use_parent_name') && $product->is_type('variation') ? $product->get_title() : $product->get_name(),
                        'quantity' => $cart_item['quantity'],
                        'price'    => $cart_item['quantity'] > 0 ? pys_round($price / $cart_item['quantity']) : $price,
                        'affiliation' => PYS_SHOP_NAME
                    );

                    if (is_array($event->args) && $product && $product->is_type('variation') ) {
                        foreach ($event->args['products'] as $event_product){
                            if($event_product['product_id'] == $product_id && !empty($event_product['variation_name']))
                            {
                                $item['variant'] = $event_product['variation_name'];
                            }

                        }
                    }
                    if (isset($cart_item['item_list_name']) && GTM()->getOption('woo_track_item_list_name')) {
                        $item['item_list_name'] = $cart_item['item_list_name'];
                    }

                    if (isset($cart_item['item_list_id']) && GTM()->getOption('woo_track_item_list_id')) {
                        $item['item_list_id'] = $cart_item['item_list_id'];
                    }

                    $category = $this->getCategoryArrayWoo($product_id, $product->is_type('variation'));
                    if (!empty($category)) {
                        $item = array_merge($item, $category);
                    }

                    if (wp_get_post_parent_id($product_id)) {
                        $brand = getBrandForWooItem($product_id) ? getBrandForWooItem($product_id) : getBrandForWooItem(wp_get_post_parent_id($product_id));
                    } else {
                        $brand = getBrandForWooItem($product_id);
                    }

                    if ($brand) {
                        $item['item_brand'] = $brand;
                    }

                    $items[] = $item;
                    $product_ids[] = $item['item_id'];
                }
            }
        }

        if (empty($items)) {
            return []; // cart is empty
        }

		$value = getWooEventCartTotal($event);
        $params = [
            'event_category' => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
        ];
        $params['items'] = $items;
        $params['coupon'] = is_array($event->args) && isset($event->args['coupon']) ? $event->args['coupon'] : '';

        if($event->getId() == 'woo_add_to_cart_on_cart_page'
            || $event->getId() == 'woo_add_to_cart_on_checkout_page'
            || $event->getId() == 'woo_initiate_checkout'
            || ($event->getId() == 'custom_event' && $event->args->gtm_track_cart_woo_data)
        ) {
            if($event->getId() == 'woo_initiate_checkout') {
                $page_type = 'checkout';
                $context = 'woo_initiate_checkout';
            } elseif ($event->getId() == 'custom_event'  && $event->args->gtm_track_cart_woo_data) {
                $page_type = 'custom';
                $context = 'woo_initiate_checkout';
            } else {
                $page_type = 'cart';
                $context = 'woo_add_to_cart';
            }

            $value_enabled_option = $context.'_value_enabled';
            // currency, value
            if ( PYS()->getOption( $value_enabled_option ) ) {

                $value_option_option  = $context.'_value_option';
                $value_global_option  = $context.'_value_global';
                $value_percent_option = $context.'_value_percent';

                $value_option   = PYS()->getOption( $value_option_option );
                $global_value   = PYS()->getOption( $value_global_option, 0 );
                $percents_value = PYS()->getOption( $value_percent_option, 100 );

                $params['value']    = getWooEventValueProducts($value_option,$global_value,$percents_value,$value,$event->args);

            }
            $dyn_remarketing = array(
                'product_id'  => $product_ids,
                'page_type'   => $page_type,
                'total_value' => $value,
            );
	        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	        if(!empty($dyn_remarketing)){
		        $params = array_merge( $params, $dyn_remarketing );
	        }
        }


        if($event->getId() == 'woo_initiate_checkout_progress_f'
            || $event->getId() == 'woo_initiate_checkout_progress_l'
            || $event->getId() == 'woo_initiate_checkout_progress_e'
            || $event->getId() == 'woo_initiate_checkout_progress_o'
        ) {
            $params["shipping"] = isset($event->args['shipping']) ? $event->args['shipping'] : '';
        }

        return $params;
    }


    private function getEddViewContentEventParams() {
        global $post;

        if ( ! $this->getOption( 'edd_view_content_enabled' ) ) {
            return false;
        }
        $value_enabled = PYS()->getOption( 'edd_view_content_value_enabled' );
        $value_option = PYS()->getOption('edd_view_content_value_option');
        $percents_value = PYS()->getOption('edd_view_content_value_percent', 100);
        $global_value = PYS()->getOption('edd_view_content_value_global', 0);
        $total_value = getEddDownloadPriceToDisplay( $post->ID );
        $params = array(
            'event_category'  => 'ecommerce',
            'currency' => edd_get_currency(),
            'items'           => array(
                array(
                    'item_id'       => Helpers\getEddDownloadContentId($post->ID),
                    'item_name'     => $post->post_title,
                    'category' => implode( '/', getObjectTerms( 'download_category', $post->ID ) ),
                    'quantity' => 1,
                    'price'    => $total_value,
                    'affiliation' => PYS_SHOP_NAME
                ),
            ),
        );
        if($value_enabled){
            $params['value'] = getEddEventValue( $value_option, $total_value, $global_value, $percents_value );
        }
        $dyn_remarketing = array(
            'product_id'  => Helpers\getEddDownloadContentId($post->ID),
            'page_type'   => 'product',
            'total_value' => $total_value,
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }

        return array(
            'name'  => 'view_item',
            'data'  => $params,
        );

    }

    private function getEddAddToCartOnButtonClickEventParams( $download_id ) {

        // maybe extract download price id
        if ( strpos( $download_id, '_') !== false ) {
            list( $download_id, $price_index ) = explode( '_', $download_id );
        } else {
            $price_index = null;
        }

        $download_post = get_post( $download_id );
        $total = getEddDownloadPriceToDisplay( $download_id, $price_index );
        $params = array(
            'event_category'  => 'ecommerce',
            'currency' => edd_get_currency(),
            'items'           => array(
                array(
                    'item_id'       => Helpers\getEddDownloadContentId($download_id),
                    'item_name'     => $download_post->post_title,
                    'category' => implode( '/', getObjectTerms( 'download_category', $download_id ) ),
                    'quantity' => 1,
                    'price'    => $total,
                    'affiliation' => PYS_SHOP_NAME
                ),
            ),
        );
        if ( PYS()->getOption( 'edd_add_to_cart_value_enabled' ) ) {

            $value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
            $percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
            $global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );

            $params['value'] = getEddEventValue( $value_option, $total, $global_value, $percents_value );
        }
        $dyn_remarketing = array(
            'product_id'  => Helpers\getEddDownloadContentId($download_id),
            'page_type'   => 'cart',
            'total_value' => $total
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }

        return $params;

    }

    /**
     * @param SingleEvent $event
     * @return bool
     */
    private function setEddCartEventParams(&$event) {

        $data = [];
        $params = [
            'event_category' => 'ecommerce',
        ];
        $value_enabled = false;
        switch($event->getId()) {

            case 'edd_add_to_cart_on_checkout_page': {
                if( !$this->getOption( 'edd_add_to_cart_enabled' ) ) return false;
                $data['name'] = 'add_to_cart';

                $value_enabled  = PYS()->getOption( 'edd_add_to_cart_value_enabled' );
                $value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
                $percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );
            }break;
            case 'edd_initiate_checkout': {
                if( !$this->getOption( 'edd_initiate_checkout_enabled' ) ) return false;
                $data['name'] = 'begin_checkout';

                $value_enabled  = PYS()->getOption( 'edd_initiate_checkout_value_enabled' );
                $value_option   = PYS()->getOption( 'edd_initiate_checkout_value_option' );
                $percents_value = PYS()->getOption( 'edd_initiate_checkout_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_initiate_checkout_value_global', 0 );
            }break;
            case 'edd_purchase': {
                if( !$this->getOption( 'edd_purchase_enabled' ) ) return false;
                $data['name'] = 'purchase';
                $params['coupon'] = $event->args['coupon'];
                $params['transaction_id'] = eddMapOrderId($event->args['order_id']);
                $params['currency'] = edd_get_currency();
                if($this->getOption('edd_purchase_new_customer') && (is_user_logged_in() || PYS()->getOption('edd_purchase_new_customer_guest') === 'yes')){
                    $params['new_customer'] = $event->args['new_customer'];
                }


                $google_consent_mode = PYS()->getOption('google_consent_mode');

				if ( isset( $_REQUEST[ 'cm_analytics_storage' ] ) ) {
					$params[ 'analytics_storage' ] = sanitize_text_field( $_REQUEST[ 'cm_analytics_storage' ] );
				} elseif ( $google_consent_mode ) {
					$params[ 'analytics_storage' ] = 'granted';
				}
				if ( isset( $_REQUEST[ 'cm_ad_storage' ] ) ) {
					$params[ 'ad_storage' ] = sanitize_text_field( $_REQUEST[ 'cm_ad_storage' ] );
				} elseif ( $google_consent_mode ) {
					$params[ 'ad_storage' ] = 'granted';
				}
				if ( isset( $_REQUEST[ 'cm_ad_user_data' ] ) ) {
					$params[ 'ad_user_data' ] = sanitize_text_field( $_REQUEST[ 'cm_ad_user_data' ] );
				} elseif ( $google_consent_mode ) {
					$params[ 'ad_user_data' ] = 'granted';
				}
				if ( isset( $_REQUEST[ 'cm_ad_personalization' ] ) ) {
					$params[ 'ad_personalization' ] = sanitize_text_field( $_REQUEST[ 'cm_ad_personalization' ] );
				} elseif ( $google_consent_mode ) {
					$params[ 'ad_personalization' ] = 'granted';
				}

                $value_enabled  = PYS()->getOption( 'edd_purchase_value_enabled', true );
                $value_option   = PYS()->getOption( 'edd_purchase_value_option' );
                $percents_value = PYS()->getOption( 'edd_purchase_value_percent', 100 );
                $global_value   = PYS()->getOption( 'edd_purchase_value_global', 0 );
            }break;
            case 'edd_refund': {
                $data['name'] = 'refund';
                $params['transaction_id'] = eddMapOrderId($event->args['order_id']);
                $params['currency'] = edd_get_currency();
            }break;
            case 'edd_frequent_shopper': {
                if( !$this->getOption( $event->getId() . '_enabled' ) ) return false;
                $data['name'] = 'FrequentShopper';
            }break;
            case 'edd_vip_client': {
                if( !$this->getOption( $event->getId() . '_enabled' ) ) return false;
                $data['name'] = 'VipClient';
            }break;
            case 'edd_big_whale': {
                if( !$this->getOption( $event->getId() . '_enabled' ) ) return false;
                $data['name'] = 'BigWhale';
            }break;
        }

        $items = array();
        $product_ids = array();
        $total = 0;
        $total_as_is = 0;
        $tax = 0;

        $include_tax = PYS()->getOption( 'edd_tax_option' ) == 'included';

        foreach ($event->args['products'] as $product) {
            $download_id   = (int) $product['product_id'];

            if ( in_array($event->getId(), array('edd_purchase','edd_refund','edd_frequent_shopper','edd_vip_client','edd_big_whale'))) {

                if ( $include_tax ) {
                    $price = $product['subtotal'] + $product['tax'] - $product['discount'];
                } else {
                    $price = $product['subtotal'] - $product['discount'];
                }
                $tax += $product['tax'];
                $total_as_is += $product['price'];
            } else {
                $price = getEddDownloadPriceToDisplay( $download_id,$product['price_index'] );
                $total_as_is += edd_get_cart_item_final_price( $product['cart_item_key']  );
            }
            $download_content_id = Helpers\getEddDownloadContentId($download_id);

            $items[] = array(
                'item_id'       => $download_content_id,
                'item_name'     => $product['name'],
                'category' => implode( '/', array_column( $product['categories'],'name') ),
                'quantity' => $product['quantity'],
                'price'    => $product['quantity'] > 0 ? pys_round($price / $product['quantity']) : $price,
                'affiliation' => PYS_SHOP_NAME
//				'variant'  => $variation_name,
            );
            $product_ids[] = $download_content_id;
            $total+=$price;
        }
        $params['items']=$items;

        //add fee
        $fee = isset($event->args['fee']) ? $event->args['fee'] : 0;
        $feeTax= isset($event->args['fee_tax']) ? $event->args['fee_tax'] : 0;
        if(PYS()->getOption( 'edd_event_value' ) == 'custom') {
            if(PYS()->getOption( 'edd_tax_option' ) == 'included') {
                $total += $fee + $feeTax;
            } else {
                $total += $fee;
            }
        } else {
            if(edd_prices_include_tax()) {
                $total_as_is += $fee + $feeTax;
            } else {
                $total_as_is += $fee;
            }
        }

        $tax += $feeTax;

        if(($event->getId() == 'edd_purchase' || $event->getId() == 'edd_refund') && PYS()->getOption( 'edd_purchase_value_enabled', true )) {
            if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
                $params['value']    = getEddEventValue( $value_option, $total, $global_value, $percents_value );
            } else {
                $params['value']    = getEddEventValue( $value_option, $total_as_is, $global_value, $percents_value );
            }
            $params['tax'] = $tax;
        } else {
            if ( $value_enabled ) {
                $params['value']    = getEddEventValue( $value_option, $total, $global_value, $percents_value );
            }
        }

        if ( $event->getId() == 'edd_add_to_cart_on_checkout_page' ) {
            $page_type = 'cart';
        } elseif ( $event->getId() == 'edd_initiate_checkout' ) {
            $page_type = 'checkout';
        }elseif ( $event->getId() == 'edd_refund' ) {
            $page_type = 'refund';
        } else {
            $page_type = 'purchase';
        }



        //DynamicRemarketing
        $dyn_remarketing = array(
            'product_id'  => $product_ids,
            'page_type'   => $page_type,
            'total_value' => $total,
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }

        // add all
        $event->addPayload($data);
        $event->addParams($params);

        return true;
    }

    private function getEddCartEventParams( $context = 'add_to_cart' ) {

	    $params['currency'] = edd_get_currency();



        return array(
            'name' => $context,
            'data' => $params,
        );

    }

    private function getEddRemoveFromCartParams( $cart_item ) {

        if ( ! $this->getOption( 'edd_remove_from_cart_enabled' ) ) {
            return false;
        }

        $download_id = $cart_item['id'];
        $download_post = get_post( $download_id );

        $price_index = ! empty( $cart_item['options'] ) && !empty($cart_item['options']['price_id']) ? $cart_item['options']['price_id'] : null;

        return array(
            'name' => 'remove_from_cart',
            'data' => array(
                'event_category'  => 'ecommerce',
                'currency'        => edd_get_currency(),
                'items'           => array(
                    array(
                        'item_id'       => Helpers\getEddDownloadContentId($download_id),
                        'item_name'     => $download_post->post_title,
                        'category' => implode( '/', getObjectTerms( 'download_category', $download_id ) ),
                        'quantity' => $cart_item['quantity'],
                        'price'    => getEddDownloadPriceToDisplay( $download_id, $price_index ),
                        'affiliation' => PYS_SHOP_NAME
//						'variant'  => $variation_name,
                    ),
                ),
            ),
        );

    }

    private function getEddViewCategoryEventParams() {
        global $posts;

        if ( ! $this->getOption( 'edd_view_category_enabled' ) ) {
            return false;
        }

        $term = get_term_by( 'slug', get_query_var( 'term' ), 'download_category' );
        if(!$term) return false;
        $parent_ids = get_ancestors( $term->term_id, 'download_category', 'taxonomy' );

        $download_categories = array();
        $download_categories[] = $term->name;

        foreach ( $parent_ids as $term_id ) {
            $parent_term = get_term_by( 'id', $term_id, 'download_category' );
            $download_categories[] = $parent_term->name;
        }

        $list_name = implode( '/', array_reverse( $download_categories ) );

        $items = array();
        $product_ids = array();
        $total_value = 0;

        for ( $i = 0; $i < count( $posts ) && $i < 10; $i ++ ) {

            $item = array(
                'item_id'            => Helpers\getEddDownloadContentId($posts[ $i ]->ID),
                'item_name'          => $posts[ $i ]->post_title,
                'category'      => implode( '/', getObjectTerms( 'download_category', $posts[ $i ]->ID ) ),
                'quantity'      => 1,
                'price'         => getEddDownloadPriceToDisplay( $posts[ $i ]->ID ),
                'list'          => $list_name,
                'affiliation' => PYS_SHOP_NAME
            );

            $items[] = $item;
            $product_ids[] = $item['item_id'];
            $total_value += $item['price'];

        }

        $params = array(
            'event_category'  => 'ecommerce',
            'event_label'     => $list_name,
            'currency' => edd_get_currency(),
            'items'           => $items,
        );

	    $params['value'] = $total_value;

        $dyn_remarketing = array(
            'product_id'  => $product_ids,
            'page_type'   => 'category',
            'total_value' => $total_value,
        );
	    $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
	    if(!empty($dyn_remarketing)){
		    $params = array_merge( $params, $dyn_remarketing );
	    }

        return array(
            'name'  => 'view_item_list',
            'data'  => $params,
        );

    }


    public function getCategoryArrayWoo($contentID, $isVariant = false)
    {
        $category_array = array();

        // Get product categories based on whether it's a variant or simple product
        if ($isVariant) {
            $parent_product_id = wp_get_post_parent_id($contentID);
            $categories = getObjectTerms('product_cat', $parent_product_id);
        } else {
            $categories = getObjectTerms('product_cat', $contentID);
        }

        // Reverse categories to get parent categories first (GA4 hierarchy requirement)

        $category_index = 1;
        $max_categories = 5;

        // Build category array with proper GA4 naming convention
        foreach ($categories as $category_name) {
            if ($category_index > $max_categories) {
                break; // Limit to maximum 5 categories as per GA4 requirements
            }

            $key = ($category_index === 1) ? 'item_category' : 'item_category' . $category_index;
            $category_array[$key] = $category_name;
            $category_index++;
        }
        return $category_array;
    }

    public function getAllPixels($checkLang = true) {
        $pixels = $this->getPixelIDs();

        $pixels = array_filter($pixels, static function ($tag) {
            return preg_match( '/^GTM-[A-Z0-9]+$/', $tag );
        });
        $hide_pixels = apply_filters('hide_pixels', array());
        $pixels = array_filter($pixels, static function ($element) use ($hide_pixels) {
            return !in_array($element, $hide_pixels);
        });
        $pixels = array_values($pixels);
        return $pixels;
    }

    private function isGTM($tag) {
        return preg_match( '/^GTM-[A-Z0-9]+$/', $tag );
    }
    /**
     * @param PYSEvent $event
     * @return array|mixed|void
     */
	public function getAllPixelsForEvent( $event ) {

        $pixels = $main_pixel = array();

		if ( $this->getOption( 'main_pixel_enabled' ) ) {
            $main_pixel = $this->getPixelIDs();
			$pixels = array_filter( $main_pixel, static function ( $tag ) {
				return preg_match( '/^GTM-[A-Z0-9]+$/', $tag );
			} );
		}

		return $pixels;
	}
}

/**
 * @return GTM
 */
function GTM() {
    return GTM::instance();
}

GTM();