<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/google_analytics/function-helpers.php';
require_once PYS_PATH . '/modules/google_analytics/GA-logger.php';

use PixelYourSite\GA\Helpers;
use WC_Product;

require_once PYS_PATH . '/modules/google_analytics/function-collect-data-4v.php';

class GA extends Settings implements Pixel {

    private static $_instance;
    private $isEnabled;
    private $configured;

    private $googleBusinessVertical;
    private $checkout_step = 2;
    /** @var array $wooOrderParams Cached WooCommerce Purchase and AM events params */
    private $wooOrderParams = array();

    private $logger;

    private $onlyGA4event = ['woo_initiate_checkout_progress_o',
        'woo_initiate_checkout_progress_e',
        'woo_initiate_checkout_progress_l',
        'woo_initiate_checkout_progress_f'];

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    public function __construct() {

        parent::__construct( 'ga' );

        $this->locateOptions(
            PYS_PATH . '/modules/google_analytics/options_fields.json',
            PYS_PATH . '/modules/google_analytics/options_defaults.json'
        );

        add_action( 'pys_register_pixels', function( $core ) {
            /** @var PYS $core */
            $core->registerPixel( $this );
        } );

        $this->logger = new GA_logger();
        add_action( 'init', array(
            $this,
            'init'
        ), 1 );
    }

    public function enabled() {
        if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
            return true;
        } else {
            return $this->getOption( 'main_pixel_enabled' );
        }
    }

    public function init() {
        $this->isEnabled = $this->enabled() && $this->isServerApiEnabled();

        if($this->isEnabled) {
            add_action( 'woocommerce_checkout_update_order_meta',array($this,'saveGATagsInOrder'),1, 2);
            add_action( 'edd_complete_purchase',array($this,'saveGATagsInEddOrder'),1, 2);
        }

        $this->googleBusinessVertical = PYS()->getOption( 'google_retargeting_logic' ) === 'ecomm' ? 'retail' : 'custom';
        $this->logger->init();
    }

    public function configured() {

        $license_status = PYS()->getOption( 'license_status' );
        $pixel_id = $this->getAllPixels();
        $disabled = false;
        $main_pixel_enabled = $this->getOption( 'main_pixel_enabled' );

        if ( isSuperPackActive() && version_compare( SuperPack()->getPluginVersion(), '3.1.1.1', '>=' ) ) {
            $disabledPixel = apply_filters( 'pys_pixel_disabled', array(), $this->getSlug() );
            $disabled = in_array( '1', $disabledPixel ) && in_array( 'all', $disabledPixel );
            $main_pixel_enabled = true;
        }

        $this->configured = !empty( $license_status ) // license was activated before
            && count( $pixel_id ) > 0
            && $main_pixel_enabled
            && !$disabled;

        return $this->configured;
    }

    public function getPixelIDs() {

        if(EventsWcf()->isEnabled() && isWcfStep()) {
            $ids = $this->getOption( 'wcf_pixel_id' );
            if(!empty($ids))
                return [$ids];
        }

        if( isSuperPackActive()
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' ) )
        {
            if ( !$this->getOption( 'main_pixel_enabled' ) ) {
                return apply_filters( "pys_ga_ids", [] );
            }
        }

        $ids = (array) $this->getOption( 'tracking_id' );

        if(count($ids) == 0|| empty($ids[0])) {
            return apply_filters("pys_ga_ids",[]);
        } else {
            $id = array_shift($ids);
            return apply_filters("pys_ga_ids", array($id)); // return first id only
        }


    }

    public function getPixelDebugMode() {

        $flags = (array) $this->getOption( 'is_enable_debug_mode' );

        if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
            $additionalPixels = SuperPack()->getGaAdditionalPixel();
            $index = 1;
            foreach ($additionalPixels as $_pixel) {
                if(isset($_pixel->extensions['debug_mode']) && $_pixel->extensions['debug_mode'])
                {
                    $flags[] = 'index_'.$index;
                }
                $index++;
            }
            return $flags;
        } else {
            $firstFlag = reset($flags);
            // If flag is empty, false, or '0', return empty array to prevent debug mode from being enabled
            if (empty($firstFlag) || $firstFlag === false || $firstFlag === '0') {
                return [];
            }
            return (array) $firstFlag;
        }
    }
    public function getPixelAdditionalConfig() {
        $flags = array();
        $pixelIDs = $this->getPixelIDs();
        if (!empty($pixelIDs)) {
            $mainTag = (string) $this->getPixelIDs()[0];
            $flags[ $mainTag ]['first_party_collection'] = $this->getOption( 'first_party_collection' );
        }

        if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
            $additionalPixels = SuperPack()->getGaAdditionalPixel();
            $index = 1;
            foreach ($additionalPixels as $_pixel) {
                if(isset($_pixel->first_party_collection))
                {
                    $flags[$_pixel->pixel]['first_party_collection'] = $_pixel->first_party_collection;
                }
                $index++;
            }
            return $flags;
        } else {
            return (array) $flags; // return first id only
        }
    }
    public function getPixelServerContainerUrls() {
        $flags = array();
        $pixelIDs = $this->getPixelIDs();
        if (!empty($pixelIDs)) {
            $mainTag                                      = (string) $this->getPixelIDs()[0];
            $flags[ $mainTag ]['enable_server_container'] = (string) $this->getOption( 'enable_server_container' );
            $flags[ $mainTag ]['server_container_url']    = $this->getOption( 'server_container_url' ) ? (string) $this->getOption( 'server_container_url' )[0] : '';
            $flags[ $mainTag ]['transport_url']           = $this->getOption( 'transport_url' ) ? (string) $this->getOption( 'transport_url' )[0] : '';
        }

        if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
            $additionalPixels = SuperPack()->getGaAdditionalPixel();
            $index = 1;
            foreach ($additionalPixels as $_pixel) {
                if(isset($_pixel->enable_server_container) && (!empty($_pixel->server_container_url) || !empty($_pixel->transport_url)))
                {
                    $flags[$_pixel->pixel]['enable_server_container'] = $_pixel->enable_server_container;
                    $flags[$_pixel->pixel]['server_container_url'] = $_pixel->server_container_url;
                    $flags[$_pixel->pixel]['transport_url'] = $_pixel->transport_url;
                }
                $index++;
            }
            return $flags;
        } else {
            return (array) $flags; // return first id only
        }
    }


    public function getPixelOptions() {

        $url_passthrough_mode = $this->getOption( 'url_passthrough' );
        $url_passthrough_mode_filter_enabled = $this->getOption( 'url_passthrough_filter' );
        $url_passthrough_mode_filter = false;

        if ( $url_passthrough_mode_filter_enabled ) {
            $url_passthrough_mode_filter = has_filter( 'pys_url_passthrough_mode' );
        }

        $cm_url_passthrough_mode_filter = apply_filters( 'cm_url_passthrough_mode', array(
            'enabled' => false,
            'value' => $url_passthrough_mode
        ) );
        $url_passthrough_mode = $url_passthrough_mode_filter ? ( apply_filters( 'pys_url_passthrough_mode', $url_passthrough_mode ) ) : ( $cm_url_passthrough_mode_filter['enabled'] ? $cm_url_passthrough_mode_filter['value'] : $url_passthrough_mode );

        $options = array(
            'trackingIds'                       => $this->getAllPixels(),
            'retargetingLogic'                  => PYS()->getOption( 'google_retargeting_logic' ),
            'crossDomainEnabled'                => $this->getOption( 'cross_domain_enabled' ),
            'crossDomainAcceptIncoming'         => $this->getOption( 'cross_domain_accept_incoming' ),
            'crossDomainDomains'                => $this->getOption( 'cross_domain_domains' ),
            'wooVariableAsSimple'               => GATags()->getOption( 'woo_variable_as_simple' ),
            'isDebugEnabled'                    => $this->getPixelDebugMode(),
            'serverContainerUrls'               => $this->getPixelServerContainerUrls(),
            'additionalConfig'                  => $this->getPixelAdditionalConfig(),
            'disableAdvertisingFeatures'        => $this->getOption( 'disable_advertising_features' ),
            'disableAdvertisingPersonalization' => $this->getOption( 'disable_advertising_personalization' ),
            'url_passthrough'                   => $url_passthrough_mode,
            'url_passthrough_filter'            => $url_passthrough_mode_filter_enabled,
            'custom_page_view_event'            => $this->getOption( 'custom_page_view_event' ),
        );

        if(isSuperPackActive('3.3.1') && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'enable_hide_this_tag_by_tags' )){
            $options['hide_pixels'] = $this->getHideInfoPixels();
        }
        return $options;
    }

    /**
     * Create pixel event and fill it
     * @param SingleEvent $event
     */
    public function generateEvents($event) {
        if ( !$this->configured() ) {
            return [];
        }
        $pixelEvents = [];
        if(isSuperPackActive() && version_compare( SuperPack()->getPluginVersion(), '3.1.1.1', '>=' ))
        {
            $disabledPixel =  apply_filters( 'pys_pixel_disabled', array(), $this->getSlug() );
            if(is_array($disabledPixel) && (in_array('1', $disabledPixel) || in_array('all', $disabledPixel))) return [];
            $hide_pixels = apply_filters('hide_pixels', array());
            $disabledPixel = array_merge($disabledPixel, $hide_pixels);
        }
        else{
            $disabledPixel =  apply_filters( 'pys_pixel_disabled', array(), $this->getSlug() );
            if(in_array('1', $disabledPixel) || in_array('all', $disabledPixel)) return [];
        }


        if($event->getId() == 'woo_remove_from_cart') {
            $product_id = $event->args['item']['product_id'];
            add_filter('pys_conditional_post_id', function($id) use ($product_id) { return $product_id; });
        }

        $pixelIds = $this->getAllPixelsForEvent($event);

        if($event->getId() == 'woo_remove_from_cart') {
            remove_all_filters('pys_conditional_post_id');
        }

        if($event->getId() == 'custom_event'){
            $containsAW = false;
            $all_pixels = array_merge($this->getAllPixels(false), Ads()->getAllPixels(false));
            $preselectedPixel = array_filter((array) $event->args->ga_ads_pixel_id, static function ($element) use ($all_pixels) {
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

                if($event->args->ga_ads_conversion_label != NULL){
                    $conversion_label = $event->args->ga_ads_conversion_label;
                    $pixelIds = array_map(function($pixelId) use ($conversion_label) {
                        if (strpos($pixelId, "AW") === 0) {
                            return $pixelId . '/' . $conversion_label;
                        }
                        return $pixelId;
                    }, $pixelIds);
                }

                foreach ($pixelIds as $pixelId) {
                    if (strpos($pixelId, "AW") === 0) {
                        $containsAW = true;
                        break;
                    }
                }

                if ($containsAW) {
                    $event->addPayload(["unify" => true]);
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
        if(($isWooEventWithProducts || $isEddEventWithProducts) && isSuperPackActive('3.0.0')
            && SuperPack()->getOption( 'enabled' ) )
        {
            $main_pixel = $this->getPixelIDs();
            $pixels_for_filter = array();
            if ( !empty( $main_pixel ) ) {
                $main_pixel_options = $this->getOption('main_pixel');
                if (!empty($main_pixel_options) && isset($main_pixel_options[0])) {
                    $main_pixel_options = $this->normalizeSPOptions($main_pixel[0], $main_pixel_options[0]);
                } else {
                    $main_pixel_options = $this->normalizeSPOptions($main_pixel[0], '');
                }
                $pixels_for_filter[] = SuperPack\SPPixelId::fromArray($main_pixel_options);
            }

            if (PYS()->getOption('merge_ga_events') === 'yes' && Ads()->configured() && !$this->isOnlyGA4event($event->getId())){
                $event->addPayload(["unify" => true]);
                $main_pixel_ads = Ads()->getPixelIDs();
                if ( !empty( $main_pixel_ads ) ) {
                    $main_pixel_ads_options = Ads()->getOption('main_pixel');
                    if (!empty($main_pixel_ads_options) && isset($main_pixel_ads_options[0])) {
                        $main_pixel_ads_options = Ads()->normalizeSPOptions($main_pixel_ads[0], $main_pixel_ads_options[0]);
                    } else {
                        $main_pixel_ads_options = Ads()->normalizeSPOptions($main_pixel_ads[0], '');
                    }
                    $pixels_for_filter[] = SuperPack\SPPixelId::fromArray($main_pixel_ads_options);
                }
            }

            if(SuperPack()->getOption( 'additional_ids_enabled' )){
                $pixels_for_filter = array_merge($pixels_for_filter, SuperPack()->getGaAdditionalPixel());
                if(PYS()->getOption('merge_ga_events') === 'yes' && Ads()->configured() && !$this->isOnlyGA4event($event->getId())){
                    $pixels_for_filter = array_merge($pixels_for_filter, SuperPack()->getAdsAdditionalPixel());
                }
            }
            $array_products = array();
            foreach ($pixels_for_filter as $_pixel) {
                $filter = null;
                if(!$_pixel->isValidForEvent($event)|| $_pixel->pixel == $disabledPixel) continue;

                if($isWooEventWithProducts) {
                    $filter = $_pixel->getWooFilter();
                }
                if($isEddEventWithProducts) {
                    $filter = $_pixel->getEddFilter();
                }
                if($filter != null) {
                    $containsAllFilter = array_filter($filter, function($item) {
                        return $item['filter'] == "all";
                    });
                    if($containsAllFilter) {
                        $array_products[$_pixel->pixel] = $event->args['products'];
                    } else {
                        if($isWooEventWithProducts) {
                            $products = EventsWoo()->filterEventProductsBy($event,$filter,$_pixel);
                            $array_products[$_pixel->pixel] = $products;
                        }
                        if($isEddEventWithProducts) {
                            $products = EventsEdd()->filterEventProductsBy($event,$filter,$_pixel);
                            $array_products[$_pixel->pixel] = $products;
                        }
                    }
                } else {
                    $array_products[$_pixel->pixel] = $event->args['products'];
                }
            }

            $grouped_pixels = [];
            $processed = [];

            foreach ($array_products as $pixel => $products) {
                if (strpos($pixel, "AW") === 0) {
                    $pixel = Ads\Helpers\getConversionIDs($event->getId(), [$pixel])[0];
                }
                if (in_array($pixel, $processed)) {
                    continue;
                }
                $ids = array_column($products, 'product_id');

                $group = [$pixel];
                foreach ($array_products as $other_pixel => $other_products) {
                    if (strpos($other_pixel, "AW") === 0) {
                        $other_pixel = Ads\Helpers\getConversionIDs($event->getId(), [$other_pixel])[0];
                    }
                    if ($pixel !== $other_pixel && !in_array($other_pixel, $processed)) {
                        $other_ids = array_column($other_products, 'product_id');
                        if ($ids === $other_ids) {
                            $group[] = $other_pixel;
                            $processed[] = $other_pixel;
                        }
                    }
                }
                $grouped_pixels[] = $group;
                $processed[] = $pixel;
            }

            foreach ($grouped_pixels as $group) {

                $pixelEvent = clone $event;
                if(isset($array_products[$group[0]])) {
                    $pixelEvent->args['products'] = $array_products[$group[0]];
                    $pixelEvent->args['filter_product'] = true;
                } else{
                    $pixelEvent->args['products'] = array();
                    $pixelEvent->args['filter_product'] = true;
                }

                $pixelEvent->addPayload([ 'trackingIds' => $group ]);
                if($this->addParamsToEvent($pixelEvent)) {
                    $pixelEvents[] = $pixelEvent;
                }
            }
        } else {

            $all_events_with_products = [
                'edd_add_to_cart_on_checkout_page',
                'edd_add_to_cart_on_cart_page',
                'edd_add_to_cart_on_button_click',
                'edd_initiate_checkout',
                'edd_purchase',
                'edd_frequent_shopper',
                'edd_vip_client',
                'edd_big_whale',
                'edd_view_content',
                'edd_view_category',
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
                'woo_add_to_cart_on_button_click',
                'woo_view_content',
                'woo_view_category',
                'woo_view_item_list',
                'woo_view_item_list_single',
                'woo_view_item_list_search',
                'woo_view_item_list_shop',
                'woo_view_item_list_tag'
            ];

            $isEventWithProducts = in_array($event->getId(), $all_events_with_products);
            // if list of pixels are empty return empty array
            if(count($pixelIds) > 0) {
                $unify_trackingIds = $pixelIds;
                $pixelEvent = clone $event;
                if(PYS()->getOption('merge_ga_events') === 'yes' && Ads()->configured() && !$this->isOnlyGA4event($pixelEvent->getId()) && $pixelEvent->getId() != 'custom_event') {
                    $Ads_event = Ads()->generateEvents($pixelEvent);
                    if ($Ads_event) {
                        $pixelEvent->addPayload(["unify" => true]);
                        switch ($pixelEvent->getId()) {
                            case 'woo_purchase':
                            case 'woo_initiate_checkout':
                            case 'woo_view_content':
                            case 'woo_view_category':
                            case 'edd_purchase':
                            case 'edd_initiate_checkout':
                            case 'edd_view_content':
                            case 'edd_view_category':
                                if(Ads()->getOption( "{$pixelEvent->getId()}_conversion_track" ) != 'conversion'){

                                    $unify_trackingIds = array_merge($this->getAllPixelsForEvent($pixelEvent), Ads\Helpers\getConversionIDs($pixelEvent->getId(), Ads()->getAllPixelsForEvent( $pixelEvent )));
                                } else {
                                    $unify_trackingIds = array_merge($this->getAllPixelsForEvent($pixelEvent), Ads()->getAllPixelsForEvent($pixelEvent));
                                }
                                break;
                            case "woo_add_to_cart_on_cart_page":
                            case "woo_add_to_cart_on_checkout_page":
                            case "woo_add_to_cart_on_button_click":
                                if(Ads()->getOption( "woo_add_to_cart_conversion_track" ) != 'conversion'){
                                    $unify_trackingIds = array_merge($this->getAllPixelsForEvent($pixelEvent), Ads\Helpers\getConversionIDs('woo_add_to_cart',Ads()->getAllPixelsForEvent( $pixelEvent )));
                                } else {
                                    $unify_trackingIds = array_merge($this->getAllPixelsForEvent($pixelEvent), Ads()->getAllPixelsForEvent($pixelEvent));
                                }
                                break;
                            case "edd_add_to_cart_on_cart_page":
                            case "edd_add_to_cart_on_checkout_page":
                            case "edd_add_to_cart_on_button_click":
                                if(Ads()->getOption( "edd_add_to_cart_conversion_track" ) != 'conversion'){
                                    $unify_trackingIds = array_merge($this->getAllPixelsForEvent($pixelEvent), Ads\Helpers\getConversionIDs('edd_add_to_cart',Ads()->getAllPixelsForEvent( $pixelEvent )));
                                } else {
                                    $unify_trackingIds = array_merge($this->getAllPixelsForEvent($pixelEvent), Ads()->getAllPixelsForEvent($pixelEvent));
                                }
                                break;
                            default:
                                if ( Ads()->issetOption( $pixelEvent->getId() . '_conversion_labels' ) && Ads()->getOption( $pixelEvent->getId() . '_conversion_labels' )) {
                                    $unify_trackingIds = array_merge( $this->getAllPixelsForEvent( $pixelEvent ), Ads\Helpers\getConversionIDs( $pixelEvent->getId(), Ads()->getAllPixelsForEvent( $pixelEvent )) );
                                } else {
                                    $unify_trackingIds = array_merge( $this->getAllPixelsForEvent( $pixelEvent ), Ads()->getAllPixelsForEvent( $pixelEvent ) );
                                }
                        }

                        $pixelEvent->payload['trackingIds'] = $unify_trackingIds;
                    }
                }
                elseif (PYS()->getOption('merge_ga_events') === 'no' &&
                    Ads()->configured() &&
                    !$this->isOnlyGA4event($pixelEvent->getId()) &&
                    $pixelEvent->getId() !== 'custom_event' &&
                    !$isEventWithProducts
                ){
                    $pixelEvent->addPayload(["unify" => true]);
                    if ( Ads()->issetOption( $pixelEvent->getId() . '_conversion_labels' ) && Ads()->getOption( $pixelEvent->getId() . '_conversion_labels' )) {
                        $unify_trackingIds = array_merge( $this->getAllPixelsForEvent( $pixelEvent ), Ads\Helpers\getConversionIDs( $pixelEvent->getId(), Ads()->getAllPixelsForEvent( $pixelEvent )) );
                    } else {
                        $unify_trackingIds = array_merge( $this->getAllPixelsForEvent( $pixelEvent ), Ads()->getAllPixelsForEvent( $pixelEvent ) );
                    }
                    $pixelEvent->payload['trackingIds'] = $unify_trackingIds;
                }

                if($this->addParamsToEvent($pixelEvent)) {

                    $pixelEvents[] = $pixelEvent;
                }
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

        switch ($event->getId()) {
            case 'init_event':{
                if ( ! $this->getOption( 'custom_page_view_event' ) ) {
                    return;
                }
                $eventData = $this->getPageViewEventParams();
                if ( $eventData ) {
                    $isActive = true;
                    $this->addDataToEvent( $eventData, $event );
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
                    "search_term" =>  empty( $_GET['s'] ) ? null : sanitize_text_field($_GET['s']),

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
                    if ( !$this->getOption( 'woo_enable_list_category' ) || !PYS()->getOption( 'woo_view_item_list_enabled' ) ) return false;
                    $eventData = $this->getWooViewCategoryEventParams($event);
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;
            case 'woo_view_item_list_single':
                {
                    if ( !GA()->getOption( 'woo_enable_list_related' ) || !PYS()->getOption( 'woo_view_item_list_enabled' ) ) return false;
                    $eventData = $this->getWooViewItemListSingleParams();
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;
            case "woo_view_item_list_search":{
                if ( !$this->getOption( 'woo_enable_list_shop' ) || !PYS()->getOption( 'woo_view_item_list_enabled' ) ) return false;
                $eventData =  $this->getWooViewItemListSearch();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case "woo_view_item_list_shop":{
                if ( !$this->getOption( 'woo_enable_list_shop' ) || !PYS()->getOption( 'woo_view_item_list_enabled' ) ) return false;
                $eventData =  $this->getWooViewItemListShop();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case "woo_view_item_list_tag":{
                if ( !$this->getOption( 'woo_enable_list_tags' ) || !PYS()->getOption( 'woo_view_item_list_enabled' ) ) return false;
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
            case 'woo_refund':{
                $isActive =  $this->getWooRefundEventParams($event);
            }break;
            case 'woo_initiate_set_checkout_option':{
                $eventData =  $this->getWooSetCheckoutOptionEventParams();
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
                $isActive = ( PYS()->getOption( 'woo_view_item_list_enabled' ) && $this->getOption('woo_enable_list_category') ) ? $this->getWooSelectContent("category",$event) : false;break;
            case "woo_select_content_single":
                $isActive = ( PYS()->getOption( 'woo_view_item_list_enabled' ) && $this->getOption('woo_enable_list_related') ) || $this->getOption('woo_enable_list_shortcodes') ? $this->getWooSelectContent("single",$event) : false;break;
            case "woo_select_content_search":
                $isActive = ( PYS()->getOption( 'woo_view_item_list_enabled' ) && $this->getOption('woo_enable_list_shop') ) ? $this->getWooSelectContent("search",$event) : false;break;
            case "woo_select_content_shop":
                $isActive = ( PYS()->getOption( 'woo_view_item_list_enabled' ) && $this->getOption('woo_enable_list_shop') ) ? $this->getWooSelectContent("shop",$event) : false;break;
            case "woo_select_content_tag":
                $isActive = ( PYS()->getOption( 'woo_view_item_list_enabled' ) && $this->getOption('woo_enable_list_tag') ) ? $this->getWooSelectContent("tag",$event) : false;break;
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
            $clone_event = clone $event;
            if(
                PYS()->getOption('merge_ga_events') === 'yes' &&
                is_array($event->getPayloadValue('trackingIds')) &&
                !empty(array_filter($event->getPayloadValue('trackingIds'), function($id) { return strpos($id, 'AW-') === 0; })) &&
                Ads()->configured() &&
                !$this->isOnlyGA4event($event->getId())
            ){
                $Ads_event = Ads()->generateEvents($clone_event);

                if($Ads_event){
                    $filtered_params = array_diff_key($Ads_event[0]->params, ['items' => '']);
                    $par = array_merge($event->params, $filtered_params);

                    if((key_exists('items',$event->params) && !empty($event->params['items']) && is_array($event->params['items'])) && (key_exists('items',$Ads_event[0]->params) && !empty($Ads_event[0]->params['items'])  && is_array($Ads_event[0]->params['items']))) {
                        $merged_items = $event->params['items'];
                        foreach ($event->params['items'] as $key => $item) {
                            foreach ($Ads_event[0]->params['items'] as $ads_item) {
                                $ads_id = $ads_item['item_id'] ?? $ads_item['id'] ?? null;

                                if ($ads_id !== null && $item['item_id'] === $ads_id) {
                                    $merged_items[$key] = array_merge($item, $ads_item);
                                }
                            }
                        }
                        $par['items'] = $merged_items;
                    }
                    $event->addParams($par);
                }
            }

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
        $event->addParams($params);
        $event->addPayload($eventData);
    }

    public function getEventData( $eventType, $args = null ) {
        return false;
    }

    public function outputNoScriptEvents() {

        if ( ! $this->configured() || $this->getOption('disable_noscript')) {
            return;
        }

        $eventsManager = PYS()->getEventsManager();

        foreach ( $eventsManager->getStaticEvents( 'ga' ) as $eventName => $events ) {
            foreach ( $events as $event ) {
                foreach ( $this->getAllPixels() as $pixelID ) {
                    if (!$this->isGaV4($pixelID) || empty($pixelID)) {
                        continue; // Skipping non-GA4 (eg AW-1234567)
                    }

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
                            $args["pr" . ($key + 1) . "id"] = isset($item['item_id']) ? urlencode($item['item_id']) : '';
                            $args["pr" . ($key + 1) . "nm"] = isset($item['item_name']) ? urlencode($item['item_name']) : '';
                            $args["pr" . ($key + 1) . "pr"] = isset($item['price']) ? (float)$item['price'] : 0;
                            $args["pr" . ($key + 1) . "qt"] = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                            $args["pr" . ($key + 1) . "ca"] = isset($item['item_category']) ? urlencode($item['item_category']) : '';
                        }
                    }

                    $src = add_query_arg( $args, 'https://www.google-analytics.com/collect' );
                    $src = str_replace("[","%5B",$src);
                    $src = str_replace("]","%5D",$src);

                    // ALT tag used to pass ADA compliance
                    printf( '<noscript><img height="1" width="1" style="display: none;" src="%s" alt="google_analytics"></noscript>',
                        $src );

                    echo "\r\n";

                }
            }
        }

    }


    private function getPageViewEventParams() {

        return array(
            'name' => 'page_view',
            'data' => array(),
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
        $ga_action = $customEvent->getMergedAction();


        if ( ! $customEvent->isUnifyAnalyticsEnabled() || empty( $ga_action ) ) {
            return false;
        }


        $params = $customEvent->getMergedGaParams();

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
                $params = array_merge($params, $added_params);
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
            $params = array_merge($params, [
                'currency' => get_woocommerce_currency(),
                'value' => $value,
                'quantity' => $quantity,
            ]);
        }

        $customParams = $customEvent->getGAMergedCustomParams();
        foreach ($customParams as $item){
            $params[$item['name']]=$item['value'];
        }
        // SuperPack Dynamic Params feature
        $params = apply_filters( 'pys_superpack_dynamic_params', $params, 'ga' );
        if(isWooCommerceActive()) {
            $hasProductData = false;

            // Check if single product tracking is enabled and we're on a product page
            if ($customEvent->ga_ads_track_single_woo_data && is_product() && ($post->post_type === 'product' || $event->getPayloadValue('productId'))) {
                $product_id = $event->getPayloadValue('productId') ?? $post->ID;
                $quantity = $event->getPayloadValue('quantity') ?? 1;
                $eventData = $this->getWooViewContentEventParams(array('id' => $product_id, 'quantity' => $quantity), $event);

                $params = isset($eventData['data']) ? array_merge($params, $eventData['data']) : $params;
                $hasProductData = true;

            } elseif ($customEvent->ga_ads_track_cart_woo_data) {
                $order = EventsWoo()->getOrder();
                if ($order) {
                    // Handle purchase event with order data
                    $purchaseEvent = EventsWoo()->getEvent('woo_purchase');
                    if ($purchaseEvent) {
                        $purchaseParams = $this->getWooPurchaseParams($purchaseEvent, $order);
                        if($purchaseParams !== false) {
                            $params = array_merge($params, $purchaseParams);
                            $hasProductData = true;
                        }
                    }
                } else {
                    // Handle cart view event
                    $cartParams = $this->getWooEventViewCartParams($event);
                    $params = array_merge($params, $cartParams);
                    // Check if cart contains any items
                    $hasProductData = !empty($cartParams['items']);
                }
            }
            $pixelIds = $event->payload['trackingIds'] ?? [];
            // Only check merge_ga_events option when product data is present
            if($hasProductData && PYS()->getOption('merge_ga_events') === 'no'){
                $event->addPayload(["unify" => false]);
                // Filter out Google Ads tracking IDs (those starting with "AW")
                $pixelIds = array_filter($pixelIds, static function ($element) {
                    return strpos($element, "AW") !== 0;
                });

                $event->payload['trackingIds'] = $pixelIds;
            }
            else{
                $event->addPayload(["unify" => true]);
            }
        }


        return array(
            'name'  => $customEvent->getMergedAction(),
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

        $max_items = 10;
        $items = array();
        $processed = 0;

        for ( $i = 0; $i < count( $posts )&& $i < 10; $i ++ ) {

            if ( $posts[ $i ]->post_type !== 'product' ) {
                continue;
            }
            if ($processed >= $max_items) {
                break; // Stop processing once limit is reached
            }
            $item = array(
                'item_id'            => Helpers\getWooProductContentId($posts[ $i ]->ID),
                'item_name'          => $posts[ $i ]->post_title,
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $posts[ $i ]->ID ),
                'item_list_name'=> GA()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GA()->getOption('woo_track_item_list_id') ? $list_id : '',
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
            $processed++;
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
        $max_items = 10;
        $items = array();
        $processed = 0;

        foreach ( $posts as $i=>$post) {
            if( $post->post_type != 'product') continue;
            if ($processed >= $max_items) {
                break; // Stop processing once limit is reached
            }
            $item = array(
                'item_id'            => Helpers\getWooProductContentId($post->ID),
                'item_name'          => $post->post_title ,
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $post->ID ),
                'item_list_name'=> GA()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GA()->getOption('woo_track_item_list_id') ? $list_id : '',
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
            $processed++;
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
        $max_items = 10;
        $items = array();
        $processed = 0;

        foreach ( $posts as $post) {
            if( $post->post_type != 'product') continue;
            if ($processed >= $max_items) {
                break; // Stop processing once limit is reached
            }
            $item = array(
                'item_id'            => Helpers\getWooProductContentId($post->ID),
                'item_name'          => $post->post_title ,
                'quantity'      => 1,
                'price'         => getWooProductPriceToDisplay( $post->ID ),
                'item_list_name'=> GA()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GA()->getOption('woo_track_item_list_id') ? $list_id : '',
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
            $processed++;
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
                'item_list_name'=> GA()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id'  => GA()->getOption('woo_track_item_list_id') ? $list_id : '',
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
                'item_list_name'          => GA()->getOption('woo_track_item_list_name') ? $list_name : '',
                'item_list_id' => GA()->getOption('woo_track_item_list_id') ? $list_id : '',
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
        $category = $this->getCategoryArrayWoo($product_id);
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
            $category = $this->getCategoryArrayWoo($product_id);
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

        $dyn_remarketing = array(
            'product_id'  => $content_ids,
            'page_type'   => 'cart',
            'total_value' => $value,
        );
        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params = array_merge( $params, $dyn_remarketing );


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
        $category = $this->getCategoryArrayWoo($product_data['id']);
        if(!empty($category))
        {
            $params['items'][0] = array_merge($params['items'][0], $category);
        }
        $brand = getBrandForWooItem($content_id);
        if($brand)
        {
            $params['items'][0]['item_brand'] = $brand;
        }

        $dyn_remarketing = array(
            'product_id'  => $content_id,
            'page_type'   => 'product',
            'total_value' => $price,
        );

        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params = array_merge( $params, $dyn_remarketing );

        $event->addParams($params);

        $event->addPayload([
            'name'  => 'view_item',
            'delay' => 0,
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
        $unifyAds = false;
        $clone_event = clone $event;

        $value_option   = PYS()->getOption( 'woo_view_content_value_option' );
        $global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

        $valueArgs = [
            'valueOption' => $value_option,
            'global' => $global_value,
            'percent' => $percents_value,
        ];

        if (!$this->getOption('woo_view_content_enabled')) {
            return false;
        }
        if(PYS()->getOption('merge_ga_events') === 'yes' && !$this->isOnlyGA4event($event->getId()) && Ads()->configured() && Ads()->getAllPixelsForEvent($event)){
            $unifyAds = true;
        }
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
        if (GATags()->getOption('woo_variable_data_select_product') && !GATags()->getOption('woo_variable_as_simple')) {
            $variable_id = getVariableIdByAttributes($product);
        }

        $realProductID = $variable_id ?? $product->get_id();

        $productId = Helpers\getWooProductContentId($realProductID);

        $category = $this->getCategoryArrayWoo($realProductID, $product->is_type('variable'));
        $brand = getBrandForWooItem($realProductID);
        if (isset($_COOKIE['select_prod_list'])) {
            $productlist = json_decode(stripslashes($_COOKIE['select_prod_list']), true);
            if (isset($productlist['list_name']) && $this->getOption('woo_track_item_list_name')) {
                $item_list_name = sanitize_text_field($productlist['list_name']);
            }

            if (isset($productlist['list_id']) && $this->getOption('woo_track_item_list_id')) {
                $item_list_id = sanitize_text_field($productlist['list_id']);
            }
            $current_url = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] ?? parse_url(get_site_url(), PHP_URL_HOST) . $_SERVER['REQUEST_URI'];


            $productlist['url'] = $current_url;
            $_COOKIE['select_prod_list'] = json_encode($productlist);
        }

        $items = array();

// Add general product
        if (empty($variable_id)) {
            $valueArgs = array_merge($valueArgs, ['product_id' => $product->get_id(),'qty' => $quantity]);
            $general_item = array(
                'item_id' => $productId,
                'item_name' => $product->get_name(),
                'quantity' => $quantity,
                'price' => getWooProductValue($valueArgs),
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
        if ($product->is_type('variable') && !GATags()->getOption( 'woo_variable_as_simple' )) {
            $variations = $product->get_available_variations();

            foreach ($variations as $variation) {

                $variationProduct = wc_get_product($variation['variation_id']);
                if(!$variationProduct) continue;
                $variationProductId = Helpers\getWooProductContentId($variation['variation_id']);
                $valueArgsLoop = [
                    'valueOption' => $value_option,
                    'global' => $global_value,
                    'percent' => $percents_value,
                    'product_id' => $variationProduct->get_id(),
                    'qty' => $quantity
                ];
                $item = array(
                    'item_id'       => $variationProductId,
                    'item_name'     => GATags()->getOption('woo_variations_use_parent_name') ? $variationProduct->get_title() : $variationProduct->get_name(),
                    'quantity' => $quantity,
                    'price'    => getWooProductValue($valueArgsLoop),
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
        $valueArgsTotal = [
            'valueOption' => $value_option,
            'global' => $global_value,
            'percent' => $percents_value,
        ];
        $valueArgsTotal = array_merge($valueArgsTotal, ['product_id' => $variable_id ?? $product->get_id(),'qty' => $quantity]);
        $dyn_remarketing = array(
            'product_id'  => $productId,
            'page_type'   => 'product',
            'total_value' => getWooProductValue($valueArgsTotal),
        );

        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params = array_merge( $params, $dyn_remarketing );


        return array(
            'name'  => 'view_item',
            'data'  => $params,
            'delay' => 0,
            'unify' => $unifyAds
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
                $name = GATags()->getOption('woo_variations_use_parent_name') ? $childProduct->get_title() : $childProduct->get_name();
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
                $items[$product_key]['list_name'] = GA()->getOption('woo_track_item_list_name') ? sanitize_text_field($productlist['pys_list_name_productlist_name']) : '';
                $items[$product_key]['item_list_id'] = GA()->getOption('woo_track_item_list_id') ? sanitize_text_field($productlist['pys_list_name_productlist_id']) : '';

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
            'total_value' => getWooProductPriceToDisplay( $product_id, $quantity ,$customProductPrice),
        );

        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params = array_merge( $params, $dyn_remarketing );


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

        if ( $product && ! empty( $cart_item['variation_id'] ) ) {
            $variation = wc_get_product( (int) $cart_item['variation_id'] );
            if($variation && is_a($variation, 'WC_Product_Variation')) {
                $parentId = $variation->get_parent_id();
                $name = GATags()->getOption('woo_variations_use_parent_name') ? $variation->get_title() : $variation->get_name();
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
        if ($_product->is_type('variable') && !GATags()->getOption( 'woo_variable_as_simple' )) {
            $params['data']['items'][0]['variant'] = $variation_name;
        }
        $category = $this->getCategoryArrayWoo($product_id);
        if(!empty($category))
        {
            $params['data']['items'][0] = array_merge($params['data']['items'][0], $category);
        }
        $brand = getBrandForWooItem($product_id);
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

    private function getWooSetCheckoutOptionEventParams() {

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
        $params['items'] = $cartParams['items'];

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
                $params['coupon'] = $cartParams['coupon'];
                if( !empty($cartParams['shipping']) )
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
         if(PYS()->getOption( 'woo_purchase_on_transaction' ) && $order->get_meta( '_pys_ga_purchase_event_fired', true )){
            return false;
        }

        $params = $this->getWooPurchaseParams($event, $order, $product_ids);

        if($params === false) {
            return false;
        }

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

        if(isset($event->args['fees'])){
            $params['fees'] = (float) $event->args['fees'];
        }


        $event->addParams($params);
        $event->addPayload([
            'name' => 'purchase',
        ]);
        return true;
    }

    private function getWooPurchaseParams($event, $order, &$product_ids = [])
    {
        $items = array();
        $tax = 0;
        $value_option   = PYS()->getOption( 'woo_purchase_value_option' );
        $global_value   = PYS()->getOption( 'woo_purchase_value_global', 0 );
        $percents_value = PYS()->getOption( 'woo_purchase_value_percent', 100 );
        $withTax = 'incl' === get_option( 'woocommerce_tax_display_cart' );

        // Handle AWCDP (Deposits & Partial Payments) plugin integration
        // Check if AWCDP order is passed in event args
        $awcdp_order = null;
        if (isset($event->args['awcdp_order']) && $event->args['awcdp_order'] instanceof \AWCDP_Order) {
            $awcdp_order = $event->args['awcdp_order'];
        } elseif ($order instanceof \AWCDP_Order) {
            // Fallback: if order itself is AWCDP_Order
            $awcdp_order = $order;
        }

        // If we have an AWCDP order, get parent order for products
        if ($awcdp_order) {
            $parent_order_id = $awcdp_order->get_parent_id();
            if ($parent_order_id) {
                $parent_order = wc_get_order($parent_order_id);
                if ($parent_order) {
                    // Use parent order items instead of partial payment order items
                    $order = $parent_order;
                }
            }
        }

        $order_Items = $order->get_items();

        foreach ( $order_Items as $order_Item ) {
            $product = $order_Item->get_product();
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

            if (GATags()->getOption('woo_variable_as_simple') && $product->is_type('variation')) {
                $product = wc_get_product($product->get_parent_id());
            }
            if(!$product) continue;
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
                'item_name'     => GATags()->getOption('woo_variations_use_parent_name') && $product->is_type('variation') ? $product->get_title() : $product->get_name(),
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
            if(!empty($list_name) && GA()->getOption('woo_track_item_list_name'))
            {
                $item['item_list_name'] = $list_name;
            }
            $item_list_id = $order_Item->get_meta('item_list_id');
            if(!empty($item_list_id) && GA()->getOption('woo_track_item_list_id'))
            {
                $item['item_list_id'] = $item_list_id;
            }

            $category = $this->getCategoryArrayWoo($product_id, $product->is_type('variation'));
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

        // For AWCDP orders, use the partial payment amount instead of parent order total
        if ($awcdp_order) {
            $total_value = $awcdp_order->get_total();
            $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total_value,$event->args);
        } else {
            $total_value = getWooEventOrderTotal($event);
            $value = getWooEventValueProducts($value_option,$global_value,$percents_value,$total_value,$event->args);
        }

        if(PYS()->getOption('enable_CwCD')) {
            if(!empty(PYS()->getOption('aw_merchant_id'))){
                $event->addParams(array('aw_merchant_id'=> PYS()->getOption('aw_merchant_id') ));
            }
            if(!empty(PYS()->getOption('aw_feed_label'))){
                $event->addParams(array('aw_feed_label'=> PYS()->getOption('aw_feed_label') ));
            }
            if(!empty(PYS()->getOption('aw_feed_language'))){
                $event->addParams(array('aw_feed_language'=> PYS()->getOption('aw_feed_language') ));
            }
        }

        $params = array(
            'event_category' => 'ecommerce',
            'transaction_id' => wooMapOrderId( $event->args[ 'order_id' ] ),
            'value'          => $value,
            'currency'       => $event->args[ 'currency' ],
            'items'          => $items,
            'tax'            => pys_round( $tax ),
            'shipping'       => pys_round( $shipping_cost, 2 ),
            'shipping_tier'  => $event->args['shipping'],
            'payment_method' => $event->args['payment_method'] ?? '',
            'coupon'         => $event->args[ 'coupon_name' ],
        );

        $dyn_remarketing = array(
            'product_id'  => $product_ids,
            'page_type'   => 'purchase',
            'total_value' => $total_value,
        );

        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params = array_merge( $params, $dyn_remarketing );

        return $params;
    }
    private function getWooRefundEventParams(&$event) {
        if ( ! PYS()->getOption( 'woo_track_refunds_GA' ) || empty($event->args['order_id']) ) {
            return false;
        }

        $total_value = getWooEventOrderTotal($event);
        $params = array(
            'event_category'  => 'ecommerce',
            'transaction_id'  => wooMapOrderId($event->args['order_id']),
            'value'           => $total_value,
            'currency'        => $event->args['currency']
        );

        $event->addParams($params);
        $event->addPayload([
            'name' => 'refund',
        ]);

        return true;
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
                        if (!empty($cart_item['variation_id']) && !GATags()->getOption('woo_variable_as_simple')) {
                            return $product['product_id'] == $cart_item['variation_id'];
                        } else {
                            return $product['product_id'] == $cart_item['product_id'];
                        }
                    });
                    $event_has_product = !empty($filtered_products);
                }

                if(!$event_has_product) continue;
                $product = wc_get_product(!empty($cart_item['variation_id'] && !GATags()->getOption('woo_variable_as_simple')) ? $cart_item['variation_id'] : $cart_item['product_id']);

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
                        'item_name'     => GATags()->getOption('woo_variations_use_parent_name') && $product->is_type('variation') ? $product->get_title() : $product->get_name(),
                        'quantity' => $cart_item['quantity'],
                        'price'    => $cart_item['quantity'] > 0 ? pys_round($price / $cart_item['quantity']) : $price,
                        'affiliation' => PYS_SHOP_NAME
                    );

                    if (is_array($event->args) && $product && $product->is_type('variation')) {
                        foreach ($event->args['products'] as $event_product){
                            if($event_product['product_id'] == $product_id && !empty($event_product['variation_name']))
                            {
                                $item['variant'] = $event_product['variation_name'];
                            }

                        }
                    }
                    if (isset($cart_item['item_list_name']) && GA()->getOption('woo_track_item_list_name')) {
                        $item['item_list_name'] = $cart_item['item_list_name'];
                    }

                    if (isset($cart_item['item_list_id']) && GA()->getOption('woo_track_item_list_id')) {
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

        if(empty($items)) return []; // cart is empty

        if(is_array($event->args)){
            $params['coupon'] = isset($event->args['coupon']) ? $event->args['coupon'] : '';
        }
        $params = [
            'event_category' => 'ecommerce',
        ];
        $params['currency'] = get_woocommerce_currency();
        $params['value'] = getWooEventCartTotal($event);
        $params['items'] = $items;


        return $params;
    }
    /**
     * @param SingleEvent $event
     * @return array
     */
    private function getWooEventCartParams( $event ){
        $params = [
            'event_category' => 'ecommerce',
            'currency'        => get_woocommerce_currency(),
        ];
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
                            return $product['product_id'] == $cart_item['product_id'] || (isset($product['parent_id']) && $product['parent_id'] == $cart_item['product_id']);
                        }
                    });
                    $event_has_product = !empty($filtered_products);
                }

                if(!$event_has_product) continue;

                $product = wc_get_product(!empty($cart_item['variation_id'] && !GATags()->getOption('woo_variable_as_simple')) ? $cart_item['variation_id'] : $cart_item['product_id']);

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
                        'item_name'     => GATags()->getOption('woo_variations_use_parent_name') && $product->is_type('variation') ? $product->get_title() : $product->get_name(),
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
                    if (isset($cart_item['item_list_name']) && GA()->getOption('woo_track_item_list_name')) {
                        $item['item_list_name'] = $cart_item['item_list_name'];
                    }

                    if (isset($cart_item['item_list_id']) && GA()->getOption('woo_track_item_list_id')) {
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

        $params['items'] = $items;
        $params['coupon'] = isset($event->args['coupon']) ? $event->args['coupon'] : '';

        $total_value = getWooEventCartTotal($event);

        if($event->getId() == 'woo_add_to_cart_on_cart_page'
            || $event->getId() == 'woo_add_to_cart_on_checkout_page'
            || $event->getId() == 'woo_initiate_checkout'
        ) {
            if($event->getId() == 'woo_initiate_checkout') {
                $page_type = 'checkout';
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

                $params['value']    = getWooEventValueProducts($value_option,$global_value,$percents_value,$total_value,$event->args);

            }

            $dyn_remarketing = array(
                'product_id'  => $product_ids,
                'page_type'   => $page_type,
                'total_value' => $total_value,
            );

            $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
            $params = array_merge( $params, $dyn_remarketing );
        }


        if($event->getId() == 'woo_initiate_checkout_progress_f'
            || $event->getId() == 'woo_initiate_checkout_progress_l'
            || $event->getId() == 'woo_initiate_checkout_progress_e'
            || $event->getId() == 'woo_initiate_checkout_progress_o'
        ) {
            $params["shipping_tier"] = isset($event->args['shipping']) ? $event->args['shipping'] : '';
        }

        return $params;
    }


    private function getEddViewContentEventParams() {
        global $post;
        $download_id = $post->ID;
        if ( ! $this->getOption( 'edd_view_content_enabled' ) || empty( $download_id ) ) {
            return false;
        }
        if ( strpos( $download_id, '_') !== false ) {
            list( $download_id, $price_index ) = explode( '_', $download_id );
        } else {
            $price_index = null;
        }


        $total = getEddDownloadPriceToDisplay( $download_id, $price_index );
        $params = array(
            'event_category'  => 'ecommerce',
            'currency' => edd_get_currency(),
            'items'           => array(
                array(
                    'item_id'       => Helpers\getEddDownloadContentId($download_id),
                    'item_name'     => $post->post_title,
                    'category' => implode( '/', getObjectTerms( 'download_category', $download_id ) ),
                    'quantity' => 1,
                    'price'    => $total,
                    'affiliation' => PYS_SHOP_NAME
                ),
            ),
        );

        if(PYS()->getOption( 'edd_view_content_value_enabled' )){
            $value_option = PYS()->getOption('edd_view_content_value_option');
            $percents_value = PYS()->getOption('edd_view_content_value_percent', 100);
            $global_value = PYS()->getOption('edd_view_content_value_global', 0);

            $params['value'] = getEddEventValue( $value_option, $total, $global_value, $percents_value );
        }

        $dyn_remarketing = array(
            'product_id'  => Helpers\getEddDownloadContentId($download_id),
            'page_type'   => 'product',
            'total_value' => getEddDownloadPriceToDisplay( $total ),
        );

        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params = array_merge( $params, $dyn_remarketing );

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
            'total_value' => getEddDownloadPriceToDisplay( $download_id, $price_index )
        );

        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params          = array_merge( $params, $dyn_remarketing );

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

        if($event->getId() === 'edd_purchase' && PYS()->getOption( 'edd_purchase_on_transaction' ) && edd_get_payment_meta( $event->args['order_id'], '_pys_ga_purchase_event_fired', true )){
            return false;
        }

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
                $params['payment_method'] = $event->args['payment_method'] ?? '';

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
                if(!isset($product['subtotal']) || !isset($product['discount'])) {
                    $price = getEddDownloadPriceToDisplay( $download_id, $product['price_index'] );
                } else {
                    if ( $include_tax ) {
                        $price = $product['subtotal'] + $product['tax'] - $product['discount'];
                    } else {
                        $price = $product['subtotal'] - $product['discount'];
                    }
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
                $params['value']  = $total;
            } else {
                $params['value']  = $total_as_is;
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
        $params = array_merge( $params, $dyn_remarketing );

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

        $dyn_remarketing = array(
            'product_id'  => $product_ids,
            'page_type'   => 'category',
            'total_value' => $total_value,
        );

        $dyn_remarketing = Helpers\adaptDynamicRemarketingParams( $dyn_remarketing );
        $params = array_merge( $params, $dyn_remarketing );

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

        if(isSuperPackActive()
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' )
        ) {
            $additionalPixels = SuperPack()->getGaAdditionalPixel();
            foreach ($additionalPixels as $_pixel) {
                if($_pixel->isEnable
                    && (!$checkLang || $_pixel->isValidForCurrentLang())
                ) {

                    $pixels[]=$_pixel->pixel;
                }
            }
        }
        $pixels = array_filter($pixels, static function ($tag) {
            return strpos($tag, 'UA-') === false;
        });
        $hide_pixels = apply_filters('hide_pixels', array());
        $pixels = array_filter($pixels, static function ($element) use ($hide_pixels) {
            return !in_array($element, $hide_pixels);
        });
        $pixels = array_values($pixels);
        return $pixels;
    }

    private function isGaV4($tag) {
        return strpos($tag, 'G-') === 0;
    }
    /**
     * @param PYSEvent $event
     * @return array|mixed|void
     */
    public function getAllPixelsForEvent($event) {
        $pixels = $main_pixel = array();
        $ga4 = $this->isOnlyGA4event($event->getId());
        if($ga4)
        {
            if($this->isGaV4($this->getPixelIDs()[0])){
                $main_pixel = $this->getPixelIDs();
            }
        }
        else{
            $main_pixel = $this->getPixelIDs();
        }

        if(isSuperPackActive('3.0.0')
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' ))
        {
            if ( !empty( $main_pixel ) ) {
                $main_pixel_options = $this->getOption( 'main_pixel' );
                if ( !empty( $main_pixel_options ) && isset( $main_pixel_options[ 0 ] ) ) {
                    $main_pixel_options = $this->normalizeSPOptions( $main_pixel[ 0 ], $main_pixel_options[ 0 ] );
                } else {
                    $main_pixel_options = $this->normalizeSPOptions( $main_pixel[ 0 ], '' );
                }
                $pixel_options = SuperPack\SPPixelId::fromArray( $main_pixel_options );
                if ( $pixel_options->isValidForEvent( $event ) && $pixel_options->isConditionalValidForEvent( $event ) ) {
                    $pixels = array_merge( $pixels, $main_pixel );
                }
            }

            $additionalPixels = SuperPack()->getGaAdditionalPixel();
            foreach ($additionalPixels as $_pixel) {
                if($_pixel->isValidForEvent($event) && $_pixel->isConditionalValidForEvent($event, $this->getSlug())) {
                    if($ga4)
                    {
                        if($this->isGaV4($_pixel->pixel)){
                            $pixels[]=$_pixel->pixel;
                        }
                    }
                    else{
                        $pixels[]=$_pixel->pixel;
                    }
                }
            }
        } elseif ( $this->getOption( 'main_pixel_enabled' ) ) {
            $pixels = array_merge( $pixels, $main_pixel );
        }
        $pixels = array_filter($pixels, static function ($tag) {
            return strpos($tag, 'UA-') === false;
        });
        return $pixels;
    }
    public function isServerApiEnabled() {
        return $this->getOption("use_server_api");
    }
    public function getApiTokens() {

        $tokens = array();


        $pixelids = (array) $this->getOption( 'tracking_id' );
        if(count($pixelids) > 0 && $this->getOption('use_server_api')) {
            $serverids = (array) $this->getOption( 'server_access_api_token' );
            $tokens[$pixelids[0]] =  reset( $serverids );
        }


        if(isSuperPackActive('3.1.1')
            && SuperPack()->getOption( 'enabled' )
            && SuperPack()->getOption( 'additional_ids_enabled' )) {
            $additionalPixels = SuperPack()->getGaAdditionalPixel();
            foreach ($additionalPixels as $additionalPixel) {
                if($additionalPixel->isUseServerApi)
                {
                    $serverid = $additionalPixel->server_access_api_token;
                    $tokens[$additionalPixel->pixel] = $serverid;
                }
            }
        }

        return $tokens;
    }

    public function saveGATagsInOrder($order_id, $data) {

        $order = wc_get_order($order_id);

        // Skip saving cookies for orders created from admin panel
        if ($order && $order->get_created_via() === 'admin') {
            return;
        }

        if ( isWooCommerceVersionGte('3.0.0') ) {
            // WooCommerce >= 3.0
            if($order) {
                $ga_cookie = $order->get_meta( 'pys_ga_cookie', true);
                if(!$ga_cookie){
                    $pysData = GaServerEventHelper::parseGaCookies();
                    $order->update_meta_data("pys_ga_cookie",$pysData);
                    $order->save();
                }
            }

        } else {
            // WooCommerce < 3.0
            $ga_cookie = get_post_meta( $order_id, 'pys_ga_cookie', true);
            if(!$ga_cookie){
                $pysData = GaServerEventHelper::parseGaCookies();
                update_post_meta( $order_id, 'pys_ga_cookie', $pysData );
            }

        }

    }

    public function saveGATagsInEddOrder($order_id) {
        // Skip saving cookies for orders created from admin panel
        if (is_admin()) {
            return;
        }

        $ga_cookie = edd_get_order_meta( $order_id, 'pys_ga_cookie', true );
        if(!$ga_cookie){
            $pysData = GaServerEventHelper::parseGaCookies();
            edd_update_payment_meta( $order_id, 'pys_ga_cookie',$pysData );
        }
    }
    public function getLog() {
        return $this->logger;
    }

    public function isOnlyGA4event($event_name) {
        return in_array($event_name, $this->onlyGA4event);
    }
}

/**
 * @return GA
 */
function GA() {
    return GA::instance();
}

GA();